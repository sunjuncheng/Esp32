/* ============================================================
 *  WarpLane ESP32 温湿度监控固件 (ST7789 2.4寸彩色屏版)
 *  功能：
 *    1. 读取 DHT11/DHT22 温湿度
 *    2. ST7789 240x320 彩色屏实时显示 + 动画
 *    3. 定时上报数据到后台服务器
 *    4. 超阈值时蜂鸣器报警（时长由后台配置）
 *    5. 拉取后台阈值/报警时长/上报间隔配置
 *    6. 心跳保活
 * ============================================================ */

// ============ TFT_eSPI 配置（无需修改库文件，直接在这里配置）============
#define USER_SETUP_LOADED
#define ST7789_DRIVER
#define TFT_WIDTH   240
#define TFT_HEIGHT  320
#define TFT_MISO    -1
#define TFT_MOSI    TFT_MOSI_PIN
#define TFT_SCLK    TFT_SCK_PIN
#define TFT_CS      TFT_CS_PIN
#define TFT_DC      TFT_DC_PIN
#define TFT_RST     TFT_RST_PIN
#define TFT_BL      TFT_BL_PIN
#define TFT_BACKLIGHT_ON HIGH
#define SPI_FREQUENCY  40000000
#define SPI_READ_FREQUENCY 20000000
#define LOAD_GLCD
#define LOAD_FONT2
#define LOAD_FONT4
#define LOAD_FONT6
#define LOAD_FONT7
#define LOAD_FONT8
#define LOAD_FONT8N
#define SMOOTH_FONT

#include <WiFi.h>
#include <HTTPClient.h>
#include <DHT.h>
#include <SPI.h>
#include <TFT_eSPI.h>
#include <ArduinoJson.h>
#include "config.h"

// ============ 全局对象 ============
DHT dht(DHT_PIN, DHT_TYPE);
TFT_eSPI tft = TFT_eSPI();

// ============ 运行时状态 ============
float currentTemp = 0;
float currentHumi = 0;
bool  sensorOK = false;

// 后台下发的配置（有默认值，启动时拉取更新）
int   reportInterval = DEFAULT_REPORT_INTERVAL;
int   alarmDuration  = DEFAULT_ALARM_DURATION;
float tempMin = -40, tempMax = 85;
float humiMin = 0,   humiMax = 100;

// 动画
int   animFrame = 0;
unsigned long lastReport = 0;
unsigned long lastHeartbeat = 0;
unsigned long lastConfigFetch = 0;
unsigned long alarmStart = 0;
bool  alarming = false;

// ============ 工具函数 ============
void beep(int ms) {
  digitalWrite(BUZZER_PIN, BUZZER_ACTIVE);
  delay(ms);
  digitalWrite(BUZZER_PIN, !BUZZER_ACTIVE);
}

void startAlarm() {
  if (alarming) return;
  alarming = true;
  alarmStart = millis();
  digitalWrite(BUZZER_PIN, BUZZER_ACTIVE);
  Serial.println("[ALARM] 开始报警");
}

void stopAlarm() {
  if (!alarming) return;
  alarming = false;
  digitalWrite(BUZZER_PIN, !BUZZER_ACTIVE);
  Serial.println("[ALARM] 停止报警");
}

