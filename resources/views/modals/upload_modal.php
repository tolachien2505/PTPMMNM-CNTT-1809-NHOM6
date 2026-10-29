    <div id="uploadModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Tải lên tệp PDF mới</h3>
                <button class="btn-close" onclick="closeModal('uploadModal')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="drop-zone" id="uploadDropZone" onclick="document.getElementById('fileInput').click()">
                <div style="margin-bottom: 12px; color: #2563EB;">
                    <svg class="svg-icon" style="width: 48px; height: 48px;" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                </div>
                <h4 style="font-size: 1rem; margin-bottom: 4px; font-weight: 700;">Kéo & Thả tệp PDF vào đây</h4>
                <p style="font-size: 0.8rem; color: var(--text-muted);">hoặc bấm vào để chọn tệp từ máy tính</p>
                <p style="font-size: 0.72rem; color: #94A3B8; margin-top: 8px;">Giới hạn dung lượng tối đa: 25 MB • Chỉ chấp nhận định dạng .pdf</p>
            </div>
            <input type="file" id="fileInput" accept="application/pdf" style="display: none;" onchange="handleFileSelect(event)">
            <div id="uploadProgress" style="display: none; margin-top: 12px;">
                <div style="font-size: 0.8rem; margin-bottom: 4px;">Đang xử lý tải lên...</div>
                <div class="progress-bar"><div class="progress-fill" style="width: 100%;"></div></div>
            </div>
        </div>
    </div>

    <!-- MODAL: BẢNG ĐỐI CHIẾU TRANG NGUỒN (SOURCE MANIFEST) -->
