<?php
include __DIR__.'/common.php';
need_admin_login();
$toast = toast_pop();
$admin_name = admin_name();

$user_total    = mysqli_fetch_row(mysqli_query($conn, 'SELECT COUNT(*) FROM user_list'))[0] ?? 0;
$goods_total   = mysqli_fetch_row(mysqli_query($conn, 'SELECT COUNT(*) FROM goods_list WHERE status=1'))[0] ?? 0;
$order_total   = mysqli_fetch_row(mysqli_query($conn, 'SELECT COUNT(*) FROM order_list'))[0] ?? 0;

$_has_pay = col_exists('order_list','pay_status') && col_exists('order_list','total_price');
if($_has_pay){
    $paid_total = mysqli_fetch_row(mysqli_query($conn, 'SELECT COALESCE(SUM(CASE WHEN pay_status=1 THEN total_price ELSE price END),0) FROM order_list'))[0] ?? 0;
}else{
    $paid_total = mysqli_fetch_row(mysqli_query($conn, 'SELECT COALESCE(SUM(price),0) FROM order_list'))[0] ?? 0;
}
$_has_bal = col_exists('user_list','balance');
$balance_sum = $_has_bal ? (mysqli_fetch_row(mysqli_query($conn, 'SELECT COALESCE(SUM(balance),0) FROM user_list'))[0] ?? 0) : 0;
$_has_rl  = col_exists('recharge_log','status');
$recharge_sum = $_has_rl ? (mysqli_fetch_row(mysqli_query($conn, 'SELECT COALESCE(SUM(amount),0) FROM recharge_log WHERE status=1'))[0] ?? 0) : 0;

$recent_orders = [];
$ro = mysqli_query($conn, "SELECT o.*,u.name as user_name FROM order_list o LEFT JOIN user_list u ON o.user_id=u.id ORDER BY o.id DESC LIMIT 8");
while($r=mysqli_fetch_assoc($ro)) $recent_orders[] = $r;

