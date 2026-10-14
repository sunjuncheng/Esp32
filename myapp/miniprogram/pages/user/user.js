// pages/user/user.js
const api  = require('../../utils/api.js');
const util = require('../../utils/util.js');
const app  = getApp();

Page({
  data: {
    user: null,
    userAvatarChar: 'U',
    balanceText: '0.00',
    stat: {
      total: 0, paid: 0, shipped: 0, done: 0
    },
    orderCount: [0,0,0,0],
    loading: false,
    inited: false,
    defaultAddrText: ''
  },
  onShow(){
    try {
      console.log('[user] onShow start');
      this.loadUser();
      this.loadStat();
      this.loadDefaultAddr();
      this.setData({ inited: true });
      console.log('[user] onShow ok');
    } catch(e) {
      console.error('[user] onShow error:', e);
    }
  },
  onPullDownRefresh(){
    try { this.loadStat(()=>wx.stopPullDownRefresh()); }
    catch(e) { wx.stopPullDownRefresh(); }
  },

  _fmtMoney(v){ return Number(v||0).toFixed(2); },
  _avatarChar(name){
    try {
      const n = name || 'U';
      return n.charAt(0);
    } catch(e) { return 'U'; }
  },

  loadUser(){
    try {
      const u = app.globalData.userInfo || wx.getStorageSync('userInfo') || null;
      this.setData({
        user: u,
        userAvatarChar: this._avatarChar(u ? u.name : ''),
        balanceText: this._fmtMoney(u ? u.balance : 0)
      });
    } catch(e) {
      console.error('[user] loadUser error:', e);
    }
  },

  async loadStat(cb){
    try {
      if (!this.data.user){
        this.setData({ loading: false });
        (typeof cb === 'function') && cb();
        return;
      }
      this.setData({ loading: true });
      const res = await api.listOrders();
      const uid = this.data.user.id;
      const mine = (res.data || []).filter(o => String(o.user_id) === String(uid));
      const allNum = mine.length;
      const shipped = mine.filter(o=> Number(o.express_no) && String(o.express_no).trim()!=='').length;
      const doneNum = mine.filter(o=>Number(o.status)===3).length;
      try {
        const ures = await api.GET('getUser', {}, { showLoading:false });
        const me = (ures.data || []).find(x => Number(x.id) === uid);
        if (me && me.balance !== undefined) {
          const u = { ...this.data.user, balance: Number(me.balance) };
          app.setLoginState(u, (app.globalData.token || 't_'));
          this.setData({ user: u, balanceText: this._fmtMoney(me.balance) });
        }
      } catch(e){}
      this.setData({
        orderCount: [allNum - shipped - doneNum, shipped, doneNum, allNum],
        loading: false
      });
    } catch(e) {
      console.error('[user] loadStat error:', e);
      this.setData({ loading: false });
    }
    (typeof cb === 'function') && cb();
  },

  goLogin(){ wx.navigateTo({ url:'/pages/login/login?redirect='+encodeURIComponent('pages/user/user') }); },
  goLogout(){
    wx.showModal({
      title:'确认退出？', content:'是否确认退出当前登录状态？',
      confirmColor:'#ef4444',
      success: (r)=>{ if(r.confirm){ app.logout(); this.loadUser(); }}
    });
  },

  // 跳订单对应Tab
  goOrders(e){
    const t = Number(e.currentTarget.dataset.t || -1);
    wx.switchTab({
      url:'/pages/orders/orders',
      success:()=>{
        const page = getCurrentPages().pop();
        if (page && page.setData && t>=0) {
          page.setData({ activeTab: t });
          page.loadOrders && page.loadOrders();
        }
      }
    });
    // switchTab 没法带参数，用缓存中转
    wx.setStorageSync('order_tab', t);
  },

  goRecharge(){
    wx.showModal({
      title:'充值指引',
      content:'小程序端暂不支持微信支付自助充值（需开通微信商户号）。\n\n请联系客服或网站后台管理员为您的账户充值余额。\n\n管理员入口：网站后台 → 用户管理 → 用户充值',
      showCancel:false, confirmText:'我知道了', confirmColor:'#667eea'
    });
  },

  copyPhone(){
    if (!this.data.user) return;
    util.copyText(this.data.user.phone || '', '手机号已复制');
  },

  async loadDefaultAddr(){
    try {
      if(!this.data.user) return;
      const r = await api.listAddress(this.data.user.id, { showLoading:false });
      const def = r.default || null;
      let txt = '';
      if(def){
        txt = (def.name||'') + ' ' + (def.phone||'') + '\n' + (def.regionText||'') + (def.detail||'');
      } else if(this.data.user && this.data.user.address){
        txt = this.data.user.address;
      }
      this.setData({ defaultAddrText: txt });
    } catch(e){}
  },
  goAddress(){
    if(!this.data.user) return app.requireLogin('/pages/user/user','/pages/user/user');
    wx.navigateTo({ url:'/pages/address/address' });
  },
  goDevice(){
    if(!this.data.user) return app.requireLogin('/pages/user/user','/pages/user/user');
    wx.navigateTo({ url:'/pages/device/list' });
  },
  goBack(){ wx.navigateBack({ fail:()=>wx.switchTab({url:'/pages/index/index'}) }); }
});
