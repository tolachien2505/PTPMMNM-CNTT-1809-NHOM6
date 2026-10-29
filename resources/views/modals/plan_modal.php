    <div class="modal-overlay" id="planModal">
        <div class="modal-box plan-modal-box">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="brand-icon" style="width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #F59E0B, #D97706);">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24" style="stroke: #fff;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    </div>
                    <div>
                        <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 2px;">Gói dịch vụ & Quyền lợi</h3>
                        <p style="font-size: 0.74rem; color: var(--text-muted);">Lựa chọn gói phù hợp với tần suất xử lý tệp PDF của bạn</p>
                    </div>
                </div>
                <button class="btn-close" onclick="closeModal('planModal')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="pricing-grid">
                <!-- Gói FREE -->
                <div class="pricing-card <?= ($_SESSION['user_plan'] ?? 'FREE') === 'FREE' ? 'current-active-plan' : '' ?>">
                    <div class="pricing-card-header">
                        <span class="plan-badge free">FREE</span>
                        <h4 style="font-size: 1.05rem; margin: 8px 0 4px; font-weight: 700;">Gói Miễn Phí</h4>
                        <div class="pricing-price">0đ <span style="font-size: 0.76rem; font-weight: normal; color: var(--text-muted);">/ trọn đời</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Dung lượng lưu trữ: 1 GB</li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Ghép, tách, sắp xếp trang tiêu chuẩn</li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Tối đa 50 trang mỗi tài liệu</li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Xuất file PDF chất lượng gốc</li>
                        <li class="disabled"><svg class="svg-icon svg-icon-xs text-muted" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Dung lượng không giới hạn</li>
                        <li class="disabled"><svg class="svg-icon svg-icon-xs text-muted" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Ưu tiên hàng chờ xử lý siêu tốc</li>
                    </ul>
                    <?php if (($_SESSION['user_plan'] ?? 'FREE') === 'FREE'): ?>
                        <button type="button" class="btn-plan-select btn-current" disabled>✓ Đang sử dụng</button>
                    <?php else: ?>
                        <button type="button" class="btn-plan-select outline" onclick="upgradePlan('FREE')">Chuyển sang gói FREE</button>
                    <?php endif; ?>
                </div>

                <!-- Gói PRO -->
                <div class="pricing-card pro-card <?= ($_SESSION['user_plan'] ?? 'FREE') === 'PRO' ? 'current-active-plan' : '' ?>">
                    <div class="pro-ribbon">PHỔ BIẾN NHẤT</div>
                    <div class="pricing-card-header">
                        <span class="plan-badge pro">PRO</span>
                        <h4 style="font-size: 1.05rem; margin: 8px 0 4px; font-weight: 700;">Gói Chuyên Nghiệp</h4>
                        <div class="pricing-price">99.000đ <span style="font-size: 0.76rem; font-weight: normal; color: var(--text-muted);">/ tháng</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> <strong>Không giới hạn</strong> dung lượng lưu trữ</li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Không giới hạn số trang tệp PDF</li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Tốc độ xử lý siêu tốc ưu tiên hàng đầu</li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Nén file tối ưu hóa dung lượng</li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Trích xuất và bảo mật cao cấp</li>
                        <li><svg class="svg-icon svg-icon-xs text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Hỗ trợ kỹ thuật chuyên gia 24/7</li>
                    </ul>
                    <?php if (($_SESSION['user_plan'] ?? 'FREE') === 'PRO'): ?>
                        <button type="button" class="btn-plan-select btn-current" disabled>✓ Đang kích hoạt</button>
                    <?php else: ?>
                        <button type="button" class="btn-plan-select pro-btn" onclick="upgradePlan('PRO')">
                            <span>Nâng cấp PRO ngay</span>
                            <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Thông tin tài khoản -->
