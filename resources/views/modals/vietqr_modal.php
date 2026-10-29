    <div id="vietQrModal" class="modal-overlay">
        <div class="modal-box vietqr-modal-box">
            <!-- Modal Header matching Screenshot 2 -->
            <div class="vqr-modal-header">
                <div class="vqr-header-info">
                    <h3 class="vqr-modal-title">Quét mã QR để chuyển khoản</h3>
                    <div class="vqr-invoice-sub">Mã hóa đơn: <span id="vqrInvoiceCode" class="vqr-inv-code">X6CWA</span></div>
                </div>
                <button type="button" class="vqr-modal-close" onclick="closeVietQrModal()" aria-label="Đóng">✕</button>
            </div>

            <div class="modal-body vietqr-modal-body">
                <!-- Status & Countdown Timer Bar matching Screenshot 2 -->
                <div class="vqr-status-timer-bar">
                    <div class="vqr-status-indicator" id="vqrStatusIndicator">
                        <div class="vqr-spinner"></div>
                        <span class="vqr-status-label" id="vqrStatusText">Đang chờ thanh toán...</span>
                    </div>
                    <div class="vqr-countdown-pill" id="vqrCountdownPill" title="Thời gian còn lại để hoàn tất chuyển khoản">
                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span id="vqrTimerVal">59:55</span>
                    </div>
                </div>

                <!-- Payment Success Banner (Shown upon payment confirmation) -->
                <div class="vqr-success-banner" id="vqrSuccessBanner" style="display: none;">
                    <div class="success-icon-badge">🎉</div>
                    <div class="success-content">
                        <strong>Nâng cấp thành công!</strong>
                        <p>Hệ thống đã xác thực giao dịch và nâng cấp tài khoản của bạn lên PRO.</p>
                    </div>
                </div>

                <!-- Main Content Split: Left QR Code, Right Copy Info Cards -->
                <div class="vqr-content-grid">
                    <!-- Left: QR Code Card matching Screenshot 2 -->
                    <div class="vqr-qr-column">
                        <div class="vqr-qr-card">
                            <div class="vqr-qr-header">
                                <span class="vqr-brand-vietqr">Viet<strong class="text-accent">QR</strong></span>
                            </div>
                            <div class="vqr-qr-img-wrap">
                                <img id="vqrQrImage" src="" alt="Mã VietQR Chuyển khoản" class="vqr-qr-img">
                            </div>
                            <div class="vqr-qr-footer">
                                <span class="vqr-brand-napas">napas<strong>247</strong></span>
                                <span class="vqr-footer-sep">|</span>
                                <span class="vqr-brand-bank" id="vqrBankShort">ACB</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Bank Details with 5 Copy Rows matching Screenshot 2 -->
                    <div class="vqr-details-column">
                        <div class="vqr-info-row">
                            <div class="vqr-row-content">
                                <span class="vqr-field-label">NGÂN HÀNG</span>
                                <strong class="vqr-field-value" id="vqrBankFullName">Ngân hàng TMCP Á Châu (ACB)</strong>
                            </div>
                            <button type="button" class="vqr-btn-copy" onclick="copyToClipboard(document.getElementById('vqrBankFullName').innerText, 'Đã sao chép tên ngân hàng!')" title="Sao chép tên ngân hàng">
                                <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            </button>
                        </div>

                        <div class="vqr-info-row">
                            <div class="vqr-row-content">
                                <span class="vqr-field-label">SỐ TÀI KHOẢN</span>
                                <strong class="vqr-field-value font-mono" id="vqrAccountNumber">17449371</strong>
                            </div>
                            <button type="button" class="vqr-btn-copy" onclick="copyToClipboard(document.getElementById('vqrAccountNumber').innerText, 'Đã sao chép số tài khoản!')" title="Sao chép số tài khoản">
                                <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            </button>
                        </div>

                        <div class="vqr-info-row">
                            <div class="vqr-row-content">
                                <span class="vqr-field-label">CHỦ TÀI KHOẢN</span>
                                <strong class="vqr-field-value text-uppercase" id="vqrAccountName">VU VAN CHIEN</strong>
                            </div>
                            <button type="button" class="vqr-btn-copy" onclick="copyToClipboard(document.getElementById('vqrAccountName').innerText, 'Đã sao chép tên chủ tài khoản!')" title="Sao chép tên chủ tài khoản">
                                <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            </button>
                        </div>

                        <div class="vqr-info-row vqr-amount-row">
                            <div class="vqr-row-content">
                                <span class="vqr-field-label">SỐ TIỀN</span>
                                <strong class="vqr-field-value text-primary font-mono" id="vqrAmountDisplay" data-raw-amount="99000">99.000đ</strong>
                            </div>
                            <button type="button" class="vqr-btn-copy" onclick="copyToClipboard(document.getElementById('vqrAmountDisplay').dataset.rawAmount || '99000', 'Đã sao chép số tiền!')" title="Sao chép số tiền">
                                <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            </button>
                        </div>

                        <div class="vqr-info-row vqr-memo-row">
                            <div class="vqr-row-content">
                                <span class="vqr-field-label">NỘI DUNG CHUYỂN KHOẢN</span>
                                <strong class="vqr-field-value font-mono vqr-memo-text" id="vqrMemoDisplay">PDFPRO <?= $currentUser['id'] ?? 1 ?> X6CWA</strong>
                            </div>
                            <button type="button" class="vqr-btn-copy" onclick="copyToClipboard(document.getElementById('vqrMemoDisplay').innerText, 'Đã sao chép nội dung chuyển khoản!')" title="Sao chép nội dung chuyển khoản">
                                <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Bottom Important Warning Alert matching Screenshot 2 -->
                <div class="vqr-alert-note">
                    <span class="vqr-alert-icon">⚠️</span>
                    <div class="vqr-alert-text">
                        <strong>Quan trọng:</strong> Phải chuyển khoản <u>chính xác số tiền</u> và <u>đúng nội dung</u> ở trên — hệ thống tự xác nhận trong <strong>30-60 giây</strong> sau khi nhận tiền. Chuyển sai vui lòng liên hệ admin.
                    </div>
                </div>
            </div>
        </div>
    </div>
