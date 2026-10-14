const chartDom = document.getElementById('chartBox');
const myChart = echarts.init(chartDom);

const option = {
  title: {
    text: '月度数据统计'
  },
  xAxis: {
    type: 'category',
    data: ['1月', '2月', '3月', '4月', '5月', '6月']
  },
  yAxis: {
    type: 'value'
  },
  series: [
    {
      data: [120, 200, 150, 80, 70, 110],
      type: 'bar'
    }
  ]
};
myChart.setOption(option);
window.addEventListener('resize',function(){
    myChart.resize();
})