// ============ 绘制彩色界面 ============
void drawScreen() {
  // 报警时整屏闪烁红色背景
  if (alarming && (animFrame / 5) % 2 == 0) {
    tft.fillScreen(TFT_RED);
  } else {
    tft.fillScreen(TFT_BLACK);
  }

  // ===== 顶部标题栏（渐变效果用矩形模拟）=====
  for (int i = 0; i < 36; i++) {
    uint16_t c = tft.color565(80 - i/2, 100 - i/3, 200 - i);
    tft.drawFastHLine(0, i, TFT_WIDTH, c);
  }
  tft.setTextColor(TFT_WHITE);
  tft.setTextDatum(TL_DATUM);
  tft.drawString("WarpLane", 10, 8, 4);
  tft.setTextDatum(TR_DATUM);
  if (WiFi.status() == WL_CONNECTED) {
    tft.setTextColor(TFT_GREEN);
    tft.drawString("WiFi", TFT_WIDTH - 10, 10, 2);
  } else {
    tft.setTextColor(TFT_RED);
    tft.drawString("X", TFT_WIDTH - 10, 10, 2);
  }

  if (!sensorOK) {
    tft.setTextColor(TFT_RED);
    tft.setTextDatum(MC_DATUM);
    tft.drawString("Sensor Error!", TFT_WIDTH/2, TFT_HEIGHT/2, 4);
    return;
  }

  // ===== 温度区域 =====
  int tempY = 60;
  // 温度标签
  tft.setTextColor(TFT_ORANGE);
  tft.setTextDatum(TL_DATUM);
  tft.drawString("TEMPERATURE", 20, tempY, 2);

  // 温度大数字
  char tempStr[12];
  snprintf(tempStr, sizeof(tempStr), "%.1f", currentTemp);
  tft.setTextColor(TFT_RED);
  tft.setTextDatum(TL_DATUM);
  tft.drawString(tempStr, 20, tempY + 24, 8);

  // 单位 ℃
  tft.setTextColor(TFT_ORANGE);
  tft.setTextDatum(TL_DATUM);
  int tempW = tft.textWidth(tempStr, 8);
  tft.drawString("C", 20 + tempW + 4, tempY + 24, 6);

  // 温度计图标（右侧）
  int tx = 190, ty = tempY + 20;
  tft.drawRect(tx, ty, 18, 70, TFT_ORANGE);
  int barH = (int)((currentTemp - tempMin) / (tempMax - tempMin) * 60);
  if (barH < 2) barH = 2;
  if (barH > 60) barH = 60;
  tft.fillRect(tx + 2, ty + 68 - barH, 14, barH, TFT_RED);
  tft.fillCircle(tx + 9, ty + 75, 10, TFT_RED);
  tft.drawCircle(tx + 9, ty + 75, 10, TFT_ORANGE);

  // ===== 分割线 =====
  tft.drawFastHLine(10, tempY + 110, TFT_WIDTH - 20, TFT_DARKGREY);

  // ===== 湿度区域 =====
  int humiY = tempY + 120;
  tft.setTextColor(TFT_CYAN);
  tft.setTextDatum(TL_DATUM);
  tft.drawString("HUMIDITY", 20, humiY, 2);

  // 湿度大数字
  char humiStr[12];
  snprintf(humiStr, sizeof(humiStr), "%.0f", currentHumi);
  tft.setTextColor(TFT_CYAN);
  tft.setTextDatum(TL_DATUM);
  tft.drawString(humiStr, 20, humiY + 24, 8);

  // 单位 %
  int humiW = tft.textWidth(humiStr, 8);
  tft.setTextColor(TFT_CYAN);
  tft.drawString("%", 20 + humiW + 4, humiY + 24, 6);

  // 水滴动画（右侧）
  int dx = 190, dy = humiY + 20 + (animFrame % 30);
  tft.fillTriangle(dx + 10, dy, dx, dy + 20, dx + 20, dy + 20, TFT_CYAN);
  tft.fillCircle(dx + 10, dy + 20, 10, TFT_CYAN);
  tft.fillCircle(dx + 6, dy + 16, 3, TFT_WHITE); // 高光

  // ===== 底部状态栏 =====
  int statusY = TFT_HEIGHT - 50;
  tft.fillRect(0, statusY, TFT_WIDTH, 50, TFT_DARKGREY);

  // 阈值信息
  tft.setTextColor(TFT_WHITE);
  tft.setTextDatum(TL_DATUM);
  char rangeStr[40];
  snprintf(rangeStr, sizeof(rangeStr), "T:%.0f~%.0f H:%.0f~%.0f", tempMin, tempMax, humiMin, humiMax);
  tft.drawString(rangeStr, 10, statusY + 6, 2);

  char cfgStr[30];
  snprintf(cfgStr, sizeof(cfgStr), "Int:%ds Rpt:%ds", alarmDuration, reportInterval);
  tft.drawString(cfgStr, 10, statusY + 26, 2);

  // 报警状态（右侧）
  tft.setTextDatum(TR_DATUM);
  if (alarming) {
    tft.setTextColor(TFT_RED);
    tft.drawString("!! ALARM !!", TFT_WIDTH - 10, statusY + 14, 4);
  } else {
    tft.setTextColor(TFT_GREEN);
    tft.drawString("NORMAL", TFT_WIDTH - 10, statusY + 14, 4);
  }

  animFrame++;
}

