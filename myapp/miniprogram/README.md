# 🛍️ Zaslon 商城 微信小程序 · 使用手册

## 📦 文件位置

```
/myapp/miniprogram/  ← 这一整个文件夹就是小程序项目根目录
```

---

## 🚀 第 1 步：用微信开发者工具打开

1. 下载「微信开发者工具」：https://developers.weixin.qq.com/miniprogram/dev/devtools/download.html
2. 打开工具 → **"+" 新建/导入项目**
   - **项目目录**：选择本文件夹 `myapp/miniprogram/`
   - **AppID**：如果还没有小程序账号，点「**测试号**」就可以直接体验！
   - **项目名称**：随便填，比如 `Zaslon商城`
3. 点「确定」→ 小程序立刻在模拟器里跑起来 ✨

> 💡 **不需要**真的有小程序 AppID，用「测试号」可以 100% 体验所有功能。

---

## 🛠️ 第 2 步：勾选「不校验合法域名」（本地调试必做！）

工具右上角 → **「详情」** → **「本地设置」** Tab：

✅ **一定要勾选！**
```
☑️ 不校验合法域名、web-view（业务域名）、TLS版本以及HTTPS证书
```

否则调用后端 API 的时候会报：
```
request 合法域名校验出错 / URL 不在以下 request 合法域名列表中
```

---

## 🔌 第 3 步：配置你的服务器 API 地址

打开文件 `miniprogram/app.js` → 拉到最下面，**修改成你自己的服务器域名**：

```js
globalData: {
  // ⚠️ 改成你自己的服务器地址！
  // 注意：正式发布时必须是 HTTPS；本地测试可以用 HTTP
  apiBase: 'http://123.56.72.218',
  ...
}
```

> 📌 后端 API 路径规则：自动拼接 `apiBase + '/api/api.php'`，如果你把 api.php 放在根目录，请修改 `utils/api.js` 里的：
> ```js
> const API_PATH = '/api.php';  // 原来是 /api/api.php
> ```

---

## 💽 第 4 步：上传后端最新版 api.php + 升级数据库

小程序用的几个接口（`getGoods` / `login` / `payOrder`）是**新增加的**，你需要：

1. 把本地 `myapp/api.php` **上传覆盖**服务器上的旧版
2. 把本地 `myapp/upgrade_db.php` 上传到网站根目录
3. 浏览器访问一次：
   ```
   http://你的域名/upgrade_db.php
   ```
   → 看到大量绿色 ✅ 就说明数据库字段全部就绪了
4. **上传完记得删了 upgrade_db.php！**（安全）

> 如果你还没给用户表加 `pwd`（密码）、`balance`（余额）字段，**先执行升级脚本**，否则登录/支付会报错。

---

## 🗂️ 小程序页面 & 功能清单

| Tab 页 | 路径 | 功能 |
|---|---|---|
| 🛍️ 商城首页 | pages/index/index | 搜索栏 / 分类筛选 / HOT热销徽章 / 商品卡片网格 / 下拉刷新 |
| 🧾 订单列表 | pages/orders/orders | 4 个分类（全部/待发货/已发货/已完成）+ 数量小红点 / 物流信息卡 / 复制运单号 / 进入详情 |
| 👤 个人中心 | pages/user/user | 渐变头卡 + 余额大数字 / 充值入口 / 4 个订单入口 / 功能菜单 / 退出登录 |

| 子页面 | 路径 | 功能 |
|---|---|---|
| 📦 商品详情 | pages/detail/detail | 大图 / 价格划线 / 库存 / 余额显示 / 数量加减 / 收货信息 / **立即购买（事务扣余额+扣库存+生成订单）** |
| 🧾 订单详情 | pages/order-detail/order-detail | 状态大卡 / 商品信息 / 收货信息 / **5步物流时间线** / 运单号一键复制 / 拨打收货电话 / 金额明细 / 复制订单号 |
| 🔐 登录注册 | pages/login/login | 登录注册 Tab 切换 / 渐变背景 / 表单校验 / 注册成功后引导登录 |

---

## 🔁 后端需要的接口（小程序 → api.php）

