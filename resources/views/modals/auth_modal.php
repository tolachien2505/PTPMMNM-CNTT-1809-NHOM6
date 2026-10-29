    <div id="authModal" class="modal-overlay">
        <div class="modal-box auth-modal-box">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <img src="/img/favicon.png" alt="PDF Online" style="width: 38px; height: 38px; object-fit: contain;">
                    <div>
                        <h3 id="authModalTitle" style="font-size: 1.15rem; font-weight: 700; margin-bottom: 2px;">Tài khoản PDF Online</h3>
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
