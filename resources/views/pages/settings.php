            <!-- VIEW 6: CÀI ĐẶT HỆ THỐNG & TÙY CHỌN XỬ LÝ -->
            <div id="view-settings" class="workspace-view <?= $currentView === 'settings' ? 'active' : '' ?>">
                <div class="settings-page-wrapper">
                    <!-- HEADER SECTION -->
                    <div class="settings-header-card">
                        <div class="settings-header-main">
                            <div class="settings-title-wrap">
                                <div class="settings-title-icon">
                                    <svg class="svg-icon" viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="3"/>
                                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="settings-title">Cài đặt hệ thống & Tài khoản</h2>
                                    <p class="settings-subtitle">Quản trị thông tin cá nhân, cấu hình bộ xử lý PDF, gói dịch vụ đám mây và giao diện ứng dụng.</p>
                                </div>
                            </div>

                            <div class="settings-header-actions">
                                <button type="button" class="btn-reset-settings" onclick="resetSettingsToDefault()" title="Khôi phục các tùy chọn xử lý về mặc định">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10"/></svg>
                                    <span>Mặc định</span>
                                </button>
                                <button type="button" class="btn-save-all-settings" onclick="saveAllSettings()" title="Lưu lại toàn bộ tùy chọn cài đặt">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                    <span>Lưu thiết lập</span>
                                </button>
                            </div>
                        </div>

                        <!-- NAVIGATION TABS -->
                        <div class="settings-nav-tabs" id="settingsNavTabs">
                            <button type="button" class="settings-tab-btn active" data-tab="profile" onclick="switchSettingsTab('profile')">
                                <svg class="svg-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <span>Hồ sơ & Tài khoản</span>
                            </button>
                            <button type="button" class="settings-tab-btn" data-tab="pdf" onclick="switchSettingsTab('pdf')">
                                <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                <span>Xử lý & Xuất PDF</span>
                            </button>
                            <button type="button" class="settings-tab-btn" data-tab="plan" onclick="switchSettingsTab('plan')">
                                <svg class="svg-icon" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                <span>Gói & Dung lượng</span>
                            </button>
                            <button type="button" class="settings-tab-btn" data-tab="security" onclick="switchSettingsTab('security')">
                                <svg class="svg-icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                <span>Bảo mật & Dữ liệu</span>
                            </button>
                            <button type="button" class="settings-tab-btn" data-tab="appearance" onclick="switchSettingsTab('appearance')">
                                <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                                <span>Giao diện & Hiển thị</span>
                            </button>
                        </div>
                    </div>

                    <!-- TAB CONTENT PANELS CONTAINER -->
                    <div class="settings-body-container">

                        <!-- TAB 1: HỒ SƠ & TÀI KHOẢN -->
                        <div class="settings-tab-pane active" id="pane-profile">
                            <div class="settings-cards-grid">
                                <!-- Card 1.1: Thông tin cá nhân -->
                                <div class="settings-card">
                                    <div class="settings-card-header">
                                        <div class="card-header-icon icon-blue">
                                            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="settings-card-title">Thông tin hồ sơ cá nhân</h3>
                                            <p class="settings-card-desc">Cập nhật họ tên, địa chỉ email và số điện thoại của bạn.</p>
                                        </div>
                                    </div>

                                    <div class="settings-card-body">
                                        <!-- Guest Warning nếu chưa đăng nhập -->
                                        <div id="settingsGuestBanner" class="settings-guest-banner" style="display: none;">
                                            <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                            <div>
                                                <strong>Bạn đang truy cập ở chế độ khách vãng lai.</strong>
                                                <p>Vui lòng đăng nhập để lưu trữ hồ sơ và đồng bộ cài đặt cá nhân trên đám mây.</p>
                                            </div>
                                            <button type="button" class="btn-primary btn-sm" onclick="openAuthModal('login')">Đăng nhập</button>
                                        </div>

                                        <form id="profileForm" onsubmit="handleProfileSubmit(event)">
                                            <div class="settings-profile-head">
                                                <div class="profile-avatar-box" id="settingsAvatarBox">
                                                    <?= mb_substr($_SESSION['user_name'] ?? 'U', 0, 2) ?>
                                                </div>
                                                <div class="profile-meta-details">
                                                    <div style="display: flex; align-items: center; gap: 8px;">
                                                        <span class="profile-name-display" id="settingsProfileDisplayName"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Khách vãng lai') ?></span>
                                                        <span class="plan-badge <?= strtolower($_SESSION['user_plan'] ?? 'FREE') ?>" id="settingsProfilePlanBadge"><?= htmlspecialchars($_SESSION['user_plan'] ?? 'FREE') ?></span>
                                                    </div>
                                                    <span class="profile-subtext" id="settingsProfileSubtext">Tham gia: <?= !empty($_SESSION['created_at']) ? date('d/m/Y', strtotime($_SESSION['created_at'])) : 'Hôm nay' ?></span>
                                                </div>
                                            </div>

                                            <div class="form-row-2">
                                                <div class="settings-form-group">
                                                    <label for="setFullName">Họ và tên <span class="text-danger">*</span></label>
                                                    <input type="text" id="setFullName" class="settings-input" placeholder="Nhập họ và tên..." value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>" required>
                                                </div>
                                                <div class="settings-form-group">
                                                    <label for="setUsername">Tên đăng nhập (Username)</label>
                                                    <input type="text" id="setUsername" class="settings-input disabled-input" value="<?= htmlspecialchars($_SESSION['username'] ?? 'guest') ?>" readonly title="Tên đăng nhập không thể thay đổi">
                                                </div>
                                            </div>

                                            <div class="form-row-2">
                                                <div class="settings-form-group">
                                                    <label for="setEmail">Địa chỉ Email <span class="text-danger">*</span></label>
                                                    <input type="email" id="setEmail" class="settings-input" placeholder="ten@domain.com" value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>" required>
                                                </div>
                                                <div class="settings-form-group">
                                                    <label for="setPhone">Số điện thoại</label>
                                                    <input type="tel" id="setPhone" class="settings-input" placeholder="Ví dụ: 0987654321" value="<?= htmlspecialchars($_SESSION['phone'] ?? '') ?>">
                                                </div>
                                            </div>

                                            <div class="settings-form-actions">
                                                <button type="submit" class="btn-save-profile" id="btnSaveProfile">
                                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                                                    <span>Lưu hồ sơ cá nhân</span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Card 1.2: Đổi mật khẩu -->
                                <div class="settings-card">
                                    <div class="settings-card-header">
                                        <div class="card-header-icon icon-purple">
                                            <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="settings-card-title">Đổi mật khẩu bảo mật</h3>
                                            <p class="settings-card-desc">Bảo vệ tài khoản bằng mật khẩu có độ bảo mật cao.</p>
                                        </div>
                                    </div>

                                    <div class="settings-card-body">
                                        <form id="passwordForm" onsubmit="handlePasswordSubmit(event)">
                                            <div class="settings-form-group">
                                                <label for="setCurrentPassword">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                                                <div class="password-input-wrap">
                                                    <input type="password" id="setCurrentPassword" class="settings-input" placeholder="Nhập mật khẩu đang dùng..." required>
                                                    <button type="button" class="btn-eye-toggle" onclick="togglePasswordVisibility('setCurrentPassword')" tabindex="-1">
                                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="form-row-2">
                                                <div class="settings-form-group">
                                                    <label for="setNewPassword">Mật khẩu mới <span class="text-danger">*</span></label>
                                                    <div class="password-input-wrap">
                                                        <input type="password" id="setNewPassword" class="settings-input" placeholder="Tối thiểu 6 ký tự..." required minlength="6" oninput="checkPasswordStrength(this.value)">
                                                        <button type="button" class="btn-eye-toggle" onclick="togglePasswordVisibility('setNewPassword')" tabindex="-1">
                                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="settings-form-group">
                                                    <label for="setConfirmPassword">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                                                    <div class="password-input-wrap">
                                                        <input type="password" id="setConfirmPassword" class="settings-input" placeholder="Nhập lại mật khẩu mới..." required minlength="6">
                                                        <button type="button" class="btn-eye-toggle" onclick="togglePasswordVisibility('setConfirmPassword')" tabindex="-1">
                                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Thanh độ mạnh mật khẩu -->
                                            <div class="password-strength-container" id="passwordStrengthBox" style="display: none;">
                                                <div class="strength-bar-track">
                                                    <div class="strength-bar-fill" id="strengthBarFill"></div>
                                                </div>
                                                <span class="strength-text" id="strengthText">Độ mạnh: Yếu</span>
                                            </div>

                                            <div class="settings-form-actions">
                                                <button type="submit" class="btn-update-password" id="btnChangePassword">
                                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                                                    <span>Cập nhật mật khẩu mới</span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: XỬ LÝ & XUẤT PDF -->
                        <div class="settings-tab-pane" id="pane-pdf">
                            <div class="settings-cards-grid">
                                <!-- Card 2.1: Cấu hình Render & DPI -->
                                <div class="settings-card">
                                    <div class="settings-card-header">
                                        <div class="card-header-icon icon-blue">
                                            <svg class="svg-icon" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="settings-card-title">Chất lượng Render & Nén tệp xuất</h3>
                                            <p class="settings-card-desc">Cân đối giữa tốc độ xử lý nhanh và độ sắc nét tài liệu in ấn.</p>
                                        </div>
                                    </div>

                                    <div class="settings-card-body">
                                        <div class="settings-form-group">
                                            <label for="setPdfDpi">Độ phân giải trang xem trước (Canvas Render DPI)</label>
                                            <select id="setPdfDpi" class="settings-select" onchange="markSettingsChanged()">
                                                <option value="150">150 DPI (Tiêu chuẩn - Tốc độ nhanh, mượt mà)</option>
                                                <option value="200">200 DPI (Cân bằng - Chữ và bảng biểu rõ nét)</option>
                                                <option value="300">300 DPI (Chất lượng in ấn chuyên nghiệp - Sắc nét Ultra HD)</option>
                                            </select>
                                            <span class="field-hint">Mức 150 DPI được khuyến nghị cho hầu hết các tác vụ xem và chỉnh sửa PDF trên trình duyệt.</span>
                                        </div>

                                        <div class="settings-form-group">
                                            <label for="setPdfCompression">Chế độ tối ưu & nén dung lượng khi xuất file</label>
                                            <select id="setPdfCompression" class="settings-select" onchange="markSettingsChanged()">
                                                <option value="medium">Nén thông minh (Tiết kiệm ~40% dung lượng, giữ nguyên độ nét)</option>
                                                <option value="maximum">Nén tối đa (Tiết kiệm tới ~70% dung lượng, thích hợp gửi email)</option>
                                                <option value="none">Giữ nguyên gốc 100% (Không nén, giữ nguyên kích thước dữ liệu)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card 2.2: Tùy biến tự động khi ghép & tách -->
                                <div class="settings-card">
                                    <div class="settings-card-header">
                                        <div class="card-header-icon icon-emerald">
                                            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="settings-card-title">Hành vi tự động khi biên tập PDF</h3>
                                            <p class="settings-card-desc">Thiết lập tự động hóa giúp tiết kiệm thời gian chỉnh sửa văn bản.</p>
                                        </div>
                                    </div>

                                    <div class="settings-card-body">
                                        <!-- Item 1: Đánh số trang -->
                                        <div class="setting-toggle-row">
                                            <div class="toggle-row-content">
                                                <span class="toggle-row-title">Tự động đánh số trang khi ghép tệp</span>
                                                <span class="toggle-row-desc">Tự động chèn số trang liên tục (1, 2, 3...) vào tài liệu sau khi ghép hoàn tất.</span>
                                            </div>
                                            <label class="switch-control">
                                                <input type="checkbox" id="setAutoPageNumbers" checked onchange="togglePageNumberPos(this.checked); markSettingsChanged();">
                                                <span class="switch-slider"></span>
                                            </label>
                                        </div>

                                        <!-- Vị trí số trang -->
                                        <div class="settings-sub-group" id="pageNumberPosWrap">
                                            <label for="setPageNumberPos">Vị trí đánh số trang:</label>
                                            <select id="setPageNumberPos" class="settings-select-sm" onchange="markSettingsChanged()">
                                                <option value="bottom-right">Góc dưới cùng bên phải</option>
                                                <option value="bottom-center">Ở giữa đáy trang</option>
                                                <option value="top-right">Góc trên cùng bên phải</option>
                                            </select>
                                        </div>

                                        <div class="setting-row-divider"></div>

                                        <!-- Item 2: Tự động tối ưu dung lượng -->
                                        <div class="setting-toggle-row">
                                            <div class="toggle-row-content">
                                                <span class="toggle-row-title">Tự động loại bỏ siêu dữ liệu thừa</span>
                                                <span class="toggle-row-desc">Xóa các metadata rác không sử dụng nhằm giảm kích thước file xuất ra.</span>
                                            </div>
                                            <label class="switch-control">
                                                <input type="checkbox" id="setAutoOptimizeSize" checked onchange="markSettingsChanged();">
                                                <span class="switch-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: GÓI & DUNG LƯỢNG -->
                        <div class="settings-tab-pane" id="pane-plan">
                            <div class="settings-cards-grid">
                                <!-- Card 3.1: Gói dịch vụ hiện tại -->
                                <div class="settings-card">
                                    <div class="settings-card-header">
                                        <div class="card-header-icon icon-amber">
                                            <svg class="svg-icon" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="settings-card-title">Gói thành viên & Đặc quyền</h3>
                                            <p class="settings-card-desc">Chi tiết gói dịch vụ bạn đang sử dụng trên hệ thống PDF Online.</p>
                                        </div>
                                    </div>

                                    <div class="settings-card-body">
                                        <div class="plan-card-showcase <?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'plan-pro-showcase' : 'plan-free-showcase' ?>">
                                            <div class="plan-showcase-header">
                                                <div>
                                                    <span class="plan-showcase-badge"><?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'VIP PRO MEMBER' : 'FREE BASIC' ?></span>
                                                    <h4 class="plan-showcase-title"><?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'Gói Chuyên Nghiệp (PRO VIP)' : 'Gói Miễn Phí (Cơ bản)' ?></h4>
                                                </div>
                                                <div class="plan-showcase-icon">
                                                    <?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? '⭐' : '📄' ?>
                                                </div>
                                            </div>

                                            <p class="plan-showcase-desc">
                                                <?= ($currentUser['plan'] ?? 'FREE') === 'PRO' 
                                                    ? 'Bạn đang sở hữu đặc quyền xử lý PDF với dung lượng không giới hạn, xuất file chất lượng cao nhất và hỗ trợ ưu tiên 24/7.' 
                                                    : 'Tài khoản miễn phí tiêu chuẩn với 100 MB lưu trữ đám mây và các công cụ ghép, tách PDF căn bản.' ?>
                                            </p>

                                            <div class="plan-features-list">
                                                <div class="plan-feature-item">
                                                    <svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                                    <span><?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'Dung lượng lưu trữ: <strong>Không giới hạn</strong>' : 'Dung lượng lưu trữ: <strong>100 MB</strong>' ?></span>
                                                </div>
                                                <div class="plan-feature-item">
                                                    <svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                                    <span><?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? 'Tốc độ xuất file: <strong>Ưu tiên cao nhất</strong>' : 'Tốc độ xuất file: <strong>Tiêu chuẩn</strong>' ?></span>
                                                </div>
                                                <div class="plan-feature-item">
                                                    <svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                                    <span>Minh bạch nguồn gốc từng trang với bảng Source Manifest</span>
                                                </div>
                                            </div>

                                            <div class="plan-showcase-footer">
                                                <button type="button" class="btn-plan-upgrade-lg" onclick="switchView('upgrade')">
                                                    <?= ($currentUser['plan'] ?? 'FREE') === 'PRO' ? '⭐ Gia hạn gói PRO / Quản lý thanh toán' : '⚡ Nâng cấp lên gói PRO ngay' ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card 3.2: Bộ nhớ lưu trữ đám mây -->
                                <div class="settings-card">
                                    <div class="settings-card-header">
                                        <div class="card-header-icon icon-purple">
                                            <svg class="svg-icon" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="settings-card-title">Phân tích dung lượng lưu trữ</h3>
                                            <p class="settings-card-desc">Thống kê không gian tệp PDF và bộ nhớ đệm đám mây.</p>
                                        </div>
                                    </div>

                                    <div class="settings-card-body">
                                        <div class="storage-breakdown-box">
                                            <div class="storage-breakdown-header">
                                                <span class="storage-text-label">Tổng dung lượng đã sử dụng</span>
                                                <strong class="storage-text-value" id="settingsStorageRatioText"><?= $storageRatioText ?? '0 MB / 100 MB' ?></strong>
                                            </div>
                                            <div class="storage-progress-bar-wrap">
                                                <div class="storage-progress-bar-fill" style="width: <?= min(100, max(5, $storagePercent ?? 5)) ?>%;"></div>
                                            </div>
                                        </div>

                                        <div class="storage-stat-mini-grid">
                                            <div class="storage-stat-mini-item">
                                                <span class="stat-mini-label">Tài liệu gốc</span>
                                                <strong class="stat-mini-value"><?= $totalUserDocs ?? 0 ?> tệp</strong>
                                            </div>
                                            <div class="storage-stat-mini-item">
                                                <span class="stat-mini-label">Tệp đã xuất</span>
                                                <strong class="stat-mini-value" id="settingsExportCountMini">-- tệp</strong>
                                            </div>
                                            <div class="storage-stat-mini-item">
                                                <span class="stat-mini-label">Trạng thái</span>
                                                <strong class="stat-mini-value text-success">An toàn</strong>
                                            </div>
                                        </div>

                                        <div class="settings-card-subaction">
                                            <button type="button" class="btn-outline btn-sm" onclick="switchView('history')">
                                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                                <span>Xem & Dọn dẹp tệp xuất thừa</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 4: BẢO MẬT & DỮ LIỆU -->
                        <div class="settings-tab-pane" id="pane-security">
                            <div class="settings-cards-grid">
                                <!-- Card 4.1: Quyền riêng tư & Tự hủy tệp -->
                                <div class="settings-card">
                                    <div class="settings-card-header">
                                        <div class="card-header-icon icon-emerald">
                                            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="settings-card-title">Quyền riêng tư & Tự động dọn dẹp</h3>
                                            <p class="settings-card-desc">Bảo vệ quyền riêng tư tài liệu theo tiêu chuẩn mã hóa SSL/TLS.</p>
                                        </div>
                                    </div>

                                    <div class="settings-card-body">
                                        <div class="settings-form-group">
                                            <label for="setAutoCleanupHours">Thời gian tự động dọn dẹp tệp tạm thời</label>
                                            <select id="setAutoCleanupHours" class="settings-select" onchange="markSettingsChanged()">
                                                <option value="1">Sau 1 giờ (Bảo mật tối đa cho tài liệu nhạy cảm)</option>
                                                <option value="24" selected>Sau 24 giờ (Tiêu chuẩn khuyến nghị)</option>
                                                <option value="168">Sau 7 ngày (Dành cho dự án cần rà soát lại)</option>
                                            </select>
                                            <span class="field-hint">Sau khoảng thời gian này, các tệp nháp tạm không xuất bản sẽ tự động được xóa vĩnh viễn khỏi máy chủ.</span>
                                        </div>

                                        <div class="setting-row-divider"></div>

                                        <div class="setting-toggle-row">
                                            <div class="toggle-row-content">
                                                <span class="toggle-row-title">Tự động xóa tài liệu gốc sau khi xuất xong</span>
                                                <span class="toggle-row-desc">Nếu bật, các trang file tải lên ban đầu sẽ được dọn dẹp ngay sau khi bấm lưu/xuất PDF.</span>
                                            </div>
                                            <label class="switch-control">
                                                <input type="checkbox" id="setDeleteAfterExport" onchange="markSettingsChanged();">
                                                <span class="switch-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card 4.2: Quản lý bộ nhớ đệm & Dữ liệu -->
                                <div class="settings-card">
                                    <div class="settings-card-header">
                                        <div class="card-header-icon icon-blue">
                                            <svg class="svg-icon" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="settings-card-title">Bộ nhớ đệm & Giải phóng bộ nhớ</h3>
                                            <p class="settings-card-desc">Xóa dữ liệu nháp trình duyệt nếu gặp tình trạng giật lag.</p>
                                        </div>
                                    </div>

                                    <div class="settings-card-body">
                                        <div class="setting-action-block">
                                            <div>
                                                <strong>Xóa bộ nhớ đệm trình duyệt (Local Cache)</strong>
                                                <p style="font-size: 0.8rem; color: var(--text-muted); margin: 3px 0 0 0;">Làm mới bộ nhớ đệm trang PDF và trạng thái chỉnh sửa chưa lưu trong bộ nhớ máy tính.</p>
                                            </div>
                                            <button type="button" class="btn-outline btn-sm" onclick="clearLocalPdfCache()">
                                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10"/></svg>
                                                <span>Xóa Cache</span>
                                            </button>
                                        </div>

                                        <div class="setting-row-divider"></div>

                                        <div class="danger-zone-box">
                                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                                <span class="badge-danger-pill">VÙNG NGUY HIỂM</span>
                                                <strong style="color: #DC2626; font-size: 0.9rem;">Dọn dẹp toàn bộ dữ liệu</strong>
                                            </div>
                                            <p style="font-size: 0.78rem; color: #64748B; margin-bottom: 12px;">Hành động này sẽ xóa toàn bộ tài liệu đã tải lên trong danh mục Quản lý tài liệu của bạn. Thao tác không thể hoàn tác.</p>
                                            <button type="button" class="btn-danger-outline" onclick="confirmPurgeAllDocuments()">
                                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                <span>Xóa toàn bộ tài liệu cá nhân</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 5: GIAO DIỆN & HIỂN THỊ -->
                        <div class="settings-tab-pane" id="pane-appearance">
                            <div class="settings-cards-grid">
                                <!-- Card 5.1: Giao diện Sáng / Tối -->
                                <div class="settings-card">
                                    <div class="settings-card-header">
                                        <div class="card-header-icon icon-blue">
                                            <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="settings-card-title">Chế độ giao diện (Theme Mode)</h3>
                                            <p class="settings-card-desc">Tùy biến màu sắc hiển thị phù hợp với điều kiện ánh sáng.</p>
                                        </div>
                                    </div>

                                    <div class="settings-card-body">
                                        <div class="theme-selector-grid">
                                            <div class="theme-choice-card" id="themeCardLight" onclick="selectAppTheme('light')">
                                                <div class="theme-preview-box preview-light">
                                                    <div class="preview-mini-header"></div>
                                                    <div class="preview-mini-body">
                                                        <div class="preview-mini-sidebar"></div>
                                                        <div class="preview-mini-content"></div>
                                                    </div>
                                                </div>
                                                <div class="theme-choice-info">
                                                    <span class="theme-choice-name">☀️ Giao diện Sáng (Light)</span>
                                                    <span class="theme-choice-desc">Rõ ràng, trực quan cho ban ngày</span>
                                                </div>
                                            </div>

                                            <div class="theme-choice-card" id="themeCardDark" onclick="selectAppTheme('dark')">
                                                <div class="theme-preview-box preview-dark">
                                                    <div class="preview-mini-header"></div>
                                                    <div class="preview-mini-body">
                                                        <div class="preview-mini-sidebar"></div>
                                                        <div class="preview-mini-content"></div>
                                                    </div>
                                                </div>
                                                <div class="theme-choice-info">
                                                    <span class="theme-choice-name">🌙 Giao diện Tối (Dark)</span>
                                                    <span class="theme-choice-desc">Dịu mắt, tiết kiệm pin khi làm việc đêm</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card 5.2: Tiện ích & Trải nghiệm thao tác -->
                                <div class="settings-card">
                                    <div class="settings-card-header">
                                        <div class="card-header-icon icon-purple">
                                            <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="settings-card-title">Trải nghiệm tương tác & Không gian</h3>
                                            <p class="settings-card-desc">Tối ưu hóa các thao tác điều hướng và thông báo.</p>
                                        </div>
                                    </div>

                                    <div class="settings-card-body">
                                        <!-- Item 1: Auto compact sidebar -->
                                        <div class="setting-toggle-row">
                                            <div class="toggle-row-content">
                                                <span class="toggle-row-title">Tự động thu gọn Sidebar trong Trình biên tập</span>
                                                <span class="toggle-row-desc">Tự động chuyển Sidebar thành dạng icon khi mở Trình biên tập để tăng tối đa vùng hiển thị trang PDF.</span>
                                            </div>
                                            <label class="switch-control">
                                                <input type="checkbox" id="setAutoCompactSidebar" checked onchange="markSettingsChanged();">
                                                <span class="switch-slider"></span>
                                            </label>
                                        </div>

                                        <div class="setting-row-divider"></div>

                                        <!-- Item 2: Notification sound -->
                                        <div class="setting-toggle-row">
                                            <div class="toggle-row-content">
                                                <span class="toggle-row-title">Âm thanh thông báo khi hoàn thành</span>
                                                <span class="toggle-row-desc">Phát âm thanh nhẹ nhàng báo hiệu khi xuất file hoặc chuyển đổi trang PDF thành công.</span>
                                            </div>
                                            <label class="switch-control">
                                                <input type="checkbox" id="setNotificationSound" checked onchange="markSettingsChanged();">
                                                <span class="switch-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
