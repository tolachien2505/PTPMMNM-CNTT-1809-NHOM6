<?php
/**
 * View Quản trị Cấp bậc Gói dịch vụ (/admin/plans)
 * Thiết kế giao diện tinh gọn (FREE & PRO) và Modal Popup chuẩn SaaS
 */
?>
<div class="admin-page <?= $adminCurrentView === 'plans' ? 'active' : '' ?>" id="adminPage-plans">
    <div class="admin-card plans-management-card">
        <!-- Header -->
        <div class="admin-card-header plans-page-header">
            <div>
                <h3 class="admin-card-title">Cấp bậc gói dịch vụ</h3>
                <p class="admin-card-subtitle">Quản lý các gói tài khoản, định mức lưu trữ và phân quyền tính năng hệ thống PDF Online</p>
            </div>
            <div class="plans-header-actions">
                <div class="admin-header-badges">
                    <span class="header-stat-pill pill-total">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M20 7h-7L10 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/></svg>
                        <strong id="plansTotalCount">2</strong> Gói
                    </span>
                    <span class="header-stat-pill pill-active">
                        <strong id="plansActiveCount">2</strong> Đang bật
                    </span>
                    <span class="header-stat-pill pill-popular" style="display: inline-flex; align-items: center; gap: 4px;">
                        <svg class="svg-icon svg-icon-xs" style="color: #D97706;" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        <strong id="plansPopularName">Chuyên nghiệp (PRO)</strong>
                    </span>
                </div>
                <button type="button" class="btn-primary" onclick="adminOpenPlanModal(null)" style="display: inline-flex; align-items: center; gap: 8px;">
                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>Thêm gói mới</span>
                </button>
            </div>
        </div>

        <!-- Table Danh sách Gói (Theo bố cục ảnh tham khảo) -->
        <div class="table-responsive plans-table-wrap">
            <table class="admin-table admin-plans-table">
                <thead>
                    <tr>
                        <th style="min-width: 140px;">GÓI</th>
                        <th style="min-width: 110px;">GIÁ</th>
                        <th style="min-width: 90px;">THỜI HẠN</th>
                        <th style="min-width: 70px;">THỨ BẬC</th>
                        <th style="min-width: 120px;">LƯU TRỮ</th>
                        <th style="min-width: 100px;">TỆP TỐI ĐA</th>
                        <th style="min-width: 110px;">TRANG/TỆP</th>
                        <th style="min-width: 260px;">TÍNH NĂNG</th>
                        <th style="min-width: 100px; text-align: center;">TRẠNG THÁI</th>
                        <th style="min-width: 90px; text-align: right;">THAO TÁC</th>
                    </tr>
                </thead>
                <tbody id="adminPlansTableBody">
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 32px; color: var(--text-muted);">
                            <div class="spinner-border spinner-border-sm" role="status"></div> Đang tải dữ liệu gói dịch vụ...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Popup Thêm / Sửa Gói Dịch Vụ (True Modal Overlay) -->
