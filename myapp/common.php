<?php
if (!defined('IN_SHOP')) define('IN_SHOP', true);

// 公共函数库
function e($s){
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function is_login(){
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

function current_user(){
    global $conn;
    if(!is_login()) return null;
    $uid = intval($_SESSION['user_id']);
    $res = mysqli_query($conn, "SELECT * FROM user_list WHERE id=$uid LIMIT 1");
    return mysqli_fetch_assoc($res);
}

function need_login($redirect_url = 'login.php'){
    if(!is_login()){
        header('Location: '.$redirect_url);
        exit;
    }
}

function set_login_user($user_id, $name=''){
    $_SESSION['user_id'] = intval($user_id);
    $_SESSION['user_name'] = $name;
}

function logout(){
    $_SESSION = [];
    session_destroy();
}

function is_admin(){
    return isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0;
}

function need_admin(){
    if(!is_admin()){
        header('Location: login.php');
        exit;
    }
}

function price($p){
    return '¥'.number_format(floatval($p),2);
}

function money($p){
    return number_format(floatval($p),2);
}

function redirect($url, $msg=''){
    if($msg){
        $_SESSION['_toast'] = $msg;
    }
    header('Location: '.$url);
    exit;
}

function toast_pop(){
    if(!empty($_SESSION['_toast'])){
        $m = $_SESSION['_toast'];
        unset($_SESSION['_toast']);
        return $m;
    }
    return null;
}

function order_status_text($s){
    $map = [0=>'待付款', 1=>'已支付', 2=>'已发货', 3=>'已完成', 9=>'已取消'];
    return $map[$s] ?? '未知';
}

function order_status_tag($s){
    $color = [0=>'orange', 1=>'blue', 2=>'purple', 3=>'green', 9=>'gray'];
    $c = $color[$s] ?? 'gray';
    return '<span class="tag tag-'.$c.'">'.order_status_text($s).'</span>';
}

function col_exists($table, $col){
    global $conn;
    $table = mysqli_real_escape_string($conn, $table);
    $col   = mysqli_real_escape_string($conn, $col);
    $res = @mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$col'");
    return $res && mysqli_num_rows($res) > 0;
}
