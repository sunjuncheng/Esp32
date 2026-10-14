// pages/index/index.js
const api  = require('../../utils/api.js');
const util = require('../../utils/util.js');
const app  = getApp();

Page({
  data: {
    goodsList: [],
    categories: [],
    activeCat: '全部',
    kw: '',
    loading: true,
    user: null,
    userAvatarChar: 'U',
    welcomeSuffix: '',
    notice: '🎉 新人福利：后台充值下单立享优惠'
  },
  onLoad() {
    try {
      console.log('[index] onLoad start');
      this.loadList();
      console.log('[index] onLoad ok');
    } catch(e) {
      console.error('[index] onLoad error:', e);
    }
  },
  onShow() {
    try {
      console.log('[index] onShow start');
      const u = app.globalData.userInfo || wx.getStorageSync('userInfo') || null;
      this.setData({
        user: u,
        userAvatarChar: this._avatarChar(u ? u.name : ''),
        welcomeSuffix: u && u.name ? '，' + u.name : ''
      });
      console.log('[index] onShow ok');
    } catch(e) {
      console.error('[index] onShow error:', e);
    }
  },
  onPullDownRefresh() {
    try { this.loadList(()=>wx.stopPullDownRefresh()); }
    catch(e) { wx.stopPullDownRefresh(); }
  },
  _fmtMoney(v){ return Number(v||0).toFixed(2); },
  _avatarChar(name){
    const n = name || 'U';
    return n.charAt(0);
  },
  _fmtGoodsList(list){
    return (list || []).map(item => {
      const price = Number(item.price || 0);
      const stock = Number(item.stock || 0);
      let priceText;
      if (item.price || item.price === 0) {
        priceText = '¥ ' + this._fmtMoney(item.price);
      } else {
        priceText = '价格面议';
      }
      let desc = item.description;
      if (!desc && item.category) {
        desc = '分类：' + item.category;
      }
      let stockText;
      if (stock > 200) {
        stockText = '库存充足';
      } else {
        stockText = '库存' + stock + '件';
      }
      return {
        ...item,
        _priceText: priceText,
        _originPriceText: this._fmtMoney(item.origin_price),
        _desc: desc || '',
        _stockText: stockText
      };
    });
  },

  async loadList(cb) {
    try {
      this.setData({ loading: true });
      try {
        const q = {};
        if (this.data.kw)       q.kw = this.data.kw;
        if (this.data.activeCat && this.data.activeCat !== '全部') q.category = this.data.activeCat;
        const res = await api.GET('getGoods', q, { showLoading: false });
        const cats = [{ name: '全部', c: res.total || res.data.length, active: this.data.activeCat==='全部' }];
        if (res.categories && Object.keys(res.categories).length) {
          Object.keys(res.categories).forEach(k => {
            if (!k) return;
            cats.push({ name: k, c: res.categories[k], active: this.data.activeCat === k });
          });
        }
        this.setData({
          goodsList: this._fmtGoodsList(res.data || []),
          categories: cats,
          loading: false
        });
      } catch(e) {
        console.warn('[index] getGoods fail, use demo. reason:', e && e.message);
        const demo = [
          { id:1, goods_name:'演示商品 iPhone 15 Pro',   emoji:'📱', price:8999, origin_price:9999, category:'数码', stock:100, is_hot:1, description:'256GB 原色钛金属 / 6.1英寸 Super Retina XDR 显示屏' },
          { id:2, goods_name:'演示商品 AirPods Pro 2',   emoji:'🎧', price:1899, origin_price:1999, category:'数码', stock:80,  is_hot:0, description:'主动降噪 H2芯片 USB-C充电盒' },
          { id:3, goods_name:'演示商品 纯棉T恤',         emoji:'👕', price:129,  origin_price:199,  category:'服饰', stock:500, is_hot:1, description:'新疆长绒棉 亲肤透气 多色可选' },
          { id:4, goods_name:'演示商品 轻薄羽绒服',       emoji:'🧥', price:599,  origin_price:1299, category:'服饰', stock:120, is_hot:0, description:'90%白鹅绒 轻量化设计 零下10°C保暖' },
          { id:5, goods_name:'演示商品 进口西梅干',       emoji:'🍑', price:39.9, origin_price:59,   category:'美食', stock:999, is_hot:1, description:'智利原产 无添加 500g/袋' },
          { id:6, goods_name:'演示商品 机械键盘',         emoji:'⌨️', price:499,  origin_price:699,  category:'数码', stock:60,  is_hot:0, description:'104键 青轴 RGB背光 PBT键帽' },
        ];
        this.setData({
          goodsList: this._fmtGoodsList(demo),
          categories: [{name:'全部',c:demo.length,active:true}],
          loading:false
        });
        wx.showModal({
          title: '提示：getGoods 接口未就绪',
          content: '当前展示的是演示商品。请把最新版 api.php 上传到服务器，并升级数据库以展示真实商品。',
          showCancel:false, confirmText:'我知道了'
        });
      }
    } catch(e) {
      console.error('[index] loadList error:', e);
      this.setData({ loading: false, goodsList: [], categories: [{name:'全部',c:0,active:true}] });
    }
    (typeof cb === 'function') && cb();
  },

  // 搜索
  onKw(e){ this.setData({ kw: e.detail.value }); },
  doSearch: util.debounce(function(){ this.loadList(); }, 300),

  // 选分类
  chooseCat(e){
    const n = e.currentTarget.dataset.n;
    const cats = this.data.categories.map(c => ({ ...c, active: c.name === n }));
    this.setData({ activeCat: n, categories: cats });
    this.loadList();
  },

  // 进入详情
  goDetail(e){
    const id = e.currentTarget.dataset.id;
    wx.navigateTo({ url: '/pages/detail/detail?id='+id });
  },

  // 头像点击：已登录去订单，未登录去登录
  onAvatarTap(){
    if (this.data.user) {
      this.goOrders();
    } else {
      this.goLogin();
    }
  },
  goOrders(){ wx.switchTab({ url: '/pages/orders/orders' }); },
  // 去登录
  goLogin(){ wx.navigateTo({ url: '/pages/login/login?redirect='+encodeURIComponent('pages/index/index') }); },
});