$top_users = [];
$tu = mysqli_query($conn, "SELECT id,name,phone,balance,create_time FROM user_list ORDER BY balance DESC LIMIT 6");
while($r=mysqli_fetch_assoc($tu)) $top_users[] = $r;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理后台 - Zaslon 商城</title>
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
.toast{background:#fff;border-radius:10px;padding:12px 16px;margin-bottom:18px;border-left:4px solid #10b981;font-size:14px;font-weight:500;box-shadow:0 2px 8px rgba(0,0,0,0.06);}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:22px;}
.stat{background:#fff;border-radius:13px;padding:20px 22px;box-shadow:0 2px 10px rgba(0,0,0,0.05);transition:all 0.2s;}
.stat:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(0,0,0,0.08);}
.stat-label{font-size:12.5px;color:#64748b;margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.stat-val{font-size:26px;font-weight:800;line-height:1.2;}
.stat.blue .stat-val{color:#3b82f6;}
.stat.green .stat-val{color:#10b981;}
.stat.purple .stat-val{color:#8b5cf6;}
.stat.orange .stat-val{color:#f59e0b;}
.stat.pink .stat-val{color:#ec4899;}
.stat-foot{margin-top:8px;font-size:12px;color:#94a3b8;}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px;}
@media(max-width:980px){.two-col{grid-template-columns:1fr;}}
.panel{background:#fff;border-radius:14px;padding:22px 24px;box-shadow:0 2px 10px rgba(0,0,0,0.05);}
.panel-title{font-size:16px;font-weight:700;margin-bottom:14px;color:#0f172a;display:flex;align-items:center;gap:8px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;}
.panel-title a{margin-left:auto;font-size:12.5px;color:#667eea;text-decoration:none;font-weight:600;}
.panel-title a:hover{text-decoration:underline;}
table{width:100%;border-collapse:collapse;font-size:13.5px;}
thead th{background:#f8fafc;color:#475569;padding:11px 8px;text-align:left;border-bottom:2px solid #e2e8f0;font-weight:600;}
tbody td{padding:11px 8px;border-bottom:1px solid #f1f5f9;color:#334155;}
tbody tr:hover{background:#f8fafc;}
.tag{display:inline-block;padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:600;}
.tag-blue{background:#dbeafe;color:#1e40af;}
.tag-green{background:#dcfce7;color:#166534;}
.tag-orange{background:#fed7aa;color:#9a3412;}
.tag-gray{background:#f1f5f9;color:#64748b;}
.tag-purple{background:#ede9fe;color:#6d28d9;}
.price{color:#ef4444;font-weight:700;}
.user-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;background:#f1f5f9;border-radius:12px;font-size:12px;font-weight:600;}
.btn{padding:7px 13px;border-radius:7px;font-size:12.5px;font-weight:600;border:none;cursor:pointer;transition:all 0.2s;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
.btn:hover{transform:translateY(-1px);box-shadow:0 4px 10px rgba(0,0,0,0.1);}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;}
.btn-success{background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;}
.btn-warning{background:linear-gradient(135deg,#f6d365,#fda085);color:#fff;}
.btn-ghost{background:#f1f5f9;color:#475569;}
.btn-danger{color:#ef4444;background:#fee2e2;}
.balance{color:#10b981;font-weight:700;}
</style>
</head>
<body>
<aside class="sidebar">
    <div class="side-brand">
        <h2>🛡️ Zaslon 商城</h2>
        <p>管理后台 v1.0</p>
    </div>
    <div class="side-user">
        当前管理员：<br><b><?php echo e($admin_name);?></b>
    </div>
    <nav class="side-menu">
        <a href="index.php" class="active">📊 控制台</a>
        <a href="recharge.php">💰 用户充值</a>
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
        <div class="page-title">📊 管理控制台 <small>实时数据概览</small></div>
        <a href="../shop.php" class="btn btn-ghost" target="_blank">🛒 打开前台商城</a>
    </div>
    <?php if($toast){?><div class="toast"><?php echo e($toast);?></div><?php }?>

    <div class="stat-grid">
        <div class="stat blue"><div class="stat-label">👥 注册用户</div><div class="stat-val"><?php echo $user_total;?></div><div class="stat-foot">累计注册会员</div></div>
        <div class="stat green"><div class="stat-label">📦 在售商品</div><div class="stat-val"><?php echo $goods_total;?></div><div class="stat-foot">已上架商品数</div></div>
        <div class="stat purple"><div class="stat-label">🧾 订单总数</div><div class="stat-val"><?php echo $order_total;?></div><div class="stat-foot">所有订单合计</div></div>
        <div class="stat orange"><div class="stat-label">💸 销售总额</div><div class="stat-val">¥<?php echo number_format($paid_total,2);?></div><div class="stat-foot">已支付订单累计</div></div>
        <div class="stat pink"><div class="stat-label">💰 会员总余额</div><div class="stat-val">¥<?php echo number_format($balance_sum,2);?></div><div class="stat-foot">所有账户余额合计</div></div>
        <div class="stat green"><div class="stat-label">🔋 充值总额</div><div class="stat-val">¥<?php echo number_format($recharge_sum,2);?></div><div class="stat-foot">累计成功充值</div></div>
    </div>

    <div class="two-col">
        <div class="panel">
            <div class="panel-title">🧾 最新订单 <a href="order_manage.php">查看全部 →</a></div>
            <?php if(empty($recent_orders)){?><div style="text-align:center;padding:30px;color:#94a3b8;">暂无订单</div>
            <?php } else { ?>
            <table>
                <thead><tr><th>订单号</th><th>用户</th><th>商品</th><th>金额</th><th>状态</th></tr></thead>
                <tbody>
                <?php foreach($recent_orders as $o){?>
                <tr>
                    <td>#<?php echo $o['id'];?></td>
                    <td><span class="user-badge"><?php echo e($o['user_name'] ?? 'UID:'.$o['user_id']);?></span></td>
                    <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo e(mb_substr($o['goods_name']?:$o['order_title'],0,14));?></td>
                    <td class="price">¥<?php echo number_format($o['total_price']?:$o['price'],2);?></td>
                    <td>
                        <?php if(intval($o['pay_status'])==1){?><span class="tag tag-green">已支付</span><?php } else {?><span class="tag tag-orange">待支付</span><?php }?>
                    </td>
                </tr>
                <?php }?>
                </tbody>
            </table>
            <?php }?>
        </div>
        <div class="panel">
            <div class="panel-title">💰 余额排行（TOP 6）<a href="recharge.php">前往充值 →</a></div>
            <?php if(empty($top_users)){?><div style="text-align:center;padding:30px;color:#94a3b8;">暂无用户</div>
            <?php } else { ?>
            <table>
                <thead><tr><th>UID</th><th>姓名</th><th>手机</th><th>余额</th></tr></thead>
                <tbody>
                <?php foreach($top_users as $u){?>
                <tr>
                    <td>#<?php echo $u['id'];?></td>
                    <td><?php echo e($u['name']);?></td>
                    <td style="font-size:12.5px;color:#64748b;"><?php echo e($u['phone'] ?? '--');?></td>
                    <td class="balance">¥<?php echo number_format($u['balance'],2);?></td>
                </tr>
                <?php }?>
                </tbody>
            </table>
            <?php }?>
        </div>
    </div>

    <div class="panel">
        <div class="panel-title">⚡ 快捷操作</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;">
            <a class="btn btn-primary" href="recharge.php">💰 给用户充值</a>
            <a class="btn btn-success" href="goods_manage.php?action=add">➕ 新增商品</a>
            <a class="btn btn-warning" href="order_manage.php">🧾 管理订单</a>
            <a class="btn btn-ghost" href="user_manage.php">👥 管理用户</a>
            <a class="btn btn-ghost" href="../shop.php" target="_blank">🛒 前台商城</a>
            <a class="btn btn-danger" href="logout.php">🚪 退出</a>
        </div>
    </div>
</main>
</body>
</html>
