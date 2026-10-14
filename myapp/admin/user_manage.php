<?php
include __DIR__.'/common.php';
need_admin_login();
$toast = toast_pop();
$admin_name = admin_name();
$err = '';

$action = $_GET['action'] ?? '';
if($action==='reset_pwd' && $_GET['id']){
    $uid = intval($_GET['id']);
    $new_pwd = '123456';
    $pwd_s = mysqli_real_escape_string($conn, $new_pwd);
    if(mysqli_query($conn, "UPDATE user_list SET pwd='$pwd_s' WHERE id=$uid")){
        redirect('user_manage.php','用户 #'.$uid.' 密码已重置为：'.$new_pwd);
    }else{ $err = '操作失败：'.mysqli_error($conn); }
}elseif($action==='chg_bal' && $_POST){
    $uid = intval($_POST['user_id']);
    $diff = floatval($_POST['amount']);
    $type = trim($_POST['type'] ?? 'add');
    if($uid<=0){ $err='用户ID非法'; }
    else{
        $u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM user_list WHERE id=$uid LIMIT 1"));
        if(!$u){ $err='用户不存在'; }
        else{
            $before = floatval($u['balance']);
            if($type==='sub'){ $diff = -abs($diff); }
            else { $diff = abs($diff); }
            $after = $before + $diff;
            if($after < 0){ $err='扣减后余额不可为负'; }
            else{
                $after_f = number_format($after,2,'.','');
                $before_f = number_format($before,2,'.','');
                $abs_diff = abs($diff);
                mysqli_query($conn, 'START TRANSACTION');
                $ok1 = mysqli_query($conn, "UPDATE user_list SET balance=$after_f WHERE id=$uid AND balance=$before_f");
                $op_s = mysqli_real_escape_string($conn,$admin_name);
                $rm_s = mysqli_real_escape_string($conn, '后台'.($diff>=0?'充值':'扣减').'：¥'.number_format($abs_diff,2));
                $status = 1;
                $ok2 = mysqli_query($conn, "INSERT INTO recharge_log(user_id,amount,balance_before,balance_after,operator,remark,status,create_time) VALUES($uid,$abs_diff,$before_f,$after_f,'$op_s','$rm_s',$status,NOW())");
                if($ok1 && $ok2 && mysqli_affected_rows($conn)==1){
                    mysqli_query($conn, 'COMMIT');
                    redirect('user_manage.php','用户余额已更新（'.($diff>=0?'+':'-').'¥'.number_format($abs_diff,2).'）');
                }else{
                    mysqli_query($conn, 'ROLLBACK');
                    $err='操作失败，请重试（余额可能已变动）';
                }
            }
        }
    }
}

