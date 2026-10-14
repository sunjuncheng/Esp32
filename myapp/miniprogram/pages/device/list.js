// pages/device/list.js
const api = require('../../utils/api.js');
const app = getApp();

Page({
  data: {
    devices: [],
    loading: false,
    user: null
  },
  onShow(){
    try {
      const u = app.globalData.userInfo || wx.getStorageSync('userInfo') || null;
      if(!u){
        wx.showModal({ title:'请先登录', content:'登录后才能管理您的设备', showCancel:false,
          success:()=>{ wx.navigateTo({ url:'/pages/login/login?redirect='+encodeURIComponent('pages/device/list') }); }
        });
        return;
      }
      this.setData({ user: u });
      this.loadDevices();
    } catch(e){ console.error('[device-list] onShow', e); }
  },
  onPullDownRefresh(){
    this.loadDevices(()=>wx.stopPullDownRefresh());
  },
  async loadDevices(cb){
    try {
      this.setData({ loading: true });
      const res = await api.listMyDevices(this.data.user.id);
      const list = (res.data || []).map(d => ({
        id: d.id,
        name: d.device_name,
        no: d.device_no,
        online: d.online == 1,
        temp: d.last_temp !== null && d.last_temp !== undefined ? Number(d.last_temp) : null,
        humidity: d.last_humidity !== null && d.last_humidity !== undefined ? Number(d.last_humidity) : null,
        lastTime: d.last_report_time || '从未上报',
        alarmToday: d.alarm_today || 0
      }));
      this.setData({ devices: list, loading: false });
    } catch(e){
      console.error('[device-list] loadDevices', e);
      this.setData({ loading: false });
    }
    (typeof cb === 'function') && cb();
  },
  goBind(){
    wx.navigateTo({ url: '/pages/device/bind' });
  },
  goDetail(e){
    const id = e.currentTarget.dataset.id;
    wx.navigateTo({ url: '/pages/device/detail?id=' + id });
  },
  unbind(e){
    const id = e.currentTarget.dataset.id;
    const name = e.currentTarget.dataset.name;
    wx.showModal({
      title:'解绑设备',
      content:'确认解绑「'+name+'」？解绑后将不再接收该设备的数据。',
      confirmColor:'#ef4444',
      success: async (r) => {
        if(!r.confirm) return;
        try {
          await api.unbindDevice({ user_id: this.data.user.id, device_id: id });
          wx.showToast({ title:'已解绑', icon:'success' });
          this.loadDevices();
        } catch(e){}
      }
    });
  }
});
