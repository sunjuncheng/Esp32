<?php
include __DIR__.'/common.php';
need_admin_login();
$toast = toast_pop();
$admin_name = admin_name();
$err = '';

// ============ 处理动作 ============
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if($action==='add' && $_POST){
    $dno = trim($_POST['device_no'] ?? '');
    $name = trim($_POST['device_name'] ?? '');
    $token = trim($_POST['device_token'] ?? '');
    $uid = intval($_POST['user_id'] ?? 0);
    $sensor = trim($_POST['sensor_type'] ?? 'DHT11');
    $tmin = floatval($_POST['temp_min'] ?? -40);
    $tmax = floatval($_POST['temp_max'] ?? 85);
    $hmin = floatval($_POST['humidity_min'] ?? 0);
    $hmax = floatval($_POST['humidity_max'] ?? 100);
    $alarmEn = intval($_POST['alarm_enabled'] ?? 1);
    $alarmDur = intval($_POST['alarm_duration'] ?? 30);
    $repInt = intval($_POST['report_interval'] ?? 10);
    $remark = trim($_POST['remark'] ?? '');

    if($dno==='' || $name===''){ $err='请填写设备编号和名称'; }
    elseif($tmin >= $tmax || $hmin >= $hmax){ $err='阈值设置不正确（下限必须小于上限）'; }
    else{
        $chk = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT id FROM iot_device WHERE device_no='".mysqli_real_escape_string($conn,$dno)."' LIMIT 1"));
        if($chk){ $err='该设备编号已存在'; }
        else{
            if($token==='') $token = md5($dno.time().rand(1000,9999));
            $sql = "INSERT INTO iot_device (device_no,device_name,device_token,user_id,sensor_type,temp_min,temp_max,humidity_min,humidity_max,alarm_enabled,alarm_duration,report_interval,remark) VALUES (
              '".mysqli_real_escape_string($conn,$dno)."',
              '".mysqli_real_escape_string($conn,$name)."',
              '".mysqli_real_escape_string($conn,$token)."',
              $uid,'".mysqli_real_escape_string($conn,$sensor)."',
              $tmin,$tmax,$hmin,$hmax,$alarmEn,$alarmDur,$repInt,
              '".mysqli_real_escape_string($conn,$remark)."')";
            if(@mysqli_query($conn,$sql)) redirect('device_manage.php','设备「'.$name.'」添加成功（Token: '.$token.'）');
            else $err = '添加失败：'.mysqli_error($conn);
        }
    }
}
elseif($action==='edit' && $_POST){
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['device_name'] ?? '');
    $uid = intval($_POST['user_id'] ?? 0);
    $sensor = trim($_POST['sensor_type'] ?? 'DHT11');
    $tmin = floatval($_POST['temp_min'] ?? -40);
    $tmax = floatval($_POST['temp_max'] ?? 85);
    $hmin = floatval($_POST['humidity_min'] ?? 0);
    $hmax = floatval($_POST['humidity_max'] ?? 100);
    $alarmEn = intval($_POST['alarm_enabled'] ?? 1);
    $alarmDur = intval($_POST['alarm_duration'] ?? 30);
    $repInt = intval($_POST['report_interval'] ?? 10);
    $status = intval($_POST['status'] ?? 1);
    $remark = trim($_POST['remark'] ?? '');
    if($id<=0){ $err='设备ID非法'; }
    elseif($tmin >= $tmax || $hmin >= $hmax){ $err='阈值设置不正确'; }
    else{
        $sql = "UPDATE iot_device SET
          device_name='".mysqli_real_escape_string($conn,$name)."',
          user_id=$uid,
          sensor_type='".mysqli_real_escape_string($conn,$sensor)."',
          temp_min=$tmin,temp_max=$tmax,humidity_min=$hmin,humidity_max=$hmax,
          alarm_enabled=$alarmEn,alarm_duration=$alarmDur,report_interval=$repInt,
          status=$status,remark='".mysqli_real_escape_string($conn,$remark)."'
          WHERE id=$id LIMIT 1";
        if(@mysqli_query($conn,$sql)) redirect('device_manage.php','设备 #'.$id.' 更新成功');
        else $err='更新失败：'.mysqli_error($conn);
    }
}
elseif($action==='del' && $_GET['id']){
    $id = intval($_GET['id']);
    @mysqli_query($conn,"DELETE FROM iot_device WHERE id=$id LIMIT 1");
    @mysqli_query($conn,"DELETE FROM iot_sensor_data WHERE device_id=$id");
    @mysqli_query($conn,"DELETE FROM iot_alarm_log WHERE device_id=$id");
    redirect('device_manage.php','设备已删除（含相关数据）');
}
elseif($action==='toggle_alarm' && $_GET['id']){
    $id = intval($_GET['id']);
    $d = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT alarm_enabled FROM iot_device WHERE id=$id LIMIT 1"));
    if($d){
        $new = $d['alarm_enabled']==1 ? 0 : 1;
        @mysqli_query($conn,"UPDATE iot_device SET alarm_enabled=$new WHERE id=$id LIMIT 1");
    }
    redirect('device_manage.php','报警状态已切换');
}
elseif($action==='handle_alarm' && $_GET['id']){
    $id = intval($_GET['id']);
    @mysqli_query($conn,"UPDATE iot_alarm_log SET handled=1,handle_time=NOW(),handle_remark='后台已处理' WHERE id=$id LIMIT 1");
    redirect('device_manage.php?view=alarm','报警 #'.$id.' 已处理');
}

// ============ 数据查询 ============
$list = [];
$res = @mysqli_query($conn,"SELECT * FROM iot_device ORDER BY create_time DESC LIMIT 100");
if($res){
    while($r = mysqli_fetch_assoc($res)){
        $ri = intval($r['report_interval']) * 2;
        if($r['last_report_time']){
            $sec = time() - strtotime($r['last_report_time']);
            $r['online'] = ($sec < $ri && $sec >= 0) ? 1 : 0;
        }else $r['online'] = 0;
        $r['_alarm_today'] = intval(@mysqli_fetch_row(@mysqli_query($conn,"SELECT COUNT(*) FROM iot_alarm_log WHERE device_id=".$r['id']." AND DATE(create_time)=CURDATE()"))[0]);
        $r['_alarm_pending'] = intval(@mysqli_fetch_row(@mysqli_query($conn,"SELECT COUNT(*) FROM iot_alarm_log WHERE device_id=".$r['id']." AND handled=0"))[0]);
        $list[] = $r;
    }
}

// 报警日志
$alarm_list = [];
$ares = @mysqli_query($conn,"SELECT a.*,d.device_name FROM iot_alarm_log a LEFT JOIN iot_device d ON a.device_id=d.id ORDER BY a.create_time DESC LIMIT 100");
if($ares) while($r=mysqli_fetch_assoc($ares)) $alarm_list[]=$r;

