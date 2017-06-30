/** Note June, 2017
 *
 * This is a script for an ESP8266 device that controls RF devices via MQTT requests.
 * This particular device controls appliances in my office. 
 * Overhead fan, overhead lights, desk fan and led strips.
 **/

/*
 *  Connect to Wifi
 *  Setup OTA updates
 *  Connect to Mosquitto MQTT Server
 */
#include "ESP8266WiFi.h"
#include <ESP8266mDNS.h>
#include <WiFiUdp.h>
#include <ArduinoOTA.h>
#include <PubSubClient.h>

// used for rf communications only (outputs harmless error)
#include <RCSwitch.h>

const char* ssid = "****";
const char* password = "*********************************"; // wifi network password jokes are fun
const char* mqtt_server = "***.***.***.***";

WiFiClient espClient;
PubSubClient client(espClient);
char msg[50];
unsigned long timeoutCounter;
int timeout = 20000;

// used only for rf communications
RCSwitch mySwitch = RCSwitch();
void setup() {
  setupSerial(115200);

  setupWifi();

  setupOTA();

  setupRcSwitch();
  
  pinMode(16, OUTPUT);
  digitalWrite(16, LOW);
  
  client.setServer(mqtt_server, 1883);
  client.setCallback(callback);

  Serial.println("Setup done");
}

void loop() {
  // connect to wifi
  connectToWifi();

  if (!client.connected()) {
    Serial.println("not connected");
    reconnect();
  }
  client.loop();
}

// FUNCTIONS
void setupSerial(int baud) {
  Serial.begin(baud);
  Serial.println("Serial Connected");
  delay(10);
}

void setupWifi() {
  // Set WiFi to station mode and disconnect from an AP if it was previously connected
  WiFi.mode(WIFI_STA);
  WiFi.disconnect();
  // give wifi time to setup
  delay(100);
}

void connectToWifi() {
  if(WiFi.status() == WL_CONNECTED) {
    return;
  }

  WiFi.disconnect();
  WiFi.begin("MyFi", "trip2011");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");

    if(WiFi.status() == WL_CONNECT_FAILED) {
      Serial.println("[ERROR] - Connection Failed");
      break;
    }
  }

  if(WiFi.status() == WL_CONNECTED) {
    Serial.println("");
    Serial.println("WiFi connected");  
    Serial.println("IP address: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("[ERROR] - unable to connect to wifi");
    Serial.print("WifiStatus = ");
    Serial.println(WiFi.status());
  }
}

void setupOTA() {
  ArduinoOTA.setPassword((const char *)"devicepass");

  ArduinoOTA.onStart([]() {
    Serial.println("Starting to Upload Over The Oxygen");
  });
  ArduinoOTA.onEnd([]() {
    Serial.println("\nEnd");
  });
  ArduinoOTA.onProgress([](unsigned int progress, unsigned int total) {
    Serial.printf("Progress: %u%%\r", (progress / (total / 100)));
  });
  ArduinoOTA.onError([](ota_error_t error) {
    Serial.printf("Error[%u]: ", error);
    if (error == OTA_AUTH_ERROR) Serial.println("Auth Failed");
    else if (error == OTA_BEGIN_ERROR) Serial.println("Begin Failed");
    else if (error == OTA_CONNECT_ERROR) Serial.println("Connect Failed");
    else if (error == OTA_RECEIVE_ERROR) Serial.println("Receive Failed");
    else if (error == OTA_END_ERROR) Serial.println("End Failed");
  });
  ArduinoOTA.begin();
}

void callback(char* topic, byte* payload, unsigned int length) {
  Serial.print("Message arrived [");
  Serial.print(topic);
  Serial.print("] ");

  // convert byte array to string
  String msg;
  for (int i = 0; i < length; i++) {
    msg += (char)payload[i];
  }
  Serial.print(msg);
  
  // turn on the led to show receipt and delay
  digitalWrite(16, HIGH);

  // convert string to int and send rf signal
  mySwitch.send(msg.toInt(), 24);

  // set a delay so it can't be used to rapidly switch the outlet
  delay(1000);

  // turn off the led to show that it is ready to activate again
  digitalWrite(16, LOW);
}

void reconnect() {
  // Loop until we're reconnected
  while (!client.connected()) {
    Serial.print("MQTT connecting...");
    // Attempt to connect
    if (client.connect("office-wemos")) {
      Serial.println("MQTT connected");
      // Once connected, publish an announcement...
      client.publish("office/controller/status", "online");
      // ... and subscribe
      client.subscribe("office/controller/status");
      client.subscribe("office/controller/switch");
    } else {
      Serial.print("[MQTT FAILED], rc=");
      Serial.print(client.state());
      Serial.println(" retry in 5 seconds...");
      // Wait 5 seconds before retrying
      delay(5000);
    }
  }
}

void setupRcSwitch() {
  mySwitch.enableTransmit(5);

  // Optional set pulse length.
  mySwitch.setPulseLength(202);
}

void setupRelay() {
  pinMode(4, OUTPUT);
}
