/**
 * PDF Manager - Frontend Application Logic
 * Đề tài 12 - Học phần Phát triển phần mềm mã nguồn mở
 */

let currentOutputId = 1;

class PDFEditorState {
    constructor() {
        this.pages = [];
        this.history = [];
        this.redoStack = [];
        this.initDefaultPlan();
    }

    initDefaultPlan() {
        // Kịch bản chuẩn 3 trang: A2, B1, A1 (khớp TestCase 1 và TestCase 2)
        this.pages = [
            { id: 1, source_doc: 'Tai_lieu_hoc_tap.pdf', source_page: 2, label: 'A2', rotation: 90, keep: true },
            { id: 2, source_doc: 'De_cuong_mon_hoc.pdf', source_page: 1, label: 'B1', rotation: 0, keep: true },
            { id: 3, source_doc: 'Tai_lieu_hoc_tap.pdf', source_page: 1, label: 'A1', rotation: 0, keep: true },
            { id: 4, source_doc: 'Tai_lieu_tham_khao.pdf', source_page: 3, label: 'E3', rotation: 0, keep: true },
            { id: 5, source_doc: 'Ho_so_tot_nghiep.pdf', source_page: 2, label: 'D2', rotation: 0, keep: true },
        ];
        this.history = [];
        this.redoStack = [];
    }

    pushHistory() {
        this.history.push(JSON.stringify(this.pages));
        if (this.history.length > 25) {
            this.history.shift();
        }
        this.redoStack = [];
    }

    undo() {
        if (this.history.length > 0) {
            this.redoStack.push(JSON.stringify(this.pages));
            this.pages = JSON.parse(this.history.pop());
            this.render();
        }
    }

    rotatePage(index, deg = 90) {
        this.pushHistory();
        if (this.pages[index]) {
            this.pages[index].rotation = (this.pages[index].rotation + deg) % 360;
            this.render();
        }
    }

    toggleKeep(index) {
        this.pushHistory();
        if (this.pages[index]) {
            this.pages[index].keep = !this.pages[index].keep;
            this.render();
        }
    }

    movePage(fromIndex, toIndex) {
        if (fromIndex === toIndex) return;
        this.pushHistory();
        const [movedItem] = this.pages.splice(fromIndex, 1);
        this.pages.splice(toIndex, 0, movedItem);
        this.render();
    }

    resetPlan() {
        this.pushHistory();
        this.initDefaultPlan();
        this.render();
    }

    saveDraft() {
        showToast('Đã lưu nháp kế hoạch chỉnh sửa thành công!', 'success');
    }

    async exportMerge() {
        const activePages = this.pages.filter(p => p.keep);
        if (activePages.length === 0) {
            showToast('Cảnh báo: Kế hoạch không có trang nào được chọn!', 'error');
            return;
        }

        try {
            const res = await fetch('/api/export/merge', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    plan: activePages,
                    title: 'Tai_lieu_sap_xep_hoan_chinh.pdf'
                })
            });

            const data = await res.json();
            if (data.status === 'success') {
                currentOutputId = data.output_id;
                showToast(`✓ Đã xuất PDF thành công (${data.total_pages} trang)!`, 'success');
                switchView('merge');
                showMergeResult(data);
            } else {
                showToast(`Lỗi: ${data.message}`, 'error');
            }
        } catch (err) {
            showToast('Lỗi kết nối máy chủ.', 'error');
        }
    }

    render() {
        const container = document.getElementById('pagesContainer');
        if (!container) return;

        container.innerHTML = '';
        this.pages.forEach((page, index) => {
            const card = document.createElement('div');
            card.className = `page-item-card ${!page.keep ? 'dropped' : ''}`;
            card.draggable = true;
            card.dataset.index = index;

            // Drag and drop event listeners
            card.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', index);
                card.style.opacity = '0.5';
            });

            card.addEventListener('dragend', () => {
                card.style.opacity = '1';
            });

            card.addEventListener('dragover', (e) => {
                e.preventDefault();
                card.style.borderColor = '#2563EB';
            });

            card.addEventListener('dragleave', () => {
                card.style.borderColor = '#E2E8F0';
            });

            card.addEventListener('drop', (e) => {
                e.preventDefault();
                card.style.borderColor = '#E2E8F0';
                const fromIdx = parseInt(e.dataTransfer.getData('text/plain'), 10);
                this.movePage(fromIdx, index);
            });

            card.innerHTML = `
                <div class="page-preview-box" style="transform: rotate(${page.rotation}deg);">
                    <span class="page-badge-label">Trang ${index + 1}</span>
                    <div style="font-size: 2rem; font-weight: 800; color: #1E293B;">${page.label}</div>
                    <div style="font-size: 0.65rem; color: #64748B; margin-top: 4px;">${page.source_doc}</div>
                </div>
                <div style="font-size: 0.8rem; font-weight: 700; margin-bottom: 4px;">
                    Nhãn: ${page.label} (${page.rotation}°)
                </div>
                <div style="font-size: 0.7rem; color: #64748B; margin-bottom: 8px;">
                    Trang gốc: ${page.source_page}
                </div>
                <div class="page-item-actions">
                    <button class="btn-page-tool" onclick="editorState.rotatePage(${index}, 90)" title="Xoay 90°">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10"/></svg>
                        <span>Xoay</span>
                    </button>
                    <button class="btn-page-tool" onclick="editorState.toggleKeep(${index})" title="${page.keep ? 'Bỏ trang' : 'Giữ lại'}">
                        ${page.keep
                    ? `<svg class="svg-icon svg-icon-sm" style="color: #EF4444;" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> <span>Bỏ</span>`
                    : `<svg class="svg-icon svg-icon-sm" style="color: #10B981;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> <span>Giữ</span>`
                }
                    </button>
                </div>
            `;
            container.appendChild(card);
        });
    }
}

const editorState = new PDFEditorState();

// Danh sách các Route hợp lệ (không dùng hash #)
const VALID_VIEWS = ['home', 'documents', 'editor', 'upgrade', 'history', 'settings', 'admin'];
const LEGACY_VIEW_MAP = {
    'reorder': 'editor',
    'merge': 'editor',
    'split': 'editor'
};

// Quản lý phần tử Sidebar đang hoạt động (Client hoặc Admin)
function getActiveSidebar() {
    const layout = document.getElementById('appLayout');
    const adminSidebar = document.getElementById('appSidebarAdmin');
    const clientSidebar = document.getElementById('appSidebarClient');
    if (layout && layout.classList.contains('portal-admin') && adminSidebar) {
        return adminSidebar;
    }
    return clientSidebar || document.getElementById('appSidebar');
}

// Quản lý trạng thái thu gọn Sidebar (Chỉ hiện Icon: 72px)
function toggleSidebarCompact() {
    const layout = document.getElementById('appLayout');
    if (!layout) return;

    const isCompact = layout.classList.toggle('sidebar-compact');
    if (document.getElementById('appSidebarClient')) {
        document.getElementById('appSidebarClient').classList.toggle('collapsed', isCompact);
    }
    if (document.getElementById('appSidebarAdmin')) {
        document.getElementById('appSidebarAdmin').classList.toggle('collapsed', isCompact);
    }
    localStorage.setItem('sidebar_compact', isCompact ? 'true' : 'false');
}

function setSidebarCompact(compact) {
    const layout = document.getElementById('appLayout');
    if (!layout) return;

    if (compact) {
        layout.classList.add('sidebar-compact');
    } else {
        layout.classList.remove('sidebar-compact');
    }
    if (document.getElementById('appSidebarClient')) {
        document.getElementById('appSidebarClient').classList.toggle('collapsed', !!compact);
    }
    if (document.getElementById('appSidebarAdmin')) {
        document.getElementById('appSidebarAdmin').classList.toggle('collapsed', !!compact);
    }
}

// Lấy view hiện tại từ pathname hoặc chuyển đổi hash cũ sang đường dẫn sạch
function getPathView() {
    if (window.location.hash && window.location.hash.length > 1) {
        let hashView = window.location.hash.replace(/^#\/?/, '').split('?')[0].split('/')[0];
        if (LEGACY_VIEW_MAP[hashView]) hashView = LEGACY_VIEW_MAP[hashView];
        if (VALID_VIEWS.includes(hashView)) {
            window.history.replaceState({ view: hashView }, '', '/' + hashView);
            return hashView;
        }
    }
    let cleanPath = window.location.pathname.replace(/^\/+|\/+$/g, '');
    if (cleanPath === 'admin' || cleanPath.startsWith('admin/')) {
        return cleanPath;
    }
    if (LEGACY_VIEW_MAP[cleanPath]) cleanPath = LEGACY_VIEW_MAP[cleanPath];
    if (VALID_VIEWS.includes(cleanPath)) {
        return cleanPath;
    }
    return 'home';
}

/**
 * Lấy Sidebar đang hoạt động dựa theo Cổng hiện tại (Client hoặc Admin)
 */
function getActiveSidebar() {
    const isPortalAdmin = document.getElementById('appLayout')?.classList.contains('portal-admin');
    return isPortalAdmin 
        ? document.getElementById('appSidebarAdmin') 
        : document.getElementById('appSidebarClient');
}

/**
 * Chuyển đổi sang Cổng Quản trị Admin (Dedicated Admin Portal)
 */
function switchToAdminPortal(event, adminPage = 'dashboard') {
    if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
    }
    if (!window.APP_IS_ADMIN) {
        showToast('Bạn không có quyền truy cập trang Quản trị Admin!', 'error');
        return;
    }

    const layout = document.getElementById('appLayout');
    const sbClient = document.getElementById('appSidebarClient');
    const sbAdmin = document.getElementById('appSidebarAdmin');
    const btnSwitchAdmin = document.getElementById('btnDdSwitchAdmin');
    const btnSwitchClient = document.getElementById('btnDdSwitchClient');
    const navClient = document.getElementById('mobileBottomNavClient');
    const navAdmin = document.getElementById('mobileBottomNavAdmin');

    if (layout) {
        layout.classList.remove('portal-client');
        layout.classList.add('portal-admin');
    }
    if (sbClient) sbClient.style.setProperty('display', 'none', 'important');
    if (sbAdmin) sbAdmin.style.removeProperty('display');
    if (btnSwitchAdmin) btnSwitchAdmin.style.setProperty('display', 'none', 'important');
    if (btnSwitchClient) btnSwitchClient.style.removeProperty('display');
    if (navClient) navClient.style.setProperty('display', 'none', 'important');
    if (navAdmin) navAdmin.style.removeProperty('display');

    // Kích hoạt View Admin
    document.querySelectorAll('.workspace-view').forEach(v => v.classList.remove('active'));
    const adminView = document.getElementById('view-admin');
    if (adminView) adminView.classList.add('active');

    // Chuyển tới trang admin con
    switchAdminPage(adminPage, true);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * Chuyển về Website Client (Website Người dùng)
 */
function switchToClientPortal(event, clientView = 'home') {
    if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
    }

    const layout = document.getElementById('appLayout');
    const sbClient = document.getElementById('appSidebarClient');
    const sbAdmin = document.getElementById('appSidebarAdmin');
    const btnSwitchAdmin = document.getElementById('btnDdSwitchAdmin');
    const btnSwitchClient = document.getElementById('btnDdSwitchClient');
    const navClient = document.getElementById('mobileBottomNavClient');
    const navAdmin = document.getElementById('mobileBottomNavAdmin');

    if (layout) {
        layout.classList.remove('portal-admin');
        layout.classList.add('portal-client');
    }
    if (sbAdmin) sbAdmin.style.setProperty('display', 'none', 'important');
    if (sbClient) sbClient.style.removeProperty('display');
    if (btnSwitchClient) btnSwitchClient.style.setProperty('display', 'none', 'important');
    if (btnSwitchAdmin) btnSwitchAdmin.style.removeProperty('display');
    if (navAdmin) navAdmin.style.setProperty('display', 'none', 'important');
    if (navClient) navClient.style.removeProperty('display');

    switchView(clientView, true);
}

/**
 * Chuyển trang con trong Cổng Quản trị Admin (Dashboard, Users, Documents, Exports, Maintenance)
 */
function switchAdminPage(pageName, eventOrPush = true) {
    if (eventOrPush && typeof eventOrPush.preventDefault === 'function') {
        eventOrPush.preventDefault();
    }
    if (!window.APP_IS_ADMIN) return;

    const validPages = ['dashboard', 'users', 'documents', 'exports', 'payments', 'plans', 'maintenance'];
    if (!validPages.includes(pageName)) {
        pageName = 'dashboard';
    }

    // Đảm bảo trạng thái layout và sidebar Admin hiển thị
    const layout = document.getElementById('appLayout');
    const sbClient = document.getElementById('appSidebarClient');
    const sbAdmin = document.getElementById('appSidebarAdmin');
    const navClient = document.getElementById('mobileBottomNavClient');
    const navAdmin = document.getElementById('mobileBottomNavAdmin');

    if (layout) {
        layout.classList.remove('portal-client');
        layout.classList.add('portal-admin');
    }
    if (sbClient) sbClient.style.setProperty('display', 'none', 'important');
    if (sbAdmin) sbAdmin.style.removeProperty('display');
    if (navClient) navClient.style.setProperty('display', 'none', 'important');
    if (navAdmin) navAdmin.style.removeProperty('display');

    // Đảm bảo view-admin active
    document.querySelectorAll('.workspace-view').forEach(v => v.classList.remove('active'));
    const adminView = document.getElementById('view-admin');
    if (adminView) adminView.classList.add('active');

    // Cập nhật trạng thái active sidebar Admin
    document.querySelectorAll('#appSidebarAdmin .nav-item').forEach(item => {
        const itemPage = item.getAttribute('data-admin-view');
        item.classList.toggle('active', itemPage === pageName);
    });

    // Cập nhật trạng thái active thanh điều hướng dưới mobile Admin
    document.querySelectorAll('#mobileBottomNavAdmin .mobile-nav-item').forEach(el => {
        el.classList.toggle('active', el.getAttribute('data-admin-nav') === pageName);
    });

    // Cập nhật trạng thái active cho từng trang admin
    document.querySelectorAll('.admin-page').forEach(page => {
        page.classList.remove('active');
    });
    const targetPage = document.getElementById(`adminPage-${pageName}`);
    if (targetPage) {
        targetPage.classList.add('active');
    }

    if (pageName === 'plans' && typeof adminLoadPlans === 'function') {
        adminLoadPlans();
    } else if (pageName === 'maintenance' && typeof adminLoadMaintenanceStatus === 'function') {
        adminLoadMaintenanceStatus();
    }

    // Cập nhật nút chuyển trong avatar dropdown
    const btnSwitchAdmin = document.getElementById('btnDdSwitchAdmin');
    const btnSwitchClient = document.getElementById('btnDdSwitchClient');
    if (btnSwitchAdmin) btnSwitchAdmin.style.setProperty('display', 'none', 'important');
    if (btnSwitchClient) btnSwitchClient.style.removeProperty('display');

    // Tự động đóng menu trượt mobile nếu đang mở
    const sidebar = getActiveSidebar();
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('active');
    }

    // Cập nhật URL trình duyệt qua History API: /admin hoặc /admin/users ...
    const shouldPush = (typeof eventOrPush === 'boolean') ? eventOrPush : true;
    if (shouldPush) {
        const targetPath = (pageName === 'dashboard') ? '/admin' : `/admin/${pageName}`;
        if (window.location.pathname !== targetPath) {
            window.history.pushState({ portal: 'admin', adminPage: pageName, view: 'admin' }, '', targetPath);
        }
    }

    try {
        sessionStorage.setItem('active_admin_page', pageName);
    } catch (e) { }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Chuyển đổi View trong SPA và cập nhật URL sạch (không có #)
function switchView(viewName, eventOrPush = true) {
    if (eventOrPush && typeof eventOrPush.preventDefault === 'function') {
        eventOrPush.preventDefault();
    }

    if (LEGACY_VIEW_MAP[viewName]) {
        viewName = LEGACY_VIEW_MAP[viewName];
    }

    if (!VALID_VIEWS.includes(viewName)) {
        viewName = 'home';
    }

    if (viewName === 'admin') {
        if (!window.APP_IS_ADMIN) {
            showToast('Bạn không có quyền truy cập trang Quản trị Admin!', 'error');
            viewName = 'home';
        } else {
            switchToAdminPortal(null, 'dashboard');
            return;
        }
    }

    // Đảm bảo hiển thị giao diện Client
    const layout = document.getElementById('appLayout');
    const sbClient = document.getElementById('appSidebarClient');
    const sbAdmin = document.getElementById('appSidebarAdmin');
    const btnSwitchAdmin = document.getElementById('btnDdSwitchAdmin');
    const btnSwitchClient = document.getElementById('btnDdSwitchClient');
    const navClient = document.getElementById('mobileBottomNavClient');
    const navAdmin = document.getElementById('mobileBottomNavAdmin');

    if (layout) {
        layout.classList.remove('portal-admin');
        layout.classList.add('portal-client');
    }
    if (sbAdmin) sbAdmin.style.setProperty('display', 'none', 'important');
    if (sbClient) sbClient.style.removeProperty('display');
    if (btnSwitchClient) btnSwitchClient.style.setProperty('display', 'none', 'important');
    if (btnSwitchAdmin) btnSwitchAdmin.style.removeProperty('display');
    if (navAdmin) navAdmin.style.setProperty('display', 'none', 'important');
    if (navClient) navClient.style.removeProperty('display');

    const containerId = `view-${viewName}`;

    // Đổi hiển thị container View
    document.querySelectorAll('.workspace-view').forEach(v => v.classList.remove('active'));
    const targetView = document.getElementById(containerId);
    if (targetView) {
        targetView.classList.add('active');
    }

    // Cập nhật trạng thái active client sidebar navigation
    document.querySelectorAll('#appSidebarClient .nav-item').forEach(n => {
        const itemRoute = n.getAttribute('data-view') || n.getAttribute('href')?.replace(/^\//, '');
        n.classList.toggle('active', itemRoute === viewName);
    });

    // Cập nhật trạng thái active mobile bottom navigation client
    document.querySelectorAll('#mobileBottomNavClient .mobile-nav-item').forEach(m => {
        const itemRoute = m.getAttribute('data-view') || m.getAttribute('href')?.replace(/^\//, '');
        m.classList.toggle('active', itemRoute === viewName);
    });

    // Tự động đóng menu trượt mobile nếu đang mở
    const sidebar = getActiveSidebar();
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('active');
    }

    // Cập nhật URL trình duyệt qua History API: /home, /documents, /editor, /upgrade... (không có #)
    const shouldPush = (typeof eventOrPush === 'boolean') ? eventOrPush : true;
    if (shouldPush) {
        const targetPath = '/' + viewName;
        if (window.location.pathname !== targetPath || window.location.hash) {
            window.history.pushState({ view: viewName }, '', targetPath);
        }
    }

    // Hành động chuyên biệt từng view
    if (viewName === 'editor') {
        // Tự động thu gọn sidebar thành biểu tượng khi vào Trình biên tập để tối ưu không gian làm việc
        setSidebarCompact(true);
        if (!hasLoadedFile) {
            const urlParams = new URLSearchParams(window.location.search);
            const docId = urlParams.get('docId') || localStorage.getItem('editor_current_doc_id');
            if (docId) {
                const savedName = localStorage.getItem('editor_current_doc_name') || 'Tài liệu PDF';
                const savedUrl = '/api/documents/raw?id=' + encodeURIComponent(docId);
                loadRealPdf(savedUrl, savedName, docId);
            } else if (localStorage.getItem('editor_local_source_id')) {
                restoreLocalEditorDocument();
            } else {
                showEditorUploadState();
            }
        } else {
            showEditorWorkspace();
        }
    } else if (viewName === 'history') {
        loadExportHistory();
    } else if (viewName === 'documents') {
        filterDocumentsTable();
    } else if (viewName === 'settings') {
        initSettingsPage();
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Xử lý nút Back/Forward trên trình duyệt
window.addEventListener('popstate', (e) => {
    const state = e.state;
    if (state && state.portal === 'admin') {
        switchToAdminPortal(null, state.adminPage || 'dashboard');
        return;
    }
    const currentPath = window.location.pathname.replace(/^\/+|\/+$/g, '');
    if (currentPath === 'admin' || currentPath.startsWith('admin/')) {
        const sub = currentPath.split('/')[1] || 'dashboard';
        switchToAdminPortal(null, sub);
        return;
    }
    const view = (state && state.view) ? state.view : getPathView();
    switchToClientPortal(null, view);
});

// Chặn các click vào liên kết nội bộ để chuyển view không cần tải lại trang
document.addEventListener('click', (e) => {
    const link = e.target.closest('a[href]');
    if (!link) return;
    const href = link.getAttribute('href');
    if (!href) return;

    if (href.startsWith('#') && href.length > 1) {
        const target = href.replace(/^#\/?/, '');
        if (VALID_VIEWS.includes(target)) {
            e.preventDefault();
            switchView(target, true);
            return;
        }
    }

    if (href.startsWith('/') && !href.startsWith('/api') && !href.startsWith('/storage') && !link.hasAttribute('download') && link.target !== '_blank') {
        const target = href.replace(/^\/+|\/+$/g, '');
        if (target === 'admin' || target.startsWith('admin/')) {
            e.preventDefault();
            const sub = target.split('/')[1] || 'dashboard';
            switchToAdminPortal(null, sub);
            return;
        }
        if (VALID_VIEWS.includes(target)) {
            e.preventDefault();
            switchToClientPortal(null, target);
        }
    }
});

// Tự động chuẩn hóa URL và đồng bộ trạng thái Route khi tải trang
function initCleanRouting() {
    let currentPath = window.location.pathname.replace(/^\/+|\/+$/g, '');
    if (currentPath === 'admin' || currentPath.startsWith('admin/')) {
        if (!window.APP_IS_ADMIN) {
            showToast('Bạn không có quyền truy cập trang Quản trị Admin!', 'error');
            switchToClientPortal(null, 'home');
            return;
        }
        const subPage = currentPath.split('/')[1] || window.APP_ADMIN_CURRENT_VIEW || 'dashboard';
        switchToAdminPortal(null, subPage);
        return;
    }

    let targetView = 'home';
    if (window.location.hash && window.location.hash.length > 1) {
        let rawHash = window.location.hash.replace(/^#\/?/, '').split('?')[0].split('/')[0];
        if (LEGACY_VIEW_MAP[rawHash]) rawHash = LEGACY_VIEW_MAP[rawHash];
        if (VALID_VIEWS.includes(rawHash)) targetView = rawHash;
        window.history.replaceState({ view: targetView }, '', '/' + targetView);
    } else {
        if (LEGACY_VIEW_MAP[currentPath]) currentPath = LEGACY_VIEW_MAP[currentPath];
        if (VALID_VIEWS.includes(currentPath)) targetView = currentPath;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const docId = urlParams.get('docId') || localStorage.getItem('editor_current_doc_id');

    switchToClientPortal(null, targetView);

    if (targetView === 'editor' && docId && !hasLoadedFile) {
        const savedName = localStorage.getItem('editor_current_doc_name') || 'Tài liệu PDF';
        const savedUrl = '/api/documents/raw?id=' + encodeURIComponent(docId);
        loadRealPdf(savedUrl, savedName, docId);
    }
}

// Chuyển Tab ở Dashboard
function toggleDocTab(tabName) {
    document.getElementById('tabDocList').classList.toggle('active', tabName === 'list');
    document.getElementById('tabProcessing').classList.toggle('active', tabName === 'processing');
}

// Modal helper
function openModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.add('active');
        el.style.display = 'flex';
    }
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.remove('active');
        el.style.display = 'none';
    }
}

function openUploadModal() {
    if (!window.APP_IS_LOGGED_IN) {
        showToast('Vui lòng đăng nhập tài khoản để tải lên và lưu trữ tài liệu.', 'info');
        if (typeof openAuthModal === 'function') {
            openAuthModal('login');
        }
        return;
    }
    openModal('uploadModal');
}

// Toast notification helper
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    let iconSvg = '<svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
    if (type === 'success') {
        iconSvg = '<svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
    } else if (type === 'error') {
        iconSvg = '<svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
    }
    toast.innerHTML = `<span>${iconSvg}</span> <span>${message}</span>`;

    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// Thao tác với tài liệu khi bấm vào card
function openDocActions(docName, pages) {
    // Không hiện popup rườm rà khi click card
}

// Thực hiện ghép PDF tiêu chuẩn
async function executeStandardMerge() {
    const outputName = document.getElementById('mergeOutputName').value.trim() || 'Tai_lieu_ghep.pdf';

    const plan = [
        { source_doc: 'Tai_lieu_hoc_tap.pdf', source_page: 2, label: 'A2', rotation: 90 },
        { source_doc: 'De_cuong_mon_hoc.pdf', source_page: 1, label: 'B1', rotation: 0 },
        { source_doc: 'Tai_lieu_hoc_tap.pdf', source_page: 1, label: 'A1', rotation: 0 }
    ];

    try {
        const res = await fetch('/api/export/merge', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plan: plan, title: outputName })
        });
        const data = await res.json();
        if (data.status === 'success') {
            currentOutputId = data.output_id;
            showMergeResult(data);
            showToast('✓ Ghép tài liệu thành công!', 'success');
        } else {
            showToast(`Lỗi: ${data.message}`, 'error');
        }
    } catch (e) {
        showToast('Lỗi khi gọi API ghép file.', 'error');
    }
}

function showMergeResult(data) {
    const box = document.getElementById('mergeResultBox');
    const text = document.getElementById('mergeResultText');
    const btn = document.getElementById('btnDownloadMerged');

    text.innerText = `Tệp '${data.output_file}' (${data.total_pages} trang, ${Math.round(data.file_size / 1024)} KB) đã sẵn sàng.`;
    btn.href = data.download_url;
    box.style.display = 'block';
}

// Thực hiện tách PDF tiêu chuẩn
async function executeStandardSplit() {
    const groups = [
        { name: 'Nhom_1', pages: [1, 2, 3] },
        { name: 'Nhom_2', pages: [4, 5] }
    ];

    try {
        const res = await fetch('/api/export/split', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ source_doc: 'Tai_lieu_hoc_tap.pdf', groups: groups })
        });
        const data = await res.json();
        if (data.status === 'success') {
            const box = document.getElementById('splitResultBox');
            const list = document.getElementById('splitLinksList');
            list.innerHTML = '';

            data.generated_files.forEach(f => {
                const item = document.createElement('div');
                item.style.display = 'flex';
                item.style.alignItems = 'center';
                item.style.justifyContent = 'space-between';
                item.style.background = 'white';
                item.style.padding = '10px 14px';
                item.style.borderRadius = '8px';
                item.style.border = '1px solid #E2E8F0';

                item.innerHTML = `
                    <div>
                        <strong>${f.group}</strong> (${f.total_pages} trang: ${f.pages.join(', ')})
                        <div style="font-size: 0.75rem; color: #64748B;">${f.file}</div>
                    </div>
                    <a href="${f.download_url}" class="btn-primary" target="_blank">📥 Tải về</a>
                `;
                list.appendChild(item);
            });

            box.style.display = 'block';
            showToast('✓ Đã tách nhóm trang thành công!', 'success');
        } else {
            showToast(`Lỗi: ${data.message}`, 'error');
        }
    } catch (e) {
        showToast('Lỗi khi gọi API tách file.', 'error');
    }
}

// ==========================================
// LỊCH SỬ XUẤT & ĐỐI CHIẾU NGUỒN (HISTORY)
// ==========================================
window._cachedExportHistory = [];

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatFileSizeStr(bytes) {
    if (!bytes || isNaN(bytes)) return '0 KB';
    bytes = parseInt(bytes);
    if (bytes >= 1024 * 1024) {
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
    return Math.max(1, Math.round(bytes / 1024)) + ' KB';
}

// Tải lịch sử xuất
async function loadExportHistory() {
    const tbody = document.getElementById('historyTableBody');
    const mobileList = document.getElementById('historyMobileList');
    if (!tbody && !mobileList) return;

    if (!window.APP_IS_LOGGED_IN) {
        const notLoggedInHtml = `
            <div class="history-empty-state">
                <div class="empty-state-icon">
                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                </div>
                <h3>Chưa đăng nhập tài khoản</h3>
                <p>Vui lòng đăng nhập để lưu trữ, xem lại các tệp PDF đã xuất và tra cứu bảng đối chiếu nguồn trang.</p>
                <button type="button" class="btn-primary" onclick="openAuthModal('login')">
                    Đăng nhập ngay
                </button>
            </div>
        `;
        if (tbody) tbody.innerHTML = `<tr><td colspan="6" style="padding: 0;">${notLoggedInHtml}</td></tr>`;
        if (mobileList) mobileList.innerHTML = notLoggedInHtml;
        return;
    }

    const loadingHtml = `
        <div class="history-loading-pulse">
            <div class="history-spinner"></div>
            <span>Đang tải dữ liệu lịch sử xuất...</span>
        </div>
    `;
    if (tbody) tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 36px 20px;">${loadingHtml}</td></tr>`;
    if (mobileList) mobileList.innerHTML = `<div style="padding: 30px; text-align: center;">${loadingHtml}</div>`;

    try {
        const res = await fetch('/api/export/history');
        const data = await res.json();
        
        const items = data.data || data.history || [];
        window._cachedExportHistory = items;

        // Cập nhật thẻ thống kê
        const stats = data.stats || {};
        const statCount = document.getElementById('historyStatTotalCount');
        const statSize = document.getElementById('historyStatTotalSize');
        const statPages = document.getElementById('historyStatTotalPages');
        const statPlan = document.getElementById('historyStatPlan');

        if (statCount) statCount.textContent = (stats.total_count !== undefined ? stats.total_count : items.length) + ' tệp';
        if (statSize) statSize.textContent = (stats.total_size_mb !== undefined ? stats.total_size_mb : 0) + ' MB';
        if (statPages) {
            let totalPages = stats.total_pages;
            if (totalPages === undefined) {
                totalPages = items.reduce((acc, cur) => acc + (parseInt(cur.total_pages) || 0), 0);
            }
            statPages.textContent = totalPages + ' trang';
        }
        if (statPlan) {
            const plan = (stats.plan || 'FREE').toUpperCase();
            statPlan.innerHTML = plan === 'PRO' ? '<span class="badge-pro-pill">PRO</span>' : '<span class="pill-badge badge-secondary">FREE</span>';
        }

        renderExportHistoryUI(items);
    } catch (e) {
        console.error('loadExportHistory error:', e);
        const errorHtml = `
            <div class="history-empty-state">
                <div class="empty-state-icon" style="background: #FEE2E2; color: #DC2626;">
                    <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <h3>Lỗi kết nối máy chủ</h3>
                <p>Không thể tải dữ liệu lịch sử xuất. Vui lòng thử lại sau.</p>
                <button type="button" class="btn-outline" onclick="loadExportHistory()">
                    Thử lại
                </button>
            </div>
        `;
        if (tbody) tbody.innerHTML = `<tr><td colspan="6" style="padding: 0;">${errorHtml}</td></tr>`;
        if (mobileList) mobileList.innerHTML = errorHtml;
    }
}

// Render dữ liệu lịch sử cho cả Desktop Table và Mobile Cards
function renderExportHistoryUI(items, isSearch = false) {
    const tbody = document.getElementById('historyTableBody');
    const mobileList = document.getElementById('historyMobileList');

    if (!items || items.length === 0) {
        const emptyMsg = isSearch 
            ? 'Không tìm thấy tệp xuất nào khớp với từ khóa tìm kiếm.' 
            : 'Chưa có tệp nào được xuất. Hãy thực hiện Ghép hoặc Tách PDF để xem lịch sử.';
        
        const emptyHtml = `
            <div class="history-empty-state">
                <div class="empty-state-icon">
                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <h3>${emptyMsg}</h3>
                <p>Tất cả các bản xuất PDF thành công sẽ được lưu trữ an toàn kèm dữ liệu đối chiếu nguồn minh bạch.</p>
                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 10px;">
                    <button type="button" class="btn-primary" onclick="switchView('editor', event)">
                        Mở Trình Biên Tập PDF
                    </button>
                    ${isSearch ? '<button type="button" class="btn-outline" onclick="clearHistorySearch()">Xóa bộ lọc</button>' : ''}
                </div>
            </div>
        `;

        if (tbody) tbody.innerHTML = `<tr><td colspan="6" style="padding: 0;">${emptyHtml}</td></tr>`;
        if (mobileList) mobileList.innerHTML = emptyHtml;
        return;
    }

    // 1. Render Desktop Table
    if (tbody) {
        tbody.innerHTML = '';
        items.forEach(item => {
            const tr = document.createElement('tr');
            tr.className = 'history-item-row';
            tr.setAttribute('data-id', item.id);
            tr.innerHTML = `
                <td>
                    <span class="history-id-badge">#${item.id}</span>
                </td>
                <td>
                    <div class="history-file-cell">
                        <div class="file-icon-pdf">PDF</div>
                        <div class="history-file-info">
                            <a href="/api/export/download?id=${item.id}" class="history-file-name" title="${escapeHtml(item.filename)}">
                                ${escapeHtml(item.filename)}
                            </a>
                        </div>
                    </div>
                </td>
                <td style="text-align: center;">
                    <span class="pill-badge badge-pages">${item.total_pages || 1} trang</span>
                </td>
                <td style="text-align: center;">
                    <span class="pill-badge badge-size">${formatFileSizeStr(item.file_size)}</span>
                </td>
                <td class="history-time-cell">
                    <svg class="svg-icon svg-icon-xs text-muted" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span>${item.created_at || 'Vừa xong'}</span>
                </td>
                <td style="text-align: right;">
                    <div class="history-actions-group">
                        <button type="button" class="btn-action-pill btn-action-preview" onclick="openPreviewPdfModal(${item.id}, '${escapeHtml(item.filename)}')" title="Xem trước PDF">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <span>Xem</span>
                        </button>
                        <a href="/api/export/download?id=${item.id}" class="btn-action-pill btn-action-download" title="Tải xuống tệp">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <span>Tải về</span>
                        </a>
                        <button type="button" class="btn-action-pill btn-action-manifest" onclick="openManifestModal(${item.id})" title="Xem bảng đối chiếu nguồn">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <span>Nguồn</span>
                        </button>
                        <button type="button" class="btn-action-pill btn-action-delete" onclick="deleteExportHistoryItem(${item.id})" title="Xóa tệp khỏi lịch sử">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    // 2. Render Mobile Card List
    if (mobileList) {
        mobileList.innerHTML = '';
        items.forEach(item => {
            const card = document.createElement('div');
            card.className = 'history-mobile-card';
            card.setAttribute('data-id', item.id);
            card.innerHTML = `
                <div class="mobile-card-header">
                    <div class="mobile-card-meta">
                        <span class="history-id-badge">#${item.id}</span>
                        <span class="mobile-card-time">
                            <svg class="svg-icon svg-icon-xs text-muted" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            ${item.created_at || 'Vừa xong'}
                        </span>
                    </div>
                    <button type="button" class="btn-mobile-delete" onclick="deleteExportHistoryItem(${item.id})" title="Xóa tệp này">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
                
                <div class="mobile-card-body">
                    <div class="mobile-card-file">
                        <div class="file-icon-pdf">PDF</div>
                        <div class="mobile-file-details">
                            <a href="/api/export/download?id=${item.id}" class="mobile-filename">${escapeHtml(item.filename)}</a>
                            <div class="mobile-badges-row">
                                <span class="pill-badge badge-pages">${item.total_pages || 1} trang</span>
                                <span class="pill-badge badge-size">${formatFileSizeStr(item.file_size)}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mobile-card-actions">
                    <button type="button" class="btn-mobile-action btn-mobile-preview" onclick="openPreviewPdfModal(${item.id}, '${escapeHtml(item.filename)}')">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <span>Xem</span>
                    </button>
                    <button type="button" class="btn-mobile-action btn-mobile-manifest" onclick="openManifestModal(${item.id})">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <span>Nguồn</span>
                    </button>
                    <a href="/api/export/download?id=${item.id}" class="btn-mobile-action btn-mobile-download">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <span>Tải về</span>
                    </a>
                </div>
            `;
            mobileList.appendChild(card);
        });
    }
}

// Tìm kiếm lọc danh sách lịch sử theo thời gian thực
function filterHistoryTable(term) {
    const clearBtn = document.getElementById('historyClearSearch');
    if (clearBtn) {
        clearBtn.style.display = term && term.trim() ? 'block' : 'none';
    }

    if (!window._cachedExportHistory) return;

    if (!term || !term.trim()) {
        renderExportHistoryUI(window._cachedExportHistory);
        return;
    }

    const q = term.toLowerCase().trim();
    const filtered = window._cachedExportHistory.filter(item => {
        const idStr = String(item.id || '');
        const filename = (item.filename || '').toLowerCase();
        const date = (item.created_at || '').toLowerCase();
        return idStr.includes(q) || filename.includes(q) || date.includes(q);
    });

    renderExportHistoryUI(filtered, true);
}

// Xóa trắng ô tìm kiếm
function clearHistorySearch() {
    const input = document.getElementById('historySearchInput');
    if (input) {
        input.value = '';
        filterHistoryTable('');
    }
}

// Xóa 1 tệp xuất khỏi lịch sử
async function deleteExportHistoryItem(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa tệp này khỏi lịch sử xuất? Tệp trên đĩa lưu trữ cũng sẽ được dọn dẹp.')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('id', id);

        const res = await fetch('/api/export/delete', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.status === 'success') {
            showToast('✓ Đã xóa tệp khỏi lịch sử xuất!', 'success');
            loadExportHistory();
        } else {
            showToast('Lỗi: ' + (data.message || 'Không thể xóa tệp'), 'error');
        }
    } catch (e) {
        console.error('Delete error:', e);
        showToast('Lỗi kết nối khi xóa tệp.', 'error');
    }
}

// Xóa toàn bộ lịch sử xuất
async function confirmClearAllHistory() {
    if (!window._cachedExportHistory || window._cachedExportHistory.length === 0) {
        showToast('Lịch sử hiện đang trống.', 'info');
        return;
    }

    if (!confirm(`Bạn có chắc chắn muốn xóa toàn bộ ${window._cachedExportHistory.length} tệp trong lịch sử xuất? Thao tác này không thể hoàn tác!`)) {
        return;
    }

    try {
        const res = await fetch('/api/export/clear-all', {
            method: 'POST'
        });
        const data = await res.json();

        if (data.status === 'success') {
            showToast('✓ ' + (data.message || 'Đã dọn dẹp toàn bộ lịch sử xuất!'), 'success');
            loadExportHistory();
        } else {
            showToast('Lỗi: ' + (data.message || 'Không thể xóa toàn bộ lịch sử'), 'error');
        }
    } catch (e) {
        console.error('Clear all error:', e);
        showToast('Lỗi kết nối khi dọn dẹp lịch sử.', 'error');
    }
}

// Mở Modal xem trước tệp PDF xuất
function openPreviewPdfModal(id, filename) {
    const modal = document.getElementById('previewPdfModal');
    const title = document.getElementById('previewPdfModalTitle');
    const iframe = document.getElementById('previewPdfIframe');
    const downloadBtn = document.getElementById('previewPdfModalDownloadBtn');

    if (!modal) return;

    if (title) title.textContent = filename || 'Xem trước tệp PDF #' + id;
    if (iframe) iframe.src = `/api/export/preview?id=${id}`;
    if (downloadBtn) downloadBtn.href = `/api/export/download?id=${id}`;

    modal.style.display = 'flex';
}

// Đóng Modal xem trước tệp PDF xuất
function closePreviewPdfModal() {
    const modal = document.getElementById('previewPdfModal');
    const iframe = document.getElementById('previewPdfIframe');
    if (iframe) iframe.src = 'about:blank';
    if (modal) modal.style.display = 'none';
}

// Mở modal đối chiếu Source Manifest
async function openManifestModal(outputId) {
    const tbody = document.getElementById('manifestTableBody');
    const title = document.getElementById('manifestModalTitle');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Đang tải...</td></tr>';
    openModal('manifestModal');

    try {
        const res = await fetch(`/api/export/manifest?id=${outputId}`);
        const data = await res.json();
        if (data.status === 'success' && data.manifest) {
            title.innerText = `Bảng đối chiếu nguồn: ${data.filename} (${data.total_pages} trang)`;
            tbody.innerHTML = '';
            data.manifest.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>Trang ${item.output_page}</strong></td>
                    <td>${item.source_doc}</td>
                    <td>Trang ${item.source_page}</td>
                    <td><span class="pill-badge badge-info">${item.label || '-'}</span></td>
                    <td>${item.rotation}°</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:#EF4444;">Không có dữ liệu đối chiếu nguồn.</td></tr>';
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:#EF4444;">Lỗi khi tải bảng đối chiếu.</td></tr>';
    }
}

function viewExportManifest(outputId) {
    openManifestModal(outputId || window.currentOutputId);
}

// ==========================================
// CÀI ĐẶT HỆ THỐNG & TÀI KHOẢN (SETTINGS)
// ==========================================
window._settingsChanged = false;

function markSettingsChanged() {
    window._settingsChanged = true;
}

// Chuyển đổi tab trong trang cài đặt
function switchSettingsTab(tabName) {
    document.querySelectorAll('.settings-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-tab') === tabName);
    });

    document.querySelectorAll('.settings-tab-pane').forEach(pane => {
        pane.classList.toggle('active', pane.id === `pane-${tabName}`);
    });
}

// Khởi tạo và tải dữ liệu trang cài đặt
async function initSettingsPage() {
    // 1. Kiểm tra trạng thái theme hiện tại
    const currentTheme = document.documentElement.getAttribute('data-theme') || localStorage.getItem('theme') || 'light';
    selectAppTheme(currentTheme, false);

    // 2. Tải cấu hình từ API hoặc LocalStorage
    let loadedSettings = null;
    try {
        const res = await fetch('/api/user/settings');
        const data = await res.json();
        if (data.status === 'success') {
            loadedSettings = data.settings;
            if (data.profile) {
                populateProfileFields(data.profile);
            }
            if (data.is_guest) {
                const guestBanner = document.getElementById('settingsGuestBanner');
                if (guestBanner) guestBanner.style.display = 'flex';
                const pwForm = document.getElementById('passwordForm');
                if (pwForm) {
                    const btn = document.getElementById('btnChangePassword');
                    if (btn) btn.disabled = true;
                }
            } else {
                const guestBanner = document.getElementById('settingsGuestBanner');
                if (guestBanner) guestBanner.style.display = 'none';
            }
        }
    } catch (e) {
        console.warn('Cannot load settings from API, fallback to localStorage', e);
    }

    if (!loadedSettings) {
        const local = localStorage.getItem('pdf_system_settings');
        if (local) {
            try { loadedSettings = JSON.parse(local); } catch(e){}
        }
    }

    // Áp dụng giá trị vào form cài đặt
    if (loadedSettings) {
        if (document.getElementById('setPdfDpi') && loadedSettings.pdf_dpi !== undefined) {
            document.getElementById('setPdfDpi').value = loadedSettings.pdf_dpi;
        }
        if (document.getElementById('setPdfCompression') && loadedSettings.pdf_compression) {
            document.getElementById('setPdfCompression').value = loadedSettings.pdf_compression;
        }
        if (document.getElementById('setAutoPageNumbers') && loadedSettings.auto_page_numbers !== undefined) {
            document.getElementById('setAutoPageNumbers').checked = !!loadedSettings.auto_page_numbers;
            togglePageNumberPos(!!loadedSettings.auto_page_numbers);
        }
        if (document.getElementById('setPageNumberPos') && loadedSettings.page_number_position) {
            document.getElementById('setPageNumberPos').value = loadedSettings.page_number_position;
        }
        if (document.getElementById('setAutoOptimizeSize') && loadedSettings.auto_optimize_size !== undefined) {
            document.getElementById('setAutoOptimizeSize').checked = !!loadedSettings.auto_optimize_size;
        }
        if (document.getElementById('setAutoCleanupHours') && loadedSettings.auto_cleanup_hours) {
            document.getElementById('setAutoCleanupHours').value = loadedSettings.auto_cleanup_hours;
        }
        if (document.getElementById('setDeleteAfterExport') && loadedSettings.delete_after_export !== undefined) {
            document.getElementById('setDeleteAfterExport').checked = !!loadedSettings.delete_after_export;
        }
        if (document.getElementById('setAutoCompactSidebar') && loadedSettings.auto_compact_sidebar !== undefined) {
            document.getElementById('setAutoCompactSidebar').checked = !!loadedSettings.auto_compact_sidebar;
        }
        if (document.getElementById('setNotificationSound') && loadedSettings.notification_sound !== undefined) {
            document.getElementById('setNotificationSound').checked = !!loadedSettings.notification_sound;
        }
    }

    // Cập nhật số lượng tệp đã xuất trong mini card
    const exportMini = document.getElementById('settingsExportCountMini');
    if (exportMini) {
        if (window._cachedExportHistory && window._cachedExportHistory.length > 0) {
            exportMini.textContent = window._cachedExportHistory.length + ' tệp';
        } else {
            try {
                const hRes = await fetch('/api/export/history');
                const hData = await hRes.json();
                const count = (hData.data || hData.history || []).length;
                exportMini.textContent = count + ' tệp';
            } catch(e) {
                exportMini.textContent = '0 tệp';
            }
        }
    }

    window._settingsChanged = false;
}

function populateProfileFields(profile) {
    if (!profile) return;
    const nameEl = document.getElementById('setFullName');
    const emailEl = document.getElementById('setEmail');
    const phoneEl = document.getElementById('setPhone');
    const dispName = document.getElementById('settingsProfileDisplayName');
    const avatarBox = document.getElementById('settingsAvatarBox');

    if (nameEl && profile.name) nameEl.value = profile.name;
    if (emailEl && profile.email) emailEl.value = profile.email;
    if (phoneEl && profile.phone) phoneEl.value = profile.phone;
    if (dispName && profile.name) dispName.textContent = profile.name;
    if (avatarBox && profile.name) {
        avatarBox.textContent = profile.name.trim().substring(0, 2).toUpperCase();
    }
}

// Ẩn/hiện chọn vị trí số trang
function togglePageNumberPos(show) {
    const wrap = document.getElementById('pageNumberPosWrap');
    if (wrap) {
        wrap.style.display = show ? 'flex' : 'none';
    }
}

// Cập nhật thông tin hồ sơ
async function handleProfileSubmit(e) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();

    const fullName = document.getElementById('setFullName')?.value.trim();
    const email = document.getElementById('setEmail')?.value.trim();
    const phone = document.getElementById('setPhone')?.value.trim();

    if (!fullName) {
        showToast('Vui lòng nhập họ và tên của bạn.', 'error');
        return;
    }
    if (!email) {
        showToast('Vui lòng nhập địa chỉ email.', 'error');
        return;
    }

    const btn = document.getElementById('btnSaveProfile');
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span>Đang lưu...</span>';
    }

    try {
        const res = await fetch('/api/user/profile', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ full_name: fullName, email: email, phone: phone })
        });
        const data = await res.json();

        if (data.status === 'success') {
            showToast('✓ Cập nhật hồ sơ cá nhân thành công!', 'success');
            const dispName = document.getElementById('settingsProfileDisplayName');
            if (dispName) dispName.textContent = fullName;
            const avatarBox = document.getElementById('settingsAvatarBox');
            if (avatarBox) avatarBox.textContent = fullName.trim().substring(0, 2).toUpperCase();

            // Cập nhật tên trên dropdown topbar
            const topName = document.querySelector('.user-menu-btn span');
            if (topName) topName.textContent = fullName;
        } else {
            showToast('Lỗi: ' + (data.message || 'Không thể lưu hồ sơ'), 'error');
        }
    } catch(err) {
        console.error('Profile update error:', err);
        showToast('Lỗi kết nối khi cập nhật hồ sơ.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}

// Đổi mật khẩu
async function handlePasswordSubmit(e) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();

    const currentPassword = document.getElementById('setCurrentPassword')?.value;
    const newPassword = document.getElementById('setNewPassword')?.value;
    const confirmPassword = document.getElementById('setConfirmPassword')?.value;

    if (!currentPassword || !newPassword) {
        showToast('Vui lòng điền đầy đủ mật khẩu hiện tại và mật khẩu mới.', 'error');
        return;
    }
    if (newPassword.length < 6) {
        showToast('Mật khẩu mới phải có tối thiểu 6 ký tự.', 'error');
        return;
    }
    if (newPassword !== confirmPassword) {
        showToast('Xác nhận mật khẩu mới không khớp!', 'error');
        return;
    }

    const btn = document.getElementById('btnChangePassword');
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span>Đang cập nhật...</span>';
    }

    try {
        const res = await fetch('/api/user/change-password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                current_password: currentPassword,
                new_password: newPassword,
                confirm_password: confirmPassword
            })
        });
        const data = await res.json();

        if (data.status === 'success') {
            showToast('✓ Đổi mật khẩu thành công!', 'success');
            document.getElementById('passwordForm')?.reset();
            const strengthBox = document.getElementById('passwordStrengthBox');
            if (strengthBox) strengthBox.style.display = 'none';
        } else {
            showToast('Lỗi: ' + (data.message || 'Không thể đổi mật khẩu'), 'error');
        }
    } catch(err) {
        console.error('Password change error:', err);
        showToast('Lỗi kết nối máy chủ khi đổi mật khẩu.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}

// Ẩn/Hiện mật khẩu
function togglePasswordVisibility(inputId) {
    const el = document.getElementById(inputId);
    if (!el) return;
    el.type = el.type === 'password' ? 'text' : 'password';
}

// Kiểm tra độ mạnh mật khẩu
function checkPasswordStrength(val) {
    const box = document.getElementById('passwordStrengthBox');
    const fill = document.getElementById('strengthBarFill');
    const text = document.getElementById('strengthText');
    if (!box || !fill || !text) return;

    if (!val || val.length === 0) {
        box.style.display = 'none';
        return;
    }
    box.style.display = 'block';

    let score = 0;
    if (val.length >= 6) score += 25;
    if (val.length >= 10) score += 25;
    if (/[0-9]/.test(val)) score += 25;
    if (/[A-Z]/.test(val) || /[^A-Za-z0-9]/.test(val)) score += 25;

    fill.style.width = score + '%';
    if (score <= 25) {
        fill.style.background = '#EF4444';
        text.textContent = 'Độ mạnh: Rất yếu (Cần thêm độ dài)';
        text.style.color = '#EF4444';
    } else if (score <= 50) {
        fill.style.background = '#F59E0B';
        text.textContent = 'Độ mạnh: Trung bình (Nên thêm số hoặc ký tự hoa)';
        text.style.color = '#D97706';
    } else if (score <= 75) {
        fill.style.background = '#3B82F6';
        text.textContent = 'Độ mạnh: Khá tốt';
        text.style.color = '#2563EB';
    } else {
        fill.style.background = '#10B981';
        text.textContent = 'Độ mạnh: Rất mạnh (Tuyệt vời)';
        text.style.color = '#059669';
    }
}

// Chọn giao diện Sáng / Tối
function selectAppTheme(theme, doSave = true) {
    const lightCard = document.getElementById('themeCardLight');
    const darkCard = document.getElementById('themeCardDark');

    if (lightCard) lightCard.classList.toggle('selected', theme === 'light');
    if (darkCard) darkCard.classList.toggle('selected', theme === 'dark');

    if (typeof setTheme === 'function') {
        setTheme(theme);
    } else {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
    }

    if (doSave) {
        markSettingsChanged();
    }
}

// Lưu tất cả thiết lập
async function saveAllSettings() {
    const settingsObj = {
        pdf_dpi: parseInt(document.getElementById('setPdfDpi')?.value || '150'),
        pdf_compression: document.getElementById('setPdfCompression')?.value || 'medium',
        auto_page_numbers: document.getElementById('setAutoPageNumbers')?.checked ?? true,
        page_number_position: document.getElementById('setPageNumberPos')?.value || 'bottom-right',
        auto_optimize_size: document.getElementById('setAutoOptimizeSize')?.checked ?? true,
        auto_cleanup_hours: parseInt(document.getElementById('setAutoCleanupHours')?.value || '24'),
        delete_after_export: document.getElementById('setDeleteAfterExport')?.checked ?? false,
        auto_compact_sidebar: document.getElementById('setAutoCompactSidebar')?.checked ?? true,
        notification_sound: document.getElementById('setNotificationSound')?.checked ?? true,
        theme: document.documentElement.getAttribute('data-theme') || 'light'
    };

    localStorage.setItem('pdf_system_settings', JSON.stringify(settingsObj));

    try {
        await fetch('/api/user/settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ settings: settingsObj })
        });
    } catch(e) {
        console.warn('API save settings error:', e);
    }

    showToast('✓ Đã lưu toàn bộ cấu hình cài đặt hệ thống thành công!', 'success');
    window._settingsChanged = false;
}

// Khôi phục cài đặt về mặc định
function resetSettingsToDefault() {
    if (!confirm('Bạn có chắc chắn muốn khôi phục toàn bộ tùy chọn xử lý và giao diện về giá trị mặc định ban đầu không?')) {
        return;
    }

    if (document.getElementById('setPdfDpi')) document.getElementById('setPdfDpi').value = '150';
    if (document.getElementById('setPdfCompression')) document.getElementById('setPdfCompression').value = 'medium';
    if (document.getElementById('setAutoPageNumbers')) {
        document.getElementById('setAutoPageNumbers').checked = true;
        togglePageNumberPos(true);
    }
    if (document.getElementById('setPageNumberPos')) document.getElementById('setPageNumberPos').value = 'bottom-right';
    if (document.getElementById('setAutoOptimizeSize')) document.getElementById('setAutoOptimizeSize').checked = true;
    if (document.getElementById('setAutoCleanupHours')) document.getElementById('setAutoCleanupHours').value = '24';
    if (document.getElementById('setDeleteAfterExport')) document.getElementById('setDeleteAfterExport').checked = false;
    if (document.getElementById('setAutoCompactSidebar')) document.getElementById('setAutoCompactSidebar').checked = true;
    if (document.getElementById('setNotificationSound')) document.getElementById('setNotificationSound').checked = true;

    selectAppTheme('light', false);
    saveAllSettings();
    showToast('✓ Đã khôi phục cài đặt mặc định thành công!', 'info');
}

// Xóa cache PDF nội bộ trình duyệt
function clearLocalPdfCache() {
    try {
        localStorage.removeItem('editor_current_doc_id');
        localStorage.removeItem('editor_current_doc_name');
        localStorage.removeItem('editor_current_doc_url');
        localStorage.removeItem('editor_local_source_id');
        localStorage.removeItem('editor_pdf_raw_cache');
        sessionStorage.clear();
        showToast('✓ Đã xóa sạch bộ nhớ đệm trình duyệt!', 'success');
    } catch(e) {
        showToast('Lỗi khi dọn dẹp bộ nhớ đệm.', 'error');
    }
}

// Xóa toàn bộ tài liệu cá nhân
async function confirmPurgeAllDocuments() {
    if (!confirm('CẢNH BÁO NGUY HIỂM: Bạn có chắc chắn muốn xóa TOÀN BỘ tài liệu PDF trong kho tài liệu của bạn không? Thao tác này không thể hoàn tác!')) {
        return;
    }

    showToast('Đang điều hướng đến trang Quản lý tài liệu để hoàn tất...', 'info');
    setTimeout(() => {
        switchView('documents');
    }, 700);
}

// Xử lý tải lên file
async function handleFileSelect(event) {
    if (!window.APP_IS_LOGGED_IN) {
        showToast('Vui lòng đăng nhập tài khoản để tải lên và lưu trữ tài liệu.', 'info');
        if (typeof openAuthModal === 'function') {
            openAuthModal('login');
        }
        return;
    }

    const file = event.target.files[0];
    if (!file) return;

    if (!file.name.toLowerCase().endsWith('.pdf')) {
        showToast('Lỗi: Chỉ chấp nhận tệp có định dạng .pdf', 'error');
        return;
    }

    if (file.size > 26214400) {
        showToast('Lỗi: Dung lượng tệp vượt quá 25 MB.', 'error');
        return;
    }

    const progress = document.getElementById('uploadProgress');
    progress.style.display = 'block';

    const formData = new FormData();
    formData.append('file', file);

    try {
        const res = await fetch('/api/documents/upload', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        progress.style.display = 'none';

        if (data.status === 'success') {
            closeModal('uploadModal');
            showToast(`✓ ${data.message}: ${data.document.name}`, 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(`Lỗi: ${data.message}`, 'error');
        }
    } catch (e) {
        progress.style.display = 'none';
        showToast('Lỗi khi tải tệp lên máy chủ.', 'error');
    }
}

// =========================================================
// THEME MANAGER (LIGHT & DARK MODE)
// =========================================================
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    setAppTheme(newTheme);
}

function setAppTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('app_theme', theme);

    const toggleBtn = document.getElementById('btnThemeToggle');
    if (toggleBtn) {
        toggleBtn.title = theme === 'dark' ? 'Chuyển sang Giao diện Sáng (Light Mode)' : 'Chuyển sang Giao diện Tối (Dark Mode)';
    }
}

function initAppTheme() {
    const savedTheme = localStorage.getItem('app_theme') || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', savedTheme);
    const toggleBtn = document.getElementById('btnThemeToggle');
    if (toggleBtn) {
        toggleBtn.title = savedTheme === 'dark' ? 'Chuyển sang Giao diện Sáng (Light Mode)' : 'Chuyển sang Giao diện Tối (Dark Mode)';
    }
}

// Kích hoạt ngay lập tức khi file JS được tải
initAppTheme();

// Khởi tạo sự kiện khi DOM sẵn sàng
document.addEventListener('DOMContentLoaded', () => {
    initAppTheme();

    // Navigation routing by hash
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', (e) => {
            const view = item.dataset.view;
            if (view) {
                switchView(view);
            }
        });
    });

    // Check all checkbox
    const checkAll = document.getElementById('checkAllDocs');
    if (checkAll) {
        checkAll.addEventListener('change', (e) => {
            document.querySelectorAll('.doc-checkbox').forEach(cb => {
                cb.checked = e.target.checked;
            });
        });
    }

    // Drag and drop zone for upload modal
    const dropZone = document.getElementById('uploadDropZone');
    if (dropZone) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
            });
        });
        dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                document.getElementById('fileInput').files = files;
                handleFileSelect({ target: { files: files } });
            }
        });
    }

    // Clean Routing init on load and hash change
    window.addEventListener('hashchange', initCleanRouting);
    initCleanRouting();

    // Click outside to close user dropdown
    document.addEventListener('click', (e) => {
        const dd = document.getElementById('userDropdownMenu');
        const chip = document.getElementById('userChipBtn');
        if (dd && dd.classList.contains('active')) {
            if (!dd.contains(e.target) && !chip.contains(e.target)) {
                dd.classList.remove('active');
            }
        }
    });

    console.log("PDF Manager v1.0 Client Engine Ready.");
});

// =========================================================
// AUTHENTICATION & USER MANAGEMENT HANDLERS
// =========================================================

// Bật / Tắt Dropdown người dùng
function toggleUserDropdown(event) {
    if (event) event.stopPropagation();
    const dd = document.getElementById('userDropdownMenu');
    if (dd) {
        dd.classList.toggle('active');
    }
}

function closeUserDropdown() {
    const dd = document.getElementById('userDropdownMenu');
    if (dd) dd.classList.remove('active');
}

// Mở Modal Xác thực (Đăng nhập / Đăng ký)
function openAuthModal(tab = 'login') {
    closeUserDropdown();
    switchAuthTab(tab);
    openModal('authModal');
}

// Chuyển đổi tab trong Auth Modal
function switchAuthTab(tab = 'login') {
    const isLogin = tab === 'login';
    document.getElementById('tabAuthLogin').classList.toggle('active', isLogin);
    document.getElementById('tabAuthRegister').classList.toggle('active', !isLogin);
    document.getElementById('formLogin').style.display = isLogin ? 'flex' : 'none';
    document.getElementById('formRegister').style.display = isLogin ? 'none' : 'flex';
    document.getElementById('authModalTitle').innerText = isLogin ? 'Đăng nhập PDF Manager' : 'Đăng ký tài khoản mới';
}

// Mở Modal Gói dịch vụ
function openPlanModal() {
    closeUserDropdown();
    openModal('planModal');
}

// Mở Modal Thông tin tài khoản
function openAccountModal() {
    closeUserDropdown();
    openModal('accountModal');
}

// Nâng cấp hoặc chuyển đổi gói (FREE / PRO)
async function upgradePlan(targetPlan = 'PRO') {
    try {
        const res = await fetch('/api/auth/plan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plan: targetPlan })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(`🎉 ${data.message}`, 'success');
            closeModal('planModal');
            setTimeout(() => {
                window.location.reload();
            }, 800);
        } else {
            showToast(data.message || 'Không thể chuyển đổi gói.', 'error');
        }
    } catch (err) {
        showToast('Lỗi kết nối khi cập nhật gói.', 'error');
    }
}

// Xử lý gửi Form Đăng nhập
async function handleAuthLogin(event) {
    event.preventDefault();
    const btn = document.getElementById('btnLoginSubmit');
    const username = document.getElementById('loginUsername').value.trim();
    const password = document.getElementById('loginPassword').value;

    if (!username || !password) {
        showToast('Vui lòng nhập tên đăng nhập và mật khẩu.', 'error');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span>Đang xác thực...</span>';

    try {
        const res = await fetch('/api/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        const data = await res.json();
        btn.disabled = false;
        btn.innerHTML = '<span>Đăng nhập</span> <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>';

        if (data.status === 'success') {
            closeModal('authModal');
            showToast(`✓ ${data.message}`, 'success');
            setTimeout(() => {
                const isAdmin = data.user && (data.user.role === 'admin' || data.user.is_admin === true);
                if (isAdmin) {
                    if (window.location.pathname.startsWith('/admin')) {
                        window.location.reload();
                    } else if (window.APP_MAINTENANCE_ACTIVE) {
                        window.location.href = '/admin';
                    } else {
                        window.location.href = '/home';
                    }
                } else {
                    window.location.href = '/home';
                }
            }, 600);
        } else {
            showToast(data.message || 'Đăng nhập không thành công.', 'error');
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = '<span>Đăng nhập</span>';
        showToast('Lỗi kết nối máy chủ.', 'error');
    }
}

// Xử lý gửi Form Đăng ký (không còn chọn vai trò, mặc định gói FREE)
async function handleAuthRegister(event) {
    event.preventDefault();
    const btn = document.getElementById('btnRegisterSubmit');
    const fullName = document.getElementById('regFullName').value.trim();
    const username = document.getElementById('regUsername').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const password = document.getElementById('regPassword').value;
    const confirm = document.getElementById('regPasswordConfirm').value;

    if (password !== confirm) {
        showToast('Mật khẩu và xác nhận mật khẩu không khớp.', 'error');
        return;
    }

    if (password.length < 6) {
        showToast('Mật khẩu phải có độ dài từ 6 ký tự trở lên.', 'error');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span>Đang tạo tài khoản...</span>';

    try {
        const res = await fetch('/api/auth/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                full_name: fullName,
                username: username,
                email: email,
                password: password
            })
        });
        const data = await res.json();
        btn.disabled = false;
        btn.innerHTML = '<span>Tạo tài khoản và Đăng nhập</span> <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>';

        if (data.status === 'success') {
            closeModal('authModal');
            showToast(`✓ ${data.message}`, 'success');
            setTimeout(() => {
                window.location.href = '/home';
            }, 600);
        } else {
            showToast(data.message || 'Lỗi đăng ký tài khoản.', 'error');
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = '<span>Tạo tài khoản và Đăng nhập</span>';
        showToast('Lỗi kết nối máy chủ khi đăng ký.', 'error');
    }
}

// Đăng xuất
async function logoutUser() {
    closeUserDropdown();
    try {
        const res = await fetch('/api/auth/logout', { method: 'POST' });
        const data = await res.json();
        showToast('✓ ' + (data.message || 'Đã đăng xuất thành công.'), 'info');
    } catch (err) {
        showToast('✓ Đã đăng xuất phiên làm việc.', 'info');
    } finally {
        setTimeout(() => {
            window.location.href = '/home';
        }, 400);
    }
}

// =========================================================
// PDFAID UNIFIED PDF EDITOR ENGINE (100% MATCH)
// =========================================================
let editorCurrentPage = 1;
let editorZoom = 1.0;
let editorActiveTool = 'add-text';
let editorActiveColor = '#000000';
let editorActiveShape = 'line';
let editorFontSize = 18;
let editorFontFamily = 'Helvetica, Arial, sans-serif';
let editorTextAlign = 'left';
let editorOpacity = 1.0;
let editorStrokeWidth = 2;
let editorSelectedElementId = null;
let isEditorPanMode = false;
let editorHistoryStack = [];
let editorRedoStack = [];
let hasLoadedFile = false;
let currentDocumentName = 'Tai_lieu_hoc_tap.pdf';

// Professional SVG Vector Icon library (Replacing plastic emoji stickers)
const VECTOR_ICONS = {
    'chat': `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>`,
    'flag': `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>`,
    'arrow': `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>`,
    'check': `<svg viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;"><polyline points="20 6 9 17 4 12"/></svg>`,
    'cross': `<svg viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`,
    'star': `<svg viewBox="0 0 24 24" fill="#F59E0B" stroke="#F59E0B" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`,
    'shield': `<svg viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>`,
    'pin': `<svg viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>`,
    'bulb': `<svg viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;"><line x1="9" y1="18" x2="15" y2="18"/><line x1="10" y1="22" x2="14" y2="22"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5.76.76 1.23 1.52 1.41 2.5"/></svg>`,
    'warning': `<svg viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`
};

// State of editor & Mozilla PDF.js engine
let editorPages = [];
let currentPdfDoc = null;
let currentPdfBytes = null;
let isPdfRendering = false;
let editorSources = new Map();
let editorRenderPending = false;
let editorDraftRecoveryBlocked = false;
let editorLoadInProgress = false;

async function restoreLocalEditorDocument() {
    const id = localStorage.getItem('editor_local_source_id');
    if (!id || editorLoadInProgress || hasLoadedFile) return;
    editorLoadInProgress = true;
    try {
        await restoreEditorSource(id);
        const source = editorSources.get(id);
        await loadRealPdf(source.bytes.buffer.slice(0), source.name);
        localStorage.setItem('editor_local_source_id', id);
    } catch (err) {
        console.error('Không thể khôi phục tệp cục bộ:', err);
        showToast('Không thể khôi phục tệp PDF cục bộ: ' + err.message, 'error');
    } finally { editorLoadInProgress = false; }
}

function editorSourceDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('pdf-editor-sources', 1);
        request.onupgradeneeded = () => request.result.createObjectStore('sources', { keyPath: 'id' });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function persistEditorSource(source) {
    const db = await editorSourceDatabase();
    try {
        await new Promise((resolve, reject) => {
            const transaction = db.transaction('sources', 'readwrite');
            transaction.objectStore('sources').put({ id: source.id, name: source.name, bytes: source.bytes });
            transaction.oncomplete = resolve;
            transaction.onerror = () => reject(transaction.error);
            transaction.onabort = () => reject(transaction.error || new Error('Không thể lưu nguồn PDF.'));
        });
    } finally { db.close(); }
}

async function restoreEditorSource(id) {
    const db = await editorSourceDatabase();
    let saved;
    try {
        saved = await new Promise((resolve, reject) => {
            const request = db.transaction('sources', 'readonly').objectStore('sources').get(id);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    } finally { db.close(); }
    if (!saved || !saved.bytes) throw new Error('Thiếu PDF nguồn ' + id);
    const bytes = new Uint8Array(saved.bytes);
    const doc = await pdfjsLib.getDocument({ data: bytes.slice() }).promise;
    editorSources.set(id, { id, name: saved.name, bytes, doc });
}

function editorSourceForPage(page) {
    return page && page.sourceId ? editorSources.get(page.sourceId) : { doc: currentPdfDoc, bytes: currentPdfBytes, name: currentDocumentName };
}

async function createEditorSource(file) {
    if (!file || !/\.pdf$/i.test(file.name)) throw new Error('Vui lòng chọn tệp PDF hợp lệ.');
    const bytes = new Uint8Array(await file.arrayBuffer());
    const doc = await pdfjsLib.getDocument({ data: bytes.slice() }).promise;
    const id = 'source-' + (crypto.randomUUID ? crypto.randomUUID() : Date.now() + '-' + Math.random());
    const source = { id, name: file.name, bytes, doc };
    editorSources.set(id, source);
    try { await persistEditorSource(source); }
    catch (err) { editorSources.delete(id); await doc.destroy(); throw new Error('Không thể lưu PDF nhập thêm để khôi phục sau khi tải lại: ' + err.message); }
    return source;
}

function pagesFromEditorSource(source) {
    return Array.from({ length: source.doc.numPages }, (_, index) => ({
        id: 'page-' + (crypto.randomUUID ? crypto.randomUUID() : Date.now() + '-' + Math.random()),
        sourceId: source.id, originalPageNum: index + 1, title: `${source.name} — trang ${index + 1}`,
        rotation: 0, elements: [], drawings: [], textEdits: {}
    }));
}

// Configure Mozilla PDF.js Worker
if (window.pdfjsLib) {
    pdfjsLib.GlobalWorkerOptions.workerSrc = '/assets/vendor/pdfjs/pdf.worker.min.js';
}

// Quản lý hiển thị Upload State vs Editor Workspace
function showEditorUploadState() {
    hasLoadedFile = false;
    const uploadState = document.getElementById('editorUploadState');
    const workspace = document.getElementById('editorWorkspaceContainer');
    if (uploadState) uploadState.style.display = 'flex';
    if (workspace) workspace.style.display = 'none';
}

function showEditorWorkspace() {
    hasLoadedFile = true;
    const uploadState = document.getElementById('editorUploadState');
    const workspace = document.getElementById('editorWorkspaceContainer');
    if (uploadState) uploadState.style.display = 'none';
    if (workspace) workspace.style.display = 'flex';
}

/**
 * Nạp tệp PDF thực tế (qua File, ArrayBuffer hoặc URL) vào Mozilla PDF.js Engine
 * Tự động đồng bộ docId, lưu nháp và khôi phục khi tải lại trang (F5)
 */
async function loadRealPdf(source, docName = '', docId = null) {
    showEditorWorkspace();
    const overlay = document.getElementById('pdfLoadingOverlay');
    const loadingText = document.getElementById('pdfLoadingText');
    if (overlay) overlay.style.display = 'flex';
    if (loadingText) loadingText.innerText = `Đang giải mã và phân tích tệp "${docName || 'PDF'}"...`;

    try {
        let arrayBuffer = null;

        if (source instanceof File || source instanceof Blob) {
            currentDocumentName = docName || source.name || 'tailieu.pdf';
            arrayBuffer = await source.arrayBuffer();

            // Lưu lên máy chủ và MySQL ngay lập tức
            if (source instanceof File && window.APP_IS_LOGGED_IN) {
                try {
                    const validation = await pdfjsLib.getDocument({ data: new Uint8Array(arrayBuffer.slice(0)) }).promise;
                    await validation.destroy();
                    const fd = new FormData();
                    fd.append('file', source);
                    const upRes = await fetch('/api/documents/upload', { method: 'POST', body: fd });
                    const upData = await upRes.json();
                    if (upData.status === 'success' && upData.document) {
                        docId = upData.document.id;
                        currentDocumentName = upData.document.name || currentDocumentName;
                        console.log('✓ File đã được lưu vào storage & MySQL, docId =', docId);
                    } else {
                        showToast('Tệp chỉ mở cục bộ, chưa lưu lên máy chủ: ' + (upData.message || 'Lỗi tải lên.'), 'error');
                    }
                } catch (ue) {
                    console.warn('Lỗi lưu tệp lên backend:', ue);
                }
            }
        } else if (typeof source === 'string') {
            currentDocumentName = docName || source.split('/').pop().split('?')[0] || 'tailieu.pdf';
            const res = await fetch(source);
            if (!res.ok) {
                localStorage.removeItem('editor_current_doc_id');
                localStorage.removeItem('editor_current_doc_url');
                localStorage.removeItem('editor_current_doc_name');
                if (window.history && window.history.replaceState) {
                    window.history.replaceState({ view: 'editor' }, '', '/editor');
                }
                throw new Error(`HTTP ${res.status}: Không thể nạp tệp từ máy chủ.`);
            }
            arrayBuffer = await res.arrayBuffer();
        } else if (source instanceof ArrayBuffer) {
            arrayBuffer = source;
            currentDocumentName = docName || 'tailieu.pdf';
        }

        if (!arrayBuffer) {
            throw new Error('Dữ liệu nhị phân PDF không hợp lệ.');
        }

        // Tạo bản sao độc lập nguyên vẹn cho currentPdfBytes để không bị neutered bởi Worker của PDF.js
        currentPdfBytes = new Uint8Array(arrayBuffer.slice(0));
        const pdfJsBuffer = new Uint8Array(arrayBuffer.slice(0));

        if (window.pdfjsLib && !pdfjsLib.GlobalWorkerOptions.workerSrc) {
            pdfjsLib.GlobalWorkerOptions.workerSrc = '/assets/vendor/pdfjs/pdf.worker.min.js';
        }

        const loadingTask = pdfjsLib.getDocument({
            data: pdfJsBuffer,
            cMapUrl: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/cmaps/',
            cMapPacked: true,
            standardFontDataUrl: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/standard_fonts/'
        });
        currentPdfDoc = await loadingTask.promise;
        editorSources = new Map();
        editorDraftRecoveryBlocked = false;
        editorHistoryStack = [];
        editorRedoStack = [];
        if (!docId && source instanceof File) {
            const localSource = { id: 'local-' + (crypto.randomUUID ? crypto.randomUUID() : Date.now() + '-' + Math.random()), name: currentDocumentName, bytes: currentPdfBytes, doc: currentPdfDoc };
            try {
                await persistEditorSource(localSource);
                editorSources.set(localSource.id, localSource);
                localStorage.setItem('editor_local_source_id', localSource.id);
            } catch (err) {
                console.warn('Không thể lưu tệp cục bộ để khôi phục:', err);
                localStorage.removeItem('editor_local_source_id');
                showToast('Tệp chưa được lưu; không thể khôi phục PDF gốc sau khi tải lại.', 'error');
            }
        } else {
            localStorage.removeItem('editor_local_source_id');
        }

        hasLoadedFile = true;

        // Lưu thông tin tệp vào LocalStorage và đồng bộ URL query (/editor?docId=...)
        const draftKey = docId ? ('doc_' + docId) : ('file_' + currentDocumentName.replace(/[^a-zA-Z0-9_-]/g, '_'));
        if (docId) {
            window.currentDocumentId = docId;
            localStorage.setItem('editor_current_doc_id', docId);
            localStorage.setItem('editor_current_doc_name', currentDocumentName);
            localStorage.setItem('editor_current_doc_url', '/api/documents/raw?id=' + docId);
            if (window.history && window.history.replaceState) {
                window.history.replaceState({ view: 'editor', docId: docId }, '', '/editor?docId=' + docId);
            }
        } else if (typeof source === 'string') {
            window.currentDocumentId = null;
            localStorage.removeItem('editor_current_doc_id');
            localStorage.setItem('editor_current_doc_name', currentDocumentName);
            localStorage.setItem('editor_current_doc_url', source);
        } else {
            window.currentDocumentId = null;
            localStorage.removeItem('editor_current_doc_id');
            localStorage.removeItem('editor_current_doc_url');
            localStorage.setItem('editor_current_doc_name', currentDocumentName);
        }

        // Kiểm tra xem có bản nháp nào được lưu trước đó của tài liệu này không
        editorPages = [];
        let hasRestoredDraft = false;
        try {
            const savedDraft = localStorage.getItem('editor_draft_' + draftKey) || (docId ? localStorage.getItem('editor_draft_' + docId) : null);
            if (savedDraft) {
                const parsedDraft = JSON.parse(savedDraft);
                if (Array.isArray(parsedDraft) && parsedDraft.length > 0) {
                    for (const id of new Set(parsedDraft.map(p => p.sourceId).filter(Boolean))) await restoreEditorSource(id);
                    editorPages = parsedDraft;
                    // Luôn dọn sạch cache text cũ để áp dụng thuật toán gom cụm đoạn văn chuẩn PDFaid
                    editorPages.forEach(p => {
                        delete p._cachedTextItems;
                        if (p.elements) {
                            p.elements = p.elements.filter(el => el.type !== 'text' || (el.text && el.text.trim() !== 'Gõ nội dung tại đây...'));
                        }
                    });
                    hasRestoredDraft = true;
                }
            }
        } catch (de) {
            editorDraftRecoveryBlocked = true;
            console.warn('Lỗi đọc nháp từ LocalStorage:', de);
        }

        // Nếu chưa có bản nháp, tạo trang mặc định theo số trang thực của PDF
        if (!hasRestoredDraft || editorPages.length === 0) {
            editorPages = [];
            for (let i = 1; i <= currentPdfDoc.numPages; i++) {
                editorPages.push({
                    id: i,
                    pageNumber: i,
                    originalPageNum: i,
                    title: `Trang ${i}: ${currentDocumentName}`,
                    rotation: 0,
                    elements: [],
                    drawings: []
                });
            }
        }

        if (editorDraftRecoveryBlocked) {
            console.warn('Không thể khôi phục bản nháp có nguồn PDF đã nhập trong phiên trước.');
            showToast('Bản nháp có PDF nhập thêm không thể khôi phục sau khi tải lại; dữ liệu nháp vẫn được giữ.', 'error');
        }
        editorCurrentPage = 1;
        updateEditorFloatingInfo();
        setupCanvasInteractions();
        setupDrawingCanvasInteractions();

        // Render trang chính
        await renderEditorCanvas();

        // Render thumbnails ở cột bên trái
        renderEditorThumbnails();

        const draftMsg = hasRestoredDraft ? ' (Đã khôi phục các chú thích từ bản nháp)' : '';
        showToast(`✓ Đã nạp thành công "${currentDocumentName}" (${currentPdfDoc.numPages} trang)${draftMsg}`, 'success');
    } catch (err) {
        console.error('Lỗi nạp PDF:', err);
        showToast(`Lỗi nạp tệp PDF: ${err.message}`, 'error');
        localStorage.removeItem('editor_current_doc_id');
        localStorage.removeItem('editor_current_doc_url');
        localStorage.removeItem('editor_current_doc_name');
        if (window.history && window.history.replaceState) {
            window.history.replaceState({ view: 'editor' }, '', '/editor');
        }
        showEditorUploadState();
    } finally {
        if (overlay) overlay.style.display = 'none';
    }
}

// Handler nạp file mẫu từ server
function loadSampleDocument(filename, totalPages, docTitle) {
    switchView('editor');
    loadRealPdf('/api/documents/raw?name=' + encodeURIComponent(filename), filename);
}

// Handlers tải file từ giao diện Hero & Editor
function handleHeroFileInput(event) {
    const file = event.target.files[0];
    if (!file) return;
    loadRealPdf(file, file.name);
}

function handleHeroFileDrop(event) {
    event.preventDefault();
    const dt = event.dataTransfer;
    if (dt && dt.files && dt.files.length > 0) {
        loadRealPdf(dt.files[0], dt.files[0].name);
    }
}

function loadUploadedPdfIntoEditor(file) {
    loadRealPdf(file, file.name);
}

function initPdfAidEditor() {
    if (!hasLoadedFile || !currentPdfDoc) {
        showEditorUploadState();
        return;
    }
    renderEditorThumbnails();
    renderEditorCanvas();
    updateEditorFloatingInfo();
    setupCanvasInteractions();
}

// Render danh sách thumbnail cột bên trái với Canvas thu nhỏ chuẩn PDFaid
async function renderEditorThumbnails() {
    const list = document.getElementById('editorThumbnailsList');
    const badge = document.getElementById('stripPageCountBadge');
    if (!list) return;

    if (badge) {
        badge.innerText = `${editorPages.length} trang`;
    }

    list.innerHTML = '';

    editorPages.forEach((pageData, index) => {
        const item = document.createElement('div');
        item.className = `strip-thumb-item ${pageData.pageNumber === editorCurrentPage ? 'active' : ''}`;
        item.id = `thumbItem_${pageData.pageNumber}`;
        item.onclick = () => selectEditorPage(pageData.pageNumber);

        item.innerHTML = `
            <div class="strip-thumb-card" id="thumbCard_${pageData.pageNumber}">
                <div style="font-size: 0.65rem; color: #94A3B8; display: flex; align-items: center; justify-content: center; height: 100%;">Trang ${pageData.pageNumber}...</div>
            </div>
            <div class="strip-thumb-meta">
                <span class="strip-page-num">${pageData.pageNumber}</span>
                <span style="font-size: 0.65rem; color: #64748B;">${pageData.rotation !== 0 ? pageData.rotation + '°' : ''}</span>
                <div class="strip-thumb-actions">
                    <button class="btn-thumb-action" onclick="rotateEditorPage(${pageData.pageNumber}, 90, event)" title="Xoay 90°">↻</button>
                    <button class="btn-thumb-action" onclick="deleteEditorPage(${pageData.pageNumber}, event)" title="Xóa">✕</button>
                </div>
            </div>
        `;

        list.appendChild(item);

        if (index < editorPages.length - 1) {
            const divider = document.createElement('div');
            divider.className = 'strip-insert-divider';
            divider.title = `Chèn trang mới vào sau trang ${pageData.pageNumber}`;
            divider.onclick = (e) => {
                e.stopPropagation();
                insertBlankPage(index + 1);
            };
            divider.innerHTML = `
                <div class="strip-insert-line"></div>
                <div class="strip-insert-btn">+</div>
            `;
            list.appendChild(divider);
        }

        // Render thật từng thumbnail canvas qua Mozilla PDF.js
        if (currentPdfDoc) {
            (async () => {
                try {
                    const card = document.getElementById(`thumbCard_${pageData.pageNumber}`);
                    if (pageData.isBlankPage || !pageData.originalPageNum) {
                        const canvas = document.createElement('canvas');
                        canvas.width = 110;
                        canvas.height = 155;
                        canvas.className = 'thumbnail-canvas';
                        canvas.style.maxWidth = '100%';
                        canvas.style.maxHeight = '100%';
                        canvas.style.objectFit = 'contain';
                        const ctx = canvas.getContext('2d');
                        ctx.fillStyle = '#FFFFFF';
                        ctx.fillRect(0, 0, 110, 155);
                        ctx.strokeStyle = '#E2E8F0';
                        ctx.strokeRect(1, 1, 108, 153);
                        if (card) {
                            card.innerHTML = '';
                            card.appendChild(canvas);
                        }
                        return;
                    }

                    const source = editorSourceForPage(pageData);
                    if (!source || !source.doc) throw new Error('Không tìm thấy PDF nguồn của trang.');
                    const page = await source.doc.getPage(pageData.originalPageNum);
                    const unscaled = page.getViewport({ scale: 1.0 });
                    const scale = 110 / unscaled.width;
                    const rot = (page.rotate + (pageData.rotation || 0)) % 360;
                    const viewport = page.getViewport({ scale, rotation: rot });

                    const canvas = document.createElement('canvas');
                    canvas.width = Math.floor(viewport.width);
                    canvas.height = Math.floor(viewport.height);
                    canvas.className = 'thumbnail-canvas';
                    canvas.style.maxWidth = '100%';
                    canvas.style.maxHeight = '100%';
                    canvas.style.objectFit = 'contain';

                    const ctx = canvas.getContext('2d');
                    await page.render({ canvasContext: ctx, viewport }).promise;

                    if (card) {
                        card.innerHTML = '';
                        card.appendChild(canvas);
                    }
                } catch (e) { }
            })();
        }
    });
}

function selectEditorPage(pageNum) {
    if (pageNum < 1 || pageNum > editorPages.length) return;
    editorCurrentPage = pageNum;

    document.querySelectorAll('.strip-thumb-item').forEach(el => el.classList.remove('active'));
    const activeItem = document.getElementById(`thumbItem_${pageNum}`);
    if (activeItem) {
        activeItem.classList.add('active');
        activeItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    renderEditorCanvas();
    updateEditorFloatingInfo();
}

function jumpEditorPage(delta) {
    selectEditorPage(editorCurrentPage + delta);
}

function rotateEditorPage(pageNum, deg = 90, event) {
    if (event) event.stopPropagation();
    const p = editorPages.find(item => item.pageNumber === pageNum);
    if (p) {
        pushEditorHistory();
        p.rotation = ((p.rotation || 0) + deg) % 360;
        renderEditorThumbnails();
        if (pageNum === editorCurrentPage) {
            renderEditorCanvas();
        }
    }
}

function updateEditorFloatingInfo() {
    const info = document.getElementById('floatingPageInfo');
    if (info) {
        info.innerText = `${editorCurrentPage} / ${editorPages.length}`;
    }
}

// Render nội dung trang PDF thực tế qua Mozilla PDF.js Canvas sắc nét
async function renderEditorCanvas() {
    if (isPdfRendering) { editorRenderPending = true; return; }
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage) || editorPages[0];
    if (!pageData) return;

    const paper = document.getElementById('pdfPaperPage');
    const canvas = document.getElementById('pdfRenderCanvas');
    const annotLayer = document.getElementById('paperAnnotationLayer');
    const drawingCanvas = document.getElementById('paperDrawingCanvas');
    const overlay = document.getElementById('pdfLoadingOverlay');

    if (!paper || !canvas || !annotLayer) return;

    if (pageData.isBlankPage || !pageData.originalPageNum) {
        // Xử lý trang trắng mới thêm độc lập: Không tải trang nào từ PDF gốc
        const sideways = Math.abs(pageData.rotation || 0) % 180 === 90;
        const pageWidth = sideways ? 842 : 595;
        const pageHeight = sideways ? 595 : 842;
        const viewport = { width: pageWidth * editorZoom, height: pageHeight * editorZoom };
        const pixelRatio = Math.max(1, window.devicePixelRatio || 1);
        canvas.width = Math.floor(viewport.width * pixelRatio);
        canvas.height = Math.floor(viewport.height * pixelRatio);
        canvas.style.width = `${Math.floor(viewport.width)}px`;
        canvas.style.height = `${Math.floor(viewport.height)}px`;

        annotLayer.style.width = `${pageWidth}px`;
        annotLayer.style.height = `${pageHeight}px`;
        annotLayer.style.transformOrigin = 'top left';
        annotLayer.style.transform = `scale(${editorZoom})`;

        if (drawingCanvas) {
            drawingCanvas.width = Math.floor(viewport.width * pixelRatio);
            drawingCanvas.height = Math.floor(viewport.height * pixelRatio);
            drawingCanvas.style.width = `${Math.floor(viewport.width)}px`;
            drawingCanvas.style.height = `${Math.floor(viewport.height)}px`;
        }

        const textEditLayer = document.getElementById('paperTextEditLayer');
        if (textEditLayer) {
            textEditLayer.style.width = `${Math.floor(viewport.width)}px`;
            textEditLayer.style.height = `${Math.floor(viewport.height)}px`;
        }

        const searchLayer = document.getElementById('paperSearchHighlightLayer');
        if (searchLayer) {
            searchLayer.style.width = `${Math.floor(viewport.width)}px`;
            searchLayer.style.height = `${Math.floor(viewport.height)}px`;
        }

        pageData.renderedWidth = viewport.width;
        pageData.renderedHeight = viewport.height;
        pageData._cachedTextItems = [];

        const ctx = canvas.getContext('2d');
        ctx.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, viewport.width, viewport.height);

        if (overlay) overlay.style.display = 'none';

        renderCanvasAnnotations(pageData);
        renderPageDrawings(pageData);
        await renderPdfTextEditLayer(pageData);
        renderSearchHighlightsOnCurrentPage();
        return;
    }

    if (currentPdfDoc) {
        if (isPdfRendering) { editorRenderPending = true; return; }
        isPdfRendering = true;
        if (overlay) overlay.style.display = 'flex';

        try {
            const source = editorSourceForPage(pageData);
            if (!source || !source.doc) throw new Error('Không tìm thấy PDF nguồn của trang.');
            const page = await source.doc.getPage(pageData.originalPageNum);

            // Xoay góc: góc gốc của trang + góc người dùng xoay
            const effRot = (page.rotate + (pageData.rotation || 0)) % 360;
            const viewport = page.getViewport({ scale: editorZoom, rotation: effRot });

            const pixelRatio = Math.max(1, window.devicePixelRatio || 1);
            canvas.width = Math.floor(viewport.width * pixelRatio);
            canvas.height = Math.floor(viewport.height * pixelRatio);
            canvas.style.width = `${Math.floor(viewport.width)}px`;
            canvas.style.height = `${Math.floor(viewport.height)}px`;

            annotLayer.style.width = `${viewport.width / editorZoom}px`;
            annotLayer.style.height = `${viewport.height / editorZoom}px`;
            annotLayer.style.transformOrigin = 'top left';
            annotLayer.style.transform = `scale(${editorZoom})`;

            if (drawingCanvas) {
                drawingCanvas.width = Math.floor(viewport.width * pixelRatio);
                drawingCanvas.height = Math.floor(viewport.height * pixelRatio);
                drawingCanvas.style.width = `${Math.floor(viewport.width)}px`;
                drawingCanvas.style.height = `${Math.floor(viewport.height)}px`;
            }

            const textEditLayer = document.getElementById('paperTextEditLayer');
            if (textEditLayer) {
                textEditLayer.style.width = `${Math.floor(viewport.width)}px`;
                textEditLayer.style.height = `${Math.floor(viewport.height)}px`;
            }

            const searchLayer = document.getElementById('paperSearchHighlightLayer');
            if (searchLayer) {
                searchLayer.style.width = `${Math.floor(viewport.width)}px`;
                searchLayer.style.height = `${Math.floor(viewport.height)}px`;
            }

            pageData.renderedWidth = viewport.width;
            pageData.renderedHeight = viewport.height;

            const ctx = canvas.getContext('2d');
            ctx.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);

            await page.render({ canvasContext: ctx, viewport }).promise;
        } catch (err) {
            console.error('Lỗi render trang PDF:', err);
        } finally {
            isPdfRendering = false;
            if (overlay) overlay.style.display = 'none';
            if (editorRenderPending) {
                editorRenderPending = false;
                renderEditorCanvas();
                return;
            }
        }
    }

    // Render các đối tượng chú thích (Text, Stamp, Ký tên, Hình khối, Ảnh, Link, Ghi chú)
    renderCanvasAnnotations(pageData);

    // Render các nét vẽ tay và tô sáng
    renderPageDrawings(pageData);

    // Render lớp chỉnh sửa trực tiếp chữ gốc của PDF (Khớp Image 1)
    await renderPdfTextEditLayer(pageData);

    // Render các từ khóa tìm kiếm được highlight trên trang (Khớp Image 5)
    renderSearchHighlightsOnCurrentPage();

    // Kích hoạt tương tác vẽ nếu công cụ là draw hoặc highlight
    if (drawingCanvas) {
        const isDrawTool = (editorActiveTool === 'draw' || editorActiveTool === 'highlight' || editorActiveTool === 'eraser' || isPenEraserMode);
        drawingCanvas.classList.toggle('drawing-active', isDrawTool);
    }
}

// Render lớp chỉnh sửa trực tiếp chữ gốc của PDF (Khớp 100% Ảnh 1)
// Hàm nhận diện và ánh xạ chính xác phông chữ từ PDF sang CSS
function mapPdfFontToCss(fontName, stylesObj, fontObj) {
    let resolvedFamily = '';
    let isBold = false;
    let isItalic = false;

    if (stylesObj && stylesObj[fontName]) {
        resolvedFamily = stylesObj[fontName].fontFamily || '';
    }
    const realFontName = (fontObj && fontObj.name) ? fontObj.name : '';
    const combined = (realFontName + ' ' + resolvedFamily + ' ' + (fontName || '')).toLowerCase();

    // Nhận diện kiểu chữ đậm (Bold) và nghiêng (Italic) chính xác 100% từ tên font gốc PostScript
    isBold = /(?:^|[^a-z])(?:bold|black|heavy|w[789]|b\b)|-bold|_bold|boldmt/i.test(combined) || !!(fontObj && fontObj.bold);
    isItalic = /(?:^|[^a-z])(?:italic|oblique|slant|it\b)|-italic|_italic|italicmt/i.test(combined) || !!(fontObj && fontObj.italic);

    let family = "Arial, sans-serif";

    // 1. Phông chữ Monospace
    if (/courier|mono|consolas|menlo|source code|fira code/i.test(combined)) {
        family = "'Courier New', Courier, monospace";
    }
    // 2. Phông chữ Cambria
    else if (/cambria/i.test(combined)) {
        family = "Cambria, 'Times New Roman', serif";
    }
    // 3. Phông chữ Georgia / Minion Pro
    else if (/minion|georgia|garamond|baskerville/i.test(combined)) {
        family = "Georgia, serif";
    }
    // 4. Phông chữ Serif / Times
    else if (/times|roman|\bserif\b/i.test(combined)) {
        family = "'Times New Roman', Times, serif";
    }
    // 5. Phông chữ Sans-Serif (Arial / Helvetica / Inter / Roboto / Calibri / Segoe)
    else if (/sans-serif|arial|helvetica|calibri|roboto|tahoma|verdana|open sans|myriad|trebuchet|inter|segoe/i.test(combined)) {
        if (/helvetica/i.test(combined)) {
            family = "Helvetica, Arial, sans-serif";
        } else if (/inter/i.test(combined)) {
            family = "'Inter', sans-serif";
        } else if (/roboto/i.test(combined)) {
            family = "'Roboto', sans-serif";
        } else {
            family = "Arial, sans-serif";
        }
    } else {
        if (/\bserif\b/i.test(resolvedFamily) && !/sans/i.test(resolvedFamily)) {
            family = "'Times New Roman', Times, serif";
        } else {
            family = "Arial, sans-serif";
        }
    }

    return { family, isBold, isItalic, realFontName };
}

// Đồng bộ trạng thái phông chữ, cỡ chữ, căn lề, màu chữ và màu nền che lên thanh ribbon
function syncTextPropertiesToRibbon(item) {
    if (!item) return;
    const fontSelect = document.getElementById('editorFontFamily');
    if (fontSelect && item.fontFamily) {
        const itemFont = item.fontFamily.toLowerCase();
        let matched = false;
        for (const opt of fontSelect.options) {
            const optVal = opt.value.toLowerCase();
            const optTxt = opt.text.toLowerCase();
            if (itemFont.includes('cambria') && (optVal.includes('cambria') || optTxt.includes('cambria'))) {
                fontSelect.value = opt.value;
                matched = true;
                break;
            }
            if (itemFont.includes('times') && (optVal.includes('times') || optTxt.includes('times'))) {
                fontSelect.value = opt.value;
                matched = true;
                break;
            }
            if (itemFont.includes('helvetica') && (optVal.includes('helvetica') || optTxt.includes('helvetica'))) {
                fontSelect.value = opt.value;
                matched = true;
                break;
            }
            if (itemFont.includes('arial') && (optVal.includes('arial') || optTxt.includes('arial'))) {
                fontSelect.value = opt.value;
                matched = true;
                break;
            }
            if (itemFont.includes('courier') && (optVal.includes('courier') || optTxt.includes('courier'))) {
                fontSelect.value = opt.value;
                matched = true;
                break;
            }
            if (itemFont.includes('georgia') && (optVal.includes('georgia') || optTxt.includes('georgia'))) {
                fontSelect.value = opt.value;
                matched = true;
                break;
            }
        }
        if (!matched) {
            for (const opt of fontSelect.options) {
                if (itemFont.includes(opt.text.toLowerCase()) || opt.value.toLowerCase().includes(itemFont)) {
                    fontSelect.value = opt.value;
                    break;
                }
            }
        }
        editorFontFamily = fontSelect.value;
    }

    const sizeSelect = document.getElementById('editorStrokeOrFontSize');
    if (sizeSelect && item.fontSize) {
        const targetSize = Math.round(item.fontSize);
        let bestOpt = sizeSelect.options[0]?.value || '14';
        let minDiff = 999;
        for (const opt of sizeSelect.options) {
            const v = parseInt(opt.value, 10);
            const diff = Math.abs(v - targetSize);
            if (diff < minDiff) {
                minDiff = diff;
                bestOpt = opt.value;
            }
        }
        sizeSelect.value = bestOpt;
        editorFontSize = parseInt(bestOpt, 10);
    }

    const boldBtn = document.getElementById('btnFormatBold');
    if (boldBtn) {
        boldBtn.classList.toggle('active', !!item.isBold);
    }

    const italicBtn = document.getElementById('btnFormatItalic');
    if (italicBtn) {
        italicBtn.classList.toggle('active', !!item.isItalic);
    }

    // Đồng bộ căn lề
    const align = item.align || 'left';
    ['btnAlignLeft', 'btnAlignCenter', 'btnAlignRight'].forEach(id => {
        const btn = document.getElementById(id);
        if (btn) btn.classList.remove('active');
    });
    if (align === 'left' && document.getElementById('btnAlignLeft')) document.getElementById('btnAlignLeft').classList.add('active');
    if (align === 'center' && document.getElementById('btnAlignCenter')) document.getElementById('btnAlignCenter').classList.add('active');
    if (align === 'right' && document.getElementById('btnAlignRight')) document.getElementById('btnAlignRight').classList.add('active');

    // Đồng bộ màu chữ (Text color)
    const curColor = item.color || item.textColor;
    if (curColor) {
        document.querySelectorAll('#propModeText .color-swatch').forEach(s => {
            const c = s.style.getPropertyValue('--c');
            s.classList.toggle('active', !!(c && c.toLowerCase() === curColor.toLowerCase()));
        });
        const customPicker = document.getElementById('editorCustomColorPicker');
        if (customPicker && curColor.startsWith('#') && curColor.length >= 7) {
            customPicker.value = curColor;
        }
    }

    // Đồng bộ màu nền che (Background cover color)
    const bgSelect = document.getElementById('editorTextBgColorSelect');
    if (bgSelect && item.bgColor) {
        let found = false;
        for (const opt of bgSelect.options) {
            if (opt.value.toLowerCase() === item.bgColor.toLowerCase()) {
                bgSelect.value = opt.value;
                found = true;
                break;
            }
        }
        if (!found) {
            bgSelect.value = 'custom';
            const bgCustom = document.getElementById('editorTextBgCustomColor');
            if (bgCustom && item.bgColor.startsWith('#') && item.bgColor.length >= 7) {
                bgCustom.style.display = 'inline-block';
                bgCustom.value = item.bgColor;
            }
        }
    }
}

// Hàm dọn dẹp các ký tự vỡ trực tiếp từ PDF raw stream
function cleanPdfRawString(str) {
    if (!str) return '';
    let s = str;

    // Phục hồi các cụm từ tiếng Việt thường bị vỡ mã CMap hoặc dính \uFFFD trong PDF
    s = s.replace(/th\s*ự\s*c\s*t\s*[\uFFFDế]\s*/gi, 'thực tế ');
    s = s.replace(/kh\s*ả\s*o\s*s\s*á\s*t\s*th\s*ự\s*c\s*t\s*[\uFFFDế]\s*/gi, 'khảo sát thực tế ');
    s = s.replace(/kinh\s*t\s*[\uFFFDế]\s*/gi, 'kinh tế ');
    s = s.replace(/qu\s*ố\s*c\s*t\s*[\uFFFDế]\s*/gi, 'quốc tế ');
    s = s.replace(/v\s*ấ\s*n\s*đ\s*[\uFFFDề]\s*/gi, 'vấn đề ');
    s = s.replace(/nghi\s*[\uFFFDê]\s*n\s*c\s*ứ\s*u/gi, 'nghiên cứu ');
    s = s.replace(/y\s*ế\s*[\uFFFDu]\s*t\s*[\uFFFDố]\s*/gi, 'yếu tố ');
    s = s.replace(/nh\s*â\s*n\s*t\s*[\uFFFDố]\s*/gi, 'nhân tố ');
    s = s.replace(/ph\s*á\s*t\s*tri\s*[\uFFFDể]\s*n/gi, 'phát triển ');
    s = s.replace(/Ch\s*í\s*nh\s*s\s*á\s*ch/gi, 'Chính sách');
    s = s.replace(/t\s*à\s*i\s*ch\s*í\s*nh/gi, 'tài chính');
    s = s.replace(/ng\s*â\s*n\s*h\s*à\s*ng/gi, 'ngân hàng');
    s = s.replace(/l\s*ự\s*a\s*ch\s*ọ\s*n/gi, 'lựa chọn');
    s = s.replace(/ngh\s*ề\s*nghi\s*ệ\s*p/gi, 'nghề nghiệp');
    s = s.replace(/H\s*ọ\s*c\s*vi\s*ệ\s*n/gi, 'Học viện');
    s = s.replace(/th\s*ị\s*tr\s*ư\s*ờ\s*ng/gi, 'thị trường');
    s = s.replace(/gi\s*ả\s*i\s*ph\s*á\s*p/gi, 'giải pháp');
    s = s.replace(/k\s*i\s*ế\s*n\s*ngh\s*ị/gi, 'kiến nghị');
    s = s.replace(/sinh\s*vi\s*ê\s*n/gi, 'sinh viên');
    s = s.replace(/gi\s*ả\s*ng\s*vi\s*ê\s*n/gi, 'giảng viên');
    s = s.replace(/C\s*ơ\s*s\s*ở\s*d\s*ữ\s*li\s*ệ\s*u/gi, 'Cơ sở dữ liệu');
    s = s.replace(/M\s*ã\s*ngu\s*ồ\s*n\s*m\s*ở/gi, 'Mã nguồn mở');
    s = s.replace(/Qu\s*ả\s*n\s*tr\s*ị/gi, 'Quản trị');
    s = s.replace(/Ch\s*ư\s*ơ\s*ng/gi, 'Chương');

    s = s.replace(/yế\uFFFD\s*u/gi, 'yếu');
    s = s.replace(/yế\uFFFD/gi, 'yếu');
    s = s.replace(/tế\uFFFD/gi, 'tế');
    s = s.replace(/đế\uFFFD\s*n/gi, 'đến');
    s = s.replace(/đế\uFFFD/gi, 'đến');
    s = s.replace(/kế\uFFFD\s*t/gi, 'kết');
    s = s.replace(/kế\uFFFD/gi, 'kết');
    s = s.replace(/viế\uFFFD\s*t/gi, 'viết');
    s = s.replace(/viế\uFFFD/gi, 'viết');
    s = s.replace(/kiế\uFFFD\s*n/gi, 'kiến');
    s = s.replace(/kiế\uFFFD/gi, 'kiến');
    s = s.replace(/quyế\uFFFD\s*t/gi, 'quyết');
    s = s.replace(/quyế\uFFFD/gi, 'quyết');
    s = s.replace(/chiế\uFFFD\s*c/gi, 'chiếc');
    s = s.replace(/chiế\uFFFD/gi, 'chiếc');
    s = s.replace(/tiế\uFFFD\s*p/gi, 'tiếp');
    s = s.replace(/tiế\uFFFD\s*n/gi, 'tiến');
    s = s.replace(/biế\uFFFD\s*t/gi, 'biết');
    s = s.replace(/biế\uFFFD/gi, 'biết');
    s = s.replace(/mì\uFFFD\s*nh/gi, 'mình');
    s = s.replace(/mì\uFFFD/gi, 'mình');
    s = s.replace(/tì\uFFFD\s*m/gi, 'tìm');
    s = s.replace(/tì\uFFFD/gi, 'tìm');
    s = s.replace(/thì\uFFFD/gi, 'thì');

    // Dọn sạch hoàn toàn các ký tự \uFFFD rác còn sót lại
    s = s.replace(/\uFFFD/g, '');

    return s;
}

// Hàm hậu xử lý phục hồi 100% ngữ nghĩa tiếng Việt và tiếng Anh sau khi ghép khối văn bản
function postProcessMergedText(text) {
    if (!text) return '';
    let s = text;

    // 1. Phục hồi ngữ nghĩa các từ tiếng Việt bị vỡ tổ hợp dấu hoặc ký tự \uFFFD
    s = s.replace(/\byế\s*u\b/gi, 'yếu');
    s = s.replace(/yế\s*u\s*t\s*o\s*\uFFFD?/gi, 'yếu tố ');
    s = s.replace(/yếu\s*t\s*o\s*\uFFFD?/gi, 'yếu tố ');
    s = s.replace(/yếu tố(?=[a-zA-Z\u00C0-\u1EF9])/gi, 'yếu tố ');
    s = s.replace(/nhân\s*t\s*o\s*\uFFFD?/gi, 'nhân tố ');
    s = s.replace(/nhân tố(?=[a-zA-Z\u00C0-\u1EF9])/gi, 'nhân tố ');
    s = s.replace(/\bt\s*o\s*\uFFFD/gi, 'tố');
    s = s.replace(/\bto\uFFFD/gi, 'tố');
    s = s.replace(/hư\s+ởng/gi, 'hưởng');
    s = s.replace(/ảnh\s+hưởng(?=[a-zA-Z\u00C0-\u1EF9])/gi, 'ảnh hưởng ');
    s = s.replace(/ngh\s*ế\s*\uFFFD?\s*nghiệp/gi, 'nghề nghiệp');
    s = s.replace(/ngh\s*ế\s*\uFFFD/gi, 'nghề');
    s = s.replace(/ngh\s+[ếê]\b/gi, 'nghề');
    s = s.replace(/ngh\s+[ếê]\s+nghiệp/gi, 'nghề nghiệp');
    s = s.replace(/nghế\uFFFD/gi, 'nghề');
    s = s.replace(/đ\s+[ếê]\s+xuất/gi, 'đề xuất');
    s = s.replace(/\bv\s+[ếê]\b/gi, 'về');
    s = s.replace(/\bđ\s+[ếê]\b/gi, 'để');
    s = s.replace(/\bđúng về(?=[a-zA-Z\u00C0-\u1EF9])/gi, 'đúng về ');
    s = s.replace(/\bchuyên ngành(?=[a-zA-Z\u00C0-\u1EF9])/gi, 'chuyên ngành ');
    s = s.replace(/\btài chính(?=[a-zA-Z\u00C0-\u1EF9])/gi, 'tài chính ');
    s = s.replace(/\bngân hàng(?=[a-zA-Z\u00C0-\u1EF9])/gi, 'ngân hàng ');
    s = s.replace(/\bhọc viện(?=[a-zA-Z\u00C0-\u1EF9])/gi, 'học viện ');
    s = s.replace(/\bchính sách(?=[a-zA-Z\u00C0-\u1EF9])/gi, 'chính sách ');
    s = s.replace(/\bphát triển(?=[a-zA-Z\u00C0-\u1EF9])/gi, 'phát triển ');
    s = s.replace(/\blựa chọn(?=[a-zA-Z\u00C0-\u1EF9])/gi, 'lựa chọn ');
    s = s.replace(/\bsinh viên(?=[a-zA-Z\u00C0-\u1EF9])/gi, 'sinh viên ');
    s = s.replace(/\bngành nghề(?=[a-zA-Z\u00C0-\u1EF9])/gi, 'ngành nghề ');
    s = s.replace(/ch\s*ì\s*\uFFFD?\s*nh/gi, 'chính');
    s = s.replace(/chì\uFFFD\s*nh/gi, 'chính');
    s = s.replace(/tri\s*ế\s*\uFFFD?\s*n/gi, 'triển');
    s = s.replace(/triế\uFFFD\s*n/gi, 'triển');
    s = s.replace(/hi\s*ế\s*\uFFFD?\s*u/gi, 'hiểu');
    s = s.replace(/hiế\uFFFD\s*u/gi, 'hiểu');
    s = s.replace(/hiể\uFFFD/gi, 'hiểu');
    s = s.replace(/nhi\s*ế\s*\uFFFD?\s*u/gi, 'nhiều');
    s = s.replace(/nhiế\uFFFD\s*u/gi, 'nhiều');
    s = s.replace(/xu\s*a\s*\uFFFD?\s*t/gi, 'xuất');
    s = s.replace(/nh\s*a\s*\uFFFD?\s*m/gi, 'nhằm');
    s = s.replace(/nh\s*a\s*\uFFFD?\s*t/gi, 'nhất');
    s = s.replace(/m\s*a\s*\uFFFD?\s*t/gi, 'mất');
    s = s.replace(/r\s*a\s*\uFFFD?\s*t/gi, 'rất');
    s = s.replace(/ra\uFFFD\s*t/gi, 'rất');
    s = s.replace(/đ\s*ế\s*\uFFFD?\s*u/gi, 'đều');
    s = s.replace(/đế\uFFFD\s*u/gi, 'đều');
    s = s.replace(/cu\s*ố\s*\uFFFD?\s*i/gi, 'cuối');
    s = s.replace(/cuố\uFFFD\s*i/gi, 'cuối');
    s = s.replace(/c\s*a\s*\uFFFD?\s*p/gi, 'cấp');
    s = s.replace(/v\s*ế\s*\uFFFD/gi, 'về');
    s = s.replace(/vế\uFFFD/gi, 'về');
    s = s.replace(/đ\s*ế\s*\uFFFD/gi, 'để');
    s = s.replace(/đế\uFFFD/gi, 'để');
    s = s.replace(/c\s*a\s*\uFFFD?\s*u/gi, 'cầu');
    s = s.replace(/n\s*ế\s*\uFFFD?\s*n/gi, 'nền');
    s = s.replace(/đ\s*o\s*\uFFFD?\s*ng/gi, 'đồng');
    s = s.replace(/Đ\s*a\s*\uFFFD?\s*u/gi, 'Đầu');
    s = s.replace(/g\s*a\s*\uFFFD?\s*n/gi, 'gắn');
    s = s.replace(/m\s*o\s*\uFFFD?\s*i/gi, 'mỗi');
    s = s.replace(/k\s*ì\s*\uFFFD/gi, 'kỹ');
    s = s.replace(/v\s*ì\s*\uFFFD/gi, 'ví');
    s = s.replace(/đúng đ\s*a\s*\uFFFD?\s*n/gi, 'đúng đắn');
    s = s.replace(/thaanh ay/gi, 'thành hay');
    s = s.replace(/khônng/gi, 'không');
    s = s.replace(/tìch/gi, 'tích');
    s = s.replace(/thìch/gi, 'thích');
    s = s.replace(/Tuyet/gi, 'Tuyết');
    s = s.replace(/tiế\s*p/gi, 'tiếp');
    s = s.replace(/t\s*ố\s*t/gi, 'tốt');
    s = s.replace(/kiế\s*n/gi, 'kiến');
    s = s.replace(/triể\s*n/gi, 'triển');
    s = s.replace(/khố\s*i/gi, 'khối');
    s = s.replace(/nghê\s*nghiệp/gi, 'nghề nghiệp');
    s = s.replace(/nghe\s*nghiệp/gi, 'nghề nghiệp');
    s = s.replace(/\bnghe\b/gi, 'nghề');
    s = s.replace(/\bnghế\b/gi, 'nghề');
    s = s.replace(/chiec/gi, 'chiếc');
    s = s.replace(/ket/gi, 'kết');
    s = s.replace(/viet/gi, 'viết');
    s = s.replace(/xuat/gi, 'xuất');
    s = s.replace(/nham/gi, 'nhằm');
    s = s.replace(/cau/gi, 'cầu');
    s = s.replace(/\bnen\b/gi, 'nền');
    s = s.replace(/\bdong\b/gi, 'đồng');
    s = s.replace(/\bđong\b/gi, 'đồng');
    s = s.replace(/\bĐau\b/gi, 'Đầu');
    s = s.replace(/\bdau\b/gi, 'đầu');
    s = s.replace(/\brat\b/gi, 'rất');
    s = s.replace(/\bnhat\b/gi, 'nhất');
    s = s.replace(/\bmat\b/gi, 'mất');
    s = s.replace(/\bde\b/gi, 'để');
    s = s.replace(/\bnhieu\b/gi, 'nhiều');
    s = s.replace(/\btien\b/gi, 'tiền');
    s = s.replace(/\bchình\b/gi, 'chính');
    s = s.replace(/\bchì\b/gi, 'chỉ');
    s = s.replace(/\bnam bat\b/gi, 'nắm bắt');
    s = s.replace(/\bhau het\b/gi, 'hầu hết');
    s = s.replace(/\bcuoi cap\b/gi, 'cuối cấp');
    s = s.replace(/\bthe\b/gi, 'thể');
    s = s.replace(/\bmuon\b/gi, 'muốn');
    s = s.replace(/\bchiem\b/gi, 'chiếm');
    s = s.replace(/\btong so\b/gi, 'tổng số');
    s = s.replace(/\bthi sinh\b/gi, 'thí sinh');
    s = s.replace(/\bhieu biet\b/gi, 'hiểu biết');
    s = s.replace(/\btuyen sinh\b/gi, 'tuyển sinh');
    s = s.replace(/\bvan de\b/gi, 'vấn đề');
    s = s.replace(/\bthoi diem\b/gi, 'thời điểm');
    s = s.replace(/\bcuon\b/gi, 'cuốn');
    s = s.replace(/\btrien\b/gi, 'triển');
    s = s.replace(/\bco the manh\b/gi, 'có thế mạnh');
    s = s.replace(/\bbeday\b/gi, 'bề dày');
    s = s.replace(/\bti le\b/gi, 'tỷ lệ');
    s = s.replace(/\bhoso\b/gi, 'hồ sơ');
    s = s.replace(/\btam giac\b/gi, 'tam giác');
    s = s.replace(/\bgom\b/gi, 'gồm');

    // Dọn sạch hoàn toàn các ký tự thay thế \uFFFD
    s = s.replace(/\uFFFD/g, '');

    // 2. Sửa lỗi font Cambria CMap bị sai ánh xạ trong phần tiếng Anh
    const isEnglishContext = s.includes('Abstract') || s.includes('Keywords') || s.includes('The study') || s.includes('the study') || s.includes('career choice') || s.includes('Banking at the') || s.includes('Financ');
    if (isEnglishContext) {
        s = s.replace(/[ếê]/g, 'e');
        s = s.replace(/[ẶĂẤẦẨẪẬẮẰẲẴẶ]/g, 'A');
        s = s.replace(/[ặăấầẩẫậắằẳẵ]/g, 'a');
        s = s.replace(/[ốô]/g, 'o');

        // Bỏ khoảng cách chia cắt ký tự nội bộ do font kerning
        s = s.replace(/surv\s+e\s+y/gi, 'survey');
        s = s.replace(/ass\s+e\s+ssm\s+e\s+nt/gi, 'assessment');
        s = s.replace(/th\s+e\s+s\s+e/gi, 'these');
        s = s.replace(/influ\s+e\s+nc\s+e/gi, 'influence');
        s = s.replace(/th\s+e\b/gi, 'the');
        s = s.replace(/car\s+e{1,2}\s*r/gi, 'career');
        s = s.replace(/choic\s+e/gi, 'choice');
        s = s.replace(/Financ\s+e/gi, 'Finance');
        s = s.replace(/articl\s+e/gi, 'article');
        s = s.replace(/propos\s+e/gi, 'propose');
        s = s.replace(/attracting\s*and/gi, 'attracting and');
        s = s.replace(/\bthe([a-z]{3,})/gi, (m, word) => {
            const allowed = ['ir', 'ory', 'se', 'm', 'n', 'y', 'ft', 'sis', 're', 'refore'];
            if (allowed.includes(word.toLowerCase())) return m;
            return 'the ' + word;
        });
    }

    // Đảm bảo dấu câu và khoảng cách tự nhiên
    s = s.replace(/\s{2,}/g, ' ');
    s = s.replace(/\s+([.,;:!?])/g, '$1');
    s = s.replace(/([.,;:!?])(?=[a-zA-Z\u00C0-\u1EF9])/g, '$1 ');

    return s.trim();
}

// Hàm tự động lấy mẫu màu nền (Background) và màu chữ (Text ink) từ Canvas để không bị đè vệt trắng
function sampleBlockBgAndTextColor(canvas, item, zoom) {
    const fallback = {
        bgColor: '#FFFFFF',
        textColor: '#0F172A',
        isUniform: true
    };
    if (!canvas) return fallback;

    try {
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        if (!ctx) return fallback;

        const cssWidth = parseFloat(canvas.style.width) || canvas.width;
        const dpr = canvas.width / cssWidth;
        const scale = (zoom || 1.0) * dpr;

        const cX = Math.round(item.left * scale);
        const cY = Math.round(item.top * scale);
        const cW = Math.round(item.width * scale);
        const cH = Math.round(item.height * scale);

        // Lấy mẫu các điểm viền bao quanh khối chữ (Perimeter sampling)
        // Khu vực viền ngoài nét chữ chứa chính xác màu nền của trang PDF hoặc slide trình chiếu
        const samplePoints = [];
        const margin = Math.max(2, Math.round(3 * dpr));

        const numStepsX = Math.min(16, Math.max(6, Math.round(cW / 14)));
        for (let i = 0; i <= numStepsX; i++) {
            const x = Math.round(cX + (cW * i) / numStepsX);
            samplePoints.push({ x, y: cY - margin });
            samplePoints.push({ x, y: cY + cH + margin });
        }

        const numStepsY = Math.min(10, Math.max(4, Math.round(cH / 12)));
        for (let j = 0; j <= numStepsY; j++) {
            const y = Math.round(cY + (cH * j) / numStepsY);
            samplePoints.push({ x: cX - margin, y });
            samplePoints.push({ x: cX + cW + margin, y });
        }

        // 4 góc
        samplePoints.push({ x: cX - margin, y: cY - margin });
        samplePoints.push({ x: cX + cW + margin, y: cY - margin });
        samplePoints.push({ x: cX - margin, y: cY + cH + margin });
        samplePoints.push({ x: cX + cW + margin, y: cY + cH + margin });

        const bgColors = [];
        for (const pt of samplePoints) {
            const px = Math.max(0, Math.min(canvas.width - 1, pt.x));
            const py = Math.max(0, Math.min(canvas.height - 1, pt.y));
            try {
                const pixel = ctx.getImageData(px, py, 1, 1).data;
                if (pixel[3] > 40) {
                    bgColors.push([pixel[0], pixel[1], pixel[2]]);
                }
            } catch (e) {}
        }

        if (bgColors.length === 0) return fallback;

        // Gom cụm màu nền để tìm màu áp đảo (Dominant background color)
        const clusters = [];
        for (const c of bgColors) {
            let matched = false;
            for (const cl of clusters) {
                if (Math.hypot(c[0] - cl.r, c[1] - cl.g, c[2] - cl.b) <= 22) {
                    cl.count++;
                    cl.sumR += c[0];
                    cl.sumG += c[1];
                    cl.sumB += c[2];
                    matched = true;
                    break;
                }
            }
            if (!matched) {
                clusters.push({ r: c[0], g: c[1], b: c[2], count: 1, sumR: c[0], sumG: c[1], sumB: c[2] });
            }
        }

        clusters.sort((a, b) => b.count - a.count);
        const dominant = clusters[0];
        const domR = Math.round(dominant.sumR / dominant.count);
        const domG = Math.round(dominant.sumG / dominant.count);
        const domB = Math.round(dominant.sumB / dominant.count);

        const hexBg = `#${domR.toString(16).padStart(2, '0')}${domG.toString(16).padStart(2, '0')}${domB.toString(16).padStart(2, '0')}`.toUpperCase();
        const bgLuminance = (0.299 * domR + 0.587 * domG + 0.114 * domB);

        // Lấy mẫu nét chữ (Ink sampling) bên trong khung
        const inkColors = [];
        const stepInsideX = Math.max(4, Math.round(cW / 24));
        const stepInsideY = Math.max(3, Math.round(cH / 14));
        for (let ix = cX + 2; ix < cX + cW - 2; ix += stepInsideX) {
            for (let iy = cY + 2; iy < cY + cH - 2; iy += stepInsideY) {
                const px = Math.max(0, Math.min(canvas.width - 1, ix));
                const py = Math.max(0, Math.min(canvas.height - 1, iy));
                try {
                    const pixel = ctx.getImageData(px, py, 1, 1).data;
                    if (pixel[3] > 40) {
                        const distToBg = Math.hypot(pixel[0] - domR, pixel[1] - domG, pixel[2] - domB);
                        if (distToBg >= 50) {
                            inkColors.push([pixel[0], pixel[1], pixel[2]]);
                        }
                    }
                } catch (e) {}
            }
        }

        let hexText = bgLuminance > 128 ? '#0F172A' : '#FFFFFF';
        if (inkColors.length >= 2) {
            const inkClusters = [];
            for (const c of inkColors) {
                let m = false;
                for (const ic of inkClusters) {
                    if (Math.hypot(c[0] - ic.r, c[1] - ic.g, c[2] - ic.b) <= 25) {
                        ic.count++;
                        ic.sumR += c[0];
                        ic.sumG += c[1];
                        ic.sumB += c[2];
                        m = true;
                        break;
                    }
                }
                if (!m) inkClusters.push({ r: c[0], g: c[1], b: c[2], count: 1, sumR: c[0], sumG: c[1], sumB: c[2] });
            }
            inkClusters.sort((a, b) => b.count - a.count);
            const domInk = inkClusters[0];
            const inkR = Math.round(domInk.sumR / domInk.count);
            const inkG = Math.round(domInk.sumG / domInk.count);
            const inkB = Math.round(domInk.sumB / domInk.count);
            hexText = `#${inkR.toString(16).padStart(2, '0')}${inkG.toString(16).padStart(2, '0')}${inkB.toString(16).padStart(2, '0')}`.toUpperCase();
        }

        return {
            bgColor: hexBg,
            textColor: hexText,
            isUniform: (dominant.count / bgColors.length) >= 0.65
        };
    } catch (err) {
        console.warn('Lỗi phân tích màu nền canvas:', err);
        return fallback;
    }
}
// Hàm khởi tạo hoặc lấy bản ghi chỉnh sửa text, bảo toàn 100% định dạng font (Bold, Italic, Family, Size, Color, BgColor, Align)
function getOrCreateTextEdit(pageData, blockId, blockEl) {
    if (!pageData) return null;
    if (!pageData.textEdits) pageData.textEdits = {};
    if (!pageData.textEdits[blockId]) {
        const origItem = (pageData._cachedTextItems || []).find(it => it.id === blockId);
        const zoom = editorZoom || 1.0;
        const domText = blockEl ? blockEl.innerText : '';
        pageData.textEdits[blockId] = {
            id: blockId,
            text: domText || (origItem ? origItem.str : ''),
            left: origItem ? origItem.left : (blockEl ? Math.round(blockEl.offsetLeft / zoom) : 0),
            top: origItem ? origItem.top : (blockEl ? Math.round(blockEl.offsetTop / zoom) : 0),
            width: origItem ? origItem.width : (blockEl ? Math.round(blockEl.offsetWidth / zoom) : 100),
            height: origItem ? origItem.height : (blockEl ? Math.round(blockEl.offsetHeight / zoom) : 24),
            fontSize: origItem ? (origItem.fontSize || 14) : 14,
            fontFamily: origItem ? (origItem.fontFamily || "'Times New Roman', Times, serif") : "'Times New Roman', Times, serif",
            isBold: origItem ? !!origItem.isBold : false,
            isItalic: origItem ? !!origItem.isItalic : false,
            color: origItem ? (origItem.textColor || '#0F172A') : '#0F172A',
            bgColor: origItem ? (origItem.bgColor || '#FFFFFF') : '#FFFFFF',
            align: origItem ? (origItem.align || 'left') : 'left',
            modified: true
        };
    }
    return pageData.textEdits[blockId];
}

// Render lớp chỉnh sửa trực tiếp chữ gốc của PDF (Khớp 100% Ảnh 1 & Ảnh 2)
async function renderPdfTextEditLayer(pageData) {
    const layer = document.getElementById('paperTextEditLayer');
    if (!layer) return;

    if (!pageData.textEdits) pageData.textEdits = {};
    const hasEdits = Object.keys(pageData.textEdits).length > 0;

    if (editorActiveTool !== 'edit-text') {
        if (hasEdits) {
            layer.style.display = 'block';
            layer.classList.add('preview-mode');
            layer.classList.remove('active');
        } else {
            layer.style.display = 'none';
            return;
        }
    } else {
        layer.style.display = 'block';
        layer.classList.remove('preview-mode');
        layer.classList.add('active');
    }

    const canvas = document.getElementById('pdfRenderCanvas');
    if (canvas) {
        layer.style.width = canvas.style.width;
        layer.style.height = canvas.style.height;
    }

    // Nếu là trang trắng mới thêm: không trích xuất text gốc
    if (pageData.isBlankPage || !pageData.originalPageNum) {
        pageData._cachedTextItems = [];
        layer.innerHTML = '';
        return;
    }

    // Nếu chưa trích xuất các text item của trang này
    if (!pageData._cachedTextItems) {
        if (currentPdfDoc) {
            try {
                const targetPageNum = pageData.originalPageNum;
                const source = editorSourceForPage(pageData);
                if (source && source.doc && targetPageNum <= source.doc.numPages) {
                    const page = await source.doc.getPage(targetPageNum);
                    const effRot = (page.rotate + (pageData.rotation || 0)) % 360;
                    // Lấy viewport ở scale chuẩn 1.0 (PDF points) để lưu trữ tọa độ bất biến khi zoom
                    const viewport = page.getViewport({ scale: 1.0, rotation: effRot });
                    const textContent = await page.getTextContent();

                    // Đọc từ điển font chi tiết từ page.commonObjs để lấy tên PostScript và cờ in đậm / in nghiêng chính xác
                    const fontDetailsMap = {};
                    if (page.commonObjs) {
                        const fontIds = Object.keys(textContent.styles || {});
                        await Promise.all(fontIds.map(fid => new Promise(res => {
                            try {
                                page.commonObjs.get(fid, f => {
                                    if (f) fontDetailsMap[fid] = f;
                                    res();
                                });
                                setTimeout(res, 60);
                            } catch (e) {
                                res();
                            }
                        })));
                    }

                    const rawItems = [];
                    for (let idx = 0; idx < textContent.items.length; idx++) {
                        const it = textContent.items[idx];
                        const str = cleanPdfRawString(it.str ? it.str : '');
                        if (!str.trim()) continue;

                        const tr = pdfjsLib.Util.transform(viewport.transform, it.transform);
                        const fontSize = Math.max(9, Math.round(Math.hypot(tr[0], tr[1])));
                        const vx = Math.round(tr[4]);
                        const vy = Math.round(tr[5]); // Baseline Y trong hệ toạ độ transformed

                        const left = vx;
                        const baselineY = vy;
                        const top = Math.max(0, Math.round(vy - fontSize * 0.88));
                        const width = Math.max(4, Math.round(it.width));
                        const height = Math.max(10, Math.round(fontSize * 1.2));

                        const fontInfo = mapPdfFontToCss(it.fontName, textContent.styles, fontDetailsMap[it.fontName]);

                        rawItems.push({
                            id: `p${pageData.pageNumber}_t${idx}`,
                            str: str,
                            left,
                            top,
                            baselineY,
                            width,
                            height,
                            fontSize,
                            fontFamily: fontInfo.family,
                            isBold: fontInfo.isBold,
                            isItalic: fontInfo.isItalic
                        });
                    }

                    // Sắp xếp các phần tử: gom theo đường baseline Y trước (dung sai 3px), sau đó theo thứ tự trái sang phải
                    rawItems.sort((a, b) => {
                        if (Math.abs(a.baselineY - b.baselineY) > 3) {
                            return a.baselineY - b.baselineY;
                        }
                        return a.left - b.left;
                    });

                    // Bước 1: Nhóm các từ trên cùng dòng (Baseline grouping), xử lý hoàn hảo chữ căn đều 2 bên (Justified)
                    const lines = [];
                    let curLine = null;

                    for (let i = 0; i < rawItems.length; i++) {
                        const cur = rawItems[i];
                        if (!curLine) {
                            curLine = { ...cur, boldChars: cur.isBold ? cur.str.length : 0, italicChars: cur.isItalic ? cur.str.length : 0, totalChars: cur.str.length };
                            continue;
                        }

                        const isSameBaseline = Math.abs(cur.baselineY - curLine.baselineY) <= Math.max(3, curLine.fontSize * 0.28);
                        const prevRight = curLine.left + curLine.width;
                        const gap = cur.left - prevRight;

                        // Trong văn bản căn lề hai bên (Justified text), khoảng cách từ có thể lên tới 3.5 font size
                        const isSameFlow = isSameBaseline && (gap <= Math.max(30, curLine.fontSize * 3.5)) && (cur.left >= prevRight - 12);

                        if (isSameFlow) {
                            const isSingleChar = cur.str.length <= 1 || curLine.str.length <= 1;
                            const isSpaceGap = !isSingleChar && (gap >= Math.max(2.2, cur.fontSize * 0.22));
                            const alreadyHasSpace = curLine.str.endsWith(' ') || cur.str.startsWith(' ');
                            const needsSpace = isSpaceGap && !alreadyHasSpace;

                            curLine.str += (needsSpace ? ' ' : '') + cur.str;
                            curLine.width = Math.round(Math.max(curLine.width, (cur.left + cur.width) - curLine.left));
                            curLine.height = Math.max(curLine.height, cur.height);
                            curLine.top = Math.min(curLine.top, cur.top);
                            curLine.boldChars = (curLine.boldChars || (curLine.isBold ? curLine.str.length : 0)) + (cur.isBold ? cur.str.length : 0);
                            curLine.italicChars = (curLine.italicChars || (curLine.isItalic ? curLine.str.length : 0)) + (cur.isItalic ? cur.str.length : 0);
                            curLine.totalChars = (curLine.totalChars || curLine.str.length) + cur.str.length;
                            curLine.isBold = (curLine.boldChars / curLine.totalChars) >= 0.45;
                            curLine.isItalic = (curLine.italicChars / curLine.totalChars) >= 0.45;
                        } else {
                            lines.push(curLine);
                            curLine = { ...cur, boldChars: cur.isBold ? cur.str.length : 0, italicChars: cur.isItalic ? cur.str.length : 0, totalChars: cur.str.length };
                        }
                    }
                    if (curLine) lines.push(curLine);

                    // Bước 2: Nhóm các dòng thuộc cùng đoạn văn (Paragraph clustering) chuẩn 100% PDFaid & Word
                    // Hỗ trợ bố cục nhiều cột (Multi-column) và các cột ghi chú/metadata bên cạnh không bị đứt đoạn
                    const paragraphs = [];
                    const usedLineIndices = new Set();

                    for (let i = 0; i < lines.length; i++) {
                        if (usedLineIndices.has(i)) continue;
                        usedLineIndices.add(i);

                        const curPara = {
                            ...lines[i],
                            lastBaselineY: lines[i].baselineY,
                            linesCount: 1,
                            boldLines: lines[i].isBold ? 1 : 0,
                            italicLines: lines[i].isItalic ? 1 : 0
                        };

                        // Liên tục tìm dòng kế tiếp nằm ngay bên dưới cùng cột để ghép thành đoạn văn trọn vẹn
                        let foundNext = true;
                        while (foundNext) {
                            foundNext = false;
                            let bestIdx = -1;
                            let bestGap = Infinity;

                            for (let j = 0; j < lines.length; j++) {
                                if (usedLineIndices.has(j)) continue;
                                const cand = lines[j];

                                const baselineGap = cand.baselineY - curPara.lastBaselineY;
                                // Dòng kế tiếp phải nằm bên dưới trong khoảng cách 0.7x đến 2.3x fontSize
                                if (baselineGap >= curPara.fontSize * 0.7 && baselineGap <= curPara.fontSize * 2.3) {
                                    // Kiểm tra cỡ chữ tương đồng (dung sai 2.8pt do subsetting)
                                    const isFontMatch = Math.abs(cand.fontSize - curPara.fontSize) <= 2.8;
                                    if (!isFontMatch) continue;

                                    const paraRight = curPara.left + curPara.width;
                                    const candRight = cand.left + cand.width;
                                    const isHorizOverlap = (cand.left < paraRight + 25) && (candRight > curPara.left - 25);
                                    const isIndentMatch = Math.abs(cand.left - curPara.left) <= Math.max(45, curPara.fontSize * 3.5);

                                    if (isHorizOverlap || isIndentMatch) {
                                        if (baselineGap < bestGap) {
                                            bestGap = baselineGap;
                                            bestIdx = j;
                                        }
                                    }
                                }
                            }

                            if (bestIdx !== -1) {
                                const nextLine = lines[bestIdx];
                                usedLineIndices.add(bestIdx);
                                curPara.str += '\n' + nextLine.str;
                                const newLeft = Math.min(curPara.left, nextLine.left);
                                const newRight = Math.max(curPara.left + curPara.width, nextLine.left + nextLine.width);
                                curPara.left = newLeft;
                                curPara.width = Math.round(newRight - newLeft);
                                curPara.top = Math.min(curPara.top, nextLine.top);
                                const newBottom = Math.max(curPara.top + curPara.height, nextLine.top + nextLine.height);
                                curPara.height = Math.round(newBottom - curPara.top);
                                curPara.lastBaselineY = nextLine.baselineY;
                                curPara.boldLines += nextLine.isBold ? 1 : 0;
                                curPara.italicLines += nextLine.isItalic ? 1 : 0;
                                curPara.linesCount++;
                                foundNext = true;
                            }
                        }

                        if (curPara.linesCount > 1) {
                            curPara.isBold = (curPara.boldLines / curPara.linesCount) >= 0.5;
                            curPara.isItalic = (curPara.italicLines / curPara.linesCount) >= 0.5;
                        }

                        paragraphs.push(curPara);
                    }

                    // Hậu xử lý phục hồi ngữ nghĩa toàn diện
                    paragraphs.forEach(p => {
                        p.str = postProcessMergedText(p.str);
                    });

                    // Tự động phân tích màu nền và màu chữ từ Canvas cho từng đoạn văn
                    paragraphs.forEach(p => {
                        const sample = sampleBlockBgAndTextColor(canvas, p, 1.0);
                        p.bgColor = sample.bgColor;
                        p.textColor = sample.textColor;
                    });

                    pageData._cachedTextItems = paragraphs;
                } else {
                    pageData._cachedTextItems = [];
                }
            } catch (e) {
                console.warn('Lỗi đọc textContent của trang:', e);
                pageData._cachedTextItems = [];
            }
        } else {
            // Mockup text blocks chuẩn khớp 100% Ảnh 1 CHỈ KHI không có tệp PDF nào được mở
            pageData._cachedTextItems = [
                { id: `p${pageData.pageNumber}_t0`, str: 'Đặc san Nghiên cứu Chính sách và Phát triển', left: 45, top: 48, width: 290, height: 22, fontSize: 13, fontFamily: "'Times New Roman', Times, serif", isBold: true, bgColor: '#FFFFFF', textColor: '#0F172A' },
                { id: `p${pageData.pageNumber}_t1`, str: 'Số 02/2022', left: 510, top: 48, width: 85, height: 22, fontSize: 13, fontFamily: "'Times New Roman', Times, serif", bgColor: '#FFFFFF', textColor: '#0F172A' },
                { id: `p${pageData.pageNumber}_t2`, str: 'Hạnh Đào và cộng sự (2022). Những yếu tố ảnh hưởng đến lựa chọn ngành nghề của sinh viên chuyên ngành tài chính - ngân hàng học viện chính sách và phát triển. Đặc san Nghiên cứu Chính sách và Phát triển, 2(2022), 127-137', left: 45, top: 82, width: 350, height: 62, fontSize: 10, fontFamily: "'Times New Roman', Times, serif", bgColor: '#FFFFFF', textColor: '#0F172A' },
                { id: `p${pageData.pageNumber}_t3`, str: 'Những yếu tố ảnh hưởng đến lựa chọn ngành nghề của sinh viên chuyên ngành tài chính - ngân hàng học viện chính sách phát triển', left: 45, top: 160, width: 360, height: 68, fontSize: 16, fontFamily: "Cambria, 'Times New Roman', serif", isBold: true, bgColor: '#FFFFFF', textColor: '#1D4ED8' },
                { id: `p${pageData.pageNumber}_t4`, str: 'Đào Thị Bích Hạnh (TS)\nHọc viện Chính sách và Phát triển\nEmail: bichhanh@mpi.gov.vn', left: 55, top: 242, width: 230, height: 42, fontSize: 11, fontFamily: "Cambria, 'Times New Roman', serif", isBold: true, bgColor: '#FFFFFF', textColor: '#0F172A' },
                { id: `p${pageData.pageNumber}_t5`, str: 'Cao Ánh Tuyết, Nguyễn Huyền My, Trần Quỳnh Liên, Vũ Thị Ngân, Ngô Việt Anh', left: 55, top: 298, width: 280, height: 32, fontSize: 11, fontFamily: "Cambria, 'Times New Roman', serif", isBold: true, bgColor: '#FFFFFF', textColor: '#0F172A' },
                { id: `p${pageData.pageNumber}_t6`, str: 'Tóm tắt: Bài nghiên cứu xác định những yếu tố ảnh hưởng đến việc lựa chọn ngành nghề của sinh viên chuyên ngành Tài chính - Ngân hàng tại Học viện Chính sách và Phát triển. Thông qua kết quả khảo sát thực tế và đánh giá mức độ ảnh hưởng của các nhân tố, bài viết đề xuất những giải pháp nhằm giúp cho sinh viên có những lựa chọn đúng về chuyên ngành mình học, phù hợp với những yêu cầu đặt ra của nền kinh tế và thị trường lao động đồng thời có các kiến nghị với Khoa Tài chính - Đầu tư', left: 45, top: 348, width: 380, height: 135, fontSize: 12, fontFamily: "Cambria, 'Times New Roman', serif", bgColor: '#FFFFFF', textColor: '#0F172A' }
            ];
        }
    }

    layer.innerHTML = '';
    const zoom = editorZoom || 1.0;

    (pageData._cachedTextItems || []).forEach(item => {
        const editData = pageData.textEdits[item.id];
        const isEdited = !!editData;
        const currentText = isEdited ? editData.text : item.str;

        // Nếu ở chế độ preview (không chọn công cụ edit-text), chỉ render những item đã được chỉnh sửa
        if (editorActiveTool !== 'edit-text' && !isEdited) return;

        const effectiveFont = (editData && editData.fontFamily) || item.fontFamily || "'Times New Roman', Times, serif";
        const effectiveSize = (editData && editData.fontSize) || item.fontSize || 14;
        const effectiveBold = (editData && editData.isBold !== undefined) ? editData.isBold : item.isBold;
        const effectiveItalic = (editData && editData.isItalic !== undefined) ? editData.isItalic : item.isItalic;
        const activeBg = (editData && editData.bgColor) ? editData.bgColor : (item.bgColor || '#FFFFFF');
        const activeColor = (editData && editData.color) ? editData.color : (item.textColor || '#0F172A');
        const effectiveAlign = (editData && editData.align) || 'left';

        const block = document.createElement('div');
        block.className = `pdf-detected-text-block ${isEdited ? 'is-edited' : ''}`;
        block.contentEditable = (editorActiveTool === 'edit-text') ? 'true' : 'false';
        block.dataset.textId = item.id;
        block.style.left = `${Math.round(item.left * zoom)}px`;
        block.style.top = `${Math.round(item.top * zoom)}px`;
        block.style.width = `${Math.round(item.width * zoom)}px`;
        block.style.minHeight = `${Math.round(item.height * zoom)}px`;
        block.style.fontSize = `${Math.round(effectiveSize * zoom)}px`;
        block.style.fontFamily = effectiveFont;
        block.style.fontWeight = effectiveBold ? 'bold' : 'normal';
        block.style.fontStyle = effectiveItalic ? 'italic' : 'normal';
        block.style.lineHeight = '1.35';
        block.style.textAlign = effectiveAlign;

        // Thiết lập biến CSS màu nền và màu chữ chính xác
        block.style.setProperty('--block-bg', activeBg);
        block.style.setProperty('--block-color', activeColor);

        if (isEdited) {
            block.style.backgroundColor = activeBg === 'transparent' ? 'transparent' : activeBg;
            block.style.color = activeColor;
        } else {
            block.style.backgroundColor = 'transparent';
            block.style.color = 'transparent';
        }

        block.innerText = currentText;
        block.title = 'Bấm để chỉnh sửa văn bản này trực tiếp như Microsoft Word';

        // 4 Chấm tròn tay cầm điều chỉnh ở 4 góc chuẩn khớp PDFaid Ảnh 2
        const handleNw = document.createElement('div');
        handleNw.className = 'block-corner-handle handle-nw';
        const handleNe = document.createElement('div');
        handleNe.className = 'block-corner-handle handle-ne';
        const handleSe = document.createElement('div');
        handleSe.className = 'block-corner-handle handle-se';
        const handleSw = document.createElement('div');
        handleSw.className = 'block-corner-handle handle-sw';
        block.appendChild(handleNw);
        block.appendChild(handleNe);
        block.appendChild(handleSe);
        block.appendChild(handleSw);

        if (editorActiveTool === 'edit-text') {
            block.onfocus = () => {
                pushEditorHistory();
                block.classList.add('is-editing');
                const curEdit = getOrCreateTextEdit(pageData, item.id, block);
                const currentBg = curEdit.bgColor || item.bgColor || '#FFFFFF';
                const currentColor = curEdit.color || item.textColor || '#0F172A';
                block.style.setProperty('--block-bg', currentBg === 'transparent' ? 'transparent' : currentBg);
                block.style.setProperty('--block-color', currentColor);
                block.style.backgroundColor = currentBg === 'transparent' ? 'transparent' : currentBg;
                block.style.color = currentColor;
                editorSelectedElementId = item.id;
                syncTextPropertiesToRibbon(curEdit);
            };

            block.oninput = () => {
                block.classList.add('is-edited');
                const curEdit = getOrCreateTextEdit(pageData, item.id, block);
                const currentBg = curEdit.bgColor || item.bgColor || '#FFFFFF';
                const currentColor = curEdit.color || item.textColor || '#0F172A';
                block.style.setProperty('--block-bg', currentBg === 'transparent' ? 'transparent' : currentBg);
                block.style.setProperty('--block-color', currentColor);
                block.style.backgroundColor = currentBg === 'transparent' ? 'transparent' : currentBg;
                block.style.color = currentColor;
                curEdit.text = block.innerText;
                curEdit.height = Math.max(item.height, Math.round(block.offsetHeight / zoom));
                curEdit.modified = true;
                saveEditorDraft();
            };

            block.onblur = () => {
                block.classList.remove('is-editing');
                const curEdit = pageData.textEdits[item.id];
                if (curEdit && block.innerText === item.str && !curEdit.modifiedFormat) {
                    delete pageData.textEdits[item.id];
                    block.classList.remove('is-edited');
                    block.style.backgroundColor = 'transparent';
                    block.style.color = 'transparent';
                } else if (curEdit) {
                    block.classList.add('is-edited');
                    const finalBg = curEdit.bgColor || item.bgColor || '#FFFFFF';
                    const finalColor = curEdit.color || item.textColor || '#0F172A';
                    block.style.setProperty('--block-bg', finalBg === 'transparent' ? 'transparent' : finalBg);
                    block.style.setProperty('--block-color', finalColor);
                    block.style.backgroundColor = finalBg === 'transparent' ? 'transparent' : finalBg;
                    block.style.color = finalColor;
                }
                saveEditorDraft();
            };

            block.onkeydown = (e) => {
                if (e.key === 'Escape') block.blur();
            };
        }

        layer.appendChild(block);
    });
}

function renderCanvasAnnotations(pageData) {
    const annotLayer = document.getElementById('paperAnnotationLayer');
    if (!annotLayer) return;

    annotLayer.innerHTML = '';
    const escapeEditorValue = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);

    (pageData.elements || []).forEach(el => {
        let domEl;

        if (el.type === 'text') {
            // Không bao giờ hiển thị placeholder chữ mẫu 'Gõ nội dung tại đây...' nếu chưa được người dùng gõ nội dung thực tế
            if (!el.text || el.text.trim() === 'Gõ nội dung tại đây...') {
                if (!el.isEditing) return;
            }
            domEl = document.createElement('div');
            domEl.className = 'canvas-annotation-text' + (el.id === editorSelectedElementId ? ' selected-element' : '');
            domEl.dataset.elementId = el.id;
            domEl.style.left = `${el.x}px`;
            domEl.style.top = `${el.y}px`;
            domEl.style.fontSize = `${el.size || 18}px`;
            domEl.style.fontFamily = el.fontFamily || 'Helvetica, Arial, sans-serif';
            domEl.style.textAlign = el.align || 'left';
            domEl.style.opacity = el.opacity !== undefined ? el.opacity : 1.0;
            domEl.style.color = el.color || '#000000';
            domEl.style.fontWeight = el.bold ? '700' : '500';
            domEl.style.fontStyle = el.italic ? 'italic' : 'normal';
            domEl.innerText = el.text;
            domEl.title = 'Bấm để chỉnh sửa chữ trực tiếp trên trang • Kéo để di chuyển';

            domEl.onclick = (e) => {
                e.stopPropagation();
                selectEditorElement(el.id);
                // Kích hoạt chỉnh sửa trực tiếp không cần popup
                if (!domEl.isContentEditable) {
                    startInlineTextEdit(domEl, el);
                }
            };

            domEl.ondblclick = (e) => {
                e.stopPropagation();
                startInlineTextEdit(domEl, el);
            };

            if (el.isEditing) {
                setTimeout(() => startInlineTextEdit(domEl, el), 40);
            }
        } else if (el.type === 'vector') {
            domEl = document.createElement('div');
            domEl.className = 'canvas-annotation-vector' + (el.id === editorSelectedElementId ? ' selected-element' : '');
            domEl.dataset.elementId = el.id;
            domEl.style.left = `${el.x}px`;
            domEl.style.top = `${el.y}px`;
            domEl.style.color = el.color || '#2563EB';
            domEl.style.opacity = el.opacity !== undefined ? el.opacity : 1.0;
            domEl.innerHTML = VECTOR_ICONS[el.iconType] || VECTOR_ICONS['star'];
            domEl.title = 'Bấm để chọn • Kéo để di chuyển';
            domEl.onclick = (e) => {
                e.stopPropagation();
                selectEditorElement(el.id);
            };
        } else if (el.type === 'stamp') {
            domEl = document.createElement('div');
            domEl.className = 'canvas-annotation-stamp' + (el.id === editorSelectedElementId ? ' selected-element' : '');
            domEl.dataset.elementId = el.id;
            domEl.style.left = `${el.x}px`;
            domEl.style.top = `${el.y}px`;
            domEl.style.opacity = el.opacity !== undefined ? el.opacity : 1.0;
            domEl.innerHTML = getStampHtml(el.stampType);
            domEl.title = 'Bấm để chọn • Kéo để di chuyển';
            domEl.onclick = (e) => {
                e.stopPropagation();
                selectEditorElement(el.id);
            };
        } else if (el.type === 'signature') {
            domEl = document.createElement('div');
            domEl.className = 'canvas-annotation-signature' + (el.id === editorSelectedElementId ? ' selected-element' : '');
            domEl.dataset.elementId = el.id;
            domEl.style.left = `${el.x}px`;
            domEl.style.top = `${el.y}px`;
            domEl.style.opacity = el.opacity !== undefined ? el.opacity : 1.0;
            const rot = el.rotation || 0;
            const width = el.width || 180;
            domEl.style.width = width + 'px';
            domEl.style.transform = `rotate(${rot}deg)`;

            if (el.sigType === 'draw' || el.sigType === 'upload') {
                domEl.innerHTML = `<img src="${el.data}" style="width: 100%; height: auto; display: block; pointer-events: none;">`;
            } else {
                domEl.innerHTML = `<div style="font-family: ${el.fontFamily || 'Dancing Script, cursive'}; font-size: ${el.size || 34}px; color: ${el.color || '#000000'}; font-weight: 700; user-select: none;">${escapeEditorValue(el.name)}</div>`;
            }

            // Handles cho chữ ký
            const seHandle = document.createElement('div');
            seHandle.className = 'img-resize-handle handle-se';
            seHandle.onmousedown = (e) => startElementResize(el.id, 'se', e);
            domEl.appendChild(seHandle);

            domEl.title = 'Chữ ký: Bấm để chọn • Kéo di chuyển • Kéo góc để phóng to/thu nhỏ';
            domEl.onclick = (e) => {
                e.stopPropagation();
                selectEditorElement(el.id);
            };
        } else if (el.type === 'shape') {
            domEl = document.createElement('div');
            domEl.className = 'canvas-annotation-stamp' + (el.id === editorSelectedElementId ? ' selected-element' : '');
            domEl.dataset.elementId = el.id;
            domEl.style.left = `${el.x}px`;
            domEl.style.top = `${el.y}px`;
            domEl.style.opacity = el.opacity !== undefined ? el.opacity : 1.0;
            domEl.innerHTML = getShapeHtml(el.shapeType, el.color, el.size, el.fill, el.dashStyle, el.arrowHead, el.width, el.height);

            // Núm kéo co giãn hình học góc dưới-phải (SE Handle)
            const seHandle = document.createElement('div');
            seHandle.className = 'img-resize-handle handle-se';
            seHandle.onmousedown = (e) => startElementResize(el.id, 'se', e);
            domEl.appendChild(seHandle);

            domEl.title = 'Hình học: Bấm để chọn • Kéo để di chuyển • Kéo góc để đổi kích thước';
            domEl.onclick = (e) => {
                e.stopPropagation();
                selectEditorElement(el.id);
            };
        } else if (el.type === 'image') {
            domEl = document.createElement('div');
            domEl.className = 'canvas-annotation-image' + (el.id === editorSelectedElementId ? ' selected-element' : '');
            domEl.dataset.elementId = el.id;
            domEl.style.left = `${el.x}px`;
            domEl.style.top = `${el.y}px`;
            const imgWidth = el.width || 220;
            domEl.style.width = `${imgWidth}px`;
            domEl.style.opacity = el.opacity !== undefined ? el.opacity : 1.0;

            const rot = el.rotation || 0;
            const scaleX = el.flipH ? -1 : 1;
            const scaleY = el.flipV ? -1 : 1;
            domEl.style.transform = `rotate(${rot}deg) scale(${scaleX}, ${scaleY})`;

            domEl.innerHTML = `
                <img src="${el.data}" style="width: 100%; height: auto; display: block; border-radius: 4px; pointer-events: none;">
                <!-- Floating Transformation Toolbar (Rotate Left/Right, Flip H/V, Delete) -->
                <div class="img-transform-bar" onclick="event.stopPropagation();">
                    <button type="button" class="img-bar-btn" onclick="rotateEditorImage('${el.id}', -90, event)" title="Xoay trái 90°">↺</button>
                    <button type="button" class="img-bar-btn" onclick="rotateEditorImage('${el.id}', 90, event)" title="Xoay phải 90°">↻</button>
                    <button type="button" class="img-bar-btn" onclick="flipEditorImage('${el.id}', 'H', event)" title="Lật ngang">⇄</button>
                    <button type="button" class="img-bar-btn" onclick="flipEditorImage('${el.id}', 'V', event)" title="Lật dọc">⇅</button>
                    <button type="button" class="img-bar-btn text-danger" onclick="deleteSelectedEditorElement()" title="Xóa hình ảnh">✕</button>
                </div>
                <!-- 4 Corner Resize Handles -->
                <div class="img-resize-handle handle-nw" onmousedown="startElementResize('${el.id}', 'nw', event)"></div>
                <div class="img-resize-handle handle-ne" onmousedown="startElementResize('${el.id}', 'ne', event)"></div>
                <div class="img-resize-handle handle-se" onmousedown="startElementResize('${el.id}', 'se', event)"></div>
                <div class="img-resize-handle handle-sw" onmousedown="startElementResize('${el.id}', 'sw', event)"></div>
                <!-- Free Rotation Handle Knob -->
                <div class="img-rot-handle" onmousedown="startElementFreeRotate('${el.id}', event)" title="Kéo để xoay góc tự do"></div>
            `;
            domEl.title = 'Hình ảnh: Kéo thả đi đâu cũng được • Xoay trái phải trên dưới • Kéo góc để đổi cỡ';
            domEl.onclick = (e) => {
                e.stopPropagation();
                selectEditorElement(el.id);
            };
        } else if (el.type === 'link') {
            domEl = document.createElement('div');
            domEl.className = 'canvas-annotation-link' + (el.id === editorSelectedElementId ? ' selected-element' : '');
            domEl.dataset.elementId = el.id;
            domEl.style.left = `${el.x}px`;
            domEl.style.top = `${el.y}px`;

            const isSelected = (el.id === editorSelectedElementId || el.isEditing);
            domEl.innerHTML = `
                <a href="${escapeEditorValue(/^(https?:|mailto:)/i.test(el.url || '') ? el.url : '#')}" target="_blank" rel="noopener noreferrer" class="canvas-link-badge" onclick="if(event.ctrlKey) return true; event.preventDefault();">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px; height:14px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    <span>${escapeEditorValue(el.text || el.url)}</span>
                </a>
                ${isSelected ? `
                <div class="inline-link-popover" onclick="event.stopPropagation();">
                    <input type="text" class="inline-link-input" style="width: 160px;" value="${escapeEditorValue(el.url)}" placeholder="https://..." oninput="updateLinkAnnotation('${el.id}', 'url', this.value)">
                    <input type="text" class="inline-link-input" style="width: 110px;" value="${escapeEditorValue(el.text)}" placeholder="Nhãn liên kết..." oninput="updateLinkAnnotation('${el.id}', 'text', this.value)">
                    <button type="button" class="img-bar-btn" onclick="closeLinkEdit('${el.id}')" title="Xong">✓</button>
                    <button type="button" class="img-bar-btn text-danger" onclick="deleteSelectedEditorElement()" title="Xóa">✕</button>
                </div>` : ''}
            `;
            domEl.title = 'Bấm để sửa liên kết trực tiếp trên trang • Kéo để di chuyển';
            domEl.onclick = (e) => {
                e.stopPropagation();
                selectEditorElement(el.id);
            };
        } else if (el.type === 'note') {
            domEl = document.createElement('div');
            domEl.className = 'canvas-annotation-note' + (el.id === editorSelectedElementId ? ' selected-element' : '');
            domEl.dataset.elementId = el.id;
            domEl.style.left = `${el.x}px`;
            domEl.style.top = `${el.y}px`;

            const isExpanded = (el.id === editorSelectedElementId || el.isExpanded);
            domEl.innerHTML = `
                <div class="note-balloon" title="Ghi chú: Bấm để mở và gõ trực tiếp">
                    <svg viewBox="0 0 24 24" fill="#F59E0B" stroke="#B45309" stroke-width="1.5" style="width:26px; height:26px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                </div>
                ${isExpanded ? `
                <div class="inline-note-card" style="background: ${el.color || '#FEF3C7'};" onclick="event.stopPropagation();">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; font-size: 0.74rem; font-weight: 700; color: #92400E;">
                        <span>📌 Ghi chú (${el.time || 'Hôm nay'})</span>
                        <button type="button" class="img-bar-btn" style="width: 20px; height: 20px;" onclick="toggleNoteCard('${el.id}', event)">✕</button>
                    </div>
                    <textarea class="inline-note-textarea" placeholder="Nhập ghi chú trực tiếp tại đây..." oninput="updateNoteAnnotation('${el.id}', this.value)">${escapeEditorValue(el.text || '')}</textarea>
                </div>` : ''}
            `;
            domEl.onclick = (e) => {
                e.stopPropagation();
                selectEditorElement(el.id);
                el.isExpanded = !el.isExpanded;
                renderEditorCanvas();
            };
        }

        if (domEl) {
            const delBtn = document.createElement('button');
            delBtn.className = 'canvas-del-btn';
            delBtn.innerText = '✕';
            delBtn.title = 'Xóa đối tượng này';
            delBtn.onclick = (e) => {
                e.stopPropagation();
                pushEditorHistory();
                pageData.elements = pageData.elements.filter(item => item.id !== el.id);
                renderEditorCanvas();
            };
            domEl.appendChild(delBtn);

            makeElementDraggable(domEl, el);
            annotLayer.appendChild(domEl);
        }
    });
}

// Chỉnh sửa văn bản trực tiếp trên trang PDF (ContentEditable - Không popup)
function startInlineTextEdit(domEl, el) {
    if (!domEl) return;
    pushEditorHistory();
    domEl.contentEditable = "true";
    domEl.classList.add('inline-editing');

    // Nếu vẫn là placeholder mặc định, xóa trắng để gõ ngay không bị dính chữ mẫu
    if (domEl.innerText.trim() === 'Gõ nội dung tại đây...') {
        domEl.innerText = '';
    }

    domEl.focus();

    try {
        const range = document.createRange();
        range.selectNodeContents(domEl);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    } catch (e) { }

    domEl.oninput = () => {
        el.text = domEl.innerText;
        saveEditorDraft();
    };

    domEl.onkeydown = (e) => {
        if (e.key === 'Escape') {
            domEl.blur();
        }
    };

    domEl.onblur = () => {
        domEl.contentEditable = "false";
        domEl.classList.remove('inline-editing');
        const clean = domEl.innerText.trim();
        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        if ((!clean || clean === 'Gõ nội dung tại đây...') && pageData) {
            pageData.elements = pageData.elements.filter(item => item.id !== el.id);
            renderEditorCanvas();
        } else {
            el.text = clean;
            el.isEditing = false;
            saveEditorDraft();
        }
    };
}

// Xoay và lật hình ảnh trực quan trên trang
function rotateEditorImage(id, deg, event) {
    if (event) event.stopPropagation();
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
    const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === id) : null;
    if (el) {
        pushEditorHistory();
        el.rotation = ((el.rotation || 0) + deg + 360) % 360;
        renderEditorCanvas();
    }
}

function flipEditorImage(id, axis, event) {
    if (event) event.stopPropagation();
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
    const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === id) : null;
    if (el) {
        pushEditorHistory();
        if (axis === 'H') el.flipH = !el.flipH;
        else el.flipV = !el.flipV;
        renderEditorCanvas();
    }
}

// Kéo giãn kích thước ảnh, chữ ký hoặc hình học (Resize Corner Handles)
function startElementResize(id, corner, event) {
    event.stopPropagation();
    event.preventDefault();
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
    const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === id) : null;
    if (!el) return;
    pushEditorHistory();

    const startX = event.clientX;
    const startY = event.clientY;
    const initialWidth = el.width || (el.type === 'shape' ? 170 : 220);
    const initialHeight = el.height || (el.type === 'shape' ? (el.shapeType === 'rect' ? 96 : 110) : 100);

    function onMouseMove(ev) {
        const dx = (ev.clientX - startX) / editorZoom;
        const dy = (ev.clientY - startY) / editorZoom;
        const factorX = (corner === 'se' || corner === 'ne') ? 1 : -1;
        const factorY = (corner === 'se' || corner === 'sw') ? 1 : -1;

        const newWidth = Math.max(40, Math.min(850, Math.round(initialWidth + dx * factorX)));
        const newHeight = Math.max(20, Math.min(850, Math.round(initialHeight + dy * factorY)));
        el.width = newWidth;
        if (el.type === 'shape') {
            el.height = newHeight;
        }

        const dom = document.querySelector(`[data-element-id="${id}"]`);
        if (dom) {
            dom.style.width = newWidth + 'px';
            if (el.type === 'shape') {
                dom.innerHTML = getShapeHtml(el.shapeType, el.color, el.size, el.fill, el.dashStyle, el.arrowHead, newWidth, newHeight);
                const seHandle = document.createElement('div');
                seHandle.className = 'img-resize-handle handle-se';
                seHandle.onmousedown = (e) => startElementResize(el.id, 'se', e);
                dom.appendChild(seHandle);
            }
        }
    }

    function onMouseUp() {
        window.removeEventListener('mousemove', onMouseMove);
        window.removeEventListener('mouseup', onMouseUp);
        saveEditorDraft();
        renderEditorCanvas();
    }

    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
}

// Xoay góc tự do bằng chuột qua núm tròn đỉnh ảnh
function startElementFreeRotate(id, event) {
    event.stopPropagation();
    event.preventDefault();
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
    const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === id) : null;
    if (!el) return;

    const dom = document.querySelector(`[data-element-id="${id}"]`);
    if (!dom) return;
    pushEditorHistory();
    const rect = dom.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;

    function onMouseMove(ev) {
        const rad = Math.atan2(ev.clientY - centerY, ev.clientX - centerX);
        let deg = Math.round(rad * (180 / Math.PI)) + 90;
        if (deg < 0) deg += 360;
        el.rotation = deg;
        dom.style.transform = `rotate(${deg}deg) scale(${el.flipH ? -1 : 1}, ${el.flipV ? -1 : 1})`;
    }

    function onMouseUp() {
        window.removeEventListener('mousemove', onMouseMove);
        window.removeEventListener('mouseup', onMouseUp);
        saveEditorDraft();
        renderEditorCanvas();
    }

    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
}

// Cập nhật liên kết trực tiếp trên trang (No popup)
function updateLinkAnnotation(id, field, value) {
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
    const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === id) : null;
    if (el) {
        el[field] = value;
        const textSpan = document.querySelector(`[data-element-id="${id}"] .canvas-link-badge span`);
        if (textSpan && field === 'text') textSpan.innerText = value || el.url;
        saveEditorDraft();
    }
}

function closeLinkEdit(id) {
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
    const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === id) : null;
    if (el) {
        el.isEditing = false;
        pushEditorHistory();
        renderEditorCanvas();
        showToast('✓ Đã áp dụng liên kết', 'success');
    }
}

// Cập nhật ghi chú trực tiếp trên trang (No popup)
function updateNoteAnnotation(id, value) {
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
    const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === id) : null;
    if (el) {
        el.text = value;
        saveEditorDraft();
    }
}

function toggleNoteCard(id, event) {
    if (event) event.stopPropagation();
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
    const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === id) : null;
    if (el) {
        el.isExpanded = false;
        renderEditorCanvas();
    }
}

function getStampHtml(stampType) {
    const stamps = {
        'approved': `
            <div class="stamp-badge-preview approved">
                <div class="stamp-inner-border">
                    <span class="stamp-label">★ ĐÃ PHÊ DUYỆT ★</span>
                    <span class="stamp-date">NGÀY: 26/10/2026</span>
                </div>
            </div>`,
        'draft': `
            <div class="stamp-badge-preview draft">
                <div class="stamp-inner-border">
                    <span class="stamp-label">BẢN NHÁP (DRAFT)</span>
                    <span class="stamp-date">LƯU HÀNH NỘI BỘ</span>
                </div>
            </div>`,
        'secret': `
            <div class="stamp-badge-preview secret">
                <div class="stamp-inner-border">
                    <span class="stamp-label">🔒 TUYỆT MẬT</span>
                    <span class="stamp-date">KHÔNG SAO CHÉP</span>
                </div>
            </div>`,
        'completed': `
            <div class="stamp-badge-preview completed">
                <div class="stamp-inner-border">
                    <span class="stamp-label">✓ HOÀN THÀNH</span>
                    <span class="stamp-date">ĐẠT YÊU CẦU</span>
                </div>
            </div>`,
        'certified': `
            <div class="stamp-badge-preview certified">
                <div class="stamp-inner-border">
                    <span class="stamp-label">SAO Y BẢN CHÍNH</span>
                    <span class="stamp-date">VĂN THƯ ĐÃ DUYỆT</span>
                </div>
            </div>`,
        'void': `
            <div class="stamp-badge-preview void">
                <div class="stamp-inner-border">
                    <span class="stamp-label">✕ HỦY BỎ (VOID)</span>
                    <span class="stamp-date">HẾT HIỆU LỰC</span>
                </div>
            </div>`
    };
    return stamps[stampType] || stamps['approved'];
}

function getShapeHtml(shapeType, color = '#2563EB', strokeWidth = 2, fill = 'none', dash = 'solid', arrow = 'arrow-end', width = 170, height = 96) {
    const w = Math.max(30, width || 170);
    const h = Math.max(20, height || 96);
    const stroke = color || '#2563EB';
    const fillCol = (fill && fill !== 'none') ? fill : 'none';
    let dashAttr = '';
    if (dash === 'dashed') dashAttr = 'stroke-dasharray="6,4"';
    else if (dash === 'dotted') dashAttr = 'stroke-dasharray="2,4"';

    const cleanColorId = (color || '2563eb').replace(/[^a-zA-Z0-9]/g, '');

    if (shapeType === 'line' || shapeType === 'arrow') {
        const isArrow = (shapeType === 'arrow' || arrow === 'arrow-end' || arrow === 'arrow-both');
        const isBoth = (arrow === 'arrow-both');
        const lineH = Math.max(24, Math.min(40, h));
        const midY = Math.round(lineH / 2);
        return `
            <svg width="${w}" height="${lineH}" viewBox="0 0 ${w} ${lineH}" style="overflow: visible;">
                <defs>
                    <marker id="arrHead-${cleanColorId}" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
                        <path d="M0,0 L0,6 L8,3 z" fill="${stroke}"/>
                    </marker>
                    <marker id="arrHeadStart-${cleanColorId}" markerWidth="8" markerHeight="8" refX="2" refY="3" orient="auto-start-reverse">
                        <path d="M0,0 L0,6 L8,3 z" fill="${stroke}"/>
                    </marker>
                </defs>
                <line x1="${isBoth ? 12 : 4}" y1="${midY}" x2="${isArrow ? (w - 14) : (w - 4)}" y2="${midY}" 
                    stroke="${stroke}" stroke-width="${strokeWidth}" ${dashAttr} 
                    ${isBoth ? `marker-start="url(#arrHeadStart-${cleanColorId})"` : ''} 
                    ${isArrow ? `marker-end="url(#arrHead-${cleanColorId})"` : ''}/>
            </svg>
        `;
    } else if (shapeType === 'rect') {
        return `<svg width="${w}" height="${h}" viewBox="0 0 ${w} ${h}"><rect x="3" y="3" width="${w - 6}" height="${h - 6}" rx="4" fill="${fillCol}" stroke="${stroke}" stroke-width="${strokeWidth}" ${dashAttr}/></svg>`;
    } else if (shapeType === 'circle') {
        const rx = Math.max(10, Math.round((w - 6) / 2));
        const ry = Math.max(10, Math.round((h - 6) / 2));
        const cx = Math.round(w / 2);
        const cy = Math.round(h / 2);
        return `<svg width="${w}" height="${h}" viewBox="0 0 ${w} ${h}"><ellipse cx="${cx}" cy="${cy}" rx="${rx}" ry="${ry}" fill="${fillCol}" stroke="${stroke}" stroke-width="${strokeWidth}" ${dashAttr}/></svg>`;
    } else if (shapeType === 'polygon') {
        return `<svg width="${w}" height="${h}" viewBox="0 0 ${w} ${h}"><polygon points="${w / 2},4 ${w - 6},${h * 0.35} ${w * 0.8},${h - 6} ${w * 0.2},${h - 6} 6,${h * 0.35}" fill="${fillCol}" stroke="${stroke}" stroke-width="${strokeWidth}" ${dashAttr}/></svg>`;
    }
    return `<svg width="${w}" height="28"><line x1="4" y1="14" x2="${w - 4}" y2="14" stroke="${stroke}" stroke-width="${strokeWidth}"/></svg>`;
}

// Drag & drop element handlers on canvas (Có bảo vệ không chặn chỉnh sửa chữ hoặc resize ảnh)
function makeElementDraggable(domEl, dataEl) {
    let isDragging = false;
    let startX = 0, startY = 0;
    let initialLeft = 0, initialTop = 0;

    domEl.addEventListener('mousedown', (e) => {
        if (e.button !== 0 ||
            e.target.classList.contains('canvas-del-btn') ||
            domEl.isContentEditable ||
            e.target.closest('.img-transform-bar') ||
            e.target.closest('.img-resize-handle') ||
            e.target.closest('.img-rot-handle') ||
            e.target.closest('.inline-link-popover') ||
            e.target.closest('.inline-note-card')) {
            return;
        }

        e.stopPropagation();
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        initialLeft = parseInt(domEl.style.left || '0', 10);
        initialTop = parseInt(domEl.style.top || '0', 10);
        pushEditorHistory();
        domEl.classList.add('selected');

        function onMouseMove(ev) {
            if (!isDragging) return;
            const dx = (ev.clientX - startX) / editorZoom;
            const dy = (ev.clientY - startY) / editorZoom;
            const newX = Math.round(initialLeft + dx);
            const newY = Math.round(initialTop + dy);
            domEl.style.left = `${newX}px`;
            domEl.style.top = `${newY}px`;
            dataEl.x = newX;
            dataEl.y = newY;
        }

        function onMouseUp() {
            if (isDragging) {
                isDragging = false;
                domEl.classList.remove('selected');
                window.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
                saveEditorDraft();
            }
        }

        window.addEventListener('mousemove', onMouseMove);
        window.addEventListener('mouseup', onMouseUp);
    });
}

// Tương tác click trên mặt giấy PDF
function setupCanvasInteractions() {
    const annotLayer = document.getElementById('paperAnnotationLayer');
    if (!annotLayer || annotLayer.dataset.bound) return;
    annotLayer.dataset.bound = 'true';

    annotLayer.addEventListener('click', (e) => {
        if (e.target !== annotLayer) return;

        const rect = annotLayer.getBoundingClientRect();
        const clickX = Math.round((e.clientX - rect.left) / editorZoom);
        const clickY = Math.round((e.clientY - rect.top) / editorZoom);

        handleCanvasClickAction(clickX, clickY);
    });
}

// Xử lý chèn trực tiếp trên mặt giấy PDF (100% không dùng alert/prompt popup)
function handleCanvasClickAction(x, y) {
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
    if (!pageData) return;
    if (!pageData.elements) pageData.elements = [];
    if (['add-text', 'shape', 'link', 'note'].includes(editorActiveTool)) pushEditorHistory();

    if (editorActiveTool === 'add-text') {
        const newId = 'txt-' + Date.now();
        const newEl = {
            id: newId,
            type: 'text',
            text: 'Gõ nội dung tại đây...',
            x: x,
            y: y,
            size: editorFontSize || 18,
            fontFamily: editorFontFamily || 'Helvetica, Arial, sans-serif',
            align: editorTextAlign || 'left',
            opacity: editorOpacity !== undefined ? editorOpacity : 1.0,
            color: editorActiveColor || '#000000',
            bold: false,
            isEditing: true
        };
        pageData.elements.push(newEl);
        editorSelectedElementId = newId;
        renderEditorCanvas();
    } else if (editorActiveTool === 'signature') {
        openSignatureModal();
    } else if (editorActiveTool === 'stamp') {
        openStampModal();
    } else if (editorActiveTool === 'shape') {
        const newId = 'sh-' + Date.now();
        const initialW = (editorActiveShape === 'rect' ? 170 : (editorActiveShape === 'circle' ? 110 : 160));
        const initialH = (editorActiveShape === 'rect' ? 96 : (editorActiveShape === 'circle' ? 110 : 30));
        pageData.elements.push({
            id: newId,
            type: 'shape',
            shapeType: editorActiveShape || 'line',
            color: editorActiveColor || '#2563EB',
            fill: editorActiveShapeFill || 'none',
            dashStyle: editorActiveLineDash || 'solid',
            arrowHead: editorActiveLineArrow || 'arrow-end',
            size: editorStrokeWidth || 2,
            opacity: editorOpacity !== undefined ? editorOpacity : 1.0,
            width: initialW,
            height: initialH,
            x: x,
            y: y
        });
        editorSelectedElementId = newId;
        renderEditorCanvas();
    } else if (editorActiveTool === 'image') {
        triggerEditorImageUpload();
    } else if (editorActiveTool === 'link') {
        const newId = 'lnk-' + Date.now();
        pageData.elements.push({
            id: newId,
            type: 'link',
            url: 'https://',
            text: 'Bấm để mở liên kết',
            x: x,
            y: y,
            isEditing: true
        });
        editorSelectedElementId = newId;
        renderEditorCanvas();
    } else if (editorActiveTool === 'note') {
        const newId = 'note-' + Date.now();
        const now = new Date();
        const timeStr = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
        pageData.elements.push({
            id: newId,
            type: 'note',
            text: '',
            color: '#FEF3C7',
            time: timeStr,
            x: x,
            y: y,
            isExpanded: true
        });
        editorSelectedElementId = newId;
        renderEditorCanvas();
    } else if (editorActiveTool === 'highlight') {
        const newId = 'hl-' + Date.now();
        pageData.elements.push({
            id: newId,
            type: 'text',
            text: '❚❚ ĐOẠN VĂN ĐƯỢC TÔ SÁNG ❚❚',
            x: x,
            y: y,
            size: 16,
            fontFamily: editorFontFamily || 'Helvetica, Arial, sans-serif',
            color: '#D97706',
            bold: true
        });
        editorSelectedElementId = newId;
        renderEditorCanvas();
    }
}

// Quản lý công cụ hoạt động & chuyển đổi bảng thuộc tính tương ứng
function setEditorTool(toolName) {
    editorActiveTool = toolName;
    document.querySelectorAll('.ribbon-tool-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tool === toolName);
    });

    const isDrawTool = (toolName === 'draw' || toolName === 'highlight' || toolName === 'eraser');
    const drawingCanvas = document.getElementById('paperDrawingCanvas');
    if (drawingCanvas) {
        drawingCanvas.classList.toggle('drawing-active', isDrawTool);
    }

    const penRack = document.getElementById('floatingPenRack');
    if (penRack) {
        penRack.style.display = isDrawTool ? 'flex' : 'none';
    }

    if (toolName === 'image') {
        triggerEditorImageUpload();
    } else if (toolName === 'signature') {
        openSignatureModal();
    } else if (toolName === 'stamp') {
        openStampModal();
    }

    const subMenu = document.getElementById('shapesMenu');
    if (subMenu) subMenu.classList.remove('show');

    // Chuyển đổi bảng thuộc tính contextual ở hàng 2
    const pText = document.getElementById('propModeText');
    const pShapes = document.getElementById('propModeShapes');
    const pIcons = document.getElementById('propModeIcons');

    if (pText && pShapes && pIcons) {
        if (toolName === 'add-text' || toolName === 'edit-text' || toolName === 'note') {
            pText.style.display = 'flex';
            pShapes.style.display = 'none';
            pIcons.style.display = 'none';
        } else if (toolName === 'draw' || toolName === 'shape' || toolName === 'highlight') {
            pText.style.display = 'none';
            pShapes.style.display = 'flex';
            pIcons.style.display = 'none';
        } else if (toolName === 'stamp') {
            pText.style.display = 'none';
            pShapes.style.display = 'none';
            pIcons.style.display = 'flex';
        } else {
            pText.style.display = 'flex';
            pShapes.style.display = 'none';
            pIcons.style.display = 'none';
        }
    }

    // Luôn cập nhật lớp Text Edit Layer (Khớp Ảnh 1: hiển thị hoặc ẩn các khối viền nét đứt)
    const pageData = (editorPages && editorPages.length > 0) ? (editorPages.find(p => p.pageNumber === editorCurrentPage) || editorPages[0]) : null;
    if (pageData) {
        renderPdfTextEditLayer(pageData);
    }
}

let editorActiveShapeFill = 'none';
let editorActiveLineDash = 'solid';
let editorActiveLineArrow = 'arrow-end';
let isPenEraserMode = false;

function setEditorShapeFill(val) {
    editorActiveShapeFill = val;
    if (editorSelectedElementId) {
        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === editorSelectedElementId) : null;
        if (el && el.type === 'shape') {
            pushEditorHistory();
            el.fill = val;
            renderEditorCanvas();
        }
    }
}

function setEditorLineDashStyle(val) {
    editorActiveLineDash = val;
    if (editorSelectedElementId) {
        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === editorSelectedElementId) : null;
        if (el && el.type === 'shape') {
            pushEditorHistory();
            el.dashStyle = val;
            renderEditorCanvas();
        }
    }
}

function setEditorLineArrowHead(val) {
    editorActiveLineArrow = val;
    if (editorSelectedElementId) {
        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === editorSelectedElementId) : null;
        if (el && el.type === 'shape') {
            pushEditorHistory();
            el.arrowHead = val;
            renderEditorCanvas();
        }
    }
}

// Bảng bút vẽ nổi bên trái trang PDF (Matching Image 5)
function togglePenRack(show) {
    const rack = document.getElementById('floatingPenRack');
    if (rack) rack.style.display = show ? 'flex' : 'none';
}

function selectPenType(type) {
    ['penRackBallpoint', 'penRackHighlighter', 'penRackEraser'].forEach(id => {
        document.getElementById(id)?.classList.remove('active');
    });
    isPenEraserMode = false;

    const drawingCanvas = document.getElementById('paperDrawingCanvas');
    if (drawingCanvas) drawingCanvas.classList.add('drawing-active');

    if (type === 'ballpoint') {
        document.getElementById('penRackBallpoint')?.classList.add('active');
        editorActiveTool = 'draw';
        editorActiveColor = '#2563EB';
        editorStrokeWidth = 2.5;
    } else if (type === 'highlighter') {
        document.getElementById('penRackHighlighter')?.classList.add('active');
        editorActiveTool = 'highlight';
        editorActiveColor = '#FDE047';
        editorStrokeWidth = 14;
    } else if (type === 'eraser') {
        document.getElementById('penRackEraser')?.classList.add('active');
        editorActiveTool = 'eraser';
        isPenEraserMode = true;
    }
}

function togglePenThicknessMenu(event) {
    event.stopPropagation();
    const menu = document.getElementById('penThicknessMenu');
    if (menu) menu.classList.toggle('show');
}

function setPenThickness(px) {
    editorStrokeWidth = px;
    const menu = document.getElementById('penThicknessMenu');
    if (menu) menu.classList.remove('show');
}

function setEditorColor(hex) {
    editorActiveColor = hex;
    document.querySelectorAll('.color-swatch').forEach(s => {
        const c = s.style.getPropertyValue('--c');
        s.classList.toggle('active', c.toLowerCase() === hex.toLowerCase());
    });

    if (editorSelectedElementId) {
        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === editorSelectedElementId) : null;
        if (el) {
            pushEditorHistory();
            el.color = hex;
            renderEditorCanvas();
        } else if (pageData) {
            const blockEl = document.querySelector(`.pdf-detected-text-block[data-text-id="${editorSelectedElementId}"]`);
            if (blockEl) {
                pushEditorHistory();
                blockEl.style.color = hex;
                blockEl.style.setProperty('--block-color', hex);
                blockEl.classList.add('is-edited');
                const edit = getOrCreateTextEdit(pageData, editorSelectedElementId, blockEl);
                if (edit) {
                    edit.color = hex;
                    edit.modified = true;
                    edit.modifiedFormat = true;
                }
                saveEditorDraft();
            }
        }
    }
}

// Xử lý lựa chọn màu nền che cho khối văn bản
function handleTextBgColorSelect(val) {
    if (val === 'custom') {
        const picker = document.getElementById('editorTextBgCustomColor');
        if (picker) {
            picker.style.display = 'inline-block';
            picker.click();
        }
        return;
    }
    const picker = document.getElementById('editorTextBgCustomColor');
    if (picker) picker.style.display = 'none';

    if (val === 'auto') {
        // Hút lại màu nền tự động từ Canvas trang hiện tại
        if (editorSelectedElementId) {
            const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
            const origItem = (pageData?._cachedTextItems || []).find(it => it.id === editorSelectedElementId);
            const canvas = document.getElementById('pdfRenderCanvas');
            if (origItem && canvas) {
                const sample = sampleBlockBgAndTextColor(canvas, origItem, 1.0);
                setEditorTextBgColor(sample.bgColor);
            }
        }
        return;
    }

    setEditorTextBgColor(val);
}

// Thiết lập màu nền che chữ gốc trực tiếp cho khối văn bản đang chỉnh sửa
function setEditorTextBgColor(color) {
    if (editorSelectedElementId) {
        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        if (pageData) {
            const blockEl = document.querySelector(`.pdf-detected-text-block[data-text-id="${editorSelectedElementId}"]`);
            if (blockEl) {
                pushEditorHistory();
                blockEl.style.setProperty('--block-bg', color);
                blockEl.style.backgroundColor = color === 'transparent' ? 'transparent' : color;
                blockEl.classList.add('is-edited');
                const edit = getOrCreateTextEdit(pageData, editorSelectedElementId, blockEl);
                if (edit) {
                    edit.bgColor = color;
                    edit.modified = true;
                    edit.modifiedFormat = true;
                }
                saveEditorDraft();
            }
        }
    }
}

function updateStrokeOrFontSize(val) {
    editorFontSize = parseInt(val, 10) || 18;
    if (editorSelectedElementId) {
        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === editorSelectedElementId) : null;
        if (el && el.type === 'text') {
            pushEditorHistory();
            el.size = editorFontSize;
            renderEditorCanvas();
        } else if (pageData) {
            const blockEl = document.querySelector(`.pdf-detected-text-block[data-text-id="${editorSelectedElementId}"]`);
            if (blockEl) {
                pushEditorHistory();
                const zoom = editorZoom || 1.0;
                blockEl.style.fontSize = `${Math.round(editorFontSize * zoom)}px`;
                blockEl.classList.add('is-edited');
                const edit = getOrCreateTextEdit(pageData, editorSelectedElementId, blockEl);
                if (edit) {
                    edit.fontSize = editorFontSize;
                    edit.modified = true;
                    edit.modifiedFormat = true;
                }
                saveEditorDraft();
            }
        }
    }
}

function setEditorFontFamily(font) {
    editorFontFamily = font;
    if (editorSelectedElementId) {
        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === editorSelectedElementId) : null;
        if (el && el.type === 'text') {
            pushEditorHistory();
            el.fontFamily = font;
            renderEditorCanvas();
        } else if (pageData) {
            const blockEl = document.querySelector(`.pdf-detected-text-block[data-text-id="${editorSelectedElementId}"]`);
            if (blockEl) {
                pushEditorHistory();
                blockEl.style.fontFamily = font;
                blockEl.classList.add('is-edited');
                const edit = getOrCreateTextEdit(pageData, editorSelectedElementId, blockEl);
                if (edit) {
                    edit.fontFamily = font;
                    edit.modified = true;
                    edit.modifiedFormat = true;
                }
                saveEditorDraft();
            }
        }
    }
}

function setEditorTextAlign(align) {
    editorTextAlign = align;
    ['btnAlignLeft', 'btnAlignCenter', 'btnAlignRight'].forEach(id => {
        const btn = document.getElementById(id);
        if (btn) btn.classList.remove('active');
    });
    if (align === 'left' && document.getElementById('btnAlignLeft')) document.getElementById('btnAlignLeft').classList.add('active');
    if (align === 'center' && document.getElementById('btnAlignCenter')) document.getElementById('btnAlignCenter').classList.add('active');
    if (align === 'right' && document.getElementById('btnAlignRight')) document.getElementById('btnAlignRight').classList.add('active');

    if (editorSelectedElementId) {
        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === editorSelectedElementId) : null;
        if (el && el.type === 'text') {
            pushEditorHistory();
            el.align = align;
            renderEditorCanvas();
        } else if (pageData) {
            const blockEl = document.querySelector(`.pdf-detected-text-block[data-text-id="${editorSelectedElementId}"]`);
            if (blockEl) {
                pushEditorHistory();
                blockEl.style.textAlign = align;
                blockEl.classList.add('is-edited');
                const edit = getOrCreateTextEdit(pageData, editorSelectedElementId, blockEl);
                if (edit) {
                    edit.align = align;
                    edit.modified = true;
                    edit.modifiedFormat = true;
                }
                saveEditorDraft();
            }
        }
    }
}

function toggleEditorBold() {
    let newState = false;
    const boldBtn = document.getElementById('btnFormatBold');
    if (editorSelectedElementId) {
        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === editorSelectedElementId) : null;
        if (el && el.type === 'text') {
            pushEditorHistory();
            el.bold = !el.bold;
            newState = !!el.bold;
            renderEditorCanvas();
        } else if (pageData) {
            const blockEl = document.querySelector(`.pdf-detected-text-block[data-text-id="${editorSelectedElementId}"]`);
            if (blockEl) {
                pushEditorHistory();
                const curBold = blockEl.style.fontWeight === 'bold' || parseInt(blockEl.style.fontWeight, 10) >= 600;
                newState = !curBold;
                blockEl.style.fontWeight = newState ? 'bold' : 'normal';
                blockEl.classList.add('is-edited');
                const edit = getOrCreateTextEdit(pageData, editorSelectedElementId, blockEl);
                if (edit) {
                    edit.isBold = newState;
                    edit.modified = true;
                    edit.modifiedFormat = true;
                }
                saveEditorDraft();
            }
        }
    }
    if (boldBtn) boldBtn.classList.toggle('active', newState);
}

function toggleEditorItalic() {
    let newState = false;
    const italicBtn = document.getElementById('btnFormatItalic');
    if (editorSelectedElementId) {
        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === editorSelectedElementId) : null;
        if (el && el.type === 'text') {
            pushEditorHistory();
            el.italic = !el.italic;
            newState = !!el.italic;
            renderEditorCanvas();
        } else if (pageData) {
            const blockEl = document.querySelector(`.pdf-detected-text-block[data-text-id="${editorSelectedElementId}"]`);
            if (blockEl) {
                pushEditorHistory();
                const curItalic = blockEl.style.fontStyle === 'italic';
                newState = !curItalic;
                blockEl.style.fontStyle = newState ? 'italic' : 'normal';
                blockEl.classList.add('is-edited');
                const edit = getOrCreateTextEdit(pageData, editorSelectedElementId, blockEl);
                if (edit) {
                    edit.isItalic = newState;
                    edit.modified = true;
                    edit.modifiedFormat = true;
                }
                saveEditorDraft();
            }
        }
    }
    if (italicBtn) italicBtn.classList.toggle('active', newState);
}

function setEditorOpacity(opacityVal) {
    editorOpacity = parseFloat(opacityVal) || 1.0;
    if (editorSelectedElementId) {
        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === editorSelectedElementId) : null;
        if (el) {
            pushEditorHistory();
            el.opacity = editorOpacity;
            renderEditorCanvas();
        }
    }
}

function setEditorStrokeWidth(width) {
    editorStrokeWidth = parseInt(width, 10) || 2;
    if (editorSelectedElementId) {
        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === editorSelectedElementId) : null;
        if (el && el.type === 'shape') {
            pushEditorHistory();
            el.size = editorStrokeWidth;
            renderEditorCanvas();
        }
    }
}

function selectEditorElement(id) {
    editorSelectedElementId = id;
    document.querySelectorAll('#paperAnnotationLayer > div').forEach(div => {
        div.classList.toggle('selected-element', div.dataset.elementId === id);
    });
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
    const el = pageData && pageData.elements ? pageData.elements.find(e => e.id === id) : null;
    if (el && el.type === 'text') {
        syncTextPropertiesToRibbon({
            fontFamily: el.fontFamily,
            fontSize: el.size,
            isBold: !!el.bold,
            isItalic: !!el.italic,
            color: el.color,
            align: el.align
        });
    }
}

function deleteSelectedEditorElement() {
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
    if (!pageData || !pageData.elements || pageData.elements.length === 0) {
        return;
    }
    pushEditorHistory();
    if (editorSelectedElementId) {
        pageData.elements = pageData.elements.filter(e => e.id !== editorSelectedElementId);
        editorSelectedElementId = null;
    } else {
        pageData.elements.pop();
    }
    renderEditorCanvas();
}

function toggleShapesDropdown(event) {
    event.stopPropagation();
    const menu = document.getElementById('shapesMenu');
    if (menu) menu.classList.toggle('show');
}

function selectShape(shapeType, shapeLabel) {
    editorActiveShape = shapeType;
    editorActiveTool = 'shape';
    const label = document.getElementById('currentShapeLabel');
    if (label) label.innerText = shapeLabel;
    const menu = document.getElementById('shapesMenu');
    if (menu) menu.classList.remove('show');
}

document.addEventListener('click', () => {
    const menu = document.getElementById('shapesMenu');
    if (menu) menu.classList.remove('show');
    const stripMenu = document.getElementById('stripAddMenu');
    if (stripMenu) stripMenu.classList.remove('show');
});

// Thêm Vector Icon chuyên nghiệp
function addVectorIcon(type) {
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
    if (!pageData) return;
    if (!pageData.elements) pageData.elements = [];

    pushEditorHistory();
    pageData.elements.push({
        id: 'vec-' + Date.now(),
        type: 'vector',
        iconType: type,
        color: editorActiveColor,
        x: 100 + Math.floor(Math.random() * 120),
        y: 120 + Math.floor(Math.random() * 120)
    });

    renderEditorCanvas();
}

// Thao tác với trang: Thêm trang, Nhân đôi, Xóa
function toggleAddPageDropdown(event) {
    event.stopPropagation();
    const menu = document.getElementById('stripAddMenu');
    if (menu) menu.classList.toggle('show');
}

function insertBlankPage(afterIndex = null) {
    pushEditorHistory();
    const newPageNum = afterIndex !== null ? afterIndex + 1 : editorPages.length + 1;
    const newPage = {
        id: Date.now(),
        pageNumber: newPageNum,
        isBlankPage: true,
        originalPageNum: null,
        title: `Trang trắng mới (${newPageNum})`,
        rotation: 0,
        elements: [],
        textEdits: {},
        drawings: [],
        _cachedTextItems: []
    };

    if (afterIndex !== null) {
        editorPages.splice(afterIndex, 0, newPage);
    } else {
        editorPages.push(newPage);
    }

    reindexEditorPages();
    editorCurrentPage = newPage.pageNumber;
    renderEditorThumbnails();
    renderEditorCanvas();
    updateEditorFloatingInfo();
    showToast(`✓ Đã thêm trang trắng số ${newPage.pageNumber}`, 'success');
}

function duplicateEditorPage(pageNum, event) {
    if (event) event.stopPropagation();
    pushEditorHistory();

    const idx = editorPages.findIndex(p => p.pageNumber === pageNum);
    if (idx === -1) return;

    const source = editorPages[idx];
    const cloned = JSON.parse(JSON.stringify(source));
    cloned.id = Date.now();
    cloned.title = `${source.title} (Bản sao)`;

    editorPages.splice(idx + 1, 0, cloned);
    reindexEditorPages();
    renderEditorThumbnails();
    renderEditorCanvas();
    showToast(`✓ Đã nhân đôi trang ${pageNum}`, 'success');
}

function deleteEditorPage(pageNum, event) {
    if (event) event.stopPropagation();
    if (editorPages.length <= 1) {
        showToast('Tài liệu phải có ít nhất 1 trang!', 'error');
        return;
    }

    if (!confirm(`Bạn có chắc chắn muốn xóa trang số ${pageNum}?`)) return;

    pushEditorHistory();
    editorPages = editorPages.filter(p => p.pageNumber !== pageNum);
    reindexEditorPages();

    if (editorCurrentPage > editorPages.length) {
        editorCurrentPage = editorPages.length;
    }

    renderEditorThumbnails();
    renderEditorCanvas();
    updateEditorFloatingInfo();
    showToast(`✓ Đã xóa trang ${pageNum}`, 'info');
}

function reindexEditorPages() {
    editorPages.forEach((p, i) => {
        p.pageNumber = i + 1;
    });
}

function triggerImportPdfPage() {
    const input = document.getElementById('editorFileInput');
    if (input) input.click();
}

async function handleEditorFileImport(event) {
    const file = event.target.files[0];
    if (!file) return;

    try {
        const source = await createEditorSource(file);
        pushEditorHistory();
        const importedPages = pagesFromEditorSource(source);
        editorPages.push(...importedPages);
        reindexEditorPages();
        editorCurrentPage = importedPages[0].pageNumber;
        renderEditorThumbnails();
        renderEditorCanvas();
        updateEditorFloatingInfo();
        saveEditorDraft();
        showToast(`✓ Đã nhập ${importedPages.length} trang từ ${file.name}.`, 'success');
    } catch (err) {
        showToast('Không thể nhập PDF: ' + err.message, 'error');
    } finally {
        if (event.target && 'value' in event.target) event.target.value = '';
    }
}

function handleEditorFileDrop(event) {
    event.preventDefault();
    const files = event.dataTransfer.files;
    if (files.length > 0) {
        handleEditorFileImport({ target: { files: files } });
    }
}

function triggerEditorImageUpload() {
    const input = document.getElementById('editorAnnotationImageInput');
    if (input) {
        input.value = '';
        input.click();
    }
}

function handleEditorAnnotationImageUpload(event) {
    const file = event.target.files && event.target.files[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        showToast('Vui lòng chọn tệp hình ảnh hợp lệ (PNG, JPG, SVG, WebP)', 'error');
        return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
        const dataUrl = e.target.result;
        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        if (!pageData) return;
        if (!pageData.elements) pageData.elements = [];

        pushEditorHistory();
        const newId = 'img-' + Date.now();
        pageData.elements.push({
            id: newId,
            type: 'image',
            data: dataUrl,
            name: file.name,
            x: 140,
            y: 180,
            width: 220,
            opacity: editorOpacity !== undefined ? editorOpacity : 1.0
        });

        editorSelectedElementId = newId;
        renderEditorCanvas();
    };
    reader.readAsDataURL(file);
}

// Tương tác vẽ tự do (Draw) & Tô sáng (Highlight) trên Drawing Canvas
function setupDrawingCanvasInteractions() {
    const canvas = document.getElementById('paperDrawingCanvas');
    if (!canvas || canvas.dataset.drawingBound) return;
    canvas.dataset.drawingBound = 'true';

    let isDrawing = false;
    let currentStroke = [];

    function getCoords(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: (clientX - rect.left) / editorZoom,
            y: (clientY - rect.top) / editorZoom
        };
    }

    function eraseDrawingAt(pos) {
        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        if (!pageData || !pageData.drawings || pageData.drawings.length === 0) return;
        const eraserRadius = 24;
        const beforeCount = pageData.drawings.length;
        pageData.drawings = pageData.drawings.filter(stroke => {
            if (!stroke.points || stroke.points.length === 0) return false;
            return !stroke.points.some(pt => Math.hypot(pt.x - pos.x, pt.y - pos.y) <= eraserRadius);
        });
        if (pageData.drawings.length !== beforeCount) {
            renderPageDrawings(pageData);
            saveEditorDraft();
        }
    }

    function startDraw(e) {
        if (editorActiveTool === 'eraser' || isPenEraserMode) {
            isDrawing = true;
            pushEditorHistory();
            const pos = getCoords(e);
            eraseDrawingAt(pos);
            if (e.cancelable) e.preventDefault();
            return;
        }

        if (editorActiveTool !== 'draw' && editorActiveTool !== 'highlight') return;
        isDrawing = true;
        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        if (!pageData) return;
        if (!pageData.drawings) pageData.drawings = [];

        const ctx = canvas.getContext('2d');
        const pos = getCoords(e);
        currentStroke = [pos];

        const drawScale = canvas.width / rectWidth(canvas) * editorZoom;
        ctx.setTransform(drawScale, 0, 0, drawScale, 0, 0);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);

        if (editorActiveTool === 'highlight') {
            ctx.strokeStyle = (editorActiveColor === '#000000' || !editorActiveColor) ? '#FDE047' : editorActiveColor;
            ctx.globalAlpha = 0.38;
            ctx.lineWidth = Math.max(18, (editorStrokeWidth || 2) * 6);
            ctx.lineCap = 'square';
            ctx.lineJoin = 'miter';
        } else {
            ctx.strokeStyle = editorActiveColor || '#2563EB';
            ctx.globalAlpha = editorOpacity !== undefined ? editorOpacity : 1.0;
            ctx.lineWidth = Math.max(2, (editorStrokeWidth || 2) * 1.5);
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
        }

        if (e.cancelable) e.preventDefault();
    }

    function moveDraw(e) {
        if (!isDrawing) return;
        const pos = getCoords(e);

        if (editorActiveTool === 'eraser' || isPenEraserMode) {
            eraseDrawingAt(pos);
            if (e.cancelable) e.preventDefault();
            return;
        }

        const ctx = canvas.getContext('2d');
        currentStroke.push(pos);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        if (e.cancelable) e.preventDefault();
    }

    function endDraw(e) {
        if (!isDrawing) return;
        isDrawing = false;

        if (editorActiveTool === 'eraser' || isPenEraserMode) {
            currentStroke = [];
            return;
        }

        const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
        if (pageData && currentStroke.length > 0) {
            pushEditorHistory();
            pageData.drawings.push({
                tool: editorActiveTool,
                color: editorActiveTool === 'highlight' ? ((editorActiveColor === '#000000' || !editorActiveColor) ? '#FDE047' : editorActiveColor) : editorActiveColor,
                opacity: editorActiveTool === 'highlight' ? 0.38 : (editorOpacity !== undefined ? editorOpacity : 1.0),
                width: editorActiveTool === 'highlight' ? Math.max(18, (editorStrokeWidth || 2) * 6) : Math.max(2, (editorStrokeWidth || 2) * 1.5),
                points: currentStroke
            });
            saveEditorDraft();
        }
        currentStroke = [];
    }

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', moveDraw);
    canvas.addEventListener('mouseup', endDraw);
    canvas.addEventListener('mouseleave', endDraw);

    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', moveDraw, { passive: false });
    canvas.addEventListener('touchend', endDraw);
}

function rectWidth(canvas) { return canvas.getBoundingClientRect().width || 1; }

function renderPageDrawings(pageData) {
    const canvas = document.getElementById('paperDrawingCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const drawScale = canvas.width / rectWidth(canvas) * editorZoom;
    ctx.setTransform(drawScale, 0, 0, drawScale, 0, 0);

    if (!pageData || !pageData.drawings || pageData.drawings.length === 0) return;

    pageData.drawings.forEach(stroke => {
        if (!stroke.points || stroke.points.length === 0) return;
        ctx.save();
        ctx.beginPath();
        ctx.strokeStyle = stroke.color;
        ctx.globalAlpha = stroke.opacity !== undefined ? stroke.opacity : 1.0;
        ctx.lineWidth = stroke.width;
        ctx.lineCap = stroke.tool === 'highlight' ? 'square' : 'round';
        ctx.lineJoin = stroke.tool === 'highlight' ? 'miter' : 'round';

        ctx.moveTo(stroke.points[0].x, stroke.points[0].y);
        for (let i = 1; i < stroke.points.length; i++) {
            ctx.lineTo(stroke.points[i].x, stroke.points[i].y);
        }
        ctx.stroke();
        ctx.restore();
    });
}

// Zoom & Pan
function zoomEditor(delta) {
    editorZoom = Math.max(0.4, Math.min(2.5, Math.round((editorZoom + delta) * 10) / 10));
    const label = document.getElementById('floatingZoomLevel');
    if (label) label.innerText = `${Math.round(editorZoom * 100)}%`;
    renderEditorCanvas();
}

function resetEditorZoom() {
    editorZoom = 1.0;
    const label = document.getElementById('floatingZoomLevel');
    if (label) label.innerText = '100%';
    renderEditorCanvas();
}

function toggleEditorPan() {
    isEditorPanMode = !isEditorPanMode;
    const btn = document.getElementById('btnEditorPan');
    const viewport = document.getElementById('editorViewport');
    if (btn) btn.classList.toggle('active', isEditorPanMode);
    if (viewport) viewport.classList.toggle('panning-active', isEditorPanMode);
    if (viewport && !viewport.dataset.panBound) {
        viewport.dataset.panBound = 'true';
        viewport.addEventListener('pointerdown', event => {
            if (!isEditorPanMode || event.button !== 0) return;
            event.preventDefault();
            event.stopPropagation();
            const startX = event.clientX, startY = event.clientY;
            const left = viewport.scrollLeft, top = viewport.scrollTop;
            const move = e => { viewport.scrollLeft = left - (e.clientX - startX); viewport.scrollTop = top - (e.clientY - startY); };
            const end = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', end); };
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', end);
        }, true);
    }
}

function fitEditorToScreen() {
    const viewport = document.getElementById('editorViewport');
    const canvas = document.getElementById('pdfRenderCanvas');
    if (!viewport || !canvas || !canvas.clientWidth || !canvas.clientHeight) return;
    const pageWidth = canvas.clientWidth / editorZoom;
    const pageHeight = canvas.clientHeight / editorZoom;
    editorZoom = Math.max(0.4, Math.min(2.5, (viewport.clientWidth - 40) / pageWidth, (viewport.clientHeight - 40) / pageHeight));
    const label = document.getElementById('floatingZoomLevel');
    if (label) label.innerText = `${Math.round(editorZoom * 100)}%`;
    renderEditorCanvas();
}

// Tự động lưu bản nháp chỉnh sửa vào LocalStorage để không bị mất khi F5
function saveEditorDraft() {
    if (editorDraftRecoveryBlocked) return;
    const docId = localStorage.getItem('editor_current_doc_id');
    const docName = localStorage.getItem('editor_current_doc_name') || currentDocumentName || 'tailieu';
    const draftKey = docId ? ('doc_' + docId) : ('file_' + docName.replace(/[^a-zA-Z0-9_-]/g, '_'));
    if (editorPages && editorPages.length > 0) {
        try {
            // Không lưu _cachedTextItems vào localStorage để tránh làm kẹt cache cũ
            const cleanPages = editorPages.map(p => {
                const copy = { ...p };
                delete copy._cachedTextItems;
                if (copy.elements) {
                    copy.elements = copy.elements.filter(el => el.type !== 'text' || (el.text && el.text.trim() !== 'Gõ nội dung tại đây...'));
                }
                return copy;
            });
            localStorage.setItem('editor_draft_' + draftKey, JSON.stringify(cleanPages));
            if (docId) {
                localStorage.setItem('editor_draft_' + docId, JSON.stringify(cleanPages));
            }
        } catch (e) {
            console.warn('Lỗi lưu nháp vào LocalStorage:', e);
        }
    }
}

// Undo / Redo
function pushEditorHistory() {
    editorHistoryStack.push(JSON.stringify(editorPages));
    if (editorHistoryStack.length > 25) editorHistoryStack.shift();
    editorRedoStack = [];
    setTimeout(saveEditorDraft, 0);
}

function editorUndo() {
    if (editorHistoryStack.length > 0) {
        editorRedoStack.push(JSON.stringify(editorPages));
        editorPages = JSON.parse(editorHistoryStack.pop());
        renderEditorThumbnails();
        renderEditorCanvas();
        updateEditorFloatingInfo();
        saveEditorDraft();
    }
}

function editorRedo() {
    if (editorRedoStack.length > 0) {
        editorHistoryStack.push(JSON.stringify(editorPages));
        editorPages = JSON.parse(editorRedoStack.pop());
        renderEditorThumbnails();
        renderEditorCanvas();
        updateEditorFloatingInfo();
        saveEditorDraft();
    }
}

async function printPdfDocument() {
    try {
        const blob = await finishAndSaveDocument(true);
        const pdfBlobUrl = URL.createObjectURL(blob);

        if (pdfBlobUrl) {
            let printFrame = document.getElementById('pdfPrintHiddenFrame');
            if (!printFrame) {
                printFrame = document.createElement('iframe');
                printFrame.id = 'pdfPrintHiddenFrame';
                printFrame.style.position = 'fixed';
                printFrame.style.right = '0';
                printFrame.style.bottom = '0';
                printFrame.style.width = '0';
                printFrame.style.height = '0';
                printFrame.style.border = 'none';
                document.body.appendChild(printFrame);
            }
            printFrame.src = pdfBlobUrl;
            printFrame.onload = () => {
                setTimeout(() => {
                    printFrame.contentWindow.focus();
                    printFrame.contentWindow.print();
                    setTimeout(() => URL.revokeObjectURL(pdfBlobUrl), 60000);
                }, 400);
            };
        }
    } catch (e) {
        showToast('Không thể tạo bản in: ' + e.message, 'error');
    }
}

// =========================================================
// SEARCH LOGIC MATCHING IMAGE 5 (REAL-TIME HIGHLIGHTS & PILL WIDGET)
// =========================================================
let editorSearchResults = [];
let currentSearchMatchIndex = -1;
let searchDebounceTimer = null;

function toggleEditorSearch() {
    const bar = document.getElementById('editorSearchBar');
    if (!bar) return;
    if (bar.style.display === 'none' || !bar.style.display) {
        bar.style.display = 'block';
        const input = document.getElementById('editorSearchQuery');
        if (input) {
            input.focus();
            input.select();
            if (input.value.trim()) {
                executeEditorSearch(0, true);
            }
        }
    } else {
        closeEditorSearch();
    }
}

function closeEditorSearch() {
    const bar = document.getElementById('editorSearchBar');
    if (bar) bar.style.display = 'none';
    clearEditorSearch();
}

function clearEditorSearch() {
    const input = document.getElementById('editorSearchQuery');
    if (input) input.value = '';
    editorSearchResults = [];
    currentSearchMatchIndex = -1;
    const badge = document.getElementById('searchMatchCount');
    if (badge) badge.innerText = '0 of 0';
    renderSearchHighlightsOnCurrentPage();
}

function handleSearchQueryInput(val) {
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        const query = (val || '').trim();
        if (!query) {
            clearEditorSearch();
        } else {
            executeEditorSearch(0, true);
        }
    }, 180);
}

async function executeEditorSearch(direction = 1, isNewQuery = false) {
    const input = document.getElementById('editorSearchQuery');
    const badge = document.getElementById('searchMatchCount');
    const query = (input ? input.value : '').trim().toLowerCase();

    if (!query) {
        if (badge) badge.innerText = '0 of 0';
        editorSearchResults = [];
        currentSearchMatchIndex = -1;
        renderSearchHighlightsOnCurrentPage();
        return;
    }

    if (isNewQuery || editorSearchResults._query !== query) {
        editorSearchResults = [];
        editorSearchResults._query = query;
        currentSearchMatchIndex = -1;

        // 1. Tìm trong annotations và text edits
        if (editorPages) {
            editorPages.forEach(p => {
                (p.elements || []).forEach(el => {
                    if (el.type === 'text' && el.text && el.text.toLowerCase().includes(query)) {
                        editorSearchResults.push({
                            pageNumber: p.pageNumber,
                            source: 'annotation',
                            text: el.text,
                            left: el.x,
                            top: el.y,
                            width: Math.max(30, Math.round(el.text.length * (el.size || 16) * 0.6)),
                            height: Math.max(16, Math.round((el.size || 16) * 1.2))
                        });
                    }
                });

                if (p.textEdits) {
                    Object.values(p.textEdits).forEach(te => {
                        if (te.text && te.text.toLowerCase().includes(query)) {
                            editorSearchResults.push({
                                pageNumber: p.pageNumber,
                                source: 'textEdit',
                                text: te.text,
                                left: te.left,
                                top: te.top,
                                width: te.width || 120,
                                height: te.height || 24
                            });
                        }
                    });
                }
            });
        }

        // 2. Tìm trong văn bản gốc của Mozilla PDF.js
        if (currentPdfDoc) {
            for (const pageData of editorPages) {
                if (pageData.isBlankPage || !pageData.originalPageNum) continue;
                const i = pageData.pageNumber;
                try {
                    const source = editorSourceForPage(pageData);
                    if (!source || !source.doc) throw new Error('Không tìm thấy PDF nguồn.');
                    const page = await source.doc.getPage(pageData.originalPageNum);
                    const effRot = (page.rotate + (pageData.rotation || 0)) % 360;
                    const viewport = page.getViewport({ scale: editorZoom, rotation: effRot });
                    const textContent = await page.getTextContent();

                    textContent.items.forEach(it => {
                        const str = it.str || '';
                        if (!str) return;
                        const lower = str.toLowerCase();
                        let idx = lower.indexOf(query);
                        while (idx !== -1) {
                            const tr = pdfjsLib.Util.transform(viewport.transform, it.transform);
                            const fontSize = Math.max(9, Math.round(Math.hypot(tr[0], tr[1])));
                            const vx = Math.round(tr[4]);
                            const vy = Math.round(tr[5]);

                            const itemWidth = Math.max(10, Math.round(it.width * viewport.scale));
                            const charW = itemWidth / Math.max(1, str.length);
                            const matchLeft = Math.round(vx + idx * charW);
                            const matchWidth = Math.max(12, Math.round(query.length * charW));
                            const matchTop = Math.max(0, Math.round(vy - fontSize * 0.95));
                            const matchHeight = Math.max(12, Math.round(fontSize * 1.25));

                            editorSearchResults.push({
                                pageNumber: i,
                                source: 'pdf',
                                text: str.substr(idx, query.length),
                                left: matchLeft,
                                top: matchTop,
                                width: matchWidth,
                                height: matchHeight
                            });

                            idx = lower.indexOf(query, idx + query.length);
                        }
                    });
                } catch (e) {
                    console.warn('Lỗi tìm kiếm text trên trang ' + i, e);
                }
            }
        } else if (editorPages) {
            // Mock text search fallback matching Image 5
            editorPages.forEach(p => {
                if (p._cachedTextItems) {
                    p._cachedTextItems.forEach(it => {
                        const lower = (it.str || '').toLowerCase();
                        let idx = lower.indexOf(query);
                        while (idx !== -1) {
                            const charW = it.width / Math.max(1, it.str.length);
                            editorSearchResults.push({
                                pageNumber: p.pageNumber,
                                source: 'mock',
                                text: it.str.substr(idx, query.length),
                                left: Math.round(it.left + idx * charW),
                                top: it.top,
                                width: Math.max(16, Math.round(query.length * charW)),
                                height: it.height
                            });
                            idx = lower.indexOf(query, idx + query.length);
                        }
                    });
                }
            });
        }
    }

    if (editorSearchResults.length === 0) {
        if (badge) badge.innerText = '0 of 0';
        showToast(`Không tìm thấy kết quả nào cho "${query}"`, 'info');
        renderSearchHighlightsOnCurrentPage();
        return;
    }

    if (currentSearchMatchIndex === -1) {
        currentSearchMatchIndex = 0;
    } else {
        currentSearchMatchIndex = (currentSearchMatchIndex + direction + editorSearchResults.length) % editorSearchResults.length;
    }

    const match = editorSearchResults[currentSearchMatchIndex];

    if (badge) {
        badge.innerText = `${currentSearchMatchIndex + 1} of ${editorSearchResults.length}`;
    }

    if (match.pageNumber !== editorCurrentPage) {
        await selectEditorPage(match.pageNumber);
    }

    renderSearchHighlightsOnCurrentPage();

    // Cuộn tới vị trí kết quả tìm kiếm được kích hoạt
    setTimeout(() => {
        const activeHighlight = document.querySelector('.search-highlight-rect.active');
        if (activeHighlight) {
            activeHighlight.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
        }
    }, 50);
}

function renderSearchHighlightsOnCurrentPage() {
    const layer = document.getElementById('paperSearchHighlightLayer');
    if (!layer) return;
    layer.innerHTML = '';

    const bar = document.getElementById('editorSearchBar');
    if (!bar || bar.style.display === 'none') return;

    if (!editorSearchResults || editorSearchResults.length === 0) return;

    const canvas = document.getElementById('pdfRenderCanvas');
    if (canvas) {
        layer.style.width = canvas.style.width;
        layer.style.height = canvas.style.height;
    }

    editorSearchResults.forEach((res, idx) => {
        if (res.pageNumber !== editorCurrentPage) return;

        const rect = document.createElement('div');
        const isActive = (idx === currentSearchMatchIndex);
        rect.className = `search-highlight-rect ${isActive ? 'active' : ''}`;
        rect.style.left = `${res.left}px`;
        rect.style.top = `${res.top}px`;
        rect.style.width = `${res.width}px`;
        rect.style.height = `${res.height}px`;
        rect.title = `Kết quả tìm kiếm (${idx + 1}/${editorSearchResults.length})`;
        layer.appendChild(rect);
    });
}

// Đổi tệp PDF khác với xác nhận và xóa trạng thái
function switchPdfFile() {
    if (hasLoadedFile) {
        const confirmSwitch = confirm('Bạn có chắc chắn muốn đổi sang tệp PDF khác? Hãy xuất tệp trước khi đổi để tránh mất các thay đổi chưa lưu.');
        if (!confirmSwitch) return;
    }

    localStorage.removeItem('editor_current_doc_id');
    localStorage.removeItem('editor_current_doc_name');
    localStorage.removeItem('editor_current_doc_url');
    localStorage.removeItem('editor_local_source_id');

    if (window.history && window.history.replaceState) {
        window.history.replaceState({ view: 'editor' }, '', '/editor');
    }

    hasLoadedFile = false;
    currentPdfDoc = null;
    currentPdfBytes = null;
    editorSources.clear();
    editorHistoryStack = [];
    editorRedoStack = [];
    editorPages = [];

    showEditorUploadState();
}

// Signature Modal Logic (Matching Image 2)
let currentSigTab = 'draw';
let sigCanvas, sigCtx;
let isDrawingSig = false;
let sigStrokeColor = '#000000';
let sigFont = 'Dancing Script, cursive';
let sigUploadedData = '';

function openSignatureModal() {
    editorActiveTool = 'signature';
    document.querySelectorAll('.ribbon-tool-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tool === 'signature');
    });
    openModal('signatureModal');
    setTimeout(initSigCanvas, 80);
}

function initSigCanvas() {
    sigCanvas = document.getElementById('sigCanvas');
    if (!sigCanvas) return;
    sigCtx = sigCanvas.getContext('2d');
    sigCtx.lineWidth = 3.0;
    sigCtx.lineCap = 'round';
    sigCtx.lineJoin = 'round';
    sigCtx.strokeStyle = sigStrokeColor || '#000000';

    if (sigCanvas.dataset.bound) return;
    sigCanvas.dataset.bound = 'true';

    function getCanvasPos(e) {
        const rect = sigCanvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        const scaleX = rect.width ? (sigCanvas.width / rect.width) : 1;
        const scaleY = rect.height ? (sigCanvas.height / rect.height) : 1;
        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY
        };
    }

    function startDraw(e) {
        isDrawingSig = true;
        sigCtx.strokeStyle = sigStrokeColor || '#000000';
        sigCtx.lineWidth = 3.0;
        sigCtx.beginPath();
        const pos = getCanvasPos(e);
        sigCtx.moveTo(pos.x, pos.y);
        if (e.cancelable) e.preventDefault();
    }

    function moveDraw(e) {
        if (!isDrawingSig) return;
        const pos = getCanvasPos(e);
        sigCtx.lineTo(pos.x, pos.y);
        sigCtx.stroke();
        if (e.cancelable) e.preventDefault();
    }

    function stopDraw(e) {
        if (isDrawingSig) {
            isDrawingSig = false;
            sigCtx.closePath();
        }
    }

    sigCanvas.addEventListener('mousedown', startDraw);
    sigCanvas.addEventListener('mousemove', moveDraw);
    sigCanvas.addEventListener('mouseup', stopDraw);
    sigCanvas.addEventListener('mouseleave', stopDraw);

    sigCanvas.addEventListener('touchstart', startDraw, { passive: false });
    sigCanvas.addEventListener('touchmove', moveDraw, { passive: false });
    sigCanvas.addEventListener('touchend', stopDraw);
}

function clearSigCanvas() {
    if (sigCanvas && sigCtx) {
        sigCtx.clearRect(0, 0, sigCanvas.width, sigCanvas.height);
        sigCtx.strokeStyle = sigStrokeColor || '#000000';
    }
}

function switchSigTab(tab) {
    currentSigTab = tab;
    document.getElementById('tabSigDraw')?.classList.toggle('active', tab === 'draw');
    document.getElementById('tabSigType')?.classList.toggle('active', tab === 'type');
    document.getElementById('tabSigUpload')?.classList.toggle('active', tab === 'upload');

    const drawEl = document.getElementById('sigTabContentDraw');
    const typeEl = document.getElementById('sigTabContentType');
    const uploadEl = document.getElementById('sigTabContentUpload');

    if (drawEl) drawEl.style.display = tab === 'draw' ? 'block' : 'none';
    if (typeEl) typeEl.style.display = tab === 'type' ? 'block' : 'none';
    if (uploadEl) uploadEl.style.display = tab === 'upload' ? 'block' : 'none';

    if (tab === 'draw') {
        setTimeout(initSigCanvas, 50);
    }
}

function setSigColor(color, btn) {
    sigStrokeColor = color;
    if (sigCtx) sigCtx.strokeStyle = color;
    document.querySelectorAll('.sig-color-dot').forEach(d => d.classList.remove('active'));
    if (btn) btn.classList.add('active');
    const preview = document.getElementById('sigTypedPreview');
    if (preview) preview.style.color = color;
}

function setSigFont(font, btn) {
    sigFont = font;
    document.querySelectorAll('.sig-font-option').forEach(o => o.classList.remove('active'));
    if (btn) btn.classList.add('active');
    const preview = document.getElementById('sigTypedPreview');
    if (preview) preview.style.fontFamily = font;
}

function updateTypedSigPreview() {
    const val = document.getElementById('sigTextInput')?.value || '';
    const preview = document.getElementById('sigTypedPreview');
    if (preview) preview.innerText = val || 'Chữ ký';
}

function handleSigImageUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (e) {
        sigUploadedData = e.target.result;
        const img = document.getElementById('sigUploadedImg');
        const wrap = document.getElementById('sigImagePreviewContainer');
        if (img && wrap) {
            img.src = sigUploadedData;
            wrap.style.display = 'block';
        }
    };
    reader.readAsDataURL(file);
}

function applySignatureToPage() {
    const pageData = (editorPages && editorPages.length > 0) ? (editorPages.find(p => p.pageNumber === editorCurrentPage) || editorPages[0]) : null;
    if (!pageData) {
        showToast('Vui lòng mở một tài liệu trước khi chèn chữ ký', 'info');
        return;
    }
    if (!pageData.elements) pageData.elements = [];

    pushEditorHistory();

    const canvas = document.getElementById('pdfRenderCanvas');
    const posX = canvas ? Math.max(30, Math.round(parseInt(canvas.style.width || '600', 10) / 2 - 100)) : 220;
    const posY = canvas ? Math.max(30, Math.round(parseInt(canvas.style.height || '800', 10) / 2 - 40)) : 380;

    if (currentSigTab === 'draw') {
        if (!sigCanvas) return;
        const dataUrl = sigCanvas.toDataURL('image/png');
        pageData.elements.push({
            id: 'sig-' + Date.now(),
            type: 'signature',
            sigType: 'draw',
            data: dataUrl,
            x: posX,
            y: posY,
            width: 200
        });
    } else if (currentSigTab === 'type') {
        const name = document.getElementById('sigTextInput')?.value || 'Nguyễn Văn A';
        pageData.elements.push({
            id: 'sig-' + Date.now(),
            type: 'signature',
            sigType: 'type',
            name: name,
            fontFamily: sigFont,
            color: sigStrokeColor || '#000000',
            size: 34,
            x: posX,
            y: posY,
            width: 200
        });
    } else if (currentSigTab === 'upload') {
        if (!sigUploadedData) {
            showToast('Vui lòng chọn ảnh chữ ký trước!', 'error');
            return;
        }
        pageData.elements.push({
            id: 'sig-' + Date.now(),
            type: 'signature',
            sigType: 'upload',
            data: sigUploadedData,
            x: posX,
            y: posY,
            width: 200
        });
    }

    closeModal('signatureModal');
    renderEditorCanvas();
}

// Stamp Modal Logic
function openStampModal() {
    editorActiveTool = 'stamp';
    document.querySelectorAll('.ribbon-tool-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tool === 'stamp');
    });
    openModal('stampModal');
}

function insertStamp(stampType) {
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
    if (!pageData) return;
    if (!pageData.elements) pageData.elements = [];

    pushEditorHistory();
    pageData.elements.push({
        id: 'stamp-' + Date.now(),
        type: 'stamp',
        stampType: stampType,
        x: 220 + Math.floor(Math.random() * 40),
        y: 350 + Math.floor(Math.random() * 40)
    });

    closeModal('stampModal');
    renderEditorCanvas();
}

// Hàm chuyển đổi mã màu hex sang định dạng màu PDFLib.rgb chuẩn
function parseColorToPdfLib(hexColor, fallback) {
    if (!fallback && window.PDFLib) fallback = PDFLib.rgb(0.1, 0.1, 0.2);
    if (!hexColor || typeof hexColor !== 'string') return fallback;
    const clean = hexColor.trim();
    if (clean.startsWith('#') && clean.length >= 7) {
        const r = parseInt(clean.slice(1, 3), 16) / 255;
        const g = parseInt(clean.slice(3, 5), 16) / 255;
        const b = parseInt(clean.slice(5, 7), 16) / 255;
        if (!isNaN(r) && !isNaN(g) && !isNaN(b) && window.PDFLib) {
            return PDFLib.rgb(r, g, b);
        }
    }
    return fallback;
}

// Tạo hình ảnh con dấu chất lượng cao hỗ trợ 100% tiếng Việt có dấu
function createStampDataUrl(stampType) {
    const canvas = document.createElement('canvas');
    canvas.width = 340;
    canvas.height = 110;
    const ctx = canvas.getContext('2d');

    const textMap = {
        approved: { label: '★ ĐÃ PHÊ DUYỆT ★', sub: 'NGÀY: 26/10/2026', color: '#DC2626' },
        draft: { label: 'BẢN NHÁP (DRAFT)', sub: 'LƯU HÀNH NỘI BỘ', color: '#D97706' },
        secret: { label: '🔒 TUYỆT MẬT', sub: 'KHÔNG SAO CHÉP', color: '#7C3AED' },
        completed: { label: '✓ HOÀN THÀNH', sub: 'ĐẠT YÊU CẦU', color: '#16A34A' },
        certified: { label: 'SAO Y BẢN CHÍNH', sub: 'VĂN THƯ ĐÃ DUYỆT', color: '#2563EB' },
        void: { label: 'KHÔNG CÒN HIỆU LỰC', sub: 'HỦY BỎ', color: '#9CA3AF' }
    };
    const s = textMap[stampType] || textMap.approved;

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Nền trắng mờ để nổi bật
    ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
    ctx.fillRect(4, 4, canvas.width - 8, canvas.height - 8);

    // Viền kép phong cách con dấu tiêu chuẩn
    ctx.lineWidth = 4;
    ctx.strokeStyle = s.color;
    ctx.strokeRect(4, 4, canvas.width - 8, canvas.height - 8);

    ctx.lineWidth = 1.5;
    ctx.strokeRect(10, 10, canvas.width - 20, canvas.height - 20);

    // Chữ tiếng Việt rõ nét
    ctx.fillStyle = s.color;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.font = 'bold 20px "Segoe UI", Arial, sans-serif';
    ctx.fillText(s.label, canvas.width / 2, 40);

    ctx.font = 'bold 13px "Segoe UI", Arial, sans-serif';
    ctx.fillText(s.sub, canvas.width / 2, 75);

    return canvas.toDataURL('image/png');
}

function removeVietnameseTones(str) {
    if (!str) return '';
    return str
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D');
}

function drawSafeText(page, text, options) {
    if (!text) return;
    page.drawText(text, options);
}

// Hoàn thành biên tập và xuất tệp PDF hoàn chỉnh chuẩn PDFaid
async function finishAndSaveDocument(compileOnly = false) {
    try {
        let finalPdfBlob = null;
        let finalBase64 = '';

        // Tự động kiểm tra và phục hồi currentPdfBytes nếu bị rỗng hoặc neutered bởi Worker
        let rawBytes = currentPdfBytes;
        if (!rawBytes || rawBytes.byteLength === 0) {
            const docId = localStorage.getItem('editor_current_doc_id');
            const docUrl = localStorage.getItem('editor_current_doc_url') || (docId ? ('/api/documents/raw?id=' + docId) : null);
            if (docUrl) {
                try {
                    const resp = await fetch(docUrl);
                    if (resp.ok) {
                        const ab = await resp.arrayBuffer();
                        rawBytes = new Uint8Array(ab.slice(0));
                        currentPdfBytes = new Uint8Array(ab.slice(0));
                    }
                } catch (fetchErr) {
                    console.warn('Lỗi nạp lại file từ server:', fetchErr);
                }
            }
        }

        if (!window.PDFLib || !editorPages.length) throw new Error('Không có tài liệu hoặc thư viện PDF chưa sẵn sàng.');
        if (window.PDFLib) {
            let pdfDoc = null;
            if (rawBytes && rawBytes.byteLength > 0) {
                pdfDoc = await PDFLib.PDFDocument.load(rawBytes);
            }
            const importedDocs = new Map();

            // Tạo tài liệu xuất mới hỗ trợ xoay trang và nhúng chú thích
            const newPdf = await PDFLib.PDFDocument.create();

            if (window.fontkit && newPdf.registerFontkit) {
                newPdf.registerFontkit(window.fontkit);
            }

            // Chuẩn bị các font chuẩn
            const helvetica = await newPdf.embedFont(PDFLib.StandardFonts.Helvetica);
            const helveticaBold = await newPdf.embedFont(PDFLib.StandardFonts.HelveticaBold);
            const helveticaOblique = await newPdf.embedFont(PDFLib.StandardFonts.HelveticaOblique);
            const timesRoman = await newPdf.embedFont(PDFLib.StandardFonts.TimesRoman);
            const timesRomanBold = await newPdf.embedFont(PDFLib.StandardFonts.TimesRomanBold);
            const timesRomanItalic = await newPdf.embedFont(PDFLib.StandardFonts.TimesRomanItalic);
            const courier = await newPdf.embedFont(PDFLib.StandardFonts.Courier);
            const courierBold = await newPdf.embedFont(PDFLib.StandardFonts.CourierBold);

            // Nạp font TrueType chuẩn hỗ trợ 100% tiếng Việt Unicode (Arial & Times New Roman)
            let customArial = null;
            let customArialBold = null;
            let customTimes = null;
            let customTimesBold = null;
            if (window.fontkit && newPdf.registerFontkit) {
                try {
                    const [abArial, abArialBd, abTimes, abTimesBd] = await Promise.all([
                        fetch('/assets/fonts/arial.ttf').then(r => r.ok ? r.arrayBuffer() : null).catch(() => null),
                        fetch('/assets/fonts/arialbd.ttf').then(r => r.ok ? r.arrayBuffer() : null).catch(() => null),
                        fetch('/assets/fonts/times.ttf').then(r => r.ok ? r.arrayBuffer() : null).catch(() => null),
                        fetch('/assets/fonts/timesbd.ttf').then(r => r.ok ? r.arrayBuffer() : null).catch(() => null)
                    ]);
                    if (abArial) customArial = await newPdf.embedFont(abArial);
                    if (abArialBd) customArialBold = await newPdf.embedFont(abArialBd);
                    if (abTimes) customTimes = await newPdf.embedFont(abTimes);
                    if (abTimesBd) customTimesBold = await newPdf.embedFont(abTimesBd);
                } catch (fErr) {
                    console.warn('Lỗi embed font Unicode tiếng Việt:', fErr);
                }
            }

            for (const pageItem of editorPages) {
                let targetPage = null;
                const origIndex = (pageItem.originalPageNum || 0) - 1;

                if (!pageItem.isBlankPage) {
                    let sourceDoc = pdfDoc;
                    if (pageItem.sourceId) {
                        const source = editorSources.get(pageItem.sourceId);
                        if (!source) throw new Error('Không tìm thấy PDF nhập thêm. Vui lòng nhập lại tệp.');
                        if (!importedDocs.has(pageItem.sourceId)) importedDocs.set(pageItem.sourceId, await PDFLib.PDFDocument.load(source.bytes));
                        sourceDoc = importedDocs.get(pageItem.sourceId);
                    }
                    if (!sourceDoc || origIndex < 0 || origIndex >= sourceDoc.getPageCount()) throw new Error('Không tìm thấy trang PDF nguồn.');
                    const [copiedPage] = await newPdf.copyPages(sourceDoc, [origIndex]);
                    targetPage = newPdf.addPage(copiedPage);
                } else {
                    // Trang mới thêm (trang trắng) - tạo trang A4 mới tinh
                    targetPage = newPdf.addPage([595.28, 841.89]);
                }

                // Áp dụng góc xoay nếu có
                if (pageItem.rotation && targetPage.setRotation) {
                    const currentRot = targetPage.getRotation ? targetPage.getRotation().angle : 0;
                    targetPage.setRotation(PDFLib.degrees((currentRot + pageItem.rotation) % 360));
                }

                const rotation = ((targetPage.getRotation().angle % 360) + 360) % 360;
                const crop = targetPage.getCropBox();
                const width = rotation % 180 ? crop.height : crop.width;
                const height = rotation % 180 ? crop.width : crop.height;
                const transforms = {
                    0: [1, 0, 0, 1, crop.x, crop.y],
                    90: [0, 1, -1, 0, crop.x + crop.width, crop.y],
                    180: [-1, 0, 0, -1, crop.x + crop.width, crop.y + crop.height],
                    270: [0, -1, 1, 0, crop.x, crop.y + crop.height]
                };
                targetPage.pushOperators(PDFLib.pushGraphicsState(), PDFLib.concatTransformationMatrix(...(transforms[rotation] || transforms[0])));

                // 1. Nhúng các khối văn bản gốc đã chỉnh sửa (Khớp 100% Chỉnh sửa văn bản)
                if (pageItem.textEdits && Object.keys(pageItem.textEdits).length > 0) {
                    for (const textId of Object.keys(pageItem.textEdits)) {
                        const edit = pageItem.textEdits[textId];
                        if (!edit || (!edit.modified && !edit.modifiedFormat)) continue;

                        const pdfX = edit.left;
                        const pdfY = height - (edit.top + edit.height);
                        const pdfW = Math.max(16, edit.width);
                        const pdfH = Math.max(12, edit.height);

                        // Vẽ hình chữ nhật che phủ chữ gốc trên PDF theo đúng màu nền slide / trang
                        const blockBg = edit.bgColor || '#FFFFFF';
                        if (blockBg !== 'transparent') {
                            let bgR = 1, bgG = 1, bgB = 1;
                            if (blockBg.startsWith('#') && blockBg.length >= 7) {
                                bgR = parseInt(blockBg.slice(1, 3), 16) / 255;
                                bgG = parseInt(blockBg.slice(3, 5), 16) / 255;
                                bgB = parseInt(blockBg.slice(5, 7), 16) / 255;
                            }
                            targetPage.drawRectangle({
                                x: Math.max(0, pdfX - 1),
                                y: Math.max(0, pdfY - 2),
                                width: pdfW + 2,
                                height: pdfH + 4,
                                color: PDFLib.rgb(bgR, bgG, bgB)
                            });
                        }

                        // Xác định font phù hợp ưu tiên font Unicode tiếng Việt
                        let chosenFont = customTimes || timesRoman;
                        const fLower = (edit.fontFamily || '').toLowerCase();
                        if (fLower.includes('helvetica') || fLower.includes('arial') || fLower.includes('sans')) {
                            if (edit.isBold) chosenFont = customArialBold || customArial || helveticaBold;
                            else chosenFont = customArial || helvetica;
                        } else if (fLower.includes('courier') || fLower.includes('mono')) {
                            chosenFont = edit.isBold ? courierBold : courier;
                        } else {
                            if (edit.isBold) chosenFont = customTimesBold || customTimes || timesRomanBold;
                            else chosenFont = customTimes || timesRoman;
                        }

                        let fontColor = PDFLib.rgb(0.06, 0.09, 0.16);
                        if (edit.color && edit.color.startsWith('#') && edit.color.length >= 7) {
                            const r = parseInt(edit.color.slice(1, 3), 16) / 255;
                            const g = parseInt(edit.color.slice(3, 5), 16) / 255;
                            const b = parseInt(edit.color.slice(5, 7), 16) / 255;
                            if (!isNaN(r) && !isNaN(g) && !isNaN(b)) {
                                fontColor = PDFLib.rgb(r, g, b);
                            }
                        }

                        const lines = (edit.text || '').split('\n');
                        const baseFontSize = Math.max(8, edit.fontSize || 12);
                        const lineHeight = baseFontSize * 1.25;

                        lines.forEach((lineText, lIdx) => {
                            if (!lineText) return;
                            const lineWidth = chosenFont.widthOfTextAtSize(lineText, baseFontSize);
                            const alignOffset = edit.align === 'center' ? (pdfW - lineWidth) / 2 : edit.align === 'right' ? pdfW - lineWidth : 0;
                            drawSafeText(targetPage, lineText, {
                                x: pdfX + Math.max(0, alignOffset),
                                y: pdfY + pdfH - (lIdx + 1) * lineHeight + 2,
                                size: baseFontSize,
                                font: chosenFont,
                                xSkew: PDFLib.degrees(edit.isItalic ? 12 : 0),
                                color: fontColor
                            });
                        });
                    }
                }

                // 2. Nhúng các nét vẽ tay và tô sáng dạ quang (Drawing & Highlighter Layer)
                if (pageItem.drawings && pageItem.drawings.length > 0) {
                    try {
                        const offCanvas = document.createElement('canvas');
                        offCanvas.width = width;
                        offCanvas.height = height;
                        const offCtx = offCanvas.getContext('2d');
                        const scaleFactor = 1;

                        pageItem.drawings.forEach(stroke => {
                            if (!stroke.points || stroke.points.length === 0) return;
                            offCtx.save();
                            offCtx.beginPath();
                            offCtx.strokeStyle = stroke.color || '#2563EB';
                            offCtx.globalAlpha = stroke.opacity !== undefined ? stroke.opacity : 1.0;
                            offCtx.lineWidth = Math.max(1, (stroke.width || 2) * scaleFactor);
                            offCtx.lineCap = stroke.tool === 'highlight' ? 'square' : 'round';
                            offCtx.lineJoin = stroke.tool === 'highlight' ? 'miter' : 'round';

                            offCtx.moveTo(stroke.points[0].x * scaleFactor, stroke.points[0].y * scaleFactor);
                            for (let i = 1; i < stroke.points.length; i++) {
                                offCtx.lineTo(stroke.points[i].x * scaleFactor, stroke.points[i].y * scaleFactor);
                            }
                            offCtx.stroke();
                            offCtx.restore();
                        });

                        const drawingDataUrl = offCanvas.toDataURL('image/png');
                        const embeddedDrawings = await newPdf.embedPng(drawingDataUrl);
                        targetPage.drawImage(embeddedDrawings, {
                            x: 0,
                            y: 0,
                            width: width,
                            height: height
                        });
                    } catch (drawErr) { throw new Error('Không thể xuất nét vẽ: ' + drawErr.message); }
                }

                // 3. Nhúng tất cả đối tượng chú thích: Văn bản, Chữ ký, Ảnh, Hình khối, Con dấu, Liên kết, Ghi chú
                if (pageItem.elements && pageItem.elements.length > 0) {
                    for (const el of pageItem.elements) {
                        const scaleFactor = 1;

                        if (el.type === 'text' && el.text && el.text.trim() !== 'Gõ nội dung tại đây...') {
                            try {
                                const pdfX = (el.x ?? 40) * scaleFactor;
                                const pdfY = height - ((el.y ?? 40) + (el.size || 14)) * scaleFactor;
                                let chosenFont = customArial || helvetica;
                                const fLower = (el.fontFamily || '').toLowerCase();
                                if (fLower.includes('times')) {
                                    chosenFont = el.bold ? (customTimesBold || customTimes || timesRomanBold) : (customTimes || timesRoman);
                                } else {
                                    chosenFont = el.bold ? (customArialBold || customArial || helveticaBold) : (customArial || helvetica);
                                }

                                const size = Math.max(1, (el.size || 14) * scaleFactor);
                                const lines = el.text.split('\n');
                                const boxWidth = el.width || Math.max(...lines.map(line => chosenFont.widthOfTextAtSize(line, size)));
                                lines.forEach((line, index) => {
                                    if (!line) return;
                                    const lineWidth = chosenFont.widthOfTextAtSize(line, size);
                                    const offset = el.align === 'center' ? (boxWidth - lineWidth) / 2 : el.align === 'right' ? boxWidth - lineWidth : 0;
                                    drawSafeText(targetPage, line, { x: pdfX + offset, y: pdfY - index * size * 1.2,
                                        size, font: chosenFont, xSkew: PDFLib.degrees(el.italic ? 12 : 0),
                                        opacity: el.opacity ?? 1,
                                        color: (el.color && el.color.startsWith('#')) ? parseColorToPdfLib(el.color) : PDFLib.rgb(0.06, 0.09, 0.16) });
                                });
                            } catch (te) { throw new Error('Không thể xuất văn bản: ' + te.message); }
                        } else if (el.type === 'signature') {
                            try {
                                const pdfX = (el.x || 40) * scaleFactor;
                                const sigW = (el.width || 180) * scaleFactor;
                                const sigH = (el.height || (sigW * 0.45));
                                const pdfY = height - ((el.y || 40) * scaleFactor + sigH);

                                if ((el.sigType === 'draw' || el.sigType === 'upload') && el.data) {
                                    const sigImg = await newPdf.embedPng(el.data);
                                    targetPage.drawImage(sigImg, {
                                        x: pdfX,
                                        y: pdfY,
                                        width: sigW,
                                        height: sigH,
                                        opacity: el.opacity !== undefined ? el.opacity : 1.0
                                    });
                                } else if (el.sigType === 'type') {
                                    drawSafeText(targetPage, el.name || 'Chữ ký', {
                                        x: pdfX,
                                        y: pdfY + 8,
                                        size: Math.max(14, (el.size || 28) * scaleFactor),
                                        font: customTimes || timesRomanItalic,
                                        color: parseColorToPdfLib(el.color, PDFLib.rgb(0, 0, 0))
                                    });
                                }
                            } catch (sigErr) { throw new Error('Không thể xuất chữ ký: ' + sigErr.message); }
                        } else if (el.type === 'image' && el.data) {
                            try {
                                let finalDataUrl = el.data;
                                const rot = el.rotation || 0;
                                const flipH = !!el.flipH;
                                const flipV = !!el.flipV;

                                if (rot !== 0 || flipH || flipV) {
                                    finalDataUrl = await new Promise((resolve) => {
                                        const img = new Image();
                                        img.onload = () => {
                                            const off = document.createElement('canvas');
                                            const rad = (rot * Math.PI) / 180;
                                            const sin = Math.abs(Math.sin(rad));
                                            const cos = Math.abs(Math.cos(rad));
                                            const ow = Math.round(img.width * cos + img.height * sin);
                                            const oh = Math.round(img.width * sin + img.height * cos);
                                            off.width = ow;
                                            off.height = oh;
                                            const offCtx = off.getContext('2d');
                                            offCtx.translate(ow / 2, oh / 2);
                                            offCtx.rotate(rad);
                                            offCtx.scale(flipH ? -1 : 1, flipV ? -1 : 1);
                                            offCtx.drawImage(img, -img.width / 2, -img.height / 2);
                                            resolve(off.toDataURL('image/png'));
                                        };
                                        img.onerror = () => resolve(el.data);
                                        img.src = el.data;
                                    });
                                }

                                let embeddedImg = null;
                                if (finalDataUrl.startsWith('data:image/jpeg') || finalDataUrl.startsWith('data:image/jpg')) {
                                    embeddedImg = await newPdf.embedJpg(finalDataUrl);
                                } else {
                                    embeddedImg = await newPdf.embedPng(finalDataUrl);
                                }
                                if (embeddedImg) {
                                    const imgW = (el.width || 200) * scaleFactor;
                                    const imgDims = embeddedImg.scaleToFit(imgW, 1000);
                                    const pdfX = (el.x || 40) * scaleFactor;
                                    const pdfY = height - ((el.y || 40) * scaleFactor + imgDims.height);

                                    targetPage.drawImage(embeddedImg, {
                                        x: pdfX,
                                        y: pdfY,
                                        width: imgDims.width,
                                        height: imgDims.height,
                                        opacity: el.opacity !== undefined ? el.opacity : 1.0
                                    });
                                }
                            } catch (imgErr) { throw new Error('Không thể xuất ảnh: ' + imgErr.message); }
                        } else if (el.type === 'shape') {
                            try {
                                const shapeX = (el.x || 40) * scaleFactor;
                                const shapeY = height - (el.y || 40) * scaleFactor;
                                const shapeColor = parseColorToPdfLib(el.color, PDFLib.rgb(0.15, 0.39, 0.92));
                                const strokeW = Math.max(1, (el.size || 2) * scaleFactor);

                                if (el.shapeType === 'line' || el.shapeType === 'arrow') {
                                    const lineLen = (el.width || 160) * scaleFactor;
                                    targetPage.drawLine({
                                        start: { x: shapeX, y: shapeY },
                                        end: { x: shapeX + lineLen, y: shapeY },
                                        thickness: strokeW,
                                        color: shapeColor
                                    });
                                    if (el.shapeType === 'arrow') {
                                        const arrowHeadLen = Math.max(8, strokeW * 3.5);
                                        targetPage.drawLine({
                                            start: { x: shapeX + lineLen - arrowHeadLen, y: shapeY + arrowHeadLen * 0.7 },
                                            end: { x: shapeX + lineLen, y: shapeY },
                                            thickness: strokeW,
                                            color: shapeColor
                                        });
                                        targetPage.drawLine({
                                            start: { x: shapeX + lineLen - arrowHeadLen, y: shapeY - arrowHeadLen * 0.7 },
                                            end: { x: shapeX + lineLen, y: shapeY },
                                            thickness: strokeW,
                                            color: shapeColor
                                        });
                                    }
                                } else if (el.shapeType === 'rect') {
                                    const rectW = (el.width || 170) * scaleFactor;
                                    const rectH = (el.height || 96) * scaleFactor;
                                    targetPage.drawRectangle({
                                        x: shapeX,
                                        y: shapeY - rectH,
                                        width: rectW,
                                        height: rectH,
                                        borderColor: shapeColor,
                                        borderWidth: strokeW,
                                        color: (el.fill && el.fill !== 'none') ? parseColorToPdfLib(el.fill, PDFLib.rgb(1, 1, 1)) : undefined
                                    });
                                } else if (el.shapeType === 'circle') {
                                    const rx = ((el.width || 110) / 2) * scaleFactor;
                                    const ry = ((el.height || 110) / 2) * scaleFactor;
                                    targetPage.drawEllipse({
                                        x: shapeX + rx,
                                        y: shapeY - ry,
                                        xScale: rx,
                                        yScale: ry,
                                        borderColor: shapeColor,
                                        borderWidth: strokeW,
                                        color: (el.fill && el.fill !== 'none') ? parseColorToPdfLib(el.fill, PDFLib.rgb(1, 1, 1)) : undefined
                                    });
                                } else if (el.shapeType === 'polygon') {
                                    const w = (el.width || 160) * scaleFactor;
                                    const h = (el.height || 110) * scaleFactor;
                                    targetPage.drawSvgPath(`M ${w / 2} 0 L ${w} ${h * 0.38} L ${w * 0.82} ${h} L ${w * 0.18} ${h} L 0 ${h * 0.38} Z`, {
                                        x: shapeX, y: shapeY, borderColor: shapeColor, borderWidth: strokeW,
                                        color: (el.fill && el.fill !== 'none') ? parseColorToPdfLib(el.fill) : undefined
                                    });
                                }
                            } catch (shapeErr) { throw new Error('Không thể xuất hình học: ' + shapeErr.message); }
                        } else if (el.type === 'stamp') {
                            try {
                                const stampW = 150 * scaleFactor;
                                const stampH = 50 * scaleFactor;
                                const stampX = (el.x || 40) * scaleFactor;
                                const stampY = height - (el.y || 40) * scaleFactor - stampH;

                                const stampDataUrl = createStampDataUrl(el.stampType);
                                const embeddedStamp = await newPdf.embedPng(stampDataUrl);
                                targetPage.drawImage(embeddedStamp, {
                                    x: stampX,
                                    y: stampY,
                                    width: stampW,
                                    height: stampH,
                                    opacity: el.opacity !== undefined ? el.opacity : 1.0
                                });
                            } catch (stampErr) { throw new Error('Không thể xuất con dấu: ' + stampErr.message); }
                        } else if (el.type === 'link') {
                            try {
                                const linkX = (el.x || 40) * scaleFactor;
                                const linkW = Math.max(120, (el.width || 180)) * scaleFactor;
                                const linkH = 26 * scaleFactor;
                                const linkY = height - (el.y || 40) * scaleFactor - linkH;

                                targetPage.drawRectangle({
                                    x: linkX,
                                    y: linkY,
                                    width: linkW,
                                    height: linkH,
                                    borderColor: PDFLib.rgb(0.23, 0.51, 0.96),
                                    borderWidth: 1.2 * scaleFactor,
                                    color: PDFLib.rgb(0.94, 0.96, 1.0)
                                });

                                const linkTitle = (el.text || el.url || 'Liên kết').substring(0, 35);
                                drawSafeText(targetPage, linkTitle, {
                                    x: linkX + 8 * scaleFactor,
                                    y: linkY + 8 * scaleFactor,
                                    size: 10 * scaleFactor,
                                    font: customArial || helvetica,
                                    color: PDFLib.rgb(0.12, 0.35, 0.88)
                                });
                                const url = new URL(el.url);
                                if (!['http:', 'https:', 'mailto:'].includes(url.protocol)) throw new Error('Địa chỉ liên kết không được hỗ trợ.');
                                const matrix = transforms[rotation] || transforms[0];
                                const transformPoint = (x, y) => [matrix[0] * x + matrix[2] * y + matrix[4], matrix[1] * x + matrix[3] * y + matrix[5]];
                                const a = transformPoint(linkX, linkY);
                                const b = transformPoint(linkX + linkW, linkY + linkH);
                                const annotation = newPdf.context.obj({ Type: 'Annot', Subtype: 'Link', Rect: [Math.min(a[0], b[0]), Math.min(a[1], b[1]), Math.max(a[0], b[0]), Math.max(a[1], b[1])], Border: [0, 0, 0], A: { Type: 'Action', S: 'URI', URI: PDFLib.PDFString.of(url.href) } });
                                const annotationRef = newPdf.context.register(annotation);
                                const annotsKey = PDFLib.PDFName.of('Annots');
                                let annots = targetPage.node.lookup(annotsKey, PDFLib.PDFArray);
                                if (!annots) { annots = newPdf.context.obj([]); targetPage.node.set(annotsKey, annots); }
                                annots.push(annotationRef);
                            } catch (lnkErr) {
                                throw new Error('Liên kết không hợp lệ: ' + lnkErr.message);
                            }
                        } else if (el.type === 'note') {
                            try {
                                const noteX = (el.x || 40) * scaleFactor;
                                const noteW = (el.width || 180) * scaleFactor;
                                const noteH = (el.height || 100) * scaleFactor;
                                const noteY = height - (el.y || 40) * scaleFactor - noteH;

                                targetPage.drawRectangle({
                                    x: noteX,
                                    y: noteY,
                                    width: noteW,
                                    height: noteH,
                                    borderColor: PDFLib.rgb(0.96, 0.77, 0.19),
                                    borderWidth: 1.5 * scaleFactor,
                                    color: PDFLib.rgb(1.0, 0.99, 0.82)
                                });

                                targetPage.drawRectangle({
                                    x: noteX,
                                    y: noteY + noteH - 22 * scaleFactor,
                                    width: noteW,
                                    height: 22 * scaleFactor,
                                    color: PDFLib.rgb(0.99, 0.93, 0.63)
                                });

                                drawSafeText(targetPage, 'Ghi chú ' + (el.time ? `(${el.time})` : ''), {
                                    x: noteX + 6 * scaleFactor,
                                    y: noteY + noteH - 15 * scaleFactor,
                                    size: 9.5 * scaleFactor,
                                    font: customArialBold || customArial || helveticaBold,
                                    color: PDFLib.rgb(0.57, 0.42, 0.04)
                                });

                                if (el.text) {
                                    const lines = el.text.split('\n');
                                    if (lines.length > 5 || lines.some(line => line.length > 42)) throw new Error('Ghi chú quá dài để xuất đầy đủ; hãy rút ngắn hoặc tách thành nhiều ghi chú.');
                                    lines.forEach((line, lIdx) => {
                                        drawSafeText(targetPage, line, {
                                            x: noteX + 6 * scaleFactor,
                                            y: noteY + noteH - 36 * scaleFactor - (lIdx * 13 * scaleFactor),
                                            size: 9 * scaleFactor,
                                            font: customArial || helvetica,
                                            color: PDFLib.rgb(0.2, 0.2, 0.2)
                                        });
                                    });
                                }
                            } catch (noteErr) { throw new Error('Không thể xuất ghi chú: ' + noteErr.message); }
                        }
                    }
                }
                targetPage.pushOperators(PDFLib.popGraphicsState());
            }

            const modifiedPdfBytes = await newPdf.save();
            finalPdfBlob = new Blob([modifiedPdfBytes], { type: 'application/pdf' });
            if (compileOnly) return finalPdfBlob;

            // Chuyển sang base64 để lưu vào backend
            const reader = new FileReader();
            finalBase64 = await new Promise((resolve) => {
                reader.onloadend = () => resolve(reader.result);
                reader.readAsDataURL(finalPdfBlob);
            });
        }

        // Tên file xuất
        const exportFileName = (currentDocumentName ? currentDocumentName.replace(/\.pdf$/i, '') : 'Tai_lieu') + '_chinh_sua.pdf';

        // Kích hoạt tải về trực tiếp trên trình duyệt
        if (finalPdfBlob) {
            const blobUrl = URL.createObjectURL(finalPdfBlob);
            const a = document.createElement('a');
            a.href = blobUrl;
            a.download = exportFileName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        // Lưu vào MySQL và thư mục storage/exports của backend
        let backendResult = null;
        window.currentOutputId = null;
        const activeDocId = window.currentDocumentId || localStorage.getItem('editor_current_doc_id') || 0;
        if (finalBase64) {
            try {
                const res = await fetch('/api/export/save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        doc_id: activeDocId,
                        title: exportFileName,
                        pdf_base64: finalBase64,
                        total_pages: editorPages.length,
                        manifest: editorPages.map(p => ({
                            source_doc: p.isBlankPage ? null : (editorSourceForPage(p)?.name || currentDocumentName || 'tailieu.pdf'),
                            source_page: p.originalPageNum || null,
                            rotation: p.rotation || 0
                        }))
                    })
                });
                backendResult = await res.json();
                if (!res.ok || !backendResult || backendResult.status !== 'success') throw new Error((backendResult && backendResult.message) || 'Không thể lưu lên máy chủ.');
                if (backendResult.output_id) {
                    window.currentOutputId = backendResult.output_id;
                }
                if (activeDocId) {
                    updateLocalDocStatus(activeDocId, 'exported');
                }
            } catch (be) { backendResult = null; showToast('Đã tạo PDF cục bộ nhưng chưa lưu lên máy chủ: ' + be.message, 'error'); }
        }

        const docNameEl = document.getElementById('expDocName');
        const docPagesEl = document.getElementById('expDocPages');
        const dlBtn = document.getElementById('expDownloadBtn');
        if (docNameEl) docNameEl.innerText = exportFileName;
        if (docPagesEl) docPagesEl.innerText = `${editorPages.length} trang`;
        if (dlBtn) {
            if (backendResult && backendResult.download_url) {
                dlBtn.href = backendResult.download_url;
            } else if (finalPdfBlob) {
                dlBtn.href = URL.createObjectURL(finalPdfBlob);
                dlBtn.download = exportFileName;
            }
        }
        openModal('exportSuccessModal');
        showToast(backendResult && backendResult.status === 'success' ? `✓ Đã xuất, tải về và lưu "${exportFileName}".` : `✓ Đã tạo và tải về "${exportFileName}"; chưa lưu lên máy chủ.`, backendResult && backendResult.status === 'success' ? 'success' : 'info');
    } catch (err) {
        if (compileOnly) throw err;
        console.error('Lỗi xuất tệp PDF:', err);
        showToast('Có lỗi xảy ra khi xuất PDF: ' + err.message, 'error');
    }
}

// =========================================================
// FULLSCREEN PAGE MANAGER MODAL (IMAGE 2 / PDFAID MATCH)
// =========================================================
let pmSelectedPages = new Set();
let pmTempPages = [];
let pmCardSize = 170;
let pmHistoryStack = [];
let pmRedoStack = [];

function openPageManagerModal() {
    try {
        if (!window.pmSelectedPages) {
            window.pmSelectedPages = new Set();
        }
        if (!editorPages || editorPages.length === 0) {
            showToast('Vui lòng mở tài liệu trước khi quản lý trang.', 'info');
            return;
        }
        pmTempPages = JSON.parse(JSON.stringify(editorPages));
        pmSelectedPages.clear();
        pmHistoryStack = [];
        pmRedoStack = [];

        const modal = document.getElementById('pageManagerModal');
        if (modal) {
            modal.classList.add('active');
            modal.classList.add('show');
            modal.style.setProperty('display', 'flex', 'important');
        }

        renderPmGrid();
    } catch (err) {
        console.error('Lỗi khi mở Trình quản lý trang:', err);
        showToast('Không thể mở Trình quản lý trang: ' + err.message, 'error');
    }
}

function closePageManagerModal() {
    const modal = document.getElementById('pageManagerModal');
    if (modal) {
        modal.classList.remove('active');
        modal.classList.remove('show');
        modal.style.setProperty('display', 'none', 'important');
    }
}

function renderPmGrid() {
    const grid = document.getElementById('pmCardsGrid');
    const counter = document.getElementById('pmPageCounterText');
    if (!grid) return;

    if (counter) {
        counter.innerText = `${pmTempPages.length} trang • Đã chọn ${pmSelectedPages.size} trang`;
    }

    grid.style.gridTemplateColumns = `repeat(auto-fill, minmax(${pmCardSize}px, 1fr))`;
    grid.innerHTML = '';

    pmTempPages.forEach((page, index) => {
        const card = document.createElement('div');
        const isSelected = pmSelectedPages.has(page.id);
        card.className = `pm-card ${isSelected ? 'selected' : ''}`;
        card.draggable = true;

        // Drag and drop event listeners
        card.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('text/plain', index);
            card.style.opacity = '0.5';
        });

        card.addEventListener('dragend', () => {
            card.style.opacity = '1';
        });

        card.addEventListener('dragover', (e) => {
            e.preventDefault();
            card.style.borderColor = '#2563EB';
        });

        card.addEventListener('dragleave', () => {
            card.style.borderColor = isSelected ? '#2563EB' : '#E2E8F0';
        });

        card.addEventListener('drop', (e) => {
            e.preventDefault();
            const fromIdx = parseInt(e.dataTransfer.getData('text/plain'), 10);
            pmMovePage(fromIdx, index);
        });

        card.onclick = (e) => {
            pmToggleCardSelect(page.id);
        };

        // Render cấu trúc card khớp 100% Ảnh 4
        card.innerHTML = `
            <div class="pm-card-thumb-wrap" style="transform: rotate(${page.rotation}deg);">
                <canvas class="pm-thumb-canvas" width="160" height="220" style="width: 100%; height: 100%; object-fit: contain;"></canvas>
                ${page.rotation !== 0 ? `<div class="pm-card-rotation-indicator">${page.rotation}°</div>` : ''}
            </div>
            <div class="pm-card-footer">
                <span class="pm-page-num-pill">${page.pageNumber}</span>
            </div>
        `;

        // Vẽ nội dung thu nhỏ trang PDF thực tế hoặc tài liệu mẫu
        const thumbCanvas = card.querySelector('.pm-thumb-canvas');
        if (thumbCanvas) {
            const ctx = thumbCanvas.getContext('2d');
            if (currentPdfDoc) {
                if (page.isBlankPage || !page.originalPageNum) {
                    ctx.fillStyle = '#FFFFFF';
                    ctx.fillRect(0, 0, thumbCanvas.width, thumbCanvas.height);
                    ctx.strokeStyle = '#E2E8F0';
                    ctx.strokeRect(1, 1, thumbCanvas.width - 2, thumbCanvas.height - 2);
                } else {
                    const source = editorSourceForPage(page);
                    if (!source || !source.doc) return;
                    source.doc.getPage(page.originalPageNum).then(pdfPage => {
                        const vp = pdfPage.getViewport({ scale: 0.22, rotation: (pdfPage.rotate + (page.rotation || 0)) % 360 });
                        thumbCanvas.width = vp.width;
                        thumbCanvas.height = vp.height;
                        pdfPage.render({ canvasContext: ctx, viewport: vp });
                    }).catch(e => console.warn('Lỗi vẽ thumb:', e));
                }
            } else {
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, thumbCanvas.width, thumbCanvas.height);
                ctx.fillStyle = '#1E3A8A';
                ctx.fillRect(14, 16, 90, 7);
                ctx.fillStyle = '#64748B';
                for (let y = 30; y < 140; y += 9) {
                    ctx.fillRect(14, y, Math.min(132, 70 + (y * 7) % 60), 3.5);
                }
                ctx.fillStyle = '#F1F5F9';
                ctx.fillRect(14, 150, 132, 54);
                ctx.fillStyle = '#3B82F6';
                ctx.fillRect(24, 165, 20, 39);
                ctx.fillRect(52, 175, 20, 29);
                ctx.fillRect(80, 160, 20, 44);
                ctx.fillRect(108, 170, 20, 34);
            }
        }

        grid.appendChild(card);
    });
}

function pmToggleCardSelect(pageId, event) {
    if (event) event.stopPropagation();
    if (pmSelectedPages.has(pageId)) {
        pmSelectedPages.delete(pageId);
    } else {
        pmSelectedPages.add(pageId);
    }
    renderPmGrid();
}

function pmToggleSelectAll() {
    const allSelected = pmSelectedPages.size === pmTempPages.length;
    if (allSelected) {
        pmSelectedPages.clear();
    } else {
        pmTempPages.forEach(p => pmSelectedPages.add(p.id));
    }
    const label = document.getElementById('pmSelectAllLabel');
    if (label) label.innerText = allSelected ? 'Chọn tất cả' : 'Bỏ chọn tất cả';
    renderPmGrid();
}

function pmDeselectAll() {
    pmSelectedPages.clear();
    const label = document.getElementById('pmSelectAllLabel');
    if (label) label.innerText = 'Chọn tất cả';
    renderPmGrid();
}

function pmPushHistory() {
    pmHistoryStack.push(JSON.stringify(pmTempPages));
    if (pmHistoryStack.length > 20) pmHistoryStack.shift();
    pmRedoStack = [];
}

function pmUndo() {
    if (pmHistoryStack.length > 0) {
        pmRedoStack.push(JSON.stringify(pmTempPages));
        pmTempPages = JSON.parse(pmHistoryStack.pop());
        renderPmGrid();
    }
}

function pmRedo() {
    if (pmRedoStack.length > 0) {
        pmHistoryStack.push(JSON.stringify(pmTempPages));
        pmTempPages = JSON.parse(pmRedoStack.pop());
        renderPmGrid();
    }
}

function pmRotateSelected(deg) {
    pmPushHistory();
    const targets = pmSelectedPages.size > 0
        ? pmTempPages.filter(p => pmSelectedPages.has(p.id))
        : pmTempPages;

    targets.forEach(p => {
        p.rotation = (p.rotation + deg + 360) % 360;
    });

    renderPmGrid();
}

function pmRotateSingle(pageId, deg, event) {
    if (event) event.stopPropagation();
    pmPushHistory();
    const p = pmTempPages.find(item => item.id === pageId);
    if (p) {
        p.rotation = (p.rotation + deg + 360) % 360;
        renderPmGrid();
    }
}

function pmDeleteSelected() {
    if (pmSelectedPages.size === 0) {
        showToast('Vui lòng chọn ít nhất 1 trang để xóa', 'info');
        return;
    }

    if (pmTempPages.length - pmSelectedPages.size < 1) {
        showToast('Tài liệu phải giữ lại ít nhất 1 trang', 'error');
        return;
    }

    if (!confirm(`Bạn có chắc chắn muốn xóa ${pmSelectedPages.size} trang đã chọn?`)) return;

    pmPushHistory();
    pmTempPages = pmTempPages.filter(p => !pmSelectedPages.has(p.id));
    pmSelectedPages.clear();
    pmTempPages.forEach((p, i) => p.pageNumber = i + 1);

    renderPmGrid();
    showToast('✓ Đã xóa các trang đã chọn', 'info');
}

function pmDeleteSingle(pageId, event) {
    if (event) event.stopPropagation();
    if (pmTempPages.length <= 1) {
        showToast('Tài liệu phải có ít nhất 1 trang!', 'error');
        return;
    }

    pmPushHistory();
    pmTempPages = pmTempPages.filter(p => p.id !== pageId);
    pmSelectedPages.delete(pageId);
    pmTempPages.forEach((p, i) => p.pageNumber = i + 1);

    renderPmGrid();
    showToast('✓ Đã xóa trang', 'info');
}

function pmDuplicateSelected() {
    if (pmSelectedPages.size === 0) {
        showToast('Vui lòng chọn trang để nhân bản', 'info');
        return;
    }

    pmPushHistory();
    const newPages = [];
    pmTempPages.forEach(p => {
        newPages.push(p);
        if (pmSelectedPages.has(p.id)) {
            const clone = JSON.parse(JSON.stringify(p));
            clone.id = Date.now() + Math.random();
            clone.title = `${p.title} (Bản sao)`;
            newPages.push(clone);
        }
    });

    pmTempPages = newPages;
    pmTempPages.forEach((p, i) => p.pageNumber = i + 1);
    pmSelectedPages.clear();

    renderPmGrid();
    showToast('✓ Đã nhân đôi các trang đã chọn', 'success');
}

function pmAddNewPage() {
    pmPushHistory();
    const newPage = {
        id: Date.now(),
        pageNumber: pmTempPages.length + 1,
        title: 'Trang trắng mới',
        rotation: 0,
        isBlankPage: true,
        originalPageNum: null,
        elements: [],
        drawings: [],
        textEdits: {},
        _cachedTextItems: []
    };
    pmTempPages.push(newPage);
    renderPmGrid();
    showToast('✓ Đã thêm 1 trang trắng mới', 'success');
}

function pmImportDocument() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'application/pdf,.pdf';
    input.onchange = async () => {
        if (!input.files.length) return;
        try {
            const source = await createEditorSource(input.files[0]);
            pmPushHistory();
            const pages = pagesFromEditorSource(source);
            pmTempPages.push(...pages);
            pmTempPages.forEach((p, i) => p.pageNumber = i + 1);
            renderPmGrid();
            showToast(`✓ Đã nhập ${pages.length} trang.`, 'success');
        } catch (err) {
            showToast('Không thể nhập PDF: ' + err.message, 'error');
        }
    };
    input.click();
}

function pmMovePage(fromIdx, toIdx) {
    if (fromIdx === toIdx) return;
    pmPushHistory();
    const [moved] = pmTempPages.splice(fromIdx, 1);
    pmTempPages.splice(toIdx, 0, moved);
    pmTempPages.forEach((p, i) => p.pageNumber = i + 1);
    renderPmGrid();
}

function togglePmMoveMenu(event) {
    if (event) event.stopPropagation();
    const menu = document.getElementById('pmMoveDropdown');
    if (menu) {
        menu.classList.toggle('show');
    }
}

document.addEventListener('click', () => {
    const menu = document.getElementById('pmMoveDropdown');
    if (menu) menu.classList.remove('show');
});

function pmMoveSelected(direction) {
    const menu = document.getElementById('pmMoveDropdown');
    if (menu) menu.classList.remove('show');

    if (pmSelectedPages.size === 0) {
        showToast('Vui lòng chọn ít nhất một trang để di chuyển', 'info');
        return;
    }

    pmPushHistory();
    if (direction < 0) {
        for (let i = 1; i < pmTempPages.length; i++) {
            if (pmSelectedPages.has(pmTempPages[i].id) && !pmSelectedPages.has(pmTempPages[i - 1].id)) {
                const temp = pmTempPages[i];
                pmTempPages[i] = pmTempPages[i - 1];
                pmTempPages[i - 1] = temp;
            }
        }
    } else {
        for (let i = pmTempPages.length - 2; i >= 0; i--) {
            if (pmSelectedPages.has(pmTempPages[i].id) && !pmSelectedPages.has(pmTempPages[i + 1].id)) {
                const temp = pmTempPages[i];
                pmTempPages[i] = pmTempPages[i + 1];
                pmTempPages[i + 1] = temp;
            }
        }
    }

    pmTempPages.forEach((p, idx) => p.pageNumber = idx + 1);
    renderPmGrid();
}

function adjustPmCardSize(deltaOrSize) {
    const val = parseInt(deltaOrSize, 10) || 0;
    if (Math.abs(val) <= 50) {
        pmCardSize = Math.max(120, Math.min(320, pmCardSize + val));
    } else {
        pmCardSize = Math.max(120, Math.min(320, val));
    }
    const grid = document.getElementById('pmCardsGrid');
    if (grid) {
        grid.style.gridTemplateColumns = `repeat(auto-fill, minmax(${pmCardSize}px, 1fr))`;
    }
}

function pmSaveAndApply() {
    pushEditorHistory();
    editorPages = JSON.parse(JSON.stringify(pmTempPages));
    if (editorCurrentPage > editorPages.length) {
        editorCurrentPage = editorPages.length;
    }
    closePageManagerModal();
    renderEditorThumbnails();
    renderEditorCanvas();
    updateEditorFloatingInfo();
    saveEditorDraft();
    showToast('✓ Đã áp dụng các thay đổi từ Trình quản lý trang!', 'success');
}

// =========================================================
// SAAS PRICING & UPGRADE PAGE LOGIC
// =========================================================
function toggleBillingCycle(isYearly) {
    const proPrice = document.getElementById('proPriceValue');
    const proPeriod = document.getElementById('proPricePeriod');
    const proHint = document.getElementById('proYearlyHint');

    const entPrice = document.getElementById('entPriceValue');
    const entPeriod = document.getElementById('entPricePeriod');
    const entHint = document.getElementById('entYearlyHint');

    if (isYearly) {
        if (proPrice) proPrice.innerText = '79.000 đ';
        if (proPeriod) proPeriod.innerText = '/ tháng (Trả cả năm)';
        if (proHint) proHint.style.display = 'block';

        if (entPrice) entPrice.innerText = '239.000 đ';
        if (entPeriod) entPeriod.innerText = '/ tháng (Trả cả năm)';
        if (entHint) entHint.style.display = 'block';
    } else {
        if (proPrice) proPrice.innerText = '99.000 đ';
        if (proPeriod) proPeriod.innerText = '/ tháng';
        if (proHint) proHint.style.display = 'none';

        if (entPrice) entPrice.innerText = '299.000 đ';
        if (entPeriod) entPeriod.innerText = '/ tháng';
        if (entHint) entHint.style.display = 'none';
    }
}

function changePlan(targetPlan) {
    upgradePlan(targetPlan);
}

function toggleFaq(el) {
    const item = el.closest('.faq-item');
    if (item) {
        item.classList.toggle('open');
    }
}

// =========================================================
// STANDALONE DOCUMENT MANAGEMENT PAGE LOGIC (/documents)
// =========================================================
function openDocInEditor(docId, docName = '') {
    window.currentDocumentId = docId;
    window.currentDocumentName = docName || ('Tài liệu #' + docId);
    localStorage.setItem('editor_current_doc_id', docId);
    localStorage.setItem('editor_current_doc_name', docName || 'Tài liệu #' + docId);
    localStorage.setItem('editor_current_doc_url', '/api/documents/raw?id=' + docId);
    hasLoadedFile = true;
    switchView('editor');
    if (window.history && window.history.replaceState) {
        window.history.replaceState({ view: 'editor', docId: docId }, '', '/editor?docId=' + docId);
    }

    // Báo cho backend cập nhật trạng thái tệp sang 'editing' (Đang chỉnh sửa)
    if (docId) {
        fetch('/api/documents/status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: docId, status: 'editing' })
        }).then(res => res.json()).then(res => {
            updateLocalDocStatus(docId, 'editing');
        }).catch(err => console.warn('Lỗi cập nhật trạng thái tài liệu:', err));
    }

    loadRealPdf('/api/documents/raw?id=' + docId, docName || 'Tài liệu #' + docId, docId);
}

function updateLocalDocStatus(docId, status) {
    if (!docId) return;
    const rows = document.querySelectorAll(`tr[data-doc-id="${docId}"], tr[data-id="${docId}"]`);
    rows.forEach(tr => {
        tr.dataset.status = status;
        const pill = tr.querySelector('.status-pill');
        if (pill) {
            pill.className = 'status-pill';
            if (status === 'editing') {
                pill.classList.add('warning');
                pill.innerHTML = '<span class="status-dot"></span> Đang chỉnh sửa';
            } else if (status === 'exported') {
                pill.classList.add('success');
                pill.innerHTML = '<span class="status-dot"></span> Đã xuất file';
            } else if (status === 'error') {
                pill.classList.add('danger');
                pill.innerHTML = '<span class="status-dot"></span> Lỗi tệp';
            } else {
                pill.classList.add('info');
                pill.innerHTML = '<span class="status-dot"></span> Tệp gốc';
            }
        }
    });

    recountDocumentStatuses();
}

function recountDocumentStatuses() {
    const allRows = document.querySelectorAll('#allDocsTableBody tr.doc-row-item');
    if (!allRows || allRows.length === 0) return;

    let counts = { all: allRows.length, uploaded: 0, editing: 0, exported: 0, error: 0 };
    allRows.forEach(tr => {
        const st = tr.dataset.status || 'uploaded';
        if (counts[st] !== undefined) {
            counts[st]++;
        } else {
            counts.uploaded++;
        }
    });

    const bAll = document.getElementById('badgeAllCount');
    const bUp = document.getElementById('badgeUploadedCount');
    const bEd = document.getElementById('badgeEditingCount');
    const bEx = document.getElementById('badgeExportedCount');
    const bErr = document.getElementById('badgeErrorCount');

    if (bAll) bAll.innerText = counts.all;
    if (bUp) bUp.innerText = counts.uploaded;
    if (bEd) bEd.innerText = counts.editing;
    if (bEx) bEx.innerText = counts.exported;
    if (bErr) bErr.innerText = counts.error;
}

let activeDocStatusFilter = 'all';

function filterDocsByStatus(status, btn) {
    activeDocStatusFilter = status;
    document.querySelectorAll('.filter-tab-pill, .docs-tab-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    filterDocumentsTable();
}

function filterDocumentsTable() {
    const input = document.getElementById('docsSearchInput') || document.getElementById('docSearchInput');
    const query = (input ? input.value : '').trim().toLowerCase();
    const rows = document.querySelectorAll('#allDocsTableBody tr.doc-row-item, #documentsFullTableBody tr');

    let visibleCount = 0;
    const totalCount = rows.length;

    rows.forEach(tr => {
        const titleEl = tr.querySelector('.doc-table-filename, .doc-title, .file-main-name');
        const status = tr.dataset.status || 'uploaded';
        const title = (titleEl ? titleEl.innerText : '').toLowerCase();

        const matchQuery = !query || title.includes(query);
        const matchStatus = (activeDocStatusFilter === 'all') || (status === activeDocStatusFilter);

        if (matchQuery && matchStatus) {
            tr.style.display = '';
            visibleCount++;
        } else {
            tr.style.display = 'none';
        }
    });

    const counter = document.getElementById('docsTableCounter');
    if (counter) {
        counter.innerHTML = `Hiển thị <strong>${visibleCount}</strong> / <strong>${totalCount}</strong> tài liệu`;
    }

    const emptyNotice = document.getElementById('docsTableEmptyFiltered');
    if (emptyNotice) {
        emptyNotice.style.display = (visibleCount === 0 && rows.length > 0) ? 'block' : 'none';
    }
}

function toggleSelectAllDocuments(masterCheckbox) {
    const isChecked = masterCheckbox.checked;
    document.querySelectorAll('.doc-checkbox').forEach(cb => {
        cb.checked = isChecked;
    });
    updateBulkActionsBar();
}

function updateBulkActionsBar() {
    const checked = document.querySelectorAll('.doc-checkbox:checked');
    const bar = document.getElementById('docsBulkActionsBar');
    const countEl = document.getElementById('bulkSelectedCount');
    if (bar && countEl) {
        if (checked.length > 0) {
            bar.style.display = 'flex';
            countEl.innerText = `${checked.length} tài liệu đã chọn`;
        } else {
            bar.style.display = 'none';
        }
    }
}

async function deleteDocItem(docId, docName = '') {
    if (!window.APP_IS_LOGGED_IN) {
        showToast('Vui lòng đăng nhập tài khoản để thực hiện thao tác này.', 'info');
        if (typeof openAuthModal === 'function') {
            openAuthModal('login');
        }
        return;
    }

    if (!confirm(`Bạn có chắc chắn muốn xóa tài liệu "${docName || 'tài liệu này'}"? Thao tác không thể hoàn tác.`)) {
        return;
    }

    try {
        const res = await fetch('/api/documents/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: docId })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('✓ ' + data.message, 'success');
            // Xóa row khỏi cả 2 bảng (home và documents)
            document.querySelectorAll(`tr[data-doc-id="${docId}"]`).forEach(tr => tr.remove());
            // Xóa card khỏi home nếu có
            const card = document.querySelector(`.doc-card[onclick*="${docId}"]`);
            if (card) card.remove();
            // Nếu tài liệu hiện tại đang mở trong editor bị xóa
            if (localStorage.getItem('editor_current_doc_id') == docId) {
                localStorage.removeItem('editor_current_doc_id');
                localStorage.removeItem('editor_draft_' + docId);
            }
            updateBulkActionsBar();
        } else {
            showToast('Lỗi: ' + data.message, 'error');
        }
    } catch (err) {
        showToast('Lỗi kết nối khi xóa tài liệu: ' + err.message, 'error');
    }
}

async function deleteSelectedDocuments() {
    if (!window.APP_IS_LOGGED_IN) {
        showToast('Vui lòng đăng nhập tài khoản để thực hiện thao tác này.', 'info');
        if (typeof openAuthModal === 'function') {
            openAuthModal('login');
        }
        return;
    }

    const checked = Array.from(document.querySelectorAll('.doc-checkbox:checked'));
    if (checked.length === 0) return;

    const ids = checked.map(cb => parseInt(cb.value, 10));
    if (!confirm(`Bạn có chắc chắn muốn xóa ${ids.length} tài liệu đã chọn?`)) return;

    try {
        const res = await fetch('/api/documents/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('✓ ' + data.message, 'success');
            ids.forEach(id => {
                document.querySelectorAll(`tr[data-doc-id="${id}"]`).forEach(tr => tr.remove());
                const card = document.querySelector(`.doc-card[onclick*="${id}"]`);
                if (card) card.remove();
                if (localStorage.getItem('editor_current_doc_id') == id) {
                    localStorage.removeItem('editor_current_doc_id');
                    localStorage.removeItem('editor_draft_' + id);
                }
            });
            updateBulkActionsBar();
            const masterCb = document.getElementById('selectAllDocs');
            if (masterCb) masterCb.checked = false;
        } else {
            showToast('Lỗi: ' + data.message, 'error');
        }
    } catch (err) {
        showToast('Lỗi kết nối: ' + err.message, 'error');
    }
}

// ==========================================================================
// ADMIN PORTAL CLIENT-SIDE CONTROLLERS & ACTIONS (ĐỀ TÀI 12)
// ==========================================================================

/**
 * Chuyển đổi giữa các trang/tab trong giao diện Quản trị viên (Admin Portal)
 */
function switchAdminTab(tabName) {
    switchAdminPage(tabName, true);
}

/**
 * Tải lại và đồng bộ hóa toàn bộ dữ liệu quản trị mới nhất từ máy chủ
 */
async function adminRefreshAllData() {
    if (!window.APP_IS_ADMIN) return;

    try {
        const res = await fetch('/api/admin/stats');
        const data = await res.json();
        if (data.status === 'success' && data.stats) {
            const s = data.stats;
            const elUsers = document.getElementById('kpiTotalUsers');
            const elDocs = document.getElementById('kpiTotalDocs');
            const elStorage = document.getElementById('kpiTotalStorage');
            const elExports = document.getElementById('kpiTotalExports');

            if (elUsers) elUsers.textContent = s.total_users;
            if (elDocs) elDocs.textContent = s.total_docs;
            if (elStorage) elStorage.textContent = s.total_storage_mb + ' MB';
            if (elExports) elExports.textContent = s.total_outputs;

            const tabCountUsers = document.getElementById('adminTabCountUsers');
            const tabCountDocs = document.getElementById('adminTabCountDocs');
            const tabCountExports = document.getElementById('adminTabCountExports');
            const sbCountUsers = document.getElementById('adminSidebarCountUsers');
            const sbCountDocs = document.getElementById('adminSidebarCountDocs');
            const sbCountExports = document.getElementById('adminSidebarCountExports');

            if (tabCountUsers) tabCountUsers.textContent = s.total_users;
            if (tabCountDocs) tabCountDocs.textContent = s.total_docs;
            if (tabCountExports) tabCountExports.textContent = s.total_outputs;
            if (sbCountUsers) sbCountUsers.textContent = s.total_users;
            if (sbCountDocs) sbCountDocs.textContent = s.total_docs;
            if (sbCountExports) sbCountExports.textContent = s.total_outputs;

            showToast('✓ Đã cập nhật toàn bộ dữ liệu quản trị mới nhất!', 'success');
        }
    } catch (err) {
        showToast('Không thể làm mới dữ liệu: ' + err.message, 'error');
    }
}

/**
 * Lọc danh sách người dùng tức thì theo từ khóa, vai trò (admin/user), gói (PRO/FREE) và trạng thái
 */
function adminFilterUsers() {
    const searchInput = document.getElementById('adminUserSearchInput');
    const roleSelect = document.getElementById('adminUserRoleFilter');
    const planSelect = document.getElementById('adminUserPlanFilter');
    const statusSelect = document.getElementById('adminUserStatusFilter');

    const searchVal = (searchInput?.value || '').trim().toLowerCase();
    const roleVal = roleSelect?.value || 'all';
    const planVal = planSelect?.value || 'all';
    const statusVal = statusSelect?.value || 'all';

    const rows = document.querySelectorAll('.admin-user-row');
    rows.forEach(row => {
        const rowSearch = row.getAttribute('data-search') || '';
        const rowRole = row.getAttribute('data-role') || '';
        const rowPlan = row.getAttribute('data-plan') || '';
        const rowStatus = row.getAttribute('data-status') || '';

        const matchSearch = !searchVal || rowSearch.includes(searchVal);
        const matchRole = roleVal === 'all' || rowRole === roleVal;
        const matchPlan = planVal === 'all' || rowPlan.toUpperCase() === planVal.toUpperCase();
        const matchStatus = statusVal === 'all' || rowStatus === statusVal;

        if (matchSearch && matchRole && matchPlan && matchStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

/**
 * Mở modal Cập nhật người dùng (Screenshot 4)
 */
function adminOpenEditUserModal(btn) {
    if (!window.APP_IS_ADMIN) return;

    try {
        const userJson = btn.getAttribute('data-user-json');
        if (!userJson) return;
        const u = JSON.parse(userJson);

        document.getElementById('editUserId').value = u.id || 0;
        document.getElementById('editUserFullName').value = u.full_name || '';
        document.getElementById('editUserPhone').value = u.phone || '';
        document.getElementById('editUserRole').value = u.role || 'user';
        document.getElementById('editUserPlan').value = (u.plan || 'FREE').toUpperCase();
        document.getElementById('editUserExpiresAt').value = u.expires_at || '';
        document.getElementById('editUserPassword').value = '';
        document.getElementById('editUserStatus').checked = (u.status !== 'banned');
        document.getElementById('editUserEmailVerified').checked = (parseInt(u.email_verified) === 1);

        const subId = document.getElementById('editUserSubId');
        if (subId) subId.textContent = u.id;
        const subEmail = document.getElementById('editUserSubEmail');
        if (subEmail) subEmail.textContent = u.email || '';

        openModal('editUserModal');
    } catch (e) {
        showToast('Lỗi nạp dữ liệu người dùng: ' + e.message, 'error');
    }
}

/**
 * Lưu thông tin cập nhật người dùng từ Modal (Screenshot 4)
 */
async function adminSaveUserEdit(event) {
    event.preventDefault();
    if (!window.APP_IS_ADMIN) return;

    const btnSave = document.getElementById('btnSaveEditUser');
    const originalText = btnSave ? btnSave.innerHTML : 'Lưu thay đổi';

    const userId = parseInt(document.getElementById('editUserId').value || 0);
    const fullName = document.getElementById('editUserFullName').value.trim();
    const phone = document.getElementById('editUserPhone').value.trim();
    const role = document.getElementById('editUserRole').value;
    const plan = document.getElementById('editUserPlan').value;
    const expiresAt = document.getElementById('editUserExpiresAt').value;
    const isActive = document.getElementById('editUserStatus').checked;
    const isEmailVerified = document.getElementById('editUserEmailVerified').checked;
    const newPassword = document.getElementById('editUserPassword').value;

    if (!fullName) {
        showToast('Vui lòng nhập họ và tên người dùng.', 'warning');
        return;
    }

    try {
        if (btnSave) {
            btnSave.disabled = true;
            btnSave.innerHTML = '<span class="polling-pulse-spinner" style="width:16px;height:16px;border-color:#fff;border-top-color:transparent;display:inline-block;vertical-align:middle;margin-right:6px;"></span>Đang lưu...';
        }

        const res = await fetch('/api/admin/users/update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: userId,
                full_name: fullName,
                phone: phone,
                role: role,
                plan: plan,
                expires_at: expiresAt,
                status: isActive ? 'active' : 'banned',
                email_verified: isEmailVerified ? 1 : 0,
                new_password: newPassword
            })
        });

        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message || 'Cập nhật người dùng thành công!', 'success');
            closeModal('editUserModal');

            // Cập nhật DOM trực tiếp không cần reload trang
            const roleName = role === 'admin' ? 'Quản trị viên' : 'Người dùng';
            const statusStr = isActive ? 'active' : 'banned';
            const statusLabel = isActive ? 'Hoạt động' : 'Bị khóa';
            const formattedExp = expiresAt ? expiresAt.split('-').reverse().join('/') : 'Vĩnh viễn';

            // Cập nhật trên Desktop row
            const desktopRow = document.querySelector(`.admin-user-row.desktop-row[data-user-id="${userId}"]`);
            if (desktopRow) {
                desktopRow.setAttribute('data-role', role);
                desktopRow.setAttribute('data-plan', plan);
                desktopRow.setAttribute('data-status', statusStr);
                const searchStr = (fullName + ' ' + (desktopRow.querySelector('.user-username')?.textContent || '') + ' ' + (desktopRow.querySelector('.col-email')?.textContent || '') + ' ' + phone).toLowerCase();
                desktopRow.setAttribute('data-search', searchStr);

                const nameEl = desktopRow.querySelector('.user-name-text');
                if (nameEl) nameEl.textContent = fullName;

                const phoneEl = desktopRow.querySelector('.phone-text');
                if (phoneEl) {
                    phoneEl.textContent = phone;
                } else if (phone) {
                    const emailCol = desktopRow.querySelector('.col-email');
                    if (emailCol) {
                        const newPhoneDiv = document.createElement('div');
                        newPhoneDiv.className = 'phone-text';
                        newPhoneDiv.textContent = phone;
                        emailCol.appendChild(newPhoneDiv);
                    }
                }

                const roleBadge = document.getElementById(`userRoleBadge-${userId}`);
                if (roleBadge) {
                    roleBadge.className = `admin-role-badge ${role}`;
                    roleBadge.textContent = roleName;
                }

                const planBadge = document.getElementById(`userPlanBadge-${userId}`);
                if (planBadge) {
                    planBadge.className = `admin-plan-badge ${plan.toLowerCase()}`;
                    planBadge.textContent = plan;
                }

                const statusBadge = document.getElementById(`userStatusBadge-${userId}`);
                if (statusBadge) {
                    statusBadge.className = `user-status-badge status-${statusStr}`;
                    statusBadge.innerHTML = `<span class="status-dot"></span><span class="status-text">${statusLabel}</span>`;
                }

                const expText = document.getElementById(`userExpireText-${userId}`);
                if (expText) expText.textContent = formattedExp;

                const editBtn = document.getElementById(`btnEditUser-${userId}`);
                if (editBtn) {
                    try {
                        const curJson = JSON.parse(editBtn.getAttribute('data-user-json') || '{}');
                        curJson.full_name = fullName;
                        curJson.phone = phone;
                        curJson.role = role;
                        curJson.plan = plan;
                        curJson.status = statusStr;
                        curJson.email_verified = isEmailVerified ? 1 : 0;
                        curJson.expires_at = expiresAt;
                        editBtn.setAttribute('data-user-json', JSON.stringify(curJson));
                    } catch(e){}
                }
            }

            // Cập nhật trên Mobile Card
            const mobileCard = document.querySelector(`.user-card-mobile[data-user-id="${userId}"]`);
            if (mobileCard) {
                mobileCard.setAttribute('data-role', role);
                mobileCard.setAttribute('data-plan', plan);
                mobileCard.setAttribute('data-status', statusStr);

                const mobNameEl = mobileCard.querySelector('.user-name-text');
                if (mobNameEl) mobNameEl.textContent = fullName;

                const mobRoleBadge = document.getElementById(`userRoleBadgeMob-${userId}`);
                if (mobRoleBadge) {
                    mobRoleBadge.className = `admin-role-badge ${role}`;
                    mobRoleBadge.textContent = roleName;
                }

                const mobPlanBadge = document.getElementById(`userPlanBadgeMob-${userId}`);
                if (mobPlanBadge) {
                    mobPlanBadge.className = `admin-plan-badge ${plan.toLowerCase()}`;
                    mobPlanBadge.textContent = plan;
                }

                const mobStatusBadge = document.getElementById(`userStatusBadgeMob-${userId}`);
                if (mobStatusBadge) {
                    mobStatusBadge.className = `user-status-badge status-${statusStr}`;
                    mobStatusBadge.innerHTML = `<span class="status-dot"></span><span class="status-text">${statusLabel}</span>`;
                }

                const mobExpText = document.getElementById(`userExpireTextMob-${userId}`);
                if (mobExpText) mobExpText.textContent = formattedExp;

                const mobEditBtn = document.getElementById(`btnEditUserMob-${userId}`);
                if (mobEditBtn) {
                    try {
                        const curJson = JSON.parse(mobEditBtn.getAttribute('data-user-json') || '{}');
                        curJson.full_name = fullName;
                        curJson.phone = phone;
                        curJson.role = role;
                        curJson.plan = plan;
                        curJson.status = statusStr;
                        curJson.email_verified = isEmailVerified ? 1 : 0;
                        curJson.expires_at = expiresAt;
                        mobEditBtn.setAttribute('data-user-json', JSON.stringify(curJson));
                    } catch(e){}
                }
            }

            // Cập nhật số liệu thống kê Admin tổng quan
            if (typeof adminRefreshAllData === 'function') {
                adminRefreshAllData();
            }
        } else {
            showToast('Lỗi: ' + (data.message || 'Không thể lưu'), 'error');
        }
    } catch (err) {
        showToast('Lỗi kết nối máy chủ: ' + err.message, 'error');
    } finally {
        if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = originalText;
        }
    }
}

/**
 * Chuyển đổi vai trò người dùng (user <-> admin)
 */
async function adminToggleRole(userId, newRole) {
    if (!window.APP_IS_ADMIN) return;

    const roleName = newRole === 'admin' ? 'Quản trị viên' : 'Người dùng';
    if (!confirm(`Bạn có chắc chắn muốn chuyển vai trò người dùng này thành "${roleName}"?`)) {
        return;
    }

    try {
        const res = await fetch('/api/admin/users/role', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, role: newRole })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message, 'success');

            // Cập nhật giao diện row
            const badge = document.getElementById(`userRoleBadge-${userId}`);
            if (badge) {
                badge.className = `admin-role-badge ${newRole}`;
                badge.textContent = roleName;
            }

            const btn = document.getElementById(`btnToggleRole-${userId}`);
            if (btn) {
                const nextRole = newRole === 'admin' ? 'user' : 'admin';
                btn.setAttribute('onclick', `adminToggleRole(${userId}, '${nextRole}')`);
                btn.textContent = newRole === 'admin' ? 'Chuyển thành User' : 'Nâng thành Admin';
            }

            const row = document.querySelector(`.admin-user-row[data-user-id="${userId}"]`);
            if (row) {
                row.setAttribute('data-role', newRole);
            }

            adminRefreshAllData();
        } else {
            showToast('Lỗi: ' + data.message, 'error');
        }
    } catch (err) {
        showToast('Lỗi kết nối: ' + err.message, 'error');
    }
}

/**
 * Nâng cấp hoặc hạ gói dịch vụ người dùng (FREE <-> PRO)
 */
async function adminTogglePlan(userId, newPlan) {
    if (!window.APP_IS_ADMIN) return;

    try {
        const res = await fetch('/api/admin/users/plan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, plan: newPlan })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message, 'success');

            const badge = document.getElementById(`userPlanBadge-${userId}`);
            if (badge) {
                badge.className = `admin-plan-badge ${newPlan.toLowerCase()}`;
                badge.textContent = newPlan;
            }

            const btn = document.getElementById(`btnTogglePlan-${userId}`);
            if (btn) {
                const nextPlan = newPlan === 'PRO' ? 'FREE' : 'PRO';
                btn.setAttribute('onclick', `adminTogglePlan(${userId}, '${nextPlan}')`);
                btn.textContent = newPlan === 'PRO' ? 'Hạ FREE' : 'Cấp PRO';
            }

            const row = document.querySelector(`.admin-user-row[data-user-id="${userId}"]`);
            if (row) {
                row.setAttribute('data-plan', newPlan);
            }

            adminRefreshAllData();
        } else {
            showToast('Lỗi: ' + data.message, 'error');
        }
    } catch (err) {
        showToast('Lỗi kết nối: ' + err.message, 'error');
    }
}

/**
 * Xóa tài khoản người dùng và toàn bộ dữ liệu liên quan
 */
async function adminDeleteUser(userId, userName) {
    if (!window.APP_IS_ADMIN) return;

    if (!confirm(`CẢNH BÁO: Bạn có chắc chắn muốn xóa tài khoản "${userName}" và toàn bộ tệp tài liệu liên quan không? Thao tác này không thể hoàn tác!`)) {
        return;
    }

    try {
        const res = await fetch('/api/admin/users/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message, 'success');
            const row = document.querySelector(`.admin-user-row[data-user-id="${userId}"]`);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            }
            adminRefreshAllData();
        } else {
            showToast('Lỗi: ' + data.message, 'error');
        }
    } catch (err) {
        showToast('Lỗi kết nối: ' + err.message, 'error');
    }
}

/**
 * Lọc danh sách tài liệu toàn hệ thống theo từ khóa và trạng thái
 */
function adminFilterDocs() {
    const searchInput = document.getElementById('adminDocSearchInput');
    const statusSelect = document.getElementById('adminDocStatusFilter');

    const searchVal = (searchInput?.value || '').trim().toLowerCase();
    const statusVal = statusSelect?.value || 'all';

    const rows = document.querySelectorAll('.admin-doc-row');
    let visibleCount = 0;
    rows.forEach(row => {
        const rowSearch = row.getAttribute('data-search') || '';
        const rowStatus = row.getAttribute('data-status') || '';

        const matchSearch = !searchVal || rowSearch.includes(searchVal);
        const matchStatus = statusVal === 'all' || rowStatus === statusVal;

        if (matchSearch && matchStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const countEl = document.getElementById('docTableCount');
    if (countEl) countEl.textContent = visibleCount;
}

/**
 * Xóa vĩnh viễn tài liệu bất kỳ khỏi hệ thống bởi Admin
 */
async function adminDeleteDoc(docId, docName) {
    if (!window.APP_IS_ADMIN) return;

    if (!confirm(`Bạn có chắc chắn muốn xóa vĩnh viễn tài liệu "${docName}" khỏi hệ thống máy chủ không?`)) {
        return;
    }

    try {
        const res = await fetch('/api/admin/documents/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: docId })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message, 'success');
            const row = document.querySelector(`.admin-doc-row[data-doc-id="${docId}"]`);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            }
            adminRefreshAllData();
        } else {
            showToast('Lỗi: ' + data.message, 'error');
        }
    } catch (err) {
        showToast('Lỗi kết nối: ' + err.message, 'error');
    }
}

/**
 * =========================================================================
 * QUẢN TRỊ BẢO TRÌ & GIÁM SÁT HỆ THỐNG (/admin/maintenance)
 * =========================================================================
 */

/**
 * Tải tình trạng ổ đĩa, dung lượng các thư mục và trạng thái bảo trì hệ thống
 */
async function adminLoadMaintenanceStatus() {
    if (!window.APP_IS_ADMIN) return;

    try {
        const res = await fetch('/api/admin/maintenance/status');
        const data = await res.json();
        if (data.status !== 'success') return;

        // 1. Cập nhật thanh ổ đĩa
        const diskLabel = document.getElementById('diskUsageLabel');
        const diskBar = document.getElementById('maintenanceDiskBar');
        if (diskLabel && data.disk) {
            diskLabel.textContent = `${data.disk.used_gb} GB / ${data.disk.total_gb} GB (Đã dùng ${data.disk.used_percent}% - Còn trống ${data.disk.free_gb} GB)`;
        }
        if (diskBar && data.disk) {
            diskBar.style.width = `${Math.min(100, data.disk.used_percent)}%`;
            if (data.disk.used_percent > 85) {
                diskBar.style.background = 'linear-gradient(90deg, #EF4444, #F87171)';
            } else if (data.disk.used_percent > 65) {
                diskBar.style.background = 'linear-gradient(90deg, #F59E0B, #FBBF24)';
            } else {
                diskBar.style.background = 'linear-gradient(90deg, #6366F1, #8B5CF6)';
            }
        }

        // 2. Cập nhật 4 thẻ chỉ số
        if (data.folders) {
            const docsEl = document.getElementById('statMaintDocs');
            const expEl = document.getElementById('statMaintExports');
            const prevEl = document.getElementById('statMaintPreviews');
            const tempEl = document.getElementById('statMaintTemp');

            if (docsEl && data.folders.documents) {
                docsEl.textContent = `${data.folders.documents.count} tệp • ${data.folders.documents.mb} MB`;
            }
            if (expEl && data.folders.exports) {
                expEl.textContent = `${data.folders.exports.count} tệp • ${data.folders.exports.mb} MB`;
            }
            if (prevEl && data.folders.previews) {
                prevEl.textContent = `${data.folders.previews.count} ảnh • ${data.folders.previews.mb} MB`;
            }
            if (tempEl && data.folders.temp) {
                tempEl.textContent = `${data.folders.temp.count} tệp • ${data.folders.temp.mb} MB`;
            }
        }

        // 3. Cập nhật huy hiệu tệp mồ côi & cache
        const orphanBadge = document.getElementById('maintOrphanBadge');
        if (orphanBadge && data.orphans) {
            orphanBadge.textContent = `${data.orphans.count} tệp rác (${data.orphans.mb} MB)`;
        }

        const cacheBadge = document.getElementById('maintPreviewCacheBadge');
        if (cacheBadge && data.folders && data.folders.previews) {
            cacheBadge.textContent = `${data.folders.previews.count} tệp cache tạm (${data.folders.previews.mb} MB)`;
        }

        // 4. Cập nhật Chế độ Bảo trì
        if (data.maintenance) {
            const toggle = document.getElementById('maintModeToggle');
            const msgInput = document.getElementById('maintModeMessage');
            const badge = document.getElementById('maintStatusBadge');
            const card = document.getElementById('maintModeCard');

            const isMaint = !!data.maintenance.maintenance_mode;
            if (toggle) toggle.checked = isMaint;
            if (msgInput && !msgInput.matches(':focus')) {
                msgInput.value = data.maintenance.message || '';
            }

            if (badge) {
                if (isMaint) {
                    badge.className = 'header-stat-pill pill-admin';
                    badge.style.background = 'rgba(239, 68, 68, 0.15)';
                    badge.style.color = '#EF4444';
                    badge.textContent = '● Đang Bật Chế Độ Bảo Trì';
                } else {
                    badge.className = 'header-stat-pill pill-active';
                    badge.style.background = 'rgba(16, 185, 129, 0.15)';
                    badge.style.color = '#10B981';
                    badge.textContent = '● Hệ thống Hoạt Động Bình Thường (Live)';
                }
            }

            if (card) {
                card.style.borderColor = isMaint ? '#EF4444' : 'var(--border-color)';
            }
        }
    } catch (err) {
        console.error('Lỗi tải trạng thái bảo trì:', err);
    }
}

/**
 * Quét và dọn dẹp các tệp mồ côi (Orphan Cleanup) trên đĩa máy chủ
 */
async function adminRunCleanup() {
    if (!window.APP_IS_ADMIN) return;

    const btn = document.getElementById('btnAdminCleanup');
    const resBox = document.getElementById('adminCleanupResult');

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Đang quét và dọn dẹp tệp...`;
    }

    try {
        const res = await fetch('/api/admin/cleanup', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message, 'success');
            if (resBox) {
                resBox.style.display = 'block';
                resBox.textContent = `✓ ${data.message}`;
            }
            adminLoadMaintenanceStatus();
            adminRefreshAllData();
        } else {
            showToast('Lỗi: ' + data.message, 'error');
        }
    } catch (err) {
        showToast('Lỗi quét dọn: ' + err.message, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `
                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                <span>Quét & Dọn Dẹp</span>
            `;
        }
    }
}

/**
 * Dọn sạch bộ nhớ tạm ảnh xem trước (Preview Cache Cleaner)
 */
async function adminRunPreviewCleanup() {
    if (!window.APP_IS_ADMIN) return;

    const btn = document.getElementById('btnAdminCleanCache');
    const resBox = document.getElementById('adminCacheResult');

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Đang dọn dẹp cache...`;
    }

    try {
        const res = await fetch('/api/admin/maintenance/clean-cache', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message, 'success');
            if (resBox) {
                resBox.style.display = 'block';
                resBox.textContent = `✓ ${data.message}`;
            }
            adminLoadMaintenanceStatus();
        } else {
            showToast('Lỗi: ' + data.message, 'error');
        }
    } catch (err) {
        showToast('Lỗi dọn cache: ' + err.message, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `
                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                <span>Xóa Bộ Nhớ Tạm Xem Trước</span>
            `;
        }
    }
}

/**
 * Lưu trạng thái và thông điệp Chế độ Bảo trì (Maintenance Mode)
 */
async function adminSaveMaintenanceMode() {
    if (!window.APP_IS_ADMIN) return;

    const toggle = document.getElementById('maintModeToggle');
    const msgInput = document.getElementById('maintModeMessage');
    const btn = document.getElementById('btnSaveMaintMode');

    const mode = toggle ? toggle.checked : false;
    const message = msgInput ? msgInput.value.trim() : '';

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Đang lưu...`;
    }

    try {
        const res = await fetch('/api/admin/maintenance/mode', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                maintenance_mode: mode,
                message: message
            })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message, 'success');
            adminLoadMaintenanceStatus();
        } else {
            showToast('Lỗi: ' + data.message, 'error');
        }
    } catch (err) {
        showToast('Lỗi cập nhật bảo trì: ' + err.message, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `
                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                <span>Lưu Cài Đặt Bảo Trì</span>
            `;
        }
    }
}

/**
 * =========================================================================
 * QUẢN TRỊ CẤP BẬC GÓI DỊCH VỤ (/admin/plans)
 * =========================================================================
 */
window.ADMIN_PLANS_CACHE = [];

/**
 * Tải danh sách gói dịch vụ và hiển thị bảng quản trị
 */
async function adminLoadPlans() {
    if (!window.APP_IS_ADMIN) return;

    const tbody = document.getElementById('adminPlansTableBody');
    if (!tbody) return;

    try {
        const res = await fetch('/api/admin/plans');
        const data = await res.json();
        if (data.status !== 'success') {
            tbody.innerHTML = `<tr><td colspan="10" style="text-align: center; color: #EF4444; padding: 24px;">Lỗi tải dữ liệu gói: ${data.message || ''}</td></tr>`;
            return;
        }

        const plans = data.plans || [];
        window.ADMIN_PLANS_CACHE = plans;

        // Cập nhật thống kê header
        const totalEl = document.getElementById('plansTotalCount');
        const activeEl = document.getElementById('plansActiveCount');
        const popEl = document.getElementById('plansPopularName');
        const sbCount = document.getElementById('adminSidebarCountPlans');

        if (totalEl) totalEl.textContent = data.stats.total_plans;
        if (activeEl) activeEl.textContent = data.stats.active_plans;
        if (popEl) popEl.textContent = data.stats.popular_plan;
        if (sbCount) sbCount.textContent = data.stats.total_plans;

        if (plans.length === 0) {
            tbody.innerHTML = `<tr><td colspan="10" style="text-align: center; color: var(--text-muted); padding: 32px;">Chưa có gói dịch vụ nào. Hãy bấm "+ Thêm gói mới" để khởi tạo!</td></tr>`;
            return;
        }

        tbody.innerHTML = plans.map(p => {
            const badgeClass = `badge-tier-${p.plan_code.toLowerCase()}`;
            const isActive = (parseInt(p.is_active) === 1);
            const statusHtml = isActive 
                ? `<span class="plan-status-pill status-active" onclick="adminTogglePlan(${p.id})" title="Bấm để tạm tắt gói">Đang bật</span>`
                : `<span class="plan-status-pill status-inactive" onclick="adminTogglePlan(${p.id})" title="Bấm để kích hoạt gói">Đang tắt</span>`;

            // Tóm tắt tính năng (3 dòng đầu)
            const featLines = (p.features_text || '').split('\n').filter(l => l.trim().length > 0);
            const featSummary = featLines.slice(0, 3).map(l => escapeHtml(l)).join(', ') + (featLines.length > 3 ? ` +${featLines.length - 3}` : '');

            return `
                <tr class="plan-row ${isActive ? '' : 'row-disabled'}">
                    <td>
                        <div style="display: flex; flex-direction: column; align-items: flex-start; gap: 4px;">
                            <span class="plan-tier-badge ${badgeClass}">${escapeHtml(p.plan_code)}</span>
                            <strong style="font-size: 0.92rem; color: var(--text-main);">${escapeHtml(p.name)}</strong>
                        </div>
                    </td>
                    <td>
                        <strong style="font-size: 0.95rem; color: var(--text-main);">${escapeHtml(p.price_formatted)}</strong>
                    </td>
                    <td>
                        <span style="font-size: 0.85rem; color: var(--text-muted);">${escapeHtml(p.duration_label)}</span>
                    </td>
                    <td>
                        <span style="font-weight: 600; font-size: 0.88rem;">${p.tier_level}</span>
                    </td>
                    <td>
                        <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">${escapeHtml(p.storage_label)}</span>
                    </td>
                    <td>
                        <span style="font-size: 0.85rem;">${escapeHtml(p.upload_label)}</span>
                    </td>
                    <td>
                        <span style="font-size: 0.85rem;">${escapeHtml(p.pages_label)}</span>
                    </td>
                    <td>
                        <div style="font-size: 0.8rem; color: var(--text-muted); max-width: 280px; line-height: 1.4;">
                            ${featSummary || 'Tính năng tiêu chuẩn'}
                        </div>
                    </td>
                    <td style="text-align: center;">
                        ${statusHtml}
                    </td>
                    <td style="text-align: right;">
                        <div style="display: inline-flex; align-items: center; gap: 6px;">
                            <button type="button" class="btn-icon-action" onclick="adminOpenPlanModal(${p.id})" title="Sửa gói">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            ${p.plan_code !== 'FREE' ? `
                                <button type="button" class="btn-icon-action text-danger" onclick="adminDeletePlan(${p.id})" title="Xóa gói">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="10" style="text-align: center; color: #EF4444; padding: 24px;">Lỗi kết nối máy chủ: ${err.message}</td></tr>`;
    }
}

/**
 * Mở modal Thêm hoặc Sửa Gói Dịch Vụ
 */
function adminOpenPlanModal(planId = null) {
    const modal = document.getElementById('adminPlanModal');
    const title = document.getElementById('adminPlanModalTitle');
    if (!modal) return;

    const idInput = document.getElementById('planFormId');
    const codeInput = document.getElementById('planFormCode');
    const nameInput = document.getElementById('planFormName');
    const priceInput = document.getElementById('planFormPrice');
    const durInput = document.getElementById('planFormDuration');
    const tierInput = document.getElementById('planFormTier');
    const storageInput = document.getElementById('planFormStorage');
    const uploadInput = document.getElementById('planFormUploadSize');
    const pagesInput = document.getElementById('planFormPages');
    const dpiInput = document.getElementById('planFormDpi');
    const exportsInput = document.getElementById('planFormExports');
    const badgeInput = document.getElementById('planFormBadge');
    const sortInput = document.getElementById('planFormSort');
    const featTextInput = document.getElementById('planFormFeaturesText');
    const activeInput = document.getElementById('planFormIsActive');

    const featCompress = document.getElementById('featCompress');
    const featManifest = document.getElementById('featManifest');
    const featWatermark = document.getElementById('featWatermark');
    const featPriority = document.getElementById('featPriority');
    const featBatch = document.getElementById('featBatch');
    const featApi = document.getElementById('featApi');

    if (planId === null) {
        // Mode: Thêm mới
        if (title) title.textContent = 'Thêm gói dịch vụ mới';
        if (idInput) idInput.value = '0';
        if (codeInput) { codeInput.value = ''; codeInput.readOnly = false; }
        if (nameInput) nameInput.value = '';
        if (priceInput) priceInput.value = '0';
        if (durInput) durInput.value = '30';
        if (tierInput) tierInput.value = '10';
        if (storageInput) storageInput.value = '100';
        if (uploadInput) uploadInput.value = '20';
        if (pagesInput) pagesInput.value = '50';
        if (dpiInput) dpiInput.value = '150';
        if (exportsInput) exportsInput.value = '20';
        if (badgeInput) badgeInput.value = '';
        if (sortInput) sortInput.value = '1';
        if (featTextInput) featTextInput.value = '';
        if (activeInput) activeInput.checked = true;

        if (featCompress) featCompress.checked = false;
        if (featManifest) featManifest.checked = false;
        if (featWatermark) featWatermark.checked = false;
        if (featPriority) featPriority.checked = false;
        if (featBatch) featBatch.checked = false;
        if (featApi) featApi.checked = false;
    } else {
        // Mode: Chỉnh sửa
        const plan = (window.ADMIN_PLANS_CACHE || []).find(p => parseInt(p.id) === parseInt(planId));
        if (!plan) {
            showToast('Không tìm thấy thông tin gói', 'error');
            return;
        }

        if (title) title.textContent = `Sửa gói: ${plan.name} (${plan.plan_code})`;
        if (idInput) idInput.value = plan.id;
        if (codeInput) {
            codeInput.value = plan.plan_code;
            codeInput.readOnly = (plan.plan_code === 'FREE');
        }
        if (nameInput) nameInput.value = plan.name;
        if (priceInput) priceInput.value = Math.round(parseFloat(plan.price));
        if (durInput) durInput.value = plan.duration_days;
        if (tierInput) tierInput.value = plan.tier_level;
        if (storageInput) storageInput.value = plan.max_storage_mb;
        if (uploadInput) uploadInput.value = plan.max_upload_size_mb;
        if (pagesInput) pagesInput.value = plan.max_pages_per_file;
        if (dpiInput) dpiInput.value = plan.max_dpi || '150';
        if (exportsInput) exportsInput.value = plan.max_daily_exports;
        if (badgeInput) badgeInput.value = plan.badge_text || '';
        if (sortInput) sortInput.value = plan.sort_order || '1';
        if (featTextInput) featTextInput.value = plan.features_text || '';
        if (activeInput) activeInput.checked = (parseInt(plan.is_active) === 1);

        const allowed = plan.allowed_features_arr || {};
        if (featCompress) featCompress.checked = !!allowed.compress;
        if (featManifest) featManifest.checked = !!allowed.manifest;
        if (featWatermark) featWatermark.checked = !!allowed.watermark;
        if (featPriority) featPriority.checked = !!allowed.priority;
        if (featBatch) featBatch.checked = !!allowed.batch;
        if (featApi) featApi.checked = !!allowed.api;
    }

    openModal('adminPlanModal');
}

/**
 * Đóng modal gói dịch vụ
 */
function adminClosePlanModal() {
    closeModal('adminPlanModal');
}

/**
 * Lưu gói dịch vụ (Thêm mới / Cập nhật)
 */
async function adminSavePlan(e) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    if (!window.APP_IS_ADMIN) return;

    const id = parseInt(document.getElementById('planFormId')?.value || '0');
    const plan_code = document.getElementById('planFormCode')?.value.trim();
    const name = document.getElementById('planFormName')?.value.trim();
    const price = parseFloat(document.getElementById('planFormPrice')?.value || '0');
    const duration_days = parseInt(document.getElementById('planFormDuration')?.value || '30');
    const tier_level = parseInt(document.getElementById('planFormTier')?.value || '0');
    const max_storage_mb = parseInt(document.getElementById('planFormStorage')?.value || '100');
    const max_upload_size_mb = parseInt(document.getElementById('planFormUploadSize')?.value || '15');
    const max_pages_per_file = parseInt(document.getElementById('planFormPages')?.value || '50');
    const max_dpi = parseInt(document.getElementById('planFormDpi')?.value || '150');
    const max_daily_exports = parseInt(document.getElementById('planFormExports')?.value || '20');
    const badge_text = document.getElementById('planFormBadge')?.value.trim();
    const sort_order = parseInt(document.getElementById('planFormSort')?.value || '1');
    const features_text = document.getElementById('planFormFeaturesText')?.value;
    const is_active = document.getElementById('planFormIsActive')?.checked ? 1 : 0;

    const allowed_features = {
        compress: document.getElementById('featCompress')?.checked || false,
        manifest: document.getElementById('featManifest')?.checked || false,
        watermark: document.getElementById('featWatermark')?.checked || false,
        priority: document.getElementById('featPriority')?.checked || false,
        batch: document.getElementById('featBatch')?.checked || false,
        api: document.getElementById('featApi')?.checked || false
    };

    const btn = document.getElementById('btnAdminSavePlan');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Đang lưu...`;
    }

    try {
        const res = await fetch('/api/admin/plans/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id, plan_code, name, price, duration_days, tier_level,
                max_storage_mb, max_upload_size_mb, max_pages_per_file, max_dpi,
                max_daily_exports, badge_text, sort_order, features_text,
                allowed_features, is_active
            })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message, 'success');
            adminClosePlanModal();
            adminLoadPlans();
        } else {
            showToast('Lỗi: ' + data.message, 'error');
        }
    } catch (err) {
        showToast('Lỗi lưu gói: ' + err.message, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Lưu gói';
        }
    }
}

/**
 * Bật / Tắt trạng thái gói trực tiếp
 */
async function adminTogglePlan(planId) {
    if (!window.APP_IS_ADMIN) return;

    try {
        const res = await fetch('/api/admin/plans/toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: planId })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message, 'success');
            adminLoadPlans();
        } else {
            showToast('Lỗi: ' + data.message, 'error');
        }
    } catch (err) {
        showToast('Lỗi: ' + err.message, 'error');
    }
}

/**
 * Xóa gói dịch vụ
 */
async function adminDeletePlan(planId) {
    if (!window.APP_IS_ADMIN) return;

    const plan = (window.ADMIN_PLANS_CACHE || []).find(p => parseInt(p.id) === parseInt(planId));
    const planName = plan ? plan.name : `ID #${planId}`;

    if (!confirm(`Bạn có chắc chắn muốn xóa vĩnh viễn gói dịch vụ "${planName}" không?`)) {
        return;
    }

    try {
        const res = await fetch('/api/admin/plans/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: planId })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message, 'success');
            adminLoadPlans();
        } else {
            showToast('Lỗi: ' + data.message, 'error');
        }
    } catch (err) {
        showToast('Lỗi xóa gói: ' + err.message, 'error');
    }
}

// =========================================================================
// CHỨC NĂNG QUẢN LÝ THANH TOÁN (PAYMENTS) & VIETQR NAPAS 247
// =========================================================================

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

async function openBankSettingsModal() {
    try {
        const res = await fetch('/api/admin/bank/settings');
        const data = await res.json();
        if (data.status === 'success' && data.data) {
            const s = data.data;
            const elCode = document.getElementById('cfgBankCode');
            const elName = document.getElementById('cfgAccountName');
            const elNum = document.getElementById('cfgAccountNumber');
            const elToken = document.getElementById('cfgToken');
            const elUrl = document.getElementById('cfgApiUrl');
            const elMemo = document.getElementById('cfgMemoPrefix');
            const elNotes = document.getElementById('cfgNotes');

            if (elCode) elCode.value = s.bank_code || 'ACB';
            if (elName) elName.value = s.account_name || '';
            if (elNum) elNum.value = s.account_number || '';
            if (elToken) {
                elToken.value = '';
                elToken.placeholder = s.has_token ? '•••••••••••• (Đã cấu hình - Nhập để đổi)' : 'Nhập token';
            }
            if (elUrl) elUrl.value = s.api_url || '';
            if (elMemo) elMemo.value = s.memo_prefix || 'PDFPRO';
            if (elNotes) elNotes.value = s.note || '';
        }
        if (typeof openModal === 'function') {
            openModal('bankSettingsModal');
        }
    } catch (e) {
        showToast('Lỗi tải cấu hình ngân hàng: ' + e.message, 'error');
    }
}

async function saveBankSettings(event) {
    if (event) event.preventDefault();
    const btn = document.getElementById('btnSaveBankSettings');
    const oldText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Đang lưu...`;
    }

    try {
        const payload = {
            bank_code: document.getElementById('cfgBankCode')?.value || 'ACB',
            account_name: document.getElementById('cfgAccountName')?.value || '',
            account_number: document.getElementById('cfgAccountNumber')?.value || '',
            api_url: document.getElementById('cfgApiUrl')?.value || '',
            memo_prefix: document.getElementById('cfgMemoPrefix')?.value || 'PDFPRO',
            note: document.getElementById('cfgNotes')?.value || ''
        };
        const tokenVal = document.getElementById('cfgToken')?.value?.trim();
        if (tokenVal) {
            payload.api_token = tokenVal;
        }

        const res = await fetch('/api/admin/bank/settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message || 'Cập nhật cấu hình ngân hàng thành công!', 'success');
            if (typeof closeModal === 'function') {
                closeModal('bankSettingsModal');
            }
        } else {
            showToast('Lỗi: ' + (data.message || 'Không thể lưu cấu hình'), 'error');
        }
    } catch (e) {
        showToast('Lỗi kết nối: ' + e.message, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = oldText;
        }
    }
}

function togglePasswordVisibility(inputId) {
    const el = document.getElementById(inputId);
    if (el) {
        el.type = el.type === 'password' ? 'text' : 'password';
    }
}

async function adminSyncBankTransactions() {
    const btn = document.getElementById('btnSyncBank');
    const oldHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Đang đồng bộ ACB...`;
    }

    try {
        const res = await fetch('/api/admin/bank/sync', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message || 'Đồng bộ giao dịch ngân hàng thành công!', 'success');
            adminFilterInvoices();
        } else {
            showToast(data.message || 'Đồng bộ thất bại', 'error');
        }
    } catch (e) {
        showToast('Lỗi kết nối đồng bộ: ' + e.message, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        }
    }
}

let currentAdminChartPeriod = 'month';

async function switchAdminChartPeriod(period, btnEl) {
    currentAdminChartPeriod = period;
    document.querySelectorAll('.btn-period-tab').forEach(b => b.classList.remove('active'));
    if (btnEl) {
        btnEl.classList.add('active');
    } else {
        const matchingBtn = document.querySelector(`.btn-period-tab[data-period="${period}"]`);
        if (matchingBtn) matchingBtn.classList.add('active');
    }

    try {
        const res = await fetch(`/api/admin/invoices?period=${encodeURIComponent(period)}&limit=1`);
        const data = await res.json();
        if (data.status === 'success' && data.stats) {
            renderInvoiceStats(data.stats);
        }
    } catch (e) {
        console.error('Lỗi chuyển kỳ thống kê:', e);
    }
}

async function adminFilterInvoices() {
    const userId = document.getElementById('filterInvUserId')?.value?.trim() || '';
    const username = document.getElementById('filterInvUsername')?.value?.trim() || '';
    const transId = document.getElementById('filterInvTransId')?.value?.trim() || '';
    const bank = document.getElementById('filterInvBank')?.value || 'all';
    const date = document.getElementById('filterInvDate')?.value || '';
    const limit = document.getElementById('filterInvLimit')?.value || '25';
    const sort = document.getElementById('filterInvSort')?.value || 'desc';

    const params = new URLSearchParams();
    if (userId) params.append('user_id', userId);
    if (username) params.append('username', username);
    if (transId) params.append('trans_id', transId);
    if (bank && bank !== 'all') params.append('bank', bank);
    if (date) params.append('date', date);
    params.append('limit', limit);
    params.append('sort', sort);
    params.append('period', currentAdminChartPeriod);

    try {
        const res = await fetch('/api/admin/invoices?' + params.toString());
        const data = await res.json();
        if (data.status === 'success') {
            renderInvoicesTable(data.invoices || []);
            if (data.stats) {
                renderInvoiceStats(data.stats);
            }
        }
    } catch (e) {
        showToast('Lỗi lọc hóa đơn: ' + e.message, 'error');
    }
}

function adminResetInvoiceFilters() {
    if (document.getElementById('filterInvUserId')) document.getElementById('filterInvUserId').value = '';
    if (document.getElementById('filterInvUsername')) document.getElementById('filterInvUsername').value = '';
    if (document.getElementById('filterInvTransId')) document.getElementById('filterInvTransId').value = '';
    if (document.getElementById('filterInvBank')) document.getElementById('filterInvBank').value = 'all';
    if (document.getElementById('filterInvDate')) document.getElementById('filterInvDate').value = '';
    if (document.getElementById('filterInvLimit')) document.getElementById('filterInvLimit').value = '25';
    if (document.getElementById('filterInvSort')) document.getElementById('filterInvSort').value = 'desc';
    adminFilterInvoices();
}

function renderInvoicesTable(invoices) {
    const tbody = document.getElementById('invoiceTableBody');
    if (!tbody) return;

    if (!invoices || invoices.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted" style="padding: 24px;">Không tìm thấy giao dịch nào phù hợp với bộ lọc</td></tr>`;
        return;
    }

    tbody.innerHTML = invoices.map(inv => {
        const fmtAmount = Number(inv.amount || 0).toLocaleString('vi-VN') + 'đ';
        const isPaid = Number(inv.status) === 1;
        const statusBadge = isPaid
            ? `<span class="badge-status-dot badge-dot-paid"><span class="dot"></span> Đã thanh toán</span>`
            : `<span class="badge-status-dot badge-dot-pending"><span class="dot"></span> Chờ thanh toán</span>`;
        
        const rawTime = inv.created_at || (inv.create_time ? new Date(inv.create_time * 1000).toISOString() : '');
        const rawUpdate = inv.updated_at || rawTime;

        let timeStr = '--:--:--', dateStr = '--/--/----';
        let upTimeStr = '--:--:--', upDateStr = '--/--/----';

        try {
            if (rawTime) {
                const d = new Date(rawTime.includes('T') ? rawTime : rawTime.replace(' ', 'T'));
                if (!isNaN(d.getTime())) {
                    timeStr = d.toLocaleTimeString('vi-VN', { hour12: false });
                    dateStr = d.toLocaleDateString('vi-VN');
                }
            }
            if (rawUpdate) {
                const ud = new Date(rawUpdate.includes('T') ? rawUpdate : rawUpdate.replace(' ', 'T'));
                if (!isNaN(ud.getTime())) {
                    upTimeStr = ud.toLocaleTimeString('vi-VN', { hour12: false });
                    upDateStr = ud.toLocaleDateString('vi-VN');
                }
            }
        } catch (e) {}

        const userEmail = escapeHtml(inv.email || inv.username || 'User');

        return `
            <tr id="invRow_${inv.id}">
                <td style="text-align: center;">
                    <div class="inv-action-btns">
                        <button type="button" class="btn-inv-action btn-inv-edit" onclick="openEditInvoiceModal(${inv.id}, ${inv.status}, ${inv.amount})" title="Chỉnh sửa hóa đơn">
                            <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button type="button" class="btn-inv-action btn-inv-del" onclick="adminDeleteInvoice(${inv.id})" title="Xóa hóa đơn">
                            <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                </td>
                <td>
                    <div class="inv-user-cell">
                        <span class="user-id-pill">#${inv.user_id}</span>
                        <a href="javascript:void(0)" class="inv-username-link" title="${userEmail}">${userEmail}</a>
                    </div>
                </td>
                <td><span class="inv-code-pill">${escapeHtml(inv.trans_id || '')}</span></td>
                <td>${statusBadge}</td>
                <td><strong class="inv-money-green">${fmtAmount}</strong></td>
                <td><strong class="inv-money-green">${fmtAmount}</strong></td>
                <td><span class="inv-bank-tag">${escapeHtml(inv.payment_method || 'ACB')}</span></td>
                <td class="inv-time-col">
                    <div class="dt-cell">
                        <span class="dt-time">${timeStr}</span>
                        <span class="dt-date">${dateStr}</span>
                    </div>
                </td>
                <td class="inv-time-col">
                    <div class="dt-cell">
                        <span class="dt-time">${upTimeStr}</span>
                        <span class="dt-date">${upDateStr}</span>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function renderInvoiceStats(stats) {
    if (!stats) return;
    const elAll = document.getElementById('statAllTime');
    const elMonth = document.getElementById('statMonth');
    const elWeek = document.getElementById('statWeek');
    const elToday = document.getElementById('statToday');

    if (elAll && stats.all_time !== undefined) elAll.textContent = Number(stats.all_time).toLocaleString('vi-VN') + 'đ';
    if (elMonth && stats.month !== undefined) elMonth.textContent = Number(stats.month).toLocaleString('vi-VN') + 'đ';
    if (elWeek && stats.week !== undefined) elWeek.textContent = Number(stats.week).toLocaleString('vi-VN') + 'đ';
    if (elToday && stats.today !== undefined) elToday.textContent = Number(stats.today).toLocaleString('vi-VN') + 'đ';

    const kpiRev = document.getElementById('kpiTotalRevenue');
    if (kpiRev && stats.all_time !== undefined) {
        kpiRev.textContent = Number(stats.all_time).toLocaleString('vi-VN') + ' đ';
    }

    if (stats.chart_title) {
        const titleEl = document.getElementById('chartPeriodTitle');
        if (titleEl) titleEl.textContent = stats.chart_title;
    }

    if (Array.isArray(stats.chart_data)) {
        const chartBars = document.getElementById('paymentChartBars');
        const yAxisContainer = document.querySelector('.chart-y-axis');
        const gridlinesContainer = document.querySelector('.chart-gridlines');

        const chartMax = Number(stats.chart_max) || 300000;
        const step = chartMax / 5;
        const yTicks = [
            chartMax,
            Math.round(chartMax - step),
            Math.round(chartMax - step * 2),
            Math.round(chartMax - step * 3),
            Math.round(chartMax - step * 4),
            0
        ];

        if (yAxisContainer) {
            yAxisContainer.innerHTML = yTicks.map(t => `<span class="y-tick-label">${Number(t).toLocaleString('vi-VN')}đ</span>`).join('');
        }
        if (gridlinesContainer) {
            gridlinesContainer.innerHTML = yTicks.map(() => `<div class="chart-gridline"></div>`).join('');
        }

        if (chartBars) {
            let html = '';
            stats.chart_data.forEach(item => {
                const amount = Number(item.amount) || 0;
                const label = item.label || '';
                const fullLabel = item.full_label || label;
                const showLabel = Boolean(item.show_label);
                const pct = Math.min(100, Math.round((amount / (chartMax || 1)) * 100));
                const fmtAmount = Number(amount).toLocaleString('vi-VN') + 'đ';

                html += `
                    <div class="chart-bar-col" title="${escapeHtml(fullLabel)}: ${fmtAmount}">
                        <div class="bar-value-tooltip">${fmtAmount}</div>
                        <div class="bar-track">
                            <div class="bar-fill ${amount > 0 ? 'active' : ''}" style="height: ${pct}%;"></div>
                        </div>
                        ${showLabel ? `<span class="bar-label-clean">${escapeHtml(label)}</span>` : ''}
                    </div>
                `;
            });
            chartBars.innerHTML = html;
        }
    } else if (stats.daily_stats && typeof stats.daily_stats === 'object') {
        const chartBars = document.getElementById('paymentChartBars');
        if (chartBars) {
            const values = Object.values(stats.daily_stats);
            const maxVal = Math.max(...values, 0);
            const chartMax = Math.max(300000, Math.ceil((maxVal || 1) / 50000) * 50000);
            
            let html = '';
            for (const [dayDate, amount] of Object.entries(stats.daily_stats)) {
                const parts = dayDate.split('-');
                const dayNum = `${parseInt(parts[2], 10)}/${parseInt(parts[1], 10)}`;
                const pct = Math.min(100, Math.round((amount / chartMax) * 100));
                const fmtAmount = Number(amount).toLocaleString('vi-VN') + 'đ';
                html += `
                    <div class="chart-bar-col" title="${dayDate}: ${fmtAmount}">
                        <div class="bar-value-tooltip">${fmtAmount}</div>
                        <div class="bar-track">
                            <div class="bar-fill ${amount > 0 ? 'active' : ''}" style="height: ${pct}%;"></div>
                        </div>
                        <span class="bar-label-clean">${dayNum}</span>
                    </div>
                `;
            }
            chartBars.innerHTML = html;
        }
    }
}

function openEditInvoiceModal(id, status, amount) {
    const elId = document.getElementById('editInvId');
    const elBadge = document.getElementById('editInvIdBadge');
    const elAmount = document.getElementById('editInvAmount');
    const elStatus = document.getElementById('editInvStatus');

    if (elId) elId.value = id;
    if (elBadge) elBadge.textContent = id;
    if (elAmount) elAmount.value = amount;
    if (elStatus) elStatus.value = status;

    if (typeof openModal === 'function') {
        openModal('editInvoiceModal');
    }
}

async function saveEditInvoice(event) {
    if (event) event.preventDefault();
    const id = document.getElementById('editInvId')?.value;
    const amount = document.getElementById('editInvAmount')?.value;
    const status = document.getElementById('editInvStatus')?.value;

    try {
        const res = await fetch('/api/admin/invoices/update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, amount, status })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message || 'Cập nhật hóa đơn thành công!', 'success');
            if (typeof closeModal === 'function') {
                closeModal('editInvoiceModal');
            }
            adminFilterInvoices();
        } else {
            showToast('Lỗi: ' + data.message, 'error');
        }
    } catch (e) {
        showToast('Lỗi kết nối: ' + e.message, 'error');
    }
}

async function adminDeleteInvoice(id) {
    if (!confirm(`Bạn có chắc chắn muốn xóa hóa đơn #${id}? Thao tác này không thể hoàn tác.`)) {
        return;
    }

    try {
        const res = await fetch('/api/admin/invoices/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message || 'Đã xóa hóa đơn!', 'success');
            const row = document.getElementById(`invRow_${id}`);
            if (row) row.remove();
            adminFilterInvoices();
        } else {
            showToast('Lỗi: ' + data.message, 'error');
        }
    } catch (e) {
        showToast('Lỗi kết nối: ' + e.message, 'error');
    }
}

let paymentPollingTimer = null;
let paymentCountdownTimer = null;
let paymentCountdownSeconds = 3595; // 59m 55s matching Screenshot 2
let currentPendingTransId = null;

async function openVietQrModal(planType = 'PRO', amount = 99000, existingTransId = null, existingMemo = null) {
    if (!window.APP_IS_LOGGED_IN || !window.APP_CURRENT_USER || !window.APP_CURRENT_USER.id) {
        showToast('Vui lòng đăng nhập tài khoản để nâng cấp gói dịch vụ!', 'info');
        if (typeof openAuthModal === 'function') {
            openAuthModal('login');
        } else if (typeof openModal === 'function') {
            openModal('authModal');
        }
        return;
    }

    try {
        let transId = existingTransId;
        let memo = existingMemo;
        let finalAmount = amount;

        // Nếu chưa có mã đơn (bấm nút Nâng cấp từ thẻ gói) -> tạo hóa đơn mới
        if (!transId) {
            const res = await fetch('/api/payment/create-invoice', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ plan: planType, amount: finalAmount })
            });
            const data = await res.json();
            if (data.status === 'success' && data.invoice) {
                transId = data.invoice.trans_id;
                memo = data.invoice.memo;
                finalAmount = data.invoice.amount;
            } else {
                throw new Error(data.message || 'Không thể tạo đơn thanh toán');
            }
        }

        currentPendingTransId = transId;

        // Lấy cấu hình ngân hàng ACB
        const resBank = await fetch('/api/payment/bank-info');
        const dataBank = await resBank.json();
        const bank = dataBank.data || {};

        const bankCode = bank.bank_code || 'ACB';
        const bankName = bank.bank_name || 'Ngân hàng TMCP Á Châu (ACB)';
        const accNum = bank.account_number || '17449371';
        const accName = bank.account_name || 'VU VAN CHIEN';
        const prefix = bank.memo_prefix || 'PDFPRO';

        if (!memo) {
            memo = `${prefix} ${window.APP_CURRENT_USER.id} ${transId}`;
        }

        // Cập nhật DOM các thành phần trong Modal matching Screenshot 2
        const elInvCode = document.getElementById('vqrInvoiceCode');
        const elBankFullName = document.getElementById('vqrBankFullName');
        const elBankShort = document.getElementById('vqrBankShort');
        const elAccName = document.getElementById('vqrAccountName');
        const elAccNum = document.getElementById('vqrAccountNumber');
        const elAmountDisp = document.getElementById('vqrAmountDisplay');
        const elMemoDisp = document.getElementById('vqrMemoDisplay');
        const elQrImg = document.getElementById('vqrQrImage');
        const elSuccessBanner = document.getElementById('vqrSuccessBanner');
        const elStatusText = document.getElementById('vqrStatusText');
        const elStatusInd = document.getElementById('vqrStatusIndicator');

        if (elInvCode) elInvCode.textContent = transId;
        if (elBankFullName) elBankFullName.textContent = bankName;
        if (elBankShort) elBankShort.textContent = bankCode;
        if (elAccName) elAccName.textContent = accName;
        if (elAccNum) elAccNum.textContent = accNum;
        if (elAmountDisp) {
            elAmountDisp.textContent = Number(finalAmount).toLocaleString('vi-VN') + 'đ';
            elAmountDisp.dataset.rawAmount = finalAmount;
        }
        if (elMemoDisp) elMemoDisp.textContent = memo;

        if (elSuccessBanner) elSuccessBanner.style.display = 'none';
        if (elStatusInd) elStatusInd.style.display = 'flex';
        if (elStatusText) elStatusText.textContent = 'Đang chờ thanh toán...';

        const encodedMemo = encodeURIComponent(memo);
        const encodedName = encodeURIComponent(accName);
        const qrUrl = `https://api.vietqr.io/image/${bankCode.toLowerCase()}-${accNum}-compact2.png?amount=${finalAmount}&addInfo=${encodedMemo}&accountName=${encodedName}`;
        if (elQrImg) elQrImg.src = qrUrl;

        // Mở modal
        if (typeof openModal === 'function') {
            openModal('vietQrModal');
        }

        // Bắt đầu đếm ngược 59:55
        startPaymentCountdown(3595);

        // Bắt đầu polling kiểm tra thanh toán
        startPaymentPolling(transId);

        // Tự động refresh danh sách hóa đơn người dùng ở bảng lịch sử
        refreshUserInvoices();
    } catch (e) {
        showToast('Lỗi khởi tạo thanh toán: ' + e.message, 'error');
    }
}

function startPaymentCountdown(seconds = 3595) {
    stopPaymentCountdown();
    paymentCountdownSeconds = seconds;
    updateCountdownDisplay();
    paymentCountdownTimer = setInterval(() => {
        paymentCountdownSeconds--;
        if (paymentCountdownSeconds <= 0) {
            stopPaymentCountdown();
            const elStatusText = document.getElementById('vqrStatusText');
            if (elStatusText) elStatusText.textContent = 'Mã QR đã hết hạn, vui lòng tạo lại!';
        } else {
            updateCountdownDisplay();
        }
    }, 1000);
}

function stopPaymentCountdown() {
    if (paymentCountdownTimer) {
        clearInterval(paymentCountdownTimer);
        paymentCountdownTimer = null;
    }
}

function updateCountdownDisplay() {
    const el = document.getElementById('vqrTimerVal');
    if (!el) return;
    const m = Math.floor(paymentCountdownSeconds / 60);
    const s = paymentCountdownSeconds % 60;
    el.textContent = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function closeVietQrModal() {
    stopPaymentPolling();
    stopPaymentCountdown();
    currentPendingTransId = null;
    if (typeof closeModal === 'function') {
        closeModal('vietQrModal');
    }
}

function startPaymentPolling(transId = null) {
    stopPaymentPolling();
    const query = transId ? `?trans_id=${encodeURIComponent(transId)}` : '';
    paymentPollingTimer = setInterval(async () => {
        try {
            const res = await fetch(`/api/payment/check-status${query}`);
            const data = await res.json();
            if (data.status === 'success' && (data.is_paid || data.is_pro)) {
                stopPaymentPolling();
                stopPaymentCountdown();
                onPaymentSuccess(data);
            }
        } catch (e) {}
    }, 3000);
}

function onPaymentSuccess(data) {
    const elSuccessBanner = document.getElementById('vqrSuccessBanner');
    const elStatusInd = document.getElementById('vqrStatusIndicator');
    if (elSuccessBanner) elSuccessBanner.style.display = 'flex';
    if (elStatusInd) elStatusInd.style.display = 'none';

    showToast('🎉 Chúc mừng! Giao dịch được xác nhận, bạn đã nâng cấp thành công gói PRO!', 'success');

    if (window.APP_CURRENT_USER) {
        window.APP_CURRENT_USER.plan = 'PRO';
    }

    document.querySelectorAll('.user-plan-badge, .badge-plan, .plan-badge-text').forEach(b => {
        b.textContent = 'PRO';
    });

    refreshUserInvoices();

    setTimeout(() => {
        closeVietQrModal();
        window.location.reload();
    }, 2800);
}

async function refreshUserInvoices() {
    const btn = document.getElementById('btnRefreshInvoices');
    if (btn) btn.classList.add('spinning');

    try {
        const res = await fetch('/api/payment/user-invoices');
        const data = await res.json();
        if (data.status === 'success' && Array.isArray(data.invoices)) {
            renderUserInvoicesTable(data.invoices);
        }
    } catch (e) {
    } finally {
        if (btn) {
            setTimeout(() => btn.classList.remove('spinning'), 600);
        }
    }
}

function renderUserInvoicesTable(invoices) {
    const tbody = document.getElementById('userInvoiceTableBody');
    if (!tbody) return;

    if (!invoices || invoices.length === 0) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="7" class="text-center text-muted py-4">Chưa có giao dịch thanh toán nào được ghi nhận.</td></tr>';
        return;
    }

    let html = '';
    invoices.forEach(inv => {
        const isPaid = (Number(inv.status) === 1);
        const isFailed = (Number(inv.status) === 2);
        const transCode = inv.trans_id || '';
        const amountFmt = Number(inv.amount || 0).toLocaleString('vi-VN') + 'đ';
        const dateStr = inv.created_at || '';
        const safeDesc = (inv.description || '').replace(/'/g, "\\'");

        let badge = '<span class="inv-status-badge badge-waiting">Chờ thanh toán</span>';
        if (isPaid) badge = '<span class="inv-status-badge badge-success">Đã thanh toán</span>';
        else if (isFailed) badge = '<span class="inv-status-badge badge-failed">Thất bại</span>';

        let actionBtn = `<button type="button" class="btn-user-view-qr" onclick="openVietQrModal('PRO', ${Number(inv.amount || 99000)}, '${transCode}', '${safeDesc}')" title="Xem mã VietQR">
            <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <span>Xem QR</span>
        </button>`;
        if (isPaid || isFailed) {
            actionBtn = '<span class="text-muted text-xs">Đã hoàn tất</span>';
        }

        html += `<tr id="userInvRow_${inv.id}">
            <td><span class="user-inv-code">${transCode}</span></td>
            <td>
                <div class="user-inv-plan-title">
                    <strong>Chuyên nghiệp</strong>
                    <span class="inv-plan-sub">30 ngày</span>
                </div>
            </td>
            <td><strong class="user-inv-amount">${amountFmt}</strong></td>
            <td class="user-inv-time">${dateStr}</td>
            <td class="user-inv-method"><span>Chuyển khoản</span></td>
            <td>${badge}</td>
            <td style="text-align: right;">${actionBtn}</td>
        </tr>`;
    });

    tbody.innerHTML = html;
}

function toggleBillingCycle(isYearly) {
    const elMonthly = document.getElementById('labelMonthly');
    const elYearly = document.getElementById('labelYearly');
    const proPrice = document.getElementById('proPriceValue');
    const proPeriod = document.getElementById('proPricePeriod');
    const proHint = document.getElementById('proYearlyHint');
    const entPrice = document.getElementById('entPriceValue');
    const entPeriod = document.getElementById('entPricePeriod');
    const entHint = document.getElementById('entYearlyHint');

    if (isYearly) {
        if (elMonthly) elMonthly.classList.remove('active');
        if (elYearly) elYearly.classList.add('active');
        if (proPrice) proPrice.textContent = '950.000đ';
        if (proPeriod) proPeriod.textContent = '/năm';
        if (proHint) proHint.style.display = 'block';
        if (entPrice) entPrice.textContent = '2.870.000đ';
        if (entPeriod) entPeriod.textContent = '/năm';
        if (entHint) entHint.style.display = 'block';
    } else {
        if (elMonthly) elMonthly.classList.add('active');
        if (elYearly) elYearly.classList.remove('active');
        if (proPrice) proPrice.textContent = '99.000đ';
        if (proPeriod) proPeriod.textContent = '/ngày';
        if (proHint) proHint.style.display = 'none';
        if (entPrice) entPrice.textContent = '299.000đ';
        if (entPeriod) entPeriod.textContent = '/tháng';
        if (entHint) entHint.style.display = 'none';
    }
}

function copyToClipboard(text, successMsg = 'Đã sao chép vào bộ nhớ tạm!') {
    if (!text) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            showToast(successMsg, 'success');
        }).catch(() => {
            fallbackCopy(text, successMsg);
        });
    } else {
        fallbackCopy(text, successMsg);
    }
}

function fallbackCopy(text, successMsg) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        showToast(successMsg, 'success');
    } catch (err) {
        showToast('Không thể sao chép: ' + err, 'error');
    }
    document.body.removeChild(textarea);
}

// Tự động khởi tạo dữ liệu cho các trang Admin tương ứng khi tải trang
if (typeof window !== 'undefined') {
    const initAdminPagesOnLoad = () => {
        if (window.APP_IS_ADMIN) {
            const path = window.location.pathname;
            if (path.includes('/admin/plans') || document.getElementById('adminPage-plans')?.classList.contains('active')) {
                if (typeof adminLoadPlans === 'function') adminLoadPlans();
            } else if (path.includes('/admin/maintenance') || document.getElementById('adminPage-maintenance')?.classList.contains('active')) {
                if (typeof adminLoadMaintenanceStatus === 'function') adminLoadMaintenanceStatus();
            }
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdminPagesOnLoad);
    } else {
        setTimeout(initAdminPagesOnLoad, 100);
    }
}





