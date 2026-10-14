<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);

$_cfg = '';
foreach (['config.php', './config.php', '../config.php', '../../config.php'] as $_p) {
    if (file_exists($_p)) { $_cfg = $_p; break; }
}
if ($_cfg === '') {
    echo json_encode(['code' => 500, 'msg' => '未找到 config.php，请确认 api.php 已正确上传']);
    exit;
}
include $_cfg;
if (!isset($conn)) {
    echo json_encode(['code' => 500, 'msg' => '数据库连接失败']);
    exit;
}

// 检查字段是否存在的工具函数
function col_exists($table, $col){
    global $conn;
    static $cache = [];
    $k = $table.'.'.$col;
    if(isset($cache[$k])) return $cache[$k];
    $res = @mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$col'");
    $ok = $res && mysqli_num_rows($res) > 0;
    $cache[$k] = $ok;
    return $ok;
}
// 检查表是否存在
function table_exists($table){
    global $conn;
    static $cache = [];
    if(isset($cache[$table])) return $cache[$table];
    $res = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $ok = $res && mysqli_num_rows($res) > 0;
    $cache[$table] = $ok;
    return $ok;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'getUser':
        $sql = "SELECT * FROM user_list ORDER BY id DESC";
        $res = mysqli_query($conn, $sql);
        $list = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $list[] = $row;
        }
        echo json_encode(['code' => 200, 'msg' => '获取成功', 'data' => $list]);
        break;

    case 'addUser':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['code' => 405, 'msg' => '请求方式错误，请使用POST']);
            exit;
        }
        $name    = $_POST['name'] ?? '';
        $phone   = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        if (trim($name) === '') {
            echo json_encode(['code' => 400, 'msg' => '姓名不能为空']);
            exit;
        }
        $name_s    = mysqli_real_escape_string($conn, $name);
        $phone_s   = mysqli_real_escape_string($conn, $phone);
        $address_s = mysqli_real_escape_string($conn, $address);
        $sql = "INSERT INTO user_list(name,phone,address,create_time) VALUES('$name_s','$phone_s','$address_s',NOW())";
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['code' => 200, 'msg' => '新增成功', 'user_id' => mysqli_insert_id($conn)]);
        } else {
            echo json_encode(['code' => 500, 'msg' => '新增失败：' . mysqli_error($conn)]);
        }
        break;

    case 'editUser':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['code' => 405, 'msg' => '请求方式错误，请使用POST']);
            exit;
        }
        $id      = $_POST['id'] ?? 0;
        $name    = $_POST['name'] ?? '';
        $phone   = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        if (!is_numeric($id) || $id <= 0) {
            echo json_encode(['code' => 400, 'msg' => '用户id非法']);
            exit;
        }
        if (trim($name) === '') {
            echo json_encode(['code' => 400, 'msg' => '姓名不能为空']);
            exit;
        }
        $id      = intval($id);
        $name_s    = mysqli_real_escape_string($conn, $name);
        $phone_s   = mysqli_real_escape_string($conn, $phone);
        $address_s = mysqli_real_escape_string($conn, $address);
        $sql = "UPDATE user_list SET name='$name_s',phone='$phone_s',address='$address_s' WHERE id=$id";
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['code' => 200, 'msg' => '修改成功']);
        } else {
            echo json_encode(['code' => 500, 'msg' => '修改失败：' . mysqli_error($conn)]);
        }
        break;

    case 'delUser':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['code' => 405, 'msg' => '请求方式错误，请使用POST']);
            exit;
        }
        $id = $_POST['id'] ?? 0;
        if (!is_numeric($id) || $id <= 0) {
            echo json_encode(['code' => 400, 'msg' => '用户id非法']);
            exit;
        }
        $id = intval($id);
        $sql = "DELETE FROM user_list WHERE id=$id";
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['code' => 200, 'msg' => '删除成功']);
        } else {
            echo json_encode(['code' => 500, 'msg' => '删除失败：' . mysqli_error($conn)]);
        }
        break;

    case 'getUserWithOrder':
        $user_id = $_GET['user_id'] ?? 0;
        if (!is_numeric($user_id) || $user_id <= 0) {
            echo json_encode(['code' => 400, 'msg' => 'user_id必须是有效的数字']);
            exit;
        }
        $user_id = intval($user_id);
        $user_sql = "SELECT * FROM user_list WHERE id=$user_id";
        $user_res = mysqli_query($conn, $user_sql);
        $user = mysqli_fetch_assoc($user_res);
        if (!$user) {
            echo json_encode(['code' => 404, 'msg' => '用户不存在']);
            exit;
        }
        $order_sql = "SELECT * FROM order_list WHERE user_id=$user_id ORDER BY id DESC";
        $order_res = mysqli_query($conn, $order_sql);
        $orders = [];
        while ($row = mysqli_fetch_assoc($order_res)) {
            $orders[] = $row;
        }
        $user['orders'] = $orders;
        echo json_encode(['code' => 200, 'msg' => '获取成功', 'data' => $user]);
        break;

    case 'addOrder':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['code' => 405, 'msg' => '请求方式错误，请使用POST']);
            exit;
        }
        $user_id     = $_POST['user_id'] ?? 0;
        $order_title = $_POST['order_title'] ?? '';
        $price       = $_POST['price'] ?? 0;
        if (!is_numeric($user_id) || $user_id <= 0) {
            echo json_encode(['code' => 400, 'msg' => 'user_id必须是有效的数字']);
            exit;
        }
        if (trim($order_title) === '') {
            echo json_encode(['code' => 400, 'msg' => '订单标题不能为空']);
            exit;
        }
        if (!is_numeric($price) || $price <= 0) {
            echo json_encode(['code' => 400, 'msg' => '订单金额必须大于0']);
            exit;
        }
        $user_id     = intval($user_id);
        $order_title_s = mysqli_real_escape_string($conn, $order_title);
        $price       = floatval($price);
        $sql = "INSERT INTO order_list(user_id, order_title, price) VALUES ($user_id, '$order_title_s', $price)";
        if (mysqli_query($conn, $sql)) {
            echo json_encode([
                'code' => 200,
                'msg'  => '订单新增成功',
                'order_id' => mysqli_insert_id($conn)
            ]);
        } else {
            echo json_encode(['code' => 500, 'msg' => '新增失败：' . mysqli_error($conn)]);
        }
        break;

    case 'editOrder':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['code' => 405, 'msg' => '请求方式错误，请使用POST']);
            exit;
        }
        $id          = $_POST['id'] ?? 0;
        $user_id     = $_POST['user_id'] ?? 0;
        $order_title = $_POST['order_title'] ?? '';
        $price       = $_POST['price'] ?? 0;
        if (!is_numeric($id) || $id <= 0) {
            echo json_encode(['code' => 400, 'msg' => '订单id非法']);
            exit;
        }
        if (!is_numeric($user_id) || $user_id <= 0 || trim($order_title) === '' || $price <= 0) {
            echo json_encode(['code' => 400, 'msg' => '参数不全或非法']);
            exit;
        }
        $id          = intval($id);
        $user_id     = intval($user_id);
        $order_title_s = mysqli_real_escape_string($conn, $order_title);
        $price       = floatval($price);
        $sql = "UPDATE order_list SET user_id=$user_id, order_title='$order_title_s', price=$price WHERE id=$id";
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['code' => 200, 'msg' => '订单修改成功']);
        } else {
            echo json_encode(['code' => 500, 'msg' => '修改失败：' . mysqli_error($conn)]);
        }
        break;

    case 'delOrder':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['code' => 405, 'msg' => '请求方式错误，请使用POST']);
            exit;
        }
        $id = $_POST['id'] ?? 0;
        if (!is_numeric($id) || $id <= 0) {
            echo json_encode(['code' => 400, 'msg' => '订单id非法']);
            exit;
        }
        $id = intval($id);
        $sql = "DELETE FROM order_list WHERE id=$id";
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['code' => 200, 'msg' => '订单删除成功']);
        } else {
            echo json_encode(['code' => 500, 'msg' => '删除失败：' . mysqli_error($conn)]);
        }
        break;

    case 'getOrder':
        $sql = "SELECT o.*, u.name as user_name 
                FROM order_list o 
                LEFT JOIN user_list u ON o.user_id = u.id 
                ORDER BY o.id DESC";
        $res = @mysqli_query($conn, $sql);
        $list = [];
        if($res){
            while ($row = mysqli_fetch_assoc($res)) {
                // 兼容字段：goods_image 字段映射
                if(!isset($row['goods_image']) && isset($row['image'])) $row['goods_image'] = $row['image'];
                if(!isset($row['goods_name']) && isset($row['order_title'])) $row['goods_name'] = $row['order_title'];
                if(!isset($row['qty'])) $row['qty'] = 1;
                if(!isset($row['unit_price'])) $row['unit_price'] = isset($row['price']) ? $row['price'] : 0;
                if(!isset($row['total_price'])) $row['total_price'] = isset($row['price']) ? $row['price'] : 0;
                if(!isset($row['status'])) $row['status'] = 1;
                if(!isset($row['pay_status'])) $row['pay_status'] = 0;
                if(!isset($row['ship_name'])) $row['ship_name'] = '';
                if(!isset($row['ship_phone'])) $row['ship_phone'] = '';
                if(!isset($row['ship_address'])) $row['ship_address'] = '';
                if(!isset($row['express_company'])) $row['express_company'] = '';
                if(!isset($row['express_no'])) $row['express_no'] = '';
                if(!isset($row['express_time'])) $row['express_time'] = '';
                $list[] = $row;
            }
        }
        echo json_encode(['code' => 200, 'msg' => '获取成功', 'data' => $list]);
        break;

    /* ================ 👆 小程序/前端 AJAX 专用接口 ================ */

    case 'getGoods':
        $_gid = intval($_GET['id'] ?? $_POST['id'] ?? 0);
        $_kw  = trim($_GET['kw'] ?? $_POST['kw'] ?? '');
        $_cat = trim($_GET['category'] ?? $_POST['category'] ?? '');
        $_where = ['1=1'];
        if (col_exists('goods_list', 'status')) $_where[] = "status=1";
        if ($_gid > 0) $_where[] = "id=$_gid";
        if ($_cat !== '') $_where[] = "category='".mysqli_real_escape_string($conn,$_cat)."'";
        if ($_kw  !== '') {
            $_k = mysqli_real_escape_string($conn, $_kw);
            $_where[] = "(goods_name LIKE '%$_k%' OR category LIKE '%$_k%' OR description LIKE '%$_k%')";
        }
        $_sort = col_exists('goods_list','sort') ? "sort ASC,id DESC" : "id DESC";
        $sql = "SELECT * FROM goods_list WHERE ".implode(' AND ',$_where)." ORDER BY $_sort";
        $res = mysqli_query($conn,$sql);
        $list = [];
        while($r = mysqli_fetch_assoc($res)){
            // 兼容字段：image <-> goods_image，小程序两边都认
            if(!isset($r['goods_image']) && isset($r['image'])) $r['goods_image'] = $r['image'];
            if(!isset($r['image'])       && isset($r['goods_image'])) $r['image'] = $r['goods_image'];
            if(!isset($r['emoji']) || $r['emoji'] === '') $r['emoji'] = '📦';
            if(!isset($r['stock'])) $r['stock'] = 999;
            if(!isset($r['category'])) $r['category'] = '';
            if(!isset($r['description'])) $r['description'] = '';
            if(!isset($r['origin_price'])) $r['origin_price'] = 0;
            if(!isset($r['is_hot'])) $r['is_hot'] = 0;
            $list[] = $r;
        }
        if($_gid>0 && !empty($list)){
            echo json_encode(['code'=>200,'msg'=>'获取成功','data'=>$list[0]]);
        }else{
            $catMap = [];
            if(table_exists('goods_list')){
                $r2 = @mysqli_query($conn,"SELECT category,COUNT(*) c FROM goods_list GROUP BY category");
                if($r2) while($r = mysqli_fetch_assoc($r2)) if(!empty($r['category'])) $catMap[$r['category']] = intval($r['c']);
            }
            echo json_encode(['code'=>200,'msg'=>'获取成功','data'=>$list,'categories'=>$catMap,'total'=>count($list)]);
        }
        break;

    case 'login':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); exit; }
        $phone = trim($_POST['phone'] ?? '');
        $pwd   = $_POST['pwd'] ?? '';
        if($phone === '' || $pwd === ''){ echo json_encode(['code'=>400,'msg'=>'手机号和密码不能为空']); exit; }
        if(!preg_match('/^1[3-9]\d{9}$/',$phone)){ echo json_encode(['code'=>400,'msg'=>'手机号格式不正确']); exit; }
        $phone = mysqli_real_escape_string($conn,$phone);
        $pwd_s = mysqli_real_escape_string($conn,$pwd);
        $sql = "SELECT * FROM user_list WHERE phone='$phone' LIMIT 1";
        $row = mysqli_fetch_assoc(mysqli_query($conn,$sql));
        if(!$row){
            echo json_encode(['code'=>400,'msg'=>'该手机号尚未注册']);
            exit;
        }
        $_has_pwd = function_exists('col_exists') && col_exists('user_list','pwd');
        $_ok = false;
        if($_has_pwd){
            $stored = $row['pwd'] ?? '';
            // 支持明文 & password_hash 双兼容
            if($stored === '' && $pwd==='123456'){ $_ok = true; }
            else if(strlen($stored)>=60 && password_verify($pwd,$stored)){ $_ok = true; }
            else if($stored === $pwd_s){ $_ok = true; }
        }else{
            // 没加 pwd 字段时，默认密码 123456 可登录
            if($pwd==='123456'){ $_ok = true; }
        }
        if(!$_ok){ echo json_encode(['code'=>400,'msg'=>'密码错误']); exit; }
        // 登录成功，生成一个简单 token
        $token = md5($row['id'].'_'.time().'_zaslon');
        $_bal = function_exists('col_exists') && col_exists('user_list','balance') ? floatval($row['balance']) : 0.00;
        $safeUser = [
            'id'       => intval($row['id']),
            'name'     => $row['name'],
            'phone'    => $row['phone'],
            'address'  => $row['address'] ?? '',
            'balance'  => $_bal,
            'create_time' => $row['create_time'] ?? ''
        ];
        echo json_encode(['code'=>200,'msg'=>'登录成功','token'=>$token,'user'=>$safeUser]);
        break;

    case 'payOrder':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); exit; }
        $user_id    = intval($_POST['user_id'] ?? 0);
        $goods_id   = intval($_POST['goods_id'] ?? 0);
        $qty        = max(1, intval($_POST['qty'] ?? 1));
        $ship_name  = trim($_POST['ship_name'] ?? '');
        $ship_phone = trim($_POST['ship_phone'] ?? '');
        $ship_addr  = trim($_POST['ship_address'] ?? '');
        if($user_id<=0){ echo json_encode(['code'=>400,'msg'=>'user_id 非法']); exit; }
        if($goods_id<=0){ echo json_encode(['code'=>400,'msg'=>'goods_id 非法']); exit; }

        // 事务保护
        mysqli_query($conn,'START TRANSACTION');
        $err = '';
        try {
            // 1. 查用户，加行锁
            $u = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM user_list WHERE id=$user_id LIMIT 1 FOR UPDATE"));
            if(!$u){ throw new Exception('用户不存在'); }
            $_has_bal = function_exists('col_exists') && col_exists('user_list','balance');
            $balance = $_has_bal ? floatval($u['balance']) : 999999;

            // 2. 查商品，加行锁
            $g = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM goods_list WHERE id=$goods_id LIMIT 1 FOR UPDATE"));
            if(!$g){ throw new Exception('商品不存在'); }
            $_has_stock = isset($g['stock']);
            if($_has_stock && intval($g['stock']) < $qty){
                throw new Exception('商品库存不足，仅剩 '.intval($g['stock']).' 件');
            }
            $price      = floatval($g['price'] ?? 0);
            $total      = round($price * $qty, 2);
            if($price<=0){ throw new Exception('商品价格异常'); }
            if($balance < $total){ throw new Exception('账户余额不足（还差 ¥'.number_format($total-$balance,2).'），请先充值'); }

            // 3. 扣余额
            if($_has_bal){
                $sql1 = "UPDATE user_list SET balance=balance-$total WHERE id=$user_id";
                if(!mysqli_query($conn,$sql1)) throw new Exception('扣款失败');
            }

            // 4. 扣库存
            if($_has_stock){
                $sql2 = "UPDATE goods_list SET stock=stock-$qty WHERE id=$goods_id";
                if(!mysqli_query($conn,$sql2)) throw new Exception('扣库存失败');
            }

            // 5. 写订单，兼容可能不存在的列
            $_cols = ['user_id','goods_id','order_title','price','qty'];
            $_vals = [$user_id,$goods_id,
                      "'".mysqli_real_escape_string($conn,$g['goods_name'])."'",
                      $total, $qty];
            $opt_cols = [
                ['goods_name', "'".mysqli_real_escape_string($conn,$g['goods_name'])."'"],
                ['goods_image',"'".mysqli_real_escape_string($conn,$g['image'] ?? '')."'"],
                ['unit_price',$price],
                ['total_price',$total],
                ['pay_status',1],
                ['pay_time','NOW()'],
                ['status',1],
                ['create_time','NOW()'],
                ['ship_name',"'".mysqli_real_escape_string($conn,$ship_name)."'"],
                ['ship_phone',"'".mysqli_real_escape_string($conn,$ship_phone)."'"],
                ['ship_address',"'".mysqli_real_escape_string($conn,$ship_addr)."'"]
            ];
            foreach($opt_cols as $c){
                if(function_exists('col_exists') && col_exists('order_list',$c[0])){
                    $_cols[] = $c[0]; $_vals[] = $c[1];
                }
            }
            $sql3 = "INSERT INTO order_list (".implode(',',$_cols).") VALUES (".implode(',',$_vals).")";
            if(!mysqli_query($conn,$sql3)) throw new Exception('生成订单失败：'.mysqli_error($conn));
            $order_id = mysqli_insert_id($conn);

            mysqli_query($conn,'COMMIT');
            echo json_encode([
                'code'=>200, 'msg'=>'支付成功，订单已提交',
                'order_id'=>$order_id,
                'total'=>$total,
                'qty'=>$qty,
                'goods_name'=>$g['goods_name'] ?? '',
                'new_balance'=> ($_has_bal ? $balance - $total : null)
            ]);
        } catch(Exception $e){
            $err = $e->getMessage();
            mysqli_query($conn,'ROLLBACK');
            echo json_encode(['code'=>400, 'msg'=>$err]);
        }
        break;

