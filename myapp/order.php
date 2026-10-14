<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config.php';

if(isset($_GET['del'])){
    $id = intval($_GET['del']);
    mysqli_query($conn,"DELETE FROM order_list WHERE id=$id");
    $redirect = "Location:order.php";
    if(!empty($_GET['user_id'])){
        $redirect .= "?user_id=".intval($_GET['user_id']);
    }
    header($redirect);exit;
}

$search_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$search_keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

$where_sql = "";
$params = [];
if($search_user_id > 0){
    $where_sql .= " WHERE o.user_id = $search_user_id ";
}
if($search_keyword !== ''){
    $keyword_s = mysqli_real_escape_string($conn, $search_keyword);
    if($where_sql === ''){
        $where_sql .= " WHERE ";
    }else{
        $where_sql .= " AND ";
    }
    $where_sql .= " (o.order_title LIKE '%$keyword_s%' OR u.name LIKE '%$keyword_s%') ";
}

$sql = "SELECT o.*, u.name as user_name 
        FROM order_list o 
        LEFT JOIN user_list u ON o.user_id = u.id 
        $where_sql
        ORDER BY o.id DESC";
$res = mysqli_query($conn,$sql);

$total_sql = "SELECT COUNT(*) as cnt, COALESCE(SUM(o.price),0) as total 
              FROM order_list o 
              LEFT JOIN user_list u ON o.user_id = u.id 
              $where_sql";
$total_res = mysqli_query($conn,$total_sql);
$total_row = mysqli_fetch_assoc($total_res);