// ============ WiFi 连接 ============
void connectWiFi() {
  Serial.printf("\n[WiFi] 连接 %s ...\n", WIFI_SSID);

  // 屏幕显示"连接中"
  tft.fillScreen(TFT_BLACK);
  tft.setTextColor(TFT_WHITE);
  tft.setTextDatum(MC_DATUM);
  tft.drawString("Connecting WiFi", TFT_WIDTH / 2, TFT_HEIGHT / 2 - 30, 2);
  tft.setTextColor(TFT_CYAN);
  tft.drawString(WIFI_SSID, TFT_WIDTH / 2, TFT_HEIGHT / 2, 2);

  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  int tries = 0;
  int dot = 0;
  while (WiFi.status() != WL_CONNECTED && tries < 40) {
    delay(500);
    Serial.print(".");

    // LED 快闪（连接中）
    digitalWrite(LED_PIN, !digitalRead(LED_PIN));

    // 屏幕上显示点动画（最多 4 个点循环）
    String dots = "";
    for (int i = 0; i < dot % 4; i++) dots += ".";
    tft.fillRect(TFT_WIDTH / 2 - 50, TFT_HEIGHT / 2 + 30, 100, 25, TFT_BLACK);
    tft.setTextColor(TFT_CYAN);
    tft.setTextDatum(MC_DATUM);
    tft.drawString(dots, TFT_WIDTH / 2, TFT_HEIGHT / 2 + 42, 4);
    dot++;
    tries++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("\n[WiFi] 已连接, IP: %s\n", WiFi.localIP().toString().c_str());
    digitalWrite(LED_PIN, HIGH); // LED 常亮 = 已连接

    // 屏幕显示"已连接" + IP
    tft.fillScreen(TFT_BLACK);
    tft.setTextColor(TFT_GREEN);
    tft.setTextDatum(MC_DATUM);
    tft.drawString("WiFi Connected", TFT_WIDTH / 2, TFT_HEIGHT / 2 - 20, 2);
    tft.setTextColor(TFT_WHITE);
    tft.drawString(WiFi.localIP().toString(), TFT_WIDTH / 2, TFT_HEIGHT / 2 + 15, 2);
    delay(1200);
  } else {
    Serial.println("\n[WiFi] 连接失败，将重试...");
    digitalWrite(LED_PIN, LOW); // LED 熄灭 = 未连接

    // 屏幕显示"连接失败"
    tft.fillScreen(TFT_BLACK);
    tft.setTextColor(TFT_RED);
    tft.setTextDatum(MC_DATUM);
    tft.drawString("WiFi Failed", TFT_WIDTH / 2, TFT_HEIGHT / 2 - 20, 4);
    tft.setTextColor(TFT_WHITE);
    tft.drawString("Retrying...", TFT_WIDTH / 2, TFT_HEIGHT / 2 + 25, 2);
    delay(1500);
  }
}

// ============ HTTP POST 请求 ============
String httpPost(String url, String body) {
  if (WiFi.status() != WL_CONNECTED) {
    connectWiFi();
    if (WiFi.status() != WL_CONNECTED) return "";
  }
  HTTPClient http;
  http.setTimeout(8000);
  http.begin(url);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  int code = http.POST(body);
  String payload = "";
  if (code > 0) payload = http.getString();
  http.end();
  return payload;
}

// ============ 拉取后台配置 ============
void fetchConfig() {
  Serial.println("[Config] 拉取后台配置...");
  String url = String(API_BASE) + API_PATH + "?action=getDeviceConfig&device_token=" + DEVICE_TOKEN;
  String resp = httpPost(url, "device_token=" + String(DEVICE_TOKEN));
  if (resp.length() == 0) { Serial.println("[Config] 请求失败"); return; }

  DynamicJsonDocument doc(512);
  DeserializationError err = deserializeJson(doc, resp);
  if (err) { Serial.printf("[Config] JSON 解析失败: %s\n", err.c_str()); return; }

  if (doc["code"] != 200) {
    Serial.printf("[Config] 接口返回错误: %s\n", doc["msg"].as<String>().c_str());
    return;
  }

  JsonObject cfg = doc["data"];
  reportInterval = cfg["report_interval"] | reportInterval;
  alarmDuration  = cfg["alarm_duration"]  | alarmDuration;
  tempMin = cfg["temp_min"] | tempMin;
  tempMax = cfg["temp_max"] | tempMax;
  humiMin = cfg["humidity_min"] | humiMin;
  humiMax = cfg["humidity_max"] | humiMax;

  Serial.printf("[Config] 已更新: 间隔=%ds 报警=%ds 温度[%.1f,%.1f] 湿度[%.1f,%.1f]\n",
    reportInterval, alarmDuration, tempMin, tempMax, humiMin, humiMax);
}

