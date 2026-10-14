<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config.php';

if($_POST){
    $user_id     = intval($_POST['user_id']);
    $order_title = mysqli_real_escape_string($conn, $_POST['order_title']);
    $price       = floatval($_POST['price']);

    if($user_id <= 0){
        $error = '请选择下单用户';
    }elseif(trim($order_title) === ''){
        $error = '订单标题不能为空';
    }elseif($price <= 0){
        $error = '订单金额必须大于0';
    }else{
        $sql = "INSERT INTO order_list(user_id, order_title, price) VALUES ($user_id, '$order_title', $price)";
        mysqli_query($conn,$sql);
        header("Location:order.php");
        exit;
    }
}

$user_res = mysqli_query($conn,"SELECT id,name FROM user_list ORDER BY id DESC");
$user_count = mysqli_num_rows($user_res);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>新增订单</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;background:#f5f7fa;color:#333;padding:40px 20px;}
        .container{max-width:560px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.08);padding:35px 40px;}
        .nav-tabs{display:flex;gap:8px;margin-bottom:25px;padding-bottom:20px;border-bottom:1px solid #eee;}
        .nav-tab{padding:8px 18px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;color:#666;transition:all 0.2s;}
        .nav-tab:hover{background:#f0f3f7;color:#333;}
        .nav-tab.active{background:#007bff;color:#fff;}
        .nav-tab.active:hover{background:#0069d9;}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;}
        .page-header h3{font-size:22px;color:#2c3e50;font-weight:600;}
        .back{text-decoration:none;color:#666;font-size:14px;display:flex;align-items:center;gap:4px;transition:color 0.2s;}
        .back:hover{color:#007bff;}
        .form-group{margin-bottom:22px;}
        label{display:block;margin-bottom:8px;font-size:14px;font-weight:500;color:#495057;}
        label .required{color:#dc3545;margin-left:2px;}
        select,input[type=text],input[type=number]{width:100%;padding:11px 14px;border:1px solid #ced4da;border-radius:8px;font-size:14px;transition:border-color 0.2s,box-shadow 0.2s;outline:none;background:#fff;-webkit-appearance:none;}
        select:focus,input:focus{border-color:#80bdff;box-shadow:0 0 0 3px rgba(0,123,255,0.1);}
        select{cursor:pointer;}
        .input-wrap{position:relative;}
        .input-wrap .prefix{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#868e96;font-size:14px;pointer-events:none;}
        .input-wrap.with-prefix input{padding-left:30px;}
        .btn-group{display:flex;gap:12px;margin-top:30px;}
        .btn{flex:1;padding:12px 20px;border:none;border-radius:8px;font-size:15px;font-weight:500;cursor:pointer;transition:all 0.2s;text-decoration:none;text-align:center;}
        .btn-primary{background:#28a745;color:#fff;}
        .btn-primary:hover{background:#218838;transform:translateY(-1px);box-shadow:0 2px 8px rgba(40,167,69,0.3);}
        .btn-primary:disabled{background:#a0d6a9;cursor:not-allowed;transform:none;box-shadow:none;}
        .btn-secondary{background:#e9ecef;color:#495057;}
        .btn-secondary:hover{background:#dee2e6;}
        .alert-danger{background:#fff5f5;border:1px solid #fed7d7;color:#c53030;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px;}
        .alert-warning{background:#fffbeb;border:1px solid #fde68a;color:#b45309;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px;}
        .alert-warning a{color:#b45309;text-decoration:underline;font-weight:500;}
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-tabs">
            <a href="index.php" class="nav-tab">👥 用户管理</a>
            <a href="order.php" class="nav-tab active">📦 订单管理</a>
        </div>
        <div class="page-header">
            <h3>➕ 新增订单</h3>
            <a href="order.php" class="back">← 返回订单列表</a>
        </div>

        <?php if($user_count == 0): ?>
            <div class="alert-warning">
                ⚠️ 还没有用户数据！请先 <a href="add.php">去新增用户</a>，然后再来下单。
            </div>
        <?php endif; ?>

        <?php if(isset($error)): ?>
            <div class="alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="user_id">下单用户<span class="required">*</span></label>
                <select id="user_id" name="user_id" required <?php echo $user_count==0?'disabled':''; ?>>
                    <option value="">-- 请选择用户 --</option>
                    <?php
                    mysqli_data_seek($user_res, 0);
                    while($u = mysqli_fetch_assoc($user_res)){
                        echo '<option value="'.$u['id'].'">'.$u['name'].' (ID:'.$u['id'].')</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="order_title">订单标题<span class="required">*</span></label>
                <input type="text" id="order_title" name="order_title" required placeholder="例如：MacBook Pro 电脑订单" maxlength="200">
            </div>
            <div class="form-group">
                <label for="price">订单金额<span class="required">*</span></label>
                <div class="input-wrap with-prefix">
                    <span class="prefix">¥</span>
                    <input type="number" id="price" name="price" required placeholder="0.00" step="0.01" min="0.01">
                </div>
            </div>
            <div class="btn-group">
                <a href="order.php" class="btn btn-secondary">取消</a>
                <input type="submit" class="btn btn-primary" value="保存订单" <?php echo $user_count==0?'disabled':''; ?>>
            </div>
        </form>
    </div>
</body>
</html>
