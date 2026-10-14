<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config.php';

$id = intval($_GET['id']);

if($_POST){
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $sql = "UPDATE user_list SET name='$name',phone='$phone',address='$address' WHERE id=$id";
    mysqli_query($conn,$sql);
    header("Location:index.php");
    exit;
}

$info = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM user_list WHERE id=$id"));
if(!$info){
    header("Location:index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>编辑用户</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;background:#f5f7fa;color:#333;padding:40px 20px;}
        .container{max-width:520px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.08);padding:35px 40px;}
        .nav-tabs{display:flex;gap:8px;margin-bottom:25px;padding-bottom:20px;border-bottom:1px solid #eee;}
        .nav-tab{padding:8px 18px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;color:#666;transition:all 0.2s;}
        .nav-tab:hover{background:#f0f3f7;color:#333;}
        .nav-tab.active{background:#007bff;color:#fff;}
        .nav-tab.active:hover{background:#0069d9;}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;}
        h3{font-size:22px;color:#2c3e50;font-weight:600;}
        .back{text-decoration:none;color:#666;font-size:14px;display:flex;align-items:center;gap:4px;transition:color 0.2s;}
        .back:hover{color:#007bff;}
        .form-group{margin-bottom:22px;}
        label{display:block;margin-bottom:8px;font-size:14px;font-weight:500;color:#495057;}
        input[type=text]{width:100%;padding:11px 14px;border:1px solid #ced4da;border-radius:8px;font-size:14px;transition:border-color 0.2s,box-shadow 0.2s;outline:none;}
        input[type=text]:focus{border-color:#80bdff;box-shadow:0 0 0 3px rgba(0,123,255,0.1);}
        .btn-group{display:flex;gap:12px;margin-top:30px;}
        .btn{flex:1;padding:12px 20px;border:none;border-radius:8px;font-size:15px;font-weight:500;cursor:pointer;transition:all 0.2s;text-decoration:none;text-align:center;}
        .btn-primary{background:#007bff;color:#fff;}
        .btn-primary:hover{background:#0069d9;transform:translateY(-1px);box-shadow:0 2px 8px rgba(0,123,255,0.3);}
        .btn-secondary{background:#e9ecef;color:#495057;}
        .btn-secondary:hover{background:#dee2e6;}
        .id-badge{display:inline-block;background:#e7f3ff;color:#007bff;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:500;margin-left:8px;}
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-tabs">
            <a href="index.php" class="nav-tab active">👥 用户管理</a>
            <a href="order.php" class="nav-tab">📦 订单管理</a>
        </div>
        <div class="header">
            <h3>✏️ 编辑用户<span class="id-badge">ID: <?php echo $info['id'] ?></span></h3>
            <a href="index.php" class="back">← 返回列表</a>
        </div>
        <form method="post">
            <div class="form-group">
                <label for="name">姓名</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($info['name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="phone">手机号</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($info['phone']) ?>">
            </div>
            <div class="form-group">
                <label for="address">地址</label>
                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($info['address']) ?>">
            </div>
            <div class="btn-group">
                <a href="index.php" class="btn btn-secondary">取消</a>
                <input type="submit" class="btn btn-primary" value="保存修改">
            </div>
        </form>
    </div>
</body>
</html>