<div id="adminPlanModal" class="modal-overlay" onclick="if(event.target === this) adminClosePlanModal();">
    <div class="modal-box modal-edit-plan" style="width: 760px; max-width: 94vw; max-height: 90vh; padding: 0; border-radius: 16px; overflow: hidden; display: flex; flex-direction: column;">
        <!-- Header -->
        <div class="modal-header" style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; background: var(--bg-card);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(99, 102, 241, 0.12); color: var(--primary); display: flex; align-items: center; justify-content: center;">
                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <div>
                    <h3 class="modal-title" id="adminPlanModalTitle" style="margin: 0; font-size: 1.2rem; font-weight: 700;">Sửa gói</h3>
                    <p style="margin: 2px 0 0; font-size: 0.78rem; color: var(--text-muted);">Cấu hình tài nguyên và phân quyền chức năng PDF Online</p>
                </div>
            </div>
            <button type="button" class="modal-close-icon-btn" onclick="adminClosePlanModal()" title="Đóng cửa sổ" style="background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 6px; border-radius: 6px;">
                <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Body with Form -->
        <div style="padding: 24px; overflow-y: auto; flex: 1;">
            <form id="adminPlanForm" onsubmit="adminSavePlan(event)">
                <input type="hidden" id="planFormId" value="0">

                <!-- Hàng 1: Mã gói, Tên hiển thị, Giá -->
                <div class="plan-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 18px;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; display: block;">Mã gói (Code)</label>
                        <input type="text" id="planFormCode" class="auth-input" placeholder="VD: FREE, PRO..." required style="text-transform: uppercase;">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; display: block;">Tên hiển thị</label>
                        <input type="text" id="planFormName" class="auth-input" placeholder="VD: Miễn phí, Chuyên nghiệp..." required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; display: block;">Giá bán (VNĐ)</label>
                        <input type="number" id="planFormPrice" class="auth-input" placeholder="0" min="0" step="1000" required>
                    </div>
                </div>

                <!-- Hàng 2: Thời hạn ngày, Thứ bậc nâng cấp, Dung lượng lưu trữ -->
                <div class="plan-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 18px;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; display: block;">Thời hạn ngày (0 = vĩnh viễn)</label>
                        <input type="number" id="planFormDuration" class="auth-input" placeholder="30" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; display: block;">Thứ bậc nâng cấp (Tier)</label>
                        <input type="number" id="planFormTier" class="auth-input" placeholder="10" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; display: block;">Dung lượng lưu trữ (MB, -1: Không GH)</label>
                        <input type="number" id="planFormStorage" class="auth-input" placeholder="100" required>
                    </div>
                </div>

                <!-- Hàng 3: Dung lượng 1 tệp, Số trang/tệp, Độ phân giải DPI -->
                <div class="plan-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 18px;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; display: block;">Tệp tối đa (MB / tệp)</label>
                        <input type="number" id="planFormUploadSize" class="auth-input" placeholder="50" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; display: block;">Số trang tối đa / tệp (-1: Không GH)</label>
                        <input type="number" id="planFormPages" class="auth-input" placeholder="150" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; display: block;">Độ phân giải xuất tối đa</label>
                        <select id="planFormDpi" class="auth-input">
                            <option value="150">150 DPI (Tiêu chuẩn)</option>
                            <option value="200">200 DPI (Sắc nét)</option>
                            <option value="300">300 DPI (In ấn cao cấp)</option>
                        </select>
                    </div>
                </div>

                <!-- Hàng 4: Số lần xuất/ngày, Huy hiệu badge, Thứ tự -->
                <div class="plan-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 18px;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; display: block;">Xuất PDF / ngày (-1: Không GH)</label>
                        <input type="number" id="planFormExports" class="auth-input" placeholder="50" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; display: block;">Huy hiệu nổi bật (Tùy chọn)</label>
                        <input type="text" id="planFormBadge" class="auth-input" placeholder="VD: Phổ biến nhất, Khuyên dùng...">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; display: block;">Thứ tự hiển thị</label>
                        <input type="number" id="planFormSort" class="auth-input" placeholder="1" min="0" value="1">
                    </div>
                </div>

                <!-- Hàng 5: Tính năng mô tả (Mỗi dòng một tính năng) -->
                <div class="form-group" style="margin-bottom: 18px;">
                    <label class="form-label" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; display: block;">Mô tả tính năng hiển thị (Mỗi dòng một tính năng):</label>
                    <textarea id="planFormFeaturesText" class="auth-input" rows="4" placeholder="VD:&#10;Lưu trữ không giới hạn&#10;Xuất PDF sắc nét 300 DPI&#10;Bảo mật tài liệu cao cấp" style="resize: vertical; font-family: inherit; font-size: 0.85rem; line-height: 1.5;"></textarea>
                </div>

                <!-- Hàng 6: Khối tính năng cho phép (Bám sát bố cục ảnh Screenshot 4) -->
                <div class="plan-allowed-features-card" style="border: 1px solid var(--border-color); border-radius: 12px; padding: 16px; background: rgba(0,0,0,0.02); margin-bottom: 20px;">
                    <div style="margin-bottom: 12px;">
                        <strong style="font-size: 0.88rem;">Tính năng cho phép</strong>
                        <span style="font-size: 0.78rem; color: var(--text-muted); margin-left: 8px;">Bật / tắt nhóm tính năng — user gói này không bật được tính năng tắt.</span>
                    </div>

                    <div class="plan-feature-checkboxes-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 14px;">
                        <label class="feature-checkbox-tile">
                            <input type="checkbox" id="featCompress">
                            <span>Nén PDF cao cấp</span>
                        </label>
                        <label class="feature-checkbox-tile">
                            <input type="checkbox" id="featManifest">
                            <span>Source Manifest</span>
                        </label>
                        <label class="feature-checkbox-tile">
                            <input type="checkbox" id="featWatermark">
                            <span>Đóng dấu Watermark</span>
                        </label>
                        <label class="feature-checkbox-tile">
                            <input type="checkbox" id="featPriority">
                            <span>Ưu tiên xử lý</span>
                        </label>
                        <label class="feature-checkbox-tile">
                            <input type="checkbox" id="featBatch">
                            <span>Xuất hàng loạt</span>
                        </label>
                    </div>

                    <div style="display: flex; gap: 24px; flex-wrap: wrap; padding-top: 10px; border-top: 1px dashed var(--border-color);">
                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 500;">
                            <input type="checkbox" id="featApi">
                            <span>Quyền truy cập API</span>
                        </label>
                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--primary);">
                            <input type="checkbox" id="planFormIsActive" checked>
                            <span>Đang bật (Kích hoạt gói)</span>
                        </label>
                    </div>
                </div>

                <!-- Footer Buttons -->
                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                    <button type="button" class="btn-secondary" onclick="adminClosePlanModal()" style="padding: 10px 24px; border-radius: 8px;">Hủy</button>
                    <button type="submit" class="btn-primary" id="btnAdminSavePlan" style="padding: 10px 28px; border-radius: 8px; font-weight: 600;">Lưu gói</button>
                </div>
            </form>
        </div>
    </div>
</div>
