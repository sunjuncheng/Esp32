// utils/api.js - 封装统一 API 请求
const app = () => getApp();

// ========== 后端接口路径配置 ==========
// 如果 api.php 放在 网站根目录/api/ 子目录（你现在的结构），下面这个不用改
// 如果以后改成根目录，改成 '/api.php'
const API_PATH = '/api/api.php';
// ======================================

function getBase() {
  try { return (app() && app().globalData && app().globalData.apiBase) || ''; }
  catch(e){ return ''; }
}

/**
 * 统一请求函数
 * @param {string} action    接口名，对应 api.php?action=xxx
 * @param {string} method    GET / POST
 * @param {object} data      提交的数据
 * @param {object} opts      选项：showLoading / loadingText / showErr
 */
function request(action, method='GET', data={}, opts={}) {
  const base = getBase();
  const url  = base + API_PATH + '?action=' + action;

  opts = Object.assign({
    showLoading: true,
    loadingText: '加载中...',
    showErr: true,
    header: {}
  }, opts || {});

  if (opts.showLoading) {
    wx.showLoading({ title: opts.loadingText, mask: true });
  }

  const header = Object.assign({
    'content-type': method === 'POST'
      ? 'application/x-www-form-urlencoded'   // 对应后端 $_POST
      : 'application/json'
  }, opts.header || {});

  // 把 token 放进 header，后端暂时没用到，但以后扩展方便
  try {
    const tk = (app() && app().globalData.token) || wx.getStorageSync('token') || '';
    if (tk) header['X-Token'] = tk;
  } catch(e){}

  return new Promise((resolve, reject) => {
    wx.request({
      url, method, data, header,
      success: (res) => {
        wx.hideLoading();
        let body = res.data;
        // 容错：后端偶尔会因为 notice 导致前面有多余字符
        if (typeof body === 'string') {
          try {
            const first = body.indexOf('{');
            if (first > 0) body = body.substring(first);
            body = JSON.parse(body);
          } catch(e) {
            if (opts.showErr) wx.showModal({ title:'响应错误', content: '服务器返回非 JSON：\n'+String(res.data).slice(0,300), showCancel:false });
            reject(new Error('non-json'));
            return;
          }
        }
        if (!body || typeof body !== 'object') {
          if (opts.showErr) wx.showToast({ title:'响应格式错误', icon:'none' });
          reject(body); return;
        }
        if (body.code !== 200) {
          if (opts.showErr) {
            wx.showToast({
              title: (body.msg || '请求失败') + '',
              icon: 'none',
              duration: 2200
            });
          }
          reject(body);
          return;
        }
        resolve(body);
      },
      fail: (err) => {
        wx.hideLoading();
        console.error('[API FAIL]', action, err);
        let msg = '网络连接失败，请检查网络';
        if (err && err.errMsg) {
          if (err.errMsg.indexOf('timeout')>=0)  msg = '请求超时';
          if (err.errMsg.indexOf('DNS')>=0)      msg = '域名解析失败';
          if (err.errMsg.indexOf('not in domain')>=0 || err.errMsg.indexOf('合法域名')>=0)
            msg = '域名未加入白名单，请在详情勾选「不校验合法域名」或配置HTTPS';
        }
        if (opts.showErr) {
          wx.showModal({
            title: '网络请求失败',
            content: msg + '\n\n接口：' + action + '\n地址：' + url,
            showCancel: false,
            confirmText: '我知道了'
          });
        }
        reject(err);
      }
    });
  });
}

// ===== 便捷方法别名 =====
const GET  = (a, d, o) => request(a, 'GET',  d || {}, o || {});
const POST = (a, d, o) => request(a, 'POST', d || {}, o || {});

// ==================================================
//         下面是封装好的具体接口，页面直接调用
// ==================================================

// 1. 获取商品列表（商城首页用，后端没有商品接口，这里临时做：用 HTML 爬？不——所以我们要扩展 api.php，这里留好接口占位）
//    既然 api.php 暂时没有 getGoods 接口，我们提供一个「兜底列表页」方式：
//    方式 A：你可以把后端的 shop.php 返回的列表改成 JSON 接口
//    方式 B：这里我提供一个后端扩展：在 api.php 里增加 case 'getGoods' 等，我们的小程序调用这些

