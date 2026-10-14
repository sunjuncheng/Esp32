<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$db_host = '127.0.0.1';
$db_name = 'db_1235672218';
$db_user = 'db_1235672218';
$db_pwd  = 'aED5nY9nW4yWbdeM';

$conn = mysqli_connect($db_host,$db_user,$db_pwd,$db_name);
if(!$conn){
    die("数据库连接失败:".mysqli_connect_error());
}
mysqli_set_charset($conn,"utf8mb4");
