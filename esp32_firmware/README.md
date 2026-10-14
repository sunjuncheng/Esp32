# WarpLane ESP32 温湿度监控固件 (ST7789 彩色屏版)

## 📋 功能清单
- 🌡️ 读取 DHT11 / DHT22 温湿度传感器
- 📺 **ST7789 2.4寸 240x320 彩色屏**实时显示温湿度 + 动画
- 📡 定时上报数据到后台服务器
- 🔔 超阈值时蜂鸣器报警（时长由后台配置）
- ⚙️ 自动从后台拉取阈值/报警时长/上报间隔配置
- 💓 心跳保活

---

## 🔌 接线图

### 所需元件
| 元件 | 数量 | 说明 |
|---|---|---|
| ESP32 开发板 | 1 | 建议用 ESP32-WROOM-32 |
| DHT11 / DHT22 | 1 | 温湿度传感器 |
| **ST7789 2.4寸 TFT 彩屏** | 1 | SPI 接口，240x320 |
| 有源蜂鸣器 | 1 | 3.3V/5V 均可 |
| 面包板 + 杜邦线 | 若干 | |

### 引脚连接

#### ST7789 TFT 彩色屏（8 个引脚）
| 屏幕引脚 | ESP32 引脚 | 说明 |
|---|---|---|
| GND | GND | 接地 |
| SCL | GPIO 18 | SPI 时钟 (SCK) |
| SDA | GPIO 23 | SPI 数据 (MOSI) |
| RES | GPIO 17 | 复位 (RST) |
| DC | GPIO 16 | 数据/命令 |
| CS | GPIO 5 | 片选 |
| BLK | GPIO 4 | 背光控制 |
| 3V3 | 3V3 | 电源 |

#### DHT 温湿度传感器
| DHT 引脚 | ESP32 引脚 | 说明 |
|---|---|---|
| VCC | 3V3 | 电源 |
| DATA | GPIO 14 | 数据脚（接 4.7KΩ 上拉电阻到 3V3） |
| GND | GND | 接地 |

#### 蜂鸣器（无源，低电平触发）
| 蜂鸣器引脚 | ESP32 引脚 |
|---|---|
| VCC | 3V3 |
| GND | GND |
| SIG | GPIO 3 |

---

## 📚 依赖库

在 Arduino IDE 中安装以下库（工具 → 管理库）：

| 库名 | 作者 | 用途 |
|---|---|---|
| **DHT sensor library** | Adafruit | DHT 温湿度读取 |
| **Adafruit Unified Sensor** | Adafruit | DHT 依赖 |
| **TFT_eSPI** | Bodmer | ST7789 彩屏驱动 |
| **ArduinoJson** | Benoit Blanchon | JSON 解析（选 6.x 版本） |

> ⚠️ **TFT_eSPI 无需修改库配置！** 引脚和驱动已在代码顶部配置好，直接安装库即可使用。

---

## 🚀 烧录步骤

### 1. 安装 Arduino IDE + ESP32 支持
1. 下载 [Arduino IDE](https://www.arduino.cc/en/software)
2. 文件 → 首选项 → 附加开发板管理器网址，填入：
   ```
   https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json
   ```
3. 工具 → 开发板 → 开发板管理器 → 搜索 `esp32` → 安装

### 2. 修改配置文件 `config.h`
```cpp
#define WIFI_SSID       "你的WiFi名称"
#define WIFI_PASSWORD   "你的WiFi密码"
#define API_BASE        "http://你的服务器IP"
#define DEVICE_TOKEN    "后台生成的device_token"
```

### 3. 选择开发板并烧录
- 工具 → 开发板 → `ESP32 Dev Module`
- 工具 → 端口 → 选择你的 ESP32 对应的 COM 口
- 点上传按钮（→）

### 4. 打开串口监视器
- 波特率选 **115200**
- 可以看到启动日志、WiFi 连接、数据上报等信息

---

## 🖥️ 屏幕显示说明

```
┌─────────────────────────┐
│ WarpLane          WiFi  │  ← 顶部渐变标题栏 + WiFi状态
│                         │
│  TEMPERATURE            │
│  26.5°C        [温度计柱]│  ← 红色大字体温度 + 温度计动画
│                         │
│ ─────────────────────── │
│                         │
│  HUMIDITY               │
│  55%          [水滴💧]  │  ← 青色大字体湿度 + 水滴下落动画
│                         │
│ T:-40~85 H:0~100        │  ← 底部状态栏：阈值范围
│ Int:30s Rpt:10s  NORMAL │  ← 报警时长 / 上报间隔 / 状态
└─────────────────────────┘
```

**报警时：** 整屏红色闪烁 + 底部显示 `!! ALARM !!`

---

## ⚙️ 配置说明

### `config.h` 全部参数
```cpp
#define WIFI_SSID         // WiFi 名称
#define WIFI_PASSWORD     // WiFi 密码
#define API_BASE          // 服务器地址（如 http://123.56.72.218）
#define API_PATH          // API 路径（/api/api.php）
#define DEVICE_TOKEN      // 设备令牌（后台生成）

#define TFT_SCK   18      // ST7789 SCL
#define TFT_MOSI  23      // ST7789 SDA
#define TFT_CS    5       // ST7789 CS
#define TFT_DC    16      // ST7789 DC
#define TFT_RST   17      // ST7789 RES
#define TFT_BL    4       // ST7789 BLK
#define TFT_ROTATION 0    // 屏幕方向 (0=竖屏, 1=横屏右, 2=竖屏倒, 3=横屏左)

#define DHT_PIN   14      // DHT 数据引脚
#define DHT_TYPE  DHT22   // DHT11 或 DHT22
#define BUZZER_PIN 3      // 蜂鸣器引脚（低电平触发）
```

---

## 🔄 运行逻辑

```
ESP32 上电
  │
  ├─ 连接 WiFi
  ├─ 初始化 ST7789 彩屏 + DHT + 蜂鸣器
  ├─ 拉取后台配置（阈值、报警时长、上报间隔）
  │
  └─ 主循环：
       ├─ 每 2 秒读取温湿度
       ├─ 每 report_interval 秒上报到服务器
       │    └─ 服务器返回 alarm=1 → 蜂鸣器响 alarm_duration 秒
       ├─ 每 30 秒发心跳
       ├─ 每 5 分钟重新拉取配置（后台改阈值自动生效）
       └─ 每 100ms 刷新彩屏
```

---

## 🐛 常见问题

### Q: 屏幕全白/不亮？
A: 检查 3V3 和 GND 是否接对，BLK 背光脚是否接了 GPIO 4 或直接接 3V3。

### Q: 屏幕花屏/显示异常？
A: 检查 SPI 接线（SCL→18, SDA→23），确认引脚没有接错。

### Q: 屏幕方向不对？
A: 修改 `config.h` 里的 `TFT_ROTATION`（0/1/2/3）。

### Q: 温度显示 NAN？
A: DHT 接线问题，检查数据脚是否接 GPIO 14、上拉电阻是否接了。

### Q: 上报失败？
A: 检查 WiFi 是否连上、API_BASE 是否正确、服务器是否放行了对应端口。

### Q: 蜂鸣器一直响？
A: 阈值设置太严，去后台调宽温湿度范围；或者检查 DHT 读数是否异常。
