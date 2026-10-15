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

// ============ 先包含配置文件（引脚等定义在 config.h）============
#include "config.h"

// ============ TFT_eSPI 配置 ============
#define USER_SETUP_LOADED
#define ST7789_DRIVER
#define TFT_MISO    -1
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
#include <FS.h>
typedef fs::FS FS;
#include <TFT_eSPI.h>
#include <ArduinoJson.h>
#include <WebServer.h>
#include <DNSServer.h>
#include <Preferences.h>

// ============ 全局对象 ============
DHT dht(DHT_PIN, DHT_TYPE);
TFT_eSPI tft = TFT_eSPI();
WebServer server(80);
DNSServer dnsServer;
Preferences preferences;

// ============ WiFi 凭证（优先用 NVS 保存的，没有则用 config.h 默认值）============
String wifiSSID = WIFI_SSID;
String wifiPassword = WIFI_PASSWORD;

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

// ============ 表情绘制函数 ============
// type: 0=思考(连接中), 1=笑脸, 2=哭脸(错误), 3=眨眼(配网)
void drawFace(int cx, int cy, int r, int type, int frame) {
  // 脸
  uint16_t faceColor = TFT_YELLOW;
  if (type == 2) faceColor = 0xFD20; // 橙色(哭脸)
  tft.fillCircle(cx, cy, r, faceColor);
  tft.drawCircle(cx, cy, r, TFT_BLACK);

  // 眼睛
  int eyeY = cy - r / 4;
  int eyeR = r / 5;
  int eyeDx = r / 3;

  if (type == 0) {
    // 思考：眼睛转圈
    float angle = frame * 0.3;
    int ex1 = cx - eyeDx + (int)(cos(angle) * eyeR * 0.5);
    int ey1 = eyeY + (int)(sin(angle) * eyeR * 0.5);
    int ex2 = cx + eyeDx + (int)(cos(angle + 3.14) * eyeR * 0.5);
    int ey2 = eyeY + (int)(sin(angle + 3.14) * eyeR * 0.5);
    tft.fillCircle(ex1, ey1, eyeR, TFT_BLACK);
    tft.fillCircle(ex2, ey2, eyeR, TFT_BLACK);
    // 嘴：一条直线（严肃）
    tft.drawLine(cx - r / 3, cy + r / 3, cx + r / 3, cy + r / 3, TFT_BLACK);
  } else if (type == 1) {
    // 笑脸
    tft.fillCircle(cx - eyeDx, eyeY, eyeR, TFT_BLACK);
    tft.fillCircle(cx + eyeDx, eyeY, eyeR, TFT_BLACK);
    // 微笑弧线
    tft.drawArc(cx, cy + r / 8, r / 3, r / 4, 200, 340, TFT_BLACK, TFT_YELLOW);
  } else if (type == 2) {
    // 哭脸：X 眼睛
    int x1 = cx - eyeDx, x2 = cx + eyeDx;
    int s = eyeR;
    tft.drawLine(x1 - s, eyeY - s, x1 + s, eyeY + s, TFT_BLACK);
    tft.drawLine(x1 + s, eyeY - s, x1 - s, eyeY + s, TFT_BLACK);
    tft.drawLine(x2 - s, eyeY - s, x2 + s, eyeY + s, TFT_BLACK);
    tft.drawLine(x2 + s, eyeY - s, x2 - s, eyeY + s, TFT_BLACK);
    // 倒过来的嘴（哭）
    tft.drawArc(cx, cy + r / 2, r / 3, r / 4, 20, 160, TFT_BLACK, 0xFD20);
  } else if (type == 3) {
    // 眨眼：左眼正常，右眼眨
    if (frame % 20 < 10) {
      tft.fillCircle(cx - eyeDx, eyeY, eyeR, TFT_BLACK);
      tft.fillCircle(cx + eyeDx, eyeY, eyeR, TFT_BLACK);
    } else {
      tft.fillCircle(cx - eyeDx, eyeY, eyeR, TFT_BLACK);
      tft.drawLine(cx + eyeDx - eyeR, eyeY, cx + eyeDx + eyeR, eyeY, TFT_BLACK);
    }
    // 微笑
    tft.drawArc(cx, cy + r / 8, r / 3, r / 4, 200, 340, TFT_BLACK, TFT_YELLOW);
  }
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
    // 传感器错误：哭脸动画
    static int errFrame = 0;
    tft.setTextColor(TFT_RED);
    tft.setTextDatum(MC_DATUM);
    tft.drawString("Sensor Error!", TFT_WIDTH / 2, 50, 2);
    drawFace(TFT_WIDTH / 2, TFT_HEIGHT / 2 - 10, 50, 2, errFrame);
    // 抖动效果
    int shakeX = (errFrame % 2 == 0) ? 0 : 2;
    tft.setTextColor(TFT_ORANGE);
    tft.drawString("Check wiring!", TFT_WIDTH / 2 + shakeX, TFT_HEIGHT / 2 + 60, 2);
    errFrame++;
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

// ============ WiFi 凭证持久化（NVS）============
void loadWiFiCredentials() {
  preferences.begin("wifipref", true);
  String ssid = preferences.getString("ssid", "");
  String pass = preferences.getString("pass", "");
  preferences.end();
  if (ssid.length() > 0) {
    wifiSSID = ssid;
    wifiPassword = pass;
    Serial.printf("[WiFi] 从 NVS 读取到已保存的 WiFi: %s\n", wifiSSID.c_str());
  }
}

void saveWiFiCredentials(String ssid, String pass) {
  preferences.begin("wifipref", false);
  preferences.putString("ssid", ssid);
  preferences.putString("pass", pass);
  preferences.end();
  Serial.printf("[WiFi] 已保存 WiFi 凭证到 NVS: %s\n", ssid.c_str());
}

void clearWiFiCredentials() {
  preferences.begin("wifipref", false);
  preferences.clear();
  preferences.end();
  Serial.println("[WiFi] 已清除 NVS 中的 WiFi 凭证");
}

// ============ WiFi 连接 ============
bool connectWiFi() {
  Serial.printf("\n[WiFi] 连接 %s ...\n", wifiSSID.c_str());

  WiFi.mode(WIFI_STA);
  WiFi.begin(wifiSSID.c_str(), wifiPassword.c_str());
  int tries = 0;
  int frame = 0;
  while (WiFi.status() != WL_CONNECTED && tries < 30) {
    delay(500);
    Serial.print(".");

    // LED 快闪（连接中）
    digitalWrite(LED_PIN, !digitalRead(LED_PIN));

    // 屏幕：思考表情 + 连接中文字
    tft.fillScreen(TFT_BLACK);
    drawFace(TFT_WIDTH / 2, TFT_HEIGHT / 2 - 30, 50, 0, frame); // 思考表情
    tft.setTextColor(TFT_WHITE);
    tft.setTextDatum(MC_DATUM);
    tft.drawString("Connecting WiFi", TFT_WIDTH / 2, TFT_HEIGHT / 2 + 40, 2);
    tft.setTextColor(TFT_CYAN);
    tft.drawString(wifiSSID, TFT_WIDTH / 2, TFT_HEIGHT / 2 + 65, 2);

    // 点动画
    String dots = "";
    for (int i = 0; i < frame % 4; i++) dots += ".";
    tft.setTextColor(TFT_CYAN);
    tft.drawString(dots, TFT_WIDTH / 2, TFT_HEIGHT / 2 + 90, 4);

    frame++;
    tries++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("\n[WiFi] 已连接, IP: %s\n", WiFi.localIP().toString().c_str());
    digitalWrite(LED_PIN, HIGH); // LED 常亮 = 已连接

    // 屏幕：笑脸 + 已连接
    for (int i = 0; i < 12; i++) {
      tft.fillScreen(TFT_BLACK);
      drawFace(TFT_WIDTH / 2, TFT_HEIGHT / 2 - 30, 50, 1, i); // 笑脸
      tft.setTextColor(TFT_GREEN);
      tft.setTextDatum(MC_DATUM);
      tft.drawString("WiFi Connected", TFT_WIDTH / 2, TFT_HEIGHT / 2 + 40, 2);
      tft.setTextColor(TFT_WHITE);
      tft.drawString(WiFi.localIP().toString(), TFT_WIDTH / 2, TFT_HEIGHT / 2 + 65, 2);
      delay(100);
    }
    return true;
  } else {
    Serial.println("\n[WiFi] 连接失败");
    digitalWrite(LED_PIN, LOW); // LED 熄灭 = 未连接

    // 屏幕：哭脸 + 连接失败
    tft.fillScreen(TFT_BLACK);
    drawFace(TFT_WIDTH / 2, TFT_HEIGHT / 2 - 30, 50, 2, 0); // 哭脸
    tft.setTextColor(TFT_RED);
    tft.setTextDatum(MC_DATUM);
    tft.drawString("WiFi Failed", TFT_WIDTH / 2, TFT_HEIGHT / 2 + 40, 2);
    tft.setTextColor(TFT_WHITE);
    tft.drawString("Retrying...", TFT_WIDTH / 2, TFT_HEIGHT / 2 + 65, 2);
    delay(1500);

    WiFi.disconnect();
    return false;
  }
}

// ============ 配网门户（AP 模式）============
const char* configPageHTML = R"HTML(
<!DOCTYPE html>
<html><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>WarpLane IoT 配网</title>
<style>
body{font-family:-apple-system,sans-serif;background:#1a1a2e;color:#eee;margin:0;padding:20px}
.card{max-width:400px;margin:40px auto;background:#16213e;padding:24px;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.4)}
h1{color:#0f3460;margin:0 0 8px;font-size:22px}
.sub{color:#e94560;font-size:13px;margin-bottom:20px}
label{display:block;margin:14px 0 6px;font-size:14px;color:#a0a0c0}
input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #333;background:#0f3460;color:#fff;border-radius:8px;font-size:15px}
input:focus{outline:none;border-color:#e94560}
button{width:100%;padding:14px;margin-top:20px;background:#e94560;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer}
button:active{background:#c73650}
.info{background:#0f3460;padding:12px;border-radius:8px;font-size:12px;color:#a0a0c0;margin-top:16px;line-height:1.6}
.hotspot{color:#e94560;font-weight:600}
</style></head><body>
<div class="card">
<h1>🌡️ WarpLane IoT</h1>
<div class="sub">设备 WiFi 配网</div>
<form action="/save" method="POST">
<label>WiFi 名称 (SSID)</label>
<input type="text" name="ssid" required placeholder="输入 WiFi 名称">
<label>WiFi 密码</label>
<input type="password" name="password" placeholder="输入 WiFi 密码（无密码留空）">
<button type="submit">保存并连接</button>
</form>
<div class="info">
连接步骤：<br>
1. 输入家里/办公室的 WiFi 名称和密码<br>
2. 点击「保存并连接」<br>
3. 设备会自动重启并连接新 WiFi<br><br>
当前热点：<span class="hotspot">%APSSID%</span>
</div>
</div></body></html>
)HTML";

const char* saveSuccessHTML = R"HTML(
<!DOCTYPE html>
<html><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>配网成功</title>
<style>
body{font-family:-apple-system,sans-serif;background:#1a1a2e;color:#eee;margin:0;padding:20px}
.card{max-width:400px;margin:60px auto;background:#16213e;padding:32px;border-radius:16px;text-align:center}
.icon{font-size:60px}
h1{color:#4ecca3;margin:16px 0 8px}
p{color:#a0a0c0;line-height:1.6}
.ssid{color:#e94560;font-weight:600}
</style></head><body>
<div class="card">
<div class="icon">✅</div>
<h1>配网成功！</h1>
<p>WiFi 信息已保存<br>设备正在重启并连接 <span class="ssid">%SSID%</span></p>
<p style="font-size:12px;margin-top:24px">2 秒后设备自动重启...</p>
</div></body></html>
)HTML";

void handleRoot() {
  String html = String(configPageHTML);
  html.replace("%APSSID%", AP_SSID);
  server.send(200, "text/html; charset=utf-8", html);
}

void handleSave() {
  String ssid = server.arg("ssid");
  String pass = server.arg("password");
  ssid.trim();
  if (ssid.length() == 0) {
    server.send(400, "text/plain", "SSID 不能为空");
    return;
  }
  saveWiFiCredentials(ssid, pass);

  String html = String(saveSuccessHTML);
  html.replace("%SSID%", ssid);
  server.send(200, "text/html; charset=utf-8", html);
  Serial.printf("[Portal] 收到 WiFi 配置: %s\n", ssid.c_str());

  // 2 秒后重启
  delay(2000);
  ESP.restart();
}

void startConfigPortal() {
  Serial.println("\n[Portal] 启动配网门户...");

  // 切换到 AP 模式
  WiFi.mode(WIFI_AP);
  WiFi.softAP(AP_SSID, AP_PASSWORD);
  IPAddress apIP = WiFi.softAPIP();
  Serial.printf("[Portal] 热点已开启: %s  IP: %s\n", AP_SSID, apIP.toString().c_str());

  // DNS 服务器：把所有域名解析到 ESP32（实现强制门户）
  dnsServer.start(53, "*", apIP);

  // Web 服务器路由
  server.on("/", handleRoot);
  server.on("/save", HTTP_POST, handleSave);
  server.onNotFound([]() {
    // 所有未匹配的请求都返回配网页面
    String html = String(configPageHTML);
    html.replace("%APSSID%", AP_SSID);
    server.send(200, "text/html; charset=utf-8", html);
  });
  server.begin();
  Serial.println("[Portal] Web 服务器已启动，等待手机连接...");

  // 循环处理请求（阻塞，直到配网成功后重启）
  int portalFrame = 0;
  while (true) {
    dnsServer.processNextRequest();
    server.handleClient();

    // LED 慢闪提示配网模式
    static unsigned long lastBlink = 0;
    if (millis() - lastBlink > 500) {
      digitalWrite(LED_PIN, !digitalRead(LED_PIN));
      lastBlink = millis();
    }

    // 屏幕：眨眼笑脸 + 配网信息（每 10 帧刷新一次，避免闪烁）
    if (portalFrame % 10 == 0) {
      tft.fillScreen(TFT_BLACK);
      drawFace(TFT_WIDTH / 2, 70, 45, 3, portalFrame); // 眨眼笑脸
      tft.setTextColor(TFT_CYAN);
      tft.setTextDatum(MC_DATUM);
      tft.drawString("WiFi Config", TFT_WIDTH / 2, 130, 2);
      tft.setTextColor(TFT_WHITE);
      tft.drawString("Connect to AP:", TFT_WIDTH / 2, 160, 2);
      tft.setTextColor(TFT_YELLOW);
      tft.drawString(AP_SSID, TFT_WIDTH / 2, 190, 2);
      tft.setTextColor(TFT_WHITE);
      tft.drawString("Open 192.168.4.1", TFT_WIDTH / 2, 220, 2);
    }
    portalFrame++;
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
  Serial.println("[Heartbeat] 发送心跳...");
  String url = String(API_BASE) + API_PATH + "?action=heartbeat";
  String resp = httpPost(url, "device_token=" + String(DEVICE_TOKEN));
  if (resp.length() == 0) { Serial.println("[Heartbeat] 请求失败"); return; }

  DynamicJsonDocument doc(256);
  if (deserializeJson(doc, resp)) { Serial.println("[Heartbeat] JSON 解析失败"); return; }

  if (doc["code"] != 200) {
    Serial.printf("[Heartbeat] 错误: %s\n", doc["msg"].as<String>().c_str());
    return;
  }
  Serial.println("[Heartbeat] OK");
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

  // 加载已保存的 WiFi 凭证
  loadWiFiCredentials();

  // BOOT 键检测（GPIO 0）：上电后给 3 秒窗口期，期间按 BOOT 进入配网模式
  // 注意：不能在通电瞬间按 BOOT，那会进入下载模式；要等启动后再按
  pinMode(0, INPUT_PULLUP);
  Serial.println("[Portal] 3秒内按 BOOT 键可进入配网模式...");
  tft.fillScreen(TFT_BLACK);
  tft.setTextColor(TFT_WHITE);
  tft.setTextDatum(MC_DATUM);
  tft.drawString("WarpLane IoT", TFT_WIDTH / 2, TFT_HEIGHT / 2 - 40, 4);
  tft.setTextColor(TFT_CYAN);
  tft.drawString("Press BOOT for", TFT_WIDTH / 2, TFT_HEIGHT / 2 + 10, 2);
  tft.drawString("WiFi Config", TFT_WIDTH / 2, TFT_HEIGHT / 2 + 35, 2);

  bool forceConfig = false;
  for (int i = 0; i < 30; i++) {
    if (digitalRead(0) == LOW) {
      forceConfig = true;
      break;
    }
    delay(100);
  }

  if (forceConfig) {
    Serial.println("[Portal] 检测到 BOOT 键按下，进入配网模式");
    clearWiFiCredentials();
    startConfigPortal();
  }

  // WiFi 连接，失败则进入配网门户
  if (!connectWiFi()) {
    Serial.println("[Portal] WiFi 连接失败，进入配网门户");
    startConfigPortal();
  }

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

  // 0. BOOT 键长按检测：长按 3 秒进入配网模式（随时可换网）
  static unsigned long bootPressedTime = 0;
  static bool bootHandled = false;
  if (digitalRead(0) == LOW) {
    if (bootPressedTime == 0) {
      bootPressedTime = now;
      bootHandled = false;
    }
    if (!bootHandled && now - bootPressedTime >= 3000) {
      bootHandled = true;
      Serial.println("\n[Portal] 长按 BOOT 3 秒，进入配网模式...");
      beep(100);
      clearWiFiCredentials();
      startConfigPortal();
      return;
    }
  } else {
    bootPressedTime = 0;
    bootHandled = false;
  }

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
