self.addEventListener('push', function (event) {
  if (!(self.Notification && self.Notification.permission === 'granted')) {
    return
  }

  if (event.data) {
    const payload = event.data.json()
    event.waitUntil(
      self.registration.showNotification(payload.title, payload.options)
    )
  }
})

self.addEventListener('notificationclick', function(event) {
  const clickedNotification = event.notification
  clickedNotification.close()

  const data = clickedNotification.data

  // Do something as the result of the notification click
  const urlToOpen = new URL(data.link, self.location.origin).href;

  const promiseChain = clients.matchAll({
    type: 'window',
    includeUncontrolled: true
  })
  .then(windowClients => {
    let matchingClient = null;
    for (let i = 0; i < windowClients.length; i++) {
      const windowClient = windowClients[i]
      if (windowClient.url === urlToOpen) {
        matchingClient = windowClient
        break
      }
    }

    if (matchingClient) {
      return matchingClient.focus()
    } else {
      return clients.openWindow(urlToOpen)
    }
  });

  event.waitUntil(promiseChain);
})