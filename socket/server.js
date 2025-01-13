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
    const db = new sqlite3.Database(`${__dirname}/../brc.db`, sqlite3.OPEN_READWRITE | sqlite3.OPEN_FULLMUTEX)
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

  function broadcastLikes (siteId, scheduleDateId) {
    db.all(`
      SELECT i.id, i.user_id, i.position 
      FROM interactions i
      JOIN users u ON u.id = i.user_id
      WHERE u.site_id = $siteId AND 
        i.schedule_date_id = $scheduleDateId AND
        i.interaction_type = 'LIKE'`, 
      {
        $siteId: siteId,
        $scheduleDateId: scheduleDateId
      }, 
      (err, rows) => {
        broadcast({
          type: 'set-interactions',
          interactions: rows
        }, null, siteId)
      })
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
    
    function setupListeners(connectionId, userRow, refreshNonce) {
      const newUser = {
        id: connectionId,
        site_id: userRow.site_id,
        databaseId: userRow.id,
        ws,
        position: `0px`,
        emoji: userRow.emoji,
        name: userRow.name,
        schedule_date_id: userRow.schedule_date_id
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
            name: p.name,
            databaseId: p.databaseId
          }))
        }))
  
      broadcast({
          type: 'add-user',
          id: connectionId,
          position: `0px`,
          emoji: userRow.emoji,
          name: userRow.name,
          databaseId: userRow.databaseId
        }, connectionId, userRow.site_id)
      
      // Handle incoming messages from clients
      ws.addEventListener('message', event => {
        refreshNonce()
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
                  name: userToSend.name,
                  databaseId: userToSend.databaseId
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
          case 'interact':
            const connectedUser = people.get(connectionId)
            if (data.interaction_type === 'LIKE') {
              const versePosition = Math.max(0, +data.position)
              if (data.newValue) {
                const dbHash = {
                  $interaction_type: data.interaction_type === 'LIKE' ? 'LIKE' : 'COMMENT',
                  $value: data.newValue,
                  $user_id: connectedUser.databaseId,
                  $schedule_date_id: connectedUser.schedule_date_id,
                  $position: versePosition,
                  $timestamp: epoch()
                }
                // doesn't exist, add like
                db.run(`
                  INSERT INTO interactions (interaction_type, value, user_id, schedule_date_id, position, timestamp)
                  VALUES ($interaction_type, $value, $user_id, $schedule_date_id, $position, $timestamp)`, 
                  dbHash, 
                  err => {
                    if (err) { 
                      console.log(dbHash)
                      console.error(err)
                    }
                    else 
                      broadcastLikes(connectedUser.site_id, connectedUser.schedule_date_id)
                  })
              }
              else {
                // already exists, unlike
                const dbHash = {
                  $user_id: connectedUser.databaseId,
                  $schedule_date_id: connectedUser.schedule_date_id,
                  $position: versePosition
                }
                db.run(`
                  DELETE FROM interactions
                  WHERE 
                    user_id=$user_id AND 
                    schedule_date_id=$schedule_date_id AND
                    interaction_type='LIKE' AND
                    position = $position`, dbHash, (err) => {
                    if (err) {
                      console.log(dbHash)
                      console.error(err)
                      return
                    }
                    else {
                      broadcastLikes(connectedUser.site_id, connectedUser.schedule_date_id)
                    }
                  })
              }
            }
            break
        }
      });
    }
  
    // websocket listening for messages
    ws.addEventListener('message', async event => {
      const err = message => {
        console.log(`${message}: ${event.data}`)
        ws.terminate();
      }
      try {
        const [ init, nonce ] = event.data.split('|')
        if (init === 'init' && nonce) {
          // 'value' is of the form "schedule_date_id|user_id" (see Redis.php)
          const value = await redisClient.get(`bible-reading-challenge:websocket-nonce/${nonce}`)
            const [ schedule_date_id, user_id ] = value.split('|');
            db.get(`
              SELECT id, site_id, name, emoji, ${schedule_date_id} schedule_date_id
              FROM users WHERE id = ?`, [ user_id ], (err, row) => {
              if (row) {
                const refreshNonce = () => {
                  redisClient.expire(`bible-reading-challenge:websocket-nonce/${nonce}`, 20)
                }
                refreshNonce()
                setupListeners(connectionId, row, refreshNonce)
              }
              else {
                err(`Bad nonce: ${nonce}`)
              }
            })
        }
        else {
          err(`Invalid initialization code from client`)
        } 
      }
      catch (e) {
        err(`Exception in initialization: ${e.name}, ${e.message}`)
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

const epoch = () => Math.floor(Date.now() / 1000)