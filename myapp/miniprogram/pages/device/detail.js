// pages/device/detail.js
const api = require('../../utils/api.js');
const app = getApp();

Page({
  data: {
    deviceId: 0,
    device: null,
    loading: true,
    activeTab: 'realtime',
    sensorData: [],
    alarms: [],
    refreshing: false,
    refreshTimer: null,
    stats: null,
    chartCanvasW: 0,
    chartCanvasH: 0
  },
  onLoad(options){
    const id = intval(options.id || 0);
    this.setData({ deviceId: id });
    this.initChartSize();
    this.loadAll();
    this.startAutoRefresh();
  },
  onUnload(){
    if(this.data.refreshTimer) clearInterval(this.data.refreshTimer);
  },
  onPullDownRefresh(){
    this.loadAll(()=>wx.stopPullDownRefresh());
  },

  initChartSize(){
    try {
      const sys = wx.getSystemInfoSync();
      this.setData({ chartCanvasW: sys.windowWidth - 60, chartCanvasH: 260 });
    } catch(e){
      this.setData({ chartCanvasW: 300, chartCanvasH: 260 });
    }
  },

  switchTab(e){
    this.setData({ activeTab: e.currentTarget.dataset.tab });
  },

  async loadAll(cb){
    try {
      this.setData({ refreshing: true });
      await Promise.all([
        this.loadDevice(),
        this.loadSensorData(),
        this.loadAlarms(),
        this.loadStats()
      ]);
    } catch(e){ console.error('[detail] loadAll', e); }
    this.setData({ refreshing: false, loading: false });
    (typeof cb==='function') && cb();
  },

  startAutoRefresh(){
    const t = setInterval(()=>{
      if(this.data.activeTab === 'realtime'){
        this.loadDevice();
        this.loadSensorData();
        this.loadStats();
      }
    }, 5000);
    this.setData({ refreshTimer: t });
  },

  async loadDevice(){
    try {
      const res = await api.getDevice(this.data.deviceId);
      const d = res.data || {};
      this.setData({
        device: {
          id: d.id,
          name: d.device_name,
          no: d.device_no,
          sensor: d.sensor_type,
          online: d.online == 1,
          temp: d.last_temp !== null && d.last_temp !== undefined ? Number(d.last_temp) : null,
          humidity: d.last_humidity !== null && d.last_humidity !== undefined ? Number(d.last_humidity) : null,
          tempMin: Number(d.temp_min), tempMax: Number(d.temp_max),
          humMin: Number(d.humidity_min), humMax: Number(d.humidity_max),
          alarmEnabled: d.alarm_enabled == 1,
          alarmDuration: d.alarm_duration,
          reportInterval: d.report_interval,
          lastTime: d.last_report_time || '从未上报',
          todayAlarm: res.today_alarm_count || 0,
          pendingAlarm: res.pending_alarm_count || 0
        }
      });
    } catch(e){ console.error('[detail] loadDevice', e); }
  },

  async loadSensorData(){
    try {
      const res = await api.getSensorData(this.data.deviceId, { limit: 60 });
      const data = (res.data || []).map(d => ({
        time: (d.create_time || '').substring(11,19),
        temp: Number(d.temp),
        humidity: Number(d.humidity),
        alarm: d.alarm == 1
      }));
      this.setData({ sensorData: data });
      this.drawChart(data);
    } catch(e){ console.error('[detail] loadSensorData', e); }
  },

  async loadAlarms(){
    try {
      const res = await api.getAlarmLog(this.data.deviceId);
      const list = (res.data || []).map(a => ({
        id: a.id,
        temp: a.temp !== null && a.temp !== undefined ? Number(a.temp) : null,
        humidity: a.humidity !== null && a.humidity !== undefined ? Number(a.humidity) : null,
        reason: a.reason,
        startTime: a.start_time,
        duration: Number(a.duration || 0),
        handled: a.handled == 1,
        handleTime: a.handle_time || '',
        handleRemark: a.handle_remark || ''
      }));
      this.setData({ alarms: list });
    } catch(e){ console.error('[detail] loadAlarms', e); }
  },

  async loadStats(){
    try {
      const res = await api.getDeviceStats(this.data.deviceId);
      this.setData({ stats: res.data || null });
    } catch(e){}
  },

  drawChart(data){
    try {
      if(!data || data.length < 2) return;
      const w = this.data.chartCanvasW;
      const h = this.data.chartCanvasH;
      const ctx = wx.createCanvasContext('tempHumiChart', this);
      const padL = 44, padR = 44, padT = 20, padB = 30;
      const cw = w - padL - padR;
      const ch = h - padT - padB;

      const temps = data.map(d=>d.temp);
      const hums = data.map(d=>d.humidity);
      const tMin = Math.min(...temps) - 2, tMax = Math.max(...temps) + 2;
      const hMin = 0, hMax = 100;

      const xAt = i => padL + (cw * i / (data.length - 1));
      const yT = v => padT + ch * (1 - (v - tMin) / (tMax - tMin));
      const yH = v => padT + ch * (1 - (v - hMin) / (hMax - hMin));

      ctx.clearRect(0, 0, w, h);

      // 网格
      ctx.setStrokeStyle('#f1f5f9');
      ctx.setLineWidth(1);
      for(let i=0;i<=4;i++){
        const y = padT + ch * i / 4;
        ctx.beginPath(); ctx.moveTo(padL, y); ctx.lineTo(w - padR, y); ctx.stroke();
      }

      // Y轴标签
      ctx.setFontSize(10);
      ctx.setFillStyle('#94a3b8');
      ctx.setTextAlign('right');
      ctx.fillText(tMax.toFixed(0), padL-6, padT+4);
      ctx.fillText(tMin.toFixed(0), padL-6, padT+ch+4);
      ctx.setTextAlign('left');
      ctx.fillText('100', w-padR+6, padT+4);
      ctx.fillText('0', w-padR+6, padT+ch+4);

      // X轴时间标签
      ctx.setTextAlign('center');
      ctx.fillText(data[0].time, padL, h-8);
      ctx.fillText(data[Math.floor(data.length/2)].time, padL+cw/2, h-8);
      ctx.fillText(data[data.length-1].time, w-padR, h-8);

      // 温度线
      ctx.setStrokeStyle('#ef4444');
      ctx.setLineWidth(2);
      ctx.beginPath();
      data.forEach((d,i)=>{
        const x = xAt(i), y = yT(d.temp);
        if(i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
      });
      ctx.stroke();

      // 湿度线
      ctx.setStrokeStyle('#3b82f6');
      ctx.setLineWidth(2);
      ctx.beginPath();
      data.forEach((d,i)=>{
        const x = xAt(i), y = yH(d.humidity);
        if(i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
      });
      ctx.stroke();

      // 报警点
      data.forEach((d,i)=>{
        if(d.alarm){
          const x = xAt(i), y = yT(d.temp);
          ctx.setFillStyle('#ef4444');
          ctx.beginPath(); ctx.arc(x, y, 4, 0, Math.PI*2); ctx.fill();
        }
      });

      ctx.draw();
    } catch(e){ console.error('[detail] drawChart', e); }
  }
});

function intval(v){ v = parseInt(v); return isNaN(v) ? 0 : v; }
