#include <WiFi.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <Adafruit_PN532.h>

// ---------- WIFI ----------
const char* WIFI_SSID = "TU_WIFI";
const char* WIFI_PASS = "TU_PASS";

// Use the Raspberry Pi LAN IP, never localhost/127.0.0.1
const char* API_URL = "http://10.204.248.185:8000/inventory/scan/esp";
const char* API_TOKEN = "";  // optional: same as RFID_ESP_TOKEN in .env
const char* SOURCE_ID = "esp32-pn532-1";

// ---------- PN532 (I2C mode) ----------
// ESP32 default I2C pins:
// SDA -> GPIO21
// SCL -> GPIO22
// Put PN532 switch/selectors in I2C mode before powering on.
static const int I2C_SDA_PIN = 21;
static const int I2C_SCL_PIN = 22;

// Typical pins used by Adafruit_PN532 in I2C mode.
// If your board does not expose these pins, keep them disconnected and use:
// Adafruit_PN532 nfc(&Wire); (depends on library version).
static const int PN532_IRQ_PIN = 4;
static const int PN532_RESET_PIN = 5;
Adafruit_PN532 nfc(PN532_IRQ_PIN, PN532_RESET_PIN);

// ---------- Tuning ----------
static const uint32_t UID_COOLDOWN_MS = 1500;   // avoid duplicate reads flood
static const uint32_t WIFI_RETRY_MS = 4000;

String lastUid = "";
uint32_t lastUidMs = 0;
uint32_t lastWifiRetryMs = 0;

String uidToHex(const uint8_t* uid, uint8_t uidLength) {
  String out;
  out.reserve(uidLength * 2);
  for (uint8_t i = 0; i < uidLength; i++) {
    if (uid[i] < 0x10) out += "0";
    out += String(uid[i], HEX);
  }
  out.toUpperCase();
  return out;
}

void connectWifi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  Serial.print("WiFi connecting");
  uint32_t started = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - started < 20000) {
    delay(300);
    Serial.print(".");
  }
  Serial.println();
  if (WiFi.status() == WL_CONNECTED) {
    Serial.print("WiFi OK. IP: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("WiFi connect timeout.");
  }
}

void ensureWifi() {
  if (WiFi.status() == WL_CONNECTED) return;
  uint32_t now = millis();
  if (now - lastWifiRetryMs < WIFI_RETRY_MS) return;
  lastWifiRetryMs = now;
  Serial.println("WiFi disconnected, reconnecting...");
  connectWifi();
}

void postUid(const String& uid) {
  ensureWifi();
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Skip POST: no WiFi.");
    return;
  }

  HTTPClient http;
  http.setConnectTimeout(2500);
  http.setTimeout(3500);
  if (!http.begin(API_URL)) {
    Serial.println("HTTP begin failed.");
    return;
  }
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
  Serial.println("ESP32 + PN532 test start");

  Wire.begin(I2C_SDA_PIN, I2C_SCL_PIN);
  nfc.begin();

  uint32_t version = nfc.getFirmwareVersion();
  if (!version) {
    Serial.println("PN532 not found. Check wiring and I2C mode.");
    while (true) {
      delay(1000);
    }
  }

  Serial.print("PN532 found. Firmware: ");
  Serial.print((version >> 24) & 0xFF, HEX);
  Serial.print(".");
  Serial.println((version >> 16) & 0xFF, HEX);

  nfc.SAMConfig();
  connectWifi();
  Serial.println("Ready. Tap an NFC tag/card...");
}

void loop() {
  ensureWifi();

  uint8_t uid[7] = {0};
  uint8_t uidLength = 0;
  bool readOk = nfc.readPassiveTargetID(
      PN532_MIFARE_ISO14443A, uid, &uidLength, 50);

  if (!readOk) {
    delay(20);
    return;
  }

  String currentUid = uidToHex(uid, uidLength);
  uint32_t now = millis();
  if (currentUid == lastUid && (now - lastUidMs) < UID_COOLDOWN_MS) {
    delay(80);
    return;
  }

  lastUid = currentUid;
  lastUidMs = now;

  Serial.print("UID read: ");
  Serial.println(currentUid);
  postUid(currentUid);
  delay(120);
}
