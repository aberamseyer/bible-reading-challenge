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

self.addEventListener('notificationclick', (event) => {
  event.notification.close()

  const notificationUrl = new URL(event.notification.data.link)
  const originAndPath = url => url.origin + url.pathname

  event.waitUntil(
    clients.matchAll({ 
      type: 'window', 
      includeUncontrolled: true 
    })
    .then(clientList => {
      const matchingClient = clientList.find(client => 
        originAndPath(notificationUrl) === originAndPath(new URL(client.url)))

      if (matchingClient) {
        matchingClient.focus()
        matchingClient.postMessage({ navigate: notificationUrl.href })
      } else {
        clients.openWindow(urlToOpen)
      }
    })
  )
})
