const WebSocket = require('ws')
const crypto = require("crypto")
const textDecoder = new TextDecoder('utf-8')

const server = new WebSocket.Server({ port: 8085 });
const people = new Map()

setInterval(() => {
  for (let [ userId, person ] of people) {
    if (person.ws.readyState === WebSocket.CLOSING || person.ws.readyState === WebSocket.CLOSED) {
      people.delete(userId)
    }
  }
}, 1000)

server.on('connection', (ws, req) => {
  const id = crypto.randomBytes(16).toString("hex")
  console.log(`Client connected: ${id}`);
  
  people.set(id, {
    id,
    ws,
    position: `0px`
  })

  // initialize client with what we know
  ws.send(JSON.stringify({ 
    type: 'init',
    people: [ ...people.values() ]
      .filter(p => p.id !== id) // everyone but ourself
      .map(p => ({ id: p.id, position: p.position })), // just the id and position from the top
    id
  }))

  // notify everyone but the joiner that someone joined
  for(let [userId, person] of people) {
    if (userId !== id) {
      person.ws.send(JSON.stringify({
        type: 'add-user',
        id: userId,
        position: `0px`
      }))
    }
  }

  // Handle incoming messages from clients
  ws.on('message', (dataArr) => {
    const asText = textDecoder.decode(dataArr)
    const data = JSON.parse(asText)

    let user
    switch (data.type) {
      case 'ping':
        // keep-alive
        // console.log('heartbeat', data)
        break
      case 'move':
        // scroll event
        // console.log('moving user', data)
        user = people.get(data.id)
        user.position = data.position
        people.set(data.id, user)
        for(let [userId, person] of people) {
          // notify everyone but the sender that someone moved
          if (userId !== id) {
            person.ws.send(JSON.stringify({
              type: 'move-user',
              id: data.id,
              position: data.position,
            }))
          }
        }
        break
    }
  });


  // prune on close
  ws.on('close', event => {
    console.log('Client disconnected', event);
    people.delete(id)
    for(let [ userId, person ] of people) {
      person.ws.send(JSON.stringify({
        type: 'remove-user',
        id: userId
      }))
    }
  });
});
