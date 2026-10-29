    <!-- MODAL: CẤU HÌNH NGÂN HÀNG (LUXURY SAAS DESIGN) -->
    <div id="bankSettingsModal" class="modal-overlay">
        <div class="modal-box modal-bank-settings">
            <!-- Modal Header -->
            <div class="modal-header">
                <div class="modal-header-info">
                    <div class="modal-header-icon-box bg-bank-gradient">
                        <svg class="svg-icon" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                    </div>
                    <div>
                        <h3 class="modal-title">Cấu hình kết nối ngân hàng</h3>
                        <p class="modal-subtitle">
                            <span class="badge-sub-id">ACB API</span>
                            <span class="sub-dot">•</span>
                            <span class="sub-meta">VietQR Napas 24/7</span>
                        </p>
                    </div>
                </div>
                <button type="button" class="modal-close-icon-btn" onclick="closeModal('bankSettingsModal')" title="Đóng cửa sổ">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <!-- Form -->
            <form id="formBankSettings" onsubmit="saveBankSettings(event)">
                <div class="modal-body">
                    <!-- Row 1: Ngân hàng & Tên hiển thị -->
                    <div class="form-row-grid">
                        <div class="form-group">
                            <label class="form-label" for="cfgBankCode">
                                <span>Mã ngân hàng</span>
                                <span class="text-danger">*</span>
                            </label>
                            <div class="custom-select-wrapper">
                                <select id="cfgBankCode" class="form-control">
                                    <option value="ACB">ACB — Ngân hàng TMCP Á Châu</option>
                                    <option value="VCB">VCB — Vietcombank</option>
                                    <option value="MB">MB — MBBank</option>
                                    <option value="TPB">TPB — TPBank</option>
                                    <option value="VPB">VPB — VPBank</option>
                                    <option value="TCB">TCB — Techcombank</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="cfgBankName">
                                <span>Tên ngân hàng hiển thị</span>
                            </label>
                            <input type="text" id="cfgBankName" class="form-control" placeholder="Ngân hàng TMCP Á Châu">
                        </div>
                    </div>

                    <!-- Row 2: Số tài khoản & Tên chủ tài khoản -->
                    <div class="form-row-grid">
                        <div class="form-group">
                            <label class="form-label" for="cfgAccountNumber">
                                <span>Số tài khoản thụ hưởng</span>
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="cfgAccountNumber" class="form-control font-mono font-bold" placeholder="17449371" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="cfgAccountName">
                                <span>Tên chủ tài khoản</span>
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="cfgAccountName" class="form-control uppercase font-mono font-bold" placeholder="VU VAN CHIEN" required>
                            <small class="form-hint">Viết hoa không dấu (trùng khớp với ngân hàng)</small>
                        </div>
                    </div>

                    <!-- Row 3: Token API -->
                    <div class="form-group">
                        <label class="form-label" for="cfgToken">
                            <span>Token API</span>
                        </label>
                        <div class="password-input-wrap">
                            <input type="password" id="cfgToken" class="form-control font-mono" placeholder="•••••••••••••">
                            <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('cfgToken')" title="Ẩn/Hiện token">
                                <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <small class="form-hint">Để trống nếu không muốn đổi token hiện tại</small>
                    </div>

                    <!-- Row 4: URL API -->
                    <div class="form-group">
                        <label class="form-label" for="cfgApiUrl">
                            <span>URL API Lịch sử giao dịch</span>
                        </label>
                        <input type="url" id="cfgApiUrl" class="form-control font-mono" placeholder="https://api.sieuthicode.net/historyapiacbv2/">
                    </div>

                    <!-- Row 5: Tiền tố nội dung nạp tiền -->
                    <div class="form-group">
                        <label class="form-label" for="cfgMemoPrefix">
                            <span>Tiền tố nội dung nạp tiền (Memo Prefix)</span>
                            <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="cfgMemoPrefix" class="form-control uppercase font-mono font-bold" placeholder="PDFPRO" value="PDFPRO" required>
                        <small class="form-hint">Cú pháp quét QR: <code>[TIỀN_TỐ] [ID_NGƯỜI_DÙNG]</code> (Ví dụ: <code>PDFPRO 1</code>)</small>
                    </div>

                    <!-- Row 6: Ghi chú -->
                    <div class="form-group">
                        <label class="form-label" for="cfgNotes">
                            <span>Ghi chú cấu hình</span>
                        </label>
                        <textarea id="cfgNotes" class="form-control" rows="2" placeholder="Ghi chú cấu hình nội bộ..."></textarea>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn-cancel-edit" onclick="closeModal('bankSettingsModal')">Hủy</button>
                    <button type="submit" class="btn-save-edit btn-save-bank" id="btnSaveBankSettings">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <span>Lưu thay đổi</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
