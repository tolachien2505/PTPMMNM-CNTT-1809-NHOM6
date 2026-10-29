<?php
/**
 * View Giao diện Khách hàng khi Chế độ Bảo trì được kích hoạt
 * Thiết kế sang trọng, chuẩn SaaS, 100% SVG Icons, không emoji/sticker
 */
?>
<div class="client-maintenance-screen" id="clientMaintenanceScreen">
    <div class="client-maintenance-card">
        <!-- Logo & Brand Header -->
        <div class="maint-brand-header">
            <a href="/" class="maint-logo-link">
                <img src="/img/logo_light.png" alt="PDF Online" class="maint-logo logo-light">
                <img src="/img/logo_dark.png" alt="PDF Online" class="maint-logo logo-dark">
            </a>
            <div class="maint-status-pill">
                <span class="maint-pulse-dot"></span>
                <span>BẢO TRÌ HỆ THỐNG</span>
            </div>
        </div>

        <!-- Central Glowing Icon -->
        <div class="maint-icon-wrapper">
            <div class="maint-icon-halo"></div>
            <div class="maint-icon-inner">
                <svg class="maint-svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="2" width="20" height="8" rx="2" ry="2"/>
                    <rect x="2" y="14" width="20" height="8" rx="2" ry="2"/>
                    <line x1="6" y1="6" x2="6.01" y2="6"/>
                    <line x1="6" y1="18" x2="6.01" y2="18"/>
                    <path d="M13 6h5"/>
                    <path d="M13 18h5"/>
                </svg>
            </div>
        </div>

        <!-- Heading & Message -->
        <div class="maint-text-content">
            <h1 class="maint-title">Hệ Thống Đang Bảo Trì</h1>
            <p class="maint-description" id="maintPublicMessage">
                <?= htmlspecialchars($maintMessage ?? 'Hệ thống đang tiến hành nâng cấp & bảo trì định kỳ nhằm tối ưu hóa hiệu năng xử lý PDF. Quý khách vui lòng quay lại sau ít phút!') ?>
            </p>
        </div>

        <!-- Service Status Checkpoints (No Emojis - Only SVG Icons) -->
        <div class="maint-checkpoints-grid">
            <div class="maint-checkpoint-item">
                <div class="chk-icon-box chk-blue">
                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div class="chk-text-info">
                    <span class="chk-label">Máy chủ</span>
                    <strong class="chk-val">Đang tối ưu</strong>
                </div>
            </div>

            <div class="maint-checkpoint-item">
                <div class="chk-icon-box chk-green">
                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <div class="chk-text-info">
                    <span class="chk-label">Dữ liệu tệp</span>
                    <strong class="chk-val">An toàn 100%</strong>
                </div>
            </div>

            <div class="maint-checkpoint-item">
                <div class="chk-icon-box chk-purple">
                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="chk-text-info">
                    <span class="chk-label">Dự kiến</span>
                    <strong class="chk-val">Sắp hoàn tất</strong>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="maint-actions-row">
            <button type="button" class="btn-maint-refresh" onclick="window.location.reload()">
                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                <span>Kiểm tra lại trạng thái</span>
            </button>

            <button type="button" class="btn-maint-admin-login" onclick="openModal('authModal')">
                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <span>Đăng nhập Quản trị viên</span>
            </button>
        </div>

        <!-- Footer Notice -->
        <div class="maint-footer-note">
            <span>PDF Online Cloud Engine • Nền tảng quản lý & xử lý tệp PDF trực tuyến</span>
        </div>
    </div>
</div>
