// pages/order-detail/order-detail.js
const api  = require('../../utils/api.js');
const util = require('../../utils/util.js');
const app  = getApp();

Page({
  data: {
    order: null,
    ship: null,
    timeline: [],
    priceTotal: '0.00',
    priceUnit: '0.00',
    loading: true,
    stText: '', stCls: '', stEmoji: '',
    reasons: ['不想要了/拍错了','商品与描述不符','商品质量问题','发货太慢/未按时送达','其他原因'],
    selReason: '',
    remark: '',
    showApplyPanel: false,
    applying: false,
    rfStatus: 0,
    canApply: false
  },
  onLoad(opt){
    this.oid = Number(opt.id || 0);
    this._autoOpen = String(opt.openRefund||'') === '1';
    if (!this.oid){
      wx.showToast({title:'订单不存在',icon:'none'});
      this.setData({loading:false});
      return;
    }
    this.load();
  },
  onPullDownRefresh(){ this.load(()=>wx.stopPullDownRefresh()); },

  async load(done){
    this.setData({ loading:true });
    try {
      const res = await api.listOrders();
      const uid = (app.globalData.userInfo || wx.getStorageSync('userInfo') || {}).id;
      const o = (res.data || []).find(x => Number(x.id)===this.oid);
      if (!o){ throw new Error('订单不存在'); }
      if (String(o.user_id) !== String(uid)){ throw new Error('这不是您的订单'); }

      const total = Number(o.total_price || o.price || 0);
      const qty   = Number(o.qty || 1);
      const unit  = Number(o.unit_price || (qty>0?total/qty:total));
      const st    = Number(o.status || 1);
      const stText = util.orderStatusText(st);
      const stCls  = util.orderStatusColor(st);
      const stEmoji= util.orderStatusEmoji(st);

      const em = String(o.express_no||'').trim();
      const ship = em ? {
        company: o.express_company || '',
        no: em,
        time: o.express_time || '',
        note: o.express_note || '',
        name: o.ship_name || '',
        phone: o.ship_phone || '',
        addr: o.ship_address || ''
      } : null;

      const timeline = this._buildTimeline(o, ship, st);

      const rf = Number(o.refund_status || 0);
      const canApply = util.canApplyRefund(o);
      const autoOpen = this._autoOpen && canApply;
      this.setData({
        order: {
          ...o,
          goods_image: o.goods_image || o.image || '',
          _total: total.toFixed(2),
          _qty: qty,
          _unit: unit.toFixed(2),
          _pay_txt: util.payStatusText(o.pay_status),
          _pay_cls: util.payStatusColor(o.pay_status)
        },
        ship, timeline,
        priceTotal: total.toFixed(2),
        priceUnit:  unit.toFixed(2),
        stText, stCls, stEmoji,
        rfStatus: rf,
        canApply: canApply,
        showApplyPanel: autoOpen,
        loading: false
      });
    } catch(e){
      wx.showToast({ title: e.msg || e.message || '加载失败', icon:'none' });
      this.setData({ loading:false });
      setTimeout(()=>wx.navigateBack(), 1200);
    }
    (typeof done === 'function') && done();
  },

  _buildTimeline(o, ship, st){
    const t = [];
    t.push({ icon:'✅', color:'green', done:true, title:'订单已提交', time:o.create_time, desc:'订单创建成功' });
    if (Number(o.pay_status)===1) {
      t.push({ icon:'💳', color:'blue', done:true, title:'支付成功', time:o.pay_time||o.create_time,
               desc:'已通过账户余额支付 ¥'+(Number(o.total_price||o.price||0)).toFixed(2) });
    }
    if (ship){
      t.push({
        icon:'🚚', color:'purple', done:true,
        title:(ship.company||'快递')+' 已揽收',
        time:ship.time || o.pay_time || o.create_time,
        desc: (ship.company?('【'+ship.company+'】'):'') + (ship.no?'单号：'+ship.no:'') + (ship.note?(' / 备注：'+ship.note):'')
      });
    }
    if (ship && (st===2 || st===3)){
      const base = new Date((ship.time||o.pay_time||o.create_time).replace(/-/g,'/'));
      const t8  = new Date(base.getTime() + 8*3600*1000);
      const t26 = new Date(base.getTime() + 26*3600*1000);
      t.push({
        icon:'📦', color:'purple', done:st>=3,
        title: st>=3 ? '✅ 运输中：已到达本地转运中心' : '⏳ 运输中：发往您所在城市',
        time: util.formatTime(t8.toISOString()),
        desc:'包裹正在运输途中，预计1-2天送达'
      });
      t.push({
        icon:'🏙️', color:'green', done:st>=3,
        title: (st>=3 ? '✅ 快递员已送达，签收成功' : '⏳ 快递员正在派件，请保持手机畅通'),
        time: util.formatTime(t26.toISOString()),
        desc: ship.name?('收件人：'+ship.name+(ship.phone?' '+ship.phone:'')+(ship.addr?'\n地址：'+ship.addr:'')):''
      });
    }
    return t;
  },

  copy(e){ util.copyText(e.currentTarget.dataset.v, e.currentTarget.dataset.c || '已复制'); },

  callPhone(){
    if (!this.data.ship || !this.data.ship.phone) return;
    wx.makePhoneCall({ phoneNumber: this.data.ship.phone }).catch(()=>{});
  },

  toggleApply(){ this.setData({ showApplyPanel: !this.data.showApplyPanel }); },
  selReason(e){
    const v = e.currentTarget.dataset.v;
    this.setData({ selReason: this.data.selReason === v ? '' : v });
  },
  onRemark(e){ this.setData({ remark: e.detail.value }); },
  async submitRefund(){
    const { selReason, remark, order } = this.data;
    const user = app.globalData.userInfo || wx.getStorageSync('userInfo');
    if(!selReason) return wx.showToast({ title:'请选择退款原因', icon:'none' });
    if(!user) return app.requireLogin();

    wx.showModal({
      title:'确认申请退款？',
      content: (Number(order.status)===1 ? '【注意：订单未发货，提交后将立即退款到余额。】\n\n' : '') +
               '退款原因：' + selReason + '\n退款金额：¥' + this.data.priceTotal,
      confirmText:'确认申请',
      confirmColor:'#ef4444',
      success: async (r)=>{
        if(!r.confirm) return;
        try {
          this.setData({ applying:true });
          const res = await api.POST('applyRefund', {
            id: this.oid,
            user_id: user.id,
            reason: selReason,
            remark: remark
          }, { loadingText:'提交中...' });
          wx.showModal({
            title: (res.status===2 ? '🎉 退款成功' : '✅ 申请已提交'),
            content: res.msg,
            confirmText:'我知道了',
            showCancel:false,
            confirmColor:'#667eea'
          });
          // 成功后刷新余额
          if(res.status === 2 && res.refund_amount !== undefined){
            try {
              const ures = await api.GET('getUser', {}, { showLoading:false });
              const me = (ures.data || []).find(x => Number(x.id) === user.id);
              if(me){
                const u = { ...user, balance: Number(me.balance||0) };
                app.setLoginState(u, (app.globalData.token || '_t'));
              }
            } catch(e){}
          }
          this.setData({
            applying:false,
            selReason:'',
            remark:'',
            showApplyPanel:false
          });
          this.load();
        } catch(e){
          this.setData({ applying:false });
        }
      }
    });
  }
});
