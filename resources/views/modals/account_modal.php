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
    </div>

    <!-- FULLSCREEN PAGE MANAGER MODAL (MATCHING IMAGE 1 / PDFAID) -->
