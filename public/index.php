<?php
/**
 * PDF Manager - Công cụ sắp xếp, ghép, tách và xử lý tài liệu PDF trên trình duyệt
 * Đề tài 12 - Học phần Phát triển phần mềm mã nguồn mở
 * Trường Đại học Đại Nam
 * 
 * Nhóm thực hiện:
 * - Đỗ Tiến Sơn (1871020508)
 * - Đỗ Anh Duy (1871020190)
 * - Vũ Văn Chiến (1871020088)
 */

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\DocumentController;
use App\Controllers\EditorController;
use App\Controllers\ExportController;
use App\Controllers\HistoryController;
use App\Controllers\SettingController;
use App\Services\PdfProcessorService;
use App\Services\SecurityValidator;
use App\Services\StorageService;

// Load .env configuration if present
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $envLine) {
        $envLine = trim($envLine);
        if ($envLine === '' || str_starts_with($envLine, '#')) continue;
        if (str_contains($envLine, '=')) {
            [$k, $v] = explode('=', $envLine, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

$pdo = null;
$dbConnection = getenv('DB_CONNECTION') ?: 'mysql';
if ($dbConnection === 'mysql') {
    $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
    $dbPort = getenv('DB_PORT') ?: '3306';
    $dbName = getenv('DB_DATABASE') ?: 'pdf_manager';
    $dbUser = getenv('DB_USERNAME') ?: 'root';
    $dbPass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
    try {
        $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (Exception $e) {
        // Fallback to SQLite if MySQL fails
        $dbPath = __DIR__ . '/../database/database.sqlite';
        if (file_exists($dbPath)) {
            try {
                $pdo = new PDO("sqlite:" . $dbPath);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (Exception $sqe) {}
        }
    }
} else {
    $dbPath = __DIR__ . '/../database/database.sqlite';
    if (file_exists($dbPath)) {
        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {}
    }
}

$authController = new AuthController($pdo);

$isLoggedIn = !empty($_SESSION['user_id']);
$currentUser = $isLoggedIn ? [
    'id' => (int)$_SESSION['user_id'],
    'name' => $_SESSION['user_name'] ?? 'Người dùng',
    'email' => $_SESSION['user_email'] ?? '',
    'username' => $_SESSION['username'] ?? '',
    'role' => $_SESSION['user_role'] ?? 'Thành viên',
    'plan' => $_SESSION['user_plan'] ?? 'FREE'
] : null;

$userPlan = $currentUser['plan'] ?? ($_SESSION['user_plan'] ?? 'FREE');

$validator = new SecurityValidator();
$storage = new StorageService();
$processor = new PdfProcessorService($validator, $storage, $pdo);

// Khởi tạo các Controllers chuyên biệt theo kiến trúc MVC chuẩn hóa
$documentController = new DocumentController($validator, $storage, $pdo, __DIR__ . '/../storage/documents');
$editorController = new EditorController($processor, $pdo);
$exportController = new ExportController($processor, $pdo, __DIR__ . '/../storage/exports');
$historyController = new HistoryController($pdo, __DIR__ . '/../storage/exports');
$settingController = new SettingController($pdo);
$effectiveUserId = (int)($_SESSION['user_id'] ?? 1);
$currentSettings = $settingController->getSettingsData($effectiveUserId);

// Xác định Base URL động (tương thích cả khi chạy qua Apache XAMPP dạng thư mục con hay qua php -S cổng 8000 trên Windows)
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = str_replace('\\', '/', dirname($scriptName));
$scriptDir = trim($scriptDir, '/');
$baseUrl = ($scriptDir === '' || $scriptDir === '.' || basename($scriptName) === 'router.php') ? '' : '/' . $scriptDir;

// Chuẩn hóa requestUri để loại bỏ tiền tố thư mục con (ví dụ: /PTPMMNM-CNTT-1809-NHOM6/public hoặc /PTPMMNM-CNTT-1809-NHOM6)
$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestUri = $rawPath;
$possibleBases = array_unique(array_filter([
    $baseUrl,
    preg_replace('#/public$#', '', $baseUrl)
]));
foreach ($possibleBases as $b) {
    if ($b !== '' && str_starts_with($requestUri, $b)) {
        $requestUri = substr($requestUri, strlen($b));
        break;
    }
}
if ($requestUri === '' || $requestUri === false) {
    $requestUri = '/';
}

// Clean URL routing without hash (#): /home, /documents, /editor, /upgrade, /history, /settings
$cleanRoute = trim($requestUri, '/');
if (in_array($cleanRoute, ['reorder', 'merge', 'split'])) {
    $cleanRoute = 'editor';
}
$validRoutes = ['home', 'documents', 'editor', 'upgrade', 'history', 'settings'];
$currentView = in_array($cleanRoute, $validRoutes) ? $cleanRoute : 'home';

// Endpoint Tải xuống file xuất qua HistoryController
if ($requestUri === '/api/download') {
    $filename = basename($_GET['file'] ?? '');
    $historyController->download($filename);
    exit;
}

// Các REST API Endpoints
if (str_starts_with($requestUri, '/api/')) {
    header('Content-Type: application/json; charset=utf-8');

    // --- Authentication & Plan Endpoints ---
    if ($requestUri === '/api/auth/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $authController->login($input);
        exit;
    }

    if ($requestUri === '/api/auth/register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $authController->register($input);
        exit;
    }

    if ($requestUri === '/api/auth/logout') {
        $authController->logout();
        exit;
    }

    if ($requestUri === '/api/auth/plan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $authController->upgradePlan($input);
        exit;
    }

    if ($requestUri === '/api/auth/me') {
        $authController->currentUser();
        exit;
    }

    if ($requestUri === '/api/auth/switch-user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $targetId = (int)($input['user_id'] ?? 1);
        $authController->switchUser($targetId);
        exit;
    }

    // --- Quản lý Tài liệu (DocumentController) ---
    if ($requestUri === '/api/documents') {
        $effectiveUserId = $_SESSION['user_id'] ?? 1;
        $documentController->index($effectiveUserId);
        exit;
    }

    if ($requestUri === '/api/documents/upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy tệp tải lên.']);
            exit;
        }
        $effectiveUserId = $_SESSION['user_id'] ?? 1;
        $documentController->upload($effectiveUserId, $_FILES['file']);
        exit;
    }

    if ($requestUri === '/api/documents/delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $docId = (int)($input['id'] ?? 0);
        $effectiveUserId = $_SESSION['user_id'] ?? 1;
        $documentController->delete($effectiveUserId, $docId);
        exit;
    }

    // --- Xử lý Xuất tài liệu (ExportController) ---
    if ($requestUri === '/api/export/merge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $plan = $input['plan'] ?? [];
        $title = $input['title'] ?? 'Tai_lieu_ghep.pdf';
        $effectiveUserId = $_SESSION['user_id'] ?? 1;
        $exportController->merge($effectiveUserId, $plan, $title);
        exit;
    }

    if ($requestUri === '/api/export/split' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $groups = $input['groups'] ?? [];
        $sourceDoc = $input['source_doc'] ?? 'Tai_lieu_hoc_tap.pdf';
        $effectiveUserId = $_SESSION['user_id'] ?? 1;
        $exportController->split($effectiveUserId, $groups, $sourceDoc);
        exit;
    }

    // --- Lịch sử xuất & Bảng đối chiếu nguồn (HistoryController) ---
    if ($requestUri === '/api/export/history') {
        $effectiveUserId = $_SESSION['user_id'] ?? 1;
        $historyController->list($effectiveUserId);
        exit;
    }

    if ($requestUri === '/api/export/delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $outputId = (int)($input['id'] ?? 0);
        $effectiveUserId = $_SESSION['user_id'] ?? 1;
        $historyController->delete($effectiveUserId, $outputId);
        exit;
    }

    if ($requestUri === '/api/export/clear-all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $effectiveUserId = $_SESSION['user_id'] ?? 1;
        $historyController->clearAll($effectiveUserId);
        exit;
    }

    if ($requestUri === '/api/export/manifest') {
        $outputId = (int)($_GET['id'] ?? 0);
        $historyController->manifest($outputId);
        exit;
    }

    // --- Cài đặt Hệ thống (SettingController) ---
    if ($requestUri === '/api/settings/get') {
        $settingController->get();
        exit;
    }

    if ($requestUri === '/api/settings/save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $settingController->save($input);
        exit;
    }

    if ($requestUri === '/api/settings/reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $settingController->reset();
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'PDF Manager API v1.0']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentSettings['language'] ?? 'vi') ?>" <?= ($currentSettings['theme'] ?? '') === 'dark' ? 'data-theme="dark"' : '' ?> class="<?= ($currentSettings['density'] ?? '') === 'compact' ? 'density-compact' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Manager v1.0 - Sắp xếp, Ghép, Tách & Xử lý PDF</title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/style.css?v=<?= time() ?>">
    <script>
        window.BASE_URL = <?= json_encode($baseUrl) ?>;
        window.INITIAL_SETTINGS = <?= json_encode($currentSettings) ?>;
        // Áp dụng ngay lập tức cấu hình giao diện đã lưu (tránh chớp sáng / trễ layout)
        (function() {
            try {
                var s = JSON.parse(localStorage.getItem('pdf_manager_user_settings') || 'null') || window.INITIAL_SETTINGS || {};
                if (s.theme === 'dark' || (s.theme === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else if (s.theme === 'light') {
                    document.documentElement.removeAttribute('data-theme');
                }
                if (s.density === 'compact') {
                    document.documentElement.classList.add('density-compact');
                } else if (s.density === 'comfortable') {
                    document.documentElement.classList.remove('density-compact');
                }
                if (s.language) {
                    document.documentElement.setAttribute('lang', s.language);
                }
            } catch(e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileSidebar()"></div>

    <div class="app-layout <?= $currentView === 'editor' ? 'sidebar-compact' : '' ?>" id="appLayout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar <?= $currentView === 'editor' ? 'collapsed' : '' ?>" id="appSidebar">
            <a href="<?= $baseUrl ?>/home" class="brand" onclick="switchView('home', event)" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 12px; cursor: pointer;">
                <div class="brand-icon">
                    <svg class="svg-icon svg-icon-lg" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="9" y1="13" x2="15" y2="13"/>
                        <line x1="9" y1="17" x2="13" y2="17"/>
                    </svg>
                </div>
                <div class="brand-info">
                    <h1 class="brand-title">PDF Manager <span class="badge">v1.0</span></h1>
                    <p class="brand-sub" data-i18n="brand.sub">Sắp xếp - Ghép - Tách - Xử lý PDF</p>
                </div>
            </a>

            <nav class="nav-menu">
                <a href="<?= $baseUrl ?>/home" class="nav-item <?= $currentView === 'home' ? 'active' : '' ?>" data-view="home" title="Trang chủ">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </span>
                    <span data-i18n="nav.home">Trang chủ</span>
                </a>
                <a href="<?= $baseUrl ?>/documents" class="nav-item <?= $currentView === 'documents' ? 'active' : '' ?>" data-view="documents" title="Quản lý tài liệu">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    </span>
                    <span data-i18n="nav.documents">Quản lý tài liệu</span>
                </a>
                <a href="<?= $baseUrl ?>/editor" class="nav-item <?= $currentView === 'editor' ? 'active' : '' ?>" data-view="editor" title="Chỉnh sửa PDF">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </span>
                    <span data-i18n="nav.editor">Chỉnh sửa PDF</span>
                </a>
                <a href="<?= $baseUrl ?>/upgrade" class="nav-item <?= $currentView === 'upgrade' ? 'active' : '' ?>" data-view="upgrade" title="Nâng Cấp Gói">
                    <span class="nav-icon">
                        <svg class="svg-icon" style="color: #F59E0B;" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    </span>
                    <span style="font-weight: 600; color: #D97706;" data-i18n="nav.upgrade">Nâng Cấp</span>
                </a>
                <a href="<?= $baseUrl ?>/history" class="nav-item <?= $currentView === 'history' ? 'active' : '' ?>" data-view="history" title="Lịch sử xuất">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </span>
                    <span data-i18n="nav.history">Lịch sử xuất</span>
                </a>
                <a href="<?= $baseUrl ?>/settings" class="nav-item <?= $currentView === 'settings' ? 'active' : '' ?>" data-view="settings" title="Cài đặt">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </span>
                    <span data-i18n="nav.settings">Cài đặt</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="storage-info">
                    <div class="storage-label">
                        <span data-i18n="nav.storage">Dung lượng lưu trữ</span>
                        <span id="storageUsageText"><?= ($_SESSION['user_plan'] ?? 'FREE') === 'PRO' ? '125 MB / Không giới hạn' : '125 MB / 1 GB' ?></span>
                    </div>
                    <div class="progress-bar"><div class="progress-fill" id="storageProgressBar" style="width: <?= ($_SESSION['user_plan'] ?? 'FREE') === 'PRO' ? '8%' : '12.5%' ?>;"></div></div>
                    <a href="<?= $baseUrl ?>/upgrade" class="storage-upgrade" onclick="switchView('upgrade', event)" data-i18n="nav.upgrade_btn"><?= ($_SESSION['user_plan'] ?? 'FREE') === 'PRO' ? 'Đã lên PRO' : 'Nâng cấp' ?></a>
                </div>
                <?php if ($isLoggedIn && $currentUser): ?>
                <div class="user-profile" onclick="toggleUserDropdown(event)" style="cursor: pointer;" title="Tài khoản & Gói hội viên">
                    <div class="user-left">
                        <div class="avatar" id="sidebarAvatar"><?= mb_substr($currentUser['name'] ?? 'NV', 0, 2) ?></div>
                        <div class="user-meta">
                            <div class="user-name" id="sidebarUserName"><?= htmlspecialchars($currentUser['name']) ?></div>
                            <div class="user-role">
                                <span class="plan-badge <?= strtolower($currentUser['plan'] ?? 'FREE') ?>" id="sidebarPlanBadge">
                                    <?= htmlspecialchars($currentUser['plan'] ?? 'FREE') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <svg class="svg-icon svg-icon-sm" style="color: #94A3B8;" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
                <?php else: ?>
                <div class="user-profile guest-profile" onclick="openAuthModal('login')" style="cursor: pointer;" title="Bấm để đăng nhập">
                    <div class="user-left">
                        <div class="avatar" style="background: #94A3B8; color: white;">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24" style="stroke: #fff;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <div class="user-meta">
                            <div class="user-name" style="color: #475569; font-size: 0.82rem;" data-i18n="nav.guest">Khách vãng lai</div>
                            <div class="user-role"><span class="badge" style="background: #F1F5F9; color: #64748B; font-size: 0.68rem;" data-i18n="nav.guest_status">Chưa đăng nhập</span></div>
                        </div>
                    </div>
                    <button type="button" class="btn-guest-login-sm" onclick="openAuthModal('login'); event.stopPropagation();" data-i18n="nav.guest_login">Đăng nhập</button>
                </div>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Topbar -->
            <header class="topbar">
                <div class="topbar-left">
                    <button class="btn-mobile-menu" onclick="toggleMobileSidebar()" title="Mở danh mục">
                        <svg class="svg-icon" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                    <button class="btn-sidebar-toggle" id="btnSidebarToggle" onclick="toggleSidebarCompact()" title="Thu gọn / Mở rộng Sidebar">
                        <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><path d="m14 15-3-3 3-3"/></svg>
                    </button>
                    <div class="search-box">
                        <span class="search-icon">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </span>
                        <input type="text" id="globalSearch" placeholder="Tìm kiếm tài liệu, tệp..." data-i18n-placeholder="topbar.search_placeholder">
                    </div>
                </div>
                <div class="topbar-actions">
                    <button class="btn-icon" title="Thông báo" onclick="showToast('Bạn có 3 thông báo mới về xử lý tệp.', 'info')">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <span class="badge-count">3</span>
                    </button>
                    
                    <?php if ($isLoggedIn && $currentUser): ?>
                    <div class="user-dropdown-wrapper">
                        <div class="user-chip" id="userChipBtn" onclick="toggleUserDropdown(event)" title="Quản lý tài khoản & Gói">
                            <div class="avatar-sm" id="userChipAvatar"><?= mb_substr($currentUser['name'] ?? 'NV', 0, 2) ?></div>
                            <div class="user-chip-meta">
                                <span class="user-chip-name" id="userChipName"><?= htmlspecialchars($currentUser['name']) ?></span>
                                <span class="plan-badge <?= strtolower($currentUser['plan'] ?? 'FREE') ?>" id="userChipPlanBadge"><?= htmlspecialchars($currentUser['plan'] ?? 'FREE') ?></span>
                            </div>
                            <svg class="svg-icon svg-icon-sm" style="color: #94A3B8;" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>

                        <!-- Dropdown Menu Người dùng -->
                        <div class="user-dropdown-menu" id="userDropdownMenu">
                            <div class="user-dd-header">
                                <div class="user-dd-avatar" id="ddAvatar"><?= mb_substr($currentUser['name'] ?? 'NV', 0, 2) ?></div>
                                <div class="user-dd-info">
                                    <div class="user-dd-name" id="ddName"><?= htmlspecialchars($currentUser['name']) ?></div>
                                    <div class="user-dd-email" id="ddEmail"><?= htmlspecialchars($currentUser['email'] ?? 'nguyenvana@gmail.com') ?></div>
                                </div>
                            </div>

                            <!-- Thẻ gói dịch vụ SaaS -->
                            <div class="user-dd-plan-card" id="userDdPlanCard">
                                <div class="plan-card-info">
                                    <div class="plan-card-title"><span data-i18n="topbar.plan_label">Gói dịch vụ:</span> <strong id="ddPlanText"><?= htmlspecialchars($currentUser['plan'] ?? 'FREE') ?></strong></div>
                                    <div class="plan-card-sub" id="ddPlanSub" data-i18n="<?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'topbar.plan_pro_unlock' : 'topbar.plan_free_limit' ?>"><?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'Mở khóa toàn bộ tính năng cao cấp' : 'Dung lượng 1GB • Ghép tách tiêu chuẩn' ?></div>
                                </div>
                                <button type="button" class="btn-plan-action" onclick="switchView('upgrade'); closeUserDropdown();" id="btnDdPlanUpgrade" data-i18n="<?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'topbar.plan_btn_pro' : 'topbar.plan_btn_upgrade' ?>">
                                    <?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'Chi tiết gói' : 'Nâng cấp PRO' ?>
                                </button>
                            </div>

                            <div class="user-dd-divider"></div>

                            <!-- Menu chức năng thật -->
                            <button type="button" class="user-dd-item" onclick="openAccountModal()">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <span data-i18n="topbar.menu_account">Thông tin tài khoản</span>
                            </button>
                            <button type="button" class="user-dd-item" onclick="switchView('upgrade'); closeUserDropdown();">
                                <svg class="svg-icon svg-icon-sm text-warning" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                <span data-i18n="topbar.menu_upgrade">Nâng cấp Gói dịch vụ</span>
                            </button>
                            <button type="button" class="user-dd-item" onclick="switchView('settings')">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                <span data-i18n="topbar.menu_settings">Cài đặt hệ thống</span>
                            </button>
                            <button type="button" class="user-dd-item" onclick="switchView('history')">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <span data-i18n="topbar.menu_history">Lịch sử xuất tệp</span>
                            </button>

                            <div class="user-dd-divider"></div>

                            <button type="button" class="user-dd-item text-danger" onclick="logoutUser()">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                <span data-i18n="topbar.menu_logout">Đăng xuất</span>
                            </button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="guest-topbar-actions">
                        <button type="button" class="btn-guest-login" onclick="openAuthModal('login')">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                            <span data-i18n="nav.guest_login">Đăng nhập</span>
                        </button>
                        <button type="button" class="btn-guest-register" onclick="openAuthModal('register')">
                            <span data-i18n="nav.guest_register">Đăng ký</span>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </header>

            <!-- VIEW 1: TRANG CHỦ DASHBOARD -->
            <div id="view-home" class="workspace-view <?= in_array($currentView, ['home', 'documents']) ? 'active' : '' ?>">
                <!-- TOP ROW: Hero Banner (Left) + 4 Quick Actions in 2x2 Grid (Right) -->
                <div class="top-dashboard-row">
                    <!-- Hero Banner -->
                    <section class="hero-banner">
                        <div class="hero-text">
                            <h2 data-i18n-html="home.hero_title">Xử lý PDF nhanh chóng,<br>linh hoạt và dễ dàng</h2>
                            <p data-i18n="home.hero_desc">Ghép, tách, sắp xếp, xoay và chỉnh sửa tài liệu PDF chỉ với vài thao tác đơn giản.</p>
                            <div class="feature-badges">
                                <span class="feature-badge-item">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                                    <span data-i18n="home.badge_secure">An toàn (Bảo mật dữ liệu)</span>
                                </span>
                                <span class="feature-badge-item">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                    <span data-i18n="home.badge_fast">Nhanh chóng (Xử lý trong vài giây)</span>
                                </span>
                                <span class="feature-badge-item">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <span data-i18n="home.badge_easy">Dễ sử dụng (Giao diện trực quan)</span>
                                </span>
                            </div>
                        </div>
                        <div class="hero-illu">
                            <div class="hero-3d-pdf">
                                <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.8">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="9" y1="13" x2="15" y2="13"/>
                                    <line x1="9" y1="17" x2="13" y2="17"/>
                                </svg>
                                <div class="pdf-stamp">PDF</div>
                            </div>
                        </div>
                    </section>

                    <!-- Quick Actions 2x2 Grid (Khớp 100% hình mẫu giaodien_demo.png) -->
                    <section class="quick-actions-2x2">
                        <!-- Card 1: Chỉnh sửa PDF chuyên nghiệp -->
                        <a href="<?= $baseUrl ?>/editor" class="action-card" onclick="switchView('editor', event)">
                            <div class="action-main">
                                <div class="action-icon-box blue">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </div>
                                <div class="action-details">
                                    <h3 data-i18n="home.qa_editor_title">Chỉnh sửa PDF</h3>
                                    <p data-i18n="home.qa_editor_desc">Thêm chữ, vẽ, ký số, con dấu & ghi chú</p>
                                </div>
                            </div>
                            <span class="action-arrow">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        </a>

                        <!-- Card 2: Quản lý & Sắp xếp trang -->
                        <a href="<?= $baseUrl ?>/editor" class="action-card" onclick="switchView('editor', event); setTimeout(openPageManagerModal, 200);">
                            <div class="action-main">
                                <div class="action-icon-box green">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                                </div>
                                <div class="action-details">
                                    <h3 data-i18n="home.qa_pages_title">Quản lý trang PDF</h3>
                                    <p data-i18n="home.qa_pages_desc">Sắp xếp, xoay góc, nhân bản & xóa trang</p>
                                </div>
                            </div>
                            <span class="action-arrow">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        </a>

                        <!-- Card 3: Ghép & Tách tài liệu -->
                        <a href="<?= $baseUrl ?>/editor" class="action-card" onclick="switchView('editor', event); setTimeout(openPageManagerModal, 200);">
                            <div class="action-main">
                                <div class="action-icon-box purple">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                </div>
                                <div class="action-details">
                                    <h3 data-i18n="home.qa_merge_title">Ghép & Tách tài liệu</h3>
                                    <p data-i18n="home.qa_merge_desc">Nhập file ghép nối hoặc trích xuất nhóm trang</p>
                                </div>
                            </div>
                            <span class="action-arrow">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        </a>

                        <!-- Card 4: Nâng cấp gói PRO -->
                        <a href="<?= $baseUrl ?>/upgrade" class="action-card" onclick="switchView('upgrade', event)">
                            <div class="action-main">
                                <div class="action-icon-box orange">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                </div>
                                <div class="action-details">
                                    <h3 data-i18n="home.qa_pro_title">Nâng cấp gói PRO</h3>
                                    <p data-i18n="home.qa_pro_desc">Dung lượng không giới hạn & công cụ VIP</p>
                                </div>
                            </div>
                            <span class="action-arrow">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        </a>
                    </section>
                </div>

                <!-- MIDDLE SECTION: Documents Workspace (Left) + Quick Stats & Guide (Right) -->
                <div class="dashboard-grid" id="documentsSection">
                    <!-- Left Column -->
                    <div class="dashboard-main-col">
                        <!-- Toolbar & Tabs -->
                        <div class="section-toolbar">
                            <div class="tabs-group">
                                <button class="tab-btn active" id="tabDocList" onclick="toggleDocTab('list')" data-i18n="home.tab_doc_list">Danh sách tài liệu</button>
                                <button class="tab-btn" id="tabProcessing" onclick="toggleDocTab('processing')" data-i18n="home.tab_processing">Đang xử lý (2)</button>
                            </div>
                            <div class="toolbar-actions">
                                <button class="btn-primary" onclick="openUploadModal()">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    <span data-i18n="home.btn_upload">Tải lên tệp PDF</span>
                                </button>
                                <button class="btn-outline" onclick="showToast('Tùy chọn hiển thị nâng cao', 'info')" title="Tùy chọn">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- 6 Document Cards Row with Realistic Scanned Thumbnails -->
                        <div class="document-cards-row" id="docCardsContainer">
                            <!-- Card 1 -->
                            <div class="doc-card" onclick="openDocActions('Tai_lieu_hoc_tap.pdf', 8)">
                                <div class="doc-thumbnail-realistic">
                                    <div class="doc-thumb-header"></div>
                                    <div class="doc-thumb-line"></div>
                                    <div class="doc-thumb-line medium"></div>
                                    <div class="doc-thumb-image-box">
                                        <svg class="svg-icon svg-icon-sm" style="color: #94A3B8;" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    </div>
                                    <span class="pdf-mini-tag">PDF</span>
                                </div>
                                <div class="doc-name" title="Tai_lieu_hoc_tap.pdf">Tai_lieu_hoc_tap.pdf</div>
                                <div class="doc-info">8 trang • 2.1 MB</div>
                                <span class="status-pill success"><span class="status-dot"></span> <span data-i18n="home.status_completed">Hoàn tất</span></span>
                            </div>

                            <!-- Card 2 -->
                            <div class="doc-card" onclick="openDocActions('De_cuong_mon_hoc.pdf', 24)">
                                <div class="doc-thumbnail-realistic">
                                    <div class="doc-thumb-header" style="width: 80%;"></div>
                                    <div class="doc-thumb-line"></div>
                                    <div class="doc-thumb-line"></div>
                                    <div class="doc-thumb-line short"></div>
                                    <div class="doc-thumb-line medium"></div>
                                    <span class="pdf-mini-tag">PDF</span>
                                </div>
                                <div class="doc-name" title="De_cuong_mon_hoc.pdf">De_cuong_mon_hoc.pdf</div>
                                <div class="doc-info">24 trang • 4.8 MB</div>
                                <span class="status-pill success"><span class="status-dot"></span> <span data-i18n="home.status_completed">Hoàn tất</span></span>
                            </div>

                            <!-- Card 3 -->
                            <div class="doc-card" onclick="openDocActions('Bai_giang_Tin_hoc.pdf', 18)">
                                <div class="doc-thumbnail-realistic">
                                    <div class="doc-thumb-header" style="background: #E0E7FF; width: 70%;"></div>
                                    <div class="doc-thumb-image-box" style="height: 38px;">
                                        <svg class="svg-icon svg-icon-sm" style="color: #818CF8;" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                    </div>
                                    <div class="doc-thumb-line short"></div>
                                    <span class="pdf-mini-tag">PDF</span>
                                </div>
                                <div class="doc-name" title="Bai_giang_Tin_hoc.pdf">Bai_giang_Tin_hoc.pdf</div>
                                <div class="doc-info">18 trang • 3.1 MB</div>
                                <span class="status-pill success"><span class="status-dot"></span> <span data-i18n="home.status_completed">Hoàn tất</span></span>
                            </div>

                            <!-- Card 4 -->
                            <div class="doc-card" onclick="openDocActions('Ho_so_tot_nghiep.pdf', 12)">
                                <div class="doc-thumbnail-realistic">
                                    <div class="doc-thumb-header" style="width: 50%;"></div>
                                    <div class="doc-thumb-line"></div>
                                    <div class="doc-thumb-line medium"></div>
                                    <div class="doc-thumb-line"></div>
                                    <span class="pdf-mini-tag">PDF</span>
                                </div>
                                <div class="doc-name" title="Ho_so_tot_nghiep.pdf">Ho_so_tot_nghiep.pdf</div>
                                <div class="doc-info">12 trang • 2.4 MB</div>
                                <span class="status-pill info"><span class="status-dot"></span> <span data-i18n="home.status_processed">Đã xử lý</span></span>
                            </div>

                            <!-- Card 5 (File rỗng/Lỗi) -->
                            <div class="doc-card card-error" onclick="showToast('Tệp File_rong.pdf (0 bytes) đã bị hệ thống từ chối an toàn!', 'error')">
                                <div class="doc-thumbnail-realistic">
                                    <div class="doc-thumb-empty">
                                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    </div>
                                </div>
                                <div class="doc-name" title="File_rong.pdf">File_rong.pdf</div>
                                <div class="doc-info">0 trang • 0 KB</div>
                                <span class="status-pill danger"><span class="status-dot"></span> <span data-i18n="home.status_error">Lỗi</span></span>
                            </div>

                            <!-- Card 6 -->
                            <div class="doc-card" onclick="openDocActions('Tai_lieu_tham_khao.pdf', 8)">
                                <div class="doc-thumbnail-realistic">
                                    <div class="doc-thumb-header" style="width: 65%;"></div>
                                    <div class="doc-thumb-line"></div>
                                    <div class="doc-thumb-line"></div>
                                    <div class="doc-thumb-line short"></div>
                                    <span class="pdf-mini-tag">PDF</span>
                                </div>
                                <div class="doc-name" title="Tai_lieu_tham_khao.pdf">Tai_lieu_tham_khao.pdf</div>
                                <div class="doc-info">15 trang • 2.7 MB</div>
                                <span class="status-pill success"><span class="status-dot"></span> <span data-i18n="home.status_completed">Hoàn tất</span></span>
                            </div>
                        </div>

                        <!-- Recent Documents Table -->
                        <div class="table-section">
                            <div class="table-header">
                                <h3 class="table-title" data-i18n="home.recent_title">Tài liệu gần đây</h3>
                                <a href="javascript:void(0)" class="storage-upgrade" style="margin: 0;" onclick="showToast('Xem danh mục đầy đủ tất cả tài liệu', 'info')" data-i18n="home.recent_view_all">Xem tất cả →</a>
                            </div>
                            <div class="table-responsive">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th width="32"><input type="checkbox" id="checkAllDocs"></th>
                                        <th data-i18n="home.th_filename">Tên tệp</th>
                                        <th data-i18n="home.th_pages">Số trang</th>
                                        <th data-i18n="home.th_size">Dung lượng</th>
                                        <th data-i18n="home.th_created">Ngày tạo</th>
                                        <th data-i18n="home.th_status">Trạng thái</th>
                                        <th data-i18n="home.th_actions">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="checkbox" class="doc-checkbox"></td>
                                        <td>
                                            <div class="file-cell">
                                                <div class="file-icon-pdf-badge">PDF</div>
                                                <span>Ho_so_tot_nghiep.pdf</span>
                                            </div>
                                        </td>
                                        <td>12</td>
                                        <td>2.4 MB</td>
                                        <td>02/10/2025 14:32</td>
                                        <td><span class="status-pill info"><span class="status-dot"></span> <span data-i18n="home.status_processed">Đã xử lý</span></span></td>
                                        <td>
                                            <div class="actions-cell">
                                                <button class="btn-table-action" title="Xem nhanh" onclick="showToast('Xem trước: Ho_so_tot_nghiep.pdf', 'info')">
                                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </button>
                                                <button class="btn-table-action" title="Thao tác" onclick="openDocActions('Ho_so_tot_nghiep.pdf', 12)">
                                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><input type="checkbox" class="doc-checkbox"></td>
                                        <td>
                                            <div class="file-cell">
                                                <div class="file-icon-pdf-badge">PDF</div>
                                                <span>Bai_giang_Tin_hoc.pdf</span>
                                            </div>
                                        </td>
                                        <td>18</td>
                                        <td>3.1 MB</td>
                                        <td>01/10/2025 16:20</td>
                                        <td><span class="status-pill info"><span class="status-dot"></span> <span data-i18n="home.status_processing">Đang xử lý</span></span></td>
                                        <td>
                                            <div class="actions-cell">
                                                <button class="btn-table-action" title="Xem nhanh" onclick="showToast('Xem trước: Bai_giang_Tin_hoc.pdf', 'info')">
                                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </button>
                                                <button class="btn-table-action" title="Thao tác" onclick="openDocActions('Bai_giang_Tin_hoc.pdf', 18)">
                                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><input type="checkbox" class="doc-checkbox"></td>
                                        <td>
                                            <div class="file-cell">
                                                <div class="file-icon-pdf-badge">PDF</div>
                                                <span>Tai_lieu_tham_khao.pdf</span>
                                            </div>
                                        </td>
                                        <td>8</td>
                                        <td>1.2 MB</td>
                                        <td>29/09/2025 10:05</td>
                                        <td><span class="status-pill success"><span class="status-dot"></span> <span data-i18n="home.status_completed">Hoàn tất</span></span></td>
                                        <td>
                                            <div class="actions-cell">
                                                <button class="btn-table-action" title="Xem nhanh" onclick="showToast('Xem trước: Tai_lieu_tham_khao.pdf', 'info')">
                                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </button>
                                                <button class="btn-table-action" title="Thao tác" onclick="openDocActions('Tai_lieu_tham_khao.pdf', 8)">
                                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><input type="checkbox" class="doc-checkbox"></td>
                                        <td>
                                            <div class="file-cell">
                                                <div class="file-icon-pdf-badge">PDF</div>
                                                <span>De_cuong_mon_hoc.pdf</span>
                                            </div>
                                        </td>
                                        <td>24</td>
                                        <td>4.8 MB</td>
                                        <td>28/09/2025 09:17</td>
                                        <td><span class="status-pill success"><span class="status-dot"></span> <span data-i18n="home.status_completed">Hoàn tất</span></span></td>
                                        <td>
                                            <div class="actions-cell">
                                                <button class="btn-table-action" title="Xem nhanh" onclick="showToast('Xem trước: De_cuong_mon_hoc.pdf', 'info')">
                                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </button>
                                                <button class="btn-table-action" title="Thao tác" onclick="openDocActions('De_cuong_mon_hoc.pdf', 24)">
                                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><input type="checkbox" class="doc-checkbox"></td>
                                        <td>
                                            <div class="file-cell">
                                                <div class="file-icon-pdf-badge">PDF</div>
                                                <span>Tai_lieu_hoc_tap.pdf</span>
                                            </div>
                                        </td>
                                        <td>15</td>
                                        <td>2.7 MB</td>
                                        <td>27/09/2025 20:41</td>
                                        <td><span class="status-pill success"><span class="status-dot"></span> <span data-i18n="home.status_completed">Hoàn tất</span></span></td>
                                        <td>
                                            <div class="actions-cell">
                                                <button class="btn-table-action" title="Xem nhanh" onclick="showToast('Xem trước: Tai_lieu_hoc_tap.pdf', 'info')">
                                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </button>
                                                <button class="btn-table-action" title="Thao tác" onclick="openDocActions('Tai_lieu_hoc_tap.pdf', 8)">
                                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>

                            <div class="table-footer">
                                <div data-i18n="home.table_showing">Hiển thị 1 - 5 / 6 tài liệu</div>
                                <div class="pagination-controls">
                                    <button class="page-btn">‹</button>
                                    <button class="page-btn active">1</button>
                                    <button class="page-btn">2</button>
                                    <button class="page-btn">›</button>
                                </div>
                                <div data-i18n="home.table_per_page">10 / trang</div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Quick Stats & Guide -->
                    <div class="dashboard-side-col">
                        <!-- Quick Stats 2x2 with Solid Color SVG Boxes -->
                        <div class="side-card">
                            <h4 class="side-card-title" data-i18n="home.stats_title">Thống kê nhanh</h4>
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-icon-sq blue">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    </div>
                                    <div class="stat-number">6</div>
                                    <div class="stat-label" data-i18n="home.stat_total_docs">Tổng số tài liệu</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon-sq green">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                                    </div>
                                    <div class="stat-number">40</div>
                                    <div class="stat-label" data-i18n="home.stat_total_pages">Tổng số trang</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon-sq purple">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    </div>
                                    <div class="stat-number">5</div>
                                    <div class="stat-label" data-i18n="home.stat_processed">Đã xử lý</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon-sq red">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    </div>
                                    <div class="stat-number">1</div>
                                    <div class="stat-label" data-i18n="home.stat_errors">Lỗi</div>
                                </div>
                            </div>
                        </div>

                        <!-- Guide List -->
                        <div class="side-card">
                            <h4 class="side-card-title" data-i18n="home.guide_title">Hướng dẫn sử dụng</h4>
                            <ul class="guide-list">
                                <li class="guide-step">
                                    <span class="step-num">1</span>
                                    <span data-i18n="home.guide_step1">Tải lên nhiều tệp PDF</span>
                                </li>
                                <li class="guide-step">
                                    <span class="step-num">2</span>
                                    <span data-i18n="home.guide_step2">Chọn, sắp xếp, xoay trang</span>
                                </li>
                                <li class="guide-step">
                                    <span class="step-num">3</span>
                                    <span data-i18n="home.guide_step3">Xem trước và kiểm tra kế hoạch</span>
                                </li>
                                <li class="guide-step">
                                    <span class="step-num">4</span>
                                    <span data-i18n="home.guide_step4">Ghép hoặc tách tài liệu</span>
                                </li>
                                <li class="guide-step">
                                    <span class="step-num">5</span>
                                    <span data-i18n="home.guide_step5">Tải xuống kết quả</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Promo Card -->
                        <div class="promo-card">
                            <h4 data-i18n-html="home.promo_title">Làm việc hiệu quả hơn<br>với PDF Manager</h4>
                            <p data-i18n="home.promo_desc">Tối ưu hóa tài liệu học tập và hồ sơ tốt nghiệp dễ dàng.</p>
                            <button class="btn-promo" onclick="switchView('reorder')">
                                <span data-i18n="home.promo_btn">Xem chi tiết</span>
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                            </button>
                            <svg class="promo-folder-svg" viewBox="0 0 100 80" fill="none">
                                <path d="M10 20C10 14.4772 14.4772 10 20 10H38L48 22H80C85.5228 22 90 26.4772 90 32V65C90 70.5228 85.5228 75 80 75H20C14.4772 75 10 70.5228 10 65V20Z" fill="#93C5FD"/>
                                <path d="M15 30C15 25.5817 18.5817 22 23 22H77C81.4183 22 85 25.5817 85 30V65C85 69.4183 81.4183 73 77 73H23C18.5817 73 15 69.4183 15 65V30Z" fill="#3B82F6"/>
                                <rect x="30" y="32" width="40" height="24" rx="4" fill="white"/>
                                <rect x="36" y="38" width="18" height="3" rx="1.5" fill="#DC2626"/>
                                <rect x="36" y="44" width="28" height="2" rx="1" fill="#CBD5E1"/>
                                <rect x="36" y="48" width="24" height="2" rx="1" fill="#CBD5E1"/>
                            </svg>
                        </div>
                    </div>
                </div>


            </div>

            <!-- VIEW: CHỈNH SỬA PDF (PDFAID STYLE 100%) -->
            <div id="view-editor" class="workspace-view <?= $currentView === 'editor' ? 'active' : '' ?>">
                <!-- STATE 1: UPLOAD & TÀI LIỆU MẪU BAN ĐẦU (MẶC ĐỊNH KHI VÀO TRANG) -->
                <div id="editorUploadState" class="editor-upload-state" style="display: flex;">
                    <div class="editor-upload-card">
                        <div class="upload-state-header">
                            <div class="upload-hero-icon">
                                <svg class="svg-icon svg-icon-xl" viewBox="0 0 24 24" style="width: 48px; height: 48px; color: var(--primary);">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="12" y1="18" x2="12" y2="12"/>
                                    <polyline points="9 15 12 12 15 15"/>
                                </svg>
                            </div>
                            <h2 class="upload-state-title" data-i18n="editor.upload_hero_title">Chỉnh sửa tệp PDF trực tuyến chuyên nghiệp</h2>
                            <p class="upload-state-subtitle" data-i18n="editor.upload_hero_subtitle">Thêm văn bản, vẽ tự do, ký số điện tử, đóng dấu phê duyệt và sắp xếp trang toàn diện</p>
                        </div>

                        <!-- Dropzone chính -->
                        <div class="upload-dropzone" id="editorHeroDropzone" onclick="document.getElementById('editorHeroFileInput').click()" ondragover="event.preventDefault(); this.classList.add('dragover');" ondragleave="this.classList.remove('dragover');" ondrop="handleHeroFileDrop(event)">
                            <div class="dropzone-cloud-icon">
                                <svg class="svg-icon" style="width: 52px; height: 52px; color: #2563EB;" viewBox="0 0 24 24">
                                    <path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/>
                                    <path d="M12 12v9"/>
                                    <path d="m8 16 4-4 4 4"/>
                                </svg>
                            </div>
                            <div class="dropzone-title" data-i18n="editor.dropzone_title">Kéo & Thả tệp PDF của bạn vào đây</div>
                            <div class="dropzone-sub" data-i18n="editor.dropzone_sub">hoặc bấm vào khung để duyệt tệp từ máy tính</div>
                            <button type="button" class="btn-primary btn-upload-hero" onclick="event.stopPropagation(); document.getElementById('editorHeroFileInput').click();">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <span data-i18n="editor.btn_upload_hero">Chọn tệp PDF tải lên</span>
                            </button>
                            <input type="file" id="editorHeroFileInput" accept="application/pdf" style="display: none;" onchange="handleHeroFileInput(event)">
                            <div class="dropzone-limits" data-i18n="editor.dropzone_limits">Hỗ trợ PDF tối đa 50 MB • Xử lý bảo mật an toàn 100%</div>
                        </div>

                        <!-- Quick Samples Grid -->
                        <div class="upload-sample-section">
                            <div class="sample-section-header">
                                <span class="sample-section-line"></span>
                                <span class="sample-section-text" data-i18n="editor.sample_divider">HOẶC CHỌN TÀI LIỆU CÓ SẴN ĐỂ CHỈNH SỬA NGAY</span>
                                <span class="sample-section-line"></span>
                            </div>

                            <div class="upload-sample-grid">
                                <div class="sample-card" onclick="loadSampleDocument('Tai_lieu_hoc_tap.pdf', 11, 'Báo cáo nghiên cứu mã nguồn mở')">
                                    <div class="sample-card-icon blue">
                                        <svg class="svg-icon svg-icon-md" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                    </div>
                                    <div class="sample-card-info">
                                        <div class="sample-name">Tai_lieu_hoc_tap.pdf</div>
                                        <div class="sample-meta" data-i18n="editor.sample1_meta">11 trang • Báo cáo nghiên cứu</div>
                                    </div>
                                    <span class="sample-arrow">→</span>
                                </div>

                                <div class="sample-card" onclick="loadSampleDocument('De_cuong_mon_hoc.pdf', 6, 'Đề cương môn học chi tiết')">
                                    <div class="sample-card-icon green">
                                        <svg class="svg-icon svg-icon-md" viewBox="0 0 24 24"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><polyline points="10 2 10 10 13 7 16 10 16 2"/></svg>
                                    </div>
                                    <div class="sample-card-info">
                                        <div class="sample-name">De_cuong_mon_hoc.pdf</div>
                                        <div class="sample-meta" data-i18n="editor.sample2_meta">6 trang • Đề cương học phần</div>
                                    </div>
                                    <span class="sample-arrow">→</span>
                                </div>

                                <div class="sample-card" onclick="loadSampleDocument('Do_an_tot_nghiep_mau.pdf', 15, 'Đồ án tốt nghiệp cử nhân')">
                                    <div class="sample-card-icon purple">
                                        <svg class="svg-icon svg-icon-md" viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                    </div>
                                    <div class="sample-card-info">
                                        <div class="sample-name">Do_an_tot_nghiep_mau.pdf</div>
                                        <div class="sample-meta" data-i18n="editor.sample3_meta">15 trang • Luận văn đồ án</div>
                                    </div>
                                    <span class="sample-arrow">→</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STATE 2: GIAO DIỆN CHỈNH SỬA TOÀN NĂNG (HIỂN THỊ KHI ĐÃ CÓ TÀI LIỆU) -->
                <div id="editorWorkspaceContainer" class="editor-container-pdfaid" style="display: none;">
                    <!-- Top Ribbon Toolbar -->
                    <div class="editor-top-ribbon">
                        <div class="editor-ribbon-group history-group">
                            <button class="ribbon-btn" id="btnEditorUndo" title="Hoàn tác (Ctrl+Z)" onclick="editorUndo()">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
                            </button>
                            <button class="ribbon-btn" id="btnEditorRedo" title="Làm lại (Ctrl+Y)" onclick="editorRedo()">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"/></svg>
                            </button>
                        </div>

                        <div class="editor-ribbon-divider"></div>

                        <!-- Tool Buttons matching pdfaid -->
                        <div class="editor-ribbon-group tools-group">
                            <button class="ribbon-tool-btn active" data-tool="add-text" onclick="setEditorTool('add-text')" title="Thêm văn bản mới">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
                                <span data-i18n="editor.tool_add_text">Thêm văn bản</span>
                            </button>
                            <button class="ribbon-tool-btn" data-tool="edit-text" onclick="setEditorTool('edit-text')" title="Chỉnh sửa văn bản có sẵn trong PDF">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                <span data-i18n="editor.tool_edit_text">Chỉnh sửa văn bản</span>
                            </button>
                            <button class="ribbon-tool-btn" data-tool="signature" onclick="openSignatureModal()" title="Tạo hoặc chèn chữ ký">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="m20.5 14.5-8-8-5 5 8 8Z"/><path d="M7.5 11.5 2 17v5h5l5.5-5.5"/><circle cx="17.5" cy="6.5" r="2.5"/></svg>
                                <span data-i18n="editor.tool_sign">Ký tên</span>
                            </button>
                            <button class="ribbon-tool-btn" data-tool="draw" onclick="setEditorTool('draw')" title="Vẽ tự do nét mực">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                                <span data-i18n="editor.tool_draw">Vẽ</span>
                            </button>
                            
                            <!-- Shapes Dropdown Menu -->
                            <div class="ribbon-dropdown-wrapper">
                                <button class="ribbon-tool-btn dropdown-toggle" id="btnShapesDropdown" onclick="toggleShapesDropdown(event)" title="Đường thẳng & Hình học">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="5" y1="19" x2="19" y2="5"/></svg>
                                    <span id="currentShapeLabel" data-i18n="editor.shape_line">Đường thẳng</span>
                                    <svg class="svg-icon svg-icon-xs dropdown-caret" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                                </button>
                                <div class="ribbon-sub-menu" id="shapesMenu">
                                    <div class="sub-menu-item" onclick="selectShape('line', 'Đường thẳng')">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="5" y1="19" x2="19" y2="5"/></svg>
                                        <span data-i18n="editor.shape_line">Đường thẳng</span>
                                    </div>
                                    <div class="sub-menu-item" onclick="selectShape('arrow', 'Mũi tên')">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                        <span data-i18n="editor.shape_arrow">Mũi tên</span>
                                    </div>
                                    <div class="sub-menu-item" onclick="selectShape('rect', 'Hình chữ nhật')">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/></svg>
                                        <span data-i18n="editor.shape_rect">Hình chữ nhật</span>
                                    </div>
                                    <div class="sub-menu-item" onclick="selectShape('circle', 'Hình tròn')">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
                                        <span data-i18n="editor.shape_circle">Hình tròn</span>
                                    </div>
                                    <div class="sub-menu-item" onclick="selectShape('polygon', 'Đa giác')">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polygon points="12 2 22 8.5 22 15.5 12 22 2 15.5 2 8.5 12 2"/></svg>
                                        <span data-i18n="editor.shape_polygon">Đa giác</span>
                                    </div>
                                </div>
                            </div>

                            <button class="ribbon-tool-btn" data-tool="highlight" onclick="setEditorTool('highlight')" title="Tô sáng / Bút nhớ">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="m9 11-6 6v3h3l6-6"/><path d="m22 12-4.6 4.6a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L14 4"/></svg>
                                <span data-i18n="editor.tool_highlight">Tô sáng</span>
                            </button>
                            <button class="ribbon-tool-btn" data-tool="image" onclick="triggerEditorImageUpload()" title="Chèn hình ảnh">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                <span data-i18n="editor.tool_image">Hình ảnh</span>
                            </button>
                            <button class="ribbon-tool-btn" data-tool="stamp" onclick="openStampModal()" title="Con dấu (Đã duyệt, Bản nháp...)">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M5 22h14"/><path d="M19.27 13.73A2.5 2.5 0 0 0 17.5 9h-11A2.5 2.5 0 0 0 4.73 13.73L7 17h10l2.27-3.27z"/><path d="M12 9V2"/></svg>
                                <span data-i18n="editor.tool_stamp">Con dấu</span>
                            </button>
                            <button class="ribbon-tool-btn" data-tool="link" onclick="setEditorTool('link')" title="Thêm liên kết">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                <span data-i18n="editor.tool_link">Liên kết</span>
                            </button>
                            <button class="ribbon-tool-btn" data-tool="note" onclick="setEditorTool('note')" title="Thêm ghi chú">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                <span data-i18n="editor.tool_note">Ghi chú</span>
                            </button>
                        </div>

                        <div class="editor-ribbon-divider"></div>

                        <!-- Management & Export Actions -->
                        <div class="editor-ribbon-group actions-group">
                            <button class="ribbon-action-btn file-switch-btn" onclick="showEditorUploadState()" title="Đổi tệp PDF khác">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                                <span data-i18n="editor.btn_switch_file">Đổi tệp</span>
                            </button>
                            <button class="ribbon-action-btn" id="btnOpenPageManagerModal" onclick="openPageManagerModal()" title="Mở Trình quản lý trang toàn diện">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                                <span data-i18n="editor.btn_page_manager">Quản lý trang</span>
                            </button>
                            <button class="ribbon-icon-btn" onclick="printPdfDocument()" title="In ấn tài liệu">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            </button>
                            <button class="ribbon-icon-btn" onclick="toggleEditorSearch()" title="Tìm kiếm trong tài liệu">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            </button>
                            <button class="btn-complete-cta" onclick="finishAndSaveDocument()" title="Hoàn thành chỉnh sửa và lưu PDF">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                <span data-i18n="editor.btn_finish">Hoàn thành</span>
                            </button>
                        </div>
                    </div>

                    <!-- Editor Sub-Ribbon (Color swatches & Vector Icons) -->
                    <div class="editor-sub-ribbon">
                        <div class="sub-ribbon-colors">
                            <span class="sub-ribbon-label" data-i18n="editor.colors_label">Màu sắc:</span>
                            <div class="color-palette">
                                <button class="color-swatch active" style="--c: #000000;" onclick="setEditorColor('#000000')" title="Đen"></button>
                                <button class="color-swatch" style="--c: #EF4444;" onclick="setEditorColor('#EF4444')" title="Đỏ"></button>
                                <button class="color-swatch" style="--c: #3B82F6;" onclick="setEditorColor('#3B82F6')" title="Xanh dương"></button>
                                <button class="color-swatch" style="--c: #10B981;" onclick="setEditorColor('#10B981')" title="Xanh lá"></button>
                                <button class="color-swatch" style="--c: #F59E0B;" onclick="setEditorColor('#F59E0B')" title="Vàng cam"></button>
                                <button class="color-swatch" style="--c: #8B5CF6;" onclick="setEditorColor('#8B5CF6')" title="Tím"></button>
                                <button class="color-swatch" style="--c: #64748B;" onclick="setEditorColor('#64748B')" title="Xám"></button>
                                <input type="color" id="editorCustomColorPicker" class="custom-color-picker" title="Tùy chọn màu..." onchange="setEditorColor(this.value)">
                            </div>
                        </div>

                        <div class="sub-ribbon-divider"></div>

                        <!-- Professional Vector Annotation Icons (Replacing plastic emoji stickers) -->
                        <div class="sub-ribbon-stickers">
                            <span class="sub-ribbon-label" data-i18n="editor.stickers_label">Biểu tượng:</span>
                            <div class="vector-icon-palette">
                                <button class="vector-icon-btn" onclick="addVectorIcon('chat')" title="Bình luận / Chat">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                </button>
                                <button class="vector-icon-btn" onclick="addVectorIcon('flag')" title="Cờ đánh dấu">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                                </button>
                                <button class="vector-icon-btn" onclick="addVectorIcon('arrow')" title="Mũi tên chỉ dẫn">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                </button>
                                <button class="vector-icon-btn" onclick="addVectorIcon('check')" title="Đã duyệt / Hợp lệ">
                                    <svg class="svg-icon svg-icon-sm text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                </button>
                                <button class="vector-icon-btn" onclick="addVectorIcon('cross')" title="Hủy / Loại bỏ">
                                    <svg class="svg-icon svg-icon-sm text-danger" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                                <button class="vector-icon-btn" onclick="addVectorIcon('star')" title="Quan trọng / Sao">
                                    <svg class="svg-icon svg-icon-sm text-warning" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                </button>
                                <button class="vector-icon-btn" onclick="addVectorIcon('shield')" title="Bảo mật / Xác thực">
                                    <svg class="svg-icon svg-icon-sm text-primary" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                </button>
                                <button class="vector-icon-btn" onclick="addVectorIcon('pin')" title="Ghim điểm nhấn">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                </button>
                                <button class="vector-icon-btn" onclick="addVectorIcon('bulb')" title="Ý tưởng / Sáng tạo">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="9" y1="18" x2="15" y2="18"/><line x1="10" y1="22" x2="14" y2="22"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5.76.76 1.23 1.52 1.41 2.5"/></svg>
                                </button>
                                <button class="vector-icon-btn" onclick="addVectorIcon('warning')" title="Cảnh báo / Lưu ý">
                                    <svg class="svg-icon svg-icon-sm text-warning" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                </button>
                            </div>
                        </div>

                        <div class="sub-ribbon-divider"></div>

                        <div class="sub-ribbon-properties" id="toolPropertiesPanel">
                            <span class="sub-ribbon-label" data-i18n="editor.stroke_label">Cỡ chữ/nét:</span>
                            <select id="editorStrokeOrFontSize" class="ribbon-select" onchange="updateStrokeOrFontSize(this.value)">
                                <option value="12" data-i18n="editor.font_12">12 px (Nhỏ)</option>
                                <option value="14" data-i18n="editor.font_14" selected>14 px (Chuẩn)</option>
                                <option value="18" data-i18n="editor.font_18">18 px (Vừa)</option>
                                <option value="24" data-i18n="editor.font_24">24 px (Lớn)</option>
                                <option value="32" data-i18n="editor.font_32">32 px (Tiêu đề)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Main Editor Body with Thumbnails Strip & Viewport -->
                    <div class="editor-main-workspace">
                        <!-- Left Thumbnails Strip -->
                        <aside class="editor-left-strip" id="editorThumbnailsAside">
                            <div class="strip-header">
                                <div class="strip-header-top">
                                    <span class="strip-title" data-i18n="editor.strip_title">Trang tài liệu</span>
                                    <span class="strip-count" id="stripPageCountBadge">11 trang</span>
                                </div>
                                <div class="strip-add-dropdown-wrapper">
                                    <button class="btn-strip-add" onclick="toggleAddPageDropdown(event)">
                                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                        <span data-i18n="editor.btn_add_page">Thêm trang</span>
                                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                                    </button>
                                    <div class="strip-add-menu" id="stripAddMenu">
                                        <button class="strip-add-option" onclick="insertBlankPage()">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="9" y1="12" x2="15" y2="12"/></svg>
                                            <span data-i18n="editor.opt_blank_page">Trang trắng mới</span>
                                        </button>
                                        <button class="strip-add-option" onclick="triggerImportPdfPage()">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            <span data-i18n="editor.opt_import_file">Nhập từ tệp tin...</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden file input for importing PDF into editor -->
                            <input type="file" id="editorFileInput" accept="application/pdf,image/*" style="display: none;" onchange="handleEditorFileImport(event)">

                            <!-- Thumbnails Scrollable List -->
                            <div class="strip-thumbnails-list" id="editorThumbnailsList">
                                <!-- Rendered dynamically by JS -->
                            </div>
                        </aside>

                        <!-- Center Canvas Viewport -->
                        <main class="editor-viewport" id="editorViewport" ondragover="event.preventDefault()" ondrop="handleEditorFileDrop(event)">
                            <div class="pdf-canvas-container" id="pdfCanvasContainer">
                                <!-- The Paper Sheet matching pdfaid white A4 standard -->
                                <div class="pdf-paper-page" id="pdfPaperPage" style="transform: scale(1.0); transform-origin: top center;">
                                    <div class="page-paper-content" id="pagePaperContent">
                                        <!-- Paper Header/Watermark if any -->
                                        <div class="paper-doc-sample-render" id="paperDocRender">
                                            <!-- Interactive or rendered document preview -->
                                        </div>
                                        <!-- Interactive Annotation Canvas Layer -->
                                        <div class="paper-annotation-layer" id="paperAnnotationLayer"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Floating Bottom Action Bar matching pdfaid -->
                            <div class="editor-floating-bar">
                                <div class="floating-group page-jump">
                                    <span class="floating-label" data-i18n="editor.floating_page_label">Trang:</span>
                                    <button class="floating-btn" onclick="jumpEditorPage(-1)" title="Trang trước">▲</button>
                                    <span class="floating-page-info" id="floatingPageInfo">1 / 11</span>
                                    <button class="floating-btn" onclick="jumpEditorPage(1)" title="Trang tiếp">▼</button>
                                </div>

                                <div class="floating-divider"></div>

                                <div class="floating-group zoom-group">
                                    <button class="floating-btn" onclick="zoomEditor(-0.1)" title="Thu nhỏ (Ctrl -)">−</button>
                                    <span class="floating-zoom-level" id="floatingZoomLevel" onclick="resetEditorZoom()" title="Bấm để đặt lại 100%">100%</span>
                                    <button class="floating-btn" onclick="zoomEditor(0.1)" title="Phóng to (Ctrl +)">+</button>
                                </div>

                                <div class="floating-divider"></div>

                                <div class="floating-group">
                                    <button class="floating-btn" id="btnEditorPan" onclick="toggleEditorPan()" title="Công cụ bàn tay di chuyển (Pan)">✋</button>
                                    <button class="floating-btn" onclick="fitEditorToScreen()" title="Vừa chiều rộng màn hình">⛶</button>
                                </div>
                            </div>
                        </main>
                    </div>
                </div>
            </div>

            <!-- VIEW: NÂNG CẤP GÓI (UPGRADE) -->
            <div id="view-upgrade" class="workspace-view <?= $currentView === 'upgrade' ? 'active' : '' ?>">
                <div class="pricing-container">
                    <!-- Pricing Hero Section -->
                    <div class="pricing-hero">
                        <div class="pricing-pill-badge" data-i18n="upgrade.pill_badge">✨ Nâng cấp trải nghiệm tài liệu của bạn</div>
                        <h1 class="pricing-hero-title" data-i18n="upgrade.hero_title">Chọn gói dịch vụ phù hợp với nhu cầu của bạn</h1>
                        <p class="pricing-hero-subtitle" data-i18n="upgrade.hero_subtitle">Tăng tốc xử lý tài liệu học tập, đồ án tốt nghiệp và hồ sơ nghiên cứu không giới hạn với sức mạnh của PDF Manager.</p>

                        <!-- Billing Toggle -->
                        <div class="billing-toggle-wrapper">
                            <span class="billing-cycle-label" id="labelMonthly" data-i18n="upgrade.monthly">Thanh toán Hàng tháng</span>
                            <label class="billing-switch">
                                <input type="checkbox" id="billingCycleToggle" onchange="toggleBillingCycle(this.checked)">
                                <span class="billing-slider"></span>
                            </label>
                            <span class="billing-cycle-label" id="labelYearly">
                                <span data-i18n="upgrade.yearly">Hàng năm</span> <span class="discount-badge" data-i18n="upgrade.discount">Tiết kiệm 20% 🎉</span>
                            </span>
                        </div>
                    </div>

                    <!-- Pricing Cards Grid -->
                    <div class="pricing-cards-grid">
                        <!-- FREE TIER -->
                        <div class="pricing-card <?= ($userPlan === 'FREE') ? 'current-tier' : '' ?>">
                            <div class="card-plan-header">
                                <div class="card-tier-name" data-i18n="upgrade.free_name">Miễn phí (FREE)</div>
                                <p class="card-tier-desc" data-i18n="upgrade.free_desc">Dành cho sinh viên và nhu cầu đọc, sắp xếp tài liệu cơ bản.</p>
                                <div class="card-tier-price">
                                    <span class="price-val">0 đ</span>
                                    <span class="price-period" data-i18n="upgrade.free_period">/ vĩnh viễn</span>
                                </div>
                            </div>
                            <div class="card-plan-features">
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n="upgrade.free_f1">Tối đa 5 tài liệu / ngày</span></div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n="upgrade.free_f2">Dung lượng lưu trữ 1 GB</span></div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n="upgrade.free_f3">Trình chỉnh sửa văn bản cơ bản</span></div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n="upgrade.free_f4">Ghép & tách tài liệu tiêu chuẩn</span></div>
                                <div class="feature-item disabled"><span class="feature-icon cross">✕</span> <span data-i18n="upgrade.free_f5">Không giới hạn số trang xử lý</span></div>
                                <div class="feature-item disabled"><span class="feature-icon cross">✕</span> <span data-i18n="upgrade.free_f6">Xử lý siêu tốc không watermark</span></div>
                                <div class="feature-item disabled"><span class="feature-icon cross">✕</span> <span data-i18n="upgrade.free_f7">Ký số & xác thực bảo mật</span></div>
                            </div>
                            <div class="card-plan-action">
                                <?php if ($userPlan === 'FREE'): ?>
                                    <button class="btn-tier-action btn-tier-current" disabled data-i18n="upgrade.btn_using">Đang sử dụng</button>
                                <?php else: ?>
                                    <button class="btn-tier-action btn-tier-secondary" onclick="changePlan('FREE')" data-i18n="upgrade.btn_switch_free">Chuyển về gói FREE</button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- PRO TIER (POPULAR) -->
                        <div class="pricing-card popular <?= ($userPlan === 'PRO') ? 'current-tier' : '' ?>">
                            <div class="popular-ribbon" data-i18n="upgrade.pro_ribbon">🔥 PHỔ BIẾN NHẤT</div>
                            <div class="card-plan-header">
                                <div class="card-tier-name" data-i18n="upgrade.pro_name">Chuyên nghiệp (PRO)</div>
                                <p class="card-tier-desc" data-i18n="upgrade.pro_desc">Dành cho sinh viên đồ án, nghiên cứu sinh và giảng viên cần xử lý cường độ cao.</p>
                                <div class="card-tier-price">
                                    <span class="price-val" id="proPriceValue">99.000 đ</span>
                                    <span class="price-period" id="proPricePeriod" data-i18n="upgrade.pro_period">/ tháng</span>
                                </div>
                                <div class="yearly-saving-hint" id="proYearlyHint" style="display: none;">Thanh toán 950.000 đ/năm (Tiết kiệm 238.000 đ)</div>
                            </div>
                            <div class="card-plan-features">
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n-html="upgrade.pro_f1"><strong>Không giới hạn</strong> số lượng tài liệu</span></div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n-html="upgrade.pro_f2"><strong>Dung lượng không giới hạn</strong> (Cloud SSD)</span></div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n="upgrade.pro_f3">Trình biên tập trực quan nâng cao 100%</span></div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n="upgrade.pro_f4">Trình quản lý trang thông minh (Kéo thả, xoay)</span></div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n="upgrade.pro_f5">Chữ ký điện tử & Ghi chú đóng dấu chuyên nghiệp</span></div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n-html="upgrade.pro_f6"><strong>Xử lý nhanh gấp 5 lần</strong> (Ưu tiên hàng đợi)</span></div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n="upgrade.pro_f7">Xuất file sạch đẹp, không watermark</span></div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n="upgrade.pro_f8">Hỗ trợ kỹ thuật ưu tiên 24/7</span></div>
                            </div>
                            <div class="card-plan-action">
                                <?php if ($userPlan === 'PRO'): ?>
                                    <button class="btn-tier-action btn-tier-current" disabled data-i18n="upgrade.btn_pro_current">⭐ Đang sử dụng gói PRO</button>
                                <?php else: ?>
                                    <button class="btn-tier-action btn-tier-primary" onclick="changePlan('PRO')">
                                        <span data-i18n="upgrade.btn_pro_cta">Nâng cấp PRO ngay</span>
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- ENTERPRISE TIER -->
                        <div class="pricing-card <?= ($userPlan === 'ENTERPRISE') ? 'current-tier' : '' ?>">
                            <div class="card-plan-header">
                                <div class="card-tier-name" data-i18n="upgrade.ent_name">Doanh nghiệp / Khoa viện</div>
                                <p class="card-tier-desc" data-i18n="upgrade.ent_desc">Dành cho nhóm nghiên cứu lớn, phòng ban hoặc toàn trường.</p>
                                <div class="card-tier-price">
                                    <span class="price-val" id="entPriceValue">299.000 đ</span>
                                    <span class="price-period" id="entPricePeriod" data-i18n="upgrade.ent_period">/ tháng</span>
                                </div>
                                <div class="yearly-saving-hint" id="entYearlyHint" style="display: none;">Thanh toán 2.870.000 đ/năm (Tiết kiệm 718.000 đ)</div>
                            </div>
                            <div class="card-plan-features">
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n-html="upgrade.ent_f1">Tất cả quyền lợi gói <strong>PRO</strong></span></div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n="upgrade.ent_f2">Quản lý tài khoản nhóm (Tối đa 25 thành viên)</span></div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n="upgrade.ent_f3">Phân quyền tài liệu & Thư mục dùng chung</span></div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n="upgrade.ent_f4">REST API tích hợp trực tiếp vào hệ thống trường</span></div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n="upgrade.ent_f5">Nhật ký kiểm toán minh bạch (Audit Log)</span></div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <span data-i18n="upgrade.ent_f6">Quản lý viên hỗ trợ riêng (Dedicated Manager)</span></div>
                            </div>
                            <div class="card-plan-action">
                                <?php if ($userPlan === 'ENTERPRISE'): ?>
                                    <button class="btn-tier-action btn-tier-current" disabled data-i18n="upgrade.btn_ent_current">Đang sử dụng Enterprise</button>
                                <?php else: ?>
                                    <button class="btn-tier-action btn-tier-secondary" onclick="changePlan('ENTERPRISE')" data-i18n="upgrade.btn_ent_cta">Nâng cấp Enterprise</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Comparison Table -->
                    <div class="comparison-section">
                        <h3 class="comparison-title" data-i18n="upgrade.cmp_title">Bảng so sánh chi tiết tính năng</h3>
                        <div class="table-responsive">
                            <table class="comparison-table">
                                <thead>
                                    <tr>
                                        <th data-i18n="upgrade.cmp_th_feature">Tính năng</th>
                                        <th data-i18n="upgrade.cmp_th_free">Miễn phí (FREE)</th>
                                        <th class="col-highlight" data-i18n="upgrade.cmp_th_pro">Chuyên nghiệp (PRO)</th>
                                        <th data-i18n="upgrade.cmp_th_ent">Doanh nghiệp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td data-i18n="upgrade.cmp_f1">Số lượng tài liệu xử lý</td>
                                        <td data-i18n="upgrade.cmp_f1_free">5 tài liệu / ngày</td>
                                        <td class="col-highlight font-weight-bold" data-i18n="upgrade.cmp_unlimited">Không giới hạn</td>
                                        <td data-i18n="upgrade.cmp_unlimited">Không giới hạn</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="upgrade.cmp_f2">Dung lượng lưu trữ đám mây</td>
                                        <td data-i18n="upgrade.cmp_f2_free">1 GB</td>
                                        <td class="col-highlight font-weight-bold" data-i18n="upgrade.cmp_unlimited">Không giới hạn</td>
                                        <td data-i18n="upgrade.cmp_unlimited">Không giới hạn</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="upgrade.cmp_f3">Trình biên tập PDFaid (Văn bản, Bút vẽ, Con dấu)</td>
                                        <td data-i18n="upgrade.cmp_basic">Cơ bản</td>
                                        <td class="col-highlight text-success" data-i18n="upgrade.cmp_full">✓ Đầy đủ công cụ</td>
                                        <td class="text-success" data-i18n="upgrade.cmp_full">✓ Đầy đủ công cụ</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="upgrade.cmp_f4">Trình quản lý trang (Xoay, Sắp xếp, Tách, Nhập)</td>
                                        <td data-i18n="upgrade.cmp_f4_free">Tối đa 10 trang</td>
                                        <td class="col-highlight text-success" data-i18n="upgrade.cmp_f4_pro">✓ Không giới hạn trang</td>
                                        <td class="text-success" data-i18n="upgrade.cmp_f4_pro">✓ Không giới hạn trang</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="upgrade.cmp_f5">Bảng đối chiếu nguồn tài liệu (Manifest)</td>
                                        <td data-i18n="upgrade.cmp_basic">Cơ bản</td>
                                        <td class="col-highlight text-success" data-i18n="upgrade.cmp_f5_pro">✓ Chi tiết từng trang</td>
                                        <td class="text-success" data-i18n="upgrade.cmp_f5_pro">✓ Chi tiết từng trang</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="upgrade.cmp_f6">Tốc độ xử lý hàng đợi</td>
                                        <td data-i18n="upgrade.cmp_standard">Tiêu chuẩn</td>
                                        <td class="col-highlight font-weight-bold" data-i18n="upgrade.cmp_5x">Nhanh gấp 5 lần</td>
                                        <td data-i18n="upgrade.cmp_highest">Ưu tiên cao nhất</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="upgrade.cmp_f7">API & Quản lý nhóm đa người dùng</td>
                                        <td class="text-muted">✕</td>
                                        <td class="col-highlight text-muted">✕</td>
                                        <td class="text-success" data-i18n="upgrade.cmp_available">✓ Có sẵn</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- FAQ Section -->
                    <div class="pricing-faq-section">
                        <h3 class="faq-title" data-i18n="upgrade.faq_title">Câu hỏi thường gặp</h3>
                        <div class="faq-accordion">
                            <div class="faq-item">
                                <div class="faq-question" onclick="toggleFaq(this)">
                                    <span data-i18n="upgrade.faq_q1">Tôi có thể hủy hoặc thay đổi gói bất cứ lúc nào không?</span>
                                    <span class="faq-toggle-icon">+</span>
                                </div>
                                <div class="faq-answer" data-i18n="upgrade.faq_a1">
                                    Hoàn toàn có thể. Bạn có thể nâng cấp, hạ cấp hoặc hủy gói bất kỳ lúc nào ngay trong phần Cài đặt tài khoản. Không có phụ phí phát sinh nào.
                                </div>
                            </div>
                            <div class="faq-item">
                                <div class="faq-question" onclick="toggleFaq(this)">
                                    <span data-i18n="upgrade.faq_q2">Phương thức thanh toán nào được hỗ trợ?</span>
                                    <span class="faq-toggle-icon">+</span>
                                </div>
                                <div class="faq-answer" data-i18n="upgrade.faq_a2">
                                    Chúng tôi hỗ trợ chuyển khoản ngân hàng QR Code (VietQR / Napas 247), ví MoMo, ZaloPay, thẻ tín dụng / ghi nợ nội địa và quốc tế (Visa, Mastercard).
                                </div>
                            </div>
                            <div class="faq-item">
                                <div class="faq-question" onclick="toggleFaq(this)">
                                    <span data-i18n="upgrade.faq_q3">Dữ liệu và tài liệu PDF của tôi có được bảo mật không?</span>
                                    <span class="faq-toggle-icon">+</span>
                                </div>
                                <div class="faq-answer" data-i18n="upgrade.faq_a3">
                                    Tất cả tài liệu được mã hóa truyền tải theo chuẩn SSL/TLS và lưu trữ an toàn trong phân vùng riêng biệt của tài khoản bạn. Dữ liệu của bạn không bao giờ được chia sẻ với bất kỳ bên thứ ba nào.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VIEW 5: LỊCH SỬ XUẤT -->
            <div id="view-history" class="workspace-view <?= $currentView === 'history' ? 'active' : '' ?>">
                <!-- Header Hero Banner -->
                <div class="view-hero-card">
                    <div class="view-hero-content">
                        <div class="view-hero-badge">
                            <span class="badge-dot"></span>
                            <span data-i18n="history.badge_sub">Nhật ký & Tra cứu xuất bản</span>
                        </div>
                        <h2 class="view-hero-title" data-i18n="history.title">Lịch sử xuất</h2>
                        <p class="view-hero-desc" data-i18n="history.desc">Quản lý và tra cứu tất cả các tệp PDF đã xuất, tải lại tài liệu và kiểm tra cấu trúc trang đối chiếu nguồn.</p>
                    </div>
                    <div class="view-hero-actions">
                        <button class="btn-outline btn-hero-action" onclick="loadExportHistory()" title="Tải lại danh sách">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10"/></svg>
                            <span data-i18n="history.btn_refresh">Làm mới</span>
                        </button>
                        <button class="btn-hero-action btn-danger-hero" onclick="clearAllHistory()" title="Xóa toàn bộ lịch sử xuất">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                            <span data-i18n="history.btn_clear">Xóa lịch sử</span>
                        </button>
                    </div>
                </div>

                <!-- 4 Thống kê Metrics Grid -->
                <div class="history-stats-grid">
                    <div class="history-stat-card">
                        <div class="stat-icon-wrap blue">
                            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" id="historyMetricFiles">0</div>
                            <div class="stat-label" data-i18n="history.stat_total">Tổng tệp đã xuất</div>
                        </div>
                    </div>
                    <div class="history-stat-card">
                        <div class="stat-icon-wrap green">
                            <svg class="svg-icon" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" id="historyMetricPages">0</div>
                            <div class="stat-label" data-i18n="history.stat_pages">Tổng số trang xử lý</div>
                        </div>
                    </div>
                    <div class="history-stat-card">
                        <div class="stat-icon-wrap purple">
                            <svg class="svg-icon" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" id="historyMetricSize">0 KB</div>
                            <div class="stat-label" data-i18n="history.stat_size">Dung lượng đã tạo</div>
                        </div>
                    </div>
                    <div class="history-stat-card">
                        <div class="stat-icon-wrap amber">
                            <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value stat-value-sm" id="historyMetricRecent">Chưa có</div>
                            <div class="stat-label" data-i18n="history.stat_latest">Lần xuất gần nhất</div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Section: Toolbar + Data Table -->
                <div class="table-section">
                    <div class="history-toolbar">
                        <div class="history-search-wrap">
                            <svg class="svg-icon svg-icon-sm search-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" id="historySearchInput" class="history-search-input" placeholder="Tìm theo tên tệp xuất hoặc mã #ID..." data-i18n-placeholder="history.search_placeholder" oninput="filterExportHistory()">
                            <button type="button" class="search-clear-btn" id="historySearchClear" onclick="clearHistorySearch()" style="display: none;">✕</button>
                        </div>
                        <div class="history-filter-group">
                            <div class="filter-select-wrap">
                                <label for="historyTypeFilter" class="filter-label" data-i18n="history.filter_type_label">Loại tác vụ:</label>
                                <select id="historyTypeFilter" class="custom-select" onchange="filterExportHistory()">
                                    <option value="all" data-i18n="history.type_all">Tất cả loại tác vụ</option>
                                    <option value="merge" data-i18n="history.type_merge">Ghép tài liệu (Merge)</option>
                                    <option value="split" data-i18n="history.type_split">Tách tài liệu (Split)</option>
                                    <option value="edit" data-i18n="history.type_edit">Biên tập chỉnh sửa</option>
                                </select>
                            </div>
                            <div class="filter-select-wrap">
                                <label for="historySortFilter" class="filter-label" data-i18n="history.filter_sort_label">Sắp xếp:</label>
                                <select id="historySortFilter" class="custom-select" onchange="filterExportHistory()">
                                    <option value="date_desc" data-i18n="history.sort_date_desc">Mới nhất trước</option>
                                    <option value="date_asc" data-i18n="history.sort_date_asc">Cũ nhất trước</option>
                                    <option value="size_desc" data-i18n="history.sort_size_desc">Dung lượng lớn nhất</option>
                                    <option value="pages_desc" data-i18n="history.sort_pages_desc">Nhiều trang nhất</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="custom-table history-data-table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;" data-i18n="history.th_id">Mã #ID</th>
                                    <th data-i18n="history.th_file">Tên tệp xuất & Định dạng</th>
                                    <th style="width: 130px;" data-i18n="history.th_action">Tác vụ</th>
                                    <th style="width: 110px;" data-i18n="history.th_pages">Số trang</th>
                                    <th style="width: 120px;" data-i18n="history.th_size">Dung lượng</th>
                                    <th style="width: 160px;" data-i18n="history.th_created">Thời gian tạo</th>
                                    <th style="width: 180px; text-align: center;" data-i18n="history.th_ops">Thao tác nhanh</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                        <div class="spinner-small" style="margin: 0 auto 10px;"></div>
                                        <span>Đang tải lịch sử xuất...</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="history-table-footer" id="historyTableFooter">
                        <span id="historyCountNotice">Hiển thị 0 tệp xuất</span>
                    </div>
                </div>
            </div>

            <!-- VIEW 6: CÀI ĐẶT HỆ THỐNG -->
            <div id="view-settings" class="workspace-view <?= $currentView === 'settings' ? 'active' : '' ?>">
                <!-- Header Hero Banner -->
                <div class="view-hero-card">
                    <div class="view-hero-content">
                        <div class="view-hero-badge">
                            <span class="badge-dot"></span>
                            <span data-i18n="settings.badge">Cấu hình & Tùy biến</span>
                        </div>
                        <h2 class="view-hero-title" data-i18n="settings.title">Cài đặt</h2>
                        <p class="view-hero-desc" data-i18n="settings.desc">Tùy biến môi trường làm việc, chất lượng xử lý tệp PDF, chính sách bảo mật dữ liệu và quản lý gói tài khoản.</p>
                    </div>
                    <div class="view-hero-actions">
                        <button type="button" class="btn-outline btn-hero-action" onclick="resetSettingsToDefault()">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                            <span data-i18n="settings.btn_reset">Khôi phục mặc định</span>
                        </button>
                        <button type="button" class="btn-hero-action btn-save-hero" onclick="saveAllSettings()">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            <span data-i18n="settings.btn_save">Lưu thiết lập</span>
                        </button>
                    </div>
                </div>

                <!-- Sub-tabs Navigation -->
                <div class="settings-nav-tabs">
                    <button type="button" class="settings-tab-btn active" data-tab="appearance" onclick="switchSettingsTab('appearance')">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                        <span data-i18n="settings.tab_appearance">Giao diện & Trải nghiệm</span>
                    </button>
                    <button type="button" class="settings-tab-btn" data-tab="engine" onclick="switchSettingsTab('engine')">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        <span data-i18n="settings.tab_engine">Công cụ xử lý PDF</span>
                    </button>
                    <button type="button" class="settings-tab-btn" data-tab="security" onclick="switchSettingsTab('security')">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span data-i18n="settings.tab_security">Bảo mật & Lưu trữ</span>
                    </button>
                    <button type="button" class="settings-tab-btn" data-tab="account" onclick="switchSettingsTab('account')">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span data-i18n="settings.tab_account">Tài khoản & Gói SaaS</span>
                    </button>
                </div>

                <!-- Tab 1: Giao diện & Trải nghiệm -->
                <div id="tab-pane-appearance" class="settings-tab-pane active">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div>
                                <h3 class="settings-card-title" data-i18n="settings.theme_card_title">Chủ đề & Hiển thị</h3>
                                <p class="settings-card-desc" data-i18n="settings.theme_card_desc">Cá nhân hóa giao diện làm việc phù hợp với mắt và thói quen của bạn</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="settings-form-row">
                                <div class="settings-label-col">
                                    <label class="settings-label" data-i18n="settings.theme_label" for="settingTheme">Chế độ giao diện</label>
                                    <span class="settings-hint" data-i18n="settings.theme_hint">Chọn gam màu hiển thị ưa thích cho toàn ứng dụng</span>
                                </div>
                                <div class="settings-control-col">
                                    <select id="settingTheme" class="custom-select settings-select">
                                        <option value="light" data-i18n="settings.theme_light" <?= ($currentSettings['theme'] ?? '') === 'light' ? 'selected' : '' ?>>☀️ Giao diện Sáng (Mặc định chuẩn)</option>
                                        <option value="dark" data-i18n="settings.theme_dark" <?= ($currentSettings['theme'] ?? '') === 'dark' ? 'selected' : '' ?>>🌙 Giao diện Tối (Bảo vệ mắt)</option>
                                        <option value="system" data-i18n="settings.theme_system" <?= ($currentSettings['theme'] ?? '') === 'system' ? 'selected' : '' ?>>🖥️ Theo cài đặt hệ điều hành</option>
                                    </select>
                                </div>
                            </div>
                            <div class="settings-form-row">
                                <div class="settings-label-col">
                                    <label class="settings-label" data-i18n="settings.lang_label" for="settingLanguage">Ngôn ngữ hiển thị</label>
                                    <span class="settings-hint" data-i18n="settings.lang_hint">Ngôn ngữ áp dụng cho toàn bộ nhãn và thông báo</span>
                                </div>
                                <div class="settings-control-col">
                                    <select id="settingLanguage" class="custom-select settings-select">
                                        <option value="vi" data-i18n="settings.lang_vi" <?= ($currentSettings['language'] ?? '') === 'vi' ? 'selected' : '' ?>>🇻🇳 Tiếng Việt (Vietnamese)</option>
                                        <option value="en" data-i18n="settings.lang_en" <?= ($currentSettings['language'] ?? '') === 'en' ? 'selected' : '' ?>>🇬🇧 English (International)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="settings-form-row">
                                <div class="settings-label-col">
                                    <label class="settings-label" data-i18n="settings.density_label" for="settingDensity">Mật độ hiển thị danh sách trang</label>
                                    <span class="settings-hint" data-i18n="settings.density_hint">Độ thoáng của lưới trang trong giao diện Chỉnh sửa</span>
                                </div>
                                <div class="settings-control-col">
                                    <select id="settingDensity" class="custom-select settings-select">
                                        <option value="comfortable" data-i18n="settings.density_comfortable" <?= ($currentSettings['density'] ?? '') === 'comfortable' ? 'selected' : '' ?>>Tiêu chuẩn (Rộng rãi, dễ kéo thả)</option>
                                        <option value="compact" data-i18n="settings.density_compact" <?= ($currentSettings['density'] ?? '') === 'compact' ? 'selected' : '' ?>>Gọn gàng (Hiển thị nhiều trang hơn)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div>
                                <h3 class="settings-card-title" data-i18n="settings.interaction_card_title">Tương tác & Thông báo</h3>
                                <p class="settings-card-desc" data-i18n="settings.interaction_card_desc">Cấu hình cách hệ thống tương tác và phản hồi</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="settings-form-row">
                                <div class="settings-label-col">
                                    <label class="settings-label" data-i18n="settings.autocollapse_label" for="settingAutoCollapse">Tự động thu gọn thanh bên (Sidebar)</label>
                                    <span class="settings-hint" data-i18n="settings.autocollapse_hint">Mở rộng tối đa không gian chỉnh sửa trang PDF</span>
                                </div>
                                <div class="settings-control-col">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="settingAutoCollapse" <?= !empty($currentSettings['auto_collapse_sidebar']) ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="settings-form-row">
                                <div class="settings-label-col">
                                    <label class="settings-label" data-i18n="settings.sound_label" for="settingNotificationSound">Âm thanh thông báo</label>
                                    <span class="settings-hint" data-i18n="settings.sound_hint">Phát chuông nhẹ khi hoàn thành ghép hoặc xuất file PDF</span>
                                </div>
                                <div class="settings-control-col">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="settingNotificationSound" <?= ($currentSettings['notification_sound'] ?? true) ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Công cụ xử lý PDF -->
                <div id="tab-pane-engine" class="settings-tab-pane">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div>
                                <h3 class="settings-card-title" data-i18n="settings.engine_card_title">Chất lượng hiển thị & Xuất bản PDF</h3>
                                <p class="settings-card-desc" data-i18n="settings.engine_card_desc">Tùy biến bộ sinh ảnh trang (Preview Engine) và thuật toán nén FPDI</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="settings-form-row">
                                <div class="settings-label-col">
                                    <label class="settings-label" data-i18n="settings.dpi_label" for="settingPreviewDpi">Độ phân giải trang xem trước (Preview DPI)</label>
                                    <span class="settings-hint" data-i18n="settings.dpi_hint">DPI càng cao hình ảnh càng rõ nét, DPI thấp xử lý nhanh hơn</span>
                                </div>
                                <div class="settings-control-col">
                                    <select id="settingPreviewDpi" class="custom-select settings-select">
                                        <option value="72" data-i18n="settings.dpi_72" <?= ((int)($currentSettings['preview_dpi'] ?? 150)) === 72 ? 'selected' : '' ?>>72 DPI (Siêu tốc - Tiết kiệm băng thông)</option>
                                        <option value="150" data-i18n="settings.dpi_150" <?= ((int)($currentSettings['preview_dpi'] ?? 150)) === 150 ? 'selected' : '' ?>>150 DPI (Cân bằng chuẩn - Khuyên dùng)</option>
                                        <option value="300" data-i18n="settings.dpi_300" <?= ((int)($currentSettings['preview_dpi'] ?? 150)) === 300 ? 'selected' : '' ?>>300 DPI (Sắc nét cao cấp - Chuẩn in ấn)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="settings-form-row">
                                <div class="settings-label-col">
                                    <label class="settings-label" data-i18n="settings.compress_label" for="settingCompression">Thuật toán nén tệp PDF xuất ra</label>
                                    <span class="settings-hint" data-i18n="settings.compress_hint">Tối ưu dung lượng tệp khi tải về hoặc chia sẻ email</span>
                                </div>
                                <div class="settings-control-col">
                                    <select id="settingCompression" class="custom-select settings-select">
                                        <option value="none" data-i18n="settings.compress_none" <?= ($currentSettings['compression_level'] ?? '') === 'none' ? 'selected' : '' ?>>Không nén (Giữ nguyên chất lượng gốc tuyệt đối)</option>
                                        <option value="balanced" data-i18n="settings.compress_balanced" <?= ($currentSettings['compression_level'] ?? '') === 'balanced' ? 'selected' : '' ?>>Nén thông minh (Tối ưu dung lượng, không vỡ ảnh)</option>
                                        <option value="maximum" data-i18n="settings.compress_maximum" <?= ($currentSettings['compression_level'] ?? '') === 'maximum' ? 'selected' : '' ?>>Nén tối đa (Dung lượng siêu nhẹ cho web/email)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="settings-form-row">
                                <div class="settings-label-col">
                                    <label class="settings-label" data-i18n="settings.orient_label" for="settingOrientation">Định hướng trang mặc định</label>
                                    <span class="settings-hint" data-i18n="settings.orient_hint">Khổ trang mặc định khi nhập và xuất trang mới</span>
                                </div>
                                <div class="settings-control-col">
                                    <select id="settingOrientation" class="custom-select settings-select">
                                        <option value="original" data-i18n="settings.orient_original" <?= ($currentSettings['default_orientation'] ?? '') === 'original' ? 'selected' : '' ?>>Giữ nguyên tỉ lệ gốc của từng trang</option>
                                        <option value="a4_portrait" data-i18n="settings.orient_a4_portrait" <?= ($currentSettings['default_orientation'] ?? '') === 'a4_portrait' ? 'selected' : '' ?>>Chuẩn hóa Khổ A4 Đứng (Portrait)</option>
                                        <option value="a4_landscape" data-i18n="settings.orient_a4_landscape" <?= ($currentSettings['default_orientation'] ?? '') === 'a4_landscape' ? 'selected' : '' ?>>Chuẩn hóa Khổ A4 Ngang (Landscape)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="settings-form-row">
                                <div class="settings-label-col">
                                    <label class="settings-label" data-i18n="settings.pagenum_label" for="settingAutoPageNumbers">Tự động nhúng số trang chân trang</label>
                                    <span class="settings-hint" data-i18n="settings.pagenum_hint">Đánh số trang dạng 'Trang X / Y' ở đáy tài liệu khi ghép</span>
                                </div>
                                <div class="settings-control-col">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="settingAutoPageNumbers" <?= !empty($currentSettings['auto_page_numbers']) ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="settings-form-row">
                                <div class="settings-label-col">
                                    <label class="settings-label" data-i18n="settings.manifest_label" for="settingKeepManifest">Lưu bảng đối chiếu nguồn (Source Manifest)</label>
                                    <span class="settings-hint" data-i18n="settings.manifest_hint">Ghi nhận metadata nguồn gốc từng trang phục vụ tra cứu sau này</span>
                                </div>
                                <div class="settings-control-col">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="settingKeepManifest" <?= ($currentSettings['keep_manifest'] ?? true) ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Bảo mật & Lưu trữ -->
                <div id="tab-pane-security" class="settings-tab-pane">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div>
                                <h3 class="settings-card-title" data-i18n="settings.sec_card_title">Chính sách lưu trữ & Vòng đời dữ liệu</h3>
                                <p class="settings-card-desc" data-i18n="settings.sec_card_desc">Cấu hình cơ chế tự động dọn dẹp và bảo mật theo chuẩn ISO/IEC 27001</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="settings-form-row">
                                <div class="settings-label-col">
                                    <label class="settings-label" data-i18n="settings.cleanup_label" for="settingAutoCleanup">Tự động dọn dẹp tệp xuất trên máy chủ</label>
                                    <span class="settings-hint" data-i18n="settings.cleanup_hint">Hệ thống sẽ tự hủy các tệp xuất sau khoảng thời gian chỉ định</span>
                                </div>
                                <div class="settings-control-col">
                                    <select id="settingAutoCleanup" class="custom-select settings-select">
                                        <option value="24h" data-i18n="settings.cleanup_24h" <?= ($currentSettings['auto_cleanup'] ?? '') === '24h' ? 'selected' : '' ?>>Tự hủy sau 24 giờ (Khuyên dùng bảo mật)</option>
                                        <option value="7d" data-i18n="settings.cleanup_7d" <?= ($currentSettings['auto_cleanup'] ?? '') === '7d' ? 'selected' : '' ?>>Tự hủy sau 7 ngày</option>
                                        <option value="30d" data-i18n="settings.cleanup_30d" <?= ($currentSettings['auto_cleanup'] ?? '') === '30d' ? 'selected' : '' ?>>Tự hủy sau 30 ngày</option>
                                        <option value="never" data-i18n="settings.cleanup_never" <?= ($currentSettings['auto_cleanup'] ?? '') === 'never' ? 'selected' : '' ?>>Không tự hủy (Chỉ xóa thủ công)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="settings-form-row">
                                <div class="settings-label-col">
                                    <label class="settings-label" data-i18n="settings.pwd_label" for="settingDefaultPassword">Mật khẩu bảo vệ mặc định khi xuất</label>
                                    <span class="settings-hint" data-i18n="settings.pwd_hint">Tự động mã hóa AES-128 cho các tệp PDF được xuất (để trống nếu không dùng)</span>
                                </div>
                                <div class="settings-control-col">
                                    <input type="password" id="settingDefaultPassword" class="auth-input settings-input" value="<?= htmlspecialchars($currentSettings['default_password'] ?? '') ?>" placeholder="Để trống nếu không khóa tệp..." data-i18n-placeholder="settings.pwd_placeholder">
                                </div>
                            </div>
                            <div class="settings-form-row">
                                <div class="settings-label-col">
                                    <label class="settings-label" data-i18n="settings.incognito_label" for="settingIncognito">Chế độ riêng tư (Incognito Editor)</label>
                                    <span class="settings-hint" data-i18n="settings.incognito_hint">Không lưu bộ nhớ đệm trang PDF trên trình duyệt máy khách</span>
                                </div>
                                <div class="settings-control-col">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="settingIncognito" <?= !empty($currentSettings['incognito_mode']) ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card danger-zone">
                        <div class="settings-card-header">
                            <div>
                                <h3 class="settings-card-title" data-i18n="settings.cache_card_title" style="color: #EF4444;">Dọn dẹp vùng nhớ & Bộ nhớ đệm</h3>
                                <p class="settings-card-desc" data-i18n="settings.cache_card_desc">Giải phóng dung lượng bộ nhớ cục bộ trên trình duyệt</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="settings-form-row">
                                <div class="settings-label-col">
                                    <label class="settings-label" data-i18n="settings.cache_label">Xóa dữ liệu bộ đệm trình duyệt (Client Cache)</label>
                                    <span class="settings-hint" data-i18n="settings.cache_hint">Xóa sạch các thumbnail trang nháp đã lưu tạm trong LocalStorage</span>
                                </div>
                                <div class="settings-control-col">
                                    <button type="button" class="btn-danger-outline" onclick="clearClientCache()">
                                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        <span data-i18n="settings.btn_cache">Dọn dẹp Cache ngay</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 4: Tài khoản & Gói SaaS -->
                <div id="tab-pane-account" class="settings-tab-pane">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div>
                                <h3 class="settings-card-title" data-i18n="settings.profile_card_title">Hồ sơ người dùng & Phân quyền</h3>
                                <p class="settings-card-desc" data-i18n="settings.profile_card_desc">Thông tin tài khoản đang đăng nhập trong phiên làm việc</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="profile-info-grid">
                                <div class="profile-field-box">
                                    <span class="profile-field-label" data-i18n="profile.name_label">Họ và tên:</span>
                                    <strong class="profile-field-val"><?= htmlspecialchars($currentUser['name'] ?? 'Khách vãng lai') ?></strong>
                                </div>
                                <div class="profile-field-box">
                                    <span class="profile-field-label" data-i18n="profile.email_label">Email tài khoản:</span>
                                    <strong class="profile-field-val"><?= htmlspecialchars($currentUser['email'] ?? 'Chưa đăng ký') ?></strong>
                                </div>
                                <div class="profile-field-box">
                                    <span class="profile-field-label" data-i18n="profile.username_label">Tên đăng nhập:</span>
                                    <strong class="profile-field-val"><?= htmlspecialchars($currentUser['username'] ?? 'guest') ?></strong>
                                </div>
                                <div class="profile-field-box">
                                    <span class="profile-field-label" data-i18n="profile.role_label">Vai trò:</span>
                                    <span class="status-pill success"><span class="status-dot"></span> <?= htmlspecialchars($currentUser['role'] ?? 'Thành viên') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div>
                                <h3 class="settings-card-title" data-i18n="settings.plan_card_title">Gói dịch vụ & Hạn mức tài nguyên</h3>
                                <p class="settings-card-desc" data-i18n="settings.plan_card_desc">Quản lý giới hạn tải lên, dung lượng lưu trữ và quyền ưu tiên xử lý</p>
                            </div>
                            <span class="plan-badge <?= strtolower($currentUser['plan'] ?? 'FREE') ?>">
                                Gói <?= htmlspecialchars($currentUser['plan'] ?? 'FREE') ?>
                            </span>
                        </div>
                        <div class="settings-card-body">
                            <div class="storage-usage-container">
                                <div class="storage-usage-header">
                                    <span data-i18n="profile.storage_limit_label">Hạn mức lưu trữ đám mây</span>
                                    <strong><?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'Không giới hạn (PRO Plan)' : '142 MB / 1,024 MB (14%)' ?></strong>
                                </div>
                                <div class="storage-progress-bar">
                                    <div class="storage-progress-fill <?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'pro-fill' : '' ?>" style="width: <?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? '100%' : '14%' ?>;"></div>
                                </div>
                                <div class="storage-features-list">
                                    <div class="storage-feature-item">
                                        <svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                        <span>Tốc độ render thumbnail: <strong><?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'Ưu tiên cao nhất' : 'Tiêu chuẩn' ?></strong></span>
                                    </div>
                                    <div class="storage-feature-item">
                                        <svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                        <span>Giới hạn kích thước tệp tải lên: <strong><?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? '500 MB / tệp' : '50 MB / tệp' ?></strong></span>
                                    </div>
                                    <div class="storage-feature-item">
                                        <svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                        <span>Đầy đủ công cụ ký số, đóng dấu & vector watermark: <strong>Đã kích hoạt</strong></span>
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top: 20px; display: flex; gap: 12px; flex-wrap: wrap;">
                                <button type="button" class="btn-primary" onclick="openPlanModal()" data-i18n="<?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'profile.btn_manage_pro' : 'profile.btn_upgrade_pro' ?>">
                                    <?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'Quản lý gói PRO' : 'Nâng cấp lên PRO ngay' ?>
                                </button>
                                <?php if ($isLoggedIn): ?>
                                    <button type="button" class="btn-outline" onclick="logout()" data-i18n="profile.btn_logout">Đăng xuất tài khoản</button>
                                <?php else: ?>
                                    <button type="button" class="btn-outline" onclick="openAuthModal('login')" data-i18n="profile.btn_login">Đăng nhập tài khoản</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Save Bar -->
                <div class="settings-save-footer">
                    <div class="settings-footer-note">
                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span data-i18n="settings.footer_note">Cài đặt sẽ tự động lưu vào trình duyệt và phiên làm việc của bạn.</span>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn-outline" onclick="resetSettingsToDefault()">
                            <span data-i18n="settings.btn_reset">Khôi phục mặc định</span>
                        </button>
                        <button type="button" class="btn-primary" onclick="saveAllSettings()">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            <span data-i18n="settings.btn_save_all">Lưu tất cả thiết lập</span>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION (Khớp bản thiết kế Mobile) -->
    <nav class="mobile-bottom-nav">
        <a href="<?= $baseUrl ?>/home" class="mobile-nav-item <?= $currentView === 'home' ? 'active' : '' ?>" data-view="home" onclick="switchView('home', event)">
            <svg class="svg-icon" viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span data-i18n="nav.home">Trang chủ</span>
        </a>
        <a href="<?= $baseUrl ?>/documents" class="mobile-nav-item <?= $currentView === 'documents' ? 'active' : '' ?>" data-view="documents" onclick="switchView('documents', event)">
            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <span data-i18n="nav.documents">Tài liệu</span>
        </a>
        <div class="mobile-nav-item center-action" onclick="switchView('editor', event)" title="Mở Trình Chỉnh sửa PDF">
            <div class="mobile-plus-circle" style="background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);">
                <svg class="svg-icon svg-icon-lg" viewBox="0 0 24 24" style="stroke: white;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </div>
        </div>
        <a href="<?= $baseUrl ?>/upgrade" class="mobile-nav-item <?= $currentView === 'upgrade' ? 'active' : '' ?>" data-view="upgrade" onclick="switchView('upgrade', event)">
            <svg class="svg-icon" viewBox="0 0 24 24" style="color: #F59E0B;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <span data-i18n="nav.upgrade">Nâng Cấp</span>
        </a>
        <a href="<?= $baseUrl ?>/settings" class="mobile-nav-item <?= $currentView === 'settings' ? 'active' : '' ?>" data-view="settings" onclick="switchView('settings', event)">
            <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span data-i18n="nav.settings">Cài đặt</span>
        </a>
    </nav>

    <!-- MODAL: TẢI LÊN TỆP PDF -->
    <div id="uploadModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 data-i18n="modal.upload_title">Tải lên tệp PDF mới</h3>
                <button class="btn-close" onclick="closeModal('uploadModal')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="drop-zone" id="uploadDropZone" onclick="document.getElementById('fileInput').click()">
                <div style="margin-bottom: 12px; color: #2563EB;">
                    <svg class="svg-icon" style="width: 48px; height: 48px;" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                </div>
                <h4 style="font-size: 1rem; margin-bottom: 4px; font-weight: 700;" data-i18n="modal.upload_drop_title">Kéo & Thả tệp PDF vào đây</h4>
                <p style="font-size: 0.8rem; color: var(--text-muted);" data-i18n="modal.upload_drop_sub">hoặc bấm vào để chọn tệp từ máy tính</p>
                <p style="font-size: 0.72rem; color: #94A3B8; margin-top: 8px;" data-i18n="modal.upload_limit">Giới hạn dung lượng tối đa: 25 MB • Chỉ chấp nhận định dạng .pdf</p>
            </div>
            <input type="file" id="fileInput" accept="application/pdf" style="display: none;" onchange="handleFileSelect(event)">
            <div id="uploadProgress" style="display: none; margin-top: 12px;">
                <div style="font-size: 0.8rem; margin-bottom: 4px;" data-i18n="modal.uploading">Đang xử lý tải lên...</div>
                <div class="progress-bar"><div class="progress-fill" style="width: 100%;"></div></div>
            </div>
        </div>
    </div>

    <!-- MODAL: BẢNG ĐỐI CHIẾU TRANG NGUỒN (SOURCE MANIFEST) -->
    <div id="manifestModal" class="modal-overlay">
        <div class="modal-box modal-lg">
            <div class="modal-header">
                <div>
                    <h3 id="manifestModalTitle" data-i18n="modal.manifest_title">Bảng đối chiếu trang nguồn (Source Manifest)</h3>
                    <p style="font-size: 0.75rem; color: var(--text-muted);" data-i18n="modal.manifest_sub">Xác minh từng trang đầu ra lấy từ tệp gốc và trang gốc nào</p>
                </div>
                <button class="btn-close" onclick="closeModal('manifestModal')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th data-i18n="modal.manifest_th_output">Trang kết quả</th>
                        <th data-i18n="modal.manifest_th_source_doc">Tệp nguồn gốc</th>
                        <th data-i18n="modal.manifest_th_source_page">Trang gốc</th>
                        <th data-i18n="modal.manifest_th_label">Nhãn nhận diện</th>
                        <th data-i18n="modal.manifest_th_rotation">Góc xoay</th>
                    </tr>
                </thead>
                <tbody id="manifestTableBody"></tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- MODAL: ĐĂNG NHẬP & ĐĂNG KÝ (AUTHENTICATION) -->
    <div id="authModal" class="modal-overlay">
        <div class="modal-box auth-modal-box">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div class="brand-icon" style="width: 36px; height: 36px; border-radius: 10px;">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg>
                    </div>
                    <div>
                        <h3 id="authModalTitle" style="font-size: 1.15rem; font-weight: 700; margin-bottom: 2px;" data-i18n="modal.auth_title">Tài khoản PDF Manager</h3>
                        <p style="font-size: 0.74rem; color: var(--text-muted);" data-i18n="modal.auth_sub">Đăng nhập hoặc đăng ký tài khoản để quản lý tài liệu cá nhân</p>
                    </div>
                </div>
                <button class="btn-close" onclick="closeModal('authModal')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <!-- Auth Tabs: Đăng nhập / Đăng ký -->
            <div class="auth-tabs">
                <button type="button" class="auth-tab-btn active" id="tabAuthLogin" onclick="switchAuthTab('login')" data-i18n="modal.auth_tab_login">Đăng nhập</button>
                <button type="button" class="auth-tab-btn" id="tabAuthRegister" onclick="switchAuthTab('register')" data-i18n="modal.auth_tab_register">Đăng ký mới</button>
            </div>

            <!-- Form Đăng nhập -->
            <form id="formLogin" onsubmit="handleAuthLogin(event)" class="auth-form-body">
                <div class="form-group">
                    <label class="form-label" data-i18n="modal.auth_login_user_lbl">Tên đăng nhập hoặc Email</label>
                    <div class="input-with-icon">
                        <svg class="svg-icon svg-icon-sm input-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" id="loginUsername" required placeholder="Nhập tên đăng nhập hoặc email..." data-i18n-placeholder="modal.auth_login_user_ph" class="auth-input">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" data-i18n="modal.auth_login_pwd_lbl">Mật khẩu</label>
                    <div class="input-with-icon">
                        <svg class="svg-icon svg-icon-sm input-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="loginPassword" required placeholder="Nhập mật khẩu của bạn..." data-i18n-placeholder="modal.auth_login_pwd_ph" class="auth-input">
                    </div>
                </div>

                <button type="submit" class="btn-primary auth-submit-btn" id="btnLoginSubmit">
                    <span data-i18n="modal.auth_login_btn">Đăng nhập</span>
                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                </button>

                <div class="auth-switch-prompt">
                    <span data-i18n="modal.auth_prompt_no_account">Chưa có tài khoản?</span> <a href="javascript:void(0)" onclick="switchAuthTab('register')" data-i18n="modal.auth_link_register">Đăng ký tài khoản mới</a>
                </div>
            </form>

            <!-- Form Đăng ký -->
            <form id="formRegister" onsubmit="handleAuthRegister(event)" class="auth-form-body" style="display: none;">
                <div class="form-group">
                    <label class="form-label" data-i18n="modal.auth_reg_fullname_lbl">Họ và tên đầy đủ</label>
                    <div class="input-with-icon">
                        <svg class="svg-icon svg-icon-sm input-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" id="regFullName" required placeholder="Ví dụ: Nguyễn Văn An..." data-i18n-placeholder="modal.auth_reg_fullname_ph" class="auth-input">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" data-i18n="modal.auth_reg_user_lbl">Tên đăng nhập</label>
                    <div class="input-with-icon">
                        <svg class="svg-icon svg-icon-sm input-icon" viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>
                        <input type="text" id="regUsername" required placeholder="Nhập tên đăng nhập (vd: nguyenvana)..." data-i18n-placeholder="modal.auth_reg_user_ph" class="auth-input">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" data-i18n="modal.auth_reg_email_lbl">Địa chỉ Email</label>
                    <div class="input-with-icon">
                        <svg class="svg-icon svg-icon-sm input-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <input type="email" id="regEmail" required placeholder="vd: user@example.com" class="auth-input">
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" data-i18n="modal.auth_reg_pwd_lbl">Mật khẩu (tối thiểu 6 ký tự)</label>
                        <input type="password" id="regPassword" required minlength="6" placeholder="Mật khẩu..." class="auth-input" style="padding-left: 12px;">
                    </div>
                    <div class="form-group">
                        <label class="form-label" data-i18n="modal.auth_reg_pwd_confirm_lbl">Xác nhận mật khẩu</label>
                        <input type="password" id="regPasswordConfirm" required minlength="6" placeholder="Nhập lại..." class="auth-input" style="padding-left: 12px;">
                    </div>
                </div>

                <button type="submit" class="btn-primary auth-submit-btn" id="btnRegisterSubmit">
                    <span data-i18n="modal.auth_reg_btn">Tạo tài khoản và Đăng nhập</span>
                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                </button>

                <div class="auth-switch-prompt">
                    <span data-i18n="modal.auth_prompt_has_account">Đã có tài khoản?</span> <a href="javascript:void(0)" onclick="switchAuthTab('login')" data-i18n="modal.auth_link_login">Đăng nhập ngay</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Gói dịch vụ & Nâng cấp (FREE / PRO) -->
    <div class="modal-overlay" id="planModal">
        <div class="modal-box plan-modal-box">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="brand-icon" style="width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #F59E0B, #D97706);">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24" style="stroke: #fff;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    </div>
                    <div>
                        <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 2px;" data-i18n="modal.plan_title">Gói dịch vụ & Quyền lợi</h3>
                        <p style="font-size: 0.74rem; color: var(--text-muted);" data-i18n="modal.plan_sub">Lựa chọn gói phù hợp với tần suất xử lý tệp PDF của bạn</p>
                    </div>
                </div>
                <button class="btn-close" onclick="closeModal('planModal')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="pricing-grid">
                <!-- Gói FREE -->
                <div class="pricing-card <?= ($_SESSION['user_plan'] ?? 'FREE') === 'FREE' ? 'current-active-plan' : '' ?>">
                    <div class="pricing-card-header">
                        <span class="plan-badge free">FREE</span>
                        <h4 style="font-size: 1.05rem; margin: 8px 0 4px; font-weight: 700;" data-i18n="modal.plan_free_name">Gói Miễn Phí</h4>
                        <div class="pricing-price">0đ <span style="font-size: 0.76rem; font-weight: normal; color: var(--text-muted);" data-i18n="modal.plan_free_unit">/ trọn đời</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> <span data-i18n="upgrade.free_f2">Dung lượng lưu trữ: 1 GB</span></li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> <span data-i18n="upgrade.free_f4">Ghép, tách, sắp xếp trang tiêu chuẩn</span></li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> <span>Tối đa 50 trang mỗi tài liệu</span></li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> <span>Xuất file PDF chất lượng gốc</span></li>
                        <li class="disabled"><svg class="svg-icon svg-icon-xs text-muted" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> <span data-i18n="upgrade.pro_f2">Dung lượng không giới hạn</span></li>
                        <li class="disabled"><svg class="svg-icon svg-icon-xs text-muted" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> <span data-i18n="upgrade.pro_f6">Ưu tiên hàng chờ xử lý siêu tốc</span></li>
                    </ul>
                    <?php if (($_SESSION['user_plan'] ?? 'FREE') === 'FREE'): ?>
                        <button type="button" class="btn-plan-select btn-current" disabled data-i18n="modal.plan_current_active">✓ Đang sử dụng</button>
                    <?php else: ?>
                        <button type="button" class="btn-plan-select outline" onclick="upgradePlan('FREE')" data-i18n="modal.plan_btn_switch_free">Chuyển sang gói FREE</button>
                    <?php endif; ?>
                </div>

                <!-- Gói PRO -->
                <div class="pricing-card pro-card <?= ($_SESSION['user_plan'] ?? 'FREE') === 'PRO' ? 'current-active-plan' : '' ?>">
                    <div class="pro-ribbon" data-i18n="upgrade.pro_ribbon">PHỔ BIẾN NHẤT</div>
                    <div class="pricing-card-header">
                        <span class="plan-badge pro">PRO</span>
                        <h4 style="font-size: 1.05rem; margin: 8px 0 4px; font-weight: 700;" data-i18n="modal.plan_pro_name">Gói Chuyên Nghiệp</h4>
                        <div class="pricing-price">99.000đ <span style="font-size: 0.76rem; font-weight: normal; color: var(--text-muted);" data-i18n="modal.plan_pro_unit">/ tháng</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> <span data-i18n-html="upgrade.pro_f2"><strong>Không giới hạn</strong> dung lượng lưu trữ</span></li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> <span data-i18n="upgrade.pro_f4">Không giới hạn số trang tệp PDF</span></li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> <span data-i18n="upgrade.pro_f6">Tốc độ xử lý siêu tốc ưu tiên hàng đầu</span></li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> <span>Nén file tối ưu hóa dung lượng</span></li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> <span>Trích xuất và bảo mật cao cấp</span></li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> <span data-i18n="upgrade.pro_f8">Hỗ trợ kỹ thuật chuyên gia 24/7</span></li>
                    </ul>
                    <?php if (($_SESSION['user_plan'] ?? 'FREE') === 'PRO'): ?>
                        <button type="button" class="btn-plan-select btn-current" disabled data-i18n="modal.plan_current_active">✓ Đang kích hoạt</button>
                    <?php else: ?>
                        <button type="button" class="btn-plan-select pro-btn" onclick="upgradePlan('PRO')">
                            <span data-i18n="modal.plan_btn_upgrade_pro">Nâng cấp PRO ngay</span>
                            <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Thông tin tài khoản -->
    <div class="modal-overlay" id="accountModal">
        <div class="modal-box account-modal-box">
            <div class="modal-header">
                <div>
                    <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 2px;" data-i18n="modal.account_title">Thông tin tài khoản</h3>
                    <p style="font-size: 0.74rem; color: var(--text-muted);" data-i18n="modal.account_sub">Quản lý hồ sơ cá nhân và gói hội viên của bạn</p>
                </div>
                <button class="btn-close" onclick="closeModal('accountModal')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            
            <div class="account-profile-header">
                <div class="account-avatar-lg" id="accModalAvatar"><?= mb_substr($_SESSION['user_name'] ?? 'NV', 0, 2) ?></div>
                <div class="account-profile-meta">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <h4 id="accModalName" style="font-size: 1.05rem; font-weight: 700; margin: 0;"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Nguyễn Văn A') ?></h4>
                        <span class="plan-badge <?= strtolower($_SESSION['user_plan'] ?? 'FREE') ?>" id="accModalPlanBadge"><?= htmlspecialchars($_SESSION['user_plan'] ?? 'FREE') ?></span>
                    </div>
                    <div class="account-email" id="accModalEmail" style="font-size: 0.8rem; color: var(--text-muted); margin-top: 3px;"><?= htmlspecialchars($_SESSION['user_email'] ?? 'nguyenvana@gmail.com') ?></div>
                </div>
            </div>

            <div class="account-info-grid">
                <div class="account-info-item">
                    <span class="info-label" data-i18n="modal.account_lbl_user">Tên đăng nhập:</span>
                    <strong class="info-value" id="accModalUsername"><?= htmlspecialchars($_SESSION['username'] ?? 'nguyenvana') ?></strong>
                </div>
                <div class="account-info-item">
                    <span class="info-label" data-i18n="modal.account_lbl_plan">Gói dịch vụ:</span>
                    <strong class="info-value" id="accModalPlan"><?= htmlspecialchars($_SESSION['user_plan'] ?? 'FREE') ?></strong>
                </div>
                <div class="account-info-item">
                    <span class="info-label" data-i18n="modal.account_lbl_status">Trạng thái:</span>
                    <strong class="info-value text-success" style="display: flex; align-items: center; gap: 4px;">
                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #10B981; display: inline-block;"></span>
                        <span data-i18n="modal.account_val_status">Hoạt động bình thường</span>
                    </strong>
                </div>
                <div class="account-info-item">
                    <span class="info-label" data-i18n="modal.account_lbl_storage">Dung lượng:</span>
                    <strong class="info-value" id="accModalStorage"><?= ($_SESSION['user_plan'] ?? 'FREE') === 'PRO' ? 'Không giới hạn' : '1 GB (Đã dùng 125 MB)' ?></strong>
                </div>
            </div>

            <div class="modal-actions" style="margin-top: 22px; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                <button type="button" class="btn-primary" onclick="switchView('upgrade'); closeModal('accountModal');" style="padding: 9px 18px; font-size: 0.84rem;" data-i18n="<?= ($_SESSION['user_plan'] ?? 'FREE') === 'PRO' ? 'modal.account_btn_pro_detail' : 'modal.account_btn_upgrade' ?>">
                    <?= ($_SESSION['user_plan'] ?? 'FREE') === 'PRO' ? '⭐ Chi tiết gói PRO' : '⚡ Nâng cấp lên gói PRO' ?>
                </button>
                <button type="button" class="btn-secondary" onclick="logoutUser()" style="color: #EF4444; border-color: #FCA5A5; padding: 9px 16px; font-size: 0.84rem;" data-i18n="modal.account_btn_logout">
                    Đăng xuất
                </button>
            </div>
        </div>
    </div>

    <!-- FULLSCREEN PAGE MANAGER MODAL (Matching Image 2 / PDFaid) -->
    <div id="pageManagerModal" class="page-manager-modal" style="display: none;">
        <div class="pm-dialog">
            <!-- Modal Header / Toolbar -->
            <div class="pm-header">
                <div class="pm-header-left">
                    <button class="pm-back-btn" onclick="closePageManagerModal()" title="Quay lại trình biên tập">
                        <svg class="svg-icon" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    </button>
                    <div>
                        <h3 class="pm-title" data-i18n="modal.pm_title">Quản lý trang tài liệu</h3>
                        <p class="pm-subtitle" id="pmPageCounterText">11 trang • Kéo thả để sắp xếp, chọn để thao tác</p>
                    </div>
                </div>

                <div class="pm-toolbar">
                    <button class="pm-tool-btn" onclick="pmAddNewPage()" title="Thêm trang trắng">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span data-i18n="modal.pm_btn_add">Thêm trang</span>
                    </button>
                    <button class="pm-tool-btn" onclick="pmRotateSelected(-90)" title="Xoay ngược chiều kim đồng hồ 90°">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M2.5 2v6h6"/><path d="M2.5 8a10 10 0 1 1 2.4 7.6"/></svg>
                        <span data-i18n="modal.pm_btn_rot_left">Xoay trái</span>
                    </button>
                    <button class="pm-tool-btn" onclick="pmRotateSelected(90)" title="Xoay theo chiều kim đồng hồ 90°">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21.5 2v6h-6"/><path d="M21.5 8a10 10 0 1 0-2.4 7.6"/></svg>
                        <span data-i18n="modal.pm_btn_rot_right">Xoay phải</span>
                    </button>
                    <button class="pm-tool-btn text-danger" onclick="pmDeleteSelected()" title="Xóa các trang đã chọn">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        <span data-i18n="modal.pm_btn_delete">Xóa trang</span>
                    </button>
                    <button class="pm-tool-btn" onclick="pmDuplicateSelected()" title="Nhân đôi trang đã chọn">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <span data-i18n="modal.pm_btn_duplicate">Nhân bản</span>
                    </button>
                    <button class="pm-tool-btn" onclick="pmImportDocument()" title="Nhập thêm trang từ tệp PDF khác">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <span data-i18n="modal.pm_btn_import">Nhập PDF</span>
                    </button>

                    <div class="pm-divider"></div>

                    <button class="pm-tool-btn" onclick="pmToggleSelectAll()" id="btnPmSelectAll">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><polyline points="9 11 12 14 22 4"/></svg>
                        <span id="pmSelectAllLabel" data-i18n="modal.pm_btn_select_all">Chọn tất cả</span>
                    </button>

                    <div class="pm-divider"></div>

                    <button class="pm-icon-btn" onclick="pmUndo()" title="Hoàn tác">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
                    </button>
                    <button class="pm-icon-btn" onclick="pmRedo()" title="Làm lại">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"/></svg>
                    </button>
                </div>

                <div class="pm-header-right">
                    <div class="pm-zoom-slider-wrap">
                        <span style="font-size: 0.75rem; color: var(--text-muted);" data-i18n="modal.pm_card_size">Cỡ thẻ:</span>
                        <input type="range" min="130" max="260" value="170" class="pm-slider" id="pmCardSizeSlider" oninput="adjustPmCardSize(this.value)">
                    </div>
                    <button class="btn-secondary" onclick="closePageManagerModal()" data-i18n="modal.pm_btn_cancel">Hủy bỏ</button>
                    <button class="btn-primary" onclick="pmSaveAndApply()">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        <span data-i18n="modal.pm_btn_save">Lưu thay đổi</span>
                    </button>
                </div>
            </div>

            <!-- Modal Body: Pages Grid (Drag-drop reordering) -->
            <div class="pm-body" id="pmBody">
                <div class="pm-grid" id="pmCardsGrid">
                    <!-- Dynamic Page Cards rendered by JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: KÝ TÊN ĐIỆN TỬ (SIGNATURE) -->
    <div id="signatureModal" class="modal-overlay">
        <div class="modal-box modal-signature-box">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="brand-icon" style="width: 36px; height: 36px; border-radius: 8px;">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="m20.5 14.5-8-8-5 5 8 8Z"/><path d="M7.5 11.5 2 17v5h5l5.5-5.5"/><circle cx="17.5" cy="6.5" r="2.5"/></svg>
                    </div>
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0;" data-i18n="modal.sig_title">Tạo và Chèn Chữ ký số</h3>
                        <p style="font-size: 0.75rem; color: var(--text-muted); margin: 2px 0 0;" data-i18n="modal.sig_sub">Ký trực tiếp bằng tay, gõ tên hoặc tải ảnh con dấu chữ ký</p>
                    </div>
                </div>
                <button class="btn-close" onclick="closeModal('signatureModal')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="sig-tabs">
                <button type="button" class="sig-tab-btn active" id="tabSigDraw" onclick="switchSigTab('draw')">
                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                    <span data-i18n="modal.sig_tab_draw">Vẽ chữ ký</span>
                </button>
                <button type="button" class="sig-tab-btn" id="tabSigType" onclick="switchSigTab('type')">
                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
                    <span data-i18n="modal.sig_tab_type">Gõ tên</span>
                </button>
                <button type="button" class="sig-tab-btn" id="tabSigUpload" onclick="switchSigTab('upload')">
                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span data-i18n="modal.sig_tab_upload">Tải ảnh</span>
                </button>
            </div>

            <!-- Tab 1: Draw Canvas -->
            <div id="sigTabContentDraw" class="sig-tab-content">
                <div class="sig-pad-wrapper">
                    <canvas id="sigCanvas" width="560" height="180" class="sig-canvas"></canvas>
                    <div class="sig-pad-baseline"></div>
                    <div class="sig-pad-hint" data-i18n="modal.sig_draw_hint">Ký tên của bạn vào đây bằng chuột hoặc cảm ứng</div>
                </div>
                <div class="sig-toolbar">
                    <div class="sig-colors">
                        <span style="font-size: 0.78rem; font-weight: 600; color: #64748B;" data-i18n="modal.sig_ink">Mực ký:</span>
                        <button type="button" class="sig-color-dot active" style="--c: #1E3A8A;" onclick="setSigColor('#1E3A8A', this)" title="Xanh mực"></button>
                        <button type="button" class="sig-color-dot" style="--c: #0F172A;" onclick="setSigColor('#0F172A', this)" title="Đen"></button>
                        <button type="button" class="sig-color-dot" style="--c: #DC2626;" onclick="setSigColor('#DC2626', this)" title="Đỏ"></button>
                    </div>
                    <button type="button" class="btn-sig-clear" onclick="clearSigCanvas()">
                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        <span data-i18n="modal.sig_clear">Ký lại</span>
                    </button>
                </div>
            </div>

            <!-- Tab 2: Type Signature -->
            <div id="sigTabContentType" class="sig-tab-content" style="display: none;">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" data-i18n="modal.sig_type_lbl">Nhập họ tên của bạn:</label>
                    <input type="text" id="sigTextInput" class="auth-input" style="padding-left: 12px;" value="Nguyễn Văn A" oninput="updateTypedSigPreview()">
                </div>
                <div class="sig-typed-preview-box">
                    <div id="sigTypedPreview" class="sig-typed-text" style="font-family: 'Brush Script MT', 'Dancing Script', cursive, sans-serif; font-size: 38px; color: #1E3A8A;">Nguyễn Văn A</div>
                </div>
                <div class="sig-font-picker">
                    <button type="button" class="sig-font-option active" onclick="setSigFont('Dancing Script, cursive', this)" data-i18n="modal.sig_font_style1">Phong cách 1 (Viết tay thanh lịch)</button>
                    <button type="button" class="sig-font-option" onclick="setSigFont('Brush Script MT, cursive', this)" data-i18n="modal.sig_font_style2">Phong cách 2 (Nét bút lông ký nhanh)</button>
                    <button type="button" class="sig-font-option" onclick="setSigFont('Segoe Script, cursive', this)" data-i18n="modal.sig_font_style3">Phong cách 3 (Cổ điển nghiêng)</button>
                </div>
            </div>

            <!-- Tab 3: Upload Image -->
            <div id="sigTabContentUpload" class="sig-tab-content" style="display: none;">
                <div class="sig-upload-box" onclick="document.getElementById('sigImageFileInput').click()">
                    <svg class="svg-icon" style="width: 36px; height: 36px; color: #2563EB; margin-bottom: 8px;" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <div style="font-weight: 600; font-size: 0.9rem;" data-i18n="modal.sig_upload_prompt">Bấm để chọn ảnh chữ ký (PNG, JPG)</div>
                    <div style="font-size: 0.75rem; color: #94A3B8; margin-top: 4px;" data-i18n="modal.sig_upload_hint">Khuyến nghị ảnh nền trong suốt (Transparent PNG)</div>
                </div>
                <input type="file" id="sigImageFileInput" accept="image/png,image/jpeg" style="display: none;" onchange="handleSigImageUpload(event)">
                <div id="sigImagePreviewContainer" style="display: none; text-align: center; margin-top: 12px;">
                    <img id="sigUploadedImg" src="" style="max-height: 100px; max-width: 280px; border: 1px dashed #CBD5E1; padding: 6px; border-radius: 6px;">
                </div>
            </div>

            <div class="modal-actions" style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn-secondary" onclick="closeModal('signatureModal')" data-i18n="modal.pm_btn_cancel">Hủy bỏ</button>
                <button type="button" class="btn-primary" onclick="applySignatureToPage()">
                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    <span data-i18n="modal.sig_btn_apply">Chèn chữ ký vào trang</span>
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: CON DẤU PHÊ DUYỆT (OFFICIAL STAMPS) -->
    <div id="stampModal" class="modal-overlay">
        <div class="modal-box modal-stamp-box">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="brand-icon" style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #DC2626, #B91C1C);">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24" style="stroke: #fff;"><path d="M5 22h14"/><path d="M19.27 13.73A2.5 2.5 0 0 0 17.5 9h-11A2.5 2.5 0 0 0 4.73 13.73L7 17h10l2.27-3.27z"/><path d="M12 9V2"/></svg>
                    </div>
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0;" data-i18n="modal.stamp_title">Chọn con dấu đóng vào tài liệu</h3>
                        <p style="font-size: 0.75rem; color: var(--text-muted); margin: 2px 0 0;" data-i18n="modal.stamp_sub">Dấu tiêu chuẩn phê duyệt, phân loại bảo mật và xác thực công văn</p>
                    </div>
                </div>
                <button class="btn-close" onclick="closeModal('stampModal')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="stamps-grid">
                <div class="stamp-pick-item stamp-approved" onclick="insertStamp('approved')">
                    <div class="stamp-badge-preview approved">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">★ ĐÃ PHÊ DUYỆT ★</span>
                            <span class="stamp-date">NGÀY: 23/10/2026</span>
                        </div>
                    </div>
                    <div class="stamp-desc" data-i18n="modal.stamp_approved_desc">Đã phê duyệt (Chính thức)</div>
                </div>

                <div class="stamp-pick-item stamp-draft" onclick="insertStamp('draft')">
                    <div class="stamp-badge-preview draft">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">BẢN NHÁP (DRAFT)</span>
                            <span class="stamp-date">LƯU HÀNH NỘI BỘ</span>
                        </div>
                    </div>
                    <div class="stamp-desc" data-i18n="modal.stamp_draft_desc">Bản nháp thử nghiệm</div>
                </div>

                <div class="stamp-pick-item stamp-secret" onclick="insertStamp('secret')">
                    <div class="stamp-badge-preview secret">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">🔒 TUYỆT MẬT</span>
                            <span class="stamp-date">KHÔNG SAO CHÉP</span>
                        </div>
                    </div>
                    <div class="stamp-desc" data-i18n="modal.stamp_secret_desc">Bảo mật / Tuyệt mật</div>
                </div>

                <div class="stamp-pick-item stamp-completed" onclick="insertStamp('completed')">
                    <div class="stamp-badge-preview completed">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">✓ HOÀN THÀNH</span>
                            <span class="stamp-date">ĐẠT YÊU CẦU</span>
                        </div>
                    </div>
                    <div class="stamp-desc" data-i18n="modal.stamp_completed_desc">Nghiệm thu hoàn tất</div>
                </div>

                <div class="stamp-pick-item stamp-certified" onclick="insertStamp('certified')">
                    <div class="stamp-badge-preview certified">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">SAO Y BẢN CHÍNH</span>
                            <span class="stamp-date">VĂN THƯ ĐÃ DUYỆT</span>
                        </div>
                    </div>
                    <div class="stamp-desc" data-i18n="modal.stamp_certified_desc">Chứng thực bản sao</div>
                </div>

                <div class="stamp-pick-item stamp-void" onclick="insertStamp('void')">
                    <div class="stamp-badge-preview void">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">✕ HỦY BỎ (VOID)</span>
                            <span class="stamp-date">HẾT HIỆU LỰC</span>
                        </div>
                    </div>
                    <div class="stamp-desc" data-i18n="modal.stamp_void_desc">Hủy bỏ / Vô hiệu</div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: XUẤT TỆP THÀNH CÔNG (EXPORT RESULT) -->
    <div id="exportSuccessModal" class="modal-overlay">
        <div class="modal-box export-modal-box">
            <div class="export-success-header">
                <div class="export-success-icon-wrap">
                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: #0F172A; margin: 12px 0 4px;" data-i18n="modal.exp_title">Xuất tệp PDF thành công!</h3>
                <p style="font-size: 0.82rem; color: #64748B; margin: 0;" data-i18n="modal.exp_sub">Tài liệu đã được biên tập, nhúng vector chuẩn và sẵn sàng tải về</p>
            </div>

            <div class="export-meta-card">
                <div class="export-meta-row">
                    <span class="meta-lbl" data-i18n="modal.exp_lbl_file">Tên tệp:</span>
                    <strong class="meta-val" id="expDocName">Tai_lieu_bien_tap.pdf</strong>
                </div>
                <div class="export-meta-row">
                    <span class="meta-lbl" data-i18n="modal.exp_lbl_pages">Tổng số trang:</span>
                    <strong class="meta-val" id="expDocPages">11 trang</strong>
                </div>
                <div class="export-meta-row">
                    <span class="meta-lbl" data-i18n="modal.exp_lbl_status">Trạng thái:</span>
                    <span class="status-pill success"><span class="status-dot"></span> <span data-i18n="modal.exp_val_status">Đã hoàn tất 100%</span></span>
                </div>
                <div class="export-meta-row">
                    <span class="meta-lbl" data-i18n="modal.exp_lbl_format">Định dạng xuất:</span>
                    <strong class="meta-val">PDF chuẩn ISO/IEC 32000</strong>
                </div>
            </div>

            <div class="export-actions">
                <a href="#" id="expDownloadBtn" class="btn-primary btn-lg" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span data-i18n="modal.exp_btn_download">Tải xuống tệp PDF hoàn chỉnh</span>
                </a>
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button type="button" class="btn-outline" style="flex: 1;" onclick="viewExportManifest(currentOutputId)">
                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                        <span data-i18n="modal.exp_btn_manifest">Xem bảng đối chiếu</span>
                    </button>
                    <button type="button" class="btn-secondary" style="flex: 1;" onclick="closeModal('exportSuccessModal')">
                        <span data-i18n="modal.exp_btn_continue">Tiếp tục chỉnh sửa</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: XEM NHANH CHI TIẾT TỆP XUẤT (HISTORY PREVIEW MODAL) -->
    <div id="historyPreviewModal" class="modal-overlay">
        <div class="modal-box history-preview-box">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="action-icon-box blue" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div>
                        <h3 class="modal-title" id="prevHistTitle" data-i18n="modal.hist_prev_title">Chi tiết tệp xuất</h3>
                        <p class="modal-sub" id="prevHistSubtitle" data-i18n="modal.hist_prev_sub">Thông số kỹ thuật và cấu trúc tài liệu PDF</p>
                    </div>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('historyPreviewModal')">✕</button>
            </div>

            <div class="modal-body" style="padding: 20px;">
                <div class="history-preview-meta-grid">
                    <div class="meta-item">
                        <span class="meta-label" data-i18n="modal.hist_lbl_id">Mã định danh:</span>
                        <strong class="meta-value" id="prevHistId">#--</strong>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label" data-i18n="modal.hist_lbl_file">Tên tệp lưu trữ:</span>
                        <strong class="meta-value" id="prevHistFilename">--</strong>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label" data-i18n="modal.hist_lbl_pages">Tổng số trang:</span>
                        <strong class="meta-value" id="prevHistPages">-- trang</strong>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label" data-i18n="modal.hist_lbl_size">Kích thước tệp:</span>
                        <strong class="meta-value" id="prevHistSize">-- KB</strong>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label" data-i18n="modal.hist_lbl_time">Thời gian xuất:</span>
                        <strong class="meta-value" id="prevHistTime">--</strong>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label" data-i18n="modal.hist_lbl_std">Tiêu chuẩn xuất bản:</span>
                        <span class="status-pill success"><span class="status-dot"></span> PDF ISO/IEC 32000-1</span>
                    </div>
                </div>

                <div class="history-preview-summary-box">
                    <h4 style="font-size: 0.85rem; font-weight: 700; color: #1E293B; margin-bottom: 6px;" data-i18n="modal.hist_struct_title">Cấu trúc trang & Nguồn gốc xuất</h4>
                    <p style="font-size: 0.8rem; color: #64748B; margin-bottom: 12px;" id="prevHistSourceSummary" data-i18n="modal.hist_struct_sub">Tài liệu này được biên soạn từ các trang nguồn với dữ liệu đối chiếu Source Manifest.</p>
                    <div id="prevHistPageBadges" class="prev-page-badges-container"></div>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: #F8FAFC; border-top: 1px solid var(--border);">
                <button type="button" class="btn-outline" id="prevHistManifestBtn">
                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <span data-i18n="modal.hist_btn_manifest">Xem bảng đối chiếu chi tiết</span>
                </button>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn-secondary" onclick="closeModal('historyPreviewModal')" data-i18n="modal.hist_btn_close">Đóng</button>
                    <a href="#" id="prevHistDownloadBtn" class="btn-primary" style="text-decoration: none; display: flex; align-items: center; gap: 6px;">
                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <span data-i18n="modal.hist_btn_download">Tải xuống PDF</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notifications Container -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="<?= $baseUrl ?>/assets/js/app.js?v=<?= time() ?>"></script>
    <script>
        function toggleMobileSidebar() {
            const sidebar = document.getElementById('appSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            sidebar.classList.toggle('open');
            backdrop.classList.toggle('active');
        }
    </script>
</body>
</html>
