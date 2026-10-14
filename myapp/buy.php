<?php
include 'config.php';
include 'common.php';
need_login('login.php?redirect=buy.php');
$user = current_user();
if(!$user){ redirect('login.php','请先登录'); }

if(!$_POST){ redirect('shop.php','请选择商品下单'); }

$goods_id = intval($_POST['goods_id'] ?? 0);
$qty      = intval($_POST['qty'] ?? 1);
$qty      = max(1,$qty);
$redirect_back = 'goods_detail.php?id='.$goods_id;

if($goods_id<=0){ redirect($redirect_back,'商品参数错误'); }

$g = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM goods_list WHERE id=$goods_id LIMIT 1"));
if(!$g || intval($g['status'])!=1){ redirect('shop.php','商品已下架或不存在'); }
if(intval($g['stock'])<$qty){ redirect($redirect_back,'库存不足，仅剩 '.intval($g['stock']).' 件'); }

$unit_price = floatval($g['price']);
$total = $unit_price * $qty;
$uid = intval($user['id']);
$bal = floatval($user['balance'] ?? 0);

if($bal < $total){
    $lack = number_format($total - $bal,2);
    $_SESSION['_toast'] = '余额不足！还需 ¥'.$lack.'，请先充值。';
    header('Location: user_center.php#recharge');
    exit;
}

mysqli_query($conn,'START TRANSACTION');
$ok = true;
$err = '';

$bal_after = $bal - $total;
$sql1 = "UPDATE user_list SET balance=$bal_after WHERE id=$uid AND balance=$bal";
if(!mysqli_query($conn,$sql1)){ $ok=false; $err='扣款失败:'.mysqli_error($conn); }
elseif(mysqli_affected_rows($conn)!=1){ $ok=false; $err='扣款失败（余额变动）'; }

if($ok){
    $new_stock = intval($g['stock']) - $qty;
    $gid = intval($g['id']);
    $old_stock = intval($g['stock']);
    $sql2 = "UPDATE goods_list SET stock=$new_stock WHERE id=$gid AND stock=$old_stock";
    if(!mysqli_query($conn,$sql2)){ $ok=false; $err='扣库存失败'; }
    elseif(mysqli_affected_rows($conn)!=1){ $ok=false; $err='扣库存失败（库存变动，请重试）'; }
}

if($ok){
    $gn_s = mysqli_real_escape_string($conn,$g['goods_name']);
    $gimg_s = mysqli_real_escape_string($conn,$g['image'] ?? '');
    $cols = ["user_id","goods_id","goods_name","goods_image","qty","unit_price","total_price","pay_status","pay_time","create_time"];
    $vals = [$uid,$gid,"'$gn_s'","'$gimg_s'",$qty,$unit_price,$total,1,'NOW()','NOW()'];
    if(col_exists('order_list','status')){      $cols[]='status';       $vals[]=1; }
    if(col_exists('order_list','order_title')){ $cols[]='order_title';  $vals[]="'$gn_s x $qty'"; }
    if(col_exists('order_list','price')){       $cols[]='price';        $vals[]=$total; }
    $sql3 = "INSERT INTO order_list(".implode(',',$cols).") VALUES(".implode(',',$vals).")";
    if(!mysqli_query($conn,$sql3)){ $ok=false; $err='创建订单失败:'.mysqli_error($conn); }
}

if($ok){
    $oid = mysqli_insert_id($conn);
    mysqli_query($conn,'COMMIT');
    $_SESSION['_toast'] = '🎉 下单成功！订单号 #'.$oid.'，已扣款 ¥'.number_format($total,2);
    header('Location: user_center.php?order='.$oid);
    exit;
}else{
    mysqli_query($conn,'ROLLBACK');
    $_SESSION['_toast'] = '下单失败：'.$err;
    header('Location: '.$redirect_back);
    exit;
}
