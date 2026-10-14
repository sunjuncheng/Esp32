<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config.php';

if(isset($_GET['del'])){
    $id = intval($_GET['del']);
    mysqli_query($conn,"DELETE FROM user_list WHERE id=$id");
    header("Location:index.php");exit;
}

$sql = "SELECT * FROM user_list ORDER BY id DESC";
$res = mysqli_query($conn,$sql);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>用户管理</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;background:#f5f7fa;color:#333;padding:40px 20px;}
        .container{max-width:900px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.08);padding:30px 40px;}
        .nav-tabs{display:flex;gap:8px;margin-bottom:25px;padding-bottom:20px;border-bottom:1px solid #eee;}
        .nav-tab{padding:8px 18px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;color:#666;transition:all 0.2s;}
        .nav-tab:hover{background:#f0f3f7;color:#333;}
        .nav-tab.active{background:#007bff;color:#fff;}
        .nav-tab.active:hover{background:#0069d9;}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;}
        h3{font-size:22px;color:#2c3e50;font-weight:600;}
        table{width:100%;border-collapse:collapse;margin-top:10px;}
        th{background:#f8f9fa;color:#495057;font-weight:600;padding:14px 12px;text-align:center;border-bottom:2px solid #dee2e6;font-size:14px;}
        td{padding:14px 12px;text-align:center;border-bottom:1px solid #e9ecef;font-size:14px;color:#555;}
        tbody tr:hover{background:#f8f9fa;transition:background 0.2s;}
        .btn{display:inline-block;padding:7px 16px;text-decoration:none;color:#fff;border-radius:6px;margin:0 4px;font-size:13px;font-weight:500;transition:all 0.2s;cursor:pointer;border:none;}
        .btn:hover{transform:translateY(-1px);box-shadow:0 2px 6px rgba(0,0,0,0.15);}
        .add{background:#28a745;}
        .add:hover{background:#218838;}
        .edit{background:#007bff;}
        .edit:hover{background:#0069d9;}
        .del{background:#dc3545;}
        .del:hover{background:#c82333;}
        .empty{text-align:center;padding:60px 20px;color:#999;font-size:15px;}
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-tabs">
            <a href="index.php" class="nav-tab active">👥 用户管理</a>
            <a href="order.php" class="nav-tab">📦 订单管理</a>
        </div>
        <div class="header">
            <h3>📋 用户列表</h3>
            <a href="add.php" class="btn add">➕ 新增用户</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>姓名</th>
                    <th>手机号</th>
                    <th>地址</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($res) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($res)){ ?>
                <tr>
                    <td><?php echo $row['id'] ?></td>
                    <td><?php echo htmlspecialchars($row['name']) ?></td>
                    <td><?php echo htmlspecialchars($row['phone']) ?></td>
                    <td><?php echo htmlspecialchars($row['address']) ?></td>
                    <td><?php echo $row['create_time'] ?></td>
                    <td>
                        <a href="edit.php?id=<?php echo $row['id'] ?>" class="btn edit">编辑</a>
                        <a href="?del=<?php echo $row['id'] ?>" onclick="return confirm('确定删除该用户？')" class="btn del">删除</a>
                    </td>
                </tr>
                <?php } ?>
                <?php else: ?>
                <tr>
                    <td colspan="6" class="empty">暂无用户数据，点击右上角「新增用户」添加</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
