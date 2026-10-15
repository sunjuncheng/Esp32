/* ============================================================
 *  WarpLane ESP32 温湿度监控 - 配置文件
 *  ⚠️ 所有需要修改的参数都在这里
 * ============================================================ */

#ifndef __CONFIG_H__
#define __CONFIG_H__

// ============ WiFi 配置 ============
#define WIFI_SSID       "你的WiFi名称"
#define WIFI_PASSWORD   "你的WiFi密码"

// ============ 服务器配置 ============
// 后台 API 地址（不带末尾斜杠）
#define API_BASE        "http://123.56.72.218"
// API 路径
#define API_PATH        "/api/api.php"

// ============ 设备令牌 ============
// 从后台「设备管理」新增设备后复制的 device_token
#define DEVICE_TOKEN    "在这里粘贴你的device_token"

// ============ 引脚定义 ============

// TFT 彩色显示屏 (ST7789 SPI, 240x320)
#define TFT_SCK         18       // SCL - SPI 时钟
#define TFT_MOSI        23       // SDA - SPI 数据 (MOSI)
#define TFT_CS          5        // CS - 片选
#define TFT_DC          16       // DC - 数据/命令
#define TFT_RST         17       // RES - 复位
#define TFT_BL          4        // BLK - 背光控制
#define TFT_WIDTH       240      // 屏幕宽度
#define TFT_HEIGHT      320      // 屏幕高度
#define TFT_ROTATION    0        // 旋转方向 (0=竖屏, 1=横屏右, 2=竖屏倒, 3=横屏左)

// DHT 温湿度传感器
#define DHT_PIN         14       // DHT 数据引脚
#define DHT_TYPE        DHT22    // DHT11 或 DHT22

// 蜂鸣器（无源，低电平触发）
#define BUZZER_PIN      3        // 蜂鸣器引脚
#define BUZZER_ACTIVE   LOW      // 触发电平：低电平响

// 板载 LED（ESP32 Dev Module 一般是 GPIO 2，用于指示 WiFi 状态：
//   连接中 = 快闪，连接成功 = 常亮，连接失败 = 熄灭）
// 注意：部分板子板载 LED 接 5V 不受 GPIO 控制，此时可忽略此功能
#define LED_PIN         2

// ============ 运行参数（会被后台配置覆盖，这里是默认值）============
#define DEFAULT_REPORT_INTERVAL   10   // 默认上报间隔（秒）
#define DEFAULT_ALARM_DURATION    30    // 默认蜂鸣器持续时间（秒）

#endif