module.exports = {
  request, GET, POST, API_PATH,

  /* -------- 商品 -------- */
  // 获取商品列表（需要 api.php 新增 getGoods 接口；如未加请见 README 提供的 SQL 或自己实现）
  getGoods: (data={}) => GET('getGoods', data),
  // 获取单个商品详情
  getGoodsDetail: (id) => GET('getGoods', { id }),

  /* -------- 用户 -------- */
  // 新增用户（注册）
  regUser: (data) => POST('addUser', data),
  // 登录（因为 api.php 暂无 login 接口，我们约定先用 getUser + 手机号匹配 + 密码存在 user_list.pwd 字段
  // 因此这里单独提供一个「兜底登录请求」，实际建议后端新增 login 接口，我们在页面里兼容两种）
  // 登录接口（推荐后端加）
  login: (data) => POST('login', data),
  // 获取全部用户（兜底匹配登录用）
  listAllUsers: () => GET('getUser', {}, { loadingText:'正在登录...' }),
  // 查询用户+关联订单（个人中心）
  getUserWithOrders: (userId) => GET('getUserWithOrder', { user_id: userId }),

  /* -------- 订单 -------- */
  // 获取全部订单（需要按用户ID过滤，我们在页面根据登录用户过滤）
  listOrders: () => GET('getOrder', {}, { loadingText:'加载订单...' }),
  // 新增订单（商城下单用）
  createOrder: (data) => POST('addOrder', data),
  // 修改订单
  updateOrder: (data) => POST('editOrder', data),
  // 删除订单
  deleteOrder: (id) => POST('delOrder', { id }),

  /* -------- 为了小程序下单，封装的「余额支付」请求 -------- */
  // 说明：后端 buy.php 是页面直接提交表单的，我们需要改为 API，这里的 payOrder 需要你在 api.php 加 case 'payOrder'
  // 若暂未加，则小程序里使用 buy.php 的 HTML 表单模式在 web-view 里打开（不推荐）
  payOrder: (data) => POST('payOrder', data, { loadingText:'正在支付...' }),

  /* -------- 地址管理 -------- */
  listAddress: (userId, opts={}) => GET('getAddress', { user_id: userId }, Object.assign({ loadingText:'加载地址...' }, opts)),
  addAddress: (data) => POST('addAddress', data, { loadingText:'保存中...' }),
  editAddress: (data) => POST('editAddress', data, { loadingText:'保存中...' }),
  delAddress: (data) => POST('delAddress', data, { loadingText:'删除中...' }),
  setDefaultAddress: (data) => POST('setDefaultAddress', data, { loadingText:'设置中...' }),

  /* -------- 订单退款 -------- */
  applyRefund: (data) => POST('applyRefund', data, { loadingText:'提交退款申请...' }),
  adminRefund: (data) => POST('adminRefund', data, { loadingText:'退款中...' }),

  /* -------- IoT 设备管理 -------- */
  // 绑定设备（输入 device_token）
  bindDevice: (data) => POST('bindDevice', data, { loadingText:'绑定中...' }),
  // 解绑设备
  unbindDevice: (data) => POST('unbindDevice', data, { loadingText:'解绑中...' }),
  // 我的设备列表
  listMyDevices: (userId) => GET('getDeviceList', { user_id: userId }, { loadingText:'加载设备...' }),
  // 设备详情
  getDevice: (id) => GET('getDevice', { id }, { loadingText:'加载中...' }),
  // 温湿度历史数据
  getSensorData: (deviceId, opts={}) => GET('getSensorData', Object.assign({ device_id: deviceId, limit: 200 }, opts), { showLoading: false }),
  // 报警日志
  getAlarmLog: (deviceId, opts={}) => GET('getAlarmLog', Object.assign({ device_id: deviceId, limit: 50 }, opts), { loadingText:'加载报警...' }),
  // 设备统计
  getDeviceStats: (deviceId) => GET('getDeviceStats', { device_id: deviceId }, { showLoading: false })
};
