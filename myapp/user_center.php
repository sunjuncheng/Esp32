<?php
include 'config.php';
include 'common.php';
need_login('login.php?redirect=user_center.php');
$toast = toast_pop();
$user = current_user();
if(!$user){ redirect('login.php','请先登录'); }
$uid = intval($user['id']);

$highlight_order = intval($_GET['order'] ?? 0);
$tab = $_GET['tab'] ?? 'orders';

$o_where = "WHERE user_id=$uid";
$order_list = [];
$ores = mysqli_query($conn, "SELECT * FROM order_list $o_where ORDER BY id DESC LIMIT 100");
while($r = mysqli_fetch_assoc($ores)) $order_list[] = $r;

$paid_sum = 0;
foreach($order_list as $o){
    $is_paid = col_exists('order_list','pay_status') ? (intval($o['pay_status']??1)==1) : true;
    if($is_paid) $paid_sum += floatval($o['total_price'] ?? $o['price']);
}

$recharge_list = [];
$_has_rl = true;
$rres = @mysqli_query($conn, "SELECT * FROM recharge_log WHERE user_id=$uid ORDER BY id DESC LIMIT 50");
if($rres){ while($r = mysqli_fetch_assoc($rres)) $recharge_list[] = $r; }
else{ $_has_rl = false; }
$recharge_sum = 0;
foreach($recharge_list as $r){ if(intval($r['status'])==1) $recharge_sum += floatval($r['amount']); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>个人中心 - <?php echo e($user['name']);?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f5f7fa;color:#333;}
.nav{background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.06);position:sticky;top:0;z-index:50;}
.nav-inner{max-width:1200px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;}
.logo{font-size:20px;font-weight:800;background:linear-gradient(135deg,#667eea,#764ba2);-webkit-background-clip:text;color:transparent;text-decoration:none;display:flex;align-items:center;gap:8px;}
.btn{padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;border:none;cursor:pointer;transition:all 0.2s;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.btn:hover{transform:translateY(-1px);}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;}
.btn-success{background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;}
.btn-ghost{background:#f1f3f5;color:#666;}
.btn-sm{padding:6px 10px;font-size:12px;}
.wrap{max-width:1200px;margin:0 auto;padding:24px 20px 60px;}
.toast{background:#fff;border-radius:10px;padding:12px 16px;margin-bottom:18px;border-left:4px solid #10b981;font-size:14px;font-weight:500;box-shadow:0 2px 8px rgba(0,0,0,0.06);}
.toast.ok{border-left-color:#10b981;}
.card-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-bottom:22px;}
.card{background:#fff;border-radius:14px;padding:22px 24px;box-shadow:0 2px 12px rgba(0,0,0,0.06);}
.card-label{font-size:13px;color:#64748b;margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.card-value{font-size:30px;font-weight:800;}
.card.green .card-value{color:#10b981;}
.card.blue .card-value{color:#667eea;}
.card.orange .card-value{color:#f59e0b;}
.card.purple .card-value{color:#a855f7;}
.card-foot{margin-top:10px;font-size:12px;color:#94a3b8;}

.user-header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:16px;padding:26px 28px;color:#fff;box-shadow:0 8px 24px rgba(102,126,234,0.25);margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;}
.uh-left{display:flex;align-items:center;gap:16px;}
.avatar{width:62px;height:62px;border-radius:50%;background:rgba(255,255,255,0.25);display:flex;align-items:center;justify-content:center;font-size:30px;border:3px solid rgba(255,255,255,0.4);}
.uh-name{font-size:20px;font-weight:800;margin-bottom:3px;}
.uh-sub{opacity:0.92;font-size:13px;}
.uh-balance{text-align:right;}
.uh-balance-label{font-size:12px;opacity:0.9;margin-bottom:4px;}
.uh-balance-val{font-size:28px;font-weight:800;}

.tabs{display:flex;gap:6px;background:#fff;padding:6px;border-radius:12px;margin-bottom:18px;box-shadow:0 2px 8px rgba(0,0,0,0.05);flex-wrap:wrap;}
.tab{padding:11px 18px;border-radius:8px;font-weight:600;font-size:14px;color:#666;cursor:pointer;transition:all 0.2s;}
.tab:hover{background:#f1f5f9;}
.tab.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;box-shadow:0 4px 10px rgba(102,126,234,0.3);}

.panel{background:#fff;border-radius:14px;padding:24px 26px;box-shadow:0 2px 12px rgba(0,0,0,0.06);margin-bottom:18px;}
.panel-title{font-size:17px;font-weight:700;color:#1e293b;margin-bottom:16px;display:flex;align-items:center;gap:8px;}
table{width:100%;border-collapse:collapse;font-size:14px;}
thead th{background:#f8fafc;color:#475569;padding:12px 10px;text-align:center;border-bottom:2px solid #e2e8f0;font-weight:600;}
tbody td{padding:14px 10px;text-align:center;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle;}
tbody tr.highlight{background:#fffbe8;}
tbody tr:hover{background:#f8fafc;}
.tag{display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;}
.tag-green{background:#dcfce7;color:#166534;}
.tag-blue{background:#dbeafe;color:#1e40af;}
.tag-orange{background:#fed7aa;color:#9a3412;}
.tag-gray{background:#f1f5f9;color:#64748b;}
.tag-purple{background:#ede9fe;color:#6d28d9;}
.price{color:#ef4444;font-weight:700;}
.goods-link{color:#667eea;font-weight:600;text-decoration:none;}
.goods-link:hover{text-decoration:underline;}
.empty{text-align:center;padding:50px 20px;color:#94a3b8;}
.empty a{color:#667eea;font-weight:600;text-decoration:none;}
.recharge-box{background:linear-gradient(135deg,#fff7ed,#ecfccb);border-radius:12px;padding:20px 22px;margin-bottom:16px;border:1px dashed #f59e0b;}
.recharge-title{font-size:15px;font-weight:700;color:#92400e;margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.recharge-box p{font-size:13px;color:#78716c;line-height:1.8;}
.qr-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-top:14px;}
.qr-item{background:#fff;padding:14px;border-radius:10px;text-align:center;font-size:12px;color:#64748b;}
.qr-item b{display:block;color:#1e293b;margin:6px 0 2px;font-size:15px;}
.info-row{display:flex;padding:10px 0;border-bottom:1px solid #f1f5f9;font-size:14px;}
.info-row:last-child{border-bottom:none;}
.info-k{width:110px;color:#64748b;font-weight:500;flex-shrink:0;}
.info-v{color:#1e293b;font-weight:600;flex:1;}
.info-v input{padding:5px 9px;border:1px solid #e2e8f0;border-radius:6px;width:100%;max-width:280px;}
#recharge:target{animation:flash 1.2s ease;}
@keyframes flash{0%,100%{background:transparent}50%{background:#fef3c7;border-radius:12px;}}
.goods-mini{display:flex;align-items:center;gap:8px;justify-content:center;text-align:left;}
.goods-mini .emoji{width:36px;height:36px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.goods-mini .name{font-weight:600;color:#1e293b;font-size:13px;line-height:1.3;}
.goods-mini .qty{font-size:11px;color:#64748b;}
</style>
</head>
<body>
<nav class="nav">
    <div class="nav-inner">
        <a href="shop.php" class="logo">🛒 Zaslon 商城</a>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <a href="shop.php" class="btn btn-ghost">🏠 继续购物</a>
            <a href="logout.php" class="btn btn-ghost" style="color:#ef4444;">🚪 退出</a>
        </div>
    </div>
</nav>
<div class="wrap">
    <?php if($toast){?><div class="toast ok"><?php echo e($toast);?></div><?php }?>

    <div class="user-header">
        <div class="uh-left">
            <div class="avatar">👤</div>
            <div>
                <div class="uh-name">欢迎，<?php echo e($user['name']);?></div>
                <div class="uh-sub">📱 <?php echo e($user['phone'] ?? '未设置手机号');?> · UID: #<?php echo $uid;?></div>
            </div>
        </div>
        <div class="uh-balance">
            <div class="uh-balance-label">💳 账户余额</div>
            <div class="uh-balance-val">¥ <?php echo money($user['balance'] ?? 0);?></div>
        </div>
    </div>

    <div class="card-grid">
        <div class="card blue">
            <div class="card-label">🛍️ 历史订单数</div>
            <div class="card-value"><?php echo count($order_list);?></div>
            <div class="card-foot">最近 100 条</div>
        </div>
        <div class="card green">
            <div class="card-label">💸 累计消费</div>
            <div class="card-value">¥ <?php echo money($paid_sum);?></div>
            <div class="card-foot">实付金额合计</div>
        </div>
        <div class="card orange">
            <div class="card-label">💰 累计充值</div>
            <div class="card-value">¥ <?php echo money($recharge_sum);?></div>
            <div class="card-foot">到账充值合计</div>
        </div>
        <div class="card purple">
            <div class="card-label">🔋 充值笔数</div>
            <div class="card-value"><?php echo count(array_filter($recharge_list,fn($r)=>$r['status']==1));?></div>
            <div class="card-foot">成功到账笔数</div>
        </div>
    </div>

    <div class="tabs">
        <a class="tab <?php echo $tab==='orders'?'active':''?>" href="?tab=orders">📦 我的订单</a>
        <a class="tab <?php echo $tab==='recharge'?'active':''?>" href="?tab=recharge#recharge" id="recharge-link">💳 充值中心</a>
        <a class="tab <?php echo $tab==='rechargelog'?'active':''?>" href="?tab=rechargelog">📜 充值记录</a>
        <a class="tab <?php echo $tab==='profile'?'active':''?>" href="?tab=profile">⚙️ 账户信息</a>
    </div>

    <?php if($tab==='orders'){?>
    <div class="panel">
        <div class="panel-title">📦 我的订单（共 <?php echo count($order_list);?> 条）</div>
        <?php if(empty($order_list)){?>
            <div class="empty">😕 您还没有订单，<a href="shop.php">去商城逛逛吧</a></div>
        <?php } else { ?>
        <div style="overflow-x:auto;">
        <table>
            <thead><tr>
                <th>订单号</th>
                <th>商品信息</th>
                <th>数量</th>
                <th>下单时间</th>
                <th>支付状态</th>
                <th style="min-width:230px;">订单状态 + 物流信息</th>
                <th>实付金额</th>
                <th style="width:90px;">操作</th>
            </tr></thead>
            <tbody>
            <?php foreach($order_list as $o){
                $total = floatval($o['total_price'] ?? $o['price']);
                $qty = intval($o['qty'] ?? 1);
            ?>
            <tr class="<?php echo $o['id']==$highlight_order?'highlight':''?>">
                <td>#<?php echo $o['id'];?></td>
                <td>
                    <div class="goods-mini">
                        <div class="emoji"><?php echo !empty($o['goods_image'])?'<img src="'.e($o['goods_image']).'" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">':($o['goods_id']?'🎁':'📦');?></div>
                        <div>
                            <div class="name">
                                <?php if($o['goods_id']){?>
                                    <a class="goods-link" href="goods_detail.php?id=<?php echo $o['goods_id'];?>"><?php echo e($o['goods_name'] ?: ($o['order_title'] ?? '--'));?></a>
                                <?php } else { echo e($o['order_title'] ?? '--'); } ?>
                            </div>
                            <?php if($qty>1 || isset($o['unit_price'])){?><div class="qty">单价 <?php echo price($o['unit_price'] ?? $o['price']);?></div><?php }?>
                        </div>
                    </div>
                </td>
                <td><?php echo $qty;?></td>
                <td style="font-size:12.5px;color:#64748b;"><?php echo $o['create_time'];?></td>
                <td>
                    <?php if(intval($o['pay_status'])==1){?><span class="tag tag-green">✅ 已支付</span><?php } else {?><span class="tag tag-orange">⏳ 待支付</span><?php }?>
                </td>
                <td>
                    <?php
                        $st = intval($o['status'] ?? 1);
                        if($st==0) echo '<span class="tag tag-gray">待确认</span>';
                        elseif($st==1) echo '<span class="tag tag-blue">📦 已下单</span>';
                        elseif($st==2) echo '<span class="tag tag-purple">🚚 已发货</span>';
                        elseif($st==3) echo '<span class="tag tag-green">🎉 已完成</span>';
                        elseif($st==9) echo '<span class="tag tag-gray">❌ 已取消</span>';

                        $_ex_no = trim($o['express_no'] ?? '');
                        $_ex_com = trim($o['express_company'] ?? '');
                        $_ex_tm  = trim($o['express_time'] ?? '');
                        $_ex_nt  = trim($o['express_note'] ?? '');
                        $_sh_nm  = trim($o['ship_name'] ?? '');
                        $_sh_ph  = trim($o['ship_phone'] ?? '');
                        $_sh_ad  = trim($o['ship_address'] ?? '');
                        if($_ex_no!=='' || $_sh_nm!=='' || $_sh_ad!==''){
                            echo '<div style="margin-top:8px;background:linear-gradient(135deg,#eff6ff,#ede9fe);border-left:4px solid #667eea;padding:9px 12px;border-radius:9px;font-size:12px;line-height:1.8;color:#1e3a8a;">';
                            if($_ex_no!==''){
                                echo '🚚 <b style="color:#4f46e5;">'.e($_ex_com).'</b> ';
                                echo '<span style="display:inline-flex;align-items:center;gap:4px;background:#fff;padding:2px 8px;border-radius:6px;border:1px solid #c7d2fe;margin-left:4px;cursor:pointer;" onclick="navigator.clipboard.writeText(this.dataset.v).then(()=>{this.innerText=\'✅已复制\';setTimeout(()=>this.innerText=\'📋 \'+this.dataset.v,1200);})" data-v="'.e($_ex_no).'" title="点击复制运单号">📋 '.e($_ex_no).'</span>';
                                if($_ex_tm) echo ' · <span style="color:#64748b;">'.$_ex_tm.' 发出</span>';
                                echo '<br>';
                                if($_ex_nt) echo '💬 '.e($_ex_nt).'<br>';
                            }
                            if($_sh_nm)     echo '👤 <b>'.e($_sh_nm).'</b> '.e($_sh_ph).'<br>';
                            if($_sh_ad)     echo '📍 '.e($_sh_ad);
                            echo '</div>';
                        }
                        if($_ex_no!=='' && ($st==3 || $st==2)){
                            echo '<div style="margin-top:6px;"><a href="order_detail.php?id='.$o['id'].'" style="color:#667eea;font-weight:700;font-size:12px;text-decoration:underline;">🔍 查看完整物流详情 →</a></div>';
                        }
                    ?>
                </td>
                <td class="price"><?php echo price($total);?></td>
                <td style="width:80px;">
                    <a href="order_detail.php?id=<?php echo $o['id'];?>" style="display:inline-flex;align-items:center;gap:4px;padding:6px 12px;background:#f1f5f9;color:#475569;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;transition:all 0.2s;" onmouseover="this.style.background='#667eea';this.style.color='#fff';" onmouseout="this.style.background='#f1f5f9';this.style.color='#475569';">📄 详情</a>
                </td>
            </tr>
            <?php }?>
            </tbody>
        </table>
        </div>
        <?php }?>
    </div>
    <?php }?>

    <?php if($tab==='recharge'){?>
    <div class="panel" id="recharge">
        <div class="panel-title">💳 余额充值中心</div>
        <div class="recharge-box">
            <div class="recharge-title">💡 充值说明</div>
            <p>
                ① 请联系管理员（<b>后台管理员</b>）为您的账户充值，即时到账，安全便捷。<br>
                ② 充值到账后，余额可用于在 <b>Zaslon 商城</b> 直接一键购买任意商品。<br>
                ③ 充值完成后刷新此页面，下方「充值记录」与「账户余额」会自动更新。<br>
                ④ 管理员入口：<a href="admin/login.php" target="_blank" style="color:#667eea;font-weight:700;">后台管理系统登录 →</a>（登录后在「用户充值」中操作）
            </p>
        </div>
        <div class="card-grid" style="grid-template-columns:repeat(auto-fit,minmax(210px,1fr));">
            <?php foreach([10,50,100,200,500,1000] as $am){?>
            <div style="background:linear-gradient(135deg,#dbeafe,#f5f3ff);border-radius:14px;padding:22px;text-align:center;cursor:pointer;transition:all 0.2s;" onclick="copyAmt(<?php echo $am;?>)">
                <div style="font-size:26px;font-weight:800;color:#4f46e5;">¥<?php echo $am;?></div>
                <div style="margin-top:6px;font-size:12px;color:#6366f1;">快速充值面额</div>
            </div>
            <?php }?>
        </div>
        <div style="margin-top:18px;background:#f8fafc;border-radius:10px;padding:16px 18px;">
            <div style="font-weight:700;color:#475569;margin-bottom:8px;">📋 账户信息（给管理员看）</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;font-size:13px;">
                <div>👤 用户名：<b style="color:#4f46e5;"><?php echo e($user['name']);?></b></div>
                <div>🆔 用户ID：<b style="color:#4f46e5;"><?php echo $uid;?></b></div>
                <div>📱 手机号：<b style="color:#4f46e5;"><?php echo e($user['phone'] ?? '--');?></b></div>
                <div>💰 当前余额：<b style="color:#10b981;">¥<?php echo money($user['balance'] ?? 0);?></b></div>
            </div>
        </div>
    </div>
    <?php }?>

    <?php if($tab==='rechargelog'){?>
    <div class="panel">
        <div class="panel-title">📜 充值记录（共 <?php echo count($recharge_list);?> 条）</div>
        <?php if(empty($recharge_list)){?>
            <div class="empty">暂无充值记录，请到 <a href="?tab=recharge#recharge">充值中心</a> 了解如何充值</div>
        <?php } else { ?>
        <div style="overflow-x:auto;">
        <table>
            <thead><tr>
                <th>充值单号</th>
                <th>充值金额</th>
                <th>到账前余额</th>
                <th>到账后余额</th>
                <th>操作管理员</th>
                <th>状态</th>
                <th>备注</th>
                <th>充值时间</th>
            </tr></thead>
            <tbody>
            <?php foreach($recharge_list as $r){?>
            <tr>
                <td>#<?php echo $r['id'];?></td>
                <td style="color:#10b981;font-weight:700;">+¥<?php echo money($r['amount']);?></td>
                <td>¥<?php echo money($r['balance_before']);?></td>
                <td>¥<?php echo money($r['balance_after']);?></td>
                <td><?php echo e($r['operator'] ?: '--');?></td>
                <td>
                    <?php if(intval($r['status'])==1){?><span class="tag tag-green">✅ 到账</span><?php } else {?><span class="tag tag-gray">处理中</span><?php }?>
                </td>
                <td style="font-size:12.5px;color:#64748b;max-width:180px;"><?php echo e($r['remark'] ?: '--');?></td>
                <td style="font-size:12.5px;color:#64748b;"><?php echo $r['create_time'];?></td>
            </tr>
            <?php }?>
            </tbody>
        </table>
        </div>
        <?php }?>
    </div>
    <?php }?>

    <?php if($tab==='profile'){?>
    <div class="panel">
        <div class="panel-title">⚙️ 账户基本信息</div>
        <div style="max-width:560px;">
            <div class="info-row"><div class="info-k">用户 ID</div><div class="info-v">#<?php echo $uid;?></div></div>
            <div class="info-row"><div class="info-k">姓名</div><div class="info-v"><?php echo e($user['name']);?></div></div>
            <div class="info-row"><div class="info-k">手机号</div><div class="info-v"><?php echo e($user['phone'] ?? '未设置');?></div></div>
            <div class="info-row"><div class="info-k">收货地址</div><div class="info-v"><?php echo e($user['address'] ?? '未设置');?></div></div>
            <div class="info-row"><div class="info-k">账户余额</div><div class="info-v" style="color:#10b981;font-size:18px;">¥ <?php echo money($user['balance'] ?? 0);?></div></div>
            <div class="info-row"><div class="info-k">注册时间</div><div class="info-v"><?php echo $user['create_time'] ?? '--';?></div></div>
            <div class="info-row"><div class="info-k">登录密码</div><div class="info-v">
                <a href="login.php" style="color:#667eea;">前往登录页可找回 / 联系管理员重置</a>
            </div></div>
        </div>
    </div>
    <?php }?>
</div>
<script>
function copyAmt(n){
    prompt('请将以下「充值金额」+「UID」发送给管理员：','UID=<?php echo $uid;?>，充值金额：¥'+n);
}
</script>
</body>
</html>
