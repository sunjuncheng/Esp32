// pages/orders/orders.js
const api  = require('../../utils/api.js');
const util = require('../../utils/util.js');
const app  = getApp();

Page({
  data: {
    user: null,
    tabs: [
      { id:-1, name:'全部',   c:0, active:true },
      { id: 1, name:'待发货', c:0, active:false },
      { id: 2, name:'已发货', c:0, active:false },
      { id: 3, name:'已完成', c:0, active:false },
    ],
    activeTab: -1,
    orders: [],
    loading: true
  },
  onLoad(){
    const t = wx.getStorageSync('order_tab');
    if (t !== '' && t !== undefined && t !== null) {
      this.onTab({ currentTarget:{ dataset:{ id: t }}});
    }
  },
  onShow(){
    const u = app.globalData.userInfo || wx.getStorageSync('userInfo');
    this.setData({ user: u });
    if (!u) {
      this.setData({ loading:false });
    } else {
      this.loadOrders();
    }
  },
  onPullDownRefresh(){ this.loadOrders(()=>wx.stopPullDownRefresh()); },

  onTab(e){
    const id = Number(e.currentTarget.dataset.id);
    const tabs = this.data.tabs.map(t => ({ ...t, active: t.id === id }));
    this.setData({ activeTab: id, tabs });
    this.loadOrders();
  },

  async loadOrders(done){
    if (!this.data.user){ (typeof done==='function')&&done(); return; }
    this.setData({ loading: true });
    try {
      const res = await api.listOrders();
      const uid = this.data.user.id;
      let all = (res.data || []).filter(o => String(o.user_id) === String(uid));
      // 排序：新的在前面
      all.sort((a,b)=>(b.id||0)-(a.id||0));

      // 统计分类数量
      const shipped = all.filter(o => String(o.express_no||'').trim() !== '');
      const doneN   = all.filter(o => Number(o.status)===3);
      const pend    = all.filter(o => Number(o.status)!==3 && String(o.express_no||'').trim()==='');
      const tabs = this.data.tabs.map(t => {
        if (t.id===-1) return { ...t, c: all.length, active: this.data.activeTab===-1 };
        if (t.id=== 1) return { ...t, c: pend.length,  active: this.data.activeTab=== 1 };
        if (t.id=== 2) return { ...t, c: shipped.length - doneN.length, active: this.data.activeTab=== 2 };
        if (t.id=== 3) return { ...t, c: doneN.length, active: this.data.activeTab=== 3 };
        return t;
      });

      // 按当前Tab过滤
      let list = all;
      if (this.data.activeTab === 1) list = pend;
      if (this.data.activeTab === 2) list = shipped.filter(o=>Number(o.status)!==3);
      if (this.data.activeTab === 3) list = doneN;

      list = list.map(o => {
        const total = Number(o.total_price || o.price || 0);
        const qty   = Number(o.qty || 1);
        const unit  = Number(o.unit_price || (qty>0?total/qty:total));
        const em = String(o.express_no||'').trim();
        const goodsImg = o.goods_image || o.image || '';
        const rs = Number(o.refund_status || 0);
        return {
          ...o,
          goods_image: goodsImg,
          _total: total.toFixed(2),
          _qty: qty,
          _unit: unit.toFixed(2),
          _st_txt: util.orderStatusText(o.status),
          _st_cls: util.orderStatusColor(o.status),
          _st_emj: util.orderStatusEmoji(o.status),
          _pay_txt: util.payStatusText(o.pay_status),
          _pay_cls: util.payStatusColor(o.pay_status),
          _rf_txt: util.refundStatusText(rs),
          _rf_cls: util.refundStatusColor(rs),
          _rf_emj: util.refundStatusEmoji(rs),
          _can_refund: util.canApplyRefund(o),
          _has_ship: em !== '',
          _ship: em ? {
              company: o.express_company || '',
              no: em,
              time: o.express_time || '',
              note: o.express_note || '',
              name: o.ship_name || '',
              phone: o.ship_phone || '',
              addr: o.ship_address || ''
          } : null
        };
      });

      this.setData({ orders: list, tabs, loading:false });
    } catch(e){
      this.setData({ loading:false });
    }
    (typeof done === 'function') && done();
  },

  goDetail(e){
    const id = e.currentTarget.dataset.id;
    wx.navigateTo({ url: '/pages/order-detail/order-detail?id='+id });
  },

  copy(e){
    const v = e.currentTarget.dataset.v;
    const c = e.currentTarget.dataset.c || '已复制';
    util.copyText(v, c);
  },

  goRefund(e){
    const id = e.currentTarget.dataset.id;
    wx.navigateTo({ url: '/pages/order-detail/order-detail?id='+id+'&openRefund=1' });
  },
  goLogin(){
    wx.navigateTo({ url:'/pages/login/login?redirect='+encodeURIComponent('pages/orders/orders') });
  },
  goIndex(){ wx.switchTab({ url:'/pages/index/index' }); }
});
