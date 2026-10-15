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

// Tải cấu hình
$config = require __DIR__ . '/../config/app.php';

// Giả lập tài khoản đang đăng nhập (để kiểm thử phân quyền)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Nguyễn Văn A';
    $_SESSION['user_role'] = 'Sinh viên';
}

// Router đơn giản phục vụ API hoặc trả về View
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (str_starts_with($requestUri, '/api/')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success',
        'message' => 'API PDF Manager đang sẵn sàng',
        'endpoint' => $requestUri,
        'user' => [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name']
        ]
    ]);
    exit;
}

// Render giao diện HTML chính thức (SPA giao diện hiện đại theo mockup)
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Manager - Sắp xếp, Ghép, Tách & Xử lý PDF</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M19 3H5C3.89543 3 3 3.89543 3 5V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V5C21 3.89543 20.1046 3 19 3Z" stroke="#2563EB" stroke-width="2"/><path d="M7 7H17M7 12H17M7 17H13" stroke="#2563EB" stroke-width="2" stroke-linecap="round"/></svg>
                </div>
                <div>
                    <h1 class="brand-title">PDF Manager <span class="badge">v1.0</span></h1>
                    <p class="brand-sub">Sắp xếp - Ghép - Tách - Xử lý PDF</p>
                </div>
            </div>

            <nav class="nav-menu">
                <a href="#home" class="nav-item active">
                    <span class="icon">🏠</span> Trang chủ
                </a>
                <a href="#documents" class="nav-item">
                    <span class="icon">📁</span> Quản lý tài liệu
                </a>
                <a href="#reorder" class="nav-item">
                    <span class="icon">📑</span> Sắp xếp trang
                </a>
                <a href="#merge" class="nav-item">
                    <span class="icon">🔗</span> Ghép PDF
                </a>
                <a href="#split" class="nav-item">
                    <span class="icon">✂️</span> Tách PDF
                </a>
                <a href="#editor" class="nav-item">
                    <span class="icon">🛠️</span> Xử lý tài liệu
                </a>
                <a href="#history" class="nav-item">
                    <span class="icon">🕒</span> Lịch sử xuất
                </a>
                <a href="#settings" class="nav-item">
                    <span class="icon">⚙️</span> Cài đặt
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="storage-info">
                    <div class="storage-label">
                        <span>Dung lượng sử dụng</span>
                        <span>125 MB / 1 GB</span>
                    </div>
                    <div class="progress-bar"><div class="progress-fill" style="width: 12.5%;"></div></div>
                </div>
                <div class="user-profile">
                    <div class="avatar">NV</div>
                    <div class="user-meta">
                        <div class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                        <div class="user-role"><?= htmlspecialchars($_SESSION['user_role']) ?></div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Header bar -->
            <header class="topbar">
                <div class="search-box">
                    <input type="text" placeholder="Tìm kiếm tài liệu, tệp...">
                </div>
                <div class="topbar-actions">
                    <button class="btn-icon">🔔 <span class="dot"></span></button>
                    <div class="user-chip">
                        <span><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                    </div>
                </div>
            </header>

            <!-- Hero Banner -->
            <section class="hero-banner">
                <div class="hero-text">
                    <h2>Xử lý PDF nhanh chóng, linh hoạt và dễ dàng</h2>
                    <p>Ghép, tách, sắp xếp, xoay và chỉnh sửa tài liệu PDF chỉ với vài thao tác đơn giản.</p>
                    <div class="feature-badges">
                        <span>🛡️ An toàn & Bảo mật</span>
                        <span>⚡ Xử lý trong vài giây</span>
                        <span>👁️ Giao diện trực quan</span>
                    </div>
                </div>
            </section>

            <!-- Quick Action Cards -->
            <section class="quick-actions-grid">
                <div class="action-card">
                    <div class="action-icon blue">🔗</div>
                    <div class="action-details">
                        <h3>Ghép PDF</h3>
                        <p>Kết hợp nhiều tệp thành một tài liệu</p>
                    </div>
                </div>
                <div class="action-card">
                    <div class="action-icon green">✂️</div>
                    <div class="action-details">
                        <h3>Tách PDF</h3>
                        <p>Tách tài liệu thành nhiều file theo trang/nhóm</p>
                    </div>
                </div>
                <div class="action-card">
                    <div class="action-icon purple">📑</div>
                    <div class="action-details">
                        <h3>Sắp xếp trang</h3>
                        <p>Đổi thứ tự, chọn/bỏ trang, xoay trang</p>
                    </div>
                </div>
                <div class="action-card">
                    <div class="action-icon orange">🛠️</div>
                    <div class="action-details">
                        <h3>Xử lý tài liệu</h3>
                        <p>Xem trước, chỉnh sửa kế hoạch và xuất file</p>
                    </div>
                </div>
            </section>

            <!-- Workspace: Documents & Test Dataset -->
            <section class="workspace-section">
                <div class="section-header">
                    <h3>Danh sách tài liệu (Bộ thử nghiệm 6 file PDF)</h3>
                    <button class="btn-primary" id="btnUpload">+ Tải lên tệp PDF</button>
                </div>

                <div class="document-cards-row">
                    <!-- Tài liệu 1 -->
                    <div class="doc-card">
                        <div class="doc-badge badge-success">Hoàn tất</div>
                        <div class="doc-thumbnail">📄</div>
                        <div class="doc-name">Tai_lieu_hoc_tap.pdf</div>
                        <div class="doc-info">8 trang • 2.1 MB</div>
                    </div>
                    <!-- Tài liệu 2 -->
                    <div class="doc-card">
                        <div class="doc-badge badge-success">Hoàn tất</div>
                        <div class="doc-thumbnail">📄</div>
                        <div class="doc-name">De_cuong_mon_hoc.pdf</div>
                        <div class="doc-info">24 trang • 4.8 MB</div>
                    </div>
                    <!-- Tài liệu 3 -->
                    <div class="doc-card">
                        <div class="doc-badge badge-success">Hoàn tất</div>
                        <div class="doc-thumbnail">📄</div>
                        <div class="doc-name">Bai_giang_Tin_hoc.pdf</div>
                        <div class="doc-info">18 trang • 3.1 MB</div>
                    </div>
                    <!-- Tài liệu 4 -->
                    <div class="doc-card">
                        <div class="doc-badge badge-info">Đã xử lý</div>
                        <div class="doc-thumbnail">📄</div>
                        <div class="doc-name">Ho_so_tot_nghiep.pdf</div>
                        <div class="doc-info">12 trang • 2.4 MB</div>
                    </div>
                    <!-- Tài liệu 5 (Tệp rỗng/lỗi) -->
                    <div class="doc-card card-error">
                        <div class="doc-badge badge-danger">Lỗi</div>
                        <div class="doc-thumbnail">⚠️</div>
                        <div class="doc-name">File_rong.pdf</div>
                        <div class="doc-info">0 trang • 0 KB</div>
                    </div>
                    <!-- Tài liệu 6 -->
                    <div class="doc-card">
                        <div class="doc-badge badge-success">Hoàn tất</div>
                        <div class="doc-thumbnail">📄</div>
                        <div class="doc-name">Tai_lieu_tham_khao.pdf</div>
                        <div class="doc-info">15 trang • 2.7 MB</div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="/assets/js/app.js"></script>
</body>
</html>
