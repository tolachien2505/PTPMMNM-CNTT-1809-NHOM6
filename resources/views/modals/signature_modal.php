    <div id="signatureModal" class="modal-overlay">
        <div class="modal-box modal-signature-box">
            <div class="modal-header sig-modal-header">
                <h3 class="sig-modal-title">Thêm chữ ký</h3>
                <button class="btn-close" onclick="closeModal('signatureModal')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="sig-tabs-bar">
                <div class="sig-tabs">
                    <button type="button" class="sig-tab-btn active" id="tabSigDraw" onclick="switchSigTab('draw')">
                        <span>Vẽ tranh</span>
                    </button>
                    <button type="button" class="sig-tab-btn" id="tabSigUpload" onclick="switchSigTab('upload')">
                        <span>Hình ảnh</span>
                    </button>
                    <button type="button" class="sig-tab-btn" id="tabSigType" onclick="switchSigTab('type')">
                        <span>Kiểu</span>
                    </button>
                </div>

                <!-- 3 Color dots on top right matching Image 2 -->
                <div class="sig-header-colors" id="sigHeaderColors">
                    <button type="button" class="sig-color-dot active" style="--c: #000000;" onclick="setSigColor('#000000', this)" title="Đen"></button>
                    <button type="button" class="sig-color-dot" style="--c: #2563EB;" onclick="setSigColor('#2563EB', this)" title="Xanh"></button>
                    <button type="button" class="sig-color-dot" style="--c: #6D28D9;" onclick="setSigColor('#6D28D9', this)" title="Tím"></button>
                </div>
            </div>

            <!-- Tab 1: Draw Canvas (Red border matching Image 2) -->
            <div id="sigTabContentDraw" class="sig-tab-content">
                <div class="sig-pad-wrapper-pdfaid">
                    <canvas id="sigCanvas" width="600" height="220" class="sig-canvas"></canvas>
                </div>
                <div class="sig-prompt-red" onclick="clearSigCanvas()" title="Bấm để vẽ lại chữ ký">Ký tên tại đây</div>
            </div>

            <!-- Tab 2: Upload Image -->
            <div id="sigTabContentUpload" class="sig-tab-content" style="display: none;">
                <div class="sig-upload-box" onclick="document.getElementById('sigImageFileInput').click()">
                    <svg class="svg-icon" style="width: 36px; height: 36px; color: #2563EB; margin-bottom: 8px;" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <div style="font-weight: 600; font-size: 0.9rem;">Bấm để chọn ảnh chữ ký (PNG, JPG)</div>
                    <div style="font-size: 0.75rem; color: #94A3B8; margin-top: 4px;">Khuyến nghị ảnh nền trong suốt (Transparent PNG)</div>
                </div>
                <input type="file" id="sigImageFileInput" accept="image/png,image/jpeg" style="display: none;" onchange="handleSigImageUpload(event)">
                <div id="sigImagePreviewContainer" style="display: none; text-align: center; margin-top: 12px;">
                    <img id="sigUploadedImg" src="" style="max-height: 120px; max-width: 300px; border: 1.5px solid #EF4444; padding: 6px; border-radius: 8px;">
                </div>
            </div>

            <!-- Tab 3: Type Signature (Kiểu) -->
            <div id="sigTabContentType" class="sig-tab-content" style="display: none;">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Nhập tên để tạo chữ ký tự động:</label>
                    <input type="text" id="sigTextInput" class="auth-input" style="padding-left: 12px;" value="Nguyễn Văn A" oninput="updateTypedSigPreview()">
                </div>
                <div class="sig-typed-preview-box">
                    <div id="sigTypedPreview" class="sig-typed-text" style="font-family: 'Dancing Script', cursive, sans-serif; font-size: 38px; color: #000000;">Nguyễn Văn A</div>
                </div>
                <div class="sig-font-picker">
                    <button type="button" class="sig-font-option active" onclick="setSigFont('Dancing Script, cursive', this)">Phong cách 1 (Nét ký thanh)</button>
                    <button type="button" class="sig-font-option" onclick="setSigFont('Brush Script MT, cursive', this)">Phong cách 2 (Nét bút lông)</button>
                    <button type="button" class="sig-font-option" onclick="setSigFont('Segoe Script, cursive', this)">Phong cách 3 (Nghiêng mềm mại)</button>
                </div>
            </div>

            <div class="modal-actions sig-modal-actions">
                <button type="button" class="btn-sig-cancel" onclick="closeModal('signatureModal')">Hủy bỏ</button>
                <button type="button" class="btn-sig-apply-red" onclick="applySignatureToPage()">Xong</button>
            </div>
        </div>
    </div>

    <!-- MODAL: CON DẤU PHÊ DUYỆT (OFFICIAL STAMPS) -->
