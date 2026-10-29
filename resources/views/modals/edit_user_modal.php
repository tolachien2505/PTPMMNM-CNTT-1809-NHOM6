    <!-- MODAL: CẬP NHẬT NGƯỜI DÙNG (ADMIN USER UPDATE MODAL - LUXURY SAAS DESIGN) -->
    <div id="editUserModal" class="modal-overlay">
        <div class="modal-box modal-edit-user">
            <!-- Modal Header with Icon & Subtitle Badge -->
            <div class="modal-header">
                <div class="modal-header-info">
                    <div class="modal-header-icon-box">
                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>
                    </div>
                    <div>
                        <h3 class="modal-title">Cập nhật người dùng</h3>
                        <p class="modal-subtitle" id="editUserSub">
                            <span class="badge-sub-id">ID #<span id="editUserSubId"></span></span>
                            <span class="sub-dot">•</span>
                            <span class="sub-email" id="editUserSubEmail"></span>
                        </p>
                    </div>
                </div>
                <button type="button" class="modal-close-icon-btn" onclick="closeModal('editUserModal')" title="Đóng cửa sổ">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <form id="formEditUser" onsubmit="adminSaveUserEdit(event)">
                <input type="hidden" id="editUserId">
                
                <div class="modal-body">
                    <!-- 2-Column Responsive Form Grid -->
                    <div class="edit-user-form-grid">
                        <!-- Họ tên -->
                        <div class="form-group">
                            <label class="form-label" for="editUserFullName">
                                <span>Họ tên</span>
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="editUserFullName" class="form-control" required placeholder="Nhập họ và tên đầy đủ">
                        </div>

                        <!-- Số điện thoại -->
                        <div class="form-group">
                            <label class="form-label" for="editUserPhone">
                                <span>Số điện thoại</span>
                            </label>
                            <input type="tel" id="editUserPhone" class="form-control" placeholder="Ví dụ: 0912345678">
                        </div>

                        <!-- Vai trò -->
                        <div class="form-group">
                            <label class="form-label" for="editUserRole">
                                <span>Vai trò</span>
                            </label>
                            <select id="editUserRole" class="form-control">
                                <option value="user">Người dùng (User)</option>
                                <option value="admin">Quản trị viên (Admin)</option>
                            </select>
                        </div>

                        <!-- Gói dịch vụ -->
                        <div class="form-group">
                            <label class="form-label" for="editUserPlan">
                                <span>Gói dịch vụ</span>
                            </label>
                            <select id="editUserPlan" class="form-control">
                                <option value="FREE">Miễn phí (FREE)</option>
                                <option value="PRO">Gói VIP (PRO)</option>
                            </select>
                        </div>

                        <!-- Ngày hết hạn -->
                        <div class="form-group">
                            <label class="form-label" for="editUserExpiresAt">
                                <span>Ngày hết hạn</span>
                            </label>
                            <input type="date" id="editUserExpiresAt" class="form-control">
                        </div>

                        <!-- Reset mật khẩu -->
                        <div class="form-group">
                            <label class="form-label" for="editUserPassword">
                                <span>Reset mật khẩu</span>
                            </label>
                            <input type="password" id="editUserPassword" class="form-control" placeholder="Để trống nếu không đổi">
                        </div>
                    </div>

                    <!-- Modern Toggle Switch Cards (Active Account & Email Verified) -->
                    <div class="edit-user-toggle-cards">
                        <label class="toggle-card-label" for="editUserStatus">
                            <div class="toggle-switch-wrapper">
                                <input type="checkbox" id="editUserStatus" class="toggle-switch-input">
                                <span class="toggle-switch-slider"></span>
                            </div>
                            <div class="toggle-card-text">
                                <strong>Tài khoản hoạt động</strong>
                                <small>Cho phép đăng nhập & sử dụng hệ thống</small>
                            </div>
                        </label>

                        <label class="toggle-card-label" for="editUserEmailVerified">
                            <div class="toggle-switch-wrapper">
                                <input type="checkbox" id="editUserEmailVerified" class="toggle-switch-input">
                                <span class="toggle-switch-slider"></span>
                            </div>
                            <div class="toggle-card-text">
                                <strong>Email đã xác thực</strong>
                                <small>Đánh dấu địa chỉ email chính chủ</small>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn-cancel-edit" onclick="closeModal('editUserModal')">Hủy</button>
                    <button type="submit" class="btn-save-edit" id="btnSaveEditUser">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Lưu thay đổi</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
