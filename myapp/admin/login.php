<?php
include __DIR__.'/common.php';
$toast = toast_pop();
$err = '';

if(is_admin_login()){ header('Location: index.php'); exit; }

if($_POST){
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['pwd'] ?? '');
    $list = ADMIN_USERS();
    if(!isset($list[$u])){
        $err = '管理员账号不存在';
    }elseif($list[$u]['pwd'] !== $p){
        $err = '密码错误';
    }else{
        set_admin_login($u, $list[$u]['name']);
        redirect('index.php','登录成功，欢迎 '.$list[$u]['name']);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>后台登录 - Zaslon 商城管理系统</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:linear-gradient(135deg,#0f172a 0%,#1e293b 50%,#312e81 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.wrap{background:rgba(255,255,255,0.98);border-radius:18px;padding:40px 42px;box-shadow:0 30px 80px rgba(0,0,0,0.4);width:100%;max-width:430px;backdrop-filter:blur(10px);}
.brand{text-align:center;margin-bottom:26px;}
.brand .emoji{font-size:50px;display:block;margin-bottom:6px;}
.brand h1{font-size:24px;background:linear-gradient(135deg,#667eea,#764ba2);-webkit-background-clip:text;color:transparent;margin-bottom:4px;}
.brand p{color:#888;font-size:13px;}
.hint-box{background:#eff6ff;border-left:4px solid #667eea;padding:10px 12px;border-radius:8px;margin-bottom:18px;font-size:12.5px;color:#1e40af;line-height:1.7;}
.hint-box b{color:#667eea;}
.toast{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;font-weight:500;}
.toast.err{background:#fee2e2;color:#b91c1c;border-left:4px solid #ef4444;}
.toast.ok{background:#dcfce7;color:#166534;border-left:4px solid #10b981;}
.form-item{margin-bottom:18px;}
.form-item label{display:block;margin-bottom:6px;font-size:13px;color:#475569;font-weight:600;}
.form-item input{width:100%;padding:12px 14px;border:1px solid #dee2e6;border-radius:10px;font-size:14px;outline:none;transition:all 0.2s;}
.form-item input:focus{border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,0.12);}
.btn{width:100%;padding:13px;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;margin-top:6px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:all 0.25s;}
.btn:hover{transform:translateY(-1px);box-shadow:0 10px 24px rgba(102,126,234,0.45);}
.back{display:block;text-align:center;margin-top:18px;color:#64748b;text-decoration:none;font-size:13px;font-weight:500;}
.back:hover{color:#667eea;}
</style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <span class="emoji">🛡️</span>
        <h1>Zaslon 商城后台</h1>
        <p>管理系统 · 登录鉴权</p>
    </div>
    <div class="hint-box">
        <b>内置管理员账号：</b><br>
        ① 用户名 <code>admin</code> / 密码 <code>admin123</code><br>
        ② 用户名 <code>shop</code> &nbsp;&nbsp;/ 密码 <code>shop123</code>
    </div>
    <?php if($toast){?><div class="toast ok"><?php echo e($toast);?></div><?php }?>
    <?php if($err){?><div class="toast err"><?php echo e($err);?></div><?php }?>
    <form method="post">
        <div class="form-item">
            <label>👤 管理员账号</label>
            <input type="text" name="username" placeholder="请输入管理员账号" required autocomplete="username">
        </div>
        <div class="form-item">
            <label>🔐 密码</label>
            <input type="password" name="pwd" placeholder="请输入密码" required autocomplete="current-password">
        </div>
        <button class="btn" type="submit">登 录 后 台</button>
    </form>
    <a href="../shop.php" class="back">← 返回商城前台</a>
</div>
</body>
</html>
