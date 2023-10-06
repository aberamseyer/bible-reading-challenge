let ws = null
let heartbeatInterval = null
let initTime = null


function setup() {
  ws = new WebSocket(WS_URL)
  initTime = Date.now()
  ws.addEventListener('open', () => {
    console.log('Connected to server')
    let myId = Date.now() // will be overwritten on connection
    const people = new Map()
  
    ws.addEventListener('message', event => {
      const data = JSON.parse(event.data)
  
      function createUser(id, position) {
        let newUser = document.createElement('span')
        newUser.classList.add('mug')
        newUser.innerHTML = '😄'
        newUser.style.top = position
        newUser.setAttribute('data-id', id)
        document.querySelector('body').appendChild(newUser)
        return newUser
      }
    
      let user = null
      switch (data.type) {
        case 'init':
          console.log(`initializing with ${data.people.length} users`)
          myId = data.id
  
          people.clear()
          for(let person of data.people) {
            people.set(person.id, createUser(person.id, person.position))
          }
  
          function heartbeat () {
            ws.send(JSON.stringify({ 
              type: 'ping',
              id: myId,
              uptime: Math.floor((Date.now() - initTime) / 1000),
              timestamp: new Date()
            }))
          }
          heartbeatInterval = setInterval(heartbeat, 10000)
          heartbeat()
          break
        case 'add-user':
          // console.log('adding user', data)
          people.delete(data.id)
          people.set(data.id, createUser(data.id, data.position))
          break
        case 'move-user':
          // console.log('moving user', data)
          if (!people.has(data.id)) {
            people.set(data.id, createUser(data.id, data.position))
          }
          else {
            user = people.get(data.id)
            user.style.top = data.position
            people.set(data.id, user)
          }
          break
        case 'remove-user':
          // console.log('removing user', data)
          user = people.get(data.id)
          if (user) {
            user.remove()
            people.delete(data.id)
          }
          break
      }
      
    });
    
    ws.addEventListener('close', () => {
      console.log('Disconnected from server')
      people.clear()
      clearInterval(heartbeatInterval)
    })
  
    let timeout
    document.addEventListener('scroll', () => {
      if (timeout) {
        clearTimeout(timeout)
      }
      timeout = setTimeout(() => {
        ws.send(JSON.stringify({
          type: 'move',
          id: myId,
          position: `${getRelativeScrollPosition()}%` // how much we've scrolled down + half of viewport / total length of documnet
        }))
      }, 20)
    })
  })
}


setInterval(() => { 
  if (!ws || ws.readyState === WebSocket.CLOSED) {
    setup()
  }
}, 7500)
setup()

function getRelativeScrollPosition() {
  const scrollTop = window.scrollY || document.documentElement.scrollTop;
  const documentHeight = Math.max(
    document.body.scrollHeight,
    document.documentElement.scrollHeight
  );
  const windowHeight = window.innerHeight || document.documentElement.clientHeight;

  const scrollPercentage = (scrollTop / (documentHeight - windowHeight)) * 100;

  return scrollPercentage;
}