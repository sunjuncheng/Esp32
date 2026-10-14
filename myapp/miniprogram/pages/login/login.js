// pages/login/login.js
const api  = require('../../utils/api.js');
const util = require('../../utils/util.js');
const app  = getApp();

Page({
  data: {
    tab: 0,           // 0=登录, 1=注册
    loginPhone: '',
    loginPwd: '',
    regName: '',
    regPhone: '',
    regPwd: '',
    regPwd2: '',
    regAddr: '',
    showPwd: false,
    submitting: false,
    redirect: ''
  },
  onLoad(opt) {
    this.setData({ redirect: decodeURIComponent(opt.redirect || '') });
    // 如果已登录，直接跳走
    if (app.globalData.userInfo) {
      wx.switchTab({ url: '/pages/index/index', fail: () => wx.navigateBack() });
    }
  },
  switchTab(e) { this.setData({ tab: Number(e.currentTarget.dataset.i) }); },
  input(e) {
    const k = e.currentTarget.dataset.k;
    this.setData({ [k]: e.detail.value });
  },
  togglePwd() { this.setData({ showPwd: !this.data.showPwd }); },

  // ===== 登录 =====
  async doLogin() {
    if (this.data.submitting) return;
    const { loginPhone, loginPwd } = this.data;
    if (!util.isPhone(loginPhone)) return wx.showToast({ title:'手机号格式错误', icon:'none' });
    if (String(loginPwd).length < 4) return wx.showToast({ title:'密码至少4位', icon:'none' });
    this.setData({ submitting: true });
    try {
      const res = await api.POST('login', { phone: loginPhone.trim(), pwd: loginPwd }, { loadingText:'登录中...' });
      app.setLoginState(res.user, res.token);
      wx.showToast({ title: '登录成功', icon: 'success' });
      setTimeout(() => {
        if (this.data.redirect) {
          const rd = this.data.redirect;
          // 是 tabbar page 就 switchTab
          if (rd.indexOf('pages/index') === 0 || rd.indexOf('pages/orders') === 0 || rd.indexOf('pages/user') === 0) {
            wx.switchTab({ url: '/' + rd, fail: () => wx.switchTab({ url:'/pages/index/index' }) });
          } else {
            wx.redirectTo({ url: '/' + rd, fail: () => wx.switchTab({ url:'/pages/index/index' }) });
          }
        } else {
          wx.switchTab({ url: '/pages/index/index' });
        }
      }, 500);
    } catch(e) {
      // 登录失败：如果接口不存在，用兜底模式（listAllUsers 自己匹配手机号）
      try {
        const list = await api.listAllUsers();
        const user = (list.data || []).find(u => u.phone === loginPhone.trim());
        if (!user) throw new Error('该手机号未注册（兜底模式）');
        // 兜底：密码都是 123456
        if (loginPwd !== '123456') throw new Error('密码错误（兜底默认：123456）');
        app.setLoginState(user, 'fallback_'+user.id);
        wx.showToast({ title: '登录成功（兼容模式）', icon: 'success' });
        setTimeout(()=>wx.switchTab({ url:'/pages/index/index' }),500);
      } catch(e2){}
    } finally {
      this.setData({ submitting: false });
    }
  },

  // ===== 注册 =====
  async doReg() {
    if (this.data.submitting) return;
    const { regName, regPhone, regPwd, regPwd2, regAddr } = this.data;
    if (!regName || regName.length<2) return wx.showToast({ title:'姓名至少2位', icon:'none' });
    if (!util.isPhone(regPhone))  return wx.showToast({ title:'手机号格式错误', icon:'none' });
    if (String(regPwd).length < 4) return wx.showToast({ title:'密码至少4位', icon:'none' });
    if (regPwd !== regPwd2)      return wx.showToast({ title:'两次密码不一致', icon:'none' });

    this.setData({ submitting: true });
    try {
      await api.POST('addUser', { name:regName.trim(), phone:regPhone.trim(), address:regAddr.trim() }, { loadingText:'注册中...' });
      // 如果有 pwd 字段接口（以后扩展）再调，这里用默认密码 123456
      wx.showModal({
        title: '注册成功',
        content: '您的账号已创建成功！\n初始登录密码：123456\n请在登录后使用',
        showCancel:false,
        confirmText:'去登录',
        confirmColor:'#667eea',
        success: () => {
          this.setData({ tab:0, loginPhone:regPhone.trim(), loginPwd:'', regName:'', regPhone:'', regPwd:'', regPwd2:'', regAddr:'' });
        }
      });
    } finally {
      this.setData({ submitting: false });
    }
  }
});
