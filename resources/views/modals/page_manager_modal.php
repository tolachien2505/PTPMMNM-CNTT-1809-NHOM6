    <div id="pageManagerModal" class="page-manager-modal">
        <div class="pm-dialog">
            <!-- Modal Header / Toolbar (Matching Image 4) -->
            <div class="pm-header-pdfaid">
                <div class="pm-toolbar-row">
                    <button class="pm-tool-item" onclick="pmAddNewPage()" title="Thêm trang trắng mới">
                        <span class="pm-tool-label">Trang mới</span>
                        <div class="pm-tool-icon-box">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                        </div>
                    </button>

                    <button class="pm-tool-item text-danger" onclick="pmDeleteSelected()" title="Xóa các trang đã chọn">
                        <span class="pm-tool-label">Xóa trang</span>
                        <div class="pm-tool-icon-box">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                        </div>
                    </button>

                    <button class="pm-tool-item" onclick="pmDuplicateSelected()" title="Sao chép / Nhân bản trang">
                        <span class="pm-tool-label">Nhân bản</span>
                        <div class="pm-tool-icon-box">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </div>
                    </button>

                    <button class="pm-tool-item" onclick="pmRotateSelected(-90)" title="Xoay ngược chiều kim đồng hồ 90°">
                        <span class="pm-tool-label">Xoay sang trái</span>
                        <div class="pm-tool-icon-box">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M2.5 2v6h6"/><path d="M2.5 8a10 10 0 1 1 2.4 7.6"/></svg>
                        </div>
                    </button>

                    <button class="pm-tool-item" onclick="pmRotateSelected(90)" title="Xoay theo chiều kim đồng hồ 90°">
                        <span class="pm-tool-label">Xoay sang phải</span>
                        <div class="pm-tool-icon-box">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21.5 2v6h-6"/><path d="M21.5 8a10 10 0 1 0-2.4 7.6"/></svg>
                        </div>
                    </button>

                    <button class="pm-tool-item" onclick="pmMoveSelected(-1)" title="Di chuyển vị trí trang">
                        <span class="pm-tool-label">↔ Di chuyển</span>
                        <div class="pm-tool-icon-box">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="17 11 12 6 7 11"/><polyline points="17 18 12 13 7 18"/><polyline points="7 13 12 18 17 13"/><polyline points="7 6 12 1 17 6"/></svg>
                        </div>
                    </button>

                    <button class="pm-tool-item" onclick="pmMoveSelected(-1)" title="Di chuyển trang lên trước">
                        <span class="pm-tool-label">Di chuyển trước</span>
                        <div class="pm-tool-icon-box">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="11 13 8 16 11 19"/><line x1="16" y1="16" x2="8" y2="16"/></svg>
                        </div>
                    </button>

                    <button class="pm-tool-item" onclick="pmMoveSelected(1)" title="Di chuyển trang xuống sau">
                        <span class="pm-tool-label">Di chuyển sau</span>
                        <div class="pm-tool-icon-box">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="13 13 16 16 13 19"/><line x1="8" y1="16" x2="16" y2="16"/></svg>
                        </div>
                    </button>

                    <button class="pm-tool-item" onclick="pmImportDocument()" title="Nhập thêm trang từ tệp PDF khác">
                        <span class="pm-tool-label">Nhập tài liệu</span>
                        <div class="pm-tool-icon-box">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </div>
                    </button>

                    <button class="pm-tool-item" onclick="pmUndo()" title="Hoàn tác (Ctrl+Z)">
                        <span class="pm-tool-label">Hoàn tác</span>
                        <div class="pm-tool-icon-box">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
                        </div>
                    </button>

                    <button class="pm-tool-item" onclick="pmRedo()" title="Làm lại (Ctrl+Y)">
                        <span class="pm-tool-label">Làm lại</span>
                        <div class="pm-tool-icon-box">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"/></svg>
                        </div>
                    </button>

                    <button class="pm-tool-item" onclick="pmToggleSelectAll()" id="btnPmSelectAll" title="Chọn tất cả các trang">
                        <span class="pm-tool-label" id="pmSelectAllLabel">Chọn tất cả</span>
                        <div class="pm-tool-icon-box">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><polyline points="9 11 12 14 22 4"/></svg>
                        </div>
                    </button>

                    <button class="pm-tool-item" onclick="pmDeselectAll()" title="Bỏ chọn tất cả">
                        <span class="pm-tool-label">Không chọn</span>
                        <div class="pm-tool-icon-box">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" stroke-dasharray="3 3"/></svg>
                        </div>
                    </button>

                    <button class="pm-tool-item" onclick="adjustPmCardSize(-25)" title="Thu nhỏ thẻ trang">
                        <span class="pm-tool-label">Thu nhỏ</span>
                        <div class="pm-tool-icon-box">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                        </div>
                    </button>

                    <button class="pm-tool-item" onclick="adjustPmCardSize(25)" title="Phóng to thẻ trang">
                        <span class="pm-tool-label">Phóng to</span>
                        <div class="pm-tool-icon-box">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Modal Body: Pages Grid (Matching Image 4) -->
            <div class="pm-body" id="pmBody">
                <div class="pm-grid" id="pmCardsGrid">
                    <!-- Dynamic Page Cards rendered by JS -->
                </div>
            </div>

            <!-- Modal Footer (Matching Image 4) -->
            <div class="pm-footer-pdfaid">
                <button type="button" class="btn-pm-cancel" onclick="closePageManagerModal()">Hủy bỏ</button>
                <button type="button" class="btn-pm-save" onclick="pmSaveAndApply()">Cứu</button>
            </div>
        </div>
    </div>

    <!-- MODAL: KÝ TÊN ĐIỆN TỬ (SIGNATURE - MATCHING IMAGE 2) -->
