    <div id="stampModal" class="modal-overlay">
        <div class="modal-box modal-stamp-box">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="brand-icon" style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #DC2626, #B91C1C);">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24" style="stroke: #fff;"><path d="M5 22h14"/><path d="M19.27 13.73A2.5 2.5 0 0 0 17.5 9h-11A2.5 2.5 0 0 0 4.73 13.73L7 17h10l2.27-3.27z"/><path d="M12 9V2"/></svg>
                    </div>
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Chọn con dấu đóng vào tài liệu</h3>
                        <p style="font-size: 0.75rem; color: var(--text-muted); margin: 2px 0 0;">Dấu tiêu chuẩn phê duyệt, phân loại bảo mật và xác thực công văn</p>
                    </div>
                </div>
                <button class="btn-close" onclick="closeModal('stampModal')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="stamps-grid">
                <div class="stamp-pick-item stamp-approved" onclick="insertStamp('approved')">
                    <div class="stamp-badge-preview approved">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">★ ĐÃ PHÊ DUYỆT ★</span>
                            <span class="stamp-date">NGÀY: 23/10/2026</span>
                        </div>
                    </div>
                    <div class="stamp-desc">Đã phê duyệt (Chính thức)</div>
                </div>

                <div class="stamp-pick-item stamp-draft" onclick="insertStamp('draft')">
                    <div class="stamp-badge-preview draft">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">BẢN NHÁP (DRAFT)</span>
                            <span class="stamp-date">LƯU HÀNH NỘI BỘ</span>
                        </div>
                    </div>
                    <div class="stamp-desc">Bản nháp thử nghiệm</div>
                </div>

                <div class="stamp-pick-item stamp-secret" onclick="insertStamp('secret')">
                    <div class="stamp-badge-preview secret">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">🔒 TUYỆT MẬT</span>
                            <span class="stamp-date">KHÔNG SAO CHÉP</span>
                        </div>
                    </div>
                    <div class="stamp-desc">Bảo mật / Tuyệt mật</div>
                </div>

                <div class="stamp-pick-item stamp-completed" onclick="insertStamp('completed')">
                    <div class="stamp-badge-preview completed">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">✓ HOÀN THÀNH</span>
                            <span class="stamp-date">ĐẠT YÊU CẦU</span>
                        </div>
                    </div>
                    <div class="stamp-desc">Nghiệm thu hoàn tất</div>
                </div>

                <div class="stamp-pick-item stamp-certified" onclick="insertStamp('certified')">
                    <div class="stamp-badge-preview certified">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">SAO Y BẢN CHÍNH</span>
                            <span class="stamp-date">VĂN THƯ ĐÃ DUYỆT</span>
                        </div>
                    </div>
                    <div class="stamp-desc">Chứng thực bản sao</div>
                </div>

                <div class="stamp-pick-item stamp-void" onclick="insertStamp('void')">
                    <div class="stamp-badge-preview void">
                        <div class="stamp-inner-border">
                            <span class="stamp-label">✕ HỦY BỎ (VOID)</span>
                            <span class="stamp-date">HẾT HIỆU LỰC</span>
                        </div>
                    </div>
                    <div class="stamp-desc">Hủy bỏ / Vô hiệu</div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: XUẤT TỆP THÀNH CÔNG (EXPORT RESULT) -->
