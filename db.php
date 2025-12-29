<?php
require_once 'config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 用户表
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'user') DEFAULT 'user'
    ) ENGINE=InnoDB");

    // 文件表
    $pdo->exec("CREATE TABLE IF NOT EXISTS files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        path VARCHAR(255) NOT NULL,
        size VARCHAR(50) NOT NULL,
        downloads INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // 增强图库表：增加 expires_at 过期时间
    $pdo->exec("CREATE TABLE IF NOT EXISTS images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        path VARCHAR(255) NOT NULL,
        uploader_ip VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME
    ) ENGINE=InnoDB");

    // 系统配置表
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        s_key VARCHAR(50) PRIMARY KEY,
        s_value VARCHAR(255)
    ) ENGINE=InnoDB");

    // 初始化管理员和默认配置
    if (!$pdo->query("SELECT id FROM users LIMIT 1")->fetch()) {
        $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')")
            ->execute([INIT_ADMIN_USER, INIT_ADMIN_PASS]);
        $pdo->prepare("INSERT INTO settings (s_key, s_value) VALUES ('img_ttl', ?)")
            ->execute([DEFAULT_IMG_TTL]);
    }

    if (!file_exists(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);

} catch (Exception $e) {
    die("数据库连接失败: " . $e->getMessage());
}
?>