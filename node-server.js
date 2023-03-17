/** Note June 2017
 *
 * This is a personal node server that runs my ESP8266 devices.
 * I didn't like the options available at the time (homeassistant).
 * So I did what a maker would do, and made my own. We have old ipads around
 * the house that control the sensors through out the house using MQTT via
 * a Mosquitto server. Also uses websockets to keep all the devices up to date.
 **/
 
// subscribe to all topics defined in the devices table
// conduit between topic messages and row values
// conduit for front-end application to send mqtt

console.log("[SUPERHOMESERVER] - Booting...");
var ipAddress = '10.0.1.6'

// Mosquitto MQTT connection
var mqtt = require('mqtt')
var mqttServer  = mqtt.connect('mqtt://' + ipAddress)

// Express Server
var express = require('express')
var app = express()
var bodyParser = require('body-parser');
app.use(bodyParser.urlencoded({ extended: true }));
app.use(bodyParser.json({ type: "application/*+json" }));
app.use(express.static('../homepage/dist'))

// PostgreSql
var pg = require('pg')
var conString = "postgres://kenelliott:@localhost:5432/superhome_production"
var pgServer = new pg.Client(conString)
pgServer.connect()

// websocket
var server = require('http').createServer()
var wsServer = require('ws').Server
var websocketServer = new wsServer({ host: ipAddress, server: server })
var clientCount=0;
var websocketClient = null;

websocketServer.on('connection', function connection(wsClient) {
  websocketClient = wsClient;
  console.log('[WEBSOCKET] - A client connected', clientCount++)

  ws.on('message', function incoming(message) {
    console.log('[WEBSOCKET] - received a message', message.toString())
    if(message.toString() == 'socket_notification') {
      console.log('[WEBSOCKET] - Sending this message to the browser: %s', message)
      // i don't remember why I did this.
      ws.send(JSON.stringify({msg:{"something": "else"}}))
    }
  })

  // send a success message
  ws.send('success')
})

// report uncaught rejections. just in case.
process.on('unhandledRejection', function(e) {
  console.log('[ERROR] - Unhandled Rejection', e.message, e.stack)
})

/**
 * subscribe to status and switch events and let
 * everyone know the server is online
 * then go through all the devices and subscribe
 * to their topics
 */
mqttServer.on('connect', function () {
  console.log("[MQTT] - connected")
  mqttServer.subscribe('server/switch')
  mqttServer.subscribe('server/status')
  mqttServer.publish('server/status', JSON.stringify({state: 'initializing'}));

  pgServer.query("select * from devices").then(function(res) {
    console.log('[PSQL] - ' + res.rows.length + ' devices found');
    res.rows.forEach(function(row) {
      ['status', 'switch'].forEach(function(infoTopic) {
        var topic = row.topic + '/' + infoTopic
        mqttServer.subscribe(topic);
        console.log('[MQTT] - server subscribed to : ' + topic);
      });
    });
  });
})

/**
 * subscribe to message and handle device updates
 */
mqttServer.on('message', function (topic, msg) {
  topic = topic.toString()
  msg = msg.toString()

  console.log('[MQTT] - Message: ', topic, msg)


  // log all but status updates
  if(topic.split("/").pop() !== 'status') {
    logEvent(pgServer, topic, msg);
  }

  if(topic === 'server/switch') {
    // this is a call to the server to initiate a switch event on a device
    // used by trips numpad, when a successful code is entered.
    msg = JSON.parse(msg);

    console.log('[MQTT] - Server switch being used', msg.id)
    // get the switch and find what its current status is
    pgServer.query("select state from switches where id = " + msg.id).then(function(response) {
      console.log('[MQTT] - Handling switch', msg.id, response.rows[0].state)
      handleSwitch(msg.id, !response.rows[0].state);
    })
  } else if(topic.split('/').pop() === 'status') {
    msg = JSON.parse(msg);
    updateDeviceStatus(pgServer, topic, msg.state)
    // send a websocket notification to the clients
    if(websocketClient && topic) {
      topic = cleanTopic(topic);
      pgServer.query("select id from devices where topic = '" + topic + "'").then(function(response) {
        console.log("[PSQL] - found device with topic", topic);
        if(response.rows.length) {
          console.log("[WEBSOCKET] - sending update notification")
          websocketClient.send(JSON.stringify(response.rows[0].id))
        }
      })
    }
    // message is Buffer
    // mqttServer.end();  
  }
})

/**
 * Express Web Server
 */
app.get('/', function (req, res) {
  res.send('SuperHome Web Server')
})

app.get('/devices', function(req, res) {
  console.log('[GET] - requesting devices')
  pgServer.query("select * from devices").then(function(result) {
    res.json(result.rows)
  });
})

