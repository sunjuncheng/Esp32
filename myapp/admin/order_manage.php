<?php
include __DIR__.'/common.php';
need_admin_login();
$toast = toast_pop();
$admin_name = admin_name();

$action = $_GET['action'] ?? '';
$_has_status = col_exists('order_list','status');
if($action==='upd_status' && $_GET['id']){
    $id = intval($_GET['id']);
    $st = intval($_GET['st'] ?? 1);
    $st = in_array($st,[0,1,2,3,9])?$st:1;
    if(!$_has_status){
        redirect('order_manage.php','数据库缺少 status 字段，请先访问 upgrade_db.php 升级');
    }
    if(mysqli_query($conn, "UPDATE order_list SET status=$st WHERE id=$id")){
        redirect('order_manage.php','订单 #'.$id.' 状态已更新');
    }
}
if($action==='del' && $_GET['id']){
    $id = intval($_GET['id']);
    if(mysqli_query($conn, "DELETE FROM order_list WHERE id=$id")){
        redirect('order_manage.php','订单 #'.$id.' 已删除');
    }
}
if($_POST && ($_POST['act']??'')==='save_express'){
    $id = intval($_POST['id'] ?? 0);
    if($id<=0){ redirect('order_manage.php','订单ID非法'); }
    $set = [];
    if(col_exists('order_list','ship_name'))    $set[] = "ship_name='".    mysqli_real_escape_string($conn, trim($_POST['ship_name']    ??''))."'";
    if(col_exists('order_list','ship_phone'))   $set[] = "ship_phone='".   mysqli_real_escape_string($conn, trim($_POST['ship_phone']   ??''))."'";
    if(col_exists('order_list','ship_address')) $set[] = "ship_address='". mysqli_real_escape_string($conn, trim($_POST['ship_address'] ??''))."'";
    if(col_exists('order_list','express_company')) $set[] = "express_company='".mysqli_real_escape_string($conn, trim($_POST['express_company']??''))."'";
    if(col_exists('order_list','express_no'))   $set[] = "express_no='".   mysqli_real_escape_string($conn, trim($_POST['express_no']   ??''))."'";
    if(col_exists('order_list','express_note')) $set[] = "express_note='". mysqli_real_escape_string($conn, trim($_POST['express_note'] ??''))."'";
    $exp_no = trim($_POST['express_no'] ?? '');
    if($exp_no!=='' && col_exists('order_list','express_time')){
        if(!isset($_POST['keep_time']) || $_POST['keep_time']!=1){
            $set[] = "express_time=NOW()";
        }
        if($_has_status){
            $st = intval($_POST['auto_status'] ?? 2);
            $set[] = "status=$st";
        }
    }
    if(empty($set)){ redirect('order_manage.php','物流字段未就绪，请先升级数据库'); }
    $sql = "UPDATE order_list SET ".implode(',',$set)." WHERE id=$id";
    if(mysqli_query($conn,$sql)){
        redirect('order_manage.php','🚚 订单 #'.$id.' 物流信息已保存'.($exp_no!==''?'（已标记为发货）':''));
    }else{
        $err_save = '保存失败：'.mysqli_error($conn);
    }
}

$where = '1=1';
$kw = trim($_GET['kw'] ?? '');
$status = intval($_GET['s'] ?? -1);
if($kw){
    $kw_s = mysqli_real_escape_string($conn,$kw);
    $where .= " AND (o.order_title LIKE '%$kw_s%' OR o.goods_name LIKE '%$kw_s%' OR u.name LIKE '%$kw_s%' OR u.phone LIKE '%$kw_s%' OR o.id LIKE '%$kw_s%')";
}
if($status>=0 && $_has_status){
    $where .= " AND o.status=$status";
}
elseif($status>=0){
    $status = -1;
}

