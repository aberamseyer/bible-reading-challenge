const WebSocket = require('ws')
const crypto = require("crypto")
const process = require('process')
const sqlite3 = require('sqlite3')

const PORT = process.env.SOCKET_PORT || 8085

const db = new sqlite3.Database(`${__dirname}/../brc.db`, sqlite3.OPEN_READONLY)
const server = new WebSocket.Server({ port: PORT });
console.log(`server listening on port ${PORT}`)

// holds all the active socket connections
const people = new Map()

function removeUser(connectionId) {
  people.delete(connectionId)
  broadcast({
      type: 'remove-user',
      id: connectionId
    })
}

// broadcasts to all websockets (optionally, except one) of an event
function broadcast (obj, exceptId) {
  for(let [userId, person] of people) {
    if (exceptId) {
      if (userId !== exceptId) {
        person.ws.send(JSON.stringify(obj))
      }
    }
    else {
      person.ws.send(JSON.stringify(obj))
    }
  }
}

server.on('connection', ws => {
  const connectionId = crypto.randomBytes(16).toString("hex")
  console.log(`Client connected: ${connectionId}`);
  
  function setupListeners(connectionId, userRow) {
    const newUser = {
      id: connectionId,
      ws,
      position: `0px`,
      emoji: userRow.emoji,
      name: userRow.name
    }
    people.set(connectionId, newUser)

    // initialize client with what we know
    ws.send(JSON.stringify({
      type: 'init-client',
      id: connectionId,
      people: [ ...people.values() ]
        .filter(p => p.id !== connectionId) // everyone but ourself
        .map(p => ({ 
          id: p.id,
          position: p.position,
          emoji: p.emoji,
          name: p.name
        }))
      }))

    broadcast({
        type: 'add-user',
        id: connectionId,
        position: `0px`,
        emoji: userRow.emoji,
        name: userRow.name
      }, connectionId)
    
    // Handle incoming messages from clients
    ws.addEventListener('message', event => {
      const data = JSON.parse(event.data)
      let user
  
      switch (data.type) {
        case 'ping':
          // keep-alive
          // console.log('heartbeat', data)
          break
        case 'get':
          // console.log('get', data)
          const userToSend = people.get(data.id)
          console.log(`requested ${data.id}, found: ${userToSend ? 1 : 0}`)
          if (user) {
            ws.send(JSON.stringify({
                type: 'add-user',
                id: data.id,
                position: userToSend.position,
                emoji: userToSend.emoji,
                name: userToSend.name
              }))
          }
          break
        case 'move':
          // scroll event
          // console.log('moving user', data)
          user = people.get(data.id)
          if (!user) {
            ws.terminate()
            return
          }
          user.position = data.position
          people.set(data.id, user)
          broadcast({
              type: 'move-user',
              id: data.id,
              position: data.position,
            }, connectionId)
          break
      }
    });
  
  
    // prune on close
    ws.addEventListener('close', event => {
      console.log(`Client disconnected`);
      removeUser(connectionId)
    }, { once: true })
  }

  ws.addEventListener('message', event => {
    const [ init, nonce ] = event.data.split('|')
    if (init === 'init' && nonce) {
      db.get(`SELECT id, name, emoji FROM users WHERE websocket_nonce = ?`, [ nonce ], (err, row) => {
        if (row) {
          setupListeners(connectionId, row)
        }
        else {
          ws.terminate()
        }
      })
    }
    else {
      console.log(`invalid initialization code: ${event.data}`)
      ws.terminate()
    }
  }, { 'once': true })
})

setInterval(() => {
  for (let [ userId, person ] of people) {
    if ([WebSocket.CLOSED, WebSocket.CLOSING ].includes(person.ws.readyState)) {
      removeUser(userId)
    }
  }
}, 5000)