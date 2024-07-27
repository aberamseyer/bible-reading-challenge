const WebSocket = require('ws')
const crypto = require("crypto")
const process = require('process')
const sqlite3 = require('sqlite3')
const redis = require('redis');

const PORT = process.env.SOCKET_PORT || 8085

// initialize connections to databases and server
Promise.all([
  new Promise(res => {
    const redisClient = redis.createClient({
      host: '127.0.0.1',
      port: '6379',
    })
    redisClient
      .on('error', err => console.error(`Redis threw:`, err))
      .on('ready', () => {
        console.log(`Redis initialized`)
        res(redisClient)
      })
      .connect()
  }),
  new Promise(res => {
    const db = new sqlite3.Database(`${__dirname}/../brc.db`, sqlite3.OPEN_READONLY)
    db
      .on('error', err => console.err(`SQLite3 threw:`, err))
      .on('open', () => {
        console.log('SQLite initialized')
        res(db)
      })
  }),
  new Promise(res => {
    const server = new WebSocket.Server({ port: PORT })
    server
      .on('error', err => console.error(`Server threw:`, err))
      .on('listening', () => {
        console.log(`server listening on port ${PORT}`)
        res(server)
      })
  })
]).then(async ([redisClient, db, server]) => {

  // holds all the active socket connections
  const people = new Map()
  
  function removeUser(connectionId) {
    const p = people.get(connectionId)
    if (p) {
      people.delete(connectionId)
      broadcast({
          type: 'remove-user',
          id: connectionId
        }, null, p.site_id)
    }
  }
  
  // broadcasts to all websockets (optionally, except one) of an event to a specific site
  function broadcast (obj, exceptId, siteId) {
    for(let [userId, person] of people) {
      if (!siteId || person.site_id === siteId) {
        if (!exceptId || userId !== exceptId) {
          person.ws.send(JSON.stringify(obj))
        }
      }
    }
  }
  
  // server listening for websocket connection
  server.on('connection', ws => {
    const connectionId = crypto.randomBytes(16).toString("hex")
    console.log(`${(new Date()).toLocaleString()} Client connected: ${connectionId}`);

    // prune on close
    ws.addEventListener('close', () => {
      console.log(`${(new Date()).toLocaleString()} Client disconnected: ${connectionId}`);
      removeUser(connectionId)
    }, { once: true })
    
    function setupListeners(connectionId, userRow) {
      const newUser = {
        id: connectionId,
        site_id: userRow.site_id,
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
          .filter(p => p.site_id === newUser.site_id) // only people in our same system
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
        }, connectionId, userRow.site_id)
      
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
              }, connectionId, user.site_id)
            break
        }
      });
    }
  
    // websocket listening for messages
    ws.addEventListener('message', async event => {
      const [ init, nonce ] = event.data.split('|')
      if (init === 'init' && nonce) {
        const user_id = await redisClient.get(`bible-reading-challenge:websocket-nonce/${nonce}`)
        db.get(`SELECT id, site_id, name, emoji FROM users WHERE id = ?`, [ user_id ], (err, row) => {
          if (row) {
            setupListeners(connectionId, row)
          }
          else {
            console.log(`Bad nonce: ${nonce}`)
            ws.terminate()
          }
        })
      }
      else {
        console.log(`invalid initialization code: ${event.data}`)
        ws.terminate()
      }
    }, { once: true })
  })
  
  setInterval(() => {
    for (let [ userId, person ] of people) {
      if ([WebSocket.CLOSED, WebSocket.CLOSING ].includes(person.ws.readyState)) {
        removeUser(userId)
      }
    }
  }, 5000)
})