$res = mysqli_query($conn, "SELECT o.*,u.name as user_name,u.phone as user_phone 
    FROM order_list o LEFT JOIN user_list u ON o.user_id=u.id 
    WHERE $where ORDER BY o.id DESC LIMIT 200");
$list = [];
while($row=mysqli_fetch_assoc($res)) $list[] = $row;

$g_total = 0; $g_paid = 0;
foreach($list as $o){
    $p = floatval($o['total_price'] ?? $o['price']);
    $g_total += $p;
    if(intval($o['pay_status'])==1) $g_paid += $p;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>订单管理 - 后台</title>
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
.toast{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;font-weight:500;background:#dcfce7;color:#166534;border-left:4px solid #10b981;}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:16px;}
.stat{background:#fff;border-radius:12px;padding:16px 20px;box-shadow:0 2px 8px rgba(0,0,0,0.05);}
.stat-label{font-size:12px;color:#64748b;margin-bottom:5px;}
.stat-val{font-size:22px;font-weight:800;}
.stat.blue .stat-val{color:#3b82f6;}
.stat.green .stat-val{color:#10b981;}
.stat.orange .stat-val{color:#f59e0b;}
.panel{background:#fff;border-radius:14px;padding:22px 24px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:18px;}
.panel-title{font-size:16px;font-weight:700;margin-bottom:14px;color:#0f172a;padding-bottom:10px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px;}
.tool-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px;}
.tool-row form{display:flex;gap:10px;flex:1;flex-wrap:wrap;}
.tool-row input,.tool-row select{padding:7px 11px;border:1px solid #dee2e6;border-radius:7px;outline:none;font-size:13px;}
table{width:100%;border-collapse:collapse;font-size:13px;}
thead th{background:#f8fafc;color:#475569;padding:10px 7px;text-align:center;border-bottom:2px solid #e2e8f0;font-weight:600;}
tbody td{padding:10px 7px;text-align:center;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle;}
tbody tr:hover{background:#f8fafc;}
.tag{display:inline-block;padding:2px 9px;border-radius:11px;font-size:11px;font-weight:600;}
.tag-green{background:#dcfce7;color:#166534;}
.tag-blue{background:#dbeafe;color:#1e40af;}
.tag-orange{background:#fed7aa;color:#9a3412;}
.tag-gray{background:#f1f5f9;color:#64748b;}
.tag-purple{background:#ede9fe;color:#6d28d9;}
.tag-pink{background:#fce7f3;color:#be185d;}
.price{color:#ef4444;font-weight:700;}
.user-b{display:inline-flex;flex-direction:column;align-items:center;gap:2px;font-size:12px;}
.user-b b{font-size:12.5px;color:#1e293b;}
.user-b s{color:#64748b;font-size:11px;text-decoration:none;}
.ops a{margin:1px;}
.empty{text-align:center;padding:50px;color:#94a3b8;}
.dropdown{position:relative;display:inline-block;}
.modal-mask{position:fixed;inset:0;background:rgba(15,23,42,0.5);z-index:100;display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);}
.modal-mask.hide{display:none;}
.modal-box{background:#fff;border-radius:16px;padding:24px 28px;max-width:580px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.25);animation:popUp 0.2s ease-out;}
@keyframes popUp{from{opacity:0;transform:translateY(20px) scale(0.97);}to{opacity:1;transform:none;}}
.modal-title{font-size:18px;font-weight:800;margin-bottom:18px;color:#0f172a;display:flex;align-items:center;gap:8px;padding-bottom:12px;border-bottom:1px solid #e2e8f0;}
.modal-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;}
.modal-grid .full{grid-column:1/-1;}
.modal-item{display:flex;flex-direction:column;gap:5px;}
.modal-item label{font-size:12.5px;font-weight:700;color:#475569;}
.modal-item input,.modal-item textarea,.modal-item select{padding:9px 12px;border:1px solid #dee2e6;border-radius:8px;font-size:13.5px;outline:none;font-family:inherit;}
.modal-item input:focus,.modal-item textarea:focus{border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,0.12);}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid #f1f5f9;}
.express-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:700;background:#ede9fe;color:#6d28d9;margin-left:6px;}
.express-badge.none{background:#f1f5f9;color:#94a3b8;}
.ship-box{background:#eff6ff;border-left:4px solid #667eea;padding:10px 14px;border-radius:8px;font-size:12.5px;color:#1e3a8a;line-height:1.7;margin-top:6px;}
.ship-box b{color:#1e40af;}
.copy-btn{padding:3px 8px;background:#fff;border:1px solid #c7d2fe;color:#4f46e5;border-radius:6px;font-size:11px;cursor:pointer;font-weight:700;transition:all 0.15s;}
.copy-btn:hover{background:#667eea;color:#fff;}
</style>
<script>
function openExpress(id, name, phone, addr, expcom, expno, expnote){
    document.getElementById('ex_id').value = id;
    document.getElementById('ex_ship_name').value = name || '';
    document.getElementById('ex_ship_phone').value = phone || '';
    document.getElementById('ex_ship_address').value = addr || '';
    document.getElementById('ex_company').value = expcom || '';
    document.getElementById('ex_no').value = expno || '';
    document.getElementById('ex_note').value = expnote || '';
    document.getElementById('expressModal').classList.remove('hide');
    document.getElementById('ex_no').focus();
}
function closeModal(){ document.getElementById('expressModal').classList.add('hide'); }
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });
</script>
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
        <a href="order_manage.php" class="active">🧾 订单管理</a>
        <a href="user_manage.php">👥 用户管理</a>
        <a href="device_manage.php">🌡️ 设备管理</a>
        <a href="logout.php" style="margin-top:22px;color:#fca5a5;border-top:1px solid rgba(255,255,255,0.05);padding-top:14px;">🚪 退出登录</a>
    </nav>
</aside>
<main class="main">
    <div class="topbar">
        <div class="page-title">🧾 订单管理 <small>最多展示最近 200 条</small></div>
        <a href="index.php" class="btn btn-ghost">← 返回控制台</a>
    </div>
    <?php if($toast){?><div class="toast"><?php echo e($toast);?></div><?php }?>

    <div class="stat-grid">
        <div class="stat blue"><div class="stat-label">订单总数</div><div class="stat-val"><?php echo count($list);?></div></div>
        <div class="stat green"><div class="stat-label">列表总金额</div><div class="stat-val">¥<?php echo number_format($g_total,2);?></div></div>
        <div class="stat orange"><div class="stat-label">列表实收金额</div><div class="stat-val">¥<?php echo number_format($g_paid,2);?></div></div>
    </div>

    <div class="panel">
        <div class="panel-title">🔍 筛选 / 搜索</div>
        <div class="tool-row">
            <form method="get">
                <input type="text" name="kw" placeholder="搜：订单号 / 用户 / 商品 / 手机号" value="<?php echo e($kw);?>" style="min-width:240px;">
                <select name="s">
                    <option value="-1">全部状态</option>
                    <?php
                    $smap = [0=>'待确认',1=>'已下单',2=>'已发货',3=>'已完成',9=>'已取消'];
                    foreach($smap as $k=>$v){
                        echo '<option value="'.$k.'" '.($status==$k?'selected':'').'>'.$v.'</option>';
                    }
                    ?>
                </select>
                <button class="btn btn-primary btn-sm">🔍 搜索</button>
                <?php if($kw||$status>=0){?><a href="order_manage.php" class="btn btn-ghost btn-sm">↺ 重置</a><?php }?>
            </form>
        </div>
        <?php if(empty($list)){?><div class="empty">暂无订单</div>
        <?php } else {?>
        <div style="overflow-x:auto;">
        <table>
            <thead><tr>
                <th>订单号</th><th>用户</th><th>商品</th><th>数量</th><th>下单时间</th>
                <th>支付</th><th>状态</th><th>实付</th><th>操作</th>
            </tr></thead>
            <tbody>
            <?php foreach($list as $o){
                $total = floatval($o['total_price'] ?? $o['price']);
                $qty = intval($o['qty'] ?? 1);
                $st = intval($o['status'] ?? 1);
            ?>
            <tr>
                <td style="font-weight:700;">#<?php echo $o['id'];?></td>
                <td><div class="user-b">
                    <b><?php echo e($o['user_name'] ?? 'UID:'.$o['user_id']);?></b>
                    <s>UID:<?php echo $o['user_id'];?> <?php echo $o['user_phone']?'· '.$o['user_phone']:'';?></s>
                </div></td>
                <td style="max-width:180px;text-align:left;">
                    <?php if($o['goods_id']){?><a href="../goods_detail.php?id=<?php echo $o['goods_id'];?>" target="_blank" style="color:#667eea;text-decoration:none;font-weight:600;"><?php }?>
                        <?php echo e($o['goods_name'] ?: ($o['order_title'] ?? '--'));?>
                    <?php if($o['goods_id']){?></a><?php }?>
                    <?php if(isset($o['unit_price']) && $o['unit_price']>0){?><div style="color:#94a3b8;font-size:11px;">单价 <?php echo number_format($o['unit_price'],2);?></div><?php }?>
                    <?php
                    $_ex_no  = trim($o['express_no'] ?? '');
                    $_ex_com = trim($o['express_company'] ?? '');
                    $_ex_tm  = trim($o['express_time'] ?? '');
                    $_sh_name= trim($o['ship_name'] ?? '');
                    $_sh_ph  = trim($o['ship_phone'] ?? '');
                    $_sh_ad  = trim($o['ship_address'] ?? '');
                    if($_ex_no!=='' || $_sh_name!=='' || $_sh_ad!==''){
                        echo '<div class="ship-box">';
                        if($_ex_no!==''){
                            echo '🚚 <b>'.$_ex_com.'</b> <span class="copy-btn" onclick="navigator.clipboard.writeText(this.dataset.v)" data-v="'.e($_ex_no).'" title="复制运单号">📋 '.e($_ex_no).'</span>';
                            if($_ex_tm) echo ' · <span style="color:#64748b;">'.$_ex_tm.'</span>';
                            echo '<br>';
                        }
                        if($_sh_name!=='') echo '👤 <b>'.e($_sh_name).'</b> '.e($_sh_ph).'<br>';
                        if($_sh_ad!=='')   echo '📍 '.e(mb_substr($_sh_ad,0,36)).(mb_strlen($_sh_ad)>36?'...':'');
                        echo '</div>';
                    }
                    ?>
                </td>
                <td><?php echo $qty;?></td>
                <td style="font-size:11.5px;color:#64748b;"><?php echo $o['create_time'];?><br><?php if($o['pay_time']){?><span style="color:#10b981;">✓ <?php echo $o['pay_time'];?></span><?php }?></td>
                <td><?php echo intval($o['pay_status'])==1?'<span class="tag tag-green">✅ 已支付</span>':'<span class="tag tag-orange">⏳ 待付</span>';
                    if($_ex_no!==''){ echo '<span class="express-badge">📦 已发货</span>'; }
                    else{ echo '<span class="express-badge none">待发货</span>'; }
                ?></td>
                <td>
                    <?php
                        if($st==0) echo '<span class="tag tag-gray">待确认</span>';
                        elseif($st==1) echo '<span class="tag tag-blue">📦 已下单</span>';
                        elseif($st==2) echo '<span class="tag tag-purple">🚚 已发货</span>';
                        elseif($st==3) echo '<span class="tag tag-green">🎉 完成</span>';
                        elseif($st==9) echo '<span class="tag tag-pink">❌ 取消</span>';
                    ?>
                </td>
                <td class="price">¥<?php echo number_format($total,2);?></td>
                <td class="ops">
                    <button class="btn btn-primary btn-sm" style="background:linear-gradient(135deg,#f6d365,#fda085);"
                        onclick='openExpress(
                            <?php echo $o['id'];?>,
                            <?php echo json_encode($o['ship_name'] ?? '');?>,
                            <?php echo json_encode($o['ship_phone'] ?? '');?>,
                            <?php echo json_encode($o['ship_address'] ?? '');?>,
                            <?php echo json_encode($o['express_company'] ?? '');?>,
                            <?php echo json_encode($o['express_no'] ?? '');?>,
                            <?php echo json_encode($o['express_note'] ?? '');?>
                        )'>📦 物流</button>
                    <?php foreach([1=>'已下单',2=>'发货',3=>'完成',9=>'取消'] as $k=>$v){
                        if($st!=$k){ echo '<a class="btn btn-ghost btn-sm" href="?action=upd_status&id='.$o['id'].'&st='.$k.'" onclick="return confirm(\'设置订单状态为：'.$v.'？\')">→'.$v.'</a>'; }
                    }?>
                    <a class="btn btn-danger btn-sm" href="?action=del&id=<?php echo $o['id'];?>" onclick="return confirm('确认永久删除订单 #<?php echo $o['id'];?>？不可恢复！')">🗑️</a>
                </td>
            </tr>
            <?php }?>
            </tbody>
        </table>
        </div>
        <?php }?>
    </div>
</main>

<!-- 📦 物流填写弹窗 -->
<div id="expressModal" class="modal-mask hide" onclick="if(event.target===this)closeModal()">
<div class="modal-box">
    <div class="modal-title">📦 订单物流信息填写 <span style="color:#667eea;font-size:13px;" id="ex_order_id_label"></span></div>
    <form method="post" onsubmit="document.getElementById('ex_order_id_label').innerText='（保存中...)'">
        <input type="hidden" name="act" value="save_express">
        <input type="hidden" name="id" id="ex_id">
        <div class="modal-grid">
            <div class="modal-item">
                <label>📝 快递公司 <span style="color:#667eea;">（填运单号即自动标记为「发货」）</span></label>
                <select id="ex_company" name="express_company">
                    <option value="">— 选择或在下面手动输入 —</option>
                    <option>顺丰速运</option><option>京东物流</option><option>圆通速递</option>
                    <option>中通快递</option><option>韵达快递</option><option>申通快递</option>
                    <option>邮政EMS</option><option>极兔速递</option><option>德邦快递</option>
                    <option>其他快递</option>
                </select>
            </div>
            <div class="modal-item">
                <label>🔢 运单号 <span style="color:#ef4444;">*</span></label>
                <input type="text" id="ex_no" name="express_no" required placeholder="请输入快递单号">
            </div>
            <div class="modal-item">
                <label>👤 收货人姓名</label>
                <input type="text" id="ex_ship_name" name="ship_name" placeholder="张三">
            </div>
            <div class="modal-item">
                <label>📱 收货人电话</label>
                <input type="text" id="ex_ship_phone" name="ship_phone" placeholder="13800138000">
            </div>
            <div class="modal-item full">
                <label>📍 收货地址</label>
                <input type="text" id="ex_ship_address" name="ship_address" placeholder="例如：北京市朝阳区XX街道XX小区XX栋XX室">
            </div>
            <div class="modal-item full">
                <label>💬 物流备注（可选）</label>
                <textarea name="express_note" id="ex_note" rows="2" placeholder="例如：工作日送达、顺丰次日达等"></textarea>
            </div>
            <div class="modal-item">
                <label><input type="checkbox" name="keep_time" value="1" style="margin-right:5px;">保留原发货时间（不更新）</label>
            </div>
            <div class="modal-item">
                <label>自动变更订单状态为：
                    <select name="auto_status" style="padding:3px 6px;border:1px solid #cbd5e1;border-radius:5px;margin-left:4px;">
                        <option value="2" selected>🚚 已发货（推荐）</option>
                        <option value="1">📦 不改变（仅存物流信息）</option>
                        <option value="3">🎉 已完成</option>
                    </select>
                </label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal()">取消</button>
                <button type="submit" class="btn btn-primary">✅ 保存物流信息</button>
            </div>
        </div>
    </form>
</div>
</div>
<script>
// 打开弹窗时同步显示订单ID
const _origOpen = openExpress;
openExpress = function(){ _origOpen(...arguments); const id = arguments[0]; document.getElementById('ex_order_id_label').innerText = '订单 #'+id; };
</script>
</body>
</html>
