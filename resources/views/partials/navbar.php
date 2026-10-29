            <header class="topbar">
                <div class="topbar-left">
                    <button class="btn-mobile-menu" onclick="toggleMobileSidebar()" title="Mở danh mục">
                        <svg class="svg-icon" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                    <button class="btn-sidebar-toggle" id="btnSidebarToggle" onclick="toggleSidebarCompact()" title="Thu gọn / Mở rộng Sidebar">
                        <svg class="svg-icon" viewBox="0 0 24 24">
                            <line x1="3" y1="6" x2="21" y2="6"/>
                            <line x1="3" y1="12" x2="21" y2="12"/>
                            <line x1="3" y1="18" x2="21" y2="18"/>
                        </svg>
                    </button>
                    <div class="search-box">
                        <span class="search-icon">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </span>
                        <input type="text" id="globalSearch" placeholder="Tìm kiếm tài liệu...">
                    </div>
                </div>
                <div class="topbar-actions">
                    <!-- Nút chuyển chế độ Giao diện Sáng / Tối cạnh nút thông báo -->
                    <button type="button" class="btn-icon btn-theme-toggle" id="btnThemeToggle" onclick="toggleTheme()" title="Chuyển chế độ Giao diện Sáng / Tối">
                        <svg class="svg-icon theme-sun-icon" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="5"/>
                            <line x1="12" y1="1" x2="12" y2="3"/>
                            <line x1="12" y1="21" x2="12" y2="23"/>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                            <line x1="1" y1="12" x2="3" y2="12"/>
                            <line x1="21" y1="12" x2="23" y2="12"/>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                        </svg>
                        <svg class="svg-icon theme-moon-icon" viewBox="0 0 24 24">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                        </svg>
                    </button>
                    <button class="btn-icon" title="Thông báo">
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

                            <?php if ($isAdmin): ?>
                            <a href="/admin" class="user-dd-item user-dd-admin-link btn-dd-switch-admin" id="btnDdSwitchAdmin" style="<?= $isPortalAdmin ? 'display: none !important;' : '' ?>" onclick="switchToAdminPortal(event); closeUserDropdown();">
                                <span class="user-dd-admin-icon">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><polygon points="12 15 13.5 17 16 17 14 18.5 15 21 12 19.5 9 21 10 18.5 8 17 10.5 17 12 15"/></svg>
                                </span>
                                <span class="user-dd-admin-title">Chuyển Trang ADMIN</span>
                                <span class="admin-badge-mini">ADMIN</span>
                            </a>
                            <a href="/home" class="user-dd-item user-dd-admin-link btn-dd-switch-client" id="btnDdSwitchClient" style="<?= !$isPortalAdmin ? 'display: none !important;' : '' ?>" onclick="switchToClientPortal(event); closeUserDropdown();">
                                <span class="user-dd-admin-icon">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                </span>
                                <span class="user-dd-admin-title">Về Website Client</span>
                                <span class="admin-badge-mini admin-badge-client-mode">CLIENT</span>
                            </a>
                            <div class="user-dd-divider"></div>
                            <?php endif; ?>

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
