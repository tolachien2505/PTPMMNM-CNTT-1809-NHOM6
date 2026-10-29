        <aside class="sidebar sidebar-client <?= $currentView === 'editor' ? 'collapsed' : '' ?>" id="appSidebarClient" <?= $isPortalAdmin ? 'style="display: none !important;"' : '' ?>>
            <div class="sidebar-header-row">
                <a href="/home" class="brand brand-logo-container" onclick="switchView('home', event); closeMobileSidebar();" title="PDF Online">
                    <img src="/img/logo_light.png" alt="PDF Online" class="brand-logo logo-light">
                    <img src="/img/logo_dark.png" alt="PDF Online" class="brand-logo logo-dark">
                    <img src="/img/favicon.png" alt="PDF Online" class="brand-logo-icon">
                </a>
                <button type="button" class="btn-sidebar-close" onclick="closeMobileSidebar()" title="Đóng danh mục">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <nav class="nav-menu">
                <a href="/home" class="nav-item <?= $currentView === 'home' ? 'active' : '' ?>" data-view="home" onclick="closeMobileSidebar();" title="Trang chủ">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </span>
                    <span>Trang chủ</span>
                </a>
                <a href="/documents" class="nav-item <?= $currentView === 'documents' ? 'active' : '' ?>" data-view="documents" onclick="closeMobileSidebar();" title="Quản lý tài liệu">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    </span>
                    <span>Quản lý tài liệu</span>
                </a>
                <a href="/editor" class="nav-item <?= $currentView === 'editor' ? 'active' : '' ?>" data-view="editor" onclick="closeMobileSidebar();" title="Chỉnh sửa PDF">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </span>
                    <span>Chỉnh sửa PDF</span>
                </a>
                <a href="/upgrade" class="nav-item <?= $currentView === 'upgrade' ? 'active' : '' ?>" data-view="upgrade" onclick="closeMobileSidebar();" title="Nâng Cấp Gói">
                    <span class="nav-icon">
                        <svg class="svg-icon" style="color: #F59E0B;" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    </span>
                    <span style="font-weight: 600; color: #D97706;">Nâng Cấp</span>
                </a>
                <a href="/history" class="nav-item <?= $currentView === 'history' ? 'active' : '' ?>" data-view="history" onclick="closeMobileSidebar();" title="Lịch sử xuất">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </span>
                    <span>Lịch sử xuất</span>
                </a>
                <a href="/settings" class="nav-item <?= $currentView === 'settings' ? 'active' : '' ?>" data-view="settings" onclick="closeMobileSidebar();" title="Cài đặt">
                    <span class="nav-icon">
                        <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                    </span>
                    <span>Cài đặt</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <?php if ($isLoggedIn): 
                    $isProUser = ($currentUser['plan'] ?? 'FREE') === 'PRO';
                    $storageRatioText = $isProUser ? "{$userStorageMb} MB/Không giới hạn" : "{$userStorageMb} MB / 100 MB";
                    $barPercent = $isProUser ? min(30, max(8, round(($userStorageMb / 2048) * 100, 1))) : min(100, max(4, round(($userStorageMb / 100) * 100, 1)));
                ?>
                <!-- Khối hiển thị đầy đủ khi Sidebar mở -->
                <div class="storage-info storage-info-full">
                    <div class="storage-head-row">
                        <span class="storage-title">
                            <svg class="svg-icon svg-icon-xs" style="color: var(--primary);" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                            Dung lượng lưu trữ
                        </span>
                        <?php if ($isProUser): ?>
                            <span class="badge-pro-pill">PRO</span>
                        <?php endif; ?>
                    </div>
                    <div class="storage-usage-row" id="storageUsageText"><?= $storageRatioText ?></div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="storageProgressBar" style="width: <?= $barPercent ?>%;"></div>
                    </div>
                    <div class="storage-actions-row">
                        <a href="/upgrade" class="storage-upgrade-link" onclick="switchView('upgrade', event); closeMobileSidebar();">
                            <?= $isProUser ? 'Đã lên PRO' : 'Nâng cấp ngay →' ?>
                        </a>
                    </div>
                </div>

                <!-- Khối hiển thị mini khi Sidebar thu nhỏ (Collapsed) -->
                <div class="storage-info-mini" title="Dung lượng: <?= $storageRatioText ?> (<?= $isProUser ? 'Gói PRO' : 'Gói Miễn phí' ?>)" onclick="switchView('upgrade', event)">
                    <div class="mini-storage-gauge">
                        <svg viewBox="0 0 36 36" class="circular-chart">
                            <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                            <path class="circle" stroke-dasharray="<?= $barPercent ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                        </svg>
                        <span class="mini-gauge-icon">
                            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                        </span>
                    </div>
                    <?php if ($isProUser): ?>
                    <span class="mini-pro-star" title="Tài khoản PRO">★</span>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <!-- Khách vãng lai -->
                <div class="storage-info storage-info-full guest-storage-box">
                    <div class="storage-head-row">
                        <span class="storage-title">Khách vãng lai</span>
                    </div>
                    <div class="storage-usage-row" style="color: var(--text-muted);">0 MB / 100 MB</div>
                    <div class="progress-bar"><div class="progress-fill" style="width: 0%;"></div></div>
                    <a href="javascript:void(0)" class="storage-upgrade-link" onclick="openAuthModal('login'); closeMobileSidebar();">Đăng nhập nhận 100 MB →</a>
                </div>
                <div class="storage-info-mini" title="Khách vãng lai: Đăng nhập để lưu trữ 100 MB" onclick="openAuthModal('login')">
                    <div class="mini-storage-gauge">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </aside>
