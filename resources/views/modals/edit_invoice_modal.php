    <!-- MODAL: CHỈNH SỬA HÓA ĐƠN NẠP TIỀN (LUXURY SAAS DESIGN) -->
    <div id="editInvoiceModal" class="modal-overlay">
        <div class="modal-box modal-edit-invoice">
            <!-- Modal Header -->
            <div class="modal-header">
                <div class="modal-header-info">
                    <div class="modal-header-icon-box">
                        <svg class="svg-icon" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                    </div>
                    <div>
                        <h3 class="modal-title">Chỉnh sửa hóa đơn</h3>
                        <p class="modal-subtitle" id="editInvSub">
                            <span class="badge-sub-id">Hóa đơn #<span id="editInvIdBadge">1</span></span>
                        </p>
                    </div>
                </div>
                <button type="button" class="modal-close-icon-btn" onclick="closeModal('editInvoiceModal')" title="Đóng cửa sổ">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <!-- Form -->
            <form id="formEditInvoice" onsubmit="saveEditInvoice(event)">
                <input type="hidden" id="editInvId">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="editInvAmount">
                            <span>Số tiền thanh toán</span>
                            <span class="text-danger">*</span>
                        </label>
                        <div class="input-with-suffix">
                            <input type="number" id="editInvAmount" class="form-control font-mono" required min="0" step="1000" placeholder="99000">
                            <span class="input-suffix-tag">VNĐ</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="editInvStatus">
                            <span>Trạng thái giao dịch</span>
                            <span class="text-danger">*</span>
                        </label>
                        <div class="custom-select-wrapper">
                            <select id="editInvStatus" class="form-control">
                                <option value="1">✓ Đã thanh toán (Thành công)</option>
                                <option value="0">⏱ Chờ thanh toán (Đang chờ nạp)</option>
                                <option value="2">✕ Thất bại (Đã hủy giao dịch)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn-cancel-edit" onclick="closeModal('editInvoiceModal')">Hủy</button>
                    <button type="submit" class="btn-save-edit" id="btnSaveInvoice">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Lưu cập nhật</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