$query_string = $_GET;
unset($query_string['del']);
$qs = http_build_query($query_string);
$qs = $qs ? '?'.$qs : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>订单管理</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;background:#f5f7fa;color:#333;padding:40px 20px;}
        .container{max-width:1100px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.08);padding:30px 40px;}
        .nav-tabs{display:flex;gap:8px;margin-bottom:25px;padding-bottom:20px;border-bottom:1px solid #eee;}
        .nav-tab{padding:8px 18px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;color:#666;transition:all 0.2s;}
        .nav-tab:hover{background:#f0f3f7;color:#333;}
        .nav-tab.active{background:#007bff;color:#fff;}
        .nav-tab.active:hover{background:#0069d9;}
        .page-title-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;}
        .page-title-row h3{font-size:22px;color:#2c3e50;font-weight:600;}
        .stats{display:flex;gap:16px;flex-wrap:wrap;}
        .stat-card{background:#f8f9fa;border-radius:8px;padding:12px 20px;min-width:140px;}
        .stat-card .label{font-size:12px;color:#868e96;margin-bottom:4px;}
        .stat-card .value{font-size:20px;font-weight:600;color:#2c3e50;}
        .stat-card.blue .value{color:#007bff;}
        .stat-card.green .value{color:#28a745;}
        .stat-card.orange{background:#fff7ec;}
        .stat-card.orange .value{color:#f08c00;}

        .search-bar{background:#f8f9fa;border-radius:10px;padding:18px 20px;margin-bottom:24px;border:1px solid #e9ecef;}
        .search-form{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;}
        .search-field{flex:1;min-width:160px;}
        .search-field label{display:block;font-size:12px;font-weight:500;color:#666;margin-bottom:6px;}
        .search-field input{width:100%;padding:9px 12px;border:1px solid #ced4da;border-radius:7px;font-size:14px;outline:none;transition:all 0.2s;background:#fff;}
        .search-field input:focus{border-color:#80bdff;box-shadow:0 0 0 3px rgba(0,123,255,0.1);}
        .search-btns{display:flex;gap:8px;}
        .btn-search{padding:9px 20px;background:#007bff;color:#fff;border:none;border-radius:7px;font-size:14px;font-weight:500;cursor:pointer;transition:all 0.2s;}
        .btn-search:hover{background:#0069d9;transform:translateY(-1px);box-shadow:0 2px 6px rgba(0,123,255,0.3);}
        .btn-reset{padding:9px 20px;background:#fff;color:#666;border:1px solid #ced4da;border-radius:7px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;transition:all 0.2s;display:inline-block;}
        .btn-reset:hover{background:#f0f3f7;}

        .toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;}
        .search-active-info{font-size:13px;color:#666;display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
        .tag-filter{display:inline-flex;align-items:center;gap:4px;background:#e7f3ff;color:#007bff;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:500;}
        .tag-filter .close{color:#0056b3;cursor:pointer;font-weight:700;margin-left:2px;}
        .btn{display:inline-block;padding:7px 16px;text-decoration:none;color:#fff;border-radius:6px;margin:0 4px;font-size:13px;font-weight:500;transition:all 0.2s;cursor:pointer;border:none;}
        .btn:hover{transform:translateY(-1px);box-shadow:0 2px 6px rgba(0,0,0,0.15);}
        .add{background:#28a745;}
        .add:hover{background:#218838;}
        .edit{background:#007bff;}
        .edit:hover{background:#0069d9;}
        .del{background:#dc3545;}
        .del:hover{background:#c82333;}

        table{width:100%;border-collapse:collapse;margin-top:10px;}
        th{background:#f8f9fa;color:#495057;font-weight:600;padding:14px 12px;text-align:center;border-bottom:2px solid #dee2e6;font-size:14px;}
        td{padding:14px 12px;text-align:center;border-bottom:1px solid #e9ecef;font-size:14px;color:#555;}
        tbody tr:hover{background:#f8f9fa;transition:background 0.2s;}
        .price{color:#28a745;font-weight:600;}
        .user-tag{display:inline-block;background:#e7f3ff;color:#007bff;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:500;}
        .user-tag.none{background:#f5f5f5;color:#999;}
        .empty{text-align:center;padding:60px 20px;color:#999;font-size:15px;}
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-tabs">
            <a href="index.php" class="nav-tab">👥 用户管理</a>
            <a href="order.php" class="nav-tab active">📦 订单管理</a>
        </div>

        <div class="page-title-row">
            <h3>📦 订单列表</h3>
            <div class="stats">
                <div class="stat-card blue">
                    <div class="label"><?php echo ($search_user_id>0||$search_keyword!=='')?'筛选后订单数':'订单总数'; ?></div>
                    <div class="value"><?php echo $total_row['cnt']; ?></div>
                </div>
                <div class="stat-card green">
                    <div class="label"><?php echo ($search_user_id>0||$search_keyword!=='')?'筛选后总金额':'订单总金额'; ?></div>
                    <div class="value">¥<?php echo number_format($total_row['total'], 2); ?></div>
                </div>
                <?php if($search_user_id > 0): ?>
                <div class="stat-card orange">
                    <div class="label">筛选条件</div>
                    <div class="value" style="font-size:15px;">UID: <?php echo $search_user_id; ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="search-bar">
            <form class="search-form" method="get" action="order.php">
                <div class="search-field">
                    <label>🔍 按用户ID搜索</label>
                    <input type="number" name="user_id" placeholder="输入用户ID，如 1" min="1" value="<?php echo $search_user_id>0?$search_user_id:''; ?>">
                </div>
                <div class="search-field" style="flex:2;min-width:220px;">
                    <label>🔎 关键词（订单标题/用户名）</label>
                    <input type="text" name="keyword" placeholder="输入关键词模糊搜索" value="<?php echo htmlspecialchars($search_keyword); ?>">
                </div>
                <div class="search-btns">
                    <button type="submit" class="btn-search">🔍 搜索</button>
                    <a href="order.php" class="btn-reset">↺ 重置</a>
                </div>
            </form>
        </div>

        <div class="toolbar">
            <div class="search-active-info">
                <?php if($search_user_id > 0 || $search_keyword !== ''): ?>
                    📌 当前筛选：
                    <?php if($search_user_id > 0): ?>
                        <span class="tag-filter">
                            用户ID=<?php echo $search_user_id; ?>
                            <a href="order.php?<?php $tmp=$_GET;unset($tmp['user_id']);echo http_build_query($tmp);?>" class="close" title="移除">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if($search_keyword !== ''): ?>
                        <span class="tag-filter">
                            关键词:「<?php echo htmlspecialchars($search_keyword); ?>」
                            <a href="order.php?<?php $tmp=$_GET;unset($tmp['keyword']);echo http_build_query($tmp);?>" class="close" title="移除">×</a>
                        </span>
                    <?php endif; ?>
                    <span style="color:#999;">— 共找到 <?php echo $total_row['cnt']; ?> 条订单</span>
                <?php else: ?>
                    <span style="color:#868e96;">显示全部订单，共 <?php echo $total_row['cnt']; ?> 条</span>
                <?php endif; ?>
            </div>
            <a href="add_order.php" class="btn add">➕ 新增订单</a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>订单ID</th>
                    <th>下单用户</th>
                    <th>订单标题</th>
                    <th>金额</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($res) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($res)){ ?>
                <tr>
                    <td>#<?php echo $row['id'] ?></td>
                    <td>
                        <?php if($row['user_name']): ?>
                            <a href="order.php?user_id=<?php echo $row['user_id']; ?>" style="text-decoration:none;">
                                <span class="user-tag" title="点击查看该用户所有订单"><?php echo htmlspecialchars($row['user_name']) ?> (UID:<?php echo $row['user_id'] ?>)</span>
                            </a>
                        <?php else: ?>
                            <span class="user-tag none">用户已删除 (UID:<?php echo $row['user_id'] ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['order_title']) ?></td>
                    <td class="price">¥<?php echo number_format($row['price'], 2); ?></td>
                    <td><?php echo $row['create_time'] ?></td>
                    <td>
                        <a href="edit_order.php?id=<?php echo $row['id'] ?>" class="btn edit">编辑</a>
                        <a href="?del=<?php echo $row['id'] ?><?php echo $qs!==''?'&'.ltrim($qs,'?'):''; ?>" onclick="return confirm('确定删除订单 #<?php echo $row['id'] ?>？')" class="btn del">删除</a>
                    </td>
                </tr>
                <?php } ?>
                <?php else: ?>
                <tr>
                    <td colspan="6" class="empty">
                        <?php if($search_user_id>0 || $search_keyword!==''): ?>
                            😕 没有找到符合条件的订单，<a href="order.php" style="color:#007bff;">清除筛选</a> 查看全部
                        <?php else: ?>
                            暂无订单数据，点击右上角「新增订单」添加
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
