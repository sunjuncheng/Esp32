// utils/util.js - 工具函数
function formatPrice(v) {
  const n = Number(v || 0);
  return '¥ ' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function formatNum(v) {
  const n = Number(v || 0);
  return n.toLocaleString('zh-CN');
}

// 补零
function _p(n) { return n < 10 ? '0' + n : '' + n; }

function formatTime(str) {
  if (!str) return '--';
  const d = typeof str === 'string' ? new Date(str.replace(/-/g, '/')) : new Date(str);
  if (isNaN(d.getTime())) return str;
  return d.getFullYear() + '-' + _p(d.getMonth()+1) + '-' + _p(d.getDate()) + ' ' + _p(d.getHours()) + ':' + _p(d.getMinutes());
}

function formatDate(str) {
  if (!str) return '--';
  return (formatTime(str) || '').split(' ')[0];
}

// 订单状态文字
function orderStatusText(s) {
  s = parseInt(s) || 1;
  return { 0:'待确认', 1:'已下单', 2:'已发货', 3:'已完成', 9:'已取消' }[s] || '未知';
}
function orderStatusColor(s) {
  s = parseInt(s) || 1;
  return { 0:'tag-gray', 1:'tag-blue', 2:'tag-purple', 3:'tag-green', 9:'tag-gray' }[s] || 'tag-gray';
}
function orderStatusEmoji(s) {
  s = parseInt(s) || 1;
  return { 0:'⏳', 1:'📦', 2:'🚚', 3:'🎉', 9:'❌' }[s] || '📦';
}

// 支付状态
function payStatusText(v){ return parseInt(v)==1 ? '已支付' : '待支付'; }
function payStatusColor(v){ return parseInt(v)==1 ? 'tag-green' : 'tag-orange'; }

// 退款状态：0未申请 1申请中 2已退款 3已拒绝
function refundStatusText(s){
  s = parseInt(s) || 0;
  return { 0:'', 1:'退款申请中', 2:'已退款', 3:'退款被拒' }[s] || '';
}
function refundStatusColor(s){
  s = parseInt(s) || 0;
  return { 0:'', 1:'tag-orange', 2:'tag-green', 3:'tag-red' }[s] || '';
}
function refundStatusEmoji(s){
  s = parseInt(s) || 0;
  return { 0:'', 1:'⏳', 2:'💸', 3:'❌' }[s] || '';
}
// 是否允许申请退款
function canApplyRefund(order){
  const o = order || {};
  const rs = parseInt(o.refund_status || 0);
  const ps = parseInt(o.pay_status || 0);
  if(ps !== 1) return false;        // 未支付不可以
  if(rs === 1) return false;        // 申请中
  if(rs === 2) return false;        // 已退款
  return true;
}

// 防抖
function debounce(fn, wait=300) {
  let t; return function(...args){ clearTimeout(t); t=setTimeout(()=>fn.apply(this,args), wait); };
}

// 复制文本
function copyText(text, toastOk='已复制') {
  wx.setClipboardData({
    data: String(text||''),
    success() { if (toastOk!==false) wx.showToast({ title: toastOk, icon: 'success' }); },
    fail()    { wx.showToast({ title: '复制失败', icon: 'none' }); }
  });
}

// 校验手机号
function isPhone(v) { return /^1[3-9]\d{9}$/.test(String(v||'').trim()); }

module.exports = {
  formatPrice, formatNum, formatTime, formatDate,
  orderStatusText, orderStatusColor, orderStatusEmoji,
  payStatusText, payStatusColor,
  refundStatusText, refundStatusColor, refundStatusEmoji, canApplyRefund,
  debounce, copyText, isPhone
};
