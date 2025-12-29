<?php
/**
 * StackStation Backend API
 * 默认管理员凭据: admin / stack123456
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

// 模拟数据库存储 (在实际部署中应替换为 db.php 中的真实数据库操作)
if (!isset($_SESSION['img_ttl'])) $_SESSION['img_ttl'] = 86400;
if (!isset($_SESSION['downloads'])) $_SESSION['downloads'] = 128;

$action = $_GET['action'] ?? '';

// 获取初始化信息
if ($action === 'get_init') {
    echo json_encode([
        'status' => 'success',
        'data' => [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'is_admin' => isset($_SESSION['admin_logged_in']),
            'img_ttl' => $_SESSION['img_ttl']
        ]
    ]);
    exit;
}

// 登录逻辑
if ($action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    // 硬编码管理员账号密码
    if ($username === 'admin' && $password === 'stack123456') {
        $_SESSION['admin_logged_in'] = true;
        echo json_encode(['status' => 'success', 'message' => '登录成功']);
    } else {
        echo json_encode(['status' => 'error', 'message' => '账号或密码错误']);
    }
    exit;
}

// 退出登录
if ($action === 'logout') {
    unset($_SESSION['admin_logged_in']);
    echo json_encode(['status' => 'success']);
    exit;
}

// 获取文件列表 (模拟数据)
if ($action === 'get_files') {
    echo json_encode([
        'status' => 'success',
        'data' => [
            ['id' => 1, 'name' => '系统架构图.pdf', 'path' => '#', 'size' => '2.4MB', 'downloads' => 45],
            ['id' => 2, 'name' => '部署指南.docx', 'path' => '#', 'size' => '1.1MB', 'downloads' => 12],
            ['id' => 3, 'name' => 'NodeJS_Runtime.zip', 'path' => '#', 'size' => '45.8MB', 'downloads' => 89]
        ]
    ]);
    exit;
}

// 获取图片列表 (模拟数据)
if ($action === 'get_images') {
    echo json_encode([
        'status' => 'success',
        'data' => [
            ['id' => 101, 'path' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=500&q=80'],
            ['id' => 102, 'path' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=500&q=80'],
            ['id' => 103, 'path' => 'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=500&q=80']
        ]
    ]);
    exit;
}

// 修改 TTL 策略
if ($action === 'set_ttl') {
    if (!isset($_SESSION['admin_logged_in'])) {
        echo json_encode(['status' => 'error', 'message' => '无权限']);
        exit;
    }
    $_SESSION['img_ttl'] = (int)$_GET['val'];
    echo json_encode(['status' => 'success', 'message' => '策略已更新']);
    exit;
}

// 获取统计数据
if ($action === 'get_stats') {
    echo json_encode([
        'status' => 'success',
        'data' => [
            'downloads' => $_SESSION['downloads'],
            'space' => ['used' => '1.2GB', 'total' => '10GB', 'percent' => 12]
        ]
    ]);
    exit;
}

// 默认 404
echo json_encode(['status' => 'error', 'message' => '无效的操作']);