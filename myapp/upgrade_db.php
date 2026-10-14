<?php
/**
 * 一键数据库升级工具
 * 用法：上传到网站根目录 → 浏览器访问 upgrade_db.php → 看提示
 * 执行完强烈建议删除此文件！
 */
header('Content-Type: text/html; charset=utf-8');
include __DIR__ . '/config.php';

echo '<style>body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f1f5f9;padding:30px;color:#1e293b;}
.wrap{max-width:900px;margin:0 auto;background:#fff;padding:30px 34px;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,0.08);}
h1{margin:0 0 16px;font-size:22px;color:#1e293b;}
.ok{background:#dcfce7;color:#166534;border-left:4px solid #10b981;padding:10px 14px;margin:8px 0;border-radius:8px;font-size:14px;}
.skip{background:#f1f5f9;color:#64748b;border-left:4px solid #94a3b8;padding:10px 14px;margin:8px 0;border-radius:8px;font-size:14px;}
.err{background:#fee2e2;color:#b91c1c;border-left:4px solid #ef4444;padding:10px 14px;margin:8px 0;border-radius:8px;font-size:14px;}
h2{font-size:16px;color:#475569;margin-top:24px;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;}
.sum{margin-top:24px;padding:16px 18px;background:#eff6ff;border-radius:10px;border-left:4px solid #667eea;font-size:14px;line-height:2;}
.sum b{color:#4f46e5;}
.danger{background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:10px;margin-top:20px;font-size:13px;line-height:1.7;}
code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:12.5px;}
.btn{display:inline-block;padding:10px 20px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;text-decoration:none;border-radius:8px;font-weight:700;margin-top:18px;}
.btn:hover{transform:translateY(-1px);}</style>';

echo '<div class="wrap">';
echo '<h1>🛠️ Zaslon 商城数据库一键升级工具</h1>';

$added = $skipped = $failed = 0;

/* =====================================================
   工具函数：安全地给某张表加字段（不存在才加，否则跳过）
   ===================================================== */
function add_col_safe($table, $col, $def){
    global $conn, $added, $skipped, $failed;
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$col'");
    if(mysqli_num_rows($res) > 0){
        echo '<div class="skip">⏭️ 跳过：表 `'.$table.'` 字段 `'.$col.'` 已存在</div>';
        $skipped++;
        return true;
    }
    $sql = "ALTER TABLE `$table` ADD COLUMN $def";
    if(mysqli_query($conn, $sql)){
        echo '<div class="ok">✅ 成功：表 `'.$table.'` 新增字段 `'.$col.'`</div>';
        $added++;
        return true;
    }else{
        echo '<div class="err">❌ 失败：表 `'.$table.'` 加字段 `'.$col.'` 失败：'.mysqli_error($conn).'<br><code style="background:#fee;">'.$sql.'</code></div>';
        $failed++;
        return false;
    }
}

/* =====================================================
   工具函数：安全修改字段类型（只在类型不是目标时才改）
   用法：modify_col_safe('goods_list','image','TEXT NOT NULL')
   ===================================================== */
function modify_col_safe($table, $col, $new_def, $comment=''){
    global $conn, $added, $skipped, $failed;
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$col'");
    if(!$res || mysqli_num_rows($res)==0){
        echo '<div class="skip">⏭️ 跳过（字段不存在）：表 `'.$table.'`.`'.$col.'`</div>';
        $skipped++;
        return false;
    }
    $row = mysqli_fetch_assoc($res);
    $cur_type = strtolower($row['Type']);
    $target_type = strtolower(explode(' ',trim($new_def))[0]);
    if(strpos($cur_type,'text')!==false || strpos($cur_type,'mediumtext')!==false){
        echo '<div class="skip">⏭️ 跳过：表 `'.$table.'`.`'.$col.'` 已是 TEXT 类型（当前 '.$cur_type.'）</div>';
        $skipped++;
        return true;
    }
    $sql = "ALTER TABLE `$table` MODIFY COLUMN `$col` $new_def";
    if($comment) $sql .= " COMMENT '$comment'";
    if(mysqli_query($conn,$sql)){
        echo '<div class="ok">✅ 成功：表 `'.$table.'`.`'.$col.'` 类型已从 `'.$cur_type.'` 升级为 '.$target_type.'（再也不怕长URL啦）</div>';
        $added++;
        return true;
    }else{
        echo '<div class="err">❌ 失败：修改字段类型 `'.$table.'`.`'.$col.'` 失败：'.mysqli_error($conn).'<br><code>'.$sql.'</code></div>';
        $failed++;
        return false;
    }
}

/* =====================================================
   工具函数：安全建表（不存在才建）
   ===================================================== */
function create_table_safe($table, $sql){
    global $conn, $added, $skipped, $failed;
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if(mysqli_num_rows($res) > 0){
        echo '<div class="skip">⏭️ 跳过：表 `'.$table.'` 已存在</div>';
        $skipped++;
        return true;
    }
    if(mysqli_query($conn, $sql)){
        echo '<div class="ok">✅ 成功：新建表 `'.$table.'`</div>';
        $added++;
        return true;
    }else{
        echo '<div class="err">❌ 失败：建表 `'.$table.'` 失败：'.mysqli_error($conn).'</div>';
        $failed++;
        return false;
    }
}

/* ============ 1. user_list ============ */
echo '<h2>👥 处理：user_list 用户表</h2>';
add_col_safe('user_list','pwd',"`pwd` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '登录密码' AFTER `address`");
add_col_safe('user_list','balance',"`balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '账户余额'");

/* ============ 2. order_list ============ */
echo '<h2>🧾 处理：order_list 订单表</h2>';
add_col_safe('order_list','goods_id',    "`goods_id` INT(11) NOT NULL DEFAULT 0 COMMENT '关联商品ID'");
add_col_safe('order_list','goods_name',  "`goods_name` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '下单时商品名称快照'");
add_col_safe('order_list','goods_image', "`goods_image` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '下单时商品图快照'");
add_col_safe('order_list','qty',         "`qty` INT(11) NOT NULL DEFAULT 1 COMMENT '购买数量'");
add_col_safe('order_list','unit_price',  "`unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '下单时单价'");
add_col_safe('order_list','total_price', "`total_price` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '订单总价'");
add_col_safe('order_list','pay_status',  "`pay_status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '支付状态：0未付 1已付'");
add_col_safe('order_list','pay_time',    "`pay_time` DATETIME NULL COMMENT '支付时间'");
add_col_safe('order_list','status',      "`status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '订单状态：1已下单 2发货中 3已完成 4已取消' AFTER `pay_time`");
add_col_safe('order_list','order_title', "`order_title` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '订单标题（兼容旧接口）' AFTER `price`");
echo '<h3 style="margin:14px 0 6px 0;color:#64748b;font-size:14px;">—— 🚚 物流相关字段 ——</h3>';
add_col_safe('order_list','ship_name',    "`ship_name`    VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '收货人姓名'  AFTER `status`");
add_col_safe('order_list','ship_phone',   "`ship_phone`   VARCHAR(20)  NOT NULL DEFAULT '' COMMENT '收货人电话'  AFTER `ship_name`");
add_col_safe('order_list','ship_address', "`ship_address` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '收货地址'     AFTER `ship_phone`");
add_col_safe('order_list','express_company',"`express_company` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '快递公司 顺丰/圆通...' AFTER `ship_address`");
add_col_safe('order_list','express_no',   "`express_no`   VARCHAR(80)  NOT NULL DEFAULT '' COMMENT '运单号'       AFTER `express_company`");
add_col_safe('order_list','express_time', "`express_time` DATETIME     NULL COMMENT '发货时间'                   AFTER `express_no`");
add_col_safe('order_list','express_note', "`express_note` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '物流备注'     AFTER `express_time`");
echo '<h3 style="margin:14px 0 6px 0;color:#64748b;font-size:14px;">—— 💸 退款相关字段 ——</h3>';
add_col_safe('order_list','refund_status', "`refund_status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '退款状态：0未申请 1申请中 2已退款 3已拒绝' AFTER `express_note`");
add_col_safe('order_list','refund_reason', "`refund_reason` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '退款原因（用户选的）' AFTER `refund_status`");
add_col_safe('order_list','refund_remark', "`refund_remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '用户补充备注 / 管理员回复' AFTER `refund_reason`");
add_col_safe('order_list','refund_time',   "`refund_time`   DATETIME     NULL COMMENT '退款完成时间' AFTER `refund_remark`");
modify_col_safe('order_list','goods_image',"TEXT NOT NULL",'下单时商品图快照（支持长URL/base64）');

/* ============ 3. goods_list 建表 ============ */
echo '<h2>📦 处理：goods_list 商品表</h2>';
create_table_safe('goods_list',"CREATE TABLE `goods_list` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `goods_name`  VARCHAR(100) NOT NULL DEFAULT '' COMMENT '商品名称',
  `category`    VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '分类',
  `price`       DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '售价',
  `origin_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '原价',
  `stock`       INT(11) NOT NULL DEFAULT 0 COMMENT '库存',
  `emoji`       VARCHAR(10)  NOT NULL DEFAULT '📦' COMMENT 'emoji图标',
  `image`       TEXT         NOT NULL COMMENT '商品图片URL',
  `goods_image` TEXT         NOT NULL COMMENT '小程序商品图片别名（同 image）',
  `description` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '简介',
  `content`     TEXT         NULL COMMENT '详情',
  `sort`        INT(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `is_hot`      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否热销',
  `status`      TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态 0下架 1在售',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`),
  KEY `idx_status_sort` (`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商城商品表'");
modify_col_safe('goods_list','image',"TEXT NOT NULL",'商品图片URL/Base64（升级为TEXT支持超长链接）');
// 给旧版 goods_list 表补 goods_image 字段（小程序可能会用到这个别名）
add_col_safe('goods_list','goods_image',"`goods_image` TEXT NOT NULL COMMENT '小程序图片别名（冗余字段，等于image）' AFTER `image`");
// goods_list 的 goods_name 字段确保有默认值
$res = mysqli_query($conn, "SHOW COLUMNS FROM `goods_list` LIKE 'goods_name'");
if($res && mysqli_num_rows($res)>0){
    $r = mysqli_fetch_assoc($res);
    if(stripos($r['Default'] ?? '', 'DEFAULT')===false && $r['Null'] !== 'YES'){
        @mysqli_query($conn, "ALTER TABLE `goods_list` MODIFY COLUMN `goods_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '商品名称'");
    }
}

/* ============ 4. address_list 地址表 ============ */
echo '<h2>📍 处理：address_list 收货地址表</h2>';
create_table_safe('address_list',"CREATE TABLE `address_list` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`      INT(11) NOT NULL DEFAULT 0 COMMENT '所属用户ID',
  `name`         VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '收货人姓名',
  `phone`        VARCHAR(20)  NOT NULL DEFAULT '' COMMENT '收货人电话',
  `province`     VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '省',
  `city`         VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '市',
  `district`     VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '区/县',
  `detail`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '详细地址（街道楼栋门牌）',
  `is_default`   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '是否默认：0否 1是',
  `sort`         INT(11)      NOT NULL DEFAULT 0 COMMENT '排序',
  `create_time`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time`  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_default` (`user_id`,`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户收货地址表'");

/* ============ 4.5 user_list 加 default_address_id ============ */
echo '<h3 style="margin:14px 0 6px 0;color:#64748b;font-size:14px;">—— 👤 用户表加默认地址字段 ——</h3>';
add_col_safe('user_list','default_address_id',"`default_address_id` INT(11) NOT NULL DEFAULT 0 COMMENT '默认收货地址ID' AFTER `balance`");

/* ============ 5. recharge_log 建表 ============ */
echo '<h2>💰 处理：recharge_log 充值日志表</h2>';
create_table_safe('recharge_log',"CREATE TABLE `recharge_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`        INT(11) NOT NULL COMMENT '用户ID',
  `amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '充值金额',
  `balance_before` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '到账前余额',
  `balance_after`  DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '到账后余额',
  `operator`       VARCHAR(50) NOT NULL DEFAULT '' COMMENT '操作管理员',
  `remark`         VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `status`         TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态 1到账',
  `create_time`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值日志表'");

/* ============ 5. goods_list 示例数据 ============ */
echo '<h2>🎁 可选：商品示例数据（仅在商品表为空时插入）</h2>';
$chk = mysqli_fetch_row(mysqli_query($conn,'SELECT COUNT(*) FROM goods_list'))[0] ?? 0;
if($chk == 0){
    $samples = [
        ["'iPhone 15 Pro'",  "'数码'",   7999.00, 8999.00, 50,  "'📱'", "'💎 256GB 原色钛金属'", 1, 1],
        ["'AirPods Pro 2'",  "'数码'",   1599.00, 1899.00, 120, "'🎧'", "'主动降噪 / USB-C 接口'",     2, 1],
        ["'纯棉短袖T恤'",    "'服饰'",   99.00,   199.00,   500, "'👕'", "'100%纯棉，多色可选'",        3, 0],
        ["'新疆西梅干 500g'","'食品'",   39.90,   69.90,    200, "'🍇'", "'自然风干 无添加'",            4, 1],
        ["'蓝牙机械键盘'",    "'数码'",   399.00,  499.00,   80,  "'⌨️'", "'青轴 / RGB / 三模'",         5, 1],
    ];
    $ok_count = 0;
    foreach($samples as $s){
        $sql = "INSERT INTO goods_list(goods_name,category,price,origin_price,stock,emoji,description,sort,is_hot,status,create_time) VALUES ($s[0],$s[1],$s[2],$s[3],$s[4],$s[5],$s[6],$s[7],$s[8],1,NOW())";
        if(mysqli_query($conn,$sql)) $ok_count++;
    }
    if($ok_count>0){
        echo '<div class="ok">✅ 成功为商品表新增 '.$ok_count.' 条示例数据（方便你立刻体验）</div>';
        $added++;
    }
}else{
    echo '<div class="skip">⏭️ 跳过：goods_list 表已有 '.$chk.' 条数据，不插入示例</div>';
    $skipped++;
}

/* ============ 6. iot_device 设备表 ============ */
echo '<h2>📡 处理：iot_device ESP32 设备表</h2>';
create_table_safe('iot_device',"CREATE TABLE `iot_device` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `device_no`     VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '设备唯一编号（MAC/芯片ID）',
  `device_name`   VARCHAR(100) NOT NULL DEFAULT '' COMMENT '设备名称（用户自定义）',
  `device_token`  VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '设备上报数据时的认证令牌',
  `user_id`       INT(11)      NOT NULL DEFAULT 0  COMMENT '绑定的用户ID',
  `sensor_type`   VARCHAR(30)  NOT NULL DEFAULT 'DHT11' COMMENT '传感器型号 DHT11/DHT22',
  `temp_min`      DECIMAL(6,2) NOT NULL DEFAULT -40.00 COMMENT '温度下限阈值（低于则报警）',
  `temp_max`      DECIMAL(6,2) NOT NULL DEFAULT 85.00  COMMENT '温度上限阈值（高于则报警）',
  `humidity_min`  DECIMAL(6,2) NOT NULL DEFAULT 0.00   COMMENT '湿度下限阈值',
  `humidity_max`  DECIMAL(6,2) NOT NULL DEFAULT 100.00 COMMENT '湿度上限阈值',
  `alarm_enabled` TINYINT(1)   NOT NULL DEFAULT 1  COMMENT '是否启用报警 0关 1开',
  `alarm_duration` INT(11)     NOT NULL DEFAULT 30 COMMENT '蜂鸣器报警持续时间（秒）',
  `report_interval` INT(11)    NOT NULL DEFAULT 10 COMMENT '数据上报间隔（秒）',
  `online`        TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '是否在线 0离线 1在线',
  `last_temp`     DECIMAL(6,2) NULL COMMENT '最新温度',
  `last_humidity` DECIMAL(6,2) NULL COMMENT '最新湿度',
  `last_report_time` DATETIME  NULL COMMENT '最后一次上报时间',
  `status`        TINYINT(1)   NOT NULL DEFAULT 1  COMMENT '状态 1启用 0停用',
  `remark`        VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time`   DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_device_no` (`device_no`),
  UNIQUE KEY `uk_device_token` (`device_token`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IoT 设备表（ESP32 温湿度监控）'");

/* ============ 7. iot_sensor_data 温湿度数据表 ============ */
echo '<h2>🌡️ 处理：iot_sensor_data 温湿度数据表</h2>';
create_table_safe('iot_sensor_data',"CREATE TABLE `iot_sensor_data` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `device_id`  INT(11)      NOT NULL COMMENT '设备ID',
  `temp`       DECIMAL(6,2) NULL COMMENT '温度 ℃',
  `humidity`   DECIMAL(6,2) NULL COMMENT '湿度 %RH',
  `alarm`      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '本次数据是否触发报警 0否 1是',
  `alarm_reason` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '报警原因',
  `create_time` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_device_time` (`device_id`,`create_time`),
  KEY `idx_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IoT 传感器数据表（温湿度历史）'");

/* ============ 8. iot_alarm_log 报警日志表 ============ */
echo '<h2>🔔 处理：iot_alarm_log 报警日志表</h2>';
create_table_safe('iot_alarm_log',"CREATE TABLE `iot_alarm_log` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `device_id`  INT(11)      NOT NULL COMMENT '设备ID',
  `temp`       DECIMAL(6,2) NULL COMMENT '报警时温度',
  `humidity`   DECIMAL(6,2) NULL COMMENT '报警时湿度',
  `reason`     VARCHAR(100) NOT NULL DEFAULT '' COMMENT '报警原因',
  `start_time` DATETIME     NOT NULL COMMENT '报警开始时间',
  `end_time`   DATETIME     NULL COMMENT '报警结束时间',
  `duration`   INT(11)      NOT NULL DEFAULT 0 COMMENT '持续秒数',
  `handled`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '是否已处理 0否 1是',
  `handle_time` DATETIME    NULL COMMENT '处理时间',
  `handle_remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '处理备注',
  `create_time` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_device` (`device_id`),
  KEY `idx_handled` (`handled`),
  KEY `idx_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IoT 报警日志表'");

/* ============ 汇总 ============ */
echo '<div class="sum">
<b>🎉 升级完成！汇总结果：</b><br>
✅ 新增 / 成功：<b>'.$added.'</b> 项<br>
⏭️ 跳过（已存在）：<b>'.$skipped.'</b> 项<br>
❌ 失败：<b>'.$failed.'</b> 项<br>';
if($failed>0){
    echo '<span style="color:#ef4444;">⚠️ 存在失败项，请截图上方红色错误信息发送给开发者</span><br>';
}else{
    echo '<span style="color:#10b981;">💯 数据库升级完美成功！现在可以开始使用啦～</span><br>';
}
echo '</div>';

echo '<div class="danger">
<b>⚠️ 安全提示：数据库升级完成后，请立即删除本文件！</b><br>
路径：<code>/www/wwwroot/123.56.72.218/upgrade_db.php</code>（宝塔里找到删掉即可）<br>
或者重命名为 <code>upgrade_db_done_'.rand(1000,9999).'.php</code> 防止被恶意访问。
</div>';

echo '<p>
<a class="btn" href="shop.php" target="_blank">🛒 进入商城首页</a>
<a class="btn" href="admin/login.php" target="_blank" style="margin-left:10px;background:linear-gradient(135deg,#11998e,#38ef7d);">🛡️ 进入后台管理</a>
<a class="btn" href="user_center.php" target="_blank" style="margin-left:10px;background:linear-gradient(135deg,#f6d365,#fda085);">👤 个人中心</a>
</p>';

echo '</div>';
