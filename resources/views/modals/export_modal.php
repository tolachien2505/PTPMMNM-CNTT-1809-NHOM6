    <div id="exportSuccessModal" class="modal-overlay">
        <div class="modal-box export-modal-box">
            <div class="export-success-header">
                <div class="export-success-icon-wrap">
                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: #0F172A; margin: 12px 0 4px;">Xuất tệp PDF thành công!</h3>
                <p style="font-size: 0.82rem; color: #64748B; margin: 0;">Tài liệu đã được biên tập, nhúng vector chuẩn và sẵn sàng tải về</p>
            </div>

            <div class="export-meta-card">
                <div class="export-meta-row">
                    <span class="meta-lbl">Tên tệp:</span>
                    <strong class="meta-val" id="expDocName">Tai_lieu_bien_tap.pdf</strong>
                </div>
                <div class="export-meta-row">
                    <span class="meta-lbl">Tổng số trang:</span>
                    <strong class="meta-val" id="expDocPages">11 trang</strong>
                </div>
                <div class="export-meta-row">
                    <span class="meta-lbl">Trạng thái:</span>
                    <span class="status-pill success"><span class="status-dot"></span> Đã hoàn tất 100%</span>
                </div>
                <div class="export-meta-row">
                    <span class="meta-lbl">Định dạng xuất:</span>
                    <strong class="meta-val">PDF chuẩn ISO/IEC 32000</strong>
                </div>
            </div>

            <div class="export-actions">
                <a href="#" id="expDownloadBtn" class="btn-primary btn-lg" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Tải xuống tệp PDF hoàn chỉnh</span>
                </a>
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button type="button" class="btn-outline" style="flex: 1;" onclick="openManifestModal(window.currentOutputId)">
                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                        <span>Xem bảng đối chiếu</span>
                    </button>
                    <button type="button" class="btn-secondary" style="flex: 1;" onclick="closeModal('exportSuccessModal')">
                        <span>Tiếp tục chỉnh sửa</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: CẤU HÌNH NGÂN HÀNG (MATCHING SCREENSHOT 4) -->
