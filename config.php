<?php
/**
 * StackStation 核心配置 - 增加销毁策略
 */

// 1. 数据库设置
define('DB_HOST', 'localhost');
define('DB_USER', '填写数据库用户名');  
define('DB_PASS', '填写数据库密码');  
define('DB_NAME', '填写数据库名称');  

// 2. 存储与路径
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');

// 3. 销毁策略 (默认保留时间，单位：秒)
// 管理员可在数据库 settings 表中修改此值
define('DEFAULT_IMG_TTL', 3600 * 24); // 默认 24 小时

date_default_timezone_set('Asia/Shanghai');
?>