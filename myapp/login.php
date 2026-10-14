<?php
include 'config.php';
include 'common.php';
$toast = toast_pop();
$tab = $_GET['tab'] ?? 'login';
$err = '';
$ok = '';

if($_POST){
    $act = $_POST['act'] ?? '';
    if($act==='login'){
        $phone = trim($_POST['phone'] ?? '');
        $pwd   = trim($_POST['pwd'] ?? '');
        if($phone==='' || $pwd===''){
            $err = '请输入手机号和密码';
        }else{
            $p_s = mysqli_real_escape_string($conn,$phone);
            $row = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM user_list WHERE phone='$p_s' LIMIT 1"));
            if(!$row){
                $err = '账号不存在';
            }else{
                $db_pwd = $row['pwd'] ?? '';
                $ok_pwd = ($db_pwd==='') ? ($pwd==='123456') : password_verify($pwd,$db_pwd) || $pwd===$db_pwd;
                if(!$ok_pwd){
                    $err = '密码错误（默认初始密码 123456）';
                }else{
                    set_login_user($row['id'], $row['name']);
                    redirect('shop.php','登录成功，欢迎 '.$row['name']);
                }
            }
        }
        $tab = 'login';
    }elseif($act==='register'){
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $pwd     = trim($_POST['pwd'] ?? '');
        $pwd2    = trim($_POST['pwd2'] ?? '');
        $address = trim($_POST['address'] ?? '');
        if($name==='' || $phone==='' || $pwd===''){
            $err = '请填写姓名、手机号、密码';
        }elseif($pwd!==$pwd2){
            $err = '两次密码不一致';
        }elseif(strlen($pwd)<4){
            $err = '密码至少4位';
        }else{
            $p_s = mysqli_real_escape_string($conn,$phone);
            $chk = mysqli_fetch_assoc(mysqli_query($conn,"SELECT id FROM user_list WHERE phone='$p_s' LIMIT 1"));
            if($chk){ $err='该手机号已注册，直接登录即可'; }
            else{
                $n_s = mysqli_real_escape_string($conn,$name);
                $a_s = mysqli_real_escape_string($conn,$address);
                $pwd_s = mysqli_real_escape_string($conn,$pwd);
                $sql = "INSERT INTO user_list(name,phone,pwd,address,balance,create_time) VALUES('$n_s','$p_s','$pwd_s','$a_s',0,NOW())";
                if(mysqli_query($conn,$sql)){
                    $uid = mysqli_insert_id($conn);
                    set_login_user($uid,$name);
                    redirect('shop.php','注册成功！已自动登录');
                }else{
                    $err = '注册失败：'.mysqli_error($conn);
                }
            }
        }
        $tab = 'reg';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>登录/注册 - 商城</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.wrap{background:#fff;border-radius:18px;padding:36px 38px;box-shadow:0 20px 60px rgba(0,0,0,0.25);width:100%;max-width:420px;}
.brand{text-align:center;margin-bottom:26px;}
.brand h1{font-size:26px;background:linear-gradient(135deg,#667eea,#764ba2);-webkit-background-clip:text;color:transparent;margin-bottom:6px;}
.brand p{color:#888;font-size:13px;}
.tabs{display:flex;background:#f1f3f5;border-radius:10px;padding:4px;margin-bottom:24px;}
.tab{flex:1;padding:11px 0;text-align:center;font-weight:600;border-radius:8px;color:#666;cursor:pointer;transition:all 0.2s;font-size:14px;}
.tab.active{background:#fff;color:#667eea;box-shadow:0 2px 8px rgba(0,0,0,0.08);}
.form-item{margin-bottom:18px;}
.form-item label{display:block;margin-bottom:6px;font-size:13px;color:#495057;font-weight:600;}
.form-item input{width:100%;padding:12px 14px;border:1px solid #dee2e6;border-radius:10px;font-size:14px;outline:none;transition:all 0.2s;}
.form-item input:focus{border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,0.12);}
.btn{width:100%;padding:13px;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;margin-top:6px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;transition:all 0.2s;}
.btn:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(102,126,234,0.4);}
.hint{margin-top:16px;text-align:center;font-size:13px;color:#888;}
.hint a{color:#667eea;font-weight:600;text-decoration:none;}
.toast{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;font-weight:500;}
.toast.err{background:#fee2e2;color:#b91c1c;border-left:4px solid #ef4444;}
.toast.ok{background:#dcfce7;color:#166534;border-left:4px solid #10b981;}
.back{display:block;text-align:center;margin-top:18px;color:#667eea;text-decoration:none;font-size:13px;font-weight:600;}
</style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <h1>🛒 Zaslon 商城</h1>
        <p>账户余额支付，安全便捷</p>
    </div>
    <?php if($toast){?><div class="toast ok"><?php echo e($toast);?></div><?php }?>
    <?php if($err){?><div class="toast err"><?php echo e($err);?></div><?php }?>
    <?php if($ok){?><div class="toast ok"><?php echo e($ok);?></div><?php }?>
    <div class="tabs">
        <div class="tab <?php echo $tab==='login'?'active':''?>" onclick="switchTab('login')">登录</div>
        <div class="tab <?php echo $tab==='reg'?'active':''?>" onclick="switchTab('reg')">注册</div>
    </div>

    <form method="post" id="form-login" style="<?php echo $tab==='reg'?'display:none':''?>">
        <input type="hidden" name="act" value="login">
        <div class="form-item">
            <label>📱 手机号</label>
            <input type="text" name="phone" placeholder="请输入手机号" required>
        </div>
        <div class="form-item">
            <label>🔑 密码</label>
            <input type="password" name="pwd" placeholder="请输入密码（初始 123456）" required>
        </div>
        <button type="submit" class="btn">登 录</button>
        <div class="hint">还没有账号？<a onclick="switchTab('reg');return false;" href="#">立即注册</a></div>
    </form>

    <form method="post" id="form-reg" style="<?php echo $tab==='login'?'display:none':''?>">
        <input type="hidden" name="act" value="register">
        <div class="form-item">
            <label>👤 姓名<span style="color:#ef4444;">*</span></label>
            <input type="text" name="name" placeholder="请输入姓名" required>
        </div>
        <div class="form-item">
            <label>📱 手机号<span style="color:#ef4444;">*</span></label>
            <input type="text" name="phone" placeholder="请输入手机号" required>
        </div>
        <div class="form-item">
            <label>🏠 收货地址</label>
            <input type="text" name="address" placeholder="选填，可后补">
        </div>
        <div class="form-item">
            <label>🔑 设置密码<span style="color:#ef4444;">*</span></label>
            <input type="password" name="pwd" placeholder="至少4位" required minlength="4">
        </div>
        <div class="form-item">
            <label>🔐 确认密码<span style="color:#ef4444;">*</span></label>
            <input type="password" name="pwd2" placeholder="再次输入密码" required minlength="4">
        </div>
        <button type="submit" class="btn">注 册 并 登 录</button>
        <div class="hint">已有账号？<a onclick="switchTab('login');return false;" href="#">去登录</a></div>
    </form>
    <a href="shop.php" class="back">← 返回商城首页</a>
</div>
<script>
function switchTab(t){
    document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));
    document.querySelector('.tab:nth-child('+(t==='login'?1:2)+')').classList.add('active');
    document.getElementById('form-login').style.display = (t==='login')?'block':'none';
    document.getElementById('form-reg').style.display   = (t==='reg')?'block':'none';
}
</script>
</body>
</html>
