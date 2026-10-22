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

$dbPath = __DIR__ . '/../database/database.sqlite';
$pdo = null;
try {
    if (file_exists($dbPath)) {
        $pdo = new PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
} catch (Exception $e) {
    // Fallback if db error
}

$authController = new AuthController($pdo);

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Nguyễn Văn A';
    $_SESSION['user_role'] = 'Sinh viên';
    $_SESSION['user_email'] = 'nguyenvana@dnu.edu.vn';
    $_SESSION['username'] = 'sinhvien_a';
}

$validator = new SecurityValidator();
$storage = new StorageService();
$processor = new PdfProcessorService($validator, $storage, $pdo);

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

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

    // --- Authentication Endpoints ---
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

    if ($requestUri === '/api/auth/logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $authController->logout();
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
            $stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY id DESC");
            $stmt->execute([$_SESSION['user_id']]);
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
                $stmt = $pdo->prepare("
                    INSERT INTO documents (user_id, original_name, stored_filename, file_size, total_pages, status)
                    VALUES (?, ?, ?, ?, ?, 'valid')
                ");
                $stmt->execute([$_SESSION['user_id'], $origName, $storedName, $file['size'], $pageCount]);
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
            $result = $processor->mergePages($_SESSION['user_id'], 1, $plan, $title);
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
            $result = $processor->splitGroups($_SESSION['user_id'], $groups, $sourceDoc);
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
            $stmt = $pdo->prepare("SELECT * FROM outputs WHERE user_id = ? ORDER BY id DESC LIMIT 20");
            $stmt->execute([$_SESSION['user_id']]);
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
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileSidebar()"></div>

    <div class="app-layout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar" id="appSidebar">
            <div class="brand">
                <div class="brand-icon">
                    <svg class="svg-icon svg-icon-lg" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="9" y1="13" x2="15" y2="13"/>
                        <line x1="9" y1="17" x2="13" y2="17"/>
                    </svg>
                </div>
                <div>
                    <h1 class="brand-title">PDF Manager <span class="badge">v1.0</span></h1>
                    <p class="brand-sub">Sắp xếp - Ghép - Tách - Xử lý PDF</p>
                </div>
            </div>

            <nav class="nav-menu">
                <a href="#home" class="nav-item active" data-view="home">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </span>
                    <span>Trang chủ</span>
                </a>
                <a href="#documents" class="nav-item" data-view="documents">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    </span>
                    <span>Quản lý tài liệu</span>
                </a>
                <a href="#reorder" class="nav-item" data-view="reorder">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                    </span>
                    <span>Sắp xếp trang</span>
                </a>
                <a href="#merge" class="nav-item" data-view="merge">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    </span>
                    <span>Ghép PDF</span>
                </a>
                <a href="#split" class="nav-item" data-view="split">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/></svg>
                    </span>
                    <span>Tách PDF</span>
                </a>
                <a href="#editor" class="nav-item" data-view="reorder">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    </span>
                    <span>Xử lý tài liệu</span>
                </a>
                <a href="#history" class="nav-item" data-view="history">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </span>
                    <span>Lịch sử xuất</span>
                </a>
                <a href="#settings" class="nav-item" data-view="settings">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </span>
                    <span>Cài đặt</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="storage-info">
                    <div class="storage-label">
                        <span>Dung lượng sử dụng</span>
                        <span>125 MB / 1 GB</span>
                    </div>
                    <div class="progress-bar"><div class="progress-fill" style="width: 12.5%;"></div></div>
                    <a href="javascript:void(0)" class="storage-upgrade" onclick="showToast('Tính năng nâng cấp dung lượng lưu trữ', 'info')">Nâng cấp</a>
                </div>
                <div class="user-profile">
                    <div class="user-left">
                        <div class="avatar">NV</div>
                        <div class="user-meta">
                            <div class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                            <div class="user-role"><?= htmlspecialchars($_SESSION['user_role']) ?></div>
                        </div>
                    </div>
                    <svg class="svg-icon svg-icon-sm" style="color: #94A3B8;" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
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
                    
                    <div class="user-dropdown-wrapper">
                        <div class="user-chip" id="userChipBtn" onclick="toggleUserDropdown(event)" title="Quản lý tài khoản">
                            <div class="avatar-sm" id="userChipAvatar"><?= mb_substr($_SESSION['user_name'] ?? 'NV', 0, 2) ?></div>
                            <span id="userChipName"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                            <svg class="svg-icon svg-icon-sm" style="color: #94A3B8;" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>

                        <!-- Dropdown Menu Người dùng -->
                        <div class="user-dropdown-menu" id="userDropdownMenu">
                            <div class="user-dd-header">
                                <div class="user-dd-avatar" id="ddAvatar"><?= mb_substr($_SESSION['user_name'] ?? 'NV', 0, 2) ?></div>
                                <div class="user-dd-info">
                                    <div class="user-dd-name" id="ddName"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                                    <div class="user-dd-role"><span class="badge" id="ddRole"><?= htmlspecialchars($_SESSION['user_role']) ?></span></div>
                                    <div class="user-dd-email" id="ddEmail"><?= htmlspecialchars($_SESSION['user_email'] ?? 'nguyenvana@dnu.edu.vn') ?></div>
                                </div>
                            </div>
                            <div class="user-dd-divider"></div>
                            <div class="user-dd-section-title">Chuyển tài khoản thử nghiệm:</div>
                            <button type="button" class="user-dd-item" onclick="switchDemoUser(1)">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <div>
                                    <div style="font-weight: 600;">Sinh viên A (Nguyễn Văn A)</div>
                                    <small style="color: var(--text-muted);">student • 6 tệp mẫu</small>
                                </div>
                            </button>
                            <button type="button" class="user-dd-item" onclick="switchDemoUser(2)">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                <div>
                                    <div style="font-weight: 600;">Giảng viên B (Trần Thị B)</div>
                                    <small style="color: var(--text-muted);">lecturer • Dữ liệu cô lập</small>
                                </div>
                            </button>
                            <div class="user-dd-divider"></div>
                            <button type="button" class="user-dd-item" onclick="openAuthModal('login')">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                                <span>Đăng nhập tài khoản khác</span>
                            </button>
                            <button type="button" class="user-dd-item" onclick="openAuthModal('register')">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                                <span>Đăng ký tài khoản mới</span>
                            </button>
                            <div class="user-dd-divider"></div>
                            <button type="button" class="user-dd-item text-danger" onclick="logoutUser()">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                <span>Đăng xuất</span>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- VIEW 1: TRANG CHỦ DASHBOARD -->
            <div id="view-home" class="workspace-view active">
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
                        <!-- Card 1: Ghép PDF -->
                        <a href="#merge" class="action-card" onclick="switchView('merge')">
                            <div class="action-main">
                                <div class="action-icon-box blue">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                </div>
                                <div class="action-details">
                                    <h3>Ghép PDF</h3>
                                    <p>Kết hợp nhiều tệp thành một tài liệu</p>
                                </div>
                            </div>
                            <span class="action-arrow">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        </a>

                        <!-- Card 2: Tách PDF -->
                        <a href="#split" class="action-card" onclick="switchView('split')">
                            <div class="action-main">
                                <div class="action-icon-box green">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/></svg>
                                </div>
                                <div class="action-details">
                                    <h3>Tách PDF</h3>
                                    <p>Tách tài liệu thành nhiều file theo trang/nhóm</p>
                                </div>
                            </div>
                            <span class="action-arrow">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        </a>

                        <!-- Card 3: Sắp xếp trang -->
                        <a href="#reorder" class="action-card" onclick="switchView('reorder')">
                            <div class="action-main">
                                <div class="action-icon-box purple">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                                </div>
                                <div class="action-details">
                                    <h3>Sắp xếp trang</h3>
                                    <p>Đổi thứ tự, chọn/bỏ trang, xoay trang</p>
                                </div>
                            </div>
                            <span class="action-arrow">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        </a>

                        <!-- Card 4: Xử lý tài liệu -->
                        <a href="#editor" class="action-card" onclick="switchView('reorder')">
                            <div class="action-main">
                                <div class="action-icon-box orange">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                                </div>
                                <div class="action-details">
                                    <h3>Xử lý tài liệu</h3>
                                    <p>Xem trước, chỉnh sửa kế hoạch và xuất file</p>
                                </div>
                            </div>
                            <span class="action-arrow">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        </a>
                    </section>
                </div>

                <!-- MIDDLE SECTION: Documents Workspace (Left) + Quick Stats & Guide (Right) -->
                <div class="dashboard-grid">
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

            <!-- VIEW 2: TRÌNH BIÊN TẬP SẮP XẾP TRANG -->
            <div id="view-reorder" class="workspace-view">
                <div class="editor-toolbar">
                    <div>
                        <h2 style="font-size: 1.25rem; font-weight: 700;">Trình biên tập sắp xếp trang</h2>
                        <p style="font-size: 0.8rem; color: var(--text-muted);">Kéo thả để đổi thứ tự trang, xoay góc (90°, 180°, 270°) và loại bỏ trang thừa</p>
                    </div>
                    <div class="editor-controls">
                        <button class="btn-outline" onclick="editorState.undo()" title="Hoàn tác thao tác vừa làm">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                            <span>Hoàn tác (Undo)</span>
                        </button>
                        <button class="btn-outline" onclick="editorState.resetPlan()">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10"/></svg>
                            <span>Đặt lại A2, B1, A1</span>
                        </button>
                        <button class="btn-outline" onclick="editorState.saveDraft()">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            <span>Lưu kế hoạch</span>
                        </button>
                        <button class="btn-primary" onclick="editorState.exportMerge()">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            <span>Xuất tệp PDF</span>
                        </button>
                    </div>
                </div>

                <!-- Interactive Page Cards Grid -->
                <div class="page-grid" id="pagesContainer"></div>
            </div>

            <!-- VIEW 3: GHÉP PDF -->
            <div id="view-merge" class="workspace-view">
                <div class="table-section">
                    <div class="table-header">
                        <div>
                            <h2 style="font-size: 1.25rem; font-weight: 700;">Công cụ Ghép PDF</h2>
                            <p style="font-size: 0.8rem; color: var(--text-muted);">Kết hợp các trang từ nhiều tài liệu thành một bản hồ sơ hoàn chỉnh duy nhất</p>
                        </div>
                        <button class="btn-primary" onclick="switchView('reorder')">Mở Trình biên tập trực quan →</button>
                    </div>

                    <div style="background: #F8FAFC; padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border); margin-bottom: 20px;">
                        <h4 style="margin-bottom: 12px; font-weight: 700;">Kịch bản thử nghiệm chuẩn: Ghép A2 + B1 + A1 (Xoay 90°)</h4>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">
                            Hệ thống sẽ lấy trang 2 của <strong>Tai_lieu_hoc_tap.pdf</strong> (nhãn A2), trang 1 của <strong>De_cuong_mon_hoc.pdf</strong> (nhãn B1), và trang 1 của <strong>Tai_lieu_hoc_tap.pdf</strong> (nhãn A1).
                        </p>
                        <div class="merge-action-bar">
                            <input type="text" id="mergeOutputName" value="Ho_so_tong_hop_A2_B1_A1.pdf" class="form-input-responsive">
                            <button class="btn-primary" onclick="executeStandardMerge()">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                <span>Bắt đầu Ghép PDF ngay</span>
                            </button>
                        </div>
                    </div>

                    <div id="mergeResultBox" style="display: none; background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: var(--radius-md); padding: 18px;">
                        <h4 style="color: #065F46; margin-bottom: 8px;">✓ Ghép tài liệu thành công!</h4>
                        <p id="mergeResultText" style="font-size: 0.85rem; color: #047857; margin-bottom: 12px;"></p>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <a id="btnDownloadMerged" href="#" class="btn-primary" target="_blank">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                <span>Tải xuống tệp PDF kết quả</span>
                            </a>
                            <button class="btn-outline" onclick="openManifestModal(currentOutputId)">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <span>Xem bảng đối chiếu nguồn (Manifest)</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VIEW 4: TÁCH PDF -->
            <div id="view-split" class="workspace-view">
                <div class="table-section">
                    <div class="table-header">
                        <div>
                            <h2 style="font-size: 1.25rem; font-weight: 700;">Công cụ Tách PDF</h2>
                            <p style="font-size: 0.8rem; color: var(--text-muted);">Tách một tài liệu thành nhiều tệp PDF độc lập theo nhóm trang</p>
                        </div>
                    </div>

                    <div style="background: #F8FAFC; padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border); margin-bottom: 20px;">
                        <h4 style="margin-bottom: 12px; font-weight: 700;">Kịch bản thử nghiệm: Tách Tai_lieu_hoc_tap.pdf (8 trang)</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px;">
                            <div style="background: white; padding: 14px; border-radius: 8px; border: 1px solid var(--border);">
                                <strong>Nhóm 1 (Trang 1, 2, 3)</strong>
                                <p style="font-size: 0.8rem; color: var(--text-muted);">Xuất tệp riêng: Nhom_1.pdf</p>
                            </div>
                            <div style="background: white; padding: 14px; border-radius: 8px; border: 1px solid var(--border);">
                                <strong>Nhóm 2 (Trang 4, 5)</strong>
                                <p style="font-size: 0.8rem; color: var(--text-muted);">Xuất tệp riêng: Nhom_2.pdf</p>
                            </div>
                        </div>
                        <button class="btn-primary" onclick="executeStandardSplit()">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/></svg>
                            <span>Thực hiện Tách nhóm trang</span>
                        </button>
                    </div>

                    <div id="splitResultBox" style="display: none; background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: var(--radius-md); padding: 18px;">
                        <h4 style="color: #065F46; margin-bottom: 10px;">✓ Tách nhóm trang thành công!</h4>
                        <div id="splitLinksList" style="display: flex; flex-direction: column; gap: 8px;"></div>
                    </div>
                </div>
            </div>

            <!-- VIEW 5: LỊCH SỬ XUẤT -->
            <div id="view-history" class="workspace-view">
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
        </main>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION (Khớp bản thiết kế Mobile) -->
    <nav class="mobile-bottom-nav">
        <a href="#home" class="mobile-nav-item active" onclick="switchView('home')">
            <svg class="svg-icon" viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Trang chủ</span>
        </a>
        <a href="#documents" class="mobile-nav-item" onclick="switchView('documents')">
            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <span>Tài liệu</span>
        </a>
        <div class="mobile-nav-item center-action" onclick="openUploadModal()">
            <div class="mobile-plus-circle">
                <svg class="svg-icon svg-icon-lg" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </div>
        </div>
        <a href="#history" class="mobile-nav-item" onclick="switchView('history')">
            <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span>Lịch sử</span>
        </a>
        <a href="#settings" class="mobile-nav-item" onclick="switchView('settings')">
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
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <label class="form-label" style="margin: 0;">Mật khẩu</label>
                        <span style="font-size: 0.74rem; color: var(--primary); cursor: pointer;" onclick="showToast('Mật khẩu mặc định hệ thống: 123456', 'info')">Gợi ý mật khẩu?</span>
                    </div>
                    <div class="input-with-icon">
                        <svg class="svg-icon svg-icon-sm input-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="loginPassword" required placeholder="Nhập mật khẩu..." class="auth-input">
                    </div>
                </div>

                <!-- Tài khoản kiểm thử nhanh -->
                <div class="demo-accounts-box">
                    <div class="demo-acc-title">🎯 Tài khoản mẫu chấm bài & kiểm thử (Bấm để điền):</div>
                    <div class="demo-acc-chips">
                        <button type="button" class="demo-chip" onclick="fillDemoLogin('sinhvien_a', '123456')">
                            <span>👨‍🎓 Sinh viên: <strong>sinhvien_a</strong> (123456)</span>
                        </button>
                        <button type="button" class="demo-chip" onclick="fillDemoLogin('giangvien_b', '123456')">
                            <span>🎓 Giảng viên: <strong>giangvien_b</strong> (123456)</span>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary auth-submit-btn" id="btnLoginSubmit">
                    <span>Đăng nhập hệ thống</span>
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
                        <input type="text" id="regFullName" required placeholder="Ví dụ: Nguyễn Văn C..." class="auth-input">
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Tên đăng nhập</label>
                        <input type="text" id="regUsername" required placeholder="vd: sinhvien_c" class="auth-input" style="padding-left: 12px;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Vai trò</label>
                        <select id="regRole" class="auth-input" style="padding-left: 10px;">
                            <option value="student">👨‍🎓 Sinh viên</option>
                            <option value="lecturer">🎓 Giảng viên</option>
                            <option value="admin">🛡️ Quản trị viên</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Địa chỉ Email</label>
                    <div class="input-with-icon">
                        <svg class="svg-icon svg-icon-sm input-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <input type="email" id="regEmail" required placeholder="vd: vanc@dnu.edu.vn" class="auth-input">
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

    <!-- Toast Notifications Container -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="/assets/js/app.js"></script>
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
