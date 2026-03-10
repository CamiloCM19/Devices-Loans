#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>

// ---------- WIFI ----------
const char* WIFI_SSID = "TU_WIFI";
const char* WIFI_PASS = "TU_PASS";

// Use PC LAN IP, never localhost/127.0.0.1
const char* API_URL = "http://172.16.19.40:8000/inventory/scan/esp";
const char* API_TOKEN = ""; // optional: same as RFID_ESP_TOKEN in .env
const char* SOURCE_ID = "esp-rc522-1";

// ---------- RC522 PINS (NodeMCU ESP8266) ----------
// SDA/SS -> D8 (GPIO15)
// SCK    -> D5 (GPIO14)
// MOSI   -> D7 (GPIO13)
// MISO   -> D6 (GPIO12)
// RST    -> D3 (GPIO0)
#define SS_PIN  D8
#define RST_PIN D3

MFRC522 mfrc522(SS_PIN, RST_PIN);

String uidToHex(const MFRC522::Uid& uid) {
  String out = "";
  for (byte i = 0; i < uid.size; i++) {
    if (uid.uidByte[i] < 0x10) out += "0";
    out += String(uid.uidByte[i], HEX);
  }
  out.toUpperCase();
  return out;
}

void connectWifi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  Serial.print("WiFi connecting");
  while (WiFi.status() != WL_CONNECTED) {
    delay(400);
    Serial.print(".");
  }
  Serial.println();
  Serial.print("WiFi OK. IP: ");
  Serial.println(WiFi.localIP());
}

void postUid(const String& uid) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi disconnected, reconnecting...");
    connectWifi();
  }

  WiFiClient client;
  HTTPClient http;
  http.begin(client, API_URL);
  http.addHeader("Content-Type", "application/json");

  String body = "{\"uid\":\"" + uid + "\",\"source\":\"" + SOURCE_ID + "\"";
  if (strlen(API_TOKEN) > 0) {
    body += ",\"token\":\"";
    body += API_TOKEN;
    body += "\"";
  }
  body += "}";

  int code = http.POST(body);
  String resp = http.getString();

  Serial.print("POST ");
  Serial.print(code);
  Serial.print(" -> ");
  Serial.println(resp);

  http.end();
}

void setup() {
  Serial.begin(115200);
  delay(200);
  Serial.println();
  Serial.println("ESP8266 + RC522 test start");

  SPI.begin();         // SCK=D5, MISO=D6, MOSI=D7
  mfrc522.PCD_Init();  // SS=D8, RST=D3
  delay(50);

  byte v = mfrc522.PCD_ReadRegister(mfrc522.VersionReg);
  Serial.print("RC522 VersionReg: 0x");
  Serial.println(v, HEX);
  if (v == 0x00 || v == 0xFF) {
    Serial.println("RC522 not detected. Check wiring/power (3.3V only).");
  }

  connectWifi();
  Serial.println("Ready. Tap a card/tag...");
}

void loop() {
  if (!mfrc522.PICC_IsNewCardPresent()) {
    delay(20);
    return;
  }
  if (!mfrc522.PICC_ReadCardSerial()) {
    delay(20);
    return;
  }

  String uid = uidToHex(mfrc522.uid);
  Serial.print("UID read: ");
  Serial.println(uid);

  postUid(uid);

  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
  delay(600);
}