/* ================= 地址管理接口 ================= */
    case 'getAddress':
        $user_id = intval($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
        if($user_id<=0){ echo json_encode(['code'=>400,'msg'=>'user_id 非法']); break; }
        $list = [];
        if(table_exists('address_list')){
            $sql = "SELECT * FROM address_list WHERE user_id=$user_id ORDER BY is_default DESC, sort ASC, id DESC";
            $res = @mysqli_query($conn,$sql);
            if($res){
                while($r = mysqli_fetch_assoc($res)){
                    $r['region'] = [$r['province']??'',$r['city']??'',$r['district']??''];
                    $r['regionText'] = implode(' ', array_filter($r['region']));
                    $r['full_address'] = $r['regionText'].($r['detail'] ?? '');
                    $list[] = $r;
                }
            }
        }
        // 如果有 default_address_id，优先把默认地址置顶
        $defId = 0;
        if(col_exists('user_list','default_address_id')){
            $u = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT default_address_id FROM user_list WHERE id=$user_id LIMIT 1"));
            if($u) $defId = intval($u['default_address_id']);
        }
        if($defId>0){
            $sorted = []; $others = [];
            foreach($list as $a){
                if(intval($a['id']) === $defId){ $a['is_default'] = 1; $sorted[] = $a; }
                else $others[] = $a;
            }
            $list = array_merge($sorted, $others);
        }
        $defAddr = !empty($list) ? $list[0] : null;
        echo json_encode(['code'=>200,'msg'=>'获取成功','data'=>$list,'default'=>$defAddr,'default_id'=>$defId]);
        break;

    case 'addAddress':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); break; }
        $uid = intval($_POST['user_id'] ?? 0);
        if($uid<=0){ echo json_encode(['code'=>400,'msg'=>'user_id 非法']); break; }
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $prov  = trim($_POST['province'] ?? '');
        $city  = trim($_POST['city'] ?? '');
        $dist  = trim($_POST['district'] ?? '');
        $det   = trim($_POST['detail'] ?? '');
        $isDef = intval($_POST['is_default'] ?? 0);
        if($name === '' || $phone === ''){ echo json_encode(['code'=>400,'msg'=>'请填写姓名和手机号']); break; }
        if(!preg_match('/^1[3-9]\d{9}$/',$phone)){ echo json_encode(['code'=>400,'msg'=>'手机号格式不正确']); break; }
        if($prov === '' || $city === '' || $dist === ''){ echo json_encode(['code'=>400,'msg'=>'请选择完整的省/市/区']); break; }
        if($det === ''){ echo json_encode(['code'=>400,'msg'=>'请填写详细地址']); break; }
        // 如果表不存在，尝试自动建
        if(!table_exists('address_list')){
            @mysqli_query($conn,"CREATE TABLE `address_list` (
              `id` INT(11) NOT NULL AUTO_INCREMENT,
              `user_id` INT(11) NOT NULL DEFAULT 0, `name` VARCHAR(50) NOT NULL DEFAULT '',
              `phone` VARCHAR(20) NOT NULL DEFAULT '', `province` VARCHAR(50) NOT NULL DEFAULT '',
              `city` VARCHAR(50) NOT NULL DEFAULT '', `district` VARCHAR(50) NOT NULL DEFAULT '',
              `detail` VARCHAR(255) NOT NULL DEFAULT '', `is_default` TINYINT(1) NOT NULL DEFAULT 0,
              `sort` INT(11) NOT NULL DEFAULT 0, `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `update_time` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`), KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        if($isDef){
            @mysqli_query($conn,"UPDATE address_list SET is_default=0 WHERE user_id=$uid");
        }
        $sql = "INSERT INTO address_list (user_id,name,phone,province,city,district,detail,is_default) VALUES ($uid,
          '".mysqli_real_escape_string($conn,$name)."',
          '".mysqli_real_escape_string($conn,$phone)."',
          '".mysqli_real_escape_string($conn,$prov)."',
          '".mysqli_real_escape_string($conn,$city)."',
          '".mysqli_real_escape_string($conn,$dist)."',
          '".mysqli_real_escape_string($conn,$det)."',
          $isDef)";
        if(!mysqli_query($conn,$sql)){ echo json_encode(['code'=>400,'msg'=>'保存失败：'.mysqli_error($conn)]); break; }
        $newId = mysqli_insert_id($conn);
        if($isDef && col_exists('user_list','default_address_id')){
            @mysqli_query($conn,"UPDATE user_list SET default_address_id=$newId WHERE id=$uid");
        }
        echo json_encode(['code'=>200,'msg'=>'添加成功','id'=>$newId]);
        break;

    case 'editAddress':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); break; }
        $id  = intval($_POST['id'] ?? 0);
        $uid = intval($_POST['user_id'] ?? 0);
        if($id<=0 || $uid<=0){ echo json_encode(['code'=>400,'msg'=>'id 或 user_id 非法']); break; }
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $prov  = trim($_POST['province'] ?? '');
        $city  = trim($_POST['city'] ?? '');
        $dist  = trim($_POST['district'] ?? '');
        $det   = trim($_POST['detail'] ?? '');
        $isDef = intval($_POST['is_default'] ?? 0);
        if($name === '' || $phone === ''){ echo json_encode(['code'=>400,'msg'=>'请填写姓名和手机号']); break; }
        if(!preg_match('/^1[3-9]\d{9}$/',$phone)){ echo json_encode(['code'=>400,'msg'=>'手机号格式不正确']); break; }
        if($prov === '' || $city === '' || $dist === ''){ echo json_encode(['code'=>400,'msg'=>'请选择完整的省/市/区']); break; }
        if($det === ''){ echo json_encode(['code'=>400,'msg'=>'请填写详细地址']); break; }
        if($isDef){
            @mysqli_query($conn,"UPDATE address_list SET is_default=0 WHERE user_id=$uid AND id<>$id");
        }
        $sql = "UPDATE address_list SET
          name='".mysqli_real_escape_string($conn,$name)."',
          phone='".mysqli_real_escape_string($conn,$phone)."',
          province='".mysqli_real_escape_string($conn,$prov)."',
          city='".mysqli_real_escape_string($conn,$city)."',
          district='".mysqli_real_escape_string($conn,$dist)."',
          detail='".mysqli_real_escape_string($conn,$det)."',
          is_default=$isDef
          WHERE id=$id AND user_id=$uid LIMIT 1";
        if(!@mysqli_query($conn,$sql)){ echo json_encode(['code'=>400,'msg'=>'保存失败：'.mysqli_error($conn)]); break; }
        if($isDef && col_exists('user_list','default_address_id')){
            @mysqli_query($conn,"UPDATE user_list SET default_address_id=$id WHERE id=$uid");
        }
        echo json_encode(['code'=>200,'msg'=>'修改成功','id'=>$id]);
        break;

    case 'delAddress':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); break; }
        $id  = intval($_POST['id'] ?? 0);
        $uid = intval($_POST['user_id'] ?? 0);
        if($id<=0 || $uid<=0){ echo json_encode(['code'=>400,'msg'=>'id 或 user_id 非法']); break; }
        // 检查要删的是不是默认地址，若是要清 user_list.default_address_id
        $r = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT is_default FROM address_list WHERE id=$id AND user_id=$uid LIMIT 1"));
        @mysqli_query($conn,"DELETE FROM address_list WHERE id=$id AND user_id=$uid LIMIT 1");
        if($r && intval($r['is_default'])===1 && col_exists('user_list','default_address_id')){
            @mysqli_query($conn,"UPDATE user_list SET default_address_id=0 WHERE id=$uid");
        }
        echo json_encode(['code'=>200,'msg'=>'删除成功']);
        break;

    case 'setDefaultAddress':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); break; }
        $id  = intval($_POST['id'] ?? 0);
        $uid = intval($_POST['user_id'] ?? 0);
        if($id<=0 || $uid<=0){ echo json_encode(['code'=>400,'msg'=>'id 或 user_id 非法']); break; }
        @mysqli_query($conn,"UPDATE address_list SET is_default=0 WHERE user_id=$uid");
        $n = @mysqli_affected_rows($conn);
        $ok = @mysqli_query($conn,"UPDATE address_list SET is_default=1 WHERE id=$id AND user_id=$uid LIMIT 1");
        if(!$ok){ echo json_encode(['code'=>400,'msg'=>'设置失败']); break; }
        if(col_exists('user_list','default_address_id')){
            @mysqli_query($conn,"UPDATE user_list SET default_address_id=$id WHERE id=$uid");
        }
        echo json_encode(['code'=>200,'msg'=>'已设为默认']);
        break;

