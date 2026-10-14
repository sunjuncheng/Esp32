<?php
if (!defined('IN_SHOP')) define('IN_SHOP', true);
$_cfg = '';
foreach (['../config.php', '../../config.php', './config.php'] as $_p) {
    if (file_exists($_p)) { $_cfg = $_p; break; }
}
if($_cfg===''){ die('后台配置文件未找到，请检查目录结构'); }
include $_cfg;
$_cmn = '';
foreach (['../common.php', '../../common.php', './common.php'] as $_p) {
    if (file_exists($_p)) { $_cmn = $_p; break; }
}
if($_cmn) include $_cmn;

function is_admin_login(){
    return isset($_SESSION['admin_id']) && $_SESSION['admin_id']>0;
}
function need_admin_login(){
    if(!is_admin_login()){
        header('Location: login.php');
        exit;
    }
}
function set_admin_login($id, $name=''){
    $_SESSION['admin_id'] = $id;
    $_SESSION['admin_name'] = $name;
}
function admin_logout(){
    unset($_SESSION['admin_id'],$_SESSION['admin_name']);
}
function admin_name(){
    return $_SESSION['admin_name'] ?? $_SESSION['admin_id'] ?? 'Admin';
}

// 固定后台账号（生产建议改为数据库管理员表）
function ADMIN_USERS(){
    return [
        'admin' => ['pwd'=>'admin123', 'name'=>'超级管理员'],
        'shop'  => ['pwd'=>'shop123',  'name'=>'商城运营']
    ];
}