// ============ 上报温湿度 ============
void reportData() {
  Serial.printf("[Report] 上报 T=%.1f H=%.0f\n", currentTemp, currentHumi);
  String url = String(API_BASE) + API_PATH + "?action=reportData";
  String body = "device_token=" + String(DEVICE_TOKEN)
              + "&temp=" + String(currentTemp, 1)
              + "&humidity=" + String(currentHumi, 1);
  String resp = httpPost(url, body);
  if (resp.length() == 0) { Serial.println("[Report] 请求失败"); return; }

  DynamicJsonDocument doc(512);
  if (deserializeJson(doc, resp)) { Serial.println("[Report] JSON 解析失败"); return; }

  if (doc["code"] != 200) {
    Serial.printf("[Report] 错误: %s\n", doc["msg"].as<String>().c_str());
    return;
  }

  // 服务端返回报警指令
  int alarm = doc["data"]["alarm"] | 0;
  int dur   = doc["data"]["alarm_duration"] | alarmDuration;
  if (alarm == 1) {
    Serial.println("[Report] 服务端指令: 报警");
    alarmDuration = dur;
    startAlarm();
  }
}

// ============ 心跳 ============
void sendHeartbeat() {
  String url = String(API_BASE) + API_PATH + "?action=heartbeat";
  httpPost(url, "device_token=" + String(DEVICE_TOKEN));
}

// ============ 读取传感器 ============
void readSensor() {
  float t = dht.readTemperature();
  float h = dht.readHumidity();
  if (isnan(t) || isnan(h)) {
    sensorOK = false;
    Serial.println("[Sensor] 读取失败");
    return;
  }
  currentTemp = t;
  currentHumi = h;
  sensorOK = true;

  // 本地也判断一次（双保险，即使服务器没响应也能报警）
  if (t < tempMin || t > tempMax || h < humiMin || h > humiMax) {
    startAlarm();
  }
}

// ============ setup ============
void setup() {
  Serial.begin(115200);
  delay(500);
  Serial.println("\n\n========== WarpLane ESP32 启动 (ST7789彩屏版) ==========");

  // 引脚初始化
  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, !BUZZER_ACTIVE); // 默认关闭（低电平触发）
  pinMode(TFT_BL, OUTPUT);
  digitalWrite(TFT_BL, HIGH); // 开启背光
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, LOW); // LED 默认灭，WiFi 连上后常亮

  // TFT 初始化
  tft.init();
  tft.setRotation(TFT_ROTATION);
  tft.fillScreen(TFT_BLACK);
  tft.setTextColor(TFT_WHITE);
  tft.setTextDatum(MC_DATUM);
  tft.drawString("WarpLane IoT", TFT_WIDTH/2, TFT_HEIGHT/2 - 20, 4);
  tft.drawString("Starting...", TFT_WIDTH/2, TFT_HEIGHT/2 + 20, 2);

  // DHT 初始化
  dht.begin();
  Serial.println("[Sensor] DHT 已初始化");

  // WiFi
  connectWiFi();

  // 启动提示音
  beep(80); delay(50); beep(80);

  // 拉取配置
  fetchConfig();
  readSensor();
  reportData();
  lastReport = millis();
  lastHeartbeat = millis();
  lastConfigFetch = millis();
}

// ============ loop ============
void loop() {
  unsigned long now = millis();

  // 1. 读取传感器（每 2 秒）
  static unsigned long lastRead = 0;
  if (now - lastRead >= 2000) {
    readSensor();
    lastRead = now;
  }

  // 2. 上报数据
  if (now - lastReport >= (unsigned long)reportInterval * 1000) {
    if (sensorOK) reportData();
    lastReport = now;
  }

  // 3. 心跳（每 30 秒）
  if (now - lastHeartbeat >= 30000) {
    sendHeartbeat();
    lastHeartbeat = now;
  }

  // 4. 定期拉取配置（每 5 分钟，后台改了阈值会自动生效）
  if (now - lastConfigFetch >= 300000) {
    fetchConfig();
    lastConfigFetch = now;
  }

  // 5. 报警时长控制
  if (alarming && (now - alarmStart >= (unsigned long)alarmDuration * 1000)) {
    stopAlarm();
  }

  // 6. 刷新屏幕
  drawScreen();

  delay(100);
}
