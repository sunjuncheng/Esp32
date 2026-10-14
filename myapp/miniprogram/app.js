// app.js - 小程序全局入口
const api = require('./utils/api.js');

App({
  onLaunch() {
    try {
      console.log('[app] onLaunch start');
      const userInfo = wx.getStorageSync('userInfo') || null;
      const token    = wx.getStorageSync('token') || null;
      this.globalData.userInfo = userInfo;
      this.globalData.token    = token;
      console.log('[Zaslon 商城] 小程序启动，登录态:', !!userInfo);
      console.log('[app] onLaunch ok');
    } catch(e) {
      console.error('[app] onLaunch error:', e);
    }
  },

  // 统一登录校验：需要登录时调用
  requireLogin(fromPage, redirect) {
    if (this.globalData.userInfo && this.globalData.userInfo.id) {
      return true;
    }
    wx.showModal({
      title: '需要登录',
      content: '此操作需要先登录账号，是否立即前往登录？',
      confirmText: '去登录',
      confirmColor: '#667eea',
      success: (res) => {
        if (res.confirm) {
          wx.navigateTo({
            url: '/pages/login/login?redirect=' + encodeURIComponent(redirect || fromPage)
          });
        }
      }
    });
    return false;
  },

  // 退出登录
  logout() {
    this.globalData.userInfo = null;
    this.globalData.token    = null;
    wx.removeStorageSync('userInfo');
    wx.removeStorageSync('token');
    wx.showToast({ title: '已退出登录', icon: 'none' });
  },

  // 登录成功后写入
  setLoginState(user, token) {
    this.globalData.userInfo = user;
    this.globalData.token    = token || ('t_' + Date.now());
    wx.setStorageSync('userInfo', user);
    wx.setStorageSync('token', this.globalData.token);
  },

  globalData: {
    // ======= ⚠️ 请修改下面这个 API 地址为你的服务器地址！=======
    // 本地调试阶段：请在微信开发者工具右上角「详情-本地设置」勾选 ☑️ 不校验合法域名
    // 正式发布：需要在小程序后台「开发管理-开发设置-服务器域名」把 HTTPS 域名加入 request合法域名
    apiBase: 'http://123.56.72.218',
    userInfo: null,
    token: null
  }
});
