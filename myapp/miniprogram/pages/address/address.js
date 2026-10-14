const app = getApp();
const api = require('../../utils/api.js');

Page({
  data: {
    list: [],
    loading: true,
    empty: false,
    selectMode: false,
    user: null
  },

  onLoad(opts){
    this.setData({
      selectMode: (opts && opts.mode === 'select') ? true : false
    });
    wx.setNavigationBarTitle({ title: this.data.selectMode ? '选择收货地址' : '收货地址管理' });
  },

  onShow(){
    try {
      this.setData({ user: (app.globalData && app.globalData.userInfo) || null });
    } catch(e){}
    if(!this.data.user) return app.requireLogin('/pages/address/address','/pages/address/address');
    this.loadList();
  },

  onPullDownRefresh(){ this.loadList(()=>wx.stopPullDownRefresh()); },

  async loadList(cb){
    try {
      this.setData({ loading:true });
      const r = await api.listAddress(this.data.user.id, { showLoading: false });
      const list = r.data || [];
      this.setData({ list, loading:false, empty: list.length===0 });
    } catch(e){
      this.setData({ loading:false, empty: true });
    } finally {
      if(typeof cb==='function') cb();
    }
  },

  goAdd(){
    wx.navigateTo({ url:'/pages/address-edit/address-edit?mode=add' });
  },

  goEdit(e){
    const id = e.currentTarget.dataset.id;
    wx.navigateTo({ url:'/pages/address-edit/address-edit?mode=edit&id='+id });
  },

  onSelectAddr(e){
    if(!this.data.selectMode) return;
    const id = e.currentTarget.dataset.id;
    const a = this.data.list.find(x => String(x.id) === String(id));
    if(!a) return;
    try {
      const pages = getCurrentPages();
      const prev = pages[pages.length-2];
      if(prev && typeof prev.applyAddress === 'function'){
        prev.applyAddress(a);
      }
    } catch(e){}
    wx.navigateBack();
  },

  onTapAddr(e){
    const id = e.currentTarget.dataset.id;
    if(this.data.selectMode){
      this.onSelectAddr(e);
    }else{
      this.goEdit(e);
    }
  },

  async onSetDefault(e){
    const id = e.currentTarget.dataset.id;
    try {
      await api.setDefaultAddress({ user_id:this.data.user.id, id });
      wx.showToast({ title:'已设为默认', icon:'success' });
      this.loadList();
    } catch(e){}
  },

  onDel(e){
    const id = e.currentTarget.dataset.id;
    wx.showModal({
      title:'确认删除',
      content:'确定要删除该收货地址吗？',
      confirmColor:'#ef4444',
      success: async (r)=>{
        if(!r.confirm) return;
        try {
          await api.delAddress({ user_id:this.data.user.id, id });
          wx.showToast({ title:'已删除', icon:'success' });
          this.loadList();
        } catch(e){}
      }
    });
  }
});
