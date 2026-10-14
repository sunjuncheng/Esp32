<?php
include __DIR__.'/common.php';
need_admin_login();
$toast = toast_pop();
$admin_name = admin_name();

$action = $_GET['action'] ?? '';
$err = ''; $ok = '';

if($action==='del' && $_GET['id']){
    $id = intval($_GET['id']);
    if(mysqli_query($conn, "UPDATE goods_list SET status=0 WHERE id=$id")){
        redirect('goods_manage.php','商品已下架（软删除）');
    }else{ $err = '操作失败：'.mysqli_error($conn); }
}elseif($action==='onsale' && $_GET['id']){
    $id = intval($_GET['id']);
    if(mysqli_query($conn, "UPDATE goods_list SET status=1 WHERE id=$id")){
        redirect('goods_manage.php','商品已重新上架');
    }else{ $err = '操作失败：'.mysqli_error($conn); }
}elseif($_POST){
    $act = $_POST['act'] ?? '';
    $data = [
        'goods_name' => trim($_POST['goods_name'] ?? ''),
        'category'   => trim($_POST['category'] ?? ''),
        'price'      => floatval($_POST['price'] ?? 0),
        'origin_price' => floatval($_POST['origin_price'] ?? 0),
        'stock'      => intval($_POST['stock'] ?? 0),
        'emoji'      => trim($_POST['emoji'] ?? ''),
        'image'      => trim($_POST['image'] ?? ''),
        'description'=> trim($_POST['description'] ?? ''),
        'content'    => trim($_POST['content'] ?? ''),
        'sort'       => intval($_POST['sort'] ?? 0),
        'is_hot'     => intval($_POST['is_hot'] ?? 0),
    ];
    if($data['goods_name']===''){ $err='商品名称不能为空'; }
    elseif($data['price']<=0){ $err='售价必须大于0'; }
    elseif(strlen($data['image'])>65000){ $err='商品图片URL太长了，请缩短或使用更短的图床链接（建议<500字符）'; }
    else{
        foreach($data as $k=>$v) if(is_string($v)) $data[$k] = mysqli_real_escape_string($conn,$v);
        if($act==='add'){
            $cols = implode(',', array_keys($data));
            $vals = "'".implode("','", array_values($data))."'";
            $sql = "INSERT INTO goods_list($cols,status,create_time) VALUES($vals,1,NOW())";
            if(mysqli_query($conn,$sql)){ redirect('goods_manage.php','新增商品成功：'.$data['goods_name']); }
            else{ $err = '新增失败：'.mysqli_error($conn); }
        }elseif($act==='edit' && ($id=intval($_POST['id']??0))>0){
            $set = [];
            foreach($data as $k=>$v){ $set[] = "$k='".(is_string($v)?$v:$v)."'"; }
            $sql = "UPDATE goods_list SET ".implode(',',$set)." WHERE id=$id";
            if(mysqli_query($conn,$sql)){ redirect('goods_manage.php','商品信息已更新'); }
            else{ $err = '更新失败：'.mysqli_error($conn); }
        }
    }
}

$edit_goods = null;
if($action==='edit' && $_GET['id']){
    $id = intval($_GET['id']);
    $edit_goods = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM goods_list WHERE id=$id LIMIT 1"));
}

