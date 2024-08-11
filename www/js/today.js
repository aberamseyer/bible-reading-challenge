// pretty much just https://github.com/Minishlink/web-push-php-example
document.addEventListener('DOMContentLoaded', async () => {
  const pushOptions = document.querySelectorAll('option[value=push], option[value=both]')
  if (!pushOptions) {
    return
  }
  else {
    pushOptions.forEach(el => el.dataset.textContent = el.textContent)
  }

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
    changeOptionsState('incompatible')
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
    const updateState = state => {
      pushOptions.forEach(el => {
        switch (state) {
          case 'enabled':
            el.disabled = false
            el.dataset.textContent = el.textContent
            break
          case 'disabled':
            el.disabled = false
            el.textContent = el.dataset.textContent
            break
          case 'computing':
            el.disabled = true
            el.textContent = 'Loading...'
            break
          case 'incompatible':
            el.disabled = true
            el.textContent = 'Notifications blocked'
            break
          default:
            console.error('Unhandled push button state', state)
            break
        }
      })
    }

    updateState(state)
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
        const result = Notification.requestPermission()
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
      try {
        // just in case something funny happened (like our VAPID keys changed)
        await push_unsubscribe()
      } catch (err) {
        console.err(err)
      }
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
        changeOptionsState('incompatible')
      } else {
        // A problem occurred with the subscription common reasons
        // include network errors or the user skipped the permission
        console.error('Impossible to subscribe to push notifications', e)
        changeOptionsState('disabled')
      }
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
  
  document.querySelector('select[name=change_subscription_type]')
  .addEventListener('change', async function(event) {
    if (event.target.value === 'email') {
      await push_unsubscribe()
    }
    else {
      await push_subscribe()
    }
    event.target.form.submit()
  })

})