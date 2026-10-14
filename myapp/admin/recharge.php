<?php
include __DIR__.'/common.php';
need_admin_login();
$toast = toast_pop();
$admin_name = admin_name();
$err = '';
$ok = '';

if($_POST && ($_POST['act']??'')==='recharge'){
    $uid = intval($_POST['user_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $remark = trim($_POST['remark'] ?? '');
    if($uid<=0){ $err='请选择用户'; }
    elseif($amount<=0){ $err='充值金额必须大于0'; }
    else{
        $u = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM user_list WHERE id=$uid LIMIT 1"));
        if(!$u){ $err='用户不存在'; }
        else{
            $before = floatval($u['balance']);
            $after = $before + $amount;
            mysqli_query($conn,'START TRANSACTION');
            $ok1 = mysqli_query($conn, "UPDATE user_list SET balance=$after WHERE id=$uid");
            $op_s = mysqli_real_escape_string($conn,$admin_name);
            $rm_s = mysqli_real_escape_string($conn,$remark);
            $ok2 = mysqli_query($conn, "INSERT INTO recharge_log(user_id,amount,balance_before,balance_after,operator,remark,status,create_time)
                VALUES($uid,$amount,$before,$after,'$op_s','$rm_s',1,NOW())");
            if($ok1 && $ok2){
                mysqli_query($conn,'COMMIT');
                $ok = '充值成功！为 UID:'.$uid.' ('.$u['name'].') 到账 ¥'.number_format($amount,2).'，当前余额 ¥'.number_format($after,2);
            }else{
                mysqli_query($conn,'ROLLBACK');
                $err = '充值失败：'.mysqli_error($conn);
            }
        }
    }
}

$recent = [];
$rr = mysqli_query($conn, "SELECT r.*,u.name as user_name,u.phone as user_phone 
    FROM recharge_log r LEFT JOIN user_list u ON r.user_id=u.id 
    ORDER BY r.id DESC LIMIT 50");
while($row=mysqli_fetch_assoc($rr)) $recent[] = $row;

$today_recharge = mysqli_fetch_row(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) FROM recharge_log WHERE status=1 AND DATE(create_time)=CURDATE()"))[0] ?? 0;
$today_user = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(DISTINCT user_id) FROM recharge_log WHERE status=1 AND DATE(create_time)=CURDATE()"))[0] ?? 0;
$total_recharge = mysqli_fetch_row(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) FROM recharge_log WHERE status=1"))[0] ?? 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>用户充值 - 管理后台</title>
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
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;}
.page-title{font-size:22px;font-weight:800;display:flex;align-items:center;gap:10px;}
.page-title small{font-size:13px;color:#64748b;font-weight:500;}
.btn{padding:7px 13px;border-radius:7px;font-size:12.5px;font-weight:600;border:none;cursor:pointer;transition:all 0.2s;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
.btn:hover{transform:translateY(-1px);box-shadow:0 4px 10px rgba(0,0,0,0.1);}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;}
.btn-success{background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;}
.btn-lg{padding:11px 20px;font-size:14px;}
.btn-ghost{background:#f1f5f9;color:#475569;}
.btn-danger{color:#ef4444;background:#fee2e2;}
.toast{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;font-weight:500;}
.toast.err{background:#fee2e2;color:#b91c1c;border-left:4px solid #ef4444;}
.toast.ok{background:#dcfce7;color:#166534;border-left:4px solid #10b981;}
.tip{background:#fff;border-radius:12px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#1e40af;background:#eff6ff;border-left:4px solid #667eea;line-height:1.7;}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:20px;}
.stat{background:#fff;border-radius:13px;padding:18px 22px;box-shadow:0 2px 10px rgba(0,0,0,0.05);}
.stat-label{font-size:12.5px;color:#64748b;margin-bottom:6px;}
.stat-val{font-size:24px;font-weight:800;}
.stat.green .stat-val{color:#10b981;}
.stat.blue .stat-val{color:#3b82f6;}
.stat.orange .stat-val{color:#f59e0b;}
.panel{background:#fff;border-radius:14px;padding:24px 26px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:20px;}
.panel-title{font-size:16px;font-weight:700;margin-bottom:16px;color:#0f172a;padding-bottom:10px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px;}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;max-width:1000px;}
.form-item{display:flex;flex-direction:column;gap:6px;}
.form-item.full{grid-column:1/-1;}
.form-item label{font-size:13px;font-weight:600;color:#475569;}
.form-item select,.form-item input,.form-item textarea{padding:10px 13px;border:1px solid #dee2e6;border-radius:9px;font-size:14px;outline:none;transition:all 0.2s;font-family:inherit;}
.form-item select:focus,.form-item input:focus,.form-item textarea:focus{border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,0.12);}
.amt-quick{display:flex;gap:6px;flex-wrap:wrap;}
.amt-quick button{padding:7px 13px;background:#eff6ff;border:1px solid #dbeafe;color:#4f46e5;border-radius:8px;font-weight:600;font-size:12.5px;cursor:pointer;}
.amt-quick button:hover{background:#dbeafe;}
.user-info{background:#f8fafc;border-radius:10px;padding:12px 16px;margin:10px 0 16px;font-size:13px;line-height:2;}
table{width:100%;border-collapse:collapse;font-size:13.5px;}
thead th{background:#f8fafc;color:#475569;padding:11px 8px;text-align:left;border-bottom:2px solid #e2e8f0;font-weight:600;}
tbody td{padding:11px 8px;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle;}
tbody tr:hover{background:#f8fafc;}
.tag{display:inline-block;padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:600;}
.tag-green{background:#dcfce7;color:#166534;}
.tag-gray{background:#f1f5f9;color:#64748b;}
.amt-in{color:#10b981;font-weight:700;}
.balance{color:#10b981;font-weight:700;}
.user-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;background:#f1f5f9;border-radius:12px;font-size:12px;font-weight:600;}
.op-box{background:#f8fafc;border-radius:10px;padding:10px 14px;font-size:13px;color:#64748b;margin-top:12px;}
.op-box b{color:#667eea;}
.search-box{display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;}
.search-box input{padding:8px 12px;border:1px solid #dee2e6;border-radius:8px;outline:none;flex:1;min-width:180px;}
.search-box .btn{padding:8px 14px;}
.empty{text-align:center;padding:40px;color:#94a3b8;}
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
        <a href="recharge.php" class="active">💰 用户充值</a>
        <a href="goods_manage.php">📦 商品管理</a>
        <a href="order_manage.php">🧾 订单管理</a>
        <a href="user_manage.php">👥 用户管理</a>
        <a href="device_manage.php">🌡️ 设备管理</a>
        <a href="logout.php" style="margin-top:22px;color:#fca5a5;border-top:1px solid rgba(255,255,255,0.05);padding-top:14px;">🚪 退出登录</a>
        <a href="../shop.php" style="color:#93c5fd;">🛒 前台商城</a>
    </nav>
</aside>
<main class="main">
    <div class="topbar">
        <div class="page-title">💰 用户充值 <small>管理员操作，即时到账</small></div>
        <a href="index.php" class="btn btn-ghost">← 返回控制台</a>
    </div>

    <?php if($toast){?><div class="toast ok"><?php echo e($toast);?></div><?php }?>
    <?php if($err){?><div class="toast err"><?php echo e($err);?></div><?php }?>
    <?php if($ok){?><div class="toast ok"><?php echo e($ok);?></div><?php }?>

    <div class="tip">💡 <b>操作说明：</b>选择用户后输入充值金额，点击「立即充值」即可。所有充值记录会自动写入充值日志，用户余额即时更新。</div>

    <div class="stat-grid">
        <div class="stat green"><div class="stat-label">今日充值总额</div><div class="stat-val">¥<?php echo number_format($today_recharge,2);?></div></div>
        <div class="stat blue"><div class="stat-label">今日充值用户数</div><div class="stat-val"><?php echo $today_user;?></div></div>
        <div class="stat orange"><div class="stat-label">历史累计充值</div><div class="stat-val">¥<?php echo number_format($total_recharge,2);?></div></div>
    </div>

    <div class="panel">
        <div class="panel-title">💳 充值操作</div>
        <form method="post" id="rechargeForm">
            <input type="hidden" name="act" value="recharge">
            <div class="form-grid">
                <div class="form-item full">
                    <label>👤 搜索并选择用户（UID/姓名/手机号均可搜索）</label>
                    <select id="userSelect" name="user_id" required onchange="showUserInfo()">
                        <option value="">-- 请选择用户 --</option>
                        <?php
                        $ures = mysqli_query($conn, "SELECT id,name,phone,balance FROM user_list ORDER BY id DESC");
                        while($uu = mysqli_fetch_assoc($ures)){
                            echo '<option value="'.$uu['id'].'" data-name="'.e($uu['name']).'" data-phone="'.e($uu['phone']).'" data-balance="'.$uu['balance'].'">';
                            echo 'UID:'.$uu['id'].' | '.e($uu['name']).($uu['phone']?' | '.e($uu['phone']):'').' | 余额:¥'.number_format($uu['balance'],2);
                            echo '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="user-info full" id="userInfo" style="display:none;margin-top:-6px;"></div>
                <div class="form-item">
                    <label>💰 充值金额 (¥)<span style="color:#ef4444;"> *</span></label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0.01" placeholder="请输入金额，例如 100" required>
                </div>
                <div class="form-item" style="justify-content:center;">
                    <label>⚡ 快捷金额</label>
                    <div class="amt-quick">
                        <?php foreach([10,50,100,200,500,1000,5000] as $a){?>
                            <button type="button" onclick="document.getElementById('amount').value=<?php echo $a;?>;">¥<?php echo $a;?></button>
                        <?php }?>
                    </div>
                </div>
                <div class="form-item full">
                    <label>📝 充值备注</label>
                    <input type="text" name="remark" placeholder="选填，例如：充值卡兑换 / 活动赠送 / 客服补偿等">
                </div>
                <div class="form-item full">
                    <button type="submit" class="btn btn-success btn-lg" onclick="return confirmRecharge();">✅ 立即充值（确认信息无误）</button>
                </div>
            </div>
            <div class="op-box">本次操作管理员：<b><?php echo e($admin_name);?></b> · 充值日志将永久留存，请注意核对用户再点击。</div>
        </form>
    </div>

    <div class="panel">
        <div class="panel-title">📜 最近充值记录（50条）</div>
        <div class="search-box">
            <input type="text" id="logSearch" placeholder="🔍 搜索：用户名 / 手机号 / UID / 备注">
            <span style="display:inline-flex;align-items:center;gap:6px;color:#64748b;font-size:12.5px;">共 <b id="logCount"><?php echo count($recent);?></b> 条</span>
        </div>
        <?php if(empty($recent)){?><div class="empty">暂无充值记录</div>
        <?php } else { ?>
        <div style="overflow-x:auto;">
        <table id="logTable">
            <thead><tr>
                <th>单号</th>
                <th>用户</th>
                <th>充值金额</th>
                <th>到账前余额</th>
                <th>到账后余额</th>
                <th>管理员</th>
                <th>状态</th>
                <th>备注</th>
                <th>时间</th>
            </tr></thead>
            <tbody>
            <?php foreach($recent as $r){?>
            <tr class="log-row" data-search="<?php echo strtolower($r['user_name'].' '.$r['user_phone'].' '.$r['user_id'].' '.$r['remark'].' '.$r['id']);?>">
                <td>#<?php echo $r['id'];?></td>
                <td><span class="user-badge"><?php echo e($r['user_name'] ?? 'UID:'.$r['user_id']);?></span>
                    <div style="font-size:11px;color:#94a3b8;margin-top:2px;"><?php echo e($r['user_phone'] ?? '');?> (UID:<?php echo $r['user_id'];?>)</div>
                </td>
                <td class="amt-in">+¥<?php echo number_format($r['amount'],2);?></td>
                <td>¥<?php echo number_format($r['balance_before'],2);?></td>
                <td class="balance">¥<?php echo number_format($r['balance_after'],2);?></td>
                <td style="font-size:12.5px;"><?php echo e($r['operator']);?></td>
                <td><?php echo intval($r['status'])==1?'<span class="tag tag-green">✅ 到账</span>':'<span class="tag tag-gray">处理中</span>';?></td>
                <td style="font-size:12.5px;color:#64748b;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo e($r['remark'] ?: '--');?></td>
                <td style="font-size:12px;color:#64748b;"><?php echo $r['create_time'];?></td>
            </tr>
            <?php }?>
            </tbody>
        </table>
        </div>
        <?php }?>
    </div>
</main>
<script>
function showUserInfo(){
    const sel = document.getElementById('userSelect');
    const opt = sel.options[sel.selectedIndex];
    const box = document.getElementById('userInfo');
    if(!opt || !opt.value){ box.style.display='none'; return; }
    const name = opt.getAttribute('data-name')||'';
    const phone = opt.getAttribute('data-phone')||'';
    const bal = parseFloat(opt.getAttribute('data-balance')||0);
    box.innerHTML = '<b>✅ 已选择用户：</b>'+name+' · 📱 '+phone+' · <span style="color:#10b981;">💳 当前余额：¥'+bal.toFixed(2)+'</span> · 充值后余额预览：<b id="newBal" style="color:#667eea;">¥'+bal.toFixed(2)+'</b>';
    box.style.display='block';
    updateBal();
}
function updateBal(){
    const sel = document.getElementById('userSelect');
    const opt = sel.options[sel.selectedIndex];
    if(!opt || !opt.value) return;
    const bal = parseFloat(opt.getAttribute('data-balance')||0);
    const amt = parseFloat(document.getElementById('amount').value||0);
    document.getElementById('newBal').innerText = '¥'+(bal+amt).toFixed(2);
}
document.getElementById('amount').addEventListener('input', updateBal);
function confirmRecharge(){
    const sel = document.getElementById('userSelect');
    const amt = document.getElementById('amount').value;
    if(!sel.value){ alert('请先选择用户'); return false; }
    const opt = sel.options[sel.selectedIndex];
    const name = opt.getAttribute('data-name')||'';
    if(!amt || parseFloat(amt)<=0){ alert('请输入有效金额'); return false; }
    return confirm('【确认充值】\n为用户：'+name+' (UID:'+sel.value+')\n充值金额：¥'+parseFloat(amt).toFixed(2)+'\n\n请确认无误，充值后无法撤销。');
}
document.getElementById('logSearch').addEventListener('input',function(e){
    const kw = e.target.value.trim().toLowerCase();
    const rows = document.querySelectorAll('.log-row');
    let c = 0;
    rows.forEach(r=>{
        if(!kw || r.dataset.search.indexOf(kw)>=0){ r.style.display=''; c++; }
        else r.style.display='none';
    });
    document.getElementById('logCount').innerText = c;
});
</script>
</body>
</html>