/**
 * output the row in json when requested
 */
app.get('/devices/:id', function(req, res) {
  console.log('[GET] - requesting a single device', req.params.id)
  pgServer.query("select * from devices where id = " + req.params.id).then(function(result) {
    res.json(result.rows[0])
  })
})

/**
 * output the row in json when requested
 */
app.get('/events/:id', function(req, res) {
  console.log('[GET] - requesting a single event', req.params.id)
  pgServer.query("select * from events where id = " + req.params.id).then(function(result) {
    res.json(result.rows[0])
  })
})

/**
 * output the row in json when requested
 */
app.get('/events', function(req, res) {
  console.log('[GET] - requesting events')
  pgServer.query("select * from events limit 10").then(function(result) {
    res.json(result.rows)
  })
})


/**
 * output the row in json when requested
 */
app.get('/switches', function(req, res) {
  console.log('[GET] - requesting switches')
  pgServer.query("select * from switches").then(function(result) {
    res.json(result.rows)
  })
})

/**
 * output the row in json when requested
 */
app.get('/forecasts', function(req, res) {
  console.log('[GET] - requesting forecasts')
  pgServer.query("select * from forecasts").then(function(result) {
    res.json(result.rows)
  })
})


/**
 * output the row in json when requested
 */
app.get('/groups', function(req, res) {
  console.log('[GET] - requesting groups')
  pgServer.query("select * from groups").then(function(result) {
    res.json(result.rows)
  })
})


/**
 * output the row in json when requested
 */
app.get('/codes', function(req, res) {
  console.log('[GET] - requesting codes')
  pgServer.query("select * from codes").then(function(result) {
    res.json(result.rows)
  })
})

/**
 * I do not think this is being used
 */
// app.put('/devices/:id', function(req, res) {
//   console.log('[PUT] - Device Request Received', req.body);
//   mqttServer.publish('server/status', 'helping')

//   // get the switch record from the database
//   // find the code that matches its state
//   // send that code in the mqtt publish
//   mqttServer.publish('garage/overhead-door/controller/switch', req.body.switch_id.toString())
//   res.json({});
// })

/**
 * PATCH is used to handle requests from the browser.
 */
app.patch('/switches/:id', function(req, res) {
  console.log('[PATCH] Switch Request Received', req.params.id, req.params);
  mqttServer.publish('server/status', JSON.stringify({state: "busy"}));

  handleSwitch(req.params.id, req.body.state, res);
})

function handleSwitch(switchId, state, res) {
  // get the switch record from the database
  // find the code that matches its state
  // send that code in the mqtt publish
  var query = "select codes.code, devices.topic from codes, devices, switches where devices.id = switches.device_id and switches.id = codes.switch_id and codes.switch_id = " + switchId + " and codes.key = '" + (state ? "on" : "off") + "'"

  // get the switch rf code
  pgServer.query(query).then(function(result) {
    console.log('[PSQL] - found switch code', result.rows[0]);

    // tell the device to handle the switch (not json yet)
    mqttServer.publish(result.rows[0].topic + '/switch', result.rows[0].code.toString())

    // update the switch record status
    pgServer.query("update switches set state=$1, updated_at=$2 where id = " + switchId, [state, new Date()]).then(function() {
      // do not need to return a record if MQTT request initiated this
      if(!res) {
        return;
      }
      // return the switch record
      pgServer.query('select * from switches where id=' + switchId).then(function(updatedResult) {
        res.json(updatedResult.rows[0]);
      })
    });
  })
}

app.listen(3000, function () {
  console.log('[WEBSERVER] - Example app listening on port 3000!')
})

server.on('request', app)
server.listen(4080, function() {
  console.log('[WEBSOCKET] - listening for websocket clients on port 4080');
})

/**
 * Methods
 */
function cleanTopic(topic) {
  var splitTopic = topic.split('/')
  splitTopic.pop()
  return splitTopic.join('/')
}

function updateDeviceStatus(pgServer, topic, msg) {
  var query = "update devices set state=$1, updated_at=$2 where topic = '" + cleanTopic(topic) + "'"

  console.log('[PSQL] - update device state', query);
  return pgServer.query(query, [msg, new Date()], function(err) {
    handleUpdateResponse(pgServer, topic, msg, err, query)
  })
}

function handleUpdateResponse(pgServer, topic, msg, err, query) {
  if(err) {
    console.log('[ERROR] - query request failed', err.message, err.stack)
    return
  }

  console.log('[PSQL] - update device state success', topic, msg)
}

function logEvent(pgServer, topic, msg) {
  console.log('[PSQL] - logging event', topic, msg)
  pgServer.query("insert into events (topic, message, created_at, updated_at) values ($1, $2, $3, $4)", [topic, msg, new Date(), new Date()])
}