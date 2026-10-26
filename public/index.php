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

$validator = new SecurityValidator();
$storage = new StorageService();
$processor = new PdfProcessorService($validator, $storage, $pdo);

// Clean URL routing without hash (#): /home, /documents, /editor, /upgrade, /history, /settings
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$cleanRoute = trim($requestUri, '/');
if (in_array($cleanRoute, ['reorder', 'merge', 'split'])) {
    $cleanRoute = 'editor';
}
$validRoutes = ['home', 'documents', 'editor', 'upgrade', 'history', 'settings'];
$currentView = in_array($cleanRoute, $validRoutes) ? $cleanRoute : 'home';

// Endpoint Tải xuống file xuất
if ($requestUri === '/api/download') {
    $filename = basename($_GET['file'] ?? '');
    $filePath = __DIR__ . '/../storage/exports/' . $filename;
    if ($filename && file_exists($filePath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
    http_response_code(404);
    echo "Tệp không tồn tại hoặc đã hết hạn.";
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

    if ($requestUri === '/api/documents') {
        if ($pdo) {
            $effectiveUserId = $_SESSION['user_id'] ?? 1;
            $stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY id DESC");
            $stmt->execute([$effectiveUserId]);
            $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $docs = [];
        }
        echo json_encode(['status' => 'success', 'data' => $docs]);
        exit;
    }

    if ($requestUri === '/api/documents/upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy tệp tải lên.']);
            exit;
        }

        $file = $_FILES['file'];
        try {
            $validator->validateUpload($file);
            $origName = basename($file['name']);
            $storedName = 'doc_' . time() . '_' . $origName;
            $destPath = __DIR__ . '/../storage/documents/' . $storedName;

            move_uploaded_file($file['tmp_name'], $destPath);

            $pageCount = 1;
            try {
                $f = new \setasign\Fpdi\Fpdi();
                $pageCount = $f->setSourceFile($destPath);
            } catch (Exception $e) {
                $pageCount = 1;
            }

            if ($pdo) {
                $effectiveUserId = $_SESSION['user_id'] ?? 1;
                $stmt = $pdo->prepare("
                    INSERT INTO documents (user_id, original_name, stored_filename, file_size, total_pages, status)
                    VALUES (?, ?, ?, ?, ?, 'valid')
                ");
                $stmt->execute([$effectiveUserId, $origName, $storedName, $file['size'], $pageCount]);
                $docId = $pdo->lastInsertId();
            } else {
                $docId = time();
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Tải lên tài liệu thành công!',
                'document' => [
                    'id' => $docId,
                    'name' => $origName,
                    'pages' => $pageCount,
                    'size' => $file['size']
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($requestUri === '/api/export/merge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $plan = $input['plan'] ?? [];
        $title = $input['title'] ?? 'Tai_lieu_ghep.pdf';

        try {
            $effectiveUserId = $_SESSION['user_id'] ?? 1;
            $result = $processor->mergePages($effectiveUserId, 1, $plan, $title);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($requestUri === '/api/export/split' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $groups = $input['groups'] ?? [];
        $sourceDoc = $input['source_doc'] ?? 'Tai_lieu_hoc_tap.pdf';

        try {
            $effectiveUserId = $_SESSION['user_id'] ?? 1;
            $result = $processor->splitGroups($effectiveUserId, $groups, $sourceDoc);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($requestUri === '/api/export/history') {
        $history = [];
        if ($pdo) {
            $effectiveUserId = $_SESSION['user_id'] ?? 1;
            $stmt = $pdo->prepare("SELECT * FROM outputs WHERE user_id = ? ORDER BY id DESC LIMIT 20");
            $stmt->execute([$effectiveUserId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['status' => 'success', 'history' => $history]);
        exit;
    }

    if ($requestUri === '/api/export/manifest') {
        $outputId = (int)($_GET['id'] ?? 0);
        $manifest = [];
        if ($pdo && $outputId > 0) {
            $stmt = $pdo->prepare("SELECT filename, source_manifest, total_pages, created_at FROM outputs WHERE id = ?");
            $stmt->execute([$outputId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $manifest = json_decode($row['source_manifest'], true);
                echo json_encode([
                    'status' => 'success',
                    'filename' => $row['filename'],
                    'total_pages' => $row['total_pages'],
                    'created_at' => $row['created_at'],
                    'manifest' => $manifest
                ]);
                exit;
            }
        }
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy dữ liệu đối chiếu']);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'PDF Manager API v1.0']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Manager v1.0 - Sắp xếp, Ghép, Tách & Xử lý PDF</title>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= time() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileSidebar()"></div>

    <div class="app-layout <?= $currentView === 'editor' ? 'sidebar-compact' : '' ?>" id="appLayout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar <?= $currentView === 'editor' ? 'collapsed' : '' ?>" id="appSidebar">
            <a href="/home" class="brand" onclick="switchView('home', event)" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 12px; cursor: pointer;">
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
                    <p class="brand-sub">Sắp xếp - Ghép - Tách - Xử lý PDF</p>
                </div>
            </a>

            <nav class="nav-menu">
                <a href="/home" class="nav-item <?= $currentView === 'home' ? 'active' : '' ?>" data-view="home" title="Trang chủ">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </span>
                    <span>Trang chủ</span>
                </a>
                <a href="/documents" class="nav-item <?= $currentView === 'documents' ? 'active' : '' ?>" data-view="documents" title="Quản lý tài liệu">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    </span>
                    <span>Quản lý tài liệu</span>
                </a>
                <a href="/editor" class="nav-item <?= $currentView === 'editor' ? 'active' : '' ?>" data-view="editor" title="Chỉnh sửa PDF">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </span>
                    <span>Chỉnh sửa PDF</span>
                </a>
                <a href="/upgrade" class="nav-item <?= $currentView === 'upgrade' ? 'active' : '' ?>" data-view="upgrade" title="Nâng Cấp Gói">
                    <span class="nav-icon">
                        <svg class="svg-icon" style="color: #F59E0B;" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    </span>
                    <span style="font-weight: 600; color: #D97706;">Nâng Cấp</span>
                </a>
                <a href="/history" class="nav-item <?= $currentView === 'history' ? 'active' : '' ?>" data-view="history" title="Lịch sử xuất">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </span>
                    <span>Lịch sử xuất</span>
                </a>
                <a href="/settings" class="nav-item <?= $currentView === 'settings' ? 'active' : '' ?>" data-view="settings" title="Cài đặt">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </span>
                    <span>Cài đặt</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="storage-info">
                    <div class="storage-label">
                        <span>Dung lượng lưu trữ</span>
                        <span id="storageUsageText"><?= ($_SESSION['user_plan'] ?? 'FREE') === 'PRO' ? '125 MB / Không giới hạn' : '125 MB / 1 GB' ?></span>
                    </div>
                    <div class="progress-bar"><div class="progress-fill" id="storageProgressBar" style="width: <?= ($_SESSION['user_plan'] ?? 'FREE') === 'PRO' ? '8%' : '12.5%' ?>;"></div></div>
                    <a href="/upgrade" class="storage-upgrade" onclick="switchView('upgrade', event)"><?= ($_SESSION['user_plan'] ?? 'FREE') === 'PRO' ? 'Đã lên PRO' : 'Nâng cấp' ?></a>
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
                            <div class="user-name" style="color: #475569; font-size: 0.82rem;">Khách vãng lai</div>
                            <div class="user-role"><span class="badge" style="background: #F1F5F9; color: #64748B; font-size: 0.68rem;">Chưa đăng nhập</span></div>
                        </div>
                    </div>
                    <button type="button" class="btn-guest-login-sm" onclick="openAuthModal('login'); event.stopPropagation();">Đăng nhập</button>
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
                        <input type="text" id="globalSearch" placeholder="Tìm kiếm tài liệu, tệp...">
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
                                    <div class="plan-card-title">Gói dịch vụ: <strong id="ddPlanText"><?= htmlspecialchars($currentUser['plan'] ?? 'FREE') ?></strong></div>
                                    <div class="plan-card-sub" id="ddPlanSub"><?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'Mở khóa toàn bộ tính năng cao cấp' : 'Dung lượng 1GB • Ghép tách tiêu chuẩn' ?></div>
                                </div>
                                <button type="button" class="btn-plan-action" onclick="switchView('upgrade'); closeUserDropdown();" id="btnDdPlanUpgrade">
                                    <?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'Chi tiết gói' : 'Nâng cấp PRO' ?>
                                </button>
                            </div>

                            <div class="user-dd-divider"></div>

                            <!-- Menu chức năng thật -->
                            <button type="button" class="user-dd-item" onclick="openAccountModal()">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <span>Thông tin tài khoản</span>
                            </button>
                            <button type="button" class="user-dd-item" onclick="switchView('upgrade'); closeUserDropdown();">
                                <svg class="svg-icon svg-icon-sm text-warning" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                <span>Nâng cấp Gói dịch vụ</span>
                            </button>
                            <button type="button" class="user-dd-item" onclick="switchView('settings')">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                <span>Cài đặt hệ thống</span>
                            </button>
                            <button type="button" class="user-dd-item" onclick="switchView('history')">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <span>Lịch sử xuất tệp</span>
                            </button>

                            <div class="user-dd-divider"></div>

                            <button type="button" class="user-dd-item text-danger" onclick="logoutUser()">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                <span>Đăng xuất</span>
                            </button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="guest-topbar-actions">
                        <button type="button" class="btn-guest-login" onclick="openAuthModal('login')">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                            <span>Đăng nhập</span>
                        </button>
                        <button type="button" class="btn-guest-register" onclick="openAuthModal('register')">
                            <span>Đăng ký</span>
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
                            <h2>Xử lý PDF nhanh chóng,<br>linh hoạt và dễ dàng</h2>
                            <p>Ghép, tách, sắp xếp, xoay và chỉnh sửa tài liệu PDF chỉ với vài thao tác đơn giản.</p>
                            <div class="feature-badges">
                                <span class="feature-badge-item">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                                    An toàn (Bảo mật dữ liệu)
                                </span>
                                <span class="feature-badge-item">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                    Nhanh chóng (Xử lý trong vài giây)
                                </span>
                                <span class="feature-badge-item">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    Dễ sử dụng (Giao diện trực quan)
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
                        <a href="/editor" class="action-card" onclick="switchView('editor', event)">
                            <div class="action-main">
                                <div class="action-icon-box blue">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </div>
                                <div class="action-details">
                                    <h3>Chỉnh sửa PDF</h3>
                                    <p>Thêm chữ, vẽ, ký số, con dấu & ghi chú</p>
                                </div>
                            </div>
                            <span class="action-arrow">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        </a>

                        <!-- Card 2: Quản lý & Sắp xếp trang -->
                        <a href="/editor" class="action-card" onclick="switchView('editor', event); setTimeout(openPageManagerModal, 200);">
                            <div class="action-main">
                                <div class="action-icon-box green">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                                </div>
                                <div class="action-details">
                                    <h3>Quản lý trang PDF</h3>
                                    <p>Sắp xếp, xoay góc, nhân bản & xóa trang</p>
                                </div>
                            </div>
                            <span class="action-arrow">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        </a>

                        <!-- Card 3: Ghép & Tách tài liệu -->
                        <a href="/editor" class="action-card" onclick="switchView('editor', event); setTimeout(openPageManagerModal, 200);">
                            <div class="action-main">
                                <div class="action-icon-box purple">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                </div>
                                <div class="action-details">
                                    <h3>Ghép & Tách tài liệu</h3>
                                    <p>Nhập file ghép nối hoặc trích xuất nhóm trang</p>
                                </div>
                            </div>
                            <span class="action-arrow">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        </a>

                        <!-- Card 4: Nâng cấp gói PRO -->
                        <a href="/upgrade" class="action-card" onclick="switchView('upgrade', event)">
                            <div class="action-main">
                                <div class="action-icon-box orange">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                </div>
                                <div class="action-details">
                                    <h3>Nâng cấp gói PRO</h3>
                                    <p>Dung lượng không giới hạn & công cụ VIP</p>
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
                                <button class="tab-btn active" id="tabDocList" onclick="toggleDocTab('list')">Danh sách tài liệu</button>
                                <button class="tab-btn" id="tabProcessing" onclick="toggleDocTab('processing')">Đang xử lý (2)</button>
                            </div>
                            <div class="toolbar-actions">
                                <button class="btn-primary" onclick="openUploadModal()">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    <span>Tải lên tệp PDF</span>
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
                                <span class="status-pill success"><span class="status-dot"></span> Hoàn tất</span>
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
                                <span class="status-pill success"><span class="status-dot"></span> Hoàn tất</span>
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
                                <span class="status-pill success"><span class="status-dot"></span> Hoàn tất</span>
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
                                <span class="status-pill info"><span class="status-dot"></span> Đã xử lý</span>
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
                                <span class="status-pill danger"><span class="status-dot"></span> Lỗi</span>
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
                                <span class="status-pill success"><span class="status-dot"></span> Hoàn tất</span>
                            </div>
                        </div>

                        <!-- Recent Documents Table -->
                        <div class="table-section">
                            <div class="table-header">
                                <h3 class="table-title">Tài liệu gần đây</h3>
                                <a href="javascript:void(0)" class="storage-upgrade" style="margin: 0;" onclick="showToast('Xem danh mục đầy đủ tất cả tài liệu', 'info')">Xem tất cả →</a>
                            </div>
                            <div class="table-responsive">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th width="32"><input type="checkbox" id="checkAllDocs"></th>
                                        <th>Tên tệp</th>
                                        <th>Số trang</th>
                                        <th>Dung lượng</th>
                                        <th>Ngày tạo</th>
                                        <th>Trạng thái</th>
                                        <th>Thao tác</th>
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
                                        <td><span class="status-pill info"><span class="status-dot"></span> Đã xử lý</span></td>
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
                                        <td><span class="status-pill info"><span class="status-dot"></span> Đang xử lý</span></td>
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
                                        <td><span class="status-pill success"><span class="status-dot"></span> Hoàn tất</span></td>
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
                                        <td><span class="status-pill success"><span class="status-dot"></span> Hoàn tất</span></td>
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
                                        <td><span class="status-pill success"><span class="status-dot"></span> Hoàn tất</span></td>
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
                                <div>Hiển thị 1 - 5 / 6 tài liệu</div>
                                <div class="pagination-controls">
                                    <button class="page-btn">‹</button>
                                    <button class="page-btn active">1</button>
                                    <button class="page-btn">2</button>
                                    <button class="page-btn">›</button>
                                </div>
                                <div>10 / trang</div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Quick Stats & Guide -->
                    <div class="dashboard-side-col">
                        <!-- Quick Stats 2x2 with Solid Color SVG Boxes -->
                        <div class="side-card">
                            <h4 class="side-card-title">Thống kê nhanh</h4>
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-icon-sq blue">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    </div>
                                    <div class="stat-number">6</div>
                                    <div class="stat-label">Tổng số tài liệu</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon-sq green">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                                    </div>
                                    <div class="stat-number">40</div>
                                    <div class="stat-label">Tổng số trang</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon-sq purple">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    </div>
                                    <div class="stat-number">5</div>
                                    <div class="stat-label">Đã xử lý</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon-sq red">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    </div>
                                    <div class="stat-number">1</div>
                                    <div class="stat-label">Lỗi</div>
                                </div>
                            </div>
                        </div>

                        <!-- Guide List -->
                        <div class="side-card">
                            <h4 class="side-card-title">Hướng dẫn sử dụng</h4>
                            <ul class="guide-list">
                                <li class="guide-step">
                                    <span class="step-num">1</span>
                                    <span>Tải lên nhiều tệp PDF</span>
                                </li>
                                <li class="guide-step">
                                    <span class="step-num">2</span>
                                    <span>Chọn, sắp xếp, xoay trang</span>
                                </li>
                                <li class="guide-step">
                                    <span class="step-num">3</span>
                                    <span>Xem trước và kiểm tra kế hoạch</span>
                                </li>
                                <li class="guide-step">
                                    <span class="step-num">4</span>
                                    <span>Ghép hoặc tách tài liệu</span>
                                </li>
                                <li class="guide-step">
                                    <span class="step-num">5</span>
                                    <span>Tải xuống kết quả</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Promo Card -->
                        <div class="promo-card">
                            <h4>Làm việc hiệu quả hơn<br>với PDF Manager</h4>
                            <p>Tối ưu hóa tài liệu học tập và hồ sơ tốt nghiệp dễ dàng.</p>
                            <button class="btn-promo" onclick="switchView('reorder')">
                                <span>Xem chi tiết</span>
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
                            <h2 class="upload-state-title">Chỉnh sửa tệp PDF trực tuyến chuyên nghiệp</h2>
                            <p class="upload-state-subtitle">Thêm văn bản, vẽ tự do, ký số điện tử, đóng dấu phê duyệt và sắp xếp trang toàn diện</p>
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
                            <div class="dropzone-title">Kéo & Thả tệp PDF của bạn vào đây</div>
                            <div class="dropzone-sub">hoặc bấm vào khung để duyệt tệp từ máy tính</div>
                            <button type="button" class="btn-primary btn-upload-hero" onclick="event.stopPropagation(); document.getElementById('editorHeroFileInput').click();">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <span>Chọn tệp PDF tải lên</span>
                            </button>
                            <input type="file" id="editorHeroFileInput" accept="application/pdf" style="display: none;" onchange="handleHeroFileInput(event)">
                            <div class="dropzone-limits">Hỗ trợ PDF tối đa 50 MB • Xử lý bảo mật an toàn 100%</div>
                        </div>

                        <!-- Quick Samples Grid -->
                        <div class="upload-sample-section">
                            <div class="sample-section-header">
                                <span class="sample-section-line"></span>
                                <span class="sample-section-text">HOẶC CHỌN TÀI LIỆU CÓ SẴN ĐỂ CHỈNH SỬA NGAY</span>
                                <span class="sample-section-line"></span>
                            </div>

                            <div class="upload-sample-grid">
                                <div class="sample-card" onclick="loadSampleDocument('Tai_lieu_hoc_tap.pdf', 11, 'Báo cáo nghiên cứu mã nguồn mở')">
                                    <div class="sample-card-icon blue">
                                        <svg class="svg-icon svg-icon-md" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                    </div>
                                    <div class="sample-card-info">
                                        <div class="sample-name">Tai_lieu_hoc_tap.pdf</div>
                                        <div class="sample-meta">11 trang • Báo cáo nghiên cứu</div>
                                    </div>
                                    <span class="sample-arrow">→</span>
                                </div>

                                <div class="sample-card" onclick="loadSampleDocument('De_cuong_mon_hoc.pdf', 6, 'Đề cương môn học chi tiết')">
                                    <div class="sample-card-icon green">
                                        <svg class="svg-icon svg-icon-md" viewBox="0 0 24 24"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><polyline points="10 2 10 10 13 7 16 10 16 2"/></svg>
                                    </div>
                                    <div class="sample-card-info">
                                        <div class="sample-name">De_cuong_mon_hoc.pdf</div>
                                        <div class="sample-meta">6 trang • Đề cương học phần</div>
                                    </div>
                                    <span class="sample-arrow">→</span>
                                </div>

                                <div class="sample-card" onclick="loadSampleDocument('Do_an_tot_nghiep_mau.pdf', 15, 'Đồ án tốt nghiệp cử nhân')">
                                    <div class="sample-card-icon purple">
                                        <svg class="svg-icon svg-icon-md" viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                    </div>
                                    <div class="sample-card-info">
                                        <div class="sample-name">Do_an_tot_nghiep_mau.pdf</div>
                                        <div class="sample-meta">15 trang • Luận văn đồ án</div>
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
                                <span>Thêm văn bản</span>
                            </button>
                            <button class="ribbon-tool-btn" data-tool="edit-text" onclick="setEditorTool('edit-text')" title="Chỉnh sửa văn bản có sẵn trong PDF">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                <span>Chỉnh sửa văn bản</span>
                            </button>
                            <button class="ribbon-tool-btn" data-tool="signature" onclick="openSignatureModal()" title="Tạo hoặc chèn chữ ký">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="m20.5 14.5-8-8-5 5 8 8Z"/><path d="M7.5 11.5 2 17v5h5l5.5-5.5"/><circle cx="17.5" cy="6.5" r="2.5"/></svg>
                                <span>Ký tên</span>
                            </button>
                            <button class="ribbon-tool-btn" data-tool="draw" onclick="setEditorTool('draw')" title="Vẽ tự do nét mực">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                                <span>Vẽ</span>
                            </button>
                            
                            <!-- Shapes Dropdown Menu -->
                            <div class="ribbon-dropdown-wrapper">
                                <button class="ribbon-tool-btn dropdown-toggle" id="btnShapesDropdown" onclick="toggleShapesDropdown(event)" title="Đường thẳng & Hình học">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="5" y1="19" x2="19" y2="5"/></svg>
                                    <span id="currentShapeLabel">Đường thẳng</span>
                                    <svg class="svg-icon svg-icon-xs dropdown-caret" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                                </button>
                                <div class="ribbon-sub-menu" id="shapesMenu">
                                    <div class="sub-menu-item" onclick="selectShape('line', 'Đường thẳng')">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="5" y1="19" x2="19" y2="5"/></svg>
                                        <span>Đường thẳng</span>
                                    </div>
                                    <div class="sub-menu-item" onclick="selectShape('arrow', 'Mũi tên')">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                        <span>Mũi tên</span>
                                    </div>
                                    <div class="sub-menu-item" onclick="selectShape('rect', 'Hình chữ nhật')">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/></svg>
                                        <span>Hình chữ nhật</span>
                                    </div>
                                    <div class="sub-menu-item" onclick="selectShape('circle', 'Hình tròn')">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
                                        <span>Hình tròn</span>
                                    </div>
                                    <div class="sub-menu-item" onclick="selectShape('polygon', 'Đa giác')">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polygon points="12 2 22 8.5 22 15.5 12 22 2 15.5 2 8.5 12 2"/></svg>
                                        <span>Đa giác</span>
                                    </div>
                                </div>
                            </div>

                            <button class="ribbon-tool-btn" data-tool="highlight" onclick="setEditorTool('highlight')" title="Tô sáng / Bút nhớ">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="m9 11-6 6v3h3l6-6"/><path d="m22 12-4.6 4.6a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L14 4"/></svg>
                                <span>Tô sáng</span>
                            </button>
                            <button class="ribbon-tool-btn" data-tool="image" onclick="triggerEditorImageUpload()" title="Chèn hình ảnh">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                <span>Hình ảnh</span>
                            </button>
                            <button class="ribbon-tool-btn" data-tool="stamp" onclick="openStampModal()" title="Con dấu (Đã duyệt, Bản nháp...)">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M5 22h14"/><path d="M19.27 13.73A2.5 2.5 0 0 0 17.5 9h-11A2.5 2.5 0 0 0 4.73 13.73L7 17h10l2.27-3.27z"/><path d="M12 9V2"/></svg>
                                <span>Con dấu</span>
                            </button>
                            <button class="ribbon-tool-btn" data-tool="link" onclick="setEditorTool('link')" title="Thêm liên kết">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                <span>Liên kết</span>
                            </button>
                            <button class="ribbon-tool-btn" data-tool="note" onclick="setEditorTool('note')" title="Thêm ghi chú">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                <span>Ghi chú</span>
                            </button>
                        </div>

                        <div class="editor-ribbon-divider"></div>

                        <!-- Management & Export Actions -->
                        <div class="editor-ribbon-group actions-group">
                            <button class="ribbon-action-btn file-switch-btn" onclick="showEditorUploadState()" title="Đổi tệp PDF khác">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                                <span>Đổi tệp</span>
                            </button>
                            <button class="ribbon-action-btn" id="btnOpenPageManagerModal" onclick="openPageManagerModal()" title="Mở Trình quản lý trang toàn diện">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                                <span>Quản lý trang</span>
                            </button>
                            <button class="ribbon-icon-btn" onclick="printPdfDocument()" title="In ấn tài liệu">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            </button>
                            <button class="ribbon-icon-btn" onclick="toggleEditorSearch()" title="Tìm kiếm trong tài liệu">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            </button>
                            <button class="btn-complete-cta" onclick="finishAndSaveDocument()" title="Hoàn thành chỉnh sửa và lưu PDF">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Hoàn thành</span>
                            </button>
                        </div>
                    </div>

                    <!-- Editor Sub-Ribbon (Color swatches & Vector Icons) -->
                    <div class="editor-sub-ribbon">
                        <div class="sub-ribbon-colors">
                            <span class="sub-ribbon-label">Màu sắc:</span>
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
                            <span class="sub-ribbon-label">Biểu tượng:</span>
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
                            <span class="sub-ribbon-label">Cỡ chữ/nét:</span>
                            <select id="editorStrokeOrFontSize" class="ribbon-select" onchange="updateStrokeOrFontSize(this.value)">
                                <option value="12">12 px (Nhỏ)</option>
                                <option value="14" selected>14 px (Chuẩn)</option>
                                <option value="18">18 px (Vừa)</option>
                                <option value="24">24 px (Lớn)</option>
                                <option value="32">32 px (Tiêu đề)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Main Editor Body with Thumbnails Strip & Viewport -->
                    <div class="editor-main-workspace">
                        <!-- Left Thumbnails Strip -->
                        <aside class="editor-left-strip" id="editorThumbnailsAside">
                            <div class="strip-header">
                                <div class="strip-header-top">
                                    <span class="strip-title">Trang tài liệu</span>
                                    <span class="strip-count" id="stripPageCountBadge">11 trang</span>
                                </div>
                                <div class="strip-add-dropdown-wrapper">
                                    <button class="btn-strip-add" onclick="toggleAddPageDropdown(event)">
                                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                        <span>Thêm trang</span>
                                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                                    </button>
                                    <div class="strip-add-menu" id="stripAddMenu">
                                        <button class="strip-add-option" onclick="insertBlankPage()">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="9" y1="12" x2="15" y2="12"/></svg>
                                            <span>Trang trắng mới</span>
                                        </button>
                                        <button class="strip-add-option" onclick="triggerImportPdfPage()">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            <span>Nhập từ tệp tin...</span>
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
                                    <span class="floating-label">Trang:</span>
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
                        <div class="pricing-pill-badge">✨ Nâng cấp trải nghiệm tài liệu của bạn</div>
                        <h1 class="pricing-hero-title">Chọn gói dịch vụ phù hợp với nhu cầu của bạn</h1>
                        <p class="pricing-hero-subtitle">Tăng tốc xử lý tài liệu học tập, đồ án tốt nghiệp và hồ sơ nghiên cứu không giới hạn với sức mạnh của PDF Manager.</p>

                        <!-- Billing Toggle -->
                        <div class="billing-toggle-wrapper">
                            <span class="billing-cycle-label" id="labelMonthly">Thanh toán Hàng tháng</span>
                            <label class="billing-switch">
                                <input type="checkbox" id="billingCycleToggle" onchange="toggleBillingCycle(this.checked)">
                                <span class="billing-slider"></span>
                            </label>
                            <span class="billing-cycle-label" id="labelYearly">
                                Hàng năm <span class="discount-badge">Tiết kiệm 20% 🎉</span>
                            </span>
                        </div>
                    </div>

                    <!-- Pricing Cards Grid -->
                    <div class="pricing-cards-grid">
                        <!-- FREE TIER -->
                        <div class="pricing-card <?= ($userPlan === 'FREE') ? 'current-tier' : '' ?>">
                            <div class="card-plan-header">
                                <div class="card-tier-name">Miễn phí (FREE)</div>
                                <p class="card-tier-desc">Dành cho sinh viên và nhu cầu đọc, sắp xếp tài liệu cơ bản.</p>
                                <div class="card-tier-price">
                                    <span class="price-val">0 đ</span>
                                    <span class="price-period">/ vĩnh viễn</span>
                                </div>
                            </div>
                            <div class="card-plan-features">
                                <div class="feature-item"><span class="feature-icon check">✓</span> Tối đa 5 tài liệu / ngày</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Dung lượng lưu trữ 1 GB</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Trình chỉnh sửa văn bản cơ bản</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Ghép & tách tài liệu tiêu chuẩn</div>
                                <div class="feature-item disabled"><span class="feature-icon cross">✕</span> Không giới hạn số trang xử lý</div>
                                <div class="feature-item disabled"><span class="feature-icon cross">✕</span> Xử lý siêu tốc không watermark</div>
                                <div class="feature-item disabled"><span class="feature-icon cross">✕</span> Ký số & xác thực bảo mật</div>
                            </div>
                            <div class="card-plan-action">
                                <?php if ($userPlan === 'FREE'): ?>
                                    <button class="btn-tier-action btn-tier-current" disabled>Đang sử dụng</button>
                                <?php else: ?>
                                    <button class="btn-tier-action btn-tier-secondary" onclick="changePlan('FREE')">Chuyển về gói FREE</button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- PRO TIER (POPULAR) -->
                        <div class="pricing-card popular <?= ($userPlan === 'PRO') ? 'current-tier' : '' ?>">
                            <div class="popular-ribbon">🔥 PHỔ BIẾN NHẤT</div>
                            <div class="card-plan-header">
                                <div class="card-tier-name">Chuyên nghiệp (PRO)</div>
                                <p class="card-tier-desc">Dành cho sinh viên đồ án, nghiên cứu sinh và giảng viên cần xử lý cường độ cao.</p>
                                <div class="card-tier-price">
                                    <span class="price-val" id="proPriceValue">99.000 đ</span>
                                    <span class="price-period" id="proPricePeriod">/ tháng</span>
                                </div>
                                <div class="yearly-saving-hint" id="proYearlyHint" style="display: none;">Thanh toán 950.000 đ/năm (Tiết kiệm 238.000 đ)</div>
                            </div>
                            <div class="card-plan-features">
                                <div class="feature-item"><span class="feature-icon check">✓</span> <strong>Không giới hạn</strong> số lượng tài liệu</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <strong>Dung lượng không giới hạn</strong> (Cloud SSD)</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Trình biên tập trực quan nâng cao 100%</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Trình quản lý trang thông minh (Kéo thả, xoay)</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Chữ ký điện tử & Ghi chú đóng dấu chuyên nghiệp</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> <strong>Xử lý nhanh gấp 5 lần</strong> (Ưu tiên hàng đợi)</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Xuất file sạch đẹp, không watermark</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Hỗ trợ kỹ thuật ưu tiên 24/7</div>
                            </div>
                            <div class="card-plan-action">
                                <?php if ($userPlan === 'PRO'): ?>
                                    <button class="btn-tier-action btn-tier-current" disabled>⭐ Đang sử dụng gói PRO</button>
                                <?php else: ?>
                                    <button class="btn-tier-action btn-tier-primary" onclick="changePlan('PRO')">
                                        <span>Nâng cấp PRO ngay</span>
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- ENTERPRISE TIER -->
                        <div class="pricing-card <?= ($userPlan === 'ENTERPRISE') ? 'current-tier' : '' ?>">
                            <div class="card-plan-header">
                                <div class="card-tier-name">Doanh nghiệp / Khoa viện</div>
                                <p class="card-tier-desc">Dành cho nhóm nghiên cứu lớn, phòng ban hoặc toàn trường.</p>
                                <div class="card-tier-price">
                                    <span class="price-val" id="entPriceValue">299.000 đ</span>
                                    <span class="price-period" id="entPricePeriod">/ tháng</span>
                                </div>
                                <div class="yearly-saving-hint" id="entYearlyHint" style="display: none;">Thanh toán 2.870.000 đ/năm (Tiết kiệm 718.000 đ)</div>
                            </div>
                            <div class="card-plan-features">
                                <div class="feature-item"><span class="feature-icon check">✓</span> Tất cả quyền lợi gói <strong>PRO</strong></div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Quản lý tài khoản nhóm (Tối đa 25 thành viên)</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Phân quyền tài liệu & Thư mục dùng chung</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> REST API tích hợp trực tiếp vào hệ thống trường</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Nhật ký kiểm toán minh bạch (Audit Log)</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Quản lý viên hỗ trợ riêng (Dedicated Manager)</div>
                            </div>
                            <div class="card-plan-action">
                                <?php if ($userPlan === 'ENTERPRISE'): ?>
                                    <button class="btn-tier-action btn-tier-current" disabled>Đang sử dụng Enterprise</button>
                                <?php else: ?>
                                    <button class="btn-tier-action btn-tier-secondary" onclick="changePlan('ENTERPRISE')">Nâng cấp Enterprise</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Comparison Table -->
                    <div class="comparison-section">
                        <h3 class="comparison-title">Bảng so sánh chi tiết tính năng</h3>
                        <div class="table-responsive">
                            <table class="comparison-table">
                                <thead>
                                    <tr>
                                        <th>Tính năng</th>
                                        <th>Miễn phí (FREE)</th>
                                        <th class="col-highlight">Chuyên nghiệp (PRO)</th>
                                        <th>Doanh nghiệp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Số lượng tài liệu xử lý</td>
                                        <td>5 tài liệu / ngày</td>
                                        <td class="col-highlight font-weight-bold">Không giới hạn</td>
                                        <td>Không giới hạn</td>
                                    </tr>
                                    <tr>
                                        <td>Dung lượng lưu trữ đám mây</td>
                                        <td>1 GB</td>
                                        <td class="col-highlight font-weight-bold">Không giới hạn</td>
                                        <td>Không giới hạn</td>
                                    </tr>
                                    <tr>
                                        <td>Trình biên tập PDFaid (Văn bản, Bút vẽ, Con dấu)</td>
                                        <td>Cơ bản</td>
                                        <td class="col-highlight text-success">✓ Đầy đủ công cụ</td>
                                        <td class="text-success">✓ Đầy đủ công cụ</td>
                                    </tr>
                                    <tr>
                                        <td>Trình quản lý trang (Xoay, Sắp xếp, Tách, Nhập)</td>
                                        <td>Tối đa 10 trang</td>
                                        <td class="col-highlight text-success">✓ Không giới hạn trang</td>
                                        <td class="text-success">✓ Không giới hạn trang</td>
                                    </tr>
                                    <tr>
                                        <td>Bảng đối chiếu nguồn tài liệu (Manifest)</td>
                                        <td>Cơ bản</td>
                                        <td class="col-highlight text-success">✓ Chi tiết từng trang</td>
                                        <td class="text-success">✓ Chi tiết từng trang</td>
                                    </tr>
                                    <tr>
                                        <td>Tốc độ xử lý hàng đợi</td>
                                        <td>Tiêu chuẩn</td>
                                        <td class="col-highlight font-weight-bold">Nhanh gấp 5 lần</td>
                                        <td>Ưu tiên cao nhất</td>
                                    </tr>
                                    <tr>
                                        <td>API & Quản lý nhóm đa người dùng</td>
                                        <td class="text-muted">✕</td>
                                        <td class="col-highlight text-muted">✕</td>
                                        <td class="text-success">✓ Có sẵn</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- FAQ Section -->
                    <div class="pricing-faq-section">
                        <h3 class="faq-title">Câu hỏi thường gặp</h3>
                        <div class="faq-accordion">
                            <div class="faq-item">
                                <div class="faq-question" onclick="toggleFaq(this)">
                                    <span>Tôi có thể hủy hoặc thay đổi gói bất cứ lúc nào không?</span>
                                    <span class="faq-toggle-icon">+</span>
                                </div>
                                <div class="faq-answer">
                                    Hoàn toàn có thể. Bạn có thể nâng cấp, hạ cấp hoặc hủy gói bất kỳ lúc nào ngay trong phần Cài đặt tài khoản. Không có phụ phí phát sinh nào.
                                </div>
                            </div>
                            <div class="faq-item">
                                <div class="faq-question" onclick="toggleFaq(this)">
                                    <span>Phương thức thanh toán nào được hỗ trợ?</span>
                                    <span class="faq-toggle-icon">+</span>
                                </div>
                                <div class="faq-answer">
                                    Chúng tôi hỗ trợ chuyển khoản ngân hàng QR Code (VietQR / Napas 247), ví MoMo, ZaloPay, thẻ tín dụng / ghi nợ nội địa và quốc tế (Visa, Mastercard).
                                </div>
                            </div>
                            <div class="faq-item">
                                <div class="faq-question" onclick="toggleFaq(this)">
                                    <span>Dữ liệu và tài liệu PDF của tôi có được bảo mật không?</span>
                                    <span class="faq-toggle-icon">+</span>
                                </div>
                                <div class="faq-answer">
                                    Tất cả tài liệu được mã hóa truyền tải theo chuẩn SSL/TLS và lưu trữ an toàn trong phân vùng riêng biệt của tài khoản bạn. Dữ liệu của bạn không bao giờ được chia sẻ với bất kỳ bên thứ ba nào.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VIEW 5: LỊCH SỬ XUẤT -->
            <div id="view-history" class="workspace-view <?= $currentView === 'history' ? 'active' : '' ?>">
                <div class="table-section">
                    <div class="table-header">
                        <div>
                            <h2 style="font-size: 1.25rem; font-weight: 700;">Lịch sử xuất & Bảng đối chiếu nguồn</h2>
                            <p style="font-size: 0.8rem; color: var(--text-muted);">Quản lý tất cả các tệp PDF đã xuất, tra cứu minh bạch nguồn gốc từng trang</p>
                        </div>
                        <button class="btn-outline" onclick="loadExportHistory()">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10"/></svg>
                            <span>Làm mới</span>
                        </button>
                    </div>

                    <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Mã ID</th>
                                <th>Tên tệp xuất</th>
                                <th>Số trang</th>
                                <th>Dung lượng</th>
                                <th>Thời gian xuất</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">Đang tải lịch sử xuất...</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

            <!-- VIEW 6: CÀI ĐẶT HỆ THỐNG -->
            <div id="view-settings" class="workspace-view <?= $currentView === 'settings' ? 'active' : '' ?>">
                <div class="table-section">
                    <div class="table-header">
                        <div>
                            <h2 style="font-size: 1.25rem; font-weight: 700;">Cài đặt hệ thống & Tùy chọn xử lý</h2>
                            <p style="font-size: 0.8rem; color: var(--text-muted);">Tùy biến cấu hình lưu trữ, chất lượng xuất PDF và tài khoản</p>
                        </div>
                    </div>

                    <div class="settings-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 16px;">
                        <!-- Thẻ 1: Thông tin gói & dung lượng -->
                        <div class="setting-card" style="background: white; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                                <h3 style="font-size: 1rem; font-weight: 700;">Gói dịch vụ & Bộ nhớ</h3>
                                <span class="plan-badge <?= strtolower($currentUser['plan'] ?? 'FREE') ?>">
                                    <?= htmlspecialchars($currentUser['plan'] ?? 'FREE') ?>
                                </span>
                            </div>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 14px;">
                                <?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'Bạn đang sử dụng gói PRO với dung lượng không giới hạn và tốc độ xử lý ưu tiên cao nhất.' : 'Tài khoản miễn phí với 1GB lưu trữ và các công cụ ghép, tách chuẩn.' ?>
                            </p>
                            <button type="button" class="btn-primary" onclick="openPlanModal()" style="width: 100%;">
                                <?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'Quản lý gói PRO' : 'Nâng cấp lên PRO ngay' ?>
                            </button>
                        </div>

                        <!-- Thẻ 2: Cấu hình xuất tệp PDF -->
                        <div class="setting-card" style="background: white; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px;">
                            <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 14px;">Tùy chọn xuất tệp</h3>
                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 0.8rem; font-weight: 600; color: #475569; display: block; margin-bottom: 6px;">Độ phân giải trang xem trước</label>
                                <select class="auth-input" style="padding-left: 12px; height: 38px;">
                                    <option>150 DPI (Tiêu chuẩn - Tốc độ nhanh)</option>
                                    <option>300 DPI (Chất lượng in ấn sắc nét)</option>
                                </select>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 14px;">
                                <span style="font-size: 0.85rem; color: #334155;">Tự động tối ưu dung lượng tệp xuất</span>
                                <input type="checkbox" checked style="width: 18px; height: 18px; cursor: pointer;">
                            </div>
                        </div>

                        <!-- Thẻ 3: Bảo mật & Dữ liệu -->
                        <div class="setting-card" style="background: white; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px;">
                            <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 14px;">Bảo mật & Tự động dọn dẹp</h3>
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <div>
                                    <div style="font-size: 0.85rem; font-weight: 600; color: #334155;">Tự hủy tệp tạm sau 24h</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">Bảo vệ quyền riêng tư tài liệu theo tiêu chuẩn</div>
                                </div>
                                <input type="checkbox" checked style="width: 18px; height: 18px; cursor: pointer;">
                            </div>
                            <button type="button" class="btn-outline" onclick="showToast('Đã lưu cấu hình cài đặt hệ thống.', 'success')" style="width: 100%; margin-top: 10px;">
                                Lưu thiết lập
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION (Khớp bản thiết kế Mobile) -->
    <nav class="mobile-bottom-nav">
        <a href="/home" class="mobile-nav-item <?= $currentView === 'home' ? 'active' : '' ?>" data-view="home" onclick="switchView('home', event)">
            <svg class="svg-icon" viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Trang chủ</span>
        </a>
        <a href="/documents" class="mobile-nav-item <?= $currentView === 'documents' ? 'active' : '' ?>" data-view="documents" onclick="switchView('documents', event)">
            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <span>Tài liệu</span>
        </a>
        <div class="mobile-nav-item center-action" onclick="switchView('editor', event)" title="Mở Trình Chỉnh sửa PDF">
            <div class="mobile-plus-circle" style="background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);">
                <svg class="svg-icon svg-icon-lg" viewBox="0 0 24 24" style="stroke: white;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </div>
        </div>
        <a href="/upgrade" class="mobile-nav-item <?= $currentView === 'upgrade' ? 'active' : '' ?>" data-view="upgrade" onclick="switchView('upgrade', event)">
            <svg class="svg-icon" viewBox="0 0 24 24" style="color: #F59E0B;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <span>Nâng Cấp</span>
        </a>
        <a href="/settings" class="mobile-nav-item <?= $currentView === 'settings' ? 'active' : '' ?>" data-view="settings" onclick="switchView('settings', event)">
            <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span>Cài đặt</span>
        </a>
    </nav>

    <!-- MODAL: TẢI LÊN TỆP PDF -->
    <div id="uploadModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Tải lên tệp PDF mới</h3>
                <button class="btn-close" onclick="closeModal('uploadModal')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="drop-zone" id="uploadDropZone" onclick="document.getElementById('fileInput').click()">
                <div style="margin-bottom: 12px; color: #2563EB;">
                    <svg class="svg-icon" style="width: 48px; height: 48px;" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                </div>
                <h4 style="font-size: 1rem; margin-bottom: 4px; font-weight: 700;">Kéo & Thả tệp PDF vào đây</h4>
                <p style="font-size: 0.8rem; color: var(--text-muted);">hoặc bấm vào để chọn tệp từ máy tính</p>
                <p style="font-size: 0.72rem; color: #94A3B8; margin-top: 8px;">Giới hạn dung lượng tối đa: 25 MB • Chỉ chấp nhận định dạng .pdf</p>
            </div>
            <input type="file" id="fileInput" accept="application/pdf" style="display: none;" onchange="handleFileSelect(event)">
            <div id="uploadProgress" style="display: none; margin-top: 12px;">
                <div style="font-size: 0.8rem; margin-bottom: 4px;">Đang xử lý tải lên...</div>
                <div class="progress-bar"><div class="progress-fill" style="width: 100%;"></div></div>
            </div>
        </div>
    </div>

    <!-- MODAL: BẢNG ĐỐI CHIẾU TRANG NGUỒN (SOURCE MANIFEST) -->
    <div id="manifestModal" class="modal-overlay">
        <div class="modal-box modal-lg">
            <div class="modal-header">
                <div>
                    <h3 id="manifestModalTitle">Bảng đối chiếu trang nguồn (Source Manifest)</h3>
                    <p style="font-size: 0.75rem; color: var(--text-muted);">Xác minh từng trang đầu ra lấy từ tệp gốc và trang gốc nào</p>
                </div>
                <button class="btn-close" onclick="closeModal('manifestModal')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Trang kết quả</th>
                        <th>Tệp nguồn gốc</th>
                        <th>Trang gốc</th>
                        <th>Nhãn nhận diện</th>
                        <th>Góc xoay</th>
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
                        <h3 id="authModalTitle" style="font-size: 1.15rem; font-weight: 700; margin-bottom: 2px;">Tài khoản PDF Manager</h3>
                        <p style="font-size: 0.74rem; color: var(--text-muted);">Đăng nhập hoặc đăng ký tài khoản để quản lý tài liệu cá nhân</p>
                    </div>
                </div>
                <button class="btn-close" onclick="closeModal('authModal')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <!-- Auth Tabs: Đăng nhập / Đăng ký -->
            <div class="auth-tabs">
                <button type="button" class="auth-tab-btn active" id="tabAuthLogin" onclick="switchAuthTab('login')">Đăng nhập</button>
                <button type="button" class="auth-tab-btn" id="tabAuthRegister" onclick="switchAuthTab('register')">Đăng ký mới</button>
            </div>

            <!-- Form Đăng nhập -->
            <form id="formLogin" onsubmit="handleAuthLogin(event)" class="auth-form-body">
                <div class="form-group">
                    <label class="form-label">Tên đăng nhập hoặc Email</label>
                    <div class="input-with-icon">
                        <svg class="svg-icon svg-icon-sm input-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" id="loginUsername" required placeholder="Nhập tên đăng nhập hoặc email..." class="auth-input">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Mật khẩu</label>
                    <div class="input-with-icon">
                        <svg class="svg-icon svg-icon-sm input-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="loginPassword" required placeholder="Nhập mật khẩu của bạn..." class="auth-input">
                    </div>
                </div>

                <button type="submit" class="btn-primary auth-submit-btn" id="btnLoginSubmit">
                    <span>Đăng nhập</span>
                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                </button>

                <div class="auth-switch-prompt">
                    Chưa có tài khoản? <a href="javascript:void(0)" onclick="switchAuthTab('register')">Đăng ký tài khoản mới</a>
                </div>
            </form>

            <!-- Form Đăng ký -->
            <form id="formRegister" onsubmit="handleAuthRegister(event)" class="auth-form-body" style="display: none;">
                <div class="form-group">
                    <label class="form-label">Họ và tên đầy đủ</label>
                    <div class="input-with-icon">
                        <svg class="svg-icon svg-icon-sm input-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" id="regFullName" required placeholder="Ví dụ: Nguyễn Văn An..." class="auth-input">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Tên đăng nhập</label>
                    <div class="input-with-icon">
                        <svg class="svg-icon svg-icon-sm input-icon" viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>
                        <input type="text" id="regUsername" required placeholder="Nhập tên đăng nhập (vd: nguyenvana)..." class="auth-input">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Địa chỉ Email</label>
                    <div class="input-with-icon">
                        <svg class="svg-icon svg-icon-sm input-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <input type="email" id="regEmail" required placeholder="vd: user@example.com" class="auth-input">
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Mật khẩu (tối thiểu 6 ký tự)</label>
                        <input type="password" id="regPassword" required minlength="6" placeholder="Mật khẩu..." class="auth-input" style="padding-left: 12px;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Xác nhận mật khẩu</label>
                        <input type="password" id="regPasswordConfirm" required minlength="6" placeholder="Nhập lại..." class="auth-input" style="padding-left: 12px;">
                    </div>
                </div>

                <button type="submit" class="btn-primary auth-submit-btn" id="btnRegisterSubmit">
                    <span>Tạo tài khoản và Đăng nhập</span>
                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                </button>

                <div class="auth-switch-prompt">
                    Đã có tài khoản? <a href="javascript:void(0)" onclick="switchAuthTab('login')">Đăng nhập ngay</a>
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
                        <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 2px;">Gói dịch vụ & Quyền lợi</h3>
                        <p style="font-size: 0.74rem; color: var(--text-muted);">Lựa chọn gói phù hợp với tần suất xử lý tệp PDF của bạn</p>
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
                        <h4 style="font-size: 1.05rem; margin: 8px 0 4px; font-weight: 700;">Gói Miễn Phí</h4>
                        <div class="pricing-price">0đ <span style="font-size: 0.76rem; font-weight: normal; color: var(--text-muted);">/ trọn đời</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Dung lượng lưu trữ: 1 GB</li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Ghép, tách, sắp xếp trang tiêu chuẩn</li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Tối đa 50 trang mỗi tài liệu</li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Xuất file PDF chất lượng gốc</li>
                        <li class="disabled"><svg class="svg-icon svg-icon-xs text-muted" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Dung lượng không giới hạn</li>
                        <li class="disabled"><svg class="svg-icon svg-icon-xs text-muted" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Ưu tiên hàng chờ xử lý siêu tốc</li>
                    </ul>
                    <?php if (($_SESSION['user_plan'] ?? 'FREE') === 'FREE'): ?>
                        <button type="button" class="btn-plan-select btn-current" disabled>✓ Đang sử dụng</button>
                    <?php else: ?>
                        <button type="button" class="btn-plan-select outline" onclick="upgradePlan('FREE')">Chuyển sang gói FREE</button>
                    <?php endif; ?>
                </div>

                <!-- Gói PRO -->
                <div class="pricing-card pro-card <?= ($_SESSION['user_plan'] ?? 'FREE') === 'PRO' ? 'current-active-plan' : '' ?>">
                    <div class="pro-ribbon">PHỔ BIẾN NHẤT</div>
                    <div class="pricing-card-header">
                        <span class="plan-badge pro">PRO</span>
                        <h4 style="font-size: 1.05rem; margin: 8px 0 4px; font-weight: 700;">Gói Chuyên Nghiệp</h4>
                        <div class="pricing-price">99.000đ <span style="font-size: 0.76rem; font-weight: normal; color: var(--text-muted);">/ tháng</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> <strong>Không giới hạn</strong> dung lượng lưu trữ</li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Không giới hạn số trang tệp PDF</li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Tốc độ xử lý siêu tốc ưu tiên hàng đầu</li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Nén file tối ưu hóa dung lượng</li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Trích xuất và bảo mật cao cấp</li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Hỗ trợ kỹ thuật chuyên gia 24/7</li>
                    </ul>
                    <?php if (($_SESSION['user_plan'] ?? 'FREE') === 'PRO'): ?>
                        <button type="button" class="btn-plan-select btn-current" disabled>✓ Đang kích hoạt</button>
                    <?php else: ?>
                        <button type="button" class="btn-plan-select pro-btn" onclick="upgradePlan('PRO')">
                            <span>Nâng cấp PRO ngay</span>
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
                    <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 2px;">Thông tin tài khoản</h3>
                    <p style="font-size: 0.74rem; color: var(--text-muted);">Quản lý hồ sơ cá nhân và gói hội viên của bạn</p>
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
                    <span class="info-label">Tên đăng nhập:</span>
                    <strong class="info-value" id="accModalUsername"><?= htmlspecialchars($_SESSION['username'] ?? 'nguyenvana') ?></strong>
                </div>
                <div class="account-info-item">
                    <span class="info-label">Gói dịch vụ:</span>
                    <strong class="info-value" id="accModalPlan"><?= htmlspecialchars($_SESSION['user_plan'] ?? 'FREE') ?></strong>
                </div>
                <div class="account-info-item">
                    <span class="info-label">Trạng thái:</span>
                    <strong class="info-value text-success" style="display: flex; align-items: center; gap: 4px;">
                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #10B981; display: inline-block;"></span>
                        Hoạt động bình thường
                    </strong>
                </div>
                <div class="account-info-item">
                    <span class="info-label">Dung lượng:</span>
                    <strong class="info-value" id="accModalStorage"><?= ($_SESSION['user_plan'] ?? 'FREE') === 'PRO' ? 'Không giới hạn' : '1 GB (Đã dùng 125 MB)' ?></strong>
                </div>
            </div>

            <div class="modal-actions" style="margin-top: 22px; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                <button type="button" class="btn-primary" onclick="switchView('upgrade'); closeModal('accountModal');" style="padding: 9px 18px; font-size: 0.84rem;">
                    <?= ($_SESSION['user_plan'] ?? 'FREE') === 'PRO' ? '⭐ Chi tiết gói PRO' : '⚡ Nâng cấp lên gói PRO' ?>
                </button>
                <button type="button" class="btn-secondary" onclick="logoutUser()" style="color: #EF4444; border-color: #FCA5A5; padding: 9px 16px; font-size: 0.84rem;">
                    Đăng xuất
                </button>
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
                        <h3 class="pm-title">Quản lý trang tài liệu</h3>
                        <p class="pm-subtitle" id="pmPageCounterText">11 trang • Kéo thả để sắp xếp, chọn để thao tác</p>
                    </div>
                </div>

                <div class="pm-toolbar">
                    <button class="pm-tool-btn" onclick="pmAddNewPage()" title="Thêm trang trắng">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span>Thêm trang</span>
                    </button>
                    <button class="pm-tool-btn" onclick="pmRotateSelected(-90)" title="Xoay ngược chiều kim đồng hồ 90°">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M2.5 2v6h6"/><path d="M2.5 8a10 10 0 1 1 2.4 7.6"/></svg>
                        <span>Xoay trái</span>
                    </button>
                    <button class="pm-tool-btn" onclick="pmRotateSelected(90)" title="Xoay theo chiều kim đồng hồ 90°">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21.5 2v6h-6"/><path d="M21.5 8a10 10 0 1 0-2.4 7.6"/></svg>
                        <span>Xoay phải</span>
                    </button>
                    <button class="pm-tool-btn text-danger" onclick="pmDeleteSelected()" title="Xóa các trang đã chọn">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        <span>Xóa trang</span>
                    </button>
                    <button class="pm-tool-btn" onclick="pmDuplicateSelected()" title="Nhân đôi trang đã chọn">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <span>Nhân bản</span>
                    </button>
                    <button class="pm-tool-btn" onclick="pmImportDocument()" title="Nhập thêm trang từ tệp PDF khác">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <span>Nhập PDF</span>
                    </button>

                    <div class="pm-divider"></div>

                    <button class="pm-tool-btn" onclick="pmToggleSelectAll()" id="btnPmSelectAll">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><polyline points="9 11 12 14 22 4"/></svg>
                        <span id="pmSelectAllLabel">Chọn tất cả</span>
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
                        <span style="font-size: 0.75rem; color: var(--text-muted);">Cỡ thẻ:</span>
                        <input type="range" min="130" max="260" value="170" class="pm-slider" id="pmCardSizeSlider" oninput="adjustPmCardSize(this.value)">
                    </div>
                    <button class="btn-secondary" onclick="closePageManagerModal()">Hủy bỏ</button>
                    <button class="btn-primary" onclick="pmSaveAndApply()">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Lưu thay đổi</span>
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
                        <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Tạo và Chèn Chữ ký số</h3>
                        <p style="font-size: 0.75rem; color: var(--text-muted); margin: 2px 0 0;">Ký trực tiếp bằng tay, gõ tên hoặc tải ảnh con dấu chữ ký</p>
                    </div>
                </div>
                <button class="btn-close" onclick="closeModal('signatureModal')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="sig-tabs">
                <button type="button" class="sig-tab-btn active" id="tabSigDraw" onclick="switchSigTab('draw')">
                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                    <span>Vẽ chữ ký</span>
                </button>
                <button type="button" class="sig-tab-btn" id="tabSigType" onclick="switchSigTab('type')">
                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
                    <span>Gõ tên</span>
                </button>
                <button type="button" class="sig-tab-btn" id="tabSigUpload" onclick="switchSigTab('upload')">
                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span>Tải ảnh</span>
                </button>
            </div>

            <!-- Tab 1: Draw Canvas -->
            <div id="sigTabContentDraw" class="sig-tab-content">
                <div class="sig-pad-wrapper">
                    <canvas id="sigCanvas" width="560" height="180" class="sig-canvas"></canvas>
                    <div class="sig-pad-baseline"></div>
                    <div class="sig-pad-hint">Ký tên của bạn vào đây bằng chuột hoặc cảm ứng</div>
                </div>
                <div class="sig-toolbar">
                    <div class="sig-colors">
                        <span style="font-size: 0.78rem; font-weight: 600; color: #64748B;">Mực ký:</span>
                        <button type="button" class="sig-color-dot active" style="--c: #1E3A8A;" onclick="setSigColor('#1E3A8A', this)" title="Xanh mực"></button>
                        <button type="button" class="sig-color-dot" style="--c: #0F172A;" onclick="setSigColor('#0F172A', this)" title="Đen"></button>
                        <button type="button" class="sig-color-dot" style="--c: #DC2626;" onclick="setSigColor('#DC2626', this)" title="Đỏ"></button>
                    </div>
                    <button type="button" class="btn-sig-clear" onclick="clearSigCanvas()">
                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        <span>Ký lại</span>
                    </button>
                </div>
            </div>

            <!-- Tab 2: Type Signature -->
            <div id="sigTabContentType" class="sig-tab-content" style="display: none;">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Nhập họ tên của bạn:</label>
                    <input type="text" id="sigTextInput" class="auth-input" style="padding-left: 12px;" value="Nguyễn Văn A" oninput="updateTypedSigPreview()">
                </div>
                <div class="sig-typed-preview-box">
                    <div id="sigTypedPreview" class="sig-typed-text" style="font-family: 'Brush Script MT', 'Dancing Script', cursive, sans-serif; font-size: 38px; color: #1E3A8A;">Nguyễn Văn A</div>
                </div>
                <div class="sig-font-picker">
                    <button type="button" class="sig-font-option active" onclick="setSigFont('Dancing Script, cursive', this)">Phong cách 1 (Viết tay thanh lịch)</button>
                    <button type="button" class="sig-font-option" onclick="setSigFont('Brush Script MT, cursive', this)">Phong cách 2 (Nét bút lông ký nhanh)</button>
                    <button type="button" class="sig-font-option" onclick="setSigFont('Segoe Script, cursive', this)">Phong cách 3 (Cổ điển nghiêng)</button>
                </div>
            </div>

            <!-- Tab 3: Upload Image -->
            <div id="sigTabContentUpload" class="sig-tab-content" style="display: none;">
                <div class="sig-upload-box" onclick="document.getElementById('sigImageFileInput').click()">
                    <svg class="svg-icon" style="width: 36px; height: 36px; color: #2563EB; margin-bottom: 8px;" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <div style="font-weight: 600; font-size: 0.9rem;">Bấm để chọn ảnh chữ ký (PNG, JPG)</div>
                    <div style="font-size: 0.75rem; color: #94A3B8; margin-top: 4px;">Khuyến nghị ảnh nền trong suốt (Transparent PNG)</div>
                </div>
                <input type="file" id="sigImageFileInput" accept="image/png,image/jpeg" style="display: none;" onchange="handleSigImageUpload(event)">
                <div id="sigImagePreviewContainer" style="display: none; text-align: center; margin-top: 12px;">
                    <img id="sigUploadedImg" src="" style="max-height: 100px; max-width: 280px; border: 1px dashed #CBD5E1; padding: 6px; border-radius: 6px;">
                </div>
            </div>

            <div class="modal-actions" style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn-secondary" onclick="closeModal('signatureModal')">Hủy bỏ</button>
                <button type="button" class="btn-primary" onclick="applySignatureToPage()">
                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>Chèn chữ ký vào trang</span>
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
                        <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Chọn con dấu đóng vào tài liệu</h3>
                        <p style="font-size: 0.75rem; color: var(--text-muted); margin: 2px 0 0;">Dấu tiêu chuẩn phê duyệt, phân loại bảo mật và xác thực công văn</p>
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
                    <div class="stamp-desc">Đã phê duyệt (Chính thức)</div>
                </div>

                <div class="stamp-pick-item stamp-draft" onclick="insertStamp('draft')">
                    <div class="stamp-badge-preview draft">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">BẢN NHÁP (DRAFT)</span>
                            <span class="stamp-date">LƯU HÀNH NỘI BỘ</span>
                        </div>
                    </div>
                    <div class="stamp-desc">Bản nháp thử nghiệm</div>
                </div>

                <div class="stamp-pick-item stamp-secret" onclick="insertStamp('secret')">
                    <div class="stamp-badge-preview secret">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">🔒 TUYỆT MẬT</span>
                            <span class="stamp-date">KHÔNG SAO CHÉP</span>
                        </div>
                    </div>
                    <div class="stamp-desc">Bảo mật / Tuyệt mật</div>
                </div>

                <div class="stamp-pick-item stamp-completed" onclick="insertStamp('completed')">
                    <div class="stamp-badge-preview completed">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">✓ HOÀN THÀNH</span>
                            <span class="stamp-date">ĐẠT YÊU CẦU</span>
                        </div>
                    </div>
                    <div class="stamp-desc">Nghiệm thu hoàn tất</div>
                </div>

                <div class="stamp-pick-item stamp-certified" onclick="insertStamp('certified')">
                    <div class="stamp-badge-preview certified">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">SAO Y BẢN CHÍNH</span>
                            <span class="stamp-date">VĂN THƯ ĐÃ DUYỆT</span>
                        </div>
                    </div>
                    <div class="stamp-desc">Chứng thực bản sao</div>
                </div>

                <div class="stamp-pick-item stamp-void" onclick="insertStamp('void')">
                    <div class="stamp-badge-preview void">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">✕ HỦY BỎ (VOID)</span>
                            <span class="stamp-date">HẾT HIỆU LỰC</span>
                        </div>
                    </div>
                    <div class="stamp-desc">Hủy bỏ / Vô hiệu</div>
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
                <h3 style="font-size: 1.25rem; font-weight: 800; color: #0F172A; margin: 12px 0 4px;">Xuất tệp PDF thành công!</h3>
                <p style="font-size: 0.82rem; color: #64748B; margin: 0;">Tài liệu đã được biên tập, nhúng vector chuẩn và sẵn sàng tải về</p>
            </div>

            <div class="export-meta-card">
                <div class="export-meta-row">
                    <span class="meta-lbl">Tên tệp:</span>
                    <strong class="meta-val" id="expDocName">Tai_lieu_bien_tap.pdf</strong>
                </div>
                <div class="export-meta-row">
                    <span class="meta-lbl">Tổng số trang:</span>
                    <strong class="meta-val" id="expDocPages">11 trang</strong>
                </div>
                <div class="export-meta-row">
                    <span class="meta-lbl">Trạng thái:</span>
                    <span class="status-pill success"><span class="status-dot"></span> Đã hoàn tất 100%</span>
                </div>
                <div class="export-meta-row">
                    <span class="meta-lbl">Định dạng xuất:</span>
                    <strong class="meta-val">PDF chuẩn ISO/IEC 32000</strong>
                </div>
            </div>

            <div class="export-actions">
                <a href="#" id="expDownloadBtn" class="btn-primary btn-lg" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Tải xuống tệp PDF hoàn chỉnh</span>
                </a>
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button type="button" class="btn-outline" style="flex: 1;" onclick="viewExportManifest(currentOutputId)">
                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                        <span>Xem bảng đối chiếu</span>
                    </button>
                    <button type="button" class="btn-secondary" style="flex: 1;" onclick="closeModal('exportSuccessModal')">
                        <span>Tiếp tục chỉnh sửa</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notifications Container -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="/assets/js/app.js?v=<?= time() ?>"></script>
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
