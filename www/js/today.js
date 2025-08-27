// pretty much just https://github.com/Minishlink/web-push-php-example
document.addEventListener('DOMContentLoaded', async () => {
  const headerForm = document.querySelector('#date-header form')
  const subscribeLabel = headerForm.querySelector('[data-push-label]')
  const subscribeInput = subscribeLabel.querySelector('input')

  if (!('serviceWorker' in navigator)) {
    console.warn('Service workers are not supported by this browser')
    changeOptionsState('incompatible')
    return
  }

  if (!('PushManager' in window)) {
    console.warn('Push notifications are not supported by this browser')
    changeOptionsState('incompatible')
    return
  }

  if (!('showNotification' in ServiceWorkerRegistration.prototype)) {
    console.warn('Notifications are not supported by this browser')
    changeOptionsState('incompatible')
    return
  }

  // Check the current Notification permission.
  // If its denied, the button should appears as such, until the user changes the permission manually
  if (Notification.permission === 'denied') {
    console.warn('Notifications are denied by the user')
    changeOptionsState('blocked')
    return
  }

  try {
    await navigator.serviceWorker.register(SERVICE_WORKER_FILE)
  } catch (e) {
    console.error('[SW] Service worker registration failed', e)
    changeOptionsState('incompatible')
  }
  console.log('[SW] Service worker has been registered')

  push_updateSubscription()

  function changeOptionsState(state) {
    switch (state) {
      case 'enabled':
      case 'disabled':
        subscribeInput.disabled = false
        subscribeLabel.childNodes[0].textContent = 'Push notifications '
        subscribeLabel.style.display = 'block'
        headerForm.style.width = '40rem'
        break
      case 'computing':
        subscribeInput.disabled = false
        subscribeLabel.childNodes[0].textContent = 'Loading... '
        subscribeLabel.style.display = 'block'
        headerForm.style.width = '34rem'
        break
      case 'incompatible':
        subscribeInput.disabled = true
        subscribeLabel.childNodes[0].textContent = 'Push incompatible '
        subscribeLabel.style.display = 'none'
        headerForm.style.width = '22rem'
        break
      case 'blocked':
        subscribeInput.disabled = true
        subscribeLabel.childNodes[0].textContent = 'Push blocked '
        subscribeLabel.style.display = 'block'
        headerForm.style.width = '36rem'
        break;
      default:
        console.error('Unhandled push button state', state)
        break
    }
  }

  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/')

    const rawData = window.atob(base64)
    const outputArray = new Uint8Array(rawData.length)

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i)
    }
    return outputArray
  }

  async function checkNotificationPermission() {
    if (Notification.permission === 'denied') {
      throw new Error('Push messages are blocked.')
    }

    if (Notification.permission === 'granted') {
      return
    }

    if (Notification.permission === 'default') {
      try {
        const result = await Notification.requestPermission()
        if (result !== 'granted') {
          throw new Error('Bad permission result')
        }
      } catch (e) {
        throw new Error('Unknown permission')
      }
    }
  }

  async function push_subscribe() {
    changeOptionsState('computing')

    try {
      await checkNotificationPermission()
      const serviceWorkerRegistration = await navigator.serviceWorker.ready
      const subscription = await serviceWorkerRegistration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBKEY),
      })
      // Subscription was successful
      // create subscription on your server
      await push_sendSubscriptionToServer(subscription, 'POST')
      changeOptionsState('enabled')
    } catch (e) {
      if (Notification.permission === 'denied') {
        // The user denied the notification permission which
        // means we failed to subscribe and the user will need
        // to manually change the notification permission to
        // subscribe to push messages
        console.warn('Notifications are denied by the user.')
        changeOptionsState('blocked')
      } else {
        // A problem occurred with the subscription. Common reasons
        // include network errors or the user skipped the permission
        console.error('Impossible to subscribe to push notifications', e)
        changeOptionsState('disabled')
      }
      subscribeInput.checked = false
    }
  }

  async function push_updateSubscription() {
    try {
      const serviceWorkerRegistration = await navigator.serviceWorker.ready
      const subscription = await serviceWorkerRegistration.pushManager.getSubscription()
      changeOptionsState('disabled')

      if (!subscription) {
        // We aren't subscribed to push, so leave UI in state to allow the user to enable push
        return
      }
      else {
        // we're already subscribed
        subscribeInput.checked = true
      }

      // Keep your server in sync with the latest endpoint
      await push_sendSubscriptionToServer(subscription, 'PUT')
      changeOptionsState('enabled')
    } catch (e) {
      console.error('Error when updating the subscription', e)
    }
  }

  async function push_unsubscribe() {
    changeOptionsState('computing')

    try {
      // To unsubscribe from push messaging, you need to get the subscription object
      const serviceWorkerRegistration = await navigator.serviceWorker.ready
      const subscription = await serviceWorkerRegistration.pushManager.getSubscription()
      // Check that we have a subscription to unsubscribe
      if (!subscription) {
        // No subscription object, so set the state
        // to allow the user to subscribe to push
        changeOptionsState('disabled')
        return
      }

      // We have a subscription, unsubscribe
      // Remove push subscription from server
      await push_sendSubscriptionToServer(subscription, 'DELETE')
      await subscription.unsubscribe()
    } catch (e) {
      // We failed to unsubscribe, this can lead to
      // an unusual state, so  it may be best to remove
      // the users data from your data store and
      // inform the user that you have done so
      console.error('Error when unsubscribing the user', e)
      changeOptionsState('disabled')
      push_sendSubscriptionToServer(subscription, 'DELETE')
    }

    changeOptionsState('disabled')
  }

  async function push_sendSubscriptionToServer(subscription, method) {
    const key = subscription.getKey('p256dh')
    const token = subscription.getKey('auth')
    const contentEncoding = (PushManager.supportedContentEncodings || ['aesgcm'])[0]

    return await fetch('/push/subscription', {
      method,
      body: JSON.stringify({
        endpoint: subscription.endpoint,
        publicKey: key ? btoa(String.fromCharCode.apply(null, new Uint8Array(key))) : null,
        authToken: token ? btoa(String.fromCharCode.apply(null, new Uint8Array(token))) : null,
        contentEncoding,
      })
    })
  }

  subscribeInput.addEventListener('change', function () {
    if (this.checked) { // this.checked is the new state
      push_subscribe()
    }
    else {
      push_unsubscribe()
    }
  })

  // listens for message from serviceworker to trigger reload
  navigator.serviceWorker.addEventListener('message', event => {
    if (event.data.action === 'refresh') {
      window.location.reload()
    }
  })
})

// iframe resizing and stuff for recovery version translation
function calculateIframeHeight(availableWidth, styles) {
  const {
    textLengths, fontSize, lineHeight,
    margins, padding,
    additionalElements
  } = styles;

  // Calculate approximate characters per line
  const avgCharWidth = fontSize * 0.5; // Approximate character width
  const contentWidth = availableWidth - (margins.left + margins.right + padding.left + padding.right);
  const charsPerLine = Math.floor(contentWidth / avgCharWidth);
  const vertSpace = padding.top + padding.bottom + margins.top + margins.bottom;

  // Calculate text height for each verse
  const textHeight = textLengths.reduce((acc, currLength) => {
    const numberOfLines = Math.ceil((currLength + 10) / charsPerLine); // 10 is an estimate for the length of the verse reference
    return acc + (numberOfLines * lineHeight) + vertSpace;
  });

  // Add heights of other elements
  const otherElementsHeight = additionalElements.reduce((sum, el) => sum + el.height, 0);

  // Calculate total height including margins, padding, and fudge factor
  let fudge = 5;
  if (contentWidth < 350)
    fudge = 20;
  if (contentWidth < 340)
    fudge = 30;

  const totalHeight = textHeight + otherElementsHeight + fudge * vertSpace;

  return totalHeight;
}

function adjustIFrame(iframe) {
  const container = iframe.closest('.iframe-container');
  const availableWidth = container.clientWidth;

  const styles = {
    textLengths: JSON.parse(container.dataset.textLengths), // Number of characters in each verse
    fontSize: 22, // in pixels
    lineHeight: 19.2, // in pixels
    margins: { top: 22, right: 0, bottom: 22, left: 0 },
    padding: { top: 0, right: 0, bottom: 0, left: 0 },
    additionalElements: []
  };

  iframe.style.height = calculateIframeHeight(availableWidth, styles) + 'px';
}
function adjustIFrames() {
  document.querySelectorAll('iframe').forEach(el => adjustIFrame(el))
}
document.querySelectorAll('iframe')
  .forEach(el => el.addEventListener('load', e => adjustIFrame(e.target)))
window.addEventListener('resize', adjustIFrames)


const tabs = document.querySelector('.tabs');
if (tabs) {
  tabs.addEventListener('change', e => {
    localStorage.setItem('activeTabId', e.target.id)
    adjustIFrames()
  })

  // set active tab on page load
  const activeTabId = localStorage.getItem('activeTabId')
  const activeTabEl = document.getElementById(activeTabId)
  if (activeTabId && activeTabEl) {
    document.querySelectorAll('[name=tabs]').forEach(x => x.checked = false)
    activeTabEl.checked = true
  }
}