/* ================= 订单退款接口 ================= */
    case 'applyRefund':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); break; }
        $id   = intval($_POST['id'] ?? 0);
        $uid  = intval($_POST['user_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $remark = trim($_POST['remark'] ?? '');
        if($id<=0 || $uid<=0){ echo json_encode(['code'=>400,'msg'=>'id 或 user_id 非法']); break; }
        if($reason === ''){ echo json_encode(['code'=>400,'msg'=>'请选择退款原因']); break; }

        // 给旧表自动补退款字段
        if(!col_exists('order_list','refund_status')){
            @mysqli_query($conn,"ALTER TABLE `order_list`
              ADD COLUMN `refund_status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0未 1申请中 2已退 3拒绝',
              ADD COLUMN `refund_reason` VARCHAR(100) NOT NULL DEFAULT '',
              ADD COLUMN `refund_remark` VARCHAR(255) NOT NULL DEFAULT '',
              ADD COLUMN `refund_time` DATETIME NULL");
        }

        // 读订单（加行锁事务保护）
        mysqli_query($conn,'START TRANSACTION');
        try {
            $o = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT * FROM order_list WHERE id=$id AND user_id=$uid LIMIT 1 FOR UPDATE"));
            if(!$o){ throw new Exception('订单不存在或无权操作'); }
            $cur = intval($o['refund_status'] ?? 0);
            if($cur === 1){ throw new Exception('退款申请中，请耐心等待处理'); }
            if($cur === 2){ throw new Exception('该订单已退款，请勿重复申请'); }
            if(intval($o['pay_status'] ?? 0) !== 1){ throw new Exception('未支付的订单无法申请退款'); }

            $total = floatval($o['total_price'] ?? $o['price'] ?? 0);
            $goods_id = intval($o['goods_id'] ?? 0);
            $qty = intval($o['qty'] ?? 1);
            $remark_db = mysqli_real_escape_string($conn, $remark);
            $reason_db = mysqli_real_escape_string($conn, $reason);

            // 先设为「申请中」状态（refund_status=1），后续可在后台手动改为 2=已退款 或 3=已拒绝
            // 如果订单状态未发货（status=1），自动直接退款（退余额+退库存+状态=2）
            $st = intval($o['status'] ?? 1);
            if($st === 1){
                // 直接退款流程
                if(col_exists('user_list','balance')){
                    $ok = @mysqli_query($conn,"UPDATE user_list SET balance=balance+$total WHERE id=$uid");
                    if(!$ok) throw new Exception('退回余额失败');
                }
                if($goods_id>0 && $qty>0 && col_exists('goods_list','stock')){
                    @mysqli_query($conn,"UPDATE goods_list SET stock=stock+$qty WHERE id=$goods_id LIMIT 1");
                }
                $sql = "UPDATE order_list SET
                  refund_status=2,
                  refund_reason='$reason_db',
                  refund_remark='$remark_db',
                  refund_time=NOW()
                  WHERE id=$id AND user_id=$uid LIMIT 1";
                @mysqli_query($conn,$sql);
                mysqli_query($conn,'COMMIT');
                echo json_encode(['code'=>200,'msg'=>'退款成功，已退回余额 ¥'.number_format($total,2), 'status'=>2, 'refund_amount'=>$total]);
            }else{
                // 已发货或已完成：进入申请中状态，等待后台人工处理
                $sql = "UPDATE order_list SET
                  refund_status=1,
                  refund_reason='$reason_db',
                  refund_remark='$remark_db'
                  WHERE id=$id AND user_id=$uid LIMIT 1";
                if(!@mysqli_query($conn,$sql)) throw new Exception('提交失败');
                mysqli_query($conn,'COMMIT');
                echo json_encode(['code'=>200,'msg'=>'申请已提交，预计1-3个工作日处理完成', 'status'=>1]);
            }
        } catch(Exception $e){
            mysqli_query($conn,'ROLLBACK');
            echo json_encode(['code'=>400, 'msg'=>$e->getMessage()]);
        }
        break;

    // 后台管理员直接退款（预留接口，不做权限校验，小程序端不直接调用，未来可以做权限控制）
    case 'adminRefund':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); break; }
        $id  = intval($_POST['id'] ?? 0);
        $remark = trim($_POST['remark'] ?? '已通过审核，退款到余额');
        if($id<=0){ echo json_encode(['code'=>400,'msg'=>'id 非法']); break; }
        if(!col_exists('order_list','refund_status')){ break; }
        mysqli_query($conn,'START TRANSACTION');
        try {
            $o = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT * FROM order_list WHERE id=$id LIMIT 1 FOR UPDATE"));
            if(!$o) throw new Exception('订单不存在');
            $cur = intval($o['refund_status'] ?? 0);
            if($cur === 2) throw new Exception('订单已退款');
            $total = floatval($o['total_price'] ?? $o['price'] ?? 0);
            $uid = intval($o['user_id'] ?? 0);
            $goods_id = intval($o['goods_id'] ?? 0);
            $qty = intval($o['qty'] ?? 1);
            $r = mysqli_real_escape_string($conn,$remark);
            if($cur === 0 || $cur === 1){
                if(col_exists('user_list','balance') && $uid>0){
                    @mysqli_query($conn,"UPDATE user_list SET balance=balance+$total WHERE id=$uid");
                }
                if($goods_id>0 && $qty>0 && col_exists('goods_list','stock')){
                    @mysqli_query($conn,"UPDATE goods_list SET stock=stock+$qty WHERE id=$goods_id LIMIT 1");
                }
            }
            @mysqli_query($conn,"UPDATE order_list SET
              refund_status=2, refund_remark='$r', refund_time=NOW() WHERE id=$id LIMIT 1");
            mysqli_query($conn,'COMMIT');
            echo json_encode(['code'=>200,'msg'=>'退款成功','refund_amount'=>$total]);
        } catch(Exception $e){
            mysqli_query($conn,'ROLLBACK');
            echo json_encode(['code'=>400,'msg'=>$e->getMessage()]);
        }
        break;

/* ===========================================================
   ==================== IoT 设备管理接口 ====================
   =========================================================== */

/* ---------- ESP32 设备端专用接口 ---------- */

    // 设备上报温湿度数据
    case 'reportData':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); break; }
        $token = trim($_POST['device_token'] ?? $_GET['device_token'] ?? '');
        $temp  = $_POST['temp'] ?? null;
        $humid = $_POST['humidity'] ?? null;
        if($token === ''){ echo json_encode(['code'=>400,'msg'=>'缺少 device_token']); break; }
        if($temp === null || $humid === null){ echo json_encode(['code'=>400,'msg'=>'缺少 temp 或 humidity']); break; }
        $temp  = floatval($temp);
        $humid = floatval($humid);

        // 认证设备
        $dev = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT * FROM iot_device WHERE device_token='".mysqli_real_escape_string($conn,$token)."' LIMIT 1"));
        if(!$dev){ echo json_encode(['code'=>401,'msg'=>'设备令牌无效']); break; }
        if(intval($dev['status']) !== 1){ echo json_encode(['code'=>403,'msg'=>'设备已停用']); break; }
        $did = intval($dev['id']);

        // 判断是否触发报警
        $alarm = 0;
        $alarm_reason = '';
        $tMin = floatval($dev['temp_min']); $tMax = floatval($dev['temp_max']);
        $hMin = floatval($dev['humidity_min']); $hMax = floatval($dev['humidity_max']);
        if(intval($dev['alarm_enabled']) === 1){
            if($temp > $tMax){ $alarm = 1; $alarm_reason = '温度过高 ('.$temp.'℃ > '.$tMax.'℃)'; }
            elseif($temp < $tMin){ $alarm = 1; $alarm_reason = '温度过低 ('.$temp.'℃ < '.$tMin.'℃)'; }
            elseif($humid > $hMax){ $alarm = 1; $alarm_reason = '湿度过高 ('.$humid.'%RH > '.$hMax.'%RH)'; }
            elseif($humid < $hMin){ $alarm = 1; $alarm_reason = '湿度过低 ('.$humid.'%RH < '.$hMin.'%RH)'; }
        }

        // 写入传感器数据
        $sql = "INSERT INTO iot_sensor_data (device_id,temp,humidity,alarm,alarm_reason) VALUES ($did,$temp,$humid,$alarm,'".mysqli_real_escape_string($conn,$alarm_reason)."')";
        @mysqli_query($conn,$sql);

        // 更新设备最新数据和在线状态
        @mysqli_query($conn,"UPDATE iot_device SET last_temp=$temp,last_humidity=$humid,last_report_time=NOW(),online=1 WHERE id=$did LIMIT 1");

        // 触发报警时，写一条报警日志（若上次报警未结束则更新结束时间）
        if($alarm === 1){
            $last = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT id FROM iot_alarm_log WHERE device_id=$did AND handled=0 ORDER BY id DESC LIMIT 1"));
            if($last){
                @mysqli_query($conn,"UPDATE iot_alarm_log SET
                  temp=$temp, humidity=$humid, reason='".mysqli_real_escape_string($conn,$alarm_reason)."',
                  end_time=NOW(), duration=TIMESTAMPDIFF(SECOND,start_time,NOW())
                  WHERE id=".intval($last['id'])." LIMIT 1");
            }else{
                @mysqli_query($conn,"INSERT INTO iot_alarm_log (device_id,temp,humidity,reason,start_time) VALUES ($did,$temp,$humid,'".mysqli_real_escape_string($conn,$alarm_reason)."',NOW())");
            }
        }

        // 返回给设备：报警指令 + 配置
        echo json_encode([
            'code' => 200,
            'msg'  => '上报成功',
            'data' => [
                'alarm' => $alarm,
                'alarm_reason' => $alarm_reason,
                'alarm_duration' => intval($dev['alarm_duration']),
                'server_time' => date('Y-m-d H:i:s')
            ]
        ]);
        break;

    // 设备获取配置（阈值、报警设置等）
    case 'getDeviceConfig':
        $token = trim($_GET['device_token'] ?? $_POST['device_token'] ?? '');
        if($token === ''){ echo json_encode(['code'=>400,'msg'=>'缺少 device_token']); break; }
        $dev = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT * FROM iot_device WHERE device_token='".mysqli_real_escape_string($conn,$token)."' LIMIT 1"));
        if(!$dev){ echo json_encode(['code'=>401,'msg'=>'设备令牌无效']); break; }
        echo json_encode([
            'code' => 200,
            'msg' => '获取成功',
            'data' => [
                'device_id' => intval($dev['id']),
                'device_name' => $dev['device_name'],
                'sensor_type' => $dev['sensor_type'],
                'temp_min' => floatval($dev['temp_min']),
                'temp_max' => floatval($dev['temp_max']),
                'humidity_min' => floatval($dev['humidity_min']),
                'humidity_max' => floatval($dev['humidity_max']),
                'alarm_enabled' => intval($dev['alarm_enabled']),
                'alarm_duration' => intval($dev['alarm_duration']),
                'report_interval' => intval($dev['report_interval']),
                'server_time' => date('Y-m-d H:i:s')
            ]
        ]);
        break;

    // 设备心跳（保持在线状态）
    case 'heartbeat':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); break; }
        $token = trim($_POST['device_token'] ?? '');
        if($token === ''){ echo json_encode(['code'=>400,'msg'=>'缺少 device_token']); break; }
        $dev = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT id FROM iot_device WHERE device_token='".mysqli_real_escape_string($conn,$token)."' LIMIT 1"));
        if(!$dev){ echo json_encode(['code'=>401,'msg'=>'设备令牌无效']); break; }
        $did = intval($dev['id']);
        @mysqli_query($conn,"UPDATE iot_device SET online=1,last_report_time=NOW() WHERE id=$did LIMIT 1");
        echo json_encode(['code'=>200,'msg'=>'心跳成功','server_time'=>date('Y-m-d H:i:s')]);
        break;

    // 用户绑定设备（小程序用户通过 device_token 绑定到自己账号）
    case 'bindDevice':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); break; }
        $uid = intval($_POST['user_id'] ?? 0);
        $token = trim($_POST['device_token'] ?? '');
        if($uid <= 0){ echo json_encode(['code'=>400,'msg'=>'缺少 user_id']); break; }
        if($token === ''){ echo json_encode(['code'=>400,'msg'=>'请输入设备令牌 device_token']); break; }
        $dev = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT * FROM iot_device WHERE device_token='".mysqli_real_escape_string($conn,$token)."' LIMIT 1"));
        if(!$dev){ echo json_encode(['code'=>404,'msg'=>'设备令牌不存在，请向管理员获取']); break; }
        if(intval($dev['status']) !== 1){ echo json_encode(['code'=>403,'msg'=>'该设备已停用']); break; }
        if(intval($dev['user_id']) === $uid){ echo json_encode(['code'=>200,'msg'=>'您已绑定该设备','device_id'=>intval($dev['id'])]); break; }
        if(intval($dev['user_id']) > 0){ echo json_encode(['code'=>403,'msg'=>'该设备已被其他用户绑定']); break; }
        @mysqli_query($conn,"UPDATE iot_device SET user_id=$uid WHERE id=".intval($dev['id'])." LIMIT 1");
        echo json_encode(['code'=>200,'msg'=>'绑定成功','device_id'=>intval($dev['id']),'device_name'=>$dev['device_name']]);
        break;

    // 用户解绑设备
    case 'unbindDevice':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); break; }
        $uid = intval($_POST['user_id'] ?? 0);
        $did = intval($_POST['device_id'] ?? 0);
        if($uid <= 0 || $did <= 0){ echo json_encode(['code'=>400,'msg'=>'参数错误']); break; }
        $dev = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT user_id FROM iot_device WHERE id=$did LIMIT 1"));
        if(!$dev){ echo json_encode(['code'=>404,'msg'=>'设备不存在']); break; }
        if(intval($dev['user_id']) !== $uid){ echo json_encode(['code'=>403,'msg'=>'只能解绑自己的设备']); break; }
        @mysqli_query($conn,"UPDATE iot_device SET user_id=0 WHERE id=$did LIMIT 1");
        echo json_encode(['code'=>200,'msg'=>'已解绑']);
        break;

/* ---------- 后台/网页管理接口 ---------- */

    // 设备列表（可按 user_id 过滤）
    case 'getDeviceList':
        $uid = intval($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
        $sql = "SELECT * FROM iot_device WHERE 1=1";
        if($uid > 0) $sql .= " AND user_id=$uid";
        $sql .= " ORDER BY create_time DESC";
        $list = [];
        $res = @mysqli_query($conn,$sql);
        if($res){
            while($r = mysqli_fetch_assoc($res)){
                // 计算在线状态：最后上报超过 2*report_interval 秒视为离线
                $ri = intval($r['report_interval']) * 2;
                if($r['last_report_time']){
                    $sec = time() - strtotime($r['last_report_time']);
                    $r['online'] = ($sec < $ri && $sec >= 0) ? 1 : 0;
                }else{
                    $r['online'] = 0;
                }
                $r['temp_min'] = floatval($r['temp_min']);
                $r['temp_max'] = floatval($r['temp_max']);
                $r['humidity_min'] = floatval($r['humidity_min']);
                $r['humidity_max'] = floatval($r['humidity_max']);
                $r['last_temp'] = $r['last_temp'] === null ? null : floatval($r['last_temp']);
                $r['last_humidity'] = $r['last_humidity'] === null ? null : floatval($r['last_humidity']);
                $list[] = $r;
            }
        }
        echo json_encode(['code'=>200,'msg'=>'获取成功','data'=>$list,'total'=>count($list)]);
        break;

    // 设备详情
    case 'getDevice':
        $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
        $dno = trim($_GET['device_no'] ?? '');
        if($id <= 0 && $dno === ''){ echo json_encode(['code'=>400,'msg'=>'缺少 id 或 device_no']); break; }
        $where = $id > 0 ? "id=$id" : "device_no='".mysqli_real_escape_string($conn,$dno)."'";
        $dev = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT * FROM iot_device WHERE $where LIMIT 1"));
        if(!$dev){ echo json_encode(['code'=>404,'msg'=>'设备不存在']); break; }
        // 最新一条数据
        $latest = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT * FROM iot_sensor_data WHERE device_id=".intval($dev['id'])." ORDER BY id DESC LIMIT 1"));
        // 今日报警次数
        $today = @mysqli_fetch_row(@mysqli_query($conn,"SELECT COUNT(*) FROM iot_alarm_log WHERE device_id=".intval($dev['id'])." AND DATE(create_time)=CURDATE()"))[0];
        // 未处理报警数
        $pending = @mysqli_fetch_row(@mysqli_query($conn,"SELECT COUNT(*) FROM iot_alarm_log WHERE device_id=".intval($dev['id'])." AND handled=0"))[0];
        $dev['temp_min'] = floatval($dev['temp_min']);
        $dev['temp_max'] = floatval($dev['temp_max']);
        $dev['humidity_min'] = floatval($dev['humidity_min']);
        $dev['humidity_max'] = floatval($dev['humidity_max']);
        echo json_encode([
            'code'=>200,'msg'=>'获取成功',
            'data'=>$dev,
            'latest_data'=>$latest,
            'today_alarm_count'=>intval($today),
            'pending_alarm_count'=>intval($pending)
        ]);
        break;

    // 新增设备
    case 'addDevice':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); break; }
        $dno = trim($_POST['device_no'] ?? '');
        $name = trim($_POST['device_name'] ?? '');
        $token = trim($_POST['device_token'] ?? '');
        $uid = intval($_POST['user_id'] ?? 0);
        if($dno === ''){ echo json_encode(['code'=>400,'msg'=>'请填写设备编号 device_no']); break; }
        if($name === ''){ echo json_encode(['code'=>400,'msg'=>'请填写设备名称 device_name']); break; }
        if($token === '') $token = md5($dno.time().rand(1000,9999));
        // 检查 device_no 是否已存在
        $chk = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT id FROM iot_device WHERE device_no='".mysqli_real_escape_string($conn,$dno)."' LIMIT 1"));
        if($chk){ echo json_encode(['code'=>400,'msg'=>'该设备编号已存在']); break; }
        $sensor = trim($_POST['sensor_type'] ?? 'DHT11');
        $tmin = floatval($_POST['temp_min'] ?? -40);
        $tmax = floatval($_POST['temp_max'] ?? 85);
        $hmin = floatval($_POST['humidity_min'] ?? 0);
        $hmax = floatval($_POST['humidity_max'] ?? 100);
        $alarmEn = intval($_POST['alarm_enabled'] ?? 1);
        $alarmDur = intval($_POST['alarm_duration'] ?? 30);
        $repInt = intval($_POST['report_interval'] ?? 10);
        $remark = trim($_POST['remark'] ?? '');
        $sql = "INSERT INTO iot_device (device_no,device_name,device_token,user_id,sensor_type,temp_min,temp_max,humidity_min,humidity_max,alarm_enabled,alarm_duration,report_interval,remark) VALUES (
          '".mysqli_real_escape_string($conn,$dno)."',
          '".mysqli_real_escape_string($conn,$name)."',
          '".mysqli_real_escape_string($conn,$token)."',
          $uid,
          '".mysqli_real_escape_string($conn,$sensor)."',
          $tmin,$tmax,$hmin,$hmax,$alarmEn,$alarmDur,$repInt,
          '".mysqli_real_escape_string($conn,$remark)."'
        )";
        if(!@mysqli_query($conn,$sql)){ echo json_encode(['code'=>400,'msg'=>'新增失败：'.mysqli_error($conn)]); break; }
        $newId = mysqli_insert_id($conn);
        echo json_encode(['code'=>200,'msg'=>'新增成功','id'=>$newId,'device_token'=>$token]);
        break;

    // 修改设备
    case 'editDevice':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); break; }
        $id = intval($_POST['id'] ?? 0);
        if($id <= 0){ echo json_encode(['code'=>400,'msg'=>'缺少 id']); break; }
        $dev = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT id FROM iot_device WHERE id=$id LIMIT 1"));
        if(!$dev){ echo json_encode(['code'=>404,'msg'=>'设备不存在']); break; }
        $sets = [];
        $map = [
            'device_name'=>'device_name','device_token'=>'device_token','user_id'=>'user_id','sensor_type'=>'sensor_type',
            'temp_min'=>'temp_min','temp_max'=>'temp_max','humidity_min'=>'humidity_min','humidity_max'=>'humidity_max',
            'alarm_enabled'=>'alarm_enabled','alarm_duration'=>'alarm_duration','report_interval'=>'report_interval',
            'status'=>'status','remark'=>'remark'
        ];
        foreach($map as $postKey => $colKey){
            if(isset($_POST[$postKey])){
                $v = $_POST[$postKey];
                if(in_array($colKey,['temp_min','temp_max','humidity_min','humidity_max'])){
                    $sets[] = "`$colKey`=".floatval($v);
                }elseif(in_array($colKey,['user_id','alarm_enabled','alarm_duration','report_interval','status'])){
                    $sets[] = "`$colKey`=".intval($v);
                }else{
                    $sets[] = "`$colKey`='".mysqli_real_escape_string($conn,trim($v))."'";
                }
            }
        }
        if(empty($sets)){ echo json_encode(['code'=>400,'msg'=>'没有可修改的字段']); break; }
        $sql = "UPDATE iot_device SET ".implode(',',$sets)." WHERE id=$id LIMIT 1";
        if(!@mysqli_query($conn,$sql)){ echo json_encode(['code'=>400,'msg'=>'修改失败：'.mysqli_error($conn)]); break; }
        echo json_encode(['code'=>200,'msg'=>'修改成功']);
        break;

    // 删除设备（同时删除其传感器数据和报警日志）
    case 'delDevice':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); break; }
        $id = intval($_POST['id'] ?? 0);
        if($id <= 0){ echo json_encode(['code'=>400,'msg'=>'缺少 id']); break; }
        @mysqli_query($conn,"DELETE FROM iot_device WHERE id=$id LIMIT 1");
        @mysqli_query($conn,"DELETE FROM iot_sensor_data WHERE device_id=$id");
        @mysqli_query($conn,"DELETE FROM iot_alarm_log WHERE device_id=$id");
        echo json_encode(['code'=>200,'msg'=>'删除成功（含相关数据）']);
        break;

    // 设置温湿度阈值
    case 'setThreshold':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); break; }
        $id = intval($_POST['id'] ?? 0);
        if($id <= 0){ echo json_encode(['code'=>400,'msg'=>'缺少 id']); break; }
        $dev = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT id FROM iot_device WHERE id=$id LIMIT 1"));
        if(!$dev){ echo json_encode(['code'=>404,'msg'=>'设备不存在']); break; }
        $tmin = isset($_POST['temp_min']) ? floatval($_POST['temp_min']) : null;
        $tmax = isset($_POST['temp_max']) ? floatval($_POST['temp_max']) : null;
        $hmin = isset($_POST['humidity_min']) ? floatval($_POST['humidity_min']) : null;
        $hmax = isset($_POST['humidity_max']) ? floatval($_POST['humidity_max']) : null;
        $sets = [];
        if($tmin !== null) $sets[] = "temp_min=$tmin";
        if($tmax !== null) $sets[] = "temp_max=$tmax";
        if($hmin !== null) $sets[] = "humidity_min=$hmin";
        if($hmax !== null) $sets[] = "humidity_max=$hmax";
        if(empty($sets)){ echo json_encode(['code'=>400,'msg'=>'请至少传一个阈值参数']); break; }
        if($tmin !== null && $tmax !== null && $tmin >= $tmax){ echo json_encode(['code'=>400,'msg'=>'温度下限必须小于上限']); break; }
        if($hmin !== null && $hmax !== null && $hmin >= $hmax){ echo json_encode(['code'=>400,'msg'=>'湿度下限必须小于上限']); break; }
        @mysqli_query($conn,"UPDATE iot_device SET ".implode(',',$sets)." WHERE id=$id LIMIT 1");
        echo json_encode(['code'=>200,'msg'=>'阈值设置成功']);
        break;

    // 设置报警配置
    case 'setAlarmConfig':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); break; }
        $id = intval($_POST['id'] ?? 0);
        if($id <= 0){ echo json_encode(['code'=>400,'msg'=>'缺少 id']); break; }
        $alarmEn = isset($_POST['alarm_enabled']) ? intval($_POST['alarm_enabled']) : null;
        $alarmDur = isset($_POST['alarm_duration']) ? intval($_POST['alarm_duration']) : null;
        $repInt = isset($_POST['report_interval']) ? intval($_POST['report_interval']) : null;
        $sets = [];
        if($alarmEn !== null) $sets[] = "alarm_enabled=$alarmEn";
        if($alarmDur !== null) $sets[] = "alarm_duration=$alarmDur";
        if($repInt !== null) $sets[] = "report_interval=$repInt";
        if(empty($sets)){ echo json_encode(['code'=>400,'msg'=>'请至少传一个参数']); break; }
        @mysqli_query($conn,"UPDATE iot_device SET ".implode(',',$sets)." WHERE id=$id LIMIT 1");
        echo json_encode(['code'=>200,'msg'=>'报警配置已更新']);
        break;

    // 查询温湿度历史数据（支持分页 + 时间范围）
    case 'getSensorData':
        $did = intval($_GET['device_id'] ?? $_POST['device_id'] ?? 0);
        if($did <= 0){ echo json_encode(['code'=>400,'msg'=>'缺少 device_id']); break; }
        $start = trim($_GET['start'] ?? '');
        $end   = trim($_GET['end'] ?? '');
        $page  = max(1, intval($_GET['page'] ?? 1));
        $limit = min(500, max(1, intval($_GET['limit'] ?? 100)));
        $offset = ($page - 1) * $limit;
        $where = "device_id=$did";
        if($start !== '') $where .= " AND create_time >= '".mysqli_real_escape_string($conn,$start)."'";
        if($end !== '')   $where .= " AND create_time <= '".mysqli_real_escape_string($conn,$end)."'";
        $total = @mysqli_fetch_row(@mysqli_query($conn,"SELECT COUNT(*) FROM iot_sensor_data WHERE $where"))[0];
        $sql = "SELECT * FROM iot_sensor_data WHERE $where ORDER BY create_time DESC LIMIT $limit OFFSET $offset";
        $list = [];
        $res = @mysqli_query($conn,$sql);
        if($res){
            while($r = mysqli_fetch_assoc($res)){
                $r['temp'] = floatval($r['temp']);
                $r['humidity'] = floatval($r['humidity']);
                $list[] = $r;
            }
        }
        echo json_encode([
            'code'=>200,'msg'=>'获取成功',
            'data'=>array_reverse($list),
            'total'=>intval($total),
            'page'=>$page,
            'limit'=>$limit,
            'total_page'=>ceil($total/$limit)
        ]);
        break;

    // 查询报警日志
    case 'getAlarmLog':
        $did = intval($_GET['device_id'] ?? $_POST['device_id'] ?? 0);
        $handled = isset($_GET['handled']) ? intval($_GET['handled']) : -1;
        $page  = max(1, intval($_GET['page'] ?? 1));
        $limit = min(200, max(1, intval($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;
        $where = "1=1";
        if($did > 0) $where .= " AND device_id=$did";
        if($handled >= 0) $where .= " AND handled=$handled";
        $total = @mysqli_fetch_row(@mysqli_query($conn,"SELECT COUNT(*) FROM iot_alarm_log WHERE $where"))[0];
        $sql = "SELECT a.*, d.device_name FROM iot_alarm_log a LEFT JOIN iot_device d ON a.device_id=d.id WHERE $where ORDER BY a.create_time DESC LIMIT $limit OFFSET $offset";
        $list = [];
        $res = @mysqli_query($conn,$sql);
        if($res){
            while($r = mysqli_fetch_assoc($res)){
                $r['temp'] = $r['temp'] === null ? null : floatval($r['temp']);
                $r['humidity'] = $r['humidity'] === null ? null : floatval($r['humidity']);
                $list[] = $r;
            }
        }
        echo json_encode([
            'code'=>200,'msg'=>'获取成功',
            'data'=>$list,
            'total'=>intval($total),
            'page'=>$page,
            'limit'=>$limit,
            'total_page'=>ceil($total/$limit)
        ]);
        break;

    // 处理报警（标记已处理）
    case 'handleAlarm':
        if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['code'=>405,'msg'=>'请使用POST']); break; }
        $id = intval($_POST['id'] ?? 0);
        $remark = trim($_POST['handle_remark'] ?? '已处理');
        if($id <= 0){ echo json_encode(['code'=>400,'msg'=>'缺少 id']); break; }
        $sql = "UPDATE iot_alarm_log SET handled=1, handle_time=NOW(), handle_remark='".mysqli_real_escape_string($conn,$remark)."' WHERE id=$id LIMIT 1";
        if(!@mysqli_query($conn,$sql)){ echo json_encode(['code'=>400,'msg'=>'处理失败']); break; }
        echo json_encode(['code'=>200,'msg'=>'已标记为已处理']);
        break;

    // 设备统计概览（给仪表盘用）
    case 'getDeviceStats':
        $did = intval($_GET['device_id'] ?? $_POST['device_id'] ?? 0);
        if($did <= 0){ echo json_encode(['code'=>400,'msg'=>'缺少 device_id']); break; }
        $dev = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT * FROM iot_device WHERE id=$did LIMIT 1"));
        if(!$dev){ echo json_encode(['code'=>404,'msg'=>'设备不存在']); break; }
        // 今日数据量
        $todayData = intval(@mysqli_fetch_row(@mysqli_query($conn,"SELECT COUNT(*) FROM iot_sensor_data WHERE device_id=$did AND DATE(create_time)=CURDATE()"))[0]);
        // 今日报警次数
        $todayAlarm = intval(@mysqli_fetch_row(@mysqli_query($conn,"SELECT COUNT(*) FROM iot_alarm_log WHERE device_id=$did AND DATE(create_time)=CURDATE()"))[0]);
        // 总报警次数
        $totalAlarm = intval(@mysqli_fetch_row(@mysqli_query($conn,"SELECT COUNT(*) FROM iot_alarm_log WHERE device_id=$did"))[0]);
        // 未处理报警
        $pendingAlarm = intval(@mysqli_fetch_row(@mysqli_query($conn,"SELECT COUNT(*) FROM iot_alarm_log WHERE device_id=$did AND handled=0"))[0]);
        // 最近24小时平均温湿度
        $avg = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT AVG(temp) t, AVG(humidity) h FROM iot_sensor_data WHERE device_id=$did AND create_time >= DATE_SUB(NOW(),INTERVAL 24 HOUR)"));
        // 最高最低温湿度（24h内）
        $range = @mysqli_fetch_assoc(@mysqli_query($conn,"SELECT MAX(temp) tmax, MIN(temp) tmin, MAX(humidity) hmax, MIN(humidity) hmin FROM iot_sensor_data WHERE device_id=$did AND create_time >= DATE_SUB(NOW(),INTERVAL 24 HOUR)"));
        echo json_encode([
            'code'=>200,'msg'=>'获取成功',
            'data'=>[
                'device_name'=>$dev['device_name'],
                'online'=>intval($dev['online']),
                'last_temp'=>floatval($dev['last_temp']),
                'last_humidity'=>floatval($dev['last_humidity']),
                'last_report_time'=>$dev['last_report_time'],
                'today_data_count'=>$todayData,
                'today_alarm_count'=>$todayAlarm,
                'total_alarm_count'=>$totalAlarm,
                'pending_alarm_count'=>$pendingAlarm,
                'avg_temp_24h'=>$avg ? round(floatval($avg['t']),2) : null,
                'avg_humidity_24h'=>$avg ? round(floatval($avg['h']),2) : null,
                'temp_max_24h'=>$range ? round(floatval($range['tmax']),2) : null,
                'temp_min_24h'=>$range ? round(floatval($range['tmin']),2) : null,
                'humidity_max_24h'=>$range ? round(floatval($range['hmax']),2) : null,
                'humidity_min_24h'=>$range ? round(floatval($range['hmin']),2) : null
            ]
        ]);
        break;

    default:
        echo json_encode([
            'code' => 404,
            'msg'  => '接口不存在',
            '服务器信息' => [
                '当前脚本路径' => __FILE__,
                '已加载config' => $_cfg,
                'PHP版本' => PHP_VERSION
            ],
            '可用接口' => [
                'getUser (GET)'           => '获取全部用户',
                'addUser (POST)'          => '新增用户：name,phone,address',
                'editUser (POST)'         => '修改用户：id,name,phone,address',
                'delUser (POST)'          => '删除用户：id',
                'getUserWithOrder (GET)'  => '用户+关联订单：user_id',
                'getOrder (GET)'          => '获取全部订单(含用户名)',
                'addOrder (POST)'         => '新增订单：user_id,order_title,price',
                'editOrder (POST)'        => '修改订单：id,user_id,order_title,price',
                'delOrder (POST)'         => '删除订单：id',
                '➖➖ 小程序专用 ➖➖'     => '========',
                'getGoods (GET/POST)'     => '商品列表：可选 id/kw/category 筛选，返回 categories 分类统计',
                'login (POST)'            => '用户登录：手机号 phone + 密码 pwd；返回 token + user 信息',
                'payOrder (POST)'         => '余额购买：user_id,goods_id,qty,[ship_name/phone/address]；事务扣余额+库存+生成订单',
                '➖➖ IoT 设备端 ➖➖'    => '========',
                'reportData (POST)'       => 'ESP32上报数据：device_token, temp, humidity；返回是否触发报警',
                'getDeviceConfig (GET)'   => '设备拉取配置：device_token；返回阈值/报警时长/上报间隔',
                'heartbeat (POST)'        => '设备心跳保活：device_token',
                '➖➖ IoT 后台管理 ➖➖' => '========',
                'getDeviceList (GET)'     => '设备列表（可传 user_id 过滤）',
                'getDevice (GET)'         => '设备详情：id 或 device_no',
                'addDevice (POST)'        => '新增设备：device_no,device_name,可选阈值/报警配置',
                'editDevice (POST)'       => '修改设备：id + 任意可改字段',
                'delDevice (POST)'        => '删除设备（级联删除数据和报警日志）',
                'setThreshold (POST)'     => '设置温湿度阈值：id, temp_min/max, humidity_min/max',
                'setAlarmConfig (POST)'   => '设置报警配置：id, alarm_enabled, alarm_duration, report_interval',
                'getSensorData (GET)'     => '温湿度历史数据：device_id, 可选 start/end/page/limit',
                'getAlarmLog (GET)'       => '报警日志列表：可选 device_id/handled/page/limit',
                'handleAlarm (POST)'      => '处理报警：id, handle_remark',
                'getDeviceStats (GET)'    => '设备统计概览：device_id（24h平均/极值/报警次数）'
            ]
        ]);
        break;
}