| 接口 | 方法 | 入参 | 说明 |
|---|---|---|---|
| `getGoods` | GET | id / kw / category | 商品列表 / 商品详情 / 关键词搜索 / 分类筛选 |
| `login` | POST | phone, pwd | **手机号+密码**登录；返回 token + user |
| `addUser` | POST | name, phone, address | 注册账号；默认密码 `123456` |
| `getUser` | GET | - | 全部用户；个人中心用来刷新最新余额 |
| `payOrder` | POST | **user_id, goods_id, qty** + ship信息 | **核心购买**：MySQL 事务（扣余额→扣库存→写订单） |
| `getOrder` | GET | - | 全部订单；小程序端按 `user_id` 自己过滤属于当前用户的 |

> 💡 上面 6 个接口已经全部写好在 `api.php` 里了，**只需要上传新版 api.php 即可**，不用自己写！

---

## 🧪 快速联调 Checklist

- [ ] 1. 微信开发者工具「导入项目」选 `miniprogram/` 文件夹
- [ ] 2. **详情 → 本地设置 → ☑️ 不校验合法域名** （不勾选会连不上服务器）
- [ ] 3. `app.js` 里的 `apiBase` 改成你服务器的正确地址（含 http/https）
- [ ] 4. 服务器上传：**新版 api.php**（新增 getGoods/login/payOrder 三个接口）
- [ ] 5. 服务器访问一次 **upgrade_db.php** 升级表结构
- [ ] 6. 后台新增几个商品（商品管理 → 新增商品）
- [ ] 7. 后台充值给自己的用户一点余额（用户充值 → 选用户 → 输金额）
- [ ] 8. 小程序首页 → 进商品 → 立即购买 → 支付成功 → 订单列表看订单
- [ ] 9. 后台「订单管理」→ 点 📦 物流 → 填快递公司/运单号 → **回到小程序订单详情就能看到 5 步物流时间线！**

---

## 🎨 想给 tabBar 加图标（可选）

现在 tabBar 用 emoji（🛍️🧾👤）显示已经很好看，如果你想要真实的 PNG 小图标：

1. 新建文件夹 `miniprogram/assets/`
2. 放 6 张 PNG（建议 81×81 像素，透明背景）：
   ```
   tab_shop.png      tab_shop_on.png
   tab_order.png     tab_order_on.png
   tab_user.png      tab_user_on.png
   ```
3. 修改 `app.json` 的 `tabBar.list`，给每项加回去：
   ```json
   {
     "pagePath": "pages/index/index",
     "text": "商城",
     "iconPath": "assets/tab_shop.png",
     "selectedIconPath": "assets/tab_shop_on.png"
   }
   ```

---

## 🎯 常见报错 & 解决方案

| 报错 | 解决 |
|---|---|
| **request:fail url not in domain list** | 看「第 2 步」，勾选不校验合法域名 |
| **getGoods 接口不存在 404** | 你还在用旧版 api.php，**上传新版 api.php** |
| **登录报错「字段 pwd 不存在」** | 没执行升级脚本，访问一次 upgrade_db.php |
| **支付报错「Unknown column ship_name」** | 同上，升级数据库字段 |
| **商品列表显示 6 个「演示商品」** | 说明 getGoods 接口不通，检查 api 路径是否正确 / api.php 是否是新版 |
| **购买时提示余额不足** | 去网站后台「用户充值」给你自己的账号充点钱 |

---

## 💎 想上线（发布到真实小程序）时要做的事

1. 注册小程序账号拿到 AppID：https://mp.weixin.qq.com/
2. 服务器配置 **SSL 证书（开启 HTTPS）**
3. 小程序后台 → 开发管理 → 开发设置 → **服务器域名 → request合法域名**：填入
   ```
   https://你的域名
   ```
   （不能带端口，必须 HTTPS）
4. 在 `app.js` 里把 `apiBase` 改成 `https://你的域名`
5. 开发者工具点「上传」→ 小程序后台提交审核 → 审核通过就可以发布啦！

🎉 恭喜你，一个完整的 B2C 商城系统（网站 + 后台 + API + 小程序四端打通）就搞定了！
