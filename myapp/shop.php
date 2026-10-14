<?php
include 'config.php';
include 'common.php';
$toast = toast_pop();
$user = current_user();
$kw = trim($_GET['kw'] ?? '');
$category = trim($_GET['c'] ?? '');

$where = 'WHERE status=1';
if($kw!==''){
    $kw_s = mysqli_real_escape_string($conn,$kw);
    $where .= " AND (goods_name LIKE '%$kw_s%' OR description LIKE '%$kw_s%' OR category LIKE '%$kw_s%')";
}
if($category!==''){
    $c_s = mysqli_real_escape_string($conn,$category);
    $where .= " AND category='$c_s'";
}

$cats_res = mysqli_query($conn, "SELECT DISTINCT category FROM goods_list WHERE status=1 AND category!='' ORDER BY category");
$categories = [];
while($r = mysqli_fetch_row($cats_res)) $categories[] = $r[0];

$res = mysqli_query($conn, "SELECT * FROM goods_list $where ORDER BY sort ASC, id DESC");
$goods = [];
while($row = mysqli_fetch_assoc($res)) $goods[] = $row;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>商城首页 - Zaslon 商城</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f5f7fa;color:#333;}
.nav{background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.06);position:sticky;top:0;z-index:50;}
.nav-inner{max-width:1200px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;}
.logo{font-size:20px;font-weight:800;background:linear-gradient(135deg,#667eea,#764ba2);-webkit-background-clip:text;color:transparent;display:flex;align-items:center;gap:8px;}
.nav-links{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.nav a{text-decoration:none;font-weight:500;}
.btn{padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;border:none;cursor:pointer;transition:all 0.2s;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.btn:hover{transform:translateY(-1px);}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;}
.btn-success{background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;}
.btn-ghost{background:#f1f3f5;color:#666;}
.btn-outline{background:#fff;color:#667eea;border:1px solid #667eea;}
.user-chip{background:#f1f3f5;padding:6px 12px;border-radius:20px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;}
.balance{color:#11998e;}
.wrap{max-width:1200px;margin:0 auto;padding:24px 20px 60px;}
.banner{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:16px;padding:34px 36px;color:#fff;margin-bottom:24px;box-shadow:0 8px 24px rgba(102,126,234,0.25);}
.banner h2{font-size:26px;margin-bottom:6px;}
.banner p{opacity:0.92;font-size:14px;}
.toast{background:#fff;border-radius:10px;padding:12px 16px;margin-bottom:18px;border-left:4px solid #10b981;font-size:14px;font-weight:500;box-shadow:0 2px 8px rgba(0,0,0,0.06);}
.tools{background:#fff;border-radius:12px;padding:16px 18px;margin-bottom:18px;box-shadow:0 2px 8px rgba(0,0,0,0.05);display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.tools input{padding:9px 13px;border:1px solid #dee2e6;border-radius:8px;outline:none;flex:1;min-width:200px;transition:all 0.2s;}
.tools input:focus{border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,0.12);}
.cat-bar{display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap;}
.cat{padding:6px 14px;border-radius:20px;background:#fff;font-size:13px;font-weight:600;cursor:pointer;color:#666;transition:all 0.2s;box-shadow:0 1px 4px rgba(0,0,0,0.05);}
.cat.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;}
.cat:hover{transform:translateY(-1px);}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:16px;}
.card{background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.06);transition:all 0.25s;display:flex;flex-direction:column;}
.card:hover{transform:translateY(-4px);box-shadow:0 12px 24px rgba(0,0,0,0.1);}
.card-img{height:170px;background:linear-gradient(135deg,#f5f7fa,#e0e7ff);display:flex;align-items:center;justify-content:center;font-size:64px;position:relative;}
.card-badge{position:absolute;top:10px;left:10px;background:#f59e0b;color:#fff;padding:3px 9px;border-radius:12px;font-size:11px;font-weight:700;}
.card-badge.hot{background:#ef4444;}
.card-body{padding:14px 16px 16px;flex:1;display:flex;flex-direction:column;gap:8px;}
.card-cat{font-size:12px;color:#667eea;font-weight:600;}
.card-name{font-size:15px;font-weight:700;color:#2c3e50;line-height:1.4;flex:1;}
.card-desc{font-size:12.5px;color:#888;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.card-bottom{display:flex;align-items:center;justify-content:space-between;margin-top:4px;}
.card-price{font-size:21px;font-weight:800;color:#ef4444;}
.card-price small{font-size:13px;font-weight:600;color:#94a3b8;margin-left:4px;}
.empty{text-align:center;padding:80px 20px;color:#aaa;background:#fff;border-radius:14px;}
.empty a{color:#667eea;font-weight:600;}
.soldout{filter:grayscale(0.7);opacity:0.7;}
.stock{font-size:12px;color:#64748b;margin-top:4px;}
.stock.low{color:#f59e0b;font-weight:600;}
.stock.out{color:#ef4444;font-weight:700;}
</style>
</head>
<body>
<nav class="nav">
    <div class="nav-inner">
        <a href="shop.php" class="logo">🛒 Zaslon 商城</a>
        <div class="nav-links">
            <a href="shop.php" class="btn btn-ghost">🏠 商城</a>
            <?php if($user){ ?>
                <a href="user_center.php" class="btn btn-outline">👤 个人中心</a>
                <div class="user-chip">
                    <span>💰 余额：<span class="balance"><?php echo price($user['balance'] ?? 0);?></span></span>
                    <span>· 欢迎，<?php echo e($user['name']);?></span>
                    <a href="logout.php" style="color:#ef4444;font-size:12px;text-decoration:none;">退出</a>
                </div>
            <?php } else { ?>
                <a href="login.php" class="btn btn-primary">🔐 登录/注册</a>
            <?php } ?>
        </div>
    </div>
</nav>
<div class="wrap">
    <div class="banner">
        <h2>🎉 新人下单送好礼</h2>
        <p>余额支付一键购买，后台充值即时到账 · 更多好物尽在 Zaslon 商城</p>
    </div>
    <?php if($toast){?><div class="toast"><?php echo e($toast);?></div><?php }?>

    <div class="tools">
        <form method="get" style="display:flex;gap:10px;flex:1;flex-wrap:wrap;">
            <input type="text" name="kw" placeholder="🔎 搜索商品名称 / 描述 / 分类" value="<?php echo e($kw);?>">
            <button class="btn btn-primary" type="submit">搜索</button>
            <?php if($kw||$category){?><a class="btn btn-ghost" href="shop.php">↺ 重置</a><?php } ?>
        </form>
    </div>

    <?php if($categories){?>
    <div class="cat-bar">
        <a class="cat <?php echo $category===''?'active':''?>" href="shop.php?kw=<?php echo urlencode($kw);?>">全部</a>
        <?php foreach($categories as $c){?>
        <a class="cat <?php echo $category===$c?'active':''?>" href="shop.php?c=<?php echo urlencode($c);?>&kw=<?php echo urlencode($kw);?>"><?php echo e($c);?></a>
        <?php }?>
    </div>
    <?php }?>

    <?php if(empty($goods)){?>
        <div class="empty">😕 暂无商品<?php echo $kw||$category?'，<a href="shop.php">清除筛选看看</a>':'，<a href="admin/goods_manage.php">去后台添加商品</a>'?></div>
    <?php } else { ?>
    <div class="grid">
        <?php foreach($goods as $g){
            $is_out = intval($g['stock'])<=0;
            $is_low = intval($g['stock'])>0 && intval($g['stock'])<=5;
        ?>
        <div class="card <?php echo $is_out?'soldout':''?>">
            <a href="goods_detail.php?id=<?php echo $g['id']?>" style="text-decoration:none;color:inherit;">
                <div class="card-img">
                    <?php if(!empty($g['image'])){?><img src="<?php echo e($g['image']);?>" style="max-height:100%;max-width:100%;object-fit:cover;height:100%;width:100%;"><?php } else {?>
                    <span><?php echo !empty($g['emoji'])?$g['emoji']:'📦';?></span>
                    <?php } ?>
                    <?php if($g['is_hot']){?><span class="card-badge hot">🔥HOT</span><?php }?>
                    <?php if(floatval($g['origin_price'])>floatval($g['price']) && floatval($g['origin_price'])>0){?><span class="card-badge">促销</span><?php }?>
                </div>
            </a>
            <div class="card-body">
                <div class="card-cat"><?php echo e($g['category'] ?: '通用');?></div>
                <a href="goods_detail.php?id=<?php echo $g['id']?>" style="text-decoration:none;color:inherit;"><div class="card-name"><?php echo e($g['goods_name']);?></div></a>
                <div class="card-desc"><?php echo e($g['description'] ?: '暂无描述');?></div>
                <div class="stock <?php echo $is_out?'out':($is_low?'low':'')?>">
                    <?php if($is_out){?>⚠️ 已售罄<?php } elseif($is_low){?>🔥 仅剩 <?php echo intval($g['stock']);?> 件<?php } else {?>库存：<?php echo intval($g['stock']);?> 件<?php }?>
                </div>
                <div class="card-bottom">
                    <div class="card-price"><?php echo price($g['price']);?><?php if(floatval($g['origin_price'])>floatval($g['price']) && floatval($g['origin_price'])>0){?><small style="text-decoration:line-through;"><?php echo price($g['origin_price']);?></small><?php }?></div>
                    <a href="goods_detail.php?id=<?php echo $g['id']?>" class="btn btn-success">查看 ›</a>
                </div>
            </div>
        </div>
        <?php }?>
    </div>
    <?php }?>
</div>
</body>
</html>
