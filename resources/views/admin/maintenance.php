<?php
/**
 * View Quản trị Cấu hình & Bảo trì Hệ thống (/admin/maintenance)
 * Giao diện tối giản, sử dụng 100% SVG Icon chuyên nghiệp, không emoji sticker
 */
?>
<div class="admin-page <?= $adminCurrentView === 'maintenance' ? 'active' : '' ?>" id="adminPage-maintenance">
    <div class="admin-maintenance-wrapper" style="display: flex; flex-direction: column; gap: 20px;">

        <!-- Header -->
        <div class="admin-card" style="padding: 20px 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h3 class="admin-card-title" style="margin: 0; font-size: 1.3rem; font-weight: 700;">Cấu hình & Bảo trì Hệ thống</h3>
                    <p class="admin-card-subtitle" style="margin: 4px 0 0; color: var(--text-muted); font-size: 0.88rem;">Giám sát tài nguyên máy chủ, dọn dẹp bộ nhớ đệm và quản lý trạng thái bảo trì toàn trang</p>
                </div>
                <button type="button" class="btn-outline" onclick="adminLoadMaintenanceStatus()" style="display: inline-flex; align-items: center; gap: 8px;">
                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    <span>Làm mới số liệu</span>
                </button>
            </div>
        </div>

        <!-- 1. DUNG LƯỢNG Ổ ĐĨA MÁY CHỦ & 4 THẺ CHỈ SỐ -->
        <div class="admin-card" style="padding: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                <div style="font-weight: 700; font-size: 1rem; display: flex; align-items: center; gap: 8px;">
                    <svg class="svg-icon svg-icon-sm" style="color: var(--primary);" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                    <span>Dung lượng Ổ đĩa Máy chủ (Server Storage)</span>
                </div>
                <span id="diskUsageLabel" style="font-size: 0.88rem; font-weight: 600; color: var(--text-muted);">Đang tính toán...</span>
            </div>

            <!-- Thanh tiến trình ổ đĩa -->
            <div class="maintenance-disk-bar-wrap" style="width: 100%; height: 12px; background: rgba(0,0,0,0.06); border-radius: 999px; overflow: hidden; margin-bottom: 24px;">
                <div id="maintenanceDiskBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #6366F1, #8B5CF6); border-radius: 999px; transition: width 0.6s ease;"></div>
            </div>

            <!-- 4 Thẻ chỉ số ngắn gọn (Icon SVG chuyên nghiệp, không emoji, không hiện đường dẫn) -->
            <div class="maintenance-metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px;">
                <!-- Card: Documents -->
                <div class="maint-stat-card" style="border: 1px solid var(--border-color); border-radius: 12px; padding: 14px 16px; background: rgba(99, 102, 241, 0.04);">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(99, 102, 241, 0.12); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <div style="font-size: 0.88rem; font-weight: 600; color: var(--text-main);">Tài liệu gốc</div>
                    </div>
                    <div id="statMaintDocs" style="font-size: 1.15rem; font-weight: 700; color: var(--text-main);">0 tệp • 0 MB</div>
                </div>

                <!-- Card: Exports -->
                <div class="maint-stat-card" style="border: 1px solid var(--border-color); border-radius: 12px; padding: 14px 16px; background: rgba(16, 185, 129, 0.04);">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(16, 185, 129, 0.12); color: #10B981; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 11 17 15 13"/></svg>
                        </div>
                        <div style="font-size: 0.88rem; font-weight: 600; color: var(--text-main);">Tệp đã xuất</div>
                    </div>
                    <div id="statMaintExports" style="font-size: 1.15rem; font-weight: 700; color: var(--text-main);">0 tệp • 0 MB</div>
                </div>

                <!-- Card: Previews Cache -->
                <div class="maint-stat-card" style="border: 1px solid var(--border-color); border-radius: 12px; padding: 14px 16px; background: rgba(245, 158, 11, 0.04);">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(245, 158, 11, 0.12); color: #F59E0B; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </div>
                        <div style="font-size: 0.88rem; font-weight: 600; color: var(--text-main);">Ảnh xem trước</div>
                    </div>
                    <div id="statMaintPreviews" style="font-size: 1.15rem; font-weight: 700; color: var(--text-main);">0 ảnh • 0 MB</div>
                </div>

                <!-- Card: Temp Storage -->
                <div class="maint-stat-card" style="border: 1px solid var(--border-color); border-radius: 12px; padding: 14px 16px; background: rgba(100, 116, 139, 0.04);">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(100, 116, 139, 0.12); color: #64748B; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <div style="font-size: 0.88rem; font-weight: 600; color: var(--text-main);">Tệp tạm dở dang</div>
                    </div>
                    <div id="statMaintTemp" style="font-size: 1.15rem; font-weight: 700; color: var(--text-main);">0 tệp • 0 MB</div>
                </div>
            </div>
        </div>

        <!-- 2. CÔNG CỤ DỌN DẸP BỘ NHỚ LƯU TRỮ (2 THẺ HÀNH ĐỘNG GỌN GÀNG) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px;">
            <!-- Action Card 1: Orphan Cleanup -->
            <div class="admin-card" style="padding: 20px; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                        <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(99, 102, 241, 0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </div>
                        <div>
                            <h4 style="margin: 0; font-size: 0.98rem; font-weight: 700;">Dọn dẹp Tệp Rác</h4>
                            <span style="font-size: 0.78rem; color: var(--text-muted);">Quét file vật lý không còn trong CSDL</span>
                        </div>
                    </div>
                    <div style="font-size: 0.84rem; color: var(--text-muted); margin: 8px 0 14px;">
                        Phát hiện: <strong id="maintOrphanBadge" style="color: var(--primary);">0 tệp rác (0 MB)</strong>
                    </div>
                </div>
                <div>
                    <div id="adminCleanupResult" style="display: none; padding: 10px 12px; border-radius: 8px; background: rgba(22, 163, 74, 0.1); color: #16A34A; font-size: 0.82rem; margin-bottom: 12px; font-weight: 600;"></div>
                    <button type="button" class="btn-primary" id="btnAdminCleanup" onclick="adminRunCleanup()" style="width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        <span>Quét & Dọn Dẹp</span>
                    </button>
                </div>
            </div>

            <!-- Action Card 2: Preview Cache Cleaner -->
            <div class="admin-card" style="padding: 20px; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                        <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(245, 158, 11, 0.1); color: #F59E0B; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                        </div>
                        <div>
                            <h4 style="margin: 0; font-size: 0.98rem; font-weight: 700;">Dọn dẹp Bộ nhớ tạm Xem trước</h4>
                            <span style="font-size: 0.78rem; color: var(--text-muted);">Xóa ảnh thumbnail tạm (sẽ tự tạo lại khi cần)</span>
                        </div>
                    </div>
                    <div style="font-size: 0.84rem; color: var(--text-muted); margin: 8px 0 14px;">
                        Trạng thái: <strong id="maintPreviewCacheBadge" style="color: #F59E0B;">0 tệp cache tạm (0 MB)</strong>
                    </div>
                </div>
                <div>
                    <div id="adminCacheResult" style="display: none; padding: 10px 12px; border-radius: 8px; background: rgba(22, 163, 74, 0.1); color: #16A34A; font-size: 0.82rem; margin-bottom: 12px; font-weight: 600;"></div>
                    <button type="button" class="btn-outline" id="btnAdminCleanCache" onclick="adminRunPreviewCleanup()" style="width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        <span>Xóa Bộ Nhớ Tạm Xem Trước</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 3. CHẾ ĐỘ BẢO TRÌ HỆ THỐNG -->
        <div class="admin-card" id="maintModeCard" style="padding: 24px; border: 1px solid var(--border-color); border-radius: 14px;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(239, 68, 68, 0.1); color: #EF4444; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </div>
                    <div>
                        <h4 style="margin: 0; font-size: 1.05rem; font-weight: 700;">Chế độ Bảo trì Hệ thống</h4>
                        <span style="font-size: 0.8rem; color: var(--text-muted);">Kiểm soát truy cập toàn hệ thống khi tiến hành nâng cấp & bảo trì</span>
                    </div>
                </div>

                <!-- Status Badge -->
                <span id="maintStatusBadge" class="header-stat-pill pill-active" style="font-size: 0.85rem; padding: 6px 14px;">
                    ● Hệ thống Hoạt Động Bình Thường (Live)
                </span>
            </div>

            <!-- Switch Bật/Tắt -->
            <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 16px; padding: 12px 16px; background: rgba(0,0,0,0.02); border-radius: 10px;">
                <label class="switch-toggle" style="position: relative; display: inline-block; width: 44px; height: 24px; margin: 0; cursor: pointer;">
                    <input type="checkbox" id="maintModeToggle" style="opacity: 0; width: 0; height: 0;">
                    <span class="switch-slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #CBD5E1; transition: .3s; border-radius: 24px;"></span>
                </label>
                <div>
                    <label for="maintModeToggle" style="font-weight: 600; font-size: 0.9rem; cursor: pointer; display: block;">Kích hoạt Chế độ Bảo trì</label>
                    <span style="font-size: 0.78rem; color: var(--text-muted);">Khi BẬT: Người dùng khách sẽ nhận được trang thông báo bảo trì. Quản trị viên (Admin) vẫn thao tác bình thường.</span>
                </div>
            </div>

            <!-- Lời nhắn bảo trì -->
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; display: block;">Thông điệp bảo trì hiển thị cho người dùng:</label>
                <textarea id="maintModeMessage" class="auth-input" rows="2" placeholder="Nhập thông điệp bảo trì..." style="resize: vertical; font-family: inherit; font-size: 0.88rem;"></textarea>
            </div>

            <!-- Nút lưu cài đặt -->
            <div style="display: flex; justify-content: flex-end;">
                <button type="button" class="btn-primary" id="btnSaveMaintMode" onclick="adminSaveMaintenanceMode()" style="display: inline-flex; align-items: center; gap: 8px;">
                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <span>Lưu Cài Đặt Bảo Trì</span>
                </button>
            </div>
        </div>

    </div>
</div>
