<?php
/**
 * StackStation Pro v7.6 - 进度条位置修复版
 * 1. 修复进度条在 PC/移动端的居中位置
 * 2. 保持 7.5 版本的所有逻辑修复（中文支持、自动清理、资源图标）
 */

header('Content-Type: text/html; charset=utf-8');
ini_set('session.cookie_httponly', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
session_start();

$baseDir = __DIR__;
$uploadDir = $baseDir . '/uploads/';
$imageRoot = $baseDir . '/images/';
$configFile = $baseDir . '/config.json';
$statsFile = $baseDir . '/stats.json';

if (!isset($_SESSION['visitor_id'])) {
    $_SESSION['visitor_id'] = substr(md5(session_id()), 0, 8);
    update_stats('visitors');
}
$visitorImgDir = $imageRoot . $_SESSION['visitor_id'] . '/';

foreach ([$uploadDir, $imageRoot, $visitorImgDir] as $dir) {
    if (!file_exists($dir)) { 
        mkdir($dir, 0777, true); 
        file_put_contents($dir . 'index.html', ''); 
    }
}

if (!file_exists($configFile)) {
    file_put_contents($configFile, json_encode(['clean_hours' => 24, 'start_time' => time()]));
}
if (!file_exists($statsFile)) {
    file_put_contents($statsFile, json_encode(['visitors' => 0, 'downloads' => 0]));
}

auto_clean_engine();

function update_stats($key) {
    global $statsFile;
    $data = json_decode(file_get_contents($statsFile), true);
    if ($data) {
        $data[$key] = ($data[$key] ?? 0) + 1;
        file_put_contents($statsFile, json_encode($data));
    }
}

function auto_clean_engine() {
    global $imageRoot, $configFile;
    $config = json_decode(file_get_contents($configFile), true);
    $limit = ($config['clean_hours'] ?? 24) * 3600;
    if (!is_dir($imageRoot)) return;
    $it = new RecursiveDirectoryIterator($imageRoot, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir()) continue;
        if ($file->getFilename() === 'index.html') continue;
        if (time() - $file->getMTime() > $limit) {
            @unlink($file->getRealPath());
        }
    }
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];
    $isAdmin = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);

    switch ($action) {
        case 'get_init':
            $stats = json_decode(file_get_contents($statsFile), true);
            $config = json_decode(file_get_contents($configFile), true);
            echo json_encode(['status' => 'success', 'data' => [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'is_admin' => $isAdmin,
                'visitor_id' => $_SESSION['visitor_id'] ?? 'Guest',
                'stats' => $stats,
                'config' => $config,
                'current_time' => time(),
                'host' => (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . $_SERVER['PHP_SELF']
            ]]);
            break;

        case 'save_config':
            if (!$isAdmin) exit;
            $input = json_decode(file_get_contents('php://input'), true);
            $current = json_decode(file_get_contents($configFile), true);
            if(isset($input['hours'])) $current['clean_hours'] = (int)$input['hours'];
            if(isset($input['start_time'])) $current['start_time'] = (int)$input['start_time'];
            file_put_contents($configFile, json_encode($current));
            echo json_encode(['status' => 'success']);
            break;

        case 'get_files':
            $files = [];
            foreach (glob($uploadDir . "*") as $file) {
                if (basename($file) == 'index.html') continue;
                $files[] = [
                    'name' => basename($file), 
                    'size' => round(filesize($file)/1024/1024, 2).'MB', 
                    'date' => date("Y-m-d H:i", filemtime($file)), 
                    'url' => 'uploads/'.basename($file),
                    'ext' => strtolower(pathinfo($file, PATHINFO_EXTENSION))
                ];
            }
            echo json_encode(['status' => 'success', 'data' => $files]);
            break;

        case 'get_images':
            $files = [];
            $vDir = $imageRoot . ($_SESSION['visitor_id'] ?? 'unknown') . '/';
            foreach (glob($vDir . "*") as $f) {
                if (basename($f) == 'index.html') continue;
                $files[] = ['path' => 'images/' . ($_SESSION['visitor_id'] ?? 'unknown') . '/' . basename($f)];
            }
            echo json_encode(['status' => 'success', 'data' => $files]);
            break;

        case 'get_admin_images':
            if (!$isAdmin) exit;
            $all = [];
            $it = new RecursiveDirectoryIterator($imageRoot, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it);
            foreach ($files as $f) {
                if ($f->getFilename() === 'index.html') continue;
                $all[] = ['name' => $f->getFilename(), 'path' => str_replace($baseDir.'/', '', $f->getRealPath()), 'time' => date("m-d H:i", $f->getMTime())];
            }
            echo json_encode(['status' => 'success', 'data' => $all]);
            break;

        case 'delete_item':
            if (!$isAdmin) exit;
            $path = $baseDir . '/' . $_GET['path'];
            if (file_exists($path) && !is_dir($path)) unlink($path);
            echo json_encode(['status' => 'success']);
            break;

        case 'upload':
            $type = $_GET['type'] ?? 'file';
            if ($type === 'file' && !$isAdmin) { echo json_encode(['status' => 'error', 'msg' => 'Admin only']); exit; }
            $target = ($type === 'image') ? $visitorImgDir : $uploadDir;
            if (!empty($_FILES['file'])) {
                $originalName = $_FILES['file']['name'];
                $cleanName = preg_replace("/[^\x{4e00}-\x{9fa5}a-zA-Z0-9\._-]/u", "_", $originalName);
                move_uploaded_file($_FILES['file']['tmp_name'], $target . $cleanName);
                echo json_encode(['status' => 'success']);
            }
            break;

        case 'login':
            $input = json_decode(file_get_contents('php://input'), true);
            if (($input['u'] ?? '') === 'admin' && ($input['p'] ?? '') === 'stack123456') {
                $_SESSION['admin_logged_in'] = true;
                session_write_close();
                echo json_encode(['status' => 'success']);
            } else { echo json_encode(['status' => 'error', 'msg' => '凭证无效']); }
            break;

        case 'logout':
            session_destroy(); echo json_encode(['status' => 'success']);
            break;
    }
    exit;
}

