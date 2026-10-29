        <aside class="sidebar sidebar-admin" id="appSidebarAdmin" <?= !$isPortalAdmin ? 'style="display: none !important;"' : '' ?>>
            <div class="sidebar-header-row">
                <div class="brand brand-logo-container brand-admin-container" title="PDF Online - Trang Quản Trị">
                    <a href="/admin" class="brand-logo-link" onclick="switchAdminPage('dashboard', event); closeMobileSidebar();">
                        <img src="/img/logo_light.png" alt="PDF Online" class="brand-logo logo-light">
                        <img src="/img/logo_dark.png" alt="PDF Online" class="brand-logo logo-dark">
                        <img src="/img/favicon.png" alt="PDF Online" class="brand-logo-icon">
                    </a>
                    <span class="badge-admin-portal">ADMIN</span>
                </div>
                <button type="button" class="btn-sidebar-close" onclick="closeMobileSidebar()" title="Đóng danh mục">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <nav class="nav-menu admin-nav-menu">
                <a href="/admin" class="nav-item <?= $adminCurrentView === 'dashboard' ? 'active' : '' ?>" data-admin-view="dashboard" onclick="switchAdminPage('dashboard', event); closeMobileSidebar();" title="Tổng quan hệ thống">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    </span>
                    <span>Tổng quan</span>
                </a>
                <a href="/admin/users" class="nav-item <?= $adminCurrentView === 'users' ? 'active' : '' ?>" data-admin-view="users" onclick="switchAdminPage('users', event); closeMobileSidebar();" title="Người dùng">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                    <span>Người dùng</span>
                    <span class="admin-sidebar-count" id="adminSidebarCountUsers"><?= count($adminUsersList) ?></span>
                </a>
                <a href="/admin/documents" class="nav-item <?= $adminCurrentView === 'documents' ? 'active' : '' ?>" data-admin-view="documents" onclick="switchAdminPage('documents', event); closeMobileSidebar();" title="Quản lý Toàn bộ Tài liệu">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </span>
                    <span>Toàn bộ tài liệu</span>
                    <span class="admin-sidebar-count" id="adminSidebarCountDocs"><?= count($adminDocsList) ?></span>
                </a>
                <a href="/admin/exports" class="nav-item <?= $adminCurrentView === 'exports' ? 'active' : '' ?>" data-admin-view="exports" onclick="switchAdminPage('exports', event); closeMobileSidebar();" title="Lịch sử & Tệp xuất">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </span>
                    <span>Lịch sử & Tệp xuất</span>
                    <span class="admin-sidebar-count" id="adminSidebarCountExports"><?= count($adminOutputsList) ?></span>
                </a>
                <a href="/admin/payments" class="nav-item <?= $adminCurrentView === 'payments' ? 'active' : '' ?>" data-admin-view="payments" onclick="switchAdminPage('payments', event); closeMobileSidebar();" title="Quản lý Thanh toán & Doanh thu">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/><path d="M12 15h2"/></svg>
                    </span>
                    <span>Thanh toán</span>
                    <span class="admin-sidebar-count" id="adminSidebarCountPayments"><?= $adminStats['total_invoices'] ?? 0 ?></span>
                </a>
                <a href="/admin/plans" class="nav-item <?= $adminCurrentView === 'plans' ? 'active' : '' ?>" data-admin-view="plans" onclick="switchAdminPage('plans', event); closeMobileSidebar();" title="Quản lý Cấp bậc Gói dịch vụ">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M20 7h-7L10 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/></svg>
                    </span>
                    <span>Gói dịch vụ</span>
                    <span class="admin-sidebar-count" id="adminSidebarCountPlans">2</span>
                </a>
                <a href="/admin/maintenance" class="nav-item <?= $adminCurrentView === 'maintenance' ? 'active' : '' ?>" data-admin-view="maintenance" onclick="switchAdminPage('maintenance', event); closeMobileSidebar();" title="Cấu hình & Bảo trì Server">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </span>
                    <span>Cấu hình & Bảo trì</span>
                </a>
            </nav>

            <div class="sidebar-footer admin-sidebar-footer">
                <a href="/home" class="btn-sidebar-back-client" onclick="switchToClientPortal(event); closeMobileSidebar();" title="Quay về giao diện Website Người dùng">
                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    <span>Về Website Client</span>
                </a>
            </div>
        </aside>
