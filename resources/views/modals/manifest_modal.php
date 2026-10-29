    <div id="manifestModal" class="modal-overlay">
        <div class="modal-box modal-lg">
            <div class="modal-header">
                <div>
                    <h3 id="manifestModalTitle">Bảng đối chiếu trang nguồn (Source Manifest)</h3>
                    <p style="font-size: 0.75rem; color: var(--text-muted);">Xác minh từng trang đầu ra lấy từ tệp gốc và trang gốc nào</p>
                </div>
                <button class="btn-close" onclick="closeModal('manifestModal')">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Trang kết quả</th>
                        <th>Tệp nguồn gốc</th>
                        <th>Trang gốc</th>
                        <th>Nhãn nhận diện</th>
                        <th>Góc xoay</th>
                    </tr>
                </thead>
                <tbody id="manifestTableBody"></tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- MODAL: ĐĂNG NHẬP & ĐĂNG KÝ (AUTHENTICATION) -->