if (isset($_GET['file_path']) && isset($_GET['download'])) {
    $path = realpath($baseDir . '/' . $_GET['file_path']);
    if ($path && file_exists($path) && (strpos($path, $imageRoot) === 0 || strpos($path, $uploadDir) === 0)) {
        update_stats('downloads');
        $fileName = basename($path);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
        readfile($path);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StackStation | 7.6 响应式进度修复</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Inter:wght@400;600;900&display=swap');
        body { font-family: 'Inter', 'Microsoft YaHei', sans-serif; background: #f3f5f4; color: #1a1a1a; overflow-x: hidden; }
        .page { display: none; }
        .page.active { display: block; animation: zoomIn 0.3s cubic-bezier(0.23, 1, 0.32, 1); }
        @keyframes zoomIn { from { opacity: 0; transform: scale(0.99); } to { opacity: 1; transform: scale(1); } }
        .nav-active { background: #000 !important; color: #fff !important; }
        .clock-font { font-family: 'Space Grotesk', sans-serif; }
        .file-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1); }
        
        /* 响应式进度条：固定在底部中间 */
        #progress-container {
            position: fixed;
            bottom: 2.5rem; /* 约 40px */
            left: 50%;
            transform: translate(-50%, 150%); /* 默认隐藏在下方 */
            width: 90%;
            max-width: 28rem; /* PC端 448px */
            background: white;
            border: 1px solid #e5e7eb;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border-radius: 1.5rem;
            padding: 1.5rem;
            z-index: 500;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
            opacity: 0;
            pointer-events: none;
        }
        #progress-container.active { 
            transform: translate(-50%, 0); 
            opacity: 1; 
            pointer-events: auto;
        }
        #progress-bar { transition: width 0.1s linear; }

        @media (max-width: 1024px) {
            .sidebar { position: fixed; transform: translateX(-100%); z-index: 100; height: 100vh; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); width: 85% !important; }
            .sidebar.open { transform: translateX(0); }
            .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 90; }
            .overlay.open { display: block; }
        }
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen">

    <div id="overlay" class="overlay" onclick="toggleMenu()"></div>
    <input type="file" id="u-input" class="hidden" onchange="handleUpload(this)">

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar w-72 bg-white border-r flex flex-col shrink-0">
        <div class="p-8 border-b flex items-center gap-3">
            <div class="w-10 h-10 bg-black rounded-xl flex items-center justify-center text-white shadow-xl"><i class="fas fa-terminal"></i></div>
            <h1 class="text-xl font-black tracking-tighter uppercase italic">StackStation</h1>
        </div>

        <nav class="flex-1 px-4 py-8 space-y-1">
            <button onclick="nav('files')" id="btn-files" class="w-full flex items-center gap-3 px-6 py-4 rounded-2xl text-sm font-bold transition-all hover:bg-gray-100 text-gray-500">
                <i class="fas fa-box-archive w-5"></i> 资源中心
            </button>
            <button onclick="nav('images')" id="btn-images" class="w-full flex items-center gap-3 px-6 py-4 rounded-2xl text-sm font-bold transition-all hover:bg-gray-100 text-gray-500">
                <i class="fas fa-panorama w-5"></i> 图库服务
            </button>
            <div id="admin-menu" class="hidden pt-8 mt-8 border-t space-y-1">
                <p class="px-6 text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">核心管控中心</p>
                <button onclick="nav('dashboard')" id="btn-dashboard" class="w-full flex items-center gap-3 px-6 py-4 rounded-2xl text-sm font-bold transition-all hover:bg-gray-100 text-gray-500">
                    <i class="fas fa-chart-line w-5"></i> 数据看板
                </button>
                <button onclick="nav('manager')" id="btn-manager" class="w-full flex items-center gap-3 px-6 py-4 rounded-2xl text-sm font-bold transition-all hover:bg-gray-100 text-gray-500">
                    <i class="fas fa-screwdriver-wrench w-5"></i> 后台管理
                </button>
            </div>
        </nav>

        <div class="px-6 py-5 bg-gray-50 border-t">
            <div class="flex items-center gap-3">
                <div class="relative"><div class="w-3 h-3 bg-green-500 rounded-full"></div><div class="absolute inset-0 w-3 h-3 bg-green-500 rounded-full animate-ping"></div></div>
                <div><p class="text-[9px] font-black text-gray-400 uppercase tracking-widest">稳定运行时间</p><p id="runtime-timer" class="text-[11px] font-bold clock-font text-gray-700">加载中...</p></div>
            </div>
        </div>

        <div class="p-6 border-t">
            <div id="admin-actions" class="hidden flex flex-col gap-2 mb-4">
                <button onclick="triggerUpload('file')" class="w-full py-4 bg-black text-white rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-xl active:scale-95 transition-all">上传主资源</button>
                <button onclick="doLogout()" class="py-2 text-rose-500 text-[9px] font-black uppercase">退出管理系统</button>
            </div>
            <button id="login-btn" onclick="openLogin()" class="w-full py-4 border-2 border-dashed border-gray-200 text-gray-400 text-[10px] font-black uppercase rounded-2xl hover:border-black hover:text-black transition-all">管理员登录</button>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0">
        <header class="h-20 bg-white border-b flex items-center justify-center px-6 sticky top-0 z-40">
            <button class="lg:hidden absolute left-6" onclick="toggleMenu()"><i class="fas fa-bars"></i></button>
            <div class="flex flex-col items-center">
                <p id="live-clock" class="text-xl font-black clock-font tabular-nums leading-none">00:00:00</p>
                <div class="flex items-center gap-2 mt-1 italic"><p id="ip-display" class="text-[9px] font-bold text-gray-300 font-mono tracking-tighter uppercase">IP: 0.0.0.0</p></div>
            </div>
            <div class="hidden sm:flex absolute right-6 items-center gap-4">
                <div class="text-right"><p id="badge-role" class="text-[9px] font-black uppercase text-gray-300 font-mono">Public Access</p><p id="v-id" class="text-[10px] font-bold text-gray-400 font-mono italic">UID: ----</p></div>
            </div>
        </header>

        <div class="p-6 lg:p-12 pb-32">
            <div id="page-files" class="page active">
                <div class="mb-10 text-center lg:text-left"><h2 class="text-3xl font-black italic tracking-tighter uppercase">资源中心</h2><p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1 italic">Archive Storage</p></div>
                <div id="file-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6"></div>
            </div>

            <div id="page-images" class="page">
                <div class="bg-black rounded-[3rem] p-10 text-white mb-10 shadow-2xl relative overflow-hidden">
                    <div class="relative z-10">
                        <h3 class="text-2xl font-black uppercase mb-2 italic">云图预览</h3>
                        <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest mb-8">预览链接支持跨端直接查看</p>
                        <button onclick="triggerUpload('image')" class="bg-white text-black px-10 py-4 rounded-2xl text-xs font-black uppercase tracking-widest shadow-xl active:scale-95 transition-all">上传照片</button>
                    </div><i class="fas fa-bolt absolute -bottom-10 -right-10 text-[15rem] opacity-5"></i>
                </div>
                <div id="image-grid" class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-6 gap-4"></div>
            </div>

            <div id="page-dashboard" class="page">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <div class="bg-white p-12 rounded-[3.5rem] border shadow-sm"><p class="text-[10px] font-black text-gray-400 uppercase mb-4 tracking-widest">总访客数</p><h4 id="stat-visitors" class="text-7xl font-black clock-font">0</h4></div>
                    <div class="bg-white p-12 rounded-[3.5rem] border shadow-sm"><p class="text-[10px] font-black text-gray-400 uppercase mb-4 tracking-widest">下载总量</p><h4 id="stat-downloads" class="text-7xl font-black clock-font text-indigo-600">0</h4></div>
                </div>
            </div>

            <div id="page-manager" class="page">
                <div class="space-y-8">
                    <div class="bg-white p-10 rounded-[3rem] border shadow-sm"><h5 class="font-black text-sm uppercase mb-6 italic">清理周期设置 (小时)</h5>
                        <div class="flex items-center gap-4 max-w-sm"><input id="clean-input" type="number" class="flex-1 px-6 py-4 bg-gray-50 border rounded-2xl font-bold"><button onclick="saveCleanHours()" class="px-8 py-4 bg-black text-white rounded-2xl text-xs font-black uppercase">应用</button></div>
                    </div>
                    <div class="bg-white p-10 rounded-[3rem] border shadow-sm"><h5 class="font-black text-sm uppercase mb-6 italic">系统启动点同步</h5>
                        <div class="flex flex-col sm:flex-row items-center gap-4 max-w-xl"><input id="start-date-input" type="datetime-local" class="w-full sm:flex-1 px-6 py-4 bg-gray-50 border rounded-2xl font-bold outline-none"><button onclick="saveStartTime()" class="w-full sm:w-auto px-10 py-4 bg-black text-white rounded-2xl text-xs font-black uppercase active:scale-95">同步</button></div>
                    </div>
                    <div class="bg-white p-10 rounded-[3rem] border shadow-sm"><h5 class="font-black text-sm uppercase mb-6 italic">资源监控器</h5><div id="admin-files" class="divide-y space-y-1 font-bold text-sm"></div></div>
                    <div class="bg-white p-10 rounded-[3rem] border shadow-sm"><h5 class="font-black text-sm uppercase mb-6 italic">全局图库预览</h5><div id="admin-imgs" class="grid grid-cols-3 md:grid-cols-8 gap-4 px-2"></div></div>
                </div>
            </div>
        </div>
    </main>

    <!-- 修复后的悬浮进度条 (底部中间，适配双端) -->
    <div id="progress-container">
        <div class="flex justify-between items-center mb-4">
            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">正在同步至云端...</span>
            <span id="progress-text" class="text-sm font-black italic">0%</span>
        </div>
        <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
            <div id="progress-bar" class="h-full bg-black w-0"></div>
        </div>
    </div>

    <!-- Modal Detail -->
    <div id="modal-detail" class="fixed inset-0 z-[200] hidden items-center justify-center bg-black/40 backdrop-blur-xl p-4">
        <div class="bg-white w-full max-w-md rounded-[3.5rem] shadow-2xl overflow-hidden animate-zoomIn">
            <div class="p-10 border-b bg-gray-50/50 text-center">
                <div id="det-icon-box" class="w-20 h-20 bg-black text-white rounded-3xl flex items-center justify-center text-3xl mb-8 shadow-2xl mx-auto transition-all"><i class="fas fa-file"></i></div>
                <h3 id="det-name" class="text-2xl font-black break-all uppercase italic leading-none mb-4 tracking-tighter">FILE</h3>
                <div class="flex flex-wrap justify-center gap-2"><span id="det-size" class="px-4 py-1.5 bg-white border rounded-full text-[10px] font-black text-gray-500 uppercase">0 MB</span><span id="det-date" class="px-4 py-1.5 bg-white border rounded-full text-[10px] font-black text-gray-500 uppercase">DATE</span></div>
            </div>
            <div class="p-10 space-y-3">
                <button id="det-btn-copy" class="w-full flex items-center justify-between px-6 py-5 bg-gray-50 hover:bg-gray-100 rounded-3xl font-black text-sm uppercase italic transition-all">复制直链 <i class="fas fa-link opacity-30"></i></button>
                <button id="det-btn-dl" class="w-full flex items-center justify-between px-6 py-5 bg-black text-white rounded-3xl font-black text-sm uppercase italic active:scale-95">下载资源 <i class="fas fa-download"></i></button>
                <button onclick="closeDetail()" class="w-full py-4 text-[10px] font-black text-gray-400 uppercase tracking-[0.3em] mt-6">CLOSE</button>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="modal-login" class="fixed inset-0 z-[200] hidden items-center justify-center bg-black/60 backdrop-blur-md p-4">
        <div class="bg-white w-full max-w-xs rounded-[3rem] p-10 text-center shadow-2xl">
            <h3 class="text-2xl font-black mb-8 italic uppercase tracking-tighter">SYSTEM LOCK</h3>
            <div class="space-y-4">
                <input id="l-u" type="text" placeholder="ID" class="w-full px-6 py-4 bg-gray-50 border rounded-2xl font-bold outline-none">
                <input id="l-p" type="password" placeholder="KEY" class="w-full px-6 py-4 bg-gray-50 border rounded-2xl font-bold outline-none">
                <button onclick="doLogin()" id="login-submit-btn" class="w-full py-4 bg-black text-white rounded-2xl font-bold shadow-xl active:scale-95 transition-all">授权登录</button>
                <button onclick="closeLogin()" class="text-[9px] font-black text-gray-400 uppercase mt-4">CANCEL</button>
            </div>
        </div>
    </div>

    <div id="toast" class="fixed bottom-10 left-1/2 -translate-x-1/2 z-[300] bg-black text-white px-8 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all opacity-0 translate-y-10 pointer-events-none shadow-2xl"></div>

    <script>
        let state = { isAdmin: false, host: '', currentNav: 'files', startTime: 0 };

        function getFileIcon(ext) {
            const icons = { 'pdf': 'fa-file-pdf text-rose-500', 'zip': 'fa-file-zipper text-amber-500', 'rar': 'fa-file-zipper text-amber-500', '7z': 'fa-file-zipper text-amber-500', 'jpg': 'fa-file-image text-sky-500', 'png': 'fa-file-image text-sky-500', 'gif': 'fa-file-image text-sky-500', 'mp4': 'fa-file-video text-violet-500', 'mov': 'fa-file-video text-violet-500', 'txt': 'fa-file-lines text-slate-400', 'doc': 'fa-file-word text-blue-600', 'docx': 'fa-file-word text-blue-600', 'xls': 'fa-file-excel text-emerald-600', 'xlsx': 'fa-file-excel text-emerald-600', 'ppt': 'fa-file-powerpoint text-orange-600', 'mp3': 'fa-file-audio text-pink-500', 'php': 'fa-file-code text-indigo-500', 'html': 'fa-file-code text-orange-500', 'js': 'fa-file-code text-yellow-500' };
            return icons[ext] || 'fa-file text-gray-300';
        }

        function showToast(m) {
            const t = document.getElementById('toast'); t.innerText = m;
            t.classList.remove('opacity-0', 'translate-y-10');
            setTimeout(() => t.classList.add('opacity-0', 'translate-y-10'), 3000);
        }

        function toggleMenu() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('overlay').classList.toggle('open'); }

        async function init(isUpdate = false) {
            if(!isUpdate) { setInterval(() => { document.getElementById('live-clock').innerText = new Date().toLocaleTimeString('zh-CN', { hour12: false }); updateRuntimeDisplay(); }, 1000); }
            try {
                const res = await (await fetch('?action=get_init')).json();
                state.isAdmin = res.data.is_admin;
                state.host = res.data.host;
                state.startTime = res.data.config.start_time;
                document.getElementById('ip-display').innerText = "IP: " + res.data.ip;
                document.getElementById('v-id').innerText = "UID: " + res.data.visitor_id;
                document.getElementById('stat-visitors').innerText = res.data.stats.visitors || 0;
                document.getElementById('stat-downloads').innerText = res.data.stats.downloads || 0;
                document.getElementById('clean-input').value = res.data.config.clean_hours;
                if(state.startTime) {
                    const d = new Date(state.startTime * 1000);
                    document.getElementById('start-date-input').value = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
                }
                const adminMenu = document.getElementById('admin-menu'), adminActions = document.getElementById('admin-actions'), loginBtn = document.getElementById('login-btn'), badgeRole = document.getElementById('badge-role');
                if(state.isAdmin) { adminMenu.classList.remove('hidden'); adminActions.classList.remove('hidden'); loginBtn.classList.add('hidden'); badgeRole.innerText = "Owner Mode"; badgeRole.classList.replace('text-gray-300', 'text-black'); } 
                else { adminMenu.classList.add('hidden'); adminActions.classList.add('hidden'); loginBtn.classList.remove('hidden'); badgeRole.innerText = "Public Access"; badgeRole.classList.replace('text-black', 'text-gray-300'); if(['dashboard', 'manager'].includes(state.currentNav)) nav('files', true); }
                if(!isUpdate) nav('files', true); else nav(state.currentNav, true);
            } catch(e) { console.error(e); }
        }

        function updateRuntimeDisplay() {
            if(!state.startTime) return;
            const diff = Math.floor(Date.now()/1000) - state.startTime;
            const d = Math.floor(diff / 86400), h = Math.floor((diff % 86400) / 3600), m = Math.floor((diff % 3600) / 60), s = diff % 60;
            document.getElementById('runtime-timer').innerText = `${String(d).padStart(2, '0')}D ${String(h).padStart(2, '0')}H ${String(m).padStart(2, '0')}M ${String(s).padStart(2, '0')}S`;
        }

        function nav(id, skipMenu = false) {
            state.currentNav = id;
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            document.getElementById('page-' + id).classList.add('active');
            document.querySelectorAll('nav button').forEach(b => b.classList.remove('nav-active'));
            const btn = document.getElementById('btn-' + id);
            if(btn) btn.classList.add('nav-active');
            if(id === 'files') loadFiles();
            if(id === 'images') loadImages();
            if(id === 'manager') loadManager();
            if(window.innerWidth < 1024 && !skipMenu) toggleMenu();
        }

        async function loadFiles() {
            const res = await (await fetch('?action=get_files')).json();
            document.getElementById('file-grid').innerHTML = res.data.map(f => {
                const iconClass = getFileIcon(f.ext);
                return `<div onclick='openDetail(${JSON.stringify(f).replace(/'/g, "&apos;")})' class="file-card bg-white p-8 rounded-[2.5rem] border border-gray-100 transition-all cursor-pointer group">
                    <div class="flex justify-between mb-8"><div class="w-14 h-14 bg-gray-50 rounded-2xl flex items-center justify-center text-xl transition-colors group-hover:bg-black group-hover:text-white"><i class="fas ${iconClass}"></i></div><i class="fas fa-plus text-gray-100 group-hover:text-black transition-all"></i></div>
                    <p class="font-black text-sm truncate uppercase italic tracking-tighter" title="${f.name}">${f.name}</p><p class="text-[9px] font-black text-gray-300 uppercase mt-2">${f.size} • ${f.ext.toUpperCase() || 'DATA'}</p></div>`;
            }).join('');
        }

        function openDetail(file) {
            const modal = document.getElementById('modal-detail');
            document.getElementById('det-name').innerText = file.name;
            document.getElementById('det-size').innerText = file.size;
            document.getElementById('det-date').innerText = file.date;
            const iconData = getFileIcon(file.ext).split(' ');
            document.getElementById('det-icon-box').innerHTML = `<i class="fas ${iconData[0]} ${iconData[1]}"></i>`;
            const dlUrl = new URL(state.host); dlUrl.searchParams.set('file_path', file.url); dlUrl.searchParams.set('download', '1');
            const dl = dlUrl.toString();
            document.getElementById('det-btn-copy').onclick = () => { copyT(dl); showToast("✅ 下载链接已存入剪贴板"); };
            document.getElementById('det-btn-dl').onclick = () => { location.href = dl; closeDetail(); };
            modal.classList.replace('hidden', 'flex');
        }

        function closeDetail() { document.getElementById('modal-detail').classList.replace('flex', 'hidden'); }

        async function loadImages() {
            const res = await (await fetch('?action=get_images')).json();
            document.getElementById('image-grid').innerHTML = res.data.map(i => {
                const previewUrl = new URL(i.path, state.host).toString();
                return `<div class="group relative aspect-square bg-white rounded-[2.5rem] overflow-hidden border shadow-sm"><img src="${i.path}" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black/70 opacity-0 group-hover:opacity-100 transition-all flex items-center justify-center"><button onclick="copyT('${previewUrl}');showToast('✅ 预览 URL 已复制')" class="bg-white text-black text-[10px] font-black px-6 py-3 rounded-full uppercase tracking-widest hover:scale-110 transition-transform">复制 URL</button></div></div>`;
            }).join('');
        }

        async function loadManager() {
            if(!state.isAdmin) return;
            const f_res = await (await fetch('?action=get_files')).json();
            document.getElementById('admin-files').innerHTML = f_res.data.map(f => `<div class="flex items-center justify-between py-4 group"><span class="truncate font-bold text-gray-700">${f.name}</span><button onclick="delItem('${f.url}')" class="text-rose-500 font-black text-[10px] uppercase opacity-50 group-hover:opacity-100">删除</button></div>`).join('');
            const i_res = await (await fetch('?action=get_admin_images')).json();
            document.getElementById('admin-imgs').innerHTML = i_res.data.map(i => `<div onclick="delItem('${i.path}')" class="relative aspect-square rounded-2xl overflow-hidden border cursor-pointer group"><img src="${i.path}" class="w-full h-full object-cover grayscale">
                <div class="absolute inset-0 bg-rose-600/80 opacity-0 group-hover:opacity-100 flex flex-col items-center justify-center text-white p-2"><span class="text-[8px] font-black uppercase text-center mb-1">${i.name}</span><div class="px-2 py-1 bg-white text-rose-600 rounded-full text-[8px] font-black">抹除</div></div></div>`).join('');
        }

        async function delItem(path) { if(!confirm("确认彻底删除？")) return; await fetch(`?action=delete_item&path=${encodeURIComponent(path)}`); loadManager(); showToast("文件已销毁"); }
        async function saveCleanHours() { const hrs = document.getElementById('clean-input').value; await fetch('?action=save_config', { method: 'POST', body: JSON.stringify({hours: hrs}) }); showToast("✅ 清理周期已同步"); init(true); }
        function triggerUpload(type) { state.uploadType = type; document.getElementById('u-input').click(); }

        function handleUpload(input) {
            if(!input.files[0]) return;
            const file = input.files[0], fd = new FormData(); fd.append('file', file);
            const container = document.getElementById('progress-container'), bar = document.getElementById('progress-bar'), text = document.getElementById('progress-text');
            bar.style.width = '0%'; text.innerText = '0%'; container.classList.add('active');
            const xhr = new XMLHttpRequest(); xhr.open('POST', `?action=upload&type=${state.uploadType}`, true);
            xhr.upload.onprogress = (e) => { if (e.lengthComputable) { const pct = Math.round((e.loaded / e.total) * 100); bar.style.width = pct + '%'; text.innerText = pct + '%'; } };
            xhr.onload = () => { container.classList.remove('active'); if (xhr.status === 200) { const res = JSON.parse(xhr.responseText); if(res.status === 'success') { showToast("🚀 同步成功"); nav(state.currentNav, true); } else { showToast("❌ 传输受阻: " + res.msg); } } else showToast("❌ 网络链路异常"); input.value = ''; };
            xhr.onerror = () => { container.classList.remove('active'); showToast("❌ 物理连接中断"); };
            xhr.send(fd);
        }

        function copyT(t) { const el = document.createElement('textarea'); el.value = t; document.body.appendChild(el); el.select(); document.execCommand('copy'); document.body.removeChild(el); }
        async function saveStartTime() { const dateStr = document.getElementById('start-date-input').value; if(!dateStr) return; const ts = Math.floor(new Date(dateStr).getTime() / 1000); await fetch('?action=save_config', { method: 'POST', body: JSON.stringify({start_time: ts}) }); state.startTime = ts; showToast("✅ 里程碑已同步"); }
        function openLogin() { document.getElementById('modal-login').classList.replace('hidden', 'flex'); }
        function closeLogin() { document.getElementById('modal-login').classList.replace('flex', 'hidden'); }
        async function doLogin() {
            const u = document.getElementById('l-u').value, p = document.getElementById('l-p').value, btn = document.getElementById('login-submit-btn');
            btn.innerText = "AUTHENTICATING..."; btn.disabled = true;
            try { const res = await (await fetch('?action=login', { method: 'POST', body: JSON.stringify({u, p}) })).json(); if(res.status === 'success') { showToast("✅ 授权成功"); closeLogin(); await init(true); } else showToast("❌ 凭证不匹配"); } catch(e) { showToast("❌ 通信失败"); }
            btn.innerText = "授权登录"; btn.disabled = false;
        }
        async function doLogout() { await fetch('?action=logout'); showToast("特权已撤销"); await init(true); }
        window.onload = () => init();
    </script>
</body>
</html>