// pages/device/bind.js
const api = require('../../utils/api.js');
const app = getApp();

Page({
  data: {
    token: '',
    submitting: false,
    user: null
  },
  onLoad(){
    const u = app.globalData.userInfo || wx.getStorageSync('userInfo') || null;
    this.setData({ user: u });
  },
  onInput(e){
    this.setData({ token: e.detail.value.trim() });
  },
  onScan(){
    if(wx.scanCode){
      wx.scanCode({
        onlyFromCamera: true,
        scanType: ['qrCode'],
        success: (res) => {
          this.setData({ token: (res.result || '').trim() });
        },
        fail: ()=>{}
      });
    }
  },
  async onSubmit(){
    if(!this.data.user){
      wx.showModal({ title:'请先登录', showCancel:false });
      return;
    }
    if(!this.data.token){
      wx.showToast({ title:'请输入设备令牌', icon:'none' });
      return;
    }
    try {
      this.setData({ submitting: true });
      const res = await api.bindDevice({ user_id: this.data.user.id, device_token: this.data.token });
      wx.showModal({
        title:'绑定成功',
        content:'已绑定设备：'+(res.device_name || '')+'\n点击确定查看设备详情',
        showCancel:false,
        confirmColor:'#667eea',
        success:()=>{
          wx.redirectTo({ url:'/pages/device/detail?id='+res.device_id });
        }
      });
    } catch(e){
      console.error('[bind] error', e);
    } finally {
      this.setData({ submitting: false });
    }
  }
});
