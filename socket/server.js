const WebSocket = require('ws')
const crypto = require("crypto")
const textDecoder = new TextDecoder('utf-8')
const process = require('process')
const sqlite3 = require('sqlite3')

const PORT = process.env.SOCKET_PORT || 8085

const db = new sqlite3.Database(`${__dirname}/../brc.db`, sqlite3.OPEN_READONLY)
const server = new WebSocket.Server({ port: PORT });
console.log(`server listening on port ${PORT}`)

// holds all the active socket connections
const people = new Map()

function getSessId(req) {
  const cookies = req.headers.cookie.split('; ')
  for(let cookie of cookies) {
    const [ key, value ] = cookie.split('=')
    if (key === 'brc-sessid') {
      return value
    }
  }
  return false
}

function removeUser(connectionId) {
  people.delete(connectionId)
  broadcast({
      type: 'remove-user',
      id: connectionId
    })
}

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

function addUser(ws, connectionId, user) {
  console.log(`Client connected: ${connectionId}`);

  const newUser = {
    id: connectionId,
    ws,
    position: `0px`,
    emoji: user.emoji,
    name: user.name
  }
  people.set(connectionId, newUser)

  // initialize client with what we know
  ws.send(JSON.stringify({
    type: 'init',
    id: connectionId,
    people: [ ...people.values() ]
      .filter(p => p.id !== connectionId) // everyone but ourself
      .map(p => ({ 
        id: p.id,
        position: p.position,
        emoji: p.emoji,
        name: p.name
      })) // just the id and position from the top
  }))

  // notify everyone but the joiner that someone joined
  broadcast({
      type: 'add-user',
      id: connectionId,
      position: `0px`,
      emoji: user.emoji,
      name: user.name
    }, connectionId)

  return newUser
}

server.on('connection', (ws, req) => {
  const connectionId = crypto.randomBytes(16).toString("hex")
  const sessionId = getSessId(req)
  if (!sessionId) {
    ws.close()
    return
  }
  
  function setupListeners(connectionId, userRow) {
    addUser(ws, connectionId, userRow)
    
    // Handle incoming messages from clients
    ws.on('message', dataArr => {
      const asText = textDecoder.decode(dataArr)
      const data = JSON.parse(asText)
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
            ws.close()
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
    ws.on('close', event => {
      console.log('Client disconnected', event);
      removeUser(connectionId)
    })
  }

  db.get(`SELECT data FROM sessions WHERE id = ?`, [ sessionId ], (err, row) => {
    if (!row) {
      ws.close()
      return
    }

    const userIdMatches = /my_id\|i\:(\d+);/g.exec(row.data)

    if (!userIdMatches) {
      ws.close()
      return
    }

    db.get(`SELECT id, name, emoji FROM users WHERE id = ?`, [ userIdMatches[1] ], (err, row) => {
      setupListeners(connectionId, row)
    })
  })

})

setInterval(() => {
  for (let [ userId, person ] of people) {
    if ([WebSocket.CLOSED, WebSocket.CLOSING ].includes(person.ws.readyState)) {
      removeUser(userId)
    }
  }
}, 5000)