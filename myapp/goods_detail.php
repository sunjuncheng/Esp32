<?php
include 'config.php';
include 'common.php';
$toast = toast_pop();
$user = current_user();
$id = intval($_GET['id'] ?? 0);
if($id<=0){ redirect('shop.php','商品不存在'); }
$g = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM goods_list WHERE id=$id LIMIT 1"));
if(!$g || intval($g['status'])!=1){ redirect('shop.php','商品已下架或不存在'); }
$is_out = intval($g['stock'])<=0;
$related = [];
if($g['category']){
    $c_s = mysqli_real_escape_string($conn,$g['category']);
    $rr = mysqli_query($conn, "SELECT * FROM goods_list WHERE status=1 AND category='$c_s' AND id<>$id ORDER BY sort ASC, id DESC LIMIT 4");
    while($r=mysqli_fetch_assoc($rr)) $related[] = $r;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($g['goods_name']);?> - Zaslon 商城</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f5f7fa;color:#333;}
.nav{background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.06);position:sticky;top:0;z-index:50;}
.nav-inner{max-width:1200px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;}
.logo{font-size:20px;font-weight:800;background:linear-gradient(135deg,#667eea,#764ba2);-webkit-background-clip:text;color:transparent;text-decoration:none;display:flex;align-items:center;gap:8px;}
.nav a{text-decoration:none;font-weight:500;}
.btn{padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;border:none;cursor:pointer;transition:all 0.2s;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.btn:hover{transform:translateY(-1px);}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;}
.btn-success{background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;}
.btn-danger{background:linear-gradient(135deg,#f5576c,#f093fb);color:#fff;}
.btn-ghost{background:#f1f3f5;color:#666;}
.btn-lg{padding:14px 26px;font-size:15px;}
.btn.disabled,.btn:disabled{background:#cbd5e1;color:#94a3b8;cursor:not-allowed;transform:none;box-shadow:none;}
.user-chip{background:#f1f3f5;padding:6px 12px;border-radius:20px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;}
.balance{color:#11998e;}
.wrap{max-width:1200px;margin:0 auto;padding:24px 20px 60px;}
.toast{background:#fff;border-radius:10px;padding:12px 16px;margin-bottom:18px;border-left:4px solid #10b981;font-size:14px;font-weight:500;box-shadow:0 2px 8px rgba(0,0,0,0.06);}
.toast.err{border-left-color:#ef4444;color:#b91c1c;}
.toast.warn{border-left-color:#f59e0b;color:#92400e;}
.breadcrumb{margin-bottom:16px;font-size:13px;color:#64748b;}
.breadcrumb a{color:#667eea;text-decoration:none;}

.product{background:#fff;border-radius:16px;padding:30px;box-shadow:0 2px 14px rgba(0,0,0,0.06);margin-bottom:24px;}
.p-row{display:grid;grid-template-columns:1fr 1fr;gap:36px;}
@media(max-width:780px){.p-row{grid-template-columns:1fr;}}
.p-img{height:360px;border-radius:14px;background:linear-gradient(135deg,#f5f7fa,#e0e7ff);display:flex;align-items:center;justify-content:center;font-size:160px;overflow:hidden;}
.p-img img{max-height:100%;max-width:100%;object-fit:cover;height:100%;width:100%;}
.p-badges{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;}
.badge{padding:4px 10px;border-radius:12px;font-size:12px;font-weight:700;}
.badge.cat{background:#eef2ff;color:#4f46e5;}
.badge.hot{background:#fee2e2;color:#dc2626;}
.badge.promo{background:#fef3c7;color:#b45309;}
.p-name{font-size:26px;font-weight:800;color:#1e293b;margin-bottom:10px;line-height:1.3;}
.p-desc{color:#64748b;line-height:1.7;margin-bottom:20px;}
.p-price-row{background:linear-gradient(135deg,#fff1f2,#fef3c7);border-radius:12px;padding:20px 22px;margin-bottom:20px;}
.p-price{font-size:36px;font-weight:800;color:#ef4444;}
.p-origin{color:#94a3b8;text-decoration:line-through;margin-left:10px;font-size:14px;}
.p-price-label{font-size:12px;color:#b91c1c;margin-bottom:4px;font-weight:600;}
.info-list{background:#f8fafc;border-radius:10px;padding:16px 18px;margin-bottom:22px;font-size:14px;color:#475569;line-height:2.1;}
.info-list b{color:#1e293b;margin-right:4px;}
.stock.ok{color:#10b981;}
.stock.low{color:#f59e0b;font-weight:700;}
.stock.out{color:#ef4444;font-weight:700;}

.qty-row{display:flex;align-items:center;gap:16px;margin-bottom:24px;}
.qty-box{display:inline-flex;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;}
.qty-box button{width:42px;height:42px;background:#f8fafc;border:none;font-size:20px;cursor:pointer;color:#475569;transition:all 0.1s;}
.qty-box button:hover{background:#eef2ff;color:#667eea;}
.qty-box input{width:60px;height:42px;border:none;text-align:center;font-size:16px;font-weight:700;color:#1e293b;outline:none;}
.qty-label{font-size:14px;color:#475569;font-weight:600;}
.buy-row{display:flex;gap:12px;flex-wrap:wrap;}
.total-box{margin-left:auto;font-size:15px;color:#64748b;display:flex;align-items:center;gap:10px;}
.total-box b{font-size:22px;color:#ef4444;font-weight:800;}
.detail-tabs{background:#fff;border-radius:16px;padding:28px;box-shadow:0 2px 14px rgba(0,0,0,0.06);margin-bottom:24px;}
.detail-tabs h3{font-size:18px;margin-bottom:14px;color:#1e293b;display:flex;align-items:center;gap:8px;}
.detail-tabs p{color:#475569;line-height:1.8;white-space:pre-wrap;}
.related{background:#fff;border-radius:16px;padding:26px;box-shadow:0 2px 14px rgba(0,0,0,0.06);}
.related h3{font-size:18px;margin-bottom:18px;color:#1e293b;}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;}
.card{border:1px solid #f1f5f9;border-radius:12px;overflow:hidden;transition:all 0.2s;}
.card:hover{transform:translateY(-2px);box-shadow:0 8px 16px rgba(0,0,0,0.08);border-color:#e0e7ff;}
.card a{text-decoration:none;color:inherit;}
.ci{height:120px;background:linear-gradient(135deg,#f5f7fa,#e0e7ff);display:flex;align-items:center;justify-content:center;font-size:46px;}
.ci img{max-height:100%;max-width:100%;object-fit:cover;height:100%;width:100%;}
.cb{padding:12px;}
.cn{font-size:14px;font-weight:700;color:#1e293b;line-height:1.4;height:2.8em;overflow:hidden;}
.cp{font-size:16px;color:#ef4444;font-weight:800;margin-top:6px;}
</style>
</head>
<body>
<nav class="nav">
    <div class="nav-inner">
        <a href="shop.php" class="logo">🛒 Zaslon 商城</a>
        <div>
            <a href="shop.php" class="btn btn-ghost">🏠 返回商城</a>
            <?php if($user){ ?>
                <a href="user_center.php" class="btn btn-primary" style="margin-left:8px;">👤 我的</a>
                <span class="user-chip" style="margin-left:8px;display:inline-flex;">💰 <span class="balance"><?php echo price($user['balance'] ?? 0);?></span> · <?php echo e($user['name']);?></span>
            <?php } else { ?>
                <a href="login.php" class="btn btn-primary" style="margin-left:8px;">🔐 登录购买</a>
            <?php } ?>
        </div>
    </div>
</nav>
<div class="wrap">
    <?php if($toast){?><div class="toast <?php echo strpos($toast,'失败')!==false || strpos($toast,'不足')!==false?'err':'';?>"><?php echo e($toast);?></div><?php }?>
    <div class="breadcrumb">
        <a href="shop.php">首页</a> /
        <?php if($g['category']){?><a href="shop.php?c=<?php echo urlencode($g['category']);?>"><?php echo e($g['category']);?></a> / <?php }?>
        <span><?php echo e($g['goods_name']);?></span>
    </div>

    <div class="product">
        <div class="p-row">
            <div class="p-img">
                <?php if(!empty($g['image'])){?><img src="<?php echo e($g['image']);?>"><?php } else { echo !empty($g['emoji'])?$g['emoji']:'📦'; } ?>
            </div>
            <div>
                <div class="p-badges">
                    <span class="badge cat">🏷️ <?php echo e($g['category'] ?: '通用');?></span>
                    <?php if($g['is_hot']){?><span class="badge hot">🔥 热销</span><?php }?>
                    <?php if(floatval($g['origin_price'])>floatval($g['price']) && floatval($g['origin_price'])>0){?><span class="badge promo">🎉 优惠中</span><?php }?>
                </div>
                <div class="p-name"><?php echo e($g['goods_name']);?></div>
                <div class="p-desc"><?php echo e($g['description'] ?: '暂无描述，联系客服了解更多');?></div>

                <div class="p-price-row">
                    <div class="p-price-label">💰 到手价</div>
                    <div class="p-price"><?php echo price($g['price']);?><?php if(floatval($g['origin_price'])>floatval($g['price']) && floatval($g['origin_price'])>0){?><span class="p-origin">原价 <?php echo price($g['origin_price']);?></span><?php }?></div>
                </div>

                <div class="info-list">
                    <div><b>📦 库存状态：</b><span class="stock <?php echo $is_out?'out':(intval($g['stock'])<=5?'low':'ok')?>">
                        <?php if($is_out){?>❌ 已售罄<?php } elseif(intval($g['stock'])<=5){?>🔥 仅剩 <?php echo intval($g['stock']);?> 件<?php } else {?>✅ 充足（<?php echo intval($g['stock']);?> 件）<?php }?>
                    </span></div>
                    <div><b>📅 上架时间：</b><?php echo $g['create_time'] ?? '--';?></div>
                    <div><b>🔢 商品编号：</b>#<?php echo $g['id'];?></div>
                </div>

                <form method="post" action="buy.php" id="buy-form">
                    <input type="hidden" name="goods_id" value="<?php echo $g['id'];?>">
                    <div class="qty-row">
                        <span class="qty-label">购买数量：</span>
                        <div class="qty-box">
                            <button type="button" onclick="chgQty(-1)">−</button>
                            <input type="number" id="qty" name="qty" value="1" min="1" max="<?php echo max(1,intval($g['stock']));?>" readonly>
                            <button type="button" onclick="chgQty(1)">+</button>
                        </div>
                        <div class="total-box">合计：<b id="total"><?php echo price($g['price']);?></b></div>
                    </div>
                    <div class="buy-row">
                        <?php if(!$user){?>
                            <a class="btn btn-primary btn-lg" href="login.php?redirect=goods_detail.php%3Fid%3D<?php echo $g['id'];?>">🔐 登录后购买</a>
                        <?php } elseif($is_out){?>
                            <button type="button" class="btn btn-lg disabled" disabled>😢 已售罄</button>
                        <?php } else {?>
                            <button type="submit" class="btn btn-success btn-lg">💳 余额支付购买</button>
                            <?php if(floatval($user['balance'] ?? 0) < floatval($g['price'])){?>
                                <a href="user_center.php#recharge" class="btn btn-danger btn-lg">💰 余额不足？去充值</a>
                            <?php }?>
                        <?php }?>
                    </div>
                    <?php if($user){?>
                        <div style="margin-top:12px;font-size:13px;color:#64748b;">
                            您当前余额：<b style="color:#10b981;"><?php echo price($user['balance'] ?? 0);?></b>
                            ，下单后将自动从余额扣除。
                        </div>
                    <?php }?>
                </form>
            </div>
        </div>
    </div>

    <div class="detail-tabs">
        <h3>📝 商品详情</h3>
        <p><?php echo e($g['content'] ?: $g['description'] ?: '更多商品参数与详细说明请联系客服咨询~');?></p>
    </div>

    <?php if($related){?>
    <div class="related">
        <h3>🔥 猜你喜欢（同分类）</h3>
        <div class="grid">
            <?php foreach($related as $r){?>
            <div class="card">
                <a href="goods_detail.php?id=<?php echo $r['id'];?>">
                    <div class="ci"><?php if(!empty($r['image'])){?><img src="<?php echo e($r['image']);?>"><?php } else { echo !empty($r['emoji'])?$r['emoji']:'📦'; } ?></div>
                    <div class="cb">
                        <div class="cn"><?php echo e($r['goods_name']);?></div>
                        <div class="cp"><?php echo price($r['price']);?></div>
                    </div>
                </a>
            </div>
            <?php }?>
        </div>
    </div>
    <?php }?>
</div>
<script>
const PRICE = <?php echo floatval($g['price']);?>;
const STOCK = <?php echo max(0,intval($g['stock']));?>;
function chgQty(d){
    let v = parseInt(document.getElementById('qty').value) || 1;
    v = Math.max(1, Math.min(STOCK || 9999, v + d));
    document.getElementById('qty').value = v;
    document.getElementById('total').innerText = '¥' + (v*PRICE).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
}
document.getElementById('qty').addEventListener && document.getElementById('qty').addEventListener('change',function(){ chgQty(0); });
document.getElementById('buy-form').addEventListener && document.getElementById('buy-form').addEventListener('submit',function(e){
    if(STOCK<=0){ e.preventDefault(); alert('商品已售罄！'); return; }
    const q = parseInt(document.getElementById('qty').value)||1;
    if(!confirm('确认以 '+document.getElementById('total').innerText+' 的价格下单购买？')) e.preventDefault();
});
</script>
</body>
</html>