$view = $_GET['view'] ?? 'list';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>设备管理 - 后台</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f1f5f9;color:#1e293b;display:flex;min-height:100vh;}
.sidebar{width:220px;background:linear-gradient(180deg,#0f172a 0%,#1e293b 100%);color:#fff;padding:20px 0;flex-shrink:0;}
.side-brand{padding:10px 22px 20px;border-bottom:1px solid rgba(255,255,255,0.08);margin-bottom:10px;}
.side-brand h2{font-size:17px;display:flex;align-items:center;gap:8px;margin-bottom:4px;}
.side-brand p{font-size:11.5px;color:#94a3b8;}
.side-user{padding:12px 22px 18px;font-size:12.5px;color:#cbd5e1;border-bottom:1px solid rgba(255,255,255,0.06);margin-bottom:10px;}
.side-user b{color:#fff;font-size:13px;}
.side-menu a{display:block;padding:12px 22px;color:#cbd5e1;text-decoration:none;font-size:14px;transition:all 0.2s;display:flex;align-items:center;gap:10px;border-left:3px solid transparent;}
.side-menu a:hover{background:rgba(102,126,234,0.1);color:#fff;}
.side-menu a.active{background:rgba(102,126,234,0.18);color:#fff;border-left-color:#a78bfa;}
.main{flex:1;padding:24px 28px;overflow-x:auto;}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px;}
.page-title{font-size:22px;font-weight:800;display:flex;align-items:center;gap:10px;}
.btn{padding:6px 11px;border-radius:7px;font-size:12px;font-weight:600;border:none;cursor:pointer;transition:all 0.2s;text-decoration:none;display:inline-flex;align-items:center;gap:4px;}
.btn:hover{transform:translateY(-1px);box-shadow:0 4px 10px rgba(0,0,0,0.1);}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;}
.btn-success{background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;}
.btn-warning{background:linear-gradient(135deg,#f6d365,#fda085);color:#fff;}
.btn-ghost{background:#f1f5f9;color:#475569;}
.btn-danger{color:#ef4444;background:#fee2e2;}
.btn-sm{padding:4px 8px;font-size:11px;}
.btn-lg{padding:10px 20px;font-size:14px;}
.toast{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;font-weight:500;}
.toast.err{background:#fee2e2;color:#b91c1c;border-left:4px solid #ef4444;}
.toast.ok{background:#dcfce7;color:#166534;border-left:4px solid #10b981;}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:16px;}
.stat{background:#fff;border-radius:12px;padding:16px 20px;box-shadow:0 2px 8px rgba(0,0,0,0.05);}
.stat-label{font-size:12px;color:#64748b;margin-bottom:5px;}
.stat-val{font-size:22px;font-weight:800;}
.stat.blue .stat-val{color:#3b82f6;}
.stat.green .stat-val{color:#10b981;}
.stat.purple .stat-val{color:#8b5cf6;}
.stat.red .stat-val{color:#ef4444;}
.panel{background:#fff;border-radius:14px;padding:22px 24px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:18px;}
.panel-title{font-size:16px;font-weight:700;margin-bottom:14px;color:#0f172a;padding-bottom:10px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px;}
table{width:100%;border-collapse:collapse;font-size:13px;}
thead th{background:#f8fafc;color:#475569;padding:10px 7px;text-align:center;border-bottom:2px solid #e2e8f0;font-weight:600;}
tbody td{padding:10px 7px;text-align:center;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle;}
tbody tr:hover{background:#f8fafc;}
.tag{display:inline-block;padding:2px 9px;border-radius:11px;font-size:11px;font-weight:600;}
.tag-green{background:#dcfce7;color:#166534;}
.tag-blue{background:#dbeafe;color:#1e40af;}
.tag-red{background:#fee2e2;color:#b91c1c;}
.tag-orange{background:#ffedd5;color:#9a3412;}
.tag-gray{background:#f1f5f9;color:#64748b;}
.dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:4px;}
.dot.on{background:#10b981;box-shadow:0 0 6px #10b981;}
.dot.off{background:#cbd5e1;}
.tabs{display:flex;gap:8px;margin-bottom:14px;}
.tab{padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;background:#e2e8f0;color:#475569;}
.tab.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;}
.modal-mask{position:fixed;inset:0;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center;z-index:999;padding:20px;}
.modal-mask.show{display:flex;}
.modal{background:#fff;border-radius:14px;padding:26px 28px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;}
.modal h3{margin-bottom:16px;font-size:17px;}
.modal .form-item{margin-bottom:14px;display:flex;flex-direction:column;gap:6px;}
.modal label{font-size:12.5px;font-weight:600;color:#475569;}
.modal input,.modal select,.modal textarea{padding:9px 12px;border:1px solid #dee2e6;border-radius:8px;font-size:14px;outline:none;}
.modal .row2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.modal .row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}
.modal .btn{padding:10px 16px;justify-content:center;font-size:13px;}
.mask-close{cursor:pointer;}
.chart-box{position:relative;height:340px;}
.token-box{background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:10px 14px;font-family:monospace;font-size:12px;color:#64748b;word-break:break-all;display:flex;justify-content:space-between;align-items:center;gap:10px;}
.token-box button{flex-shrink:0;}
.switch{display:inline-flex;align-items:center;gap:8px;cursor:pointer;}
.switch input{display:none;}
.switch .slider{width:42px;height:22px;background:#cbd5e1;border-radius:11px;position:relative;transition:0.2s;}
.switch .slider::after{content:'';position:absolute;top:2px;left:2px;width:18px;height:18px;background:#fff;border-radius:50%;transition:0.2s;}
.switch input:checked + .slider{background:#10b981;}
.switch input:checked + .slider::after{left:22px;}
.empty{text-align:center;padding:50px;color:#94a3b8;}
</style>
</head>
<body>
<aside class="sidebar">
    <div class="side-brand">
        <h2>🛡️ Zaslon 商城</h2>
        <p>管理后台 v1.0</p>
    </div>
    <div class="side-user">当前管理员：<br><b><?php echo e($admin_name);?></b></div>
    <nav class="side-menu">
        <a href="index.php">📊 控制台</a>
        <a href="recharge.php">💰 用户充值</a>
        <a href="goods_manage.php">📦 商品管理</a>
        <a href="order_manage.php">🧾 订单管理</a>
        <a href="user_manage.php">👥 用户管理</a>
        <a href="device_manage.php" class="active">🌡️ 设备管理</a>
        <a href="logout.php" style="margin-top:22px;color:#fca5a5;border-top:1px solid rgba(255,255,255,0.05);padding-top:14px;">🚪 退出登录</a>
    </nav>
</aside>
<main class="main">
    <div class="topbar">
        <div class="page-title">🌡️ 设备管理 <small style="font-size:13px;font-weight:400;color:#64748b;">ESP32 温湿度监控</small></div>
        <button class="btn btn-primary btn-lg" onclick="openModal()">➕ 新增设备</button>
    </div>
    <?php if($toast){?><div class="toast ok"><?php echo e($toast);?></div><?php }?>
    <?php if($err){?><div class="toast err"><?php echo e($err);?></div><?php }?>

    <div class="stat-grid">
        <div class="stat blue"><div class="stat-label">设备总数</div><div class="stat-val"><?php echo count($list);?></div></div>
        <div class="stat green"><div class="stat-label">在线设备</div><div class="stat-val"><?php echo count(array_filter($list,fn($d)=>$d['online']==1));?></div></div>
        <div class="stat orange"><div class="stat-label">今日报警</div><div class="stat-val"><?php echo array_sum(array_map(fn($d)=>$d['_alarm_today'],$list));?></div></div>
        <div class="stat red"><div class="stat-label">待处理报警</div><div class="stat-val"><?php echo array_sum(array_map(fn($d)=>$d['_alarm_pending'],$list));?></div></div>
    </div>

    <div class="tabs">
        <div class="tab <?php echo $view==='list'?'active':'';?>" onclick="location.href='?view=list'">📡 设备列表</div>
        <div class="tab <?php echo $view==='alarm'?'active':'';?>" onclick="location.href='?view=alarm'">🔔 报警日志 <span class="tag tag-red"><?php echo count(array_filter($alarm_list,fn($a)=>$a['handled']==0));?></span></div>
    </div>

    <?php if($view==='alarm'){?>
    <!-- ============ 报警日志 ============ -->
    <div class="panel">
        <div class="panel-title">🔔 报警日志（最近 100 条）</div>
        <?php if(empty($alarm_list)){?><div class="empty">暂无报警记录</div>
        <?php }else{?>
        <div style="overflow-x:auto;">
        <table>
            <thead><tr><th>ID</th><th>设备</th><th>温度</th><th>湿度</th><th>原因</th><th>开始时间</th><th>持续</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            <?php foreach($alarm_list as $a){?>
            <tr>
                <td style="font-weight:700;">#<?php echo $a['id'];?></td>
                <td><b><?php echo e($a['device_name'] ?? '--');?></b></td>
                <td><?php echo $a['temp']===null?'--':number_format($a['temp'],1).'℃';?></td>
                <td><?php echo $a['humidity']===null?'--':number_format($a['humidity'],1).'%';?></td>
                <td style="color:#ef4444;max-width:220px;"><?php echo e($a['reason']);?></td>
                <td style="color:#64748b;font-size:12px;"><?php echo e($a['start_time']);?></td>
                <td><?php echo intval($a['duration'])>0?intval($a['duration']).'秒':'进行中';?></td>
                <td><?php echo $a['handled']==1?'<span class="tag tag-green">已处理</span>':'<span class="tag tag-red">待处理</span>';?></td>
                <td><?php if($a['handled']==0){?><a class="btn btn-success btn-sm" href="?action=handle_alarm&id=<?php echo $a['id'];?>" onclick="return confirm('确认标记为已处理？')">✅ 处理</a><?php }else{ echo '<span style="color:#94a3b8;font-size:12px;">'.$a['handle_time'].'</span>'; }?></td>
            </tr>
            <?php }?>
            </tbody>
        </table>
        </div>
        <?php }?>
    </div>
    <?php }else{?>
    <!-- ============ 设备列表 ============ -->
    <div class="panel">
        <div class="panel-title">📡 设备列表</div>
        <?php if(empty($list)){?><div class="empty">暂无设备，点击右上角「新增设备」添加</div>
        <?php }else{?>
        <div style="overflow-x:auto;">
        <table>
            <thead><tr>
                <th>ID</th><th>设备名称</th><th>状态</th><th>最新温度</th><th>最新湿度</th>
                <th>温度阈值</th><th>湿度阈值</th><th>报警</th><th>今日报警</th><th>最后上报</th><th>操作</th>
            </tr></thead>
            <tbody>
            <?php foreach($list as $d){
                $online = $d['online']==1;
                $tempOk = $d['last_temp']!==null;
                $humOk = $d['last_humidity']!==null;
            ?>
            <tr>
                <td style="font-weight:700;">#<?php echo $d['id'];?></td>
                <td><b><?php echo e($d['device_name']);?></b><br><span style="color:#94a3b8;font-size:11px;"><?php echo e($d['device_no']);?></span></td>
                <td><span class="dot <?php echo $online?'on':'off';?>"></span><?php echo $online?'<span class="tag tag-green">在线</span>':'<span class="tag tag-gray">离线</span>';?></td>
                <td><?php echo $tempOk?number_format($d['last_temp'],1).'℃':'--';?></td>
                <td><?php echo $humOk?number_format($d['last_humidity'],1).'%':'--';?></td>
                <td style="font-size:12px;color:#64748b;"><?php echo $d['temp_min'].' ~ '.$d['temp_max'];?>℃</td>
                <td style="font-size:12px;color:#64748b;"><?php echo $d['humidity_min'].' ~ '.$d['humidity_max'];?>%</td>
                <td><?php echo $d['alarm_enabled']==1?'<span class="tag tag-green">开</span>':'<span class="tag tag-gray">关</span>';?></td>
                <td><?php if($d['_alarm_today']>0){ echo '<span class="tag tag-red">'.$d['_alarm_today'].'次</span>'; if($d['_alarm_pending']>0) echo ' <span class="tag tag-orange">'.$d['_alarm_pending'].'待</span>'; }else echo '<span style="color:#94a3b8;">0</span>';?></td>
                <td style="color:#64748b;font-size:11px;"><?php echo e($d['last_report_time'] ?? '从未上报');?></td>
                <td>
                    <a class="btn btn-ghost btn-sm" href="?view=chart&id=<?php echo $d['id'];?>">📈 图表</a>
                    <a class="btn btn-warning btn-sm" href="#" onclick='openModal(<?php echo json_encode($d,JSON_UNESCAPED_UNICODE);?>);return false;'>✏️ 编辑</a>
                    <a class="btn btn-ghost btn-sm" href="?action=toggle_alarm&id=<?php echo $d['id'];?>"><?php echo $d['alarm_enabled']==1?'🔕 关报警':'🔔 开报警';?></a>
                    <a class="btn btn-danger btn-sm" href="?action=del&id=<?php echo $d['id'];?>" onclick="return confirm('确认删除设备「<?php echo e($d['device_name']);?>」？相关数据将一并删除！')">🗑️ 删除</a>
                </td>
            </tr>
            <?php }?>
            </tbody>
        </table>
        </div>
        <?php }?>
    </div>
    <?php }?>

    <!-- ============ 历史数据图表 ============ -->
    <?php if($view==='chart' && isset($_GET['id'])){
        $cid = intval($_GET['id']);
        $cdev = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT * FROM iot_device WHERE id=$cid LIMIT 1"));
        if($cdev){
    ?>
    <div class="panel">
        <div class="panel-title">📈 <?php echo e($cdev['device_name']);?> - 最近 24 小时温湿度</div>
        <div class="chart-box"><canvas id="tempHumiChart"></canvas></div>
        <div style="margin-top:16px;text-align:center;color:#64748b;font-size:12px;">
            温度阈值：<?php echo $cdev['temp_min'];?>℃ ~ <?php echo $cdev['temp_max'];?>℃
            &nbsp;|&nbsp; 湿度阈值：<?php echo $cdev['humidity_min'];?>% ~ <?php echo $cdev['humidity_max'];?>%
            &nbsp;|&nbsp; 报警时长：<?php echo $cdev['alarm_duration'];?>秒
            &nbsp;|&nbsp; 上报间隔：<?php echo $cdev['report_interval'];?>秒
        </div>
    </div>
    <script>
    fetch('../api.php?action=getSensorData&device_id=<?php echo $cid;?>&limit=500')
        .then(r=>r.json()).then(res=>{
            if(res.code!==200 || !res.data) return;
            const data = res.data;
            const labels = data.map(d=>d.create_time.substring(11,19));
            const temps = data.map(d=>parseFloat(d.temp));
            const hums = data.map(d=>parseFloat(d.humidity));
            new Chart(document.getElementById('tempHumiChart'),{
                type:'line',
                data:{
                    labels:labels,
                    datasets:[
                        {label:'温度 ℃',data:temps,borderColor:'#ef4444',backgroundColor:'rgba(239,68,68,0.08)',tension:0.3,fill:true,yAxisID:'y'},
                        {label:'湿度 %',data:hums,borderColor:'#3b82f6',backgroundColor:'rgba(59,130,246,0.08)',tension:0.3,fill:true,yAxisID:'y1'}
                    ]
                },
                options:{
                    responsive:true,maintainAspectRatio:false,
                    interaction:{mode:'index',intersect:false},
                    scales:{
                        y:{type:'linear',position:'left',title:{display:true,text:'温度 ℃'},min:Math.min(...temps.filter(t=>!isNaN(t)))-2,max:Math.max(...temps.filter(t=>!isNaN(t)))+2},
                        y1:{type:'linear',position:'right',title:{display:true,text:'湿度 %'},min:0,max:100,grid:{drawOnChartArea:false}}
                    }
                }
            });
        });
    </script>
    <?php }}?>
</main>

<!-- ============ 新增/编辑弹窗 ============ -->
<div class="modal-mask" id="devMask">
    <div class="modal">
        <h3 id="modalTitle">➕ 新增设备</h3>
        <form method="post" id="devForm">
            <input type="hidden" name="action" id="f_action" value="add">
            <input type="hidden" name="id" id="f_id">
            <div class="form-item">
                <label>设备编号 device_no *（ESP32 的 MAC 或芯片ID）</label>
                <input type="text" id="f_device_no" name="device_no" required placeholder="例如：A4:CF:12:34:56:78">
            </div>
            <div class="form-item">
                <label>设备名称 *</label>
                <input type="text" id="f_device_name" name="device_name" required placeholder="例如：客厅温湿度监测">
            </div>
            <div class="row2">
                <div class="form-item">
                    <label>传感器型号</label>
                    <select id="f_sensor_type" name="sensor_type">
                        <option value="DHT11">DHT11</option>
                        <option value="DHT22">DHT22 (AM2302)</option>
                        <option value="SHT30">SHT30</option>
                        <option value="BME280">BME280</option>
                    </select>
                </div>
                <div class="form-item">
                    <label>绑定用户ID</label>
                    <input type="number" id="f_user_id" name="user_id" value="0">
                </div>
            </div>

            <div style="background:#f8fafc;padding:10px 14px;border-radius:8px;margin:10px 0;font-size:12.5px;color:#64748b;">
                🌡️ 温湿度阈值设置（超出范围触发报警）
            </div>
            <div class="row2">
                <div class="form-item"><label>温度下限 ℃</label><input type="number" step="0.1" id="f_temp_min" name="temp_min" value="-40"></div>
                <div class="form-item"><label>温度上限 ℃</label><input type="number" step="0.1" id="f_temp_max" name="temp_max" value="85"></div>
            </div>
            <div class="row2">
                <div class="form-item"><label>湿度下限 %</label><input type="number" step="0.1" id="f_humidity_min" name="humidity_min" value="0"></div>
                <div class="form-item"><label>湿度上限 %</label><input type="number" step="0.1" id="f_humidity_max" name="humidity_max" value="100"></div>
            </div>

            <div style="background:#f8fafc;padding:10px 14px;border-radius:8px;margin:10px 0;font-size:12.5px;color:#64748b;">
                🔔 报警配置
            </div>
            <div class="row3">
                <div class="form-item">
                    <label>报警开关</label>
                    <select name="alarm_enabled">
                        <option value="1">开启</option><option value="0">关闭</option>
                    </select>
                </div>
                <div class="form-item">
                    <label>蜂鸣器持续（秒）</label>
                    <input type="number" id="f_alarm_duration" name="alarm_duration" value="30" min="1">
                </div>
                <div class="form-item">
                    <label>上报间隔（秒）</label>
                    <input type="number" id="f_report_interval" name="report_interval" value="10" min="1">
                </div>
            </div>

            <div class="row2">
                <div class="form-item">
                    <label>设备状态</label>
                    <select name="status"><option value="1">启用</option><option value="0">停用</option></select>
                </div>
                <div class="form-item">
                    <label>备注</label>
                    <input type="text" name="remark" placeholder="可选">
                </div>
            </div>

            <div class="token-box" id="tokenBox" style="display:none;">
                <span>🔑 device_token: <b id="tokenText" style="color:#334155;"></b></span>
                <button type="button" class="btn btn-sm btn-ghost" onclick="copyToken()">📋 复制</button>
            </div>

            <div class="row" style="display:flex;gap:10px;margin-top:18px;">
                <button type="button" class="btn btn-ghost mask-close" style="flex:1;">取消</button>
                <button type="submit" class="btn btn-primary" style="flex:1;" onclick="return confirm('确认保存？')">✅ 保存</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(d){
    const mask = document.getElementById('devMask');
    const form = document.getElementById('devForm');
    const title = document.getElementById('modalTitle');
    const tokenBox = document.getElementById('tokenBox');
    form.reset();
    if(d){
        title.textContent = '✏️ 编辑设备 #'+d.id;
        document.getElementById('f_action').value = 'edit';
        document.getElementById('f_id').value = d.id;
        document.getElementById('f_device_no').value = d.device_no;
        document.getElementById('f_device_no').readonly = true;
        document.getElementById('f_device_name').value = d.device_name;
        document.getElementById('f_sensor_type').value = d.sensor_type || 'DHT11';
        document.getElementById('f_user_id').value = d.user_id || 0;
        document.getElementById('f_temp_min').value = d.temp_min;
        document.getElementById('f_temp_max').value = d.temp_max;
        document.getElementById('f_humidity_min').value = d.humidity_min;
        document.getElementById('f_humidity_max').value = d.humidity_max;
        form.alarm_enabled.value = d.alarm_enabled;
        document.getElementById('f_alarm_duration').value = d.alarm_duration;
        document.getElementById('f_report_interval').value = d.report_interval;
        form.status.value = d.status;
        form.remark.value = d.remark || '';
        tokenBox.style.display = 'flex';
        document.getElementById('tokenText').textContent = d.device_token;
        window.__curToken = d.device_token;
    }else{
        title.textContent = '➕ 新增设备';
        document.getElementById('f_action').value = 'add';
        document.getElementById('f_id').value = '';
        document.getElementById('f_device_no').readonly = false;
        tokenBox.style.display = 'none';
    }
    mask.classList.add('show');
}
function copyToken(){
    navigator.clipboard.writeText(window.__curToken).then(()=>alert('Token 已复制！烧录到 ESP32 时使用。'));
}
document.querySelectorAll('.mask-close').forEach(el=>{
    el.addEventListener('click',()=>document.getElementById('devMask').classList.remove('show'));
});
document.getElementById('devMask').addEventListener('click',e=>{ if(e.target.id==='devMask') e.target.classList.remove('show'); });
document.addEventListener('keydown',e=>{ if(e.key==='Escape') document.getElementById('devMask').classList.remove('show'); });
</script>
</body>
</html>
