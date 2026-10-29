<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Online - Sắp xếp, Ghép, Tách & Xử lý PDF</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/img/favicon.png">
    <link rel="shortcut icon" type="image/png" href="/img/favicon.png">
    <link rel="apple-touch-icon" href="/img/favicon.png">
    <!-- Immediate Theme Application (Zero Flash of Unstyled Theme) -->
    <script>
        (function() {
            var savedTheme = localStorage.getItem('app_theme') || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= time() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Mozilla PDF.js & PDF-Lib Engine (Local with CDN fallback) -->
    <script src="/assets/vendor/pdfjs/pdf.min.js"></script>
    <script src="/assets/vendor/pdflib/pdf-lib.min.js"></script>
    <script src="/assets/vendor/pdflib/fontkit.umd.min.js"></script>
    <script>
        window.APP_IS_LOGGED_IN = <?= json_encode($isLoggedIn) ?>;
        window.APP_IS_ADMIN = <?= json_encode($isAdmin) ?>;
        window.APP_CURRENT_USER = <?= json_encode($currentUser) ?>;
        window.APP_IS_PORTAL_ADMIN = <?= json_encode($isPortalAdmin) ?>;
        window.APP_ADMIN_CURRENT_VIEW = <?= json_encode($adminCurrentView) ?>;
        window.APP_MAINTENANCE_ACTIVE = <?= json_encode($isMaintenanceActive ?? false) ?>;
        window.APP_AUTO_OPEN_ADMIN_LOGIN = <?= json_encode($autoOpenAdminLogin ?? false) ?>;
    </script>
</head>
<body>
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileSidebar()"></div>

    <div class="app-layout <?= $isPortalAdmin ? 'portal-admin' : 'portal-client' ?> <?= $currentView === 'editor' ? 'sidebar-compact' : '' ?> <?= ($isMaintenanceActive && !$isAdmin) ? 'app-maintenance-mode' : '' ?>" id="appLayout">
        <!-- 1. SIDEBAR CLIENT (WEBSITE NGƯỜI DÙNG) -->
        <?php if (!$isMaintenanceActive || $isAdmin): ?>
            <?php include dirname(__DIR__) . '/partials/sidebar_client.php'; ?>
        <?php endif; ?>

        <!-- 2. SIDEBAR ADMIN (QUẢN TRỊ VIÊN HỆ THỐNG) -->
        <?php if ($isAdmin): ?>
            <?php include dirname(__DIR__) . '/partials/sidebar_admin.php'; ?>
        <?php endif; ?>

        <!-- 3. KHÔNG GIAN LÀM VIỆC CHÍNH (MAIN WORKSPACE) -->
        <main class="main-content <?= ($isMaintenanceActive && !$isAdmin) ? 'main-maintenance-active' : '' ?>">
            <?php if ($isMaintenanceActive && !$isAdmin): ?>
                <!-- GIAO DIỆN BẢO TRÌ HỆ THỐNG (CLIENT MAINTENANCE VIEW) -->
                <?php include dirname(__DIR__) . '/pages/client_maintenance.php'; ?>
            <?php else: ?>
                <!-- TOP NAVBAR -->
                <?php include dirname(__DIR__) . '/partials/navbar.php'; ?>

                <!-- CLIENT PAGES -->
                <?php include dirname(__DIR__) . '/pages/home.php'; ?>
                <?php include dirname(__DIR__) . '/pages/documents.php'; ?>
                <?php include dirname(__DIR__) . '/pages/editor.php'; ?>
                <?php include dirname(__DIR__) . '/pages/upgrade.php'; ?>
                <?php include dirname(__DIR__) . '/pages/history.php'; ?>
                <?php include dirname(__DIR__) . '/pages/settings.php'; ?>

                <!-- ADMIN PORTAL (ONLY FOR ADMIN) -->
                <?php if ($isAdmin): ?>
                <div id="view-admin" class="workspace-view <?= $currentView === 'admin' ? 'active' : '' ?>">
                    <?php include dirname(__DIR__) . '/admin/dashboard.php'; ?>
                    <?php include dirname(__DIR__) . '/admin/users.php'; ?>
                    <?php include dirname(__DIR__) . '/admin/documents.php'; ?>
                    <?php include dirname(__DIR__) . '/admin/exports.php'; ?>
                    <?php include dirname(__DIR__) . '/admin/payments.php'; ?>
                    <?php include dirname(__DIR__) . '/admin/plans.php'; ?>
                    <?php include dirname(__DIR__) . '/admin/maintenance.php'; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION FOR CLIENT -->
    <?php if (!$isMaintenanceActive || $isAdmin): ?>
    <nav class="mobile-bottom-nav mobile-bottom-nav-client" id="mobileBottomNavClient" <?= $isPortalAdmin ? 'style="display: none !important;"' : '' ?>>
        <a href="/home" class="mobile-nav-item <?= $currentView === 'home' ? 'active' : '' ?>" data-view="home" onclick="switchView('home', event)">
            <svg class="svg-icon" viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Trang chủ</span>
        </a>
        <a href="/documents" class="mobile-nav-item <?= $currentView === 'documents' ? 'active' : '' ?>" data-view="documents" onclick="switchView('documents', event)">
            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <span>Tài liệu</span>
        </a>
        <div class="mobile-nav-fab-wrap">
            <button class="mobile-nav-fab" onclick="switchView('editor', event)" title="Mở Trình Biên Tập PDF">
                <svg class="svg-icon" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
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
    <?php endif; ?>

    <!-- MOBILE BOTTOM NAVIGATION FOR ADMIN (KHI Ở PORTAL ADMIN) -->
    <?php if ($isAdmin): ?>
    <nav class="mobile-bottom-nav mobile-bottom-nav-admin" id="mobileBottomNavAdmin" <?= !$isPortalAdmin ? 'style="display: none !important;"' : '' ?>>
        <a href="/admin" class="mobile-nav-item <?= $adminCurrentView === 'dashboard' ? 'active' : '' ?>" data-admin-nav="dashboard" onclick="switchAdminPage('dashboard', event)">
            <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <span>Tổng quan</span>
        </a>
        <a href="/admin/users" class="mobile-nav-item <?= $adminCurrentView === 'users' ? 'active' : '' ?>" data-admin-nav="users" onclick="switchAdminPage('users', event)">
            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span>Người dùng</span>
        </a>
        <a href="/admin/documents" class="mobile-nav-item <?= $adminCurrentView === 'documents' ? 'active' : '' ?>" data-admin-nav="documents" onclick="switchAdminPage('documents', event)">
            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <span>Tài liệu</span>
        </a>
        <a href="/admin/payments" class="mobile-nav-item <?= $adminCurrentView === 'payments' ? 'active' : '' ?>" data-admin-nav="payments" onclick="switchAdminPage('payments', event)">
            <svg class="svg-icon" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/><path d="M12 15h2"/></svg>
            <span>Doanh thu</span>
        </a>
        <a href="/home" class="mobile-nav-item mobile-nav-back-client" onclick="switchToClientPortal(event)" title="Về Website Client">
            <svg class="svg-icon" viewBox="0 0 24 24" style="color: #3B82F6;"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Về Client</span>
        </a>
    </nav>
    <?php endif; ?>

    <!-- MODALS HỆ THỐNG -->
    <?php include dirname(__DIR__) . '/modals/upload_modal.php'; ?>
    <?php include dirname(__DIR__) . '/modals/manifest_modal.php'; ?>
    <?php include dirname(__DIR__) . '/modals/auth_modal.php'; ?>
    <?php include dirname(__DIR__) . '/modals/plan_modal.php'; ?>
    <?php include dirname(__DIR__) . '/modals/account_modal.php'; ?>
    <?php include dirname(__DIR__) . '/modals/page_manager_modal.php'; ?>
    <?php include dirname(__DIR__) . '/modals/signature_modal.php'; ?>
    <?php include dirname(__DIR__) . '/modals/stamp_modal.php'; ?>
    <?php include dirname(__DIR__) . '/modals/export_modal.php'; ?>
    <?php include dirname(__DIR__) . '/modals/bank_settings_modal.php'; ?>
    <?php include dirname(__DIR__) . '/modals/edit_invoice_modal.php'; ?>
    <?php include dirname(__DIR__) . '/modals/vietqr_modal.php'; ?>
    <?php include dirname(__DIR__) . '/modals/edit_user_modal.php'; ?>

    <!-- Toast Notifications Container -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="/assets/js/app.js?v=<?= time() ?>"></script>
    <script>
        function toggleMobileSidebar() {
            const isPortalAdmin = document.getElementById('appLayout')?.classList.contains('portal-admin');
            const sidebar = isPortalAdmin 
                ? document.getElementById('appSidebarAdmin') 
                : document.getElementById('appSidebarClient');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar) sidebar.classList.toggle('open');
            if (backdrop) backdrop.classList.toggle('active');
        }

        function closeMobileSidebar() {
            const sbAdmin = document.getElementById('appSidebarAdmin');
            const sbClient = document.getElementById('appSidebarClient');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sbAdmin) sbAdmin.classList.remove('open');
            if (sbClient) sbClient.classList.remove('open');
            if (backdrop) backdrop.classList.remove('active');
        }

        // Tự động mở form Đăng nhập Quản trị viên khi truy cập các route /admin mà chưa đăng nhập
        if (window.APP_AUTO_OPEN_ADMIN_LOGIN) {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    if (typeof openModal === 'function') {
                        openModal('authModal');
                        var title = document.getElementById('authModalTitle');
                        if (title) title.textContent = 'Đăng nhập Quản trị viên';
                    }
                }, 150);
            });
        }
    </script>
</body>
</html>
