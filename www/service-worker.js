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

  const urlToOpen = new URL(event.notification.data.link, self.location.origin).href

  event.waitUntil(
    clients.matchAll({ 
      type: 'window', 
      includeUncontrolled: true 
    })
    .then(clientList => {
      const matchingClient = clientList.find(client => client.url === urlToOpen)

      if (matchingClient) {
        matchingClient.focus()
        matchingClient.postMessage({ action: 'refresh' })
      } else {
        clients.openWindow(urlToOpen)
      }
    })
  )
})