$list = [];
$kw = trim($_GET['kw'] ?? '');
$where = '1=1';
if($kw){
    $kw_s = mysqli_real_escape_string($conn,$kw);
    $where .= " AND (goods_name LIKE '%$kw_s%' OR category LIKE '%$kw_s%' OR description LIKE '%$kw_s%')";
}
$res = mysqli_query($conn, "SELECT * FROM goods_list WHERE $where ORDER BY status DESC, sort ASC, id DESC");
while($row=mysqli_fetch_assoc($res)) $list[] = $row;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>商品管理 - 后台</title>
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
.btn{padding:7px 13px;border-radius:7px;font-size:12.5px;font-weight:600;border:none;cursor:pointer;transition:all 0.2s;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
.btn:hover{transform:translateY(-1px);box-shadow:0 4px 10px rgba(0,0,0,0.1);}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;}
.btn-success{background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;}
.btn-warning{background:linear-gradient(135deg,#f6d365,#fda085);color:#fff;}
.btn-ghost{background:#f1f5f9;color:#475569;}
.btn-danger{color:#ef4444;background:#fee2e2;}
.btn-sm{padding:5px 10px;font-size:11.5px;}
.toast{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;font-weight:500;}
.toast.err{background:#fee2e2;color:#b91c1c;border-left:4px solid #ef4444;}
.toast.ok{background:#dcfce7;color:#166534;border-left:4px solid #10b981;}
.panel{background:#fff;border-radius:14px;padding:24px 26px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:18px;}
.panel-title{font-size:16px;font-weight:700;margin-bottom:16px;color:#0f172a;padding-bottom:10px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px;}
.tool-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px;}
.tool-row input{padding:8px 12px;border:1px solid #dee2e6;border-radius:8px;outline:none;}
.tool-row form{display:flex;gap:10px;flex:1;flex-wrap:wrap;}
table{width:100%;border-collapse:collapse;font-size:13.5px;}
thead th{background:#f8fafc;color:#475569;padding:11px 8px;text-align:center;border-bottom:2px solid #e2e8f0;font-weight:600;}
tbody td{padding:11px 8px;text-align:center;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle;}
tbody tr:hover{background:#f8fafc;}
.tag{display:inline-block;padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:600;}
.tag-green{background:#dcfce7;color:#166534;}
.tag-gray{background:#f1f5f9;color:#64748b;}
.tag-orange{background:#fed7aa;color:#9a3412;}
.tag-blue{background:#dbeafe;color:#1e40af;}
.tag-purple{background:#ede9fe;color:#6d28d9;}
.price{color:#ef4444;font-weight:700;}
.emoji{width:42px;height:42px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto;overflow:hidden;}
.emoji img{width:100%;height:100%;object-fit:cover;}
.stock.ok{color:#10b981;}
.stock.low{color:#f59e0b;font-weight:700;}
.stock.out{color:#ef4444;font-weight:700;}
.empty{text-align:center;padding:50px;color:#94a3b8;}
.empty a{color:#667eea;font-weight:600;text-decoration:none;}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;max-width:1100px;}
.form-item{display:flex;flex-direction:column;gap:6px;}
.form-item.full{grid-column:1/-1;}
.form-item label{font-size:12.5px;font-weight:600;color:#475569;display:flex;align-items:center;gap:4px;}
.form-item label span{color:#ef4444;}
.form-item input,.form-item select,.form-item textarea{padding:9px 12px;border:1px solid #dee2e6;border-radius:8px;font-size:13.5px;outline:none;transition:all 0.2s;font-family:inherit;}
.form-item input:focus,.form-item select:focus,.form-item textarea:focus{border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,0.12);}
.form-actions{grid-column:1/-1;display:flex;gap:10px;margin-top:6px;}
.hr{margin:20px 0;border:none;border-top:1px dashed #e2e8f0;}
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
        <a href="goods_manage.php" class="active">📦 商品管理</a>
        <a href="order_manage.php">🧾 订单管理</a>
        <a href="user_manage.php">👥 用户管理</a>
        <a href="device_manage.php">🌡️ 设备管理</a>
        <a href="logout.php" style="margin-top:22px;color:#fca5a5;border-top:1px solid rgba(255,255,255,0.05);padding-top:14px;">🚪 退出登录</a>
    </nav>
</aside>
<main class="main">
    <div class="topbar">
        <div class="page-title">📦 商品管理 <small>增删改查 / 上下架</small></div>
        <a href="?action=add#form" class="btn btn-primary">➕ 新增商品</a>
    </div>
    <?php if($toast){?><div class="toast ok"><?php echo e($toast);?></div><?php }?>
    <?php if($err){?><div class="toast err"><?php echo e($err);?></div><?php }?>

    <div class="panel" id="form">
        <div class="panel-title"><?php echo $edit_goods?'✏️ 编辑商品 (ID:'.$edit_goods['id'].')':'➕ 新增商品';?></div>
        <form method="post">
            <input type="hidden" name="act" value="<?php echo $edit_goods?'edit':'add';?>">
            <?php if($edit_goods){?><input type="hidden" name="id" value="<?php echo $edit_goods['id'];?>"><?php }?>
            <div class="form-grid">
                <div class="form-item">
                    <label>商品名称 <span>*</span></label>
                    <input type="text" name="goods_name" maxlength="100" required value="<?php echo e($edit_goods['goods_name'] ?? '');?>" placeholder="例如：iPhone 15 Pro">
                </div>
                <div class="form-item">
                    <label>商品分类</label>
                    <input type="text" name="category" maxlength="50" value="<?php echo e($edit_goods['category'] ?? '');?>" placeholder="例如：数码/服饰/食品">
                </div>
                <div class="form-item">
                    <label>售价 (¥) <span>*</span></label>
                    <input type="number" step="0.01" min="0.01" name="price" required value="<?php echo $edit_goods['price'] ?? '';?>" placeholder="0.00">
                </div>
                <div class="form-item">
                    <label>原价 (¥)，显示划线</label>
                    <input type="number" step="0.01" min="0" name="origin_price" value="<?php echo $edit_goods['origin_price'] ?? 0;?>" placeholder="0.00（不显示可填 0）">
                </div>
                <div class="form-item">
                    <label>库存数量 <span>*</span></label>
                    <input type="number" min="0" name="stock" required value="<?php echo $edit_goods['stock'] ?? 100;?>">
                </div>
                <div class="form-item">
                    <label>排序（数字越小越靠前）</label>
                    <input type="number" name="sort" value="<?php echo $edit_goods['sort'] ?? 0;?>">
                </div>
                <div class="form-item">
                    <label>Emoji 图标（无图时显示）</label>
                    <input type="text" name="emoji" maxlength="8" value="<?php echo e($edit_goods['emoji'] ?? '📦');?>" placeholder="如：📱👕🍎">
                </div>
                <div class="form-item">
                    <label>商品图片 URL（可选）</label>
                    <input type="url" name="image" value="<?php echo e($edit_goods['image'] ?? '');?>" placeholder="https://.../xxx.jpg">
                </div>
                <div class="form-item">
                    <label>热销标识</label>
                    <select name="is_hot">
                        <option value="0" <?php echo intval($edit_goods['is_hot']??0)==0?'selected':'';?>>普通</option>
                        <option value="1" <?php echo intval($edit_goods['is_hot']??0)==1?'selected':'';?>>🔥 热销</option>
                    </select>
                </div>
                <div class="form-item full">
                    <label>商品简介（列表显示）</label>
                    <textarea name="description" rows="2" placeholder="简短描述，2-3 行为佳"><?php echo e($edit_goods['description'] ?? '');?></textarea>
                </div>
                <div class="form-item full">
                    <label>商品详情（详情页展示，支持换行）</label>
                    <textarea name="content" rows="5" placeholder="详细介绍商品规格、参数、售后等"><?php echo e($edit_goods['content'] ?? '');?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-success"><?php echo $edit_goods?'💾 保存修改':'✅ 新增上架';?></button>
                    <a href="goods_manage.php" class="btn btn-ghost">取消 / 清空</a>
                </div>
            </div>
        </form>
    </div>

    <div class="panel">
        <div class="panel-title">📋 商品列表（共 <?php echo count($list);?> 条）</div>
        <div class="tool-row">
            <form method="get">
                <input type="text" name="kw" placeholder="🔍 搜索名称/分类/描述" value="<?php echo e($kw);?>">
                <button class="btn btn-primary btn-sm">搜索</button>
                <?php if($kw){?><a href="goods_manage.php" class="btn btn-ghost btn-sm">↺ 重置</a><?php }?>
            </form>
        </div>
        <?php if(empty($list)){?><div class="empty">暂无商品，<a href="?action=add#form">去添加第一个商品 →</a></div>
        <?php } else {?>
        <div style="overflow-x:auto;">
        <table>
            <thead><tr>
                <th>ID</th><th>图</th><th>商品名称</th><th>分类</th><th>售价</th><th>原价</th><th>库存</th><th>排序</th><th>标识</th><th>状态</th><th>操作</th>
            </tr></thead>
            <tbody>
            <?php foreach($list as $g){
                $stk = intval($g['stock']);
                $stk_cls = $stk<=0?'out':($stk<=5?'low':'ok');
                ?>
            <tr>
                <td>#<?php echo $g['id'];?></td>
                <td><div class="emoji"><?php if($g['image']){?><img src="<?php echo e($g['image']);?>"><?php } else { echo e($g['emoji']?:'📦'); } ?></div></td>
                <td style="text-align:left;"><b><?php echo e($g['goods_name']);?></b>
                    <div style="color:#94a3b8;font-size:11.5px;line-height:1.4;margin-top:3px;"><?php echo e(mb_substr($g['description'],0,40));?></div>
                </td>
                <td><?php if($g['category']){?><span class="tag tag-purple"><?php echo e($g['category']);?></span><?php } else {?>--<?php }?></td>
                <td class="price"><?php echo price($g['price']);?></td>
                <td style="color:#94a3b8;text-decoration:line-through;"><?php echo floatval($g['origin_price'])>0?price($g['origin_price']):'--';?></td>
                <td class="stock <?php echo $stk_cls;?>">
                    <?php if($stk<=0){?>❌ 售罄<?php } else { echo $stk; } ?>
                </td>
                <td><?php echo intval($g['sort']);?></td>
                <td><?php echo intval($g['is_hot'])?'<span class="tag tag-orange">🔥HOT</span>':'--';?></td>
                <td><?php echo intval($g['status'])==1?'<span class="tag tag-green">在售</span>':'<span class="tag tag-gray">已下架</span>';?></td>
                <td>
                    <a class="btn btn-warning btn-sm" href="?action=edit&id=<?php echo $g['id'];?>#form">✏️ 编辑</a>
                    <?php if(intval($g['status'])==1){?>
                        <a class="btn btn-danger btn-sm" href="?action=del&id=<?php echo $g['id'];?>" onclick="return confirm('确认下架商品？')">📤 下架</a>
                    <?php } else {?>
                        <a class="btn btn-success btn-sm" href="?action=onsale&id=<?php echo $g['id'];?>" onclick="return confirm('确认重新上架？')">📥 上架</a>
                    <?php }?>
                </td>
            </tr>
            <?php }?>
            </tbody>
        </table>
        </div>
        <?php }?>
    </div>
</main>
</body>
</html>