$list = [];
$kw = trim($_GET['kw'] ?? '');
$where = '1=1';
if($kw){
    $kw_s = mysqli_real_escape_string($conn,$kw);
    $where .= " AND (name LIKE '%$kw_s%' OR phone LIKE '%$kw_s%' OR id LIKE '%$kw_s%' OR address LIKE '%$kw_s%')";
}
$res = mysqli_query($conn, "SELECT u.*, 
    (SELECT COUNT(*) FROM order_list o WHERE o.user_id=u.id) as order_cnt,
    (SELECT COALESCE(SUM(CASE WHEN pay_status=1 THEN total_price ELSE price END),0) FROM order_list o WHERE o.user_id=u.id) as pay_total
    FROM user_list u WHERE $where ORDER BY id DESC LIMIT 200");
while($row=mysqli_fetch_assoc($res)) $list[] = $row;

$total_balance = 0;
foreach($list as $u) $total_balance += floatval($u['balance']??0);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>用户管理 - 后台</title>
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
.panel{background:#fff;border-radius:14px;padding:22px 24px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:18px;}
.panel-title{font-size:16px;font-weight:700;margin-bottom:14px;color:#0f172a;padding-bottom:10px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px;}
.tool-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px;}
.tool-row form{display:flex;gap:10px;flex:1;flex-wrap:wrap;}
.tool-row input{padding:7px 11px;border:1px solid #dee2e6;border-radius:7px;outline:none;font-size:13px;min-width:220px;}
table{width:100%;border-collapse:collapse;font-size:13px;}
thead th{background:#f8fafc;color:#475569;padding:10px 7px;text-align:center;border-bottom:2px solid #e2e8f0;font-weight:600;}
tbody td{padding:10px 7px;text-align:center;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle;}
tbody tr:hover{background:#f8fafc;}
.balance{color:#10b981;font-weight:700;}
.balance.zero{color:#94a3b8;font-weight:500;}
.tag{display:inline-block;padding:2px 9px;border-radius:11px;font-size:11px;font-weight:600;}
.tag-green{background:#dcfce7;color:#166534;}
.tag-blue{background:#dbeafe;color:#1e40af;}
.tag-gray{background:#f1f5f9;color:#64748b;}
.empty{text-align:center;padding:50px;color:#94a3b8;}
.modal-mask{position:fixed;inset:0;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center;z-index:999;padding:20px;}
.modal-mask.show{display:flex;}
.modal{background:#fff;border-radius:14px;padding:26px 28px;width:100%;max-width:430px;}
.modal h3{margin-bottom:16px;font-size:17px;}
.modal .form-item{margin-bottom:14px;display:flex;flex-direction:column;gap:6px;}
.modal label{font-size:12.5px;font-weight:600;color:#475569;}
.modal input,.modal select{padding:9px 12px;border:1px solid #dee2e6;border-radius:8px;font-size:14px;outline:none;}
.modal .row{display:flex;gap:10px;}
.modal .btn{padding:10px 16px;justify-content:center;font-size:13px;}
.mask-close{cursor:pointer;}
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
        <a href="user_manage.php" class="active">👥 用户管理</a>
        <a href="device_manage.php">🌡️ 设备管理</a>
        <a href="logout.php" style="margin-top:22px;color:#fca5a5;border-top:1px solid rgba(255,255,255,0.05);padding-top:14px;">🚪 退出登录</a>
    </nav>
</aside>
<main class="main">
    <div class="topbar">
        <div class="page-title">👥 用户管理 <small>充值 / 重置密码 / 调整余额</small></div>
        <a href="recharge.php" class="btn btn-primary">💳 快速充值</a>
    </div>
    <?php if($toast){?><div class="toast ok"><?php echo e($toast);?></div><?php }?>
    <?php if($err){?><div class="toast err"><?php echo e($err);?></div><?php }?>

    <div class="stat-grid">
        <div class="stat blue"><div class="stat-label">用户总数</div><div class="stat-val"><?php echo count($list);?></div></div>
        <div class="stat green"><div class="stat-label">余额总计</div><div class="stat-val">¥<?php echo number_format($total_balance,2);?></div></div>
        <div class="stat purple"><div class="stat-label">有余额用户</div><div class="stat-val"><?php echo count(array_filter($list,fn($u)=>floatval($u['balance']??0)>0));?></div></div>
    </div>

    <div class="panel">
        <div class="panel-title">🔍 搜索用户（UID / 姓名 / 手机号 / 地址）</div>
        <div class="tool-row">
            <form method="get">
                <input type="text" name="kw" placeholder="输入搜索关键词" value="<?php echo e($kw);?>">
                <button class="btn btn-primary btn-sm">🔍 搜索</button>
                <?php if($kw){?><a href="user_manage.php" class="btn btn-ghost btn-sm">↺ 重置</a><?php }?>
            </form>
        </div>
        <?php if(empty($list)){?><div class="empty">暂无用户</div>
        <?php } else {?>
        <div style="overflow-x:auto;">
        <table>
            <thead><tr>
                <th>UID</th><th>姓名</th><th>手机号</th><th>地址</th>
                <th>账户余额</th><th>订单数</th><th>累计消费</th>
                <th>注册时间</th><th>操作</th>
            </tr></thead>
            <tbody>
            <?php foreach($list as $u){
                $b = floatval($u['balance']??0);
            ?>
            <tr>
                <td style="font-weight:700;">#<?php echo $u['id'];?></td>
                <td><b><?php echo e($u['name']);?></b></td>
                <td style="color:#64748b;"><?php echo e($u['phone'] ?? '--');?></td>
                <td style="color:#64748b;font-size:12px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo e($u['address'] ?: '--');?></td>
                <td class="balance <?php echo $b<=0?'zero':'';?>">¥<?php echo number_format($b,2);?></td>
                <td><span class="tag tag-blue"><?php echo intval($u['order_cnt']);?> 笔</span></td>
                <td style="color:#ef4444;font-weight:700;"><?php echo floatval($u['pay_total'])>0?'¥'.number_format($u['pay_total'],2):'--';?></td>
                <td style="color:#94a3b8;font-size:11.5px;"><?php echo $u['create_time'] ?? '--';?></td>
                <td>
                    <a class="btn btn-success btn-sm" href="recharge.php#userSelect" onclick="sessionStorage.setItem('recharge_uid',<?php echo $u['id'];?>);">💳 充值</a>
                    <a class="btn btn-warning btn-sm" href="#" onclick="openBal(<?php echo $u['id'];?>,'<?php echo e($u['name']);?>',<?php echo $b;?>);return false;">⚖️ 调余额</a>
                    <a class="btn btn-ghost btn-sm" href="?action=reset_pwd&id=<?php echo $u['id'];?>" onclick="return confirm('确认重置用户「<?php echo e($u['name']);?>」的密码为 123456？')">🔑 重置密码</a>
                </td>
            </tr>
            <?php }?>
            </tbody>
        </table>
        </div>
        <?php }?>
    </div>
</main>

<div class="modal-mask" id="balMask">
    <div class="modal">
        <h3>⚖️ 调整用户余额</h3>
        <div id="balUserInfo" style="background:#f8fafc;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;"></div>
        <form method="post">
            <input type="hidden" name="action" value="chg_bal">
            <input type="hidden" name="user_id" id="bal_uid" value="">
            <div class="form-item">
                <label>调整类型</label>
                <select name="type" id="bal_type" onchange="updateBalPreview()">
                    <option value="add">➕ 增加余额（充值）</option>
                    <option value="sub">➖ 扣减余额</option>
                </select>
            </div>
            <div class="form-item">
                <label>金额（¥）</label>
                <input type="number" id="bal_amt" step="0.01" min="0.01" name="amount" placeholder="请输入金额" oninput="updateBalPreview()" required>
            </div>
            <div class="form-item" id="bal_preview" style="color:#64748b;font-size:12.5px;"></div>
            <div class="row">
                <button type="button" class="btn btn-ghost mask-close" style="flex:1;">取消</button>
                <button type="submit" class="btn btn-primary" style="flex:1;" onclick="return confirm('确认调整余额？')">✅ 确认调整</button>
            </div>
        </form>
    </div>
</div>
<script>
function openBal(id,name,bal){
    document.getElementById('balMask').classList.add('show');
    document.getElementById('bal_uid').value = id;
    document.getElementById('balUserInfo').innerHTML = '<b>👤 '+name+'</b> (UID: '+id+') · 当前余额：<b style="color:#10b981;">¥'+bal.toFixed(2)+'</b>';
    window.__curBal = bal;
    updateBalPreview();
}
function updateBalPreview(){
    const b = parseFloat(window.__curBal || 0);
    const t = document.getElementById('bal_type').value;
    const a = parseFloat(document.getElementById('bal_amt').value||0);
    const diff = (t==='add'?1:-1) * a;
    const new_b = b + diff;
    const tip = t==='add' ? '<span style="color:#10b981;">+¥'+a.toFixed(2)+'</span>' : '<span style="color:#ef4444;">-¥'+a.toFixed(2)+'</span>';
    const tip2 = (new_b<0 ? '<span style="color:#ef4444;">⚠️ 扣减后余额为负，请检查</span>' : '<span style="color:#667eea;">¥'+new_b.toFixed(2)+'</span>');
    document.getElementById('bal_preview').innerHTML = '调整：'+tip+' → 新余额：'+tip2;
}
document.querySelectorAll('.mask-close').forEach(el=>{
    el.addEventListener('click',()=>document.getElementById('balMask').classList.remove('show'));
});
document.getElementById('balMask').addEventListener('click',e=>{ if(e.target.id==='balMask') e.target.classList.remove('show'); });
document.addEventListener('keydown',e=>{ if(e.key==='Escape') document.getElementById('balMask').classList.remove('show'); });
</script>
</body>
</html>
