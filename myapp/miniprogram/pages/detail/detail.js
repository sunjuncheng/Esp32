// pages/detail/detail.js
const api  = require('../../utils/api.js');
const util = require('../../utils/util.js');
const app  = getApp();

Page({
  data: {
    goods: null,
    qty: 1,
    total: 0,
    loading: true,
    user: null,
    userBalanceText: '0.00',
    showBuy: false,
    shipName: '',
    shipPhone: '',
    region: [],
    regionText: '',
    shipDetail: '',
    shipAddress: '',
    paying: false,
    hasAddr: false,
    selectedAddrDefault: false,
    defaultAddrLoaded: false
  },
  onLoad(opt){
    try {
      console.log('[detail] onLoad start, opt=', opt);
      this.goodsId = Number(opt ? (opt.id || 0) : 0);
      const u = app.globalData.userInfo || wx.getStorageSync('userInfo') || null;
      this.setData({
        user: u,
        userBalanceText: this._fmtMoney(u ? u.balance : 0)
      });
      this.loadGoods();
      console.log('[detail] onLoad ok');
    } catch(e) {
      console.error('[detail] onLoad error:', e);
      this.setData({ loading: false });
    }
  },

  async loadGoods(){
    try {
      if (!this.goodsId) { this.setData({loading:false}); return wx.showToast({title:'商品不存在',icon:'none'}); }
      this.setData({ loading: true });
      try {
        const res = await api.GET('getGoods', { id: this.goodsId }, { loadingText:'加载中...' });
        const g = this._fmtGoods(res.data);
        const uAddr = this.data.user ? (this.data.user.address || '') : '';
        const parsed = this._parseAddress(uAddr);
        this.setData({
          goods: g,
          total: this._total(g.price, this.data.qty),
          shipName: this.data.user ? (this.data.user.name || '') : '',
          shipPhone: this.data.user ? (this.data.user.phone || '') : '',
          region: parsed.region,
          regionText: parsed.regionText,
          shipDetail: parsed.detail,
          shipAddress: uAddr,
          hasAddr: !!(parsed.region && parsed.region.length>0 && parsed.region[0] && parsed.detail)
        });
        if(this.data.user) this._loadDefaultAddr();
        wx.setNavigationBarTitle({ title: (g.goods_name || '商品详情').slice(0,10) });
      } catch(e) {
        console.warn('[detail] getGoods fail, use demo. reason:', e && e.message);
        const demo = {
          id: this.goodsId, goods_name:'演示商品 '+this.goodsId, emoji:'📦',
          price: 99.00, origin_price: 199.00, stock: 88,
          category: '演示分类',
          description:'这是演示模式下的商品信息。请上传最新 api.php 并升级数据库后查看真实商品。',
          content:'详细介绍：请先在后台商品管理中新增商品，小程序首页即可看到。'
        };
        const g = this._fmtGoods(demo);
        this.setData({ goods: g, total: this._total(g.price, this.data.qty) });
      }
    } catch(e) {
      console.error('[detail] loadGoods error:', e);
    } finally {
      this.setData({ loading: false });
    }
  },

  _fmtMoney(v){ return Number(v||0).toFixed(2); },
  _fmtGoods(g){
    if (!g) return g;
    return {
      ...g,
      _priceText: this._fmtMoney(g.price),
      _originPriceText: this._fmtMoney(g.origin_price)
    };
  },
  _total(price, qty){ return (Number(price||0) * Number(qty||1)).toFixed(2); },
  _parseAddress(full){
    full = full || '';
    const reg = /^(北京市|上海市|天津市|重庆市|河北省|山西省|辽宁省|吉林省|黑龙江省|江苏省|浙江省|安徽省|福建省|江西省|山东省|河南省|湖北省|湖南省|广东省|海南省|四川省|贵州省|云南省|陕西省|甘肃省|青海省|台湾省|内蒙古自治区|广西壮族自治区|西藏自治区|宁夏回族自治区|新疆维吾尔自治区|香港特别行政区|澳门特别行政区)/;
    if (reg.test(full)) {
      const m = full.match(reg);
      const p = m[1];
      const rest = full.slice(p.length);
      return {
        region: [p, '', ''],
        regionText: p,
        detail: rest
      };
    }
    return { region: [], regionText: '', detail: full };
  },
  _joinAddress(region, detail){
    const r = Array.isArray(region) ? region.join('') : (region || '');
    const d = detail || '';
    return (r + d).trim();
  },
  minusQty(){
    if (this.data.qty<=1) return;
    this.setData({ qty:this.data.qty-1, total: this._total(this.data.goods.price, this.data.qty-1) });
  },
  plusQty(){
    const mx = Math.max(1, Number(this.data.goods.stock||999));
    if (this.data.qty>=mx) return wx.showToast({title:'库存不足，最多 '+mx+' 件',icon:'none'});
    this.setData({ qty:this.data.qty+1, total: this._total(this.data.goods.price, this.data.qty+1) });
  },
  onQty(e){
    let v = parseInt(e.detail.value) || 1;
    if (v<1) v=1;
    const mx = Math.max(1, Number(this.data.goods.stock||999));
    if (v>mx){ v=mx; wx.showToast({title:'库存不足，最多 '+mx+' 件',icon:'none'}); }
    this.setData({ qty:v, total:this._total(this.data.goods.price, v) });
  },
  inp(e){
    const k = e.currentTarget.dataset.k;
    const v = e.detail.value;
    const patch = { [k]: v };
    if (k === 'shipDetail' || k === 'region') {
      patch.shipAddress = this._joinAddress(
        (k === 'region' ? v : this.data.region),
        (k === 'shipDetail' ? v : this.data.shipDetail)
      );
    }
    this.setData(patch);
  },
  onRegion(e){
    const arr = e.detail.value || [];
    this.setData({
      region: arr,
      regionText: arr.join(' / '),
      shipAddress: this._joinAddress(arr, this.data.shipDetail)
    });
  },

  // 立即购买
  async buyNow(){
    if (!this.data.user){
      return app.requireLogin('pages/detail/detail?id='+this.goodsId, 'pages/detail/detail?id='+this.goodsId);
    }
    const hasRegion = Array.isArray(this.data.region) && this.data.region.length>0;
    const hasDetail = String(this.data.shipDetail || '').trim() !== '';
    if (!this.data.shipName || !this.data.shipPhone || (!hasRegion && !hasDetail)){
      this.setData({ showBuy: true });
      return wx.showToast({ title:'请填写完整收货信息', icon:'none' });
    }
    const fullAddr = this._joinAddress(this.data.region, this.data.shipDetail) || this.data.shipAddress;
    if (!fullAddr){
      this.setData({ showBuy: true });
      return wx.showToast({ title:'请填写收货地址', icon:'none' });
    }
    if (!util.isPhone(this.data.shipPhone)){
      return wx.showToast({ title:'手机号格式错误', icon:'none' });
    }
    if (this.data.paying) return;
    this.setData({ paying: true });
    wx.showModal({
      title:'确认支付',
      content:'商品：'+this.data.goods.goods_name+'\n数量：×'+this.data.qty+'\n合计：¥ '+this.data.total+'\n\n将从您的账户余额中扣除。',
      confirmText:'确认支付',
      confirmColor:'#ef4444',
      success: async (r) => {
        if (!r.confirm){ this.setData({ paying:false }); return; }
        try {
          const res = await api.POST('payOrder', {
            user_id: this.data.user.id,
            goods_id: this.data.goods.id,
            qty: this.data.qty,
            ship_name: this.data.shipName.trim(),
            ship_phone: this.data.shipPhone.trim(),
            ship_address: fullAddr.trim()
          }, { loadingText:'支付中...' });
          // 成功：刷新本地余额
          if (res.new_balance !== null && res.new_balance !== undefined) {
            const u = { ...(app.globalData.userInfo), balance: Number(res.new_balance) };
            app.setLoginState(u, (app.globalData.token||'_t'));
            this.setData({ user: u, userBalanceText: this._fmtMoney(res.new_balance) });
          }
          wx.showModal({
            title:'🎉 支付成功',
            content:'订单 #'+res.order_id+' 已创建，商家将尽快为您发货！',
            confirmText:'去看订单',
            cancelText:'继续逛逛',
            confirmColor:'#667eea',
            success: (r2)=>{
              if (r2.confirm){
                wx.redirectTo({ url:'/pages/order-detail/order-detail?id='+res.order_id });
              }
            }
          });
        } finally {
          this.setData({ paying: false });
        }
      },
      fail: () => this.setData({ paying:false })
    });
  },

  async _loadDefaultAddr(){
    try {
      if(!this.data.user || this.data.defaultAddrLoaded) return;
      const r = await api.listAddress(this.data.user.id, { showLoading:false });
      const def = r.default || null;
      this.setData({ defaultAddrLoaded: true });
      if(def){
        this.applyAddress(def, true);
      }
    } catch(e){}
  },
  applyAddress(a, isDefault=false){
    if(!a) return;
    const region = a.region || [a.province||'',a.city||'',a.district||''];
    const regionText = [region[0],region[1],region[2]].filter(x=>x).join(' / ');
    const regionRaw = region[0] || region[1] || region[2] ? [region[0]||'',region[1]||'',region[2]||''].join('') : '';
    const has = !!(regionRaw && a.detail);
    this.setData({
      shipName: a.name || this.data.shipName,
      shipPhone: a.phone || this.data.shipPhone,
      region: region,
      regionText: regionText,
      shipDetail: a.detail || this.data.shipDetail,
      shipAddress: regionRaw + (a.detail || ''),
      hasAddr: has || this.data.hasAddr,
      selectedAddrDefault: !!Number(a.is_default)
    });
  },
  goSelectAddress(){
    if(!this.data.user) return app.requireLogin('pages/detail/detail?id='+this.goodsId, 'pages/detail/detail?id='+this.goodsId);
    wx.navigateTo({ url:'/pages/address/address?mode=select' });
  },
  toggleShip(){ this.setData({ showBuy: !this.data.showBuy }); },
  goOrders(){ wx.switchTab({ url:'/pages/orders/orders' }); },
  goBack(){ wx.navigateBack({ fail: ()=>wx.switchTab({url:'/pages/index/index'}) }); }
});
