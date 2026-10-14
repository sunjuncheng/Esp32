<?php
include 'config.php';
include 'common.php';
need_login('login.php?redirect=order_detail.php'.(isset($_GET['id'])?('?id='.intval($_GET['id'])):''));
$toast = toast_pop();
$user = current_user();
if(!$user){ redirect('login.php','请先登录'); }

$id = intval($_GET['id'] ?? 0);
if($id<=0){ redirect('user_center.php?tab=orders','订单不存在'); }

$o = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT o.*,u.name as user_name,u.phone as user_phone,u.address as user_addr
     FROM order_list o LEFT JOIN user_list u ON o.user_id=u.id
     WHERE o.id=$id LIMIT 1"));
if(!$o){ redirect('user_center.php?tab=orders','订单不存在'); }
if(intval($o['user_id']) !== intval($user['id'])){
    redirect('user_center.php?tab=orders','这不是您的订单');
}

$st = intval($o['status'] ?? 1);
$is_paid = intval($o['pay_status'])==1;
$total = floatval($o['total_price'] ?? $o['price']);
$qty   = intval($o['qty'] ?? 1);
$unit_price = floatval($o['unit_price'] ?? ($qty>0?($total/$qty):$total));

$_ex_com = trim($o['express_company'] ?? '');
$_ex_no  = trim($o['express_no'] ?? '');
$_ex_tm  = trim($o['express_time'] ?? '');
$_ex_nt  = trim($o['express_note'] ?? '');
$_sh_nm  = trim($o['ship_name'] ?? '');
$_sh_ph  = trim($o['ship_phone'] ?? '');
$_sh_ad  = trim($o['ship_address'] ?? '');

// 模拟物流时间线（如果有运单号就根据发货时间生成 4-5 条节点）
$timeline = [];
if($st>=1){
    $timeline[] = [
        'icon'=>'✅','title'=>'订单已提交','time'=>$o['create_time'] ?? date('Y-m-d H:i:s'),
        'desc'=>'您的订单已成功提交，等待发货',
        'done'=>true,'color'=>'green'
    ];
}
if($is_paid){
    $timeline[] = [
        'icon'=>'💳','title'=>'支付成功','time'=>$o['pay_time'] ?? ($o['create_time']),
        'desc'=>'已通过账户余额支付 '.price($total),
        'done'=>true,'color'=>'blue'
    ];
}
if($_ex_no!==''){
    $ship_t = $_ex_tm ?: (date('Y-m-d H:i:s', strtotime(($o['pay_time'] ?? $o['create_time']).' +12 hours')));
    $timeline[] = [
        'icon'=>'🚚','title'=>($_ex_com?:'快递').' 已揽收','time'=>$ship_t,
        'desc'=>'【'.$_ex_com.'】单号：<b class="copy-td" data-v="'.e($_ex_no).'">📋 '.e($_ex_no).' (点击复制)</b>'.($_ex_nt?'<br><span style="color:#64748b;">备注：'.e($_ex_nt).'</span>':''),
        'done'=>true,'color'=>'purple'
    ];
    if($st>=3 || $st==2){
        $t1 = date('Y-m-d H:i:s', strtotime($ship_t.' +8 hours'));
        $timeline[] = [
            'icon'=>'📦','title'=>'运输中：已到达【本地转运中心】',
            'time'=>$t1,'desc'=>'包裹正发往您所在城市，预计 1-2 天送达',
            'done'=>$st>=3,'color'=>'purple','last'=>false
        ];
        $t2 = date('Y-m-d H:i:s', strtotime($ship_t.' +26 hours'));
        $timeline[] = [
            'icon'=>'🏙️','title'=>($st>=3?'✅ ':'⏳ ').'派送中：'.($st>=3?'快递员已送达签收':'快递员正在派件，请保持手机畅通'),
            'time'=>$t2,'desc'=>($_sh_nm?('收件人：'.e($_sh_nm).' '.e($_sh_ph).' → '.e($_sh_ad)):''),
            'done'=>$st>=3,'color'=>'green','last'=>true
        ];
    }
}

// 状态图映射
$status_icon = ['','📦','🚚','🎉','❌'];
$status_icon = $status_icon[$st] ?? '📦';
$status_text = [0=>'待确认',1=>'已下单',2=>'已发货',3=>'已完成',9=>'已取消'];
$status_text = $status_text[$st] ?? '未知';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>订单详情 - #<?php echo $id;?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f5f7fa;color:#334155;}
.nav{background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.06);position:sticky;top:0;z-index:50;}
.nav-inner{max-width:960px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;}
.logo{font-size:18px;font-weight:800;background:linear-gradient(135deg,#667eea,#764ba2);-webkit-background-clip:text;color:transparent;text-decoration:none;display:flex;align-items:center;gap:8px;}
.nav-right{display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
.btn{padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;border:none;cursor:pointer;transition:all 0.2s;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.btn:hover{transform:translateY(-1px);}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;}
.btn-outline{background:#fff;border:1px solid #e2e8f0;color:#475569;}
.btn-green{background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;}

.wrap{max-width:960px;margin:24px auto;padding:0 20px;padding-bottom:60px;}

.toast{max-width:960px;margin:16px auto 0;padding:0 20px;}
.toast-box{padding:12px 16px;background:#dcfce7;color:#166534;border-left:4px solid #10b981;border-radius:10px;font-weight:500;font-size:13.5px;}

.status-hero{background:linear-gradient(135deg,#667eea 0%,#764ba2 50%,#8b5cf6 100%);border-radius:18px;padding:28px 30px;color:#fff;margin-bottom:22px;box-shadow:0 10px 30px rgba(102,126,234,0.28);}
.status-row{display:flex;align-items:center;gap:20px;justify-content:space-between;flex-wrap:wrap;}
.status-left{display:flex;align-items:center;gap:18px;}
.status-emoji{width:70px;height:70px;border-radius:20px;background:rgba(255,255,255,0.2);backdrop-filter:blur(10px);display:flex;align-items:center;justify-content:center;font-size:36px;}
.status-txt h1{font-size:24px;margin-bottom:4px;}
.status-txt p{opacity:0.9;font-size:13px;line-height:1.7;}
.status-right{text-align:right;}
.order-id{font-size:13px;opacity:0.9;margin-bottom:6px;}
.price-big{font-size:32px;font-weight:800;letter-spacing:-0.5px;}

.panel{background:#fff;border-radius:14px;padding:20px 24px;margin-bottom:18px;box-shadow:0 2px 10px rgba(0,0,0,0.04);}
.panel-title{font-size:16px;font-weight:700;color:#0f172a;margin-bottom:16px;display:flex;align-items:center;gap:8px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;}

.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;}
.info-item{padding:10px 0;border-bottom:1px dashed #f1f5f9;}
.info-item label{display:block;font-size:11.5px;color:#94a3b8;margin-bottom:4px;font-weight:600;letter-spacing:0.3px;}
.info-item span{color:#1e293b;font-weight:600;font-size:13.5px;}
.copy-td{cursor:pointer;color:#667eea;display:inline-block;padding:1px 6px;border-radius:5px;background:#eff6ff;transition:all 0.15s;}
.copy-td:hover{background:#667eea;color:#fff;}

/* 商品行 */
.goods-row{display:flex;gap:16px;padding:14px;background:#f8fafc;border-radius:12px;margin-bottom:10px;}
.goods-img{width:74px;height:74px;border-radius:10px;background:linear-gradient(135deg,#dbeafe,#ede9fe);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:34px;overflow:hidden;}
.goods-img img{width:100%;height:100%;object-fit:cover;}
.goods-info{flex:1;min-width:0;display:flex;flex-direction:column;justify-content:center;gap:5px;}
.goods-name{font-size:15px;font-weight:700;color:#0f172a;}
.goods-meta{color:#64748b;font-size:12.5px;}
.goods-right{text-align:right;display:flex;flex-direction:column;justify-content:center;gap:5px;}
.goods-price{color:#ef4444;font-size:15px;font-weight:700;}

/* 物流时间线 */
.timeline{padding:4px 0;}
.t-item{position:relative;padding-left:54px;padding-bottom:28px;}
.t-item:last-child{padding-bottom:8px;}
.t-icon{position:absolute;left:0;top:0;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,0.12);}
.t-icon.green{background:linear-gradient(135deg,#10b981,#059669);}
.t-icon.blue{background:linear-gradient(135deg,#3b82f6,#6366f1);}
.t-icon.purple{background:linear-gradient(135deg,#8b5cf6,#7c3aed);}
.t-icon.gray{background:#cbd5e1;color:#fff;}
.t-item::before{content:"";position:absolute;left:19.5px;top:40px;bottom:-2px;width:2px;background:#e2e8f0;}
.t-item:last-child::before{display:none;}
.t-item.todo .t-icon{opacity:0.5;}
.t-title{font-size:14.5px;font-weight:700;color:#0f172a;margin-bottom:3px;}
.t-time{font-size:12px;color:#94a3b8;margin-bottom:6px;display:inline-block;}
.t-desc{font-size:13px;color:#475569;line-height:1.65;}
.t-item.done .t-title{color:#0f172a;}
.t-item.todo .t-title{color:#94a3b8;}

.ship-card{background:linear-gradient(135deg,#eff6ff,#ede9fe);border:1px solid #c7d2fe;border-radius:12px;padding:16px 18px;margin-top:6px;}
.ship-row{display:flex;gap:10px;margin-bottom:8px;align-items:flex-start;}
.ship-row:last-child{margin-bottom:0;}
.ship-label{font-size:12px;color:#4f46e5;font-weight:700;flex-shrink:0;width:68px;}
.ship-value{font-size:13.5px;color:#1e1b4b;line-height:1.6;}

.price-table{width:100%;font-size:13.5px;}
.price-table td{padding:7px 0;border-bottom:1px dashed #f1f5f9;}
.price-table td:first-child{color:#64748b;}
.price-table td:last-child{text-align:right;color:#0f172a;font-weight:600;}
.price-table tr.total td{border-bottom:none;font-size:15px;padding-top:14px;}
.price-table tr.total td:last-child{color:#ef4444;font-size:20px;font-weight:800;}

.tabs-sm{display:inline-flex;gap:4px;background:#f1f5f9;padding:4px;border-radius:10px;margin-left:10px;}
.tabs-sm a{padding:5px 12px;border-radius:7px;font-size:12px;text-decoration:none;color:#64748b;font-weight:600;}
.tabs-sm a:hover{background:#fff;color:#475569;}
.tabs-sm a.active{background:#fff;color:#667eea;box-shadow:0 1px 3px rgba(0,0,0,0.06);}
</style>
</head>
<body>
<div class="nav">
    <div class="nav-inner">
        <a href="shop.php" class="logo">🛍️ Zaslon 商城</a>
        <div class="nav-right">
            <a href="shop.php" class="btn btn-outline">🛒 继续购物</a>
            <a href="user_center.php?tab=orders" class="btn btn-primary">📦 我的订单</a>
        </div>
    </div>
</div>

<?php if($toast){?><div class="toast"><div class="toast-box"><?php echo e($toast);?></div></div><?php }?>

<div class="wrap">

    <!-- 状态大卡 -->
    <div class="status-hero">
        <div class="status-row">
            <div class="status-left">
                <div class="status-emoji"><?php echo $status_icon;?></div>
                <div class="status-txt">
                    <h1><?php echo $status_text;?>
                        <?php if($_ex_no!==''){ echo '<span class="tabs-sm"><a class="active">🚚 物流运输中</a></span>';}?>
                    </h1>
                    <p>
                        下单时间：<?php echo e($o['create_time']);?><br>
                        <?php if($_ex_no!==''){ echo '发货时间：<b>'.e($_ex_tm).'</b> · 快递：<b>'.e($_ex_com).'</b>'; }?>
                    </p>
                </div>
            </div>
            <div class="status-right">
                <div class="order-id">订单号 <b style="font-size:16px;">#<?php echo $id;?></b></div>
                <div class="price-big"><?php echo price($total);?></div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-title">🛍️ 商品信息</div>
        <div class="goods-row">
            <div class="goods-img">
                <?php if(!empty($o['goods_image'])){?><img src="<?php echo e($o['goods_image']);?>" alt=""><?php } else { echo e($o['goods_name']?'📦':'📦'); } ?>
            </div>
            <div class="goods-info">
                <div class="goods-name">
                    <?php if(!empty($o['goods_id'])){?><a href="goods_detail.php?id=<?php echo $o['goods_id'];?>" style="color:inherit;text-decoration:none;"><?php }?>
                    <?php echo e($o['goods_name'] ?: ($o['order_title'] ?? '商品购买'));?>
                    <?php if(!empty($o['goods_id'])){?></a><?php }?>
                </div>
                <div class="goods-meta">单价：<?php echo price($unit_price);?> × 数量：<?php echo $qty;?></div>
            </div>
            <div class="goods-right">
                <div class="goods-price"><?php echo price($total);?></div>
            </div>
        </div>
    </div>

    <!-- 收货地址 -->
    <?php if($_sh_nm!=='' || $_sh_ad!==''){?>
    <div class="panel">
        <div class="panel-title">📍 收货信息</div>
        <div class="ship-card">
            <div class="ship-row"><div class="ship-label">👤 收件人</div><div class="ship-value"><b><?php echo e($_sh_nm);?></b> <?php echo e($_sh_ph);?></div></div>
            <div class="ship-row"><div class="ship-label">📍 收货地址</div><div class="ship-value"><?php echo e($_sh_ad);?></div></div>
        </div>
    </div>
    <?php }?>

    <!-- 物流时间线 -->
    <div class="panel">
        <div class="panel-title">
            <?php if($_ex_no!==''){?>
            🚚 物流跟踪
            <span style="margin-left:auto;font-size:12px;color:#64748b;font-weight:500;">
                <?php echo e($_ex_com);?> 运单号：
                <span class="copy-td" onclick="copyEl(this)" data-v="<?php echo e($_ex_no);?>">📋 <?php echo e($_ex_no);?></span>
            </span>
            <?php } else {?>
            ⏳ 物流状态（商家暂未发货）
            <?php }?>
        </div>
        <?php if(empty($timeline)){?>
            <div style="text-align:center;padding:30px;color:#94a3b8;">暂无物流信息，请耐心等待商家发货～</div>
        <?php } else {?>
        <div class="timeline">
            <?php foreach($timeline as $i=>$t){
                $cls = !empty($t['done']) ? 'done' : 'todo';
                $color = $t['color'] ?? 'gray';
                $ic_class = !empty($t['done']) ? $color : 'gray';
                ?>
            <div class="t-item <?php echo $cls;?>">
                <div class="t-icon <?php echo $ic_class;?>"><?php echo $t['icon'];?></div>
                <div class="t-title"><?php echo $t['title'];?></div>
                <div class="t-time">🕓 <?php echo e($t['time']);?></div>
                <div class="t-desc"><?php echo $t['desc'];?></div>
            </div>
            <?php }?>
        </div>
        <?php }?>
    </div>

    <!-- 订单基础信息 -->
    <div class="panel">
        <div class="panel-title">📋 订单详细信息</div>
        <div class="info-grid">
            <div class="info-item"><label>订单号</label><span>#<?php echo $o['id'];?></span></div>
            <div class="info-item"><label>下单人</label><span><?php echo e($o['user_name'] ?? '');?> (UID <?php echo $o['user_id'];?>)</span></div>
            <div class="info-item"><label>下单时间</label><span><?php echo e($o['create_time']);?></span></div>
            <div class="info-item"><label>支付状态</label><span><?php echo $is_paid?'<span style="color:#10b981;">✅ 已支付</span>':'<span style="color:#f59e0b;">⏳ 待支付</span>';?></span></div>
            <?php if($is_paid){?>
            <div class="info-item"><label>支付时间</label><span><?php echo e($o['pay_time'] ?? '--');?></span></div>
            <?php }?>
            <div class="info-item"><label>订单状态</label><span><?php echo e($status_text);?></span></div>
            <?php if($_ex_no!==''){?>
            <div class="info-item"><label>发货时间</label><span><?php echo e($_ex_tm);?></span></div>
            <?php }?>
            <div class="info-item"><label>下单账户</label><span><?php echo e($o['user_phone'] ?? $o['user_addr'] ?? '--');?></span></div>
        </div>
    </div>

    <!-- 金额明细 -->
    <div class="panel">
        <div class="panel-title">💰 金额明细</div>
        <table class="price-table">
            <tr><td>商品单价</td><td><?php echo price($unit_price);?></td></tr>
            <tr><td>购买数量</td><td>× <?php echo $qty;?></td></tr>
            <tr><td>商品小计</td><td><?php echo price($unit_price * $qty);?></td></tr>
            <tr><td>运费</td><td>¥ 0.00 （包邮）</td></tr>
            <tr><td>优惠/折扣</td><td>- ¥ 0.00</td></tr>
            <tr class="total"><td>应付总金额（实付）</td><td><?php echo price($total);?></td></tr>
        </table>
    </div>

    <!-- 操作按钮 -->
    <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:flex-end;">
        <a href="user_center.php?tab=orders" class="btn btn-outline">← 返回订单列表</a>
        <?php if(!$_ex_no){?><a href="shop.php" class="btn btn-green">🛒 去商城逛逛</a><?php }?>
        <?php if($st==2 && $_ex_no){?>
        <button class="btn btn-primary" onclick="toastOk()">✅ 确认收货</button>
        <script>function toastOk(){ if(confirm('确认已收到货？')) location.href='user_center.php?tab=orders&_done=1'; }</script>
        <?php }?>
    </div>

</div>
<script>
function copyEl(el){
    const v = el.dataset.v;
    navigator.clipboard.writeText(v).then(()=>{
        const orig = el.innerText;
        el.innerText='✅ 已复制'; el.style.background='#10b981'; el.style.color='#fff';
        setTimeout(()=>{ el.innerText=orig; el.style.background=''; el.style.color=''; },1200);
    });
}
</script>
</body>
</html>
