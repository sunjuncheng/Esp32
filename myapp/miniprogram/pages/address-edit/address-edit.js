const app = getApp();
const api = require('../../utils/api.js');

Page({
  data: {
    mode: 'add', // add | edit
    id: 0,
    name: '',
    phone: '',
    region: [],
    regionText: '',
    detail: '',
    is_default: false,
    loading: false,
    saving: false
  },

  onLoad(opts){
    this.setData({
      mode: (opts && opts.mode === 'edit') ? 'edit' : 'add',
      id: Number(opts && opts.id || 0)
    });
    wx.setNavigationBarTitle({ title: this.data.mode === 'edit' ? '编辑地址' : '新增收货地址' });
    if(this.data.mode === 'edit' && this.data.id>0){
      this.loadDetail();
    } else {
      this.setData({ loading:false });
    }
  },

  async loadDetail(){
    const user = (app.globalData && app.globalData.userInfo) || null;
    if(!user) return app.requireLogin();
    try {
      this.setData({ loading:true });
      const r = await api.listAddress(user.id, { showLoading:false });
      const list = r.data || [];
      const a = list.find(x => Number(x.id) === Number(this.data.id));
      if(!a){
        wx.showToast({ title:'地址不存在', icon:'none' });
        setTimeout(()=>wx.navigateBack(), 1000);
        return;
      }
      this.setData({
        name: a.name || '',
        phone: a.phone || '',
        region: a.region || [a.province||'',a.city||'',a.district||''],
        regionText: (a.province||'') + ' / ' + (a.city||'') + ' / ' + (a.district||''),
        detail: a.detail || '',
        is_default: !!Number(a.is_default),
        loading: false
      });
    } catch(e){
      this.setData({ loading:false });
    }
  },

  inp(e){
    const k = e.currentTarget.dataset.k;
    this.setData({ [k]: e.detail.value });
  },

  onRegion(e){
    const arr = e.detail.value || [];
    this.setData({
      region: arr,
      regionText: arr.join(' / ')
    });
  },

  onToggleDefault(){
    this.setData({ is_default: !this.data.is_default });
  },

  async onSave(){
    const user = (app.globalData && app.globalData.userInfo) || null;
    if(!user) return app.requireLogin();
    const { name, phone, region, detail, mode, id, is_default } = this.data;
    if(!name.trim()) return wx.showToast({ title:'请输入收货人姓名', icon:'none' });
    if(!/^1[3-9]\d{9}$/.test(phone.trim())) return wx.showToast({ title:'请输入正确的手机号', icon:'none' });
    if(!Array.isArray(region) || region.length<3 || !region[0] || !region[1] || !region[2]){
      return wx.showToast({ title:'请选择完整的省/市/区', icon:'none' });
    }
    if(!detail.trim()) return wx.showToast({ title:'请填写详细地址', icon:'none' });

    try {
      this.setData({ saving:true });
      const payload = {
        user_id: user.id,
        name: name.trim(),
        phone: phone.trim(),
        province: region[0],
        city: region[1],
        district: region[2],
        detail: detail.trim(),
        is_default: is_default ? 1 : 0
      };
      if(mode === 'edit') payload.id = id;
      const fn = mode === 'edit' ? api.editAddress : api.addAddress;
      const r = await fn(payload);
      wx.showToast({ title:'保存成功', icon:'success' });
      setTimeout(()=>wx.navigateBack(), 600);
    } catch(e){
      this.setData({ saving:false });
    }
  }
});
