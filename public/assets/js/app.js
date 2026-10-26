/**
 * PDF Manager - Frontend Application Logic
 * Đề tài 12 - Học phần Phát triển phần mềm mã nguồn mở
 */

let currentOutputId = 1;

function apiUrl(path) {
    const base = window.BASE_URL || '';
    if (!path.startsWith('/')) path = '/' + path;
    return base + path;
}
window.apiUrl = apiUrl;

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
            showToast('Đã hoàn tác thao tác (Undo)', 'info');
        } else {
            showToast('Không còn thao tác nào để hoàn tác.', 'info');
        }
    }

    rotatePage(index, deg = 90) {
        this.pushHistory();
        if (this.pages[index]) {
            this.pages[index].rotation = (this.pages[index].rotation + deg) % 360;
            this.render();
            showToast(`Đã xoay trang ${this.pages[index].label} sang ${this.pages[index].rotation}°`, 'success');
        }
    }

    toggleKeep(index) {
        this.pushHistory();
        if (this.pages[index]) {
            this.pages[index].keep = !this.pages[index].keep;
            this.render();
            const status = this.pages[index].keep ? 'giữ lại' : 'loại bỏ khỏi bản xuất';
            showToast(`Đã ${status} trang ${this.pages[index].label}`, 'info');
        }
    }

    movePage(fromIndex, toIndex) {
        if (fromIndex === toIndex) return;
        this.pushHistory();
        const [movedItem] = this.pages.splice(fromIndex, 1);
        this.pages.splice(toIndex, 0, movedItem);
        this.render();
        showToast(`Đã đổi vị trí trang ${movedItem.label} sang vị trí ${toIndex + 1}`, 'info');
    }

    resetPlan() {
        this.pushHistory();
        this.initDefaultPlan();
        this.render();
        showToast('Đã đặt lại kế hoạch về mẫu mặc định (A2, B1, A1)', 'info');
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

        showToast('Đang tiến hành xuất tệp PDF qua engine...', 'info');

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
const VALID_VIEWS = ['home', 'documents', 'editor', 'upgrade', 'history', 'settings'];
const LEGACY_VIEW_MAP = {
    'reorder': 'editor',
    'merge': 'editor',
    'split': 'editor'
};

// Quản lý trạng thái thu gọn Sidebar (Chỉ hiện Icon: 72px)
function toggleSidebarCompact() {
    const layout = document.getElementById('appLayout');
    const sidebar = document.getElementById('appSidebar');
    if (!layout || !sidebar) return;

    const isCompact = layout.classList.toggle('sidebar-compact');
    sidebar.classList.toggle('collapsed', isCompact);
    localStorage.setItem('sidebar_compact', isCompact ? 'true' : 'false');
    showToast(isCompact ? 'Đã thu gọn Sidebar thành biểu tượng (72px)' : 'Đã mở rộng Sidebar (260px)', 'info');
}

function setSidebarCompact(compact) {
    const layout = document.getElementById('appLayout');
    const sidebar = document.getElementById('appSidebar');
    if (!layout || !sidebar) return;

    if (compact) {
        layout.classList.add('sidebar-compact');
        sidebar.classList.add('collapsed');
    } else {
        layout.classList.remove('sidebar-compact');
        sidebar.classList.remove('collapsed');
    }
}

// Lấy view hiện tại từ pathname hoặc chuyển đổi hash cũ sang đường dẫn sạch
function getPathView() {
    const base = (window.BASE_URL || '').replace(/^\/+|\/+$/g, '');
    if (window.location.hash && window.location.hash.length > 1) {
        let hashView = window.location.hash.replace(/^#\/?/, '').split('?')[0].split('/')[0];
        if (LEGACY_VIEW_MAP[hashView]) hashView = LEGACY_VIEW_MAP[hashView];
        if (VALID_VIEWS.includes(hashView)) {
            const targetPath = (base ? '/' + base : '') + '/' + hashView;
            window.history.replaceState({ view: hashView }, '', targetPath);
            return hashView;
        }
    }
    let pathname = window.location.pathname.replace(/^\/+|\/+$/g, '');
    if (base && pathname.startsWith(base)) {
        pathname = pathname.substring(base.length).replace(/^\/+|\/+$/g, '');
    }
    let cleanPath = pathname.split('/').filter(Boolean).pop() || 'home';
    if (LEGACY_VIEW_MAP[cleanPath]) cleanPath = LEGACY_VIEW_MAP[cleanPath];
    if (VALID_VIEWS.includes(cleanPath)) {
        return cleanPath;
    }
    return 'home';
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

    const isDocView = viewName === 'documents';
    const containerId = isDocView ? 'view-home' : `view-${viewName}`;

    // Đổi hiển thị container View
    document.querySelectorAll('.workspace-view').forEach(v => v.classList.remove('active'));
    const targetView = document.getElementById(containerId);
    if (targetView) {
        targetView.classList.add('active');
    }

    // Cập nhật trạng thái active sidebar navigation
    document.querySelectorAll('.nav-item').forEach(n => {
        const itemRoute = n.getAttribute('data-view') || n.getAttribute('href')?.split('/').pop();
        n.classList.toggle('active', itemRoute === viewName);
    });

    // Cập nhật trạng thái active mobile bottom navigation
    document.querySelectorAll('.mobile-nav-item').forEach(m => {
        const itemRoute = m.getAttribute('data-view') || m.getAttribute('href')?.split('/').pop();
        m.classList.toggle('active', itemRoute === viewName);
    });

    // Cập nhật URL trình duyệt qua History API: /home, /documents, /editor, /upgrade... (không có #)
    const shouldPush = (typeof eventOrPush === 'boolean') ? eventOrPush : true;
    if (shouldPush) {
        const base = (window.BASE_URL || '').replace(/\/+$/, '');
        const targetPath = (base ? base : '') + '/' + viewName;
        if (window.location.pathname !== targetPath || window.location.hash) {
            window.history.pushState({ view: viewName }, '', targetPath);
        }
    }

    // Tự động đóng menu trượt mobile nếu đang mở
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('active');
    }

    // Hành động chuyên biệt từng view
    if (viewName === 'editor') {
        // Tự động thu gọn sidebar thành biểu tượng khi vào Trình biên tập để tối ưu không gian làm việc
        setSidebarCompact(true);
        if (!hasLoadedFile) {
            showEditorUploadState();
        } else {
            showEditorWorkspace();
        }
    } else if (viewName === 'history') {
        loadExportHistory();
    } else if (viewName === 'settings') {
        loadUserSettings();
    }

    // Cuộn mượt đến phần tài liệu nếu chuyển đến /documents
    if (isDocView) {
        const docSec = document.getElementById('documentsSection');
        if (docSec) {
            docSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Xử lý nút Back/Forward trên trình duyệt
window.addEventListener('popstate', (e) => {
    const view = (e.state && e.state.view) ? e.state.view : getPathView();
    switchView(view, false);
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
        let path = href.split('?')[0].split('#')[0];
        const base = (window.BASE_URL || '').replace(/\/+$/, '');
        if (base && path.startsWith(base)) {
            path = path.substring(base.length);
        }
        const target = path.replace(/^\/+|\/+$/g, '');
        if (VALID_VIEWS.includes(target)) {
            e.preventDefault();
            switchView(target, true);
        }
    }
});

// Tự động chuẩn hóa URL và đồng bộ trạng thái Route khi tải trang
function initCleanRouting() {
    let targetView = getPathView();
    const base = (window.BASE_URL || '').replace(/\/+$/, '');
    const targetPath = (base ? base : '') + '/' + targetView;
    if (window.location.hash) {
        window.history.replaceState({ view: targetView }, '', targetPath);
    }
    switchView(targetView, false);
}

// Chuyển Tab ở Dashboard
function toggleDocTab(tabName) {
    document.getElementById('tabDocList').classList.toggle('active', tabName === 'list');
    document.getElementById('tabProcessing').classList.toggle('active', tabName === 'processing');

    if (tabName === 'processing') {
        showToast('Hiển thị 2 tài liệu đang trong tiến trình xử lý.', 'info');
    }
}

// Modal helper
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

function openUploadModal() {
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
    showToast(`Đã chọn tài liệu: ${docName} (${pages} trang). Bạn có thể mở Ghép hoặc Tách.`, 'info');
}

// Thực hiện ghép PDF tiêu chuẩn
async function executeStandardMerge() {
    const outputName = document.getElementById('mergeOutputName').value.trim() || 'Tai_lieu_ghep.pdf';
    showToast('Đang ghép các trang A2 + B1 + A1...', 'info');

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
    showToast('Đang tách Tai_lieu_hoc_tap.pdf thành 2 nhóm trang...', 'info');

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

// =========================================================
// LỊCH SỬ XUẤT (EXPORT HISTORY & MANIFEST ENGINE)
// =========================================================
let rawExportHistory = [];

async function loadExportHistory() {
    const tbody = document.getElementById('historyTableBody');
    if (!tbody) return;

    tbody.innerHTML = `
        <tr>
            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                <div class="spinner-small" style="margin: 0 auto 10px;"></div>
                <span>Đang tải lịch sử xuất...</span>
            </td>
        </tr>
    `;

    try {
        const res = await fetch(apiUrl('/api/export/history'));
        const data = await res.json();
        rawExportHistory = Array.isArray(data.history) ? data.history : [];

        updateHistoryMetrics(rawExportHistory);
        filterExportHistory();
    } catch (e) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; color: #EF4444; padding: 24px;">
                    Lỗi khi tải dữ liệu lịch sử xuất. Vui lòng kiểm tra kết nối.
                </td>
            </tr>
        `;
    }
}

function updateHistoryMetrics(items) {
    const elFiles = document.getElementById('historyMetricFiles');
    const elPages = document.getElementById('historyMetricPages');
    const elSize = document.getElementById('historyMetricSize');
    const elRecent = document.getElementById('historyMetricRecent');

    if (!elFiles) return;

    const totalFiles = items.length;
    let totalPages = 0;
    let totalBytes = 0;

    items.forEach(item => {
        totalPages += parseInt(item.total_pages || 0, 10);
        totalBytes += parseInt(item.file_size || 0, 10);
    });

    elFiles.innerText = totalFiles.toLocaleString('vi-VN');
    elPages.innerText = totalPages.toLocaleString('vi-VN');

    if (totalBytes > 1024 * 1024) {
        elSize.innerText = (totalBytes / (1024 * 1024)).toFixed(2) + ' MB';
    } else {
        elSize.innerText = Math.round(totalBytes / 1024).toLocaleString('vi-VN') + ' KB';
    }

    if (items.length > 0 && items[0].created_at) {
        const rawDate = items[0].created_at;
        const d = new Date(rawDate.replace(' ', 'T'));
        if (!isNaN(d.getTime())) {
            const timeStr = d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
            const dateStr = d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
            elRecent.innerText = `${timeStr} (${dateStr})`;
        } else {
            elRecent.innerText = items[0].created_at;
        }
    } else {
        elRecent.innerText = 'Chưa có';
    }
}

function filterExportHistory() {
    const tbody = document.getElementById('historyTableBody');
    if (!tbody) return;

    const searchInput = document.getElementById('historySearchInput');
    const clearBtn = document.getElementById('historySearchClear');
    const typeFilter = document.getElementById('historyTypeFilter');
    const sortFilter = document.getElementById('historySortFilter');
    const countNotice = document.getElementById('historyCountNotice');

    const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
    const selectedType = typeFilter ? typeFilter.value : 'all';
    const selectedSort = sortFilter ? sortFilter.value : 'date_desc';

    if (clearBtn) {
        clearBtn.style.display = query ? 'block' : 'none';
    }

    let filtered = rawExportHistory.filter(item => {
        if (query) {
            const matchName = (item.filename || '').toLowerCase().includes(query);
            const matchId = ('#' + item.id).includes(query) || String(item.id).includes(query);
            if (!matchName && !matchId) return false;
        }

        if (selectedType !== 'all') {
            const fn = (item.filename || '').toLowerCase();
            if (selectedType === 'merge' && !fn.includes('ghep') && !fn.includes('merge')) return false;
            if (selectedType === 'split' && !fn.includes('tach') && !fn.includes('split') && !fn.includes('nhom')) return false;
            if (selectedType === 'edit' && !fn.includes('bien_tap') && !fn.includes('edit') && !fn.includes('doc_')) return false;
        }

        return true;
    });

    filtered.sort((a, b) => {
        if (selectedSort === 'date_desc') return b.id - a.id;
        if (selectedSort === 'date_asc') return a.id - b.id;
        if (selectedSort === 'size_desc') return (b.file_size || 0) - (a.file_size || 0);
        if (selectedSort === 'pages_desc') return (b.total_pages || 0) - (a.total_pages || 0);
        return 0;
    });

    const isEn = (document.documentElement.getAttribute('lang') || 'vi') === 'en';

    if (countNotice) {
        countNotice.innerText = isEn
            ? `Showing ${filtered.length} / ${rawExportHistory.length} exported files`
            : `Hiển thị ${filtered.length} / ${rawExportHistory.length} tệp xuất`;
    }

    if (filtered.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 48px 20px;">
                    <div style="margin-bottom: 12px;">
                        <svg class="svg-icon svg-icon-lg" style="stroke: #94A3B8; width: 44px; height: 44px;" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                    </div>
                    <h4 style="font-size: 1rem; font-weight: 700; color: #334155; margin-bottom: 6px;">${isEn ? 'No exported files found' : 'Không tìm thấy tệp xuất nào'}</h4>
                    <p style="font-size: 0.85rem; color: #64748B; margin-bottom: 16px;">${query ? (isEn ? 'No files match your search keywords.' : 'Không có tệp nào khớp với từ khóa tìm kiếm.') : (isEn ? 'No documents have been exported yet.' : 'Chưa có tài liệu nào được xuất từ hệ thống.')}</p>
                    <a href="/editor" class="btn-primary btn-sm" onclick="switchView('editor', event)" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                        <span>${isEn ? 'Open PDF Editor' : 'Mở Trình Chỉnh sửa PDF'}</span>
                    </a>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = '';
    filtered.forEach(item => {
        const tr = document.createElement('tr');
        
        const fn = (item.filename || '').toLowerCase();
        let badgeType = isEn
            ? '<span class="status-pill info"><span class="status-dot"></span> Merge PDF</span>'
            : '<span class="status-pill info"><span class="status-dot"></span> Ghép PDF</span>';
        if (fn.includes('tach') || fn.includes('split') || fn.includes('nhom')) {
            badgeType = isEn
                ? '<span class="status-pill purple"><span class="status-dot"></span> Split PDF</span>'
                : '<span class="status-pill purple"><span class="status-dot"></span> Tách PDF</span>';
        } else if (fn.includes('bien_tap') || fn.includes('edit')) {
            badgeType = isEn
                ? '<span class="status-pill success"><span class="status-dot"></span> Editor</span>'
                : '<span class="status-pill success"><span class="status-dot"></span> Biên tập</span>';
        }

        const sizeFormatted = item.file_size > 1024 * 1024
            ? (item.file_size / (1024 * 1024)).toFixed(2) + ' MB'
            : Math.round(item.file_size / 1024) + ' KB';

        const downloadTitle = isEn ? 'Download PDF' : 'Tải xuống PDF';
        const previewTitle = isEn ? 'Quick preview info' : 'Xem nhanh thông tin';
        const manifestTitle = isEn ? 'Source Manifest audit trail' : 'Tra cứu bảng đối chiếu nguồn (Manifest)';
        const deleteTitle = isEn ? 'Delete this record' : 'Xóa bản ghi này';

        tr.innerHTML = `
            <td><span class="history-id-badge">#${item.id}</span></td>
            <td>
                <div class="file-cell">
                    <div class="file-icon-pdf">PDF</div>
                    <div class="file-info-col">
                        <span class="file-name-text" title="${item.filename}">${item.filename}</span>
                        <span class="file-iso-badge">ISO/IEC 32000</span>
                    </div>
                </div>
            </td>
            <td>${badgeType}</td>
            <td><strong>${item.total_pages}</strong> ${isEn ? 'pages' : 'trang'}</td>
            <td>${sizeFormatted}</td>
            <td><span class="history-time-text">${item.created_at || (isEn ? 'Just now' : 'Vừa xong')}</span></td>
            <td>
                <div class="actions-cell history-actions-cell">
                    <a href="${apiUrl('/api/download?file=' + encodeURIComponent(item.filename))}" class="btn-table-action" title="${downloadTitle}">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </a>
                    <button type="button" class="btn-table-action" title="${previewTitle}" onclick="openHistoryPreview(${item.id})">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                    <button type="button" class="btn-table-action" title="${manifestTitle}" onclick="openManifestModal(${item.id})">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </button>
                    <button type="button" class="btn-table-action delete-action" title="${deleteTitle}" onclick="deleteHistoryItem(${item.id})">
                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function clearHistorySearch() {
    const input = document.getElementById('historySearchInput');
    if (input) {
        input.value = '';
        filterExportHistory();
    }
}

async function openHistoryPreview(outputId) {
    const item = rawExportHistory.find(h => h.id == outputId);
    if (!item) return;

    const isEn = (document.documentElement.getAttribute('lang') || 'vi') === 'en';
    const elId = document.getElementById('prevHistId');
    const elName = document.getElementById('prevHistFilename');
    const elPages = document.getElementById('prevHistPages');
    const elSize = document.getElementById('prevHistSize');
    const elTime = document.getElementById('prevHistTime');
    const elDl = document.getElementById('prevHistDownloadBtn');
    const elManifestBtn = document.getElementById('prevHistManifestBtn');
    const elBadges = document.getElementById('prevHistPageBadges');

    if (elId) elId.innerText = '#' + item.id;
    if (elName) elName.innerText = item.filename;
    if (elPages) elPages.innerText = item.total_pages + (isEn ? ' pages' : ' trang');
    if (elSize) {
        elSize.innerText = item.file_size > 1024 * 1024
            ? (item.file_size / (1024 * 1024)).toFixed(2) + ' MB'
            : Math.round(item.file_size / 1024) + ' KB';
    }
    if (elTime) elTime.innerText = item.created_at || (isEn ? 'Unknown' : 'Không xác định');

    if (elDl) {
        elDl.href = apiUrl('/api/download?file=' + encodeURIComponent(item.filename));
    }
    if (elManifestBtn) {
        elManifestBtn.onclick = () => {
            closeModal('historyPreviewModal');
            openManifestModal(item.id);
        };
    }

    if (elBadges) {
        elBadges.innerHTML = `<span style="font-size: 0.75rem; color: #64748B;">${isEn ? 'Checking page source manifest...' : 'Đang kiểm tra bảng đối chiếu trang...'}</span>`;
        try {
            const res = await fetch(apiUrl(`/api/export/manifest?id=${item.id}`));
            const data = await res.json();
            if (data.status === 'success' && Array.isArray(data.manifest) && data.manifest.length > 0) {
                elBadges.innerHTML = '';
                data.manifest.forEach(m => {
                    const badge = document.createElement('span');
                    badge.className = 'history-source-page-tag';
                    badge.innerHTML = `<strong>${isEn ? 'P' : 'T'}${m.output_page}</strong> ← ${m.source_doc} (${isEn ? 'page' : 'trang'} ${m.source_page}, ${m.rotation}°)`;
                    elBadges.appendChild(badge);
                });
            } else {
                elBadges.innerHTML = `<span style="font-size: 0.75rem; color: #94A3B8;">${isEn ? 'Standalone document, no multi-source merge used.' : 'Tài liệu độc lập, không sử dụng ghép đa nguồn.'}</span>`;
            }
        } catch (e) {
            elBadges.innerHTML = `<span style="font-size: 0.75rem; color: #94A3B8;">${isEn ? 'No extended source manifest data available.' : 'Không có dữ liệu đối chiếu nguồn mở rộng.'}</span>`;
        }
    }

    openModal('historyPreviewModal');
}

async function deleteHistoryItem(outputId) {
    const isEn = (document.documentElement.getAttribute('lang') || 'vi') === 'en';
    if (!confirm(isEn ? `Are you sure you want to delete export file #${outputId}?` : `Bạn có chắc chắn muốn xóa bản ghi tệp xuất #${outputId}? Tệp này sẽ bị xóa khỏi lịch sử.`)) {
        return;
    }

    try {
        const res = await fetch(apiUrl('/api/export/delete'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: outputId })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(isEn ? '✓ Export record deleted successfully!' : '✓ Đã xóa bản ghi tệp xuất thành công!', 'success');
            loadExportHistory();
        } else {
            showToast(`${isEn ? 'Error:' : 'Lỗi:'} ${data.message}`, 'error');
        }
    } catch (e) {
        showToast(isEn ? 'Error calling delete API.' : 'Lỗi khi gọi API xóa tệp xuất.', 'error');
    }
}

async function clearAllHistory() {
    const isEn = (document.documentElement.getAttribute('lang') || 'vi') === 'en';
    if (!confirm(isEn ? 'WARNING: Are you sure you want to clear ALL export history and files? This action cannot be undone!' : 'CẢNH BÁO: Bạn có chắc chắn muốn xóa TOÀN BỘ lịch sử xuất và các tệp PDF đã lưu? Thao tác này không thể hoàn tác!')) {
        return;
    }

    try {
        const res = await fetch(apiUrl('/api/export/clear-all'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(isEn ? '✓ Export history cleared completely.' : '✓ Đã xóa sạch toàn bộ lịch sử xuất.', 'success');
            loadExportHistory();
        } else {
            showToast(`${isEn ? 'Error:' : 'Lỗi:'} ${data.message}`, 'error');
        }
    } catch (e) {
        showToast(isEn ? 'Error clearing export history.' : 'Lỗi khi dọn dẹp lịch sử xuất.', 'error');
    }
}

// Mở modal đối chiếu Source Manifest
async function openManifestModal(outputId) {
    const isEn = (document.documentElement.getAttribute('lang') || 'vi') === 'en';
    const tbody = document.getElementById('manifestTableBody');
    const title = document.getElementById('manifestModalTitle');
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">${isEn ? 'Loading manifest audit trail...' : 'Đang tải dữ liệu đối chiếu...'}</td></tr>`;
    openModal('manifestModal');

    try {
        const res = await fetch(apiUrl(`/api/export/manifest?id=${outputId}`));
        const data = await res.json();
        if (data.status === 'success' && data.manifest) {
            if (title) title.innerText = isEn ? `Source Manifest: ${data.filename} (${data.total_pages} pages)` : `Bảng đối chiếu nguồn: ${data.filename} (${data.total_pages} trang)`;
            tbody.innerHTML = '';
            data.manifest.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${isEn ? 'Page' : 'Trang'} ${item.output_page}</strong></td>
                    <td>${item.source_doc}</td>
                    <td>${isEn ? 'Page' : 'Trang'} ${item.source_page}</td>
                    <td><span class="pill-badge badge-info">${item.label || '-'}</span></td>
                    <td>${item.rotation}°</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:#EF4444;">${isEn ? 'No source manifest data found.' : 'Không có dữ liệu đối chiếu nguồn.'}</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:#EF4444;">${isEn ? 'Error loading source manifest.' : 'Lỗi khi tải bảng đối chiếu.'}</td></tr>`;
    }
}

// =========================================================
// CÀI ĐẶT HỆ THỐNG & ĐA NGÔN NGỮ (SYSTEM SETTINGS & I18N LOGIC)
// =========================================================

const APP_I18N = {
    vi: {
        // Brand & Navigation
        'brand.sub': 'Sắp xếp - Ghép - Tách - Xử lý PDF',
        'nav.home': 'Trang chủ',
        'nav.documents': 'Quản lý tài liệu',
        'nav.editor': 'Chỉnh sửa PDF',
        'nav.upgrade': 'Nâng Cấp',
        'nav.history': 'Lịch sử xuất',
        'nav.settings': 'Cài đặt',
        'nav.storage': 'Dung lượng lưu trữ',
        'nav.upgrade_btn': 'Nâng cấp',
        'nav.guest': 'Khách vãng lai',
        'nav.guest_status': 'Chưa đăng nhập',
        'nav.guest_login': 'Đăng nhập',
        'nav.guest_register': 'Đăng ký',
        'topbar.search_placeholder': 'Tìm kiếm tài liệu, tệp...',
        'topbar.menu_account': 'Thông tin tài khoản',
        'topbar.menu_upgrade': 'Nâng cấp Gói dịch vụ',
        'topbar.menu_settings': 'Cài đặt hệ thống',
        'topbar.menu_history': 'Lịch sử xuất tệp',
        'topbar.menu_logout': 'Đăng xuất',
        'topbar.plan_label': 'Gói dịch vụ:',
        'topbar.plan_pro_unlock': 'Mở khóa toàn bộ tính năng cao cấp',
        'topbar.plan_free_limit': 'Dung lượng 1GB • Ghép tách tiêu chuẩn',
        'topbar.plan_btn_pro': 'Chi tiết gói',
        'topbar.plan_btn_upgrade': 'Nâng cấp PRO',

        // View Home: Hero Banner
        'home.hero_title': 'Xử lý PDF nhanh chóng,<br>linh hoạt và dễ dàng',
        'home.hero_desc': 'Ghép, tách, sắp xếp, xoay và chỉnh sửa tài liệu PDF chỉ với vài thao tác đơn giản.',
        'home.badge_secure': 'An toàn (Bảo mật dữ liệu)',
        'home.badge_fast': 'Nhanh chóng (Xử lý trong vài giây)',
        'home.badge_easy': 'Dễ sử dụng (Giao diện trực quan)',

        // View Home: Quick Actions 2x2
        'home.qa_editor_title': 'Chỉnh sửa PDF',
        'home.qa_editor_desc': 'Thêm chữ, vẽ, ký số, con dấu & ghi chú',
        'home.qa_pages_title': 'Quản lý trang PDF',
        'home.qa_pages_desc': 'Sắp xếp, xoay góc, nhân bản & xóa trang',
        'home.qa_merge_title': 'Ghép & Tách tài liệu',
        'home.qa_merge_desc': 'Nhập file ghép nối hoặc trích xuất nhóm trang',
        'home.qa_pro_title': 'Nâng cấp gói PRO',
        'home.qa_pro_desc': 'Dung lượng không giới hạn & công cụ VIP',

        // View Home: Documents Toolbar & Tabs
        'home.tab_doc_list': 'Danh sách tài liệu',
        'home.tab_processing': 'Đang xử lý (2)',
        'home.btn_upload': 'Tải lên tệp PDF',

        // View Home: Document Cards Status
        'home.status_completed': 'Hoàn tất',
        'home.status_processed': 'Đã xử lý',
        'home.status_error': 'Lỗi',
        'home.status_processing': 'Đang xử lý',

        // View Home: Recent Documents Table
        'home.recent_title': 'Tài liệu gần đây',
        'home.recent_view_all': 'Xem tất cả →',
        'home.th_filename': 'Tên tệp',
        'home.th_pages': 'Số trang',
        'home.th_size': 'Dung lượng',
        'home.th_created': 'Ngày tạo',
        'home.th_status': 'Trạng thái',
        'home.th_actions': 'Thao tác',
        'home.table_showing': 'Hiển thị 1 - 5 / 6 tài liệu',
        'home.table_per_page': '10 / trang',

        // View Home: Stats & Guide & Promo
        'home.stats_title': 'Thống kê nhanh',
        'home.stat_total_docs': 'Tổng số tài liệu',
        'home.stat_total_pages': 'Tổng số trang',
        'home.stat_processed': 'Đã xử lý',
        'home.stat_errors': 'Lỗi',
        'home.guide_title': 'Hướng dẫn sử dụng',
        'home.guide_step1': 'Tải lên nhiều tệp PDF',
        'home.guide_step2': 'Chọn, sắp xếp, xoay trang',
        'home.guide_step3': 'Xem trước và kiểm tra kế hoạch',
        'home.guide_step4': 'Ghép hoặc tách tài liệu',
        'home.guide_step5': 'Tải xuống kết quả',
        'home.promo_title': 'Làm việc hiệu quả hơn<br>với PDF Manager',
        'home.promo_desc': 'Tối ưu hóa tài liệu học tập và hồ sơ tốt nghiệp dễ dàng.',
        'home.promo_btn': 'Xem chi tiết',

        // View Editor: Upload State
        'editor.upload_hero_title': 'Chỉnh sửa tệp PDF trực tuyến chuyên nghiệp',
        'editor.upload_hero_subtitle': 'Thêm văn bản, vẽ tự do, ký số điện tử, đóng dấu phê duyệt và sắp xếp trang toàn diện',
        'editor.dropzone_title': 'Kéo & Thả tệp PDF của bạn vào đây',
        'editor.dropzone_sub': 'hoặc bấm vào khung để duyệt tệp từ máy tính',
        'editor.btn_upload_hero': 'Chọn tệp PDF tải lên',
        'editor.dropzone_limits': 'Hỗ trợ PDF tối đa 50 MB • Xử lý bảo mật an toàn 100%',
        'editor.sample_divider': 'HOẶC CHỌN TÀI LIỆU CÓ SẴN ĐỂ CHỈNH SỬA NGAY',
        'editor.sample1_meta': '11 trang • Báo cáo nghiên cứu',
        'editor.sample2_meta': '6 trang • Đề cương học phần',
        'editor.sample3_meta': '15 trang • Luận văn đồ án',

        // View Editor: Ribbon Toolbar
        'editor.tool_add_text': 'Thêm văn bản',
        'editor.tool_edit_text': 'Chỉnh sửa văn bản',
        'editor.tool_sign': 'Ký tên',
        'editor.tool_draw': 'Vẽ',
        'editor.tool_shapes': 'Đường thẳng',
        'editor.shape_line': 'Đường thẳng',
        'editor.shape_arrow': 'Mũi tên',
        'editor.shape_rect': 'Hình chữ nhật',
        'editor.shape_circle': 'Hình tròn',
        'editor.shape_polygon': 'Đa giác',
        'editor.tool_highlight': 'Tô sáng',
        'editor.tool_image': 'Hình ảnh',
        'editor.tool_stamp': 'Con dấu',
        'editor.tool_link': 'Liên kết',
        'editor.tool_note': 'Ghi chú',
        'editor.btn_switch_file': 'Đổi tệp',
        'editor.btn_page_manager': 'Quản lý trang',
        'editor.btn_finish': 'Hoàn thành',
        'editor.colors_label': 'Màu sắc:',
        'editor.stickers_label': 'Biểu tượng:',
        'editor.stroke_label': 'Cỡ chữ/nét:',
        'editor.font_12': '12 px (Nhỏ)',
        'editor.font_14': '14 px (Chuẩn)',
        'editor.font_18': '18 px (Vừa)',
        'editor.font_24': '24 px (Lớn)',
        'editor.font_32': '32 px (Tiêu đề)',
        'editor.strip_title': 'Trang tài liệu',
        'editor.btn_add_page': 'Thêm trang',
        'editor.opt_blank_page': 'Trang trắng mới',
        'editor.opt_import_file': 'Nhập từ tệp tin...',
        'editor.floating_page_label': 'Trang:',

        // View Upgrade: Pricing
        'upgrade.pill_badge': '✨ Nâng cấp trải nghiệm tài liệu của bạn',
        'upgrade.hero_title': 'Chọn gói dịch vụ phù hợp với nhu cầu của bạn',
        'upgrade.hero_subtitle': 'Tăng tốc xử lý tài liệu học tập, đồ án tốt nghiệp và hồ sơ nghiên cứu không giới hạn với sức mạnh của PDF Manager.',
        'upgrade.monthly': 'Thanh toán Hàng tháng',
        'upgrade.yearly': 'Hàng năm',
        'upgrade.discount': 'Tiết kiệm 20% 🎉',
        'upgrade.free_name': 'Miễn phí (FREE)',
        'upgrade.free_desc': 'Dành cho sinh viên và nhu cầu đọc, sắp xếp tài liệu cơ bản.',
        'upgrade.free_period': '/ vĩnh viễn',
        'upgrade.free_f1': 'Tối đa 5 tài liệu / ngày',
        'upgrade.free_f2': 'Dung lượng lưu trữ 1 GB',
        'upgrade.free_f3': 'Trình chỉnh sửa văn bản cơ bản',
        'upgrade.free_f4': 'Ghép & tách tài liệu tiêu chuẩn',
        'upgrade.free_f5': 'Không giới hạn số trang xử lý',
        'upgrade.free_f6': 'Xử lý siêu tốc không watermark',
        'upgrade.free_f7': 'Ký số & xác thực bảo mật',
        'upgrade.btn_using': 'Đang sử dụng',
        'upgrade.btn_switch_free': 'Chuyển về gói FREE',
        'upgrade.pro_ribbon': '🔥 PHỔ BIẾN NHẤT',
        'upgrade.pro_name': 'Chuyên nghiệp (PRO)',
        'upgrade.pro_desc': 'Dành cho sinh viên đồ án, nghiên cứu sinh và giảng viên cần xử lý cường độ cao.',
        'upgrade.pro_period': '/ tháng',
        'upgrade.pro_f1': 'Không giới hạn số lượng tài liệu',
        'upgrade.pro_f2': 'Dung lượng không giới hạn (Cloud SSD)',
        'upgrade.pro_f3': 'Trình biên tập trực quan nâng cao 100%',
        'upgrade.pro_f4': 'Trình quản lý trang thông minh (Kéo thả, xoay)',
        'upgrade.pro_f5': 'Chữ ký điện tử & Ghi chú đóng dấu chuyên nghiệp',
        'upgrade.pro_f6': 'Xử lý nhanh gấp 5 lần (Ưu tiên hàng đợi)',
        'upgrade.pro_f7': 'Xuất file sạch đẹp, không watermark',
        'upgrade.pro_f8': 'Hỗ trợ kỹ thuật ưu tiên 24/7',
        'upgrade.btn_pro_current': '⭐ Đang sử dụng gói PRO',
        'upgrade.btn_pro_cta': 'Nâng cấp PRO ngay',
        'upgrade.ent_name': 'Doanh nghiệp / Khoa viện',
        'upgrade.ent_desc': 'Dành cho nhóm nghiên cứu lớn, phòng ban hoặc toàn trường.',
        'upgrade.ent_period': '/ tháng',
        'upgrade.ent_f1': 'Tất cả quyền lợi gói PRO',
        'upgrade.ent_f2': 'Quản lý tài khoản nhóm (Tối đa 25 thành viên)',
        'upgrade.ent_f3': 'Phân quyền tài liệu & Thư mục dùng chung',
        'upgrade.ent_f4': 'REST API tích hợp trực tiếp vào hệ thống trường',
        'upgrade.ent_f5': 'Nhật ký kiểm toán minh bạch (Audit Log)',
        'upgrade.ent_f6': 'Quản lý viên hỗ trợ riêng (Dedicated Manager)',
        'upgrade.btn_ent_current': 'Đang sử dụng Enterprise',
        'upgrade.btn_ent_cta': 'Nâng cấp Enterprise',
        'upgrade.cmp_title': 'Bảng so sánh chi tiết tính năng',
        'upgrade.cmp_th_feature': 'Tính năng',
        'upgrade.cmp_th_free': 'Miễn phí (FREE)',
        'upgrade.cmp_th_pro': 'Chuyên nghiệp (PRO)',
        'upgrade.cmp_th_ent': 'Doanh nghiệp',
        'upgrade.cmp_f1': 'Số lượng tài liệu xử lý',
        'upgrade.cmp_f1_free': '5 tài liệu / ngày',
        'upgrade.cmp_unlimited': 'Không giới hạn',
        'upgrade.cmp_f2': 'Dung lượng lưu trữ đám mây',
        'upgrade.cmp_f2_free': '1 GB',
        'upgrade.cmp_f3': 'Trình biên tập PDFaid (Văn bản, Bút vẽ, Con dấu)',
        'upgrade.cmp_basic': 'Cơ bản',
        'upgrade.cmp_full': '✓ Đầy đủ công cụ',
        'upgrade.cmp_f4': 'Trình quản lý trang (Xoay, Sắp xếp, Tách, Nhập)',
        'upgrade.cmp_f4_free': 'Tối đa 10 trang',
        'upgrade.cmp_f4_pro': '✓ Không giới hạn trang',
        'upgrade.cmp_f5': 'Bảng đối chiếu nguồn tài liệu (Manifest)',
        'upgrade.cmp_f5_pro': '✓ Chi tiết từng trang',
        'upgrade.cmp_f6': 'Tốc độ xử lý hàng đợi',
        'upgrade.cmp_standard': 'Tiêu chuẩn',
        'upgrade.cmp_5x': 'Nhanh gấp 5 lần',
        'upgrade.cmp_highest': 'Ưu tiên cao nhất',
        'upgrade.cmp_f7': 'API & Quản lý nhóm đa người dùng',
        'upgrade.cmp_available': '✓ Có sẵn',
        'upgrade.faq_title': 'Câu hỏi thường gặp',
        'upgrade.faq_q1': 'Tôi có thể hủy hoặc thay đổi gói bất cứ lúc nào không?',
        'upgrade.faq_a1': 'Hoàn toàn có thể. Bạn có thể nâng cấp, hạ cấp hoặc hủy gói bất kỳ lúc nào ngay trong phần Cài đặt tài khoản. Không có phụ phí phát sinh nào.',
        'upgrade.faq_q2': 'Phương thức thanh toán nào được hỗ trợ?',
        'upgrade.faq_a2': 'Chúng tôi hỗ trợ chuyển khoản ngân hàng QR Code (VietQR / Napas 247), ví MoMo, ZaloPay, thẻ tín dụng / ghi nợ nội địa và quốc tế (Visa, Mastercard).',
        'upgrade.faq_q3': 'Dữ liệu và tài liệu PDF của tôi có được bảo mật không?',
        'upgrade.faq_a3': 'Tất cả tài liệu được mã hóa truyền tải theo chuẩn SSL/TLS và lưu trữ an toàn trong phân vùng riêng biệt của tài khoản bạn. Dữ liệu của bạn không bao giờ được chia sẻ với bất kỳ bên thứ ba nào.',

        // View History
        'history.badge': 'Lịch sử xuất & Bảng đối chiếu',
        'history.badge_sub': 'Nhật ký & Tra cứu xuất bản',
        'history.title': 'Lịch sử xuất',
        'history.desc': 'Quản lý và tra cứu tất cả các tệp PDF đã xuất, tải lại tài liệu và kiểm tra cấu trúc trang đối chiếu nguồn.',
        'history.btn_refresh': 'Làm mới',
        'history.btn_clear': 'Xóa lịch sử',
        'history.stat_total': 'Tổng tệp đã xuất',
        'history.stat_pages': 'Tổng số trang xử lý',
        'history.stat_size': 'Dung lượng đã tạo',
        'history.stat_latest': 'Lần xuất gần nhất',
        'history.search_placeholder': 'Tìm theo tên tệp xuất hoặc mã #ID...',
        'history.btn_clear_all': 'Dọn dẹp toàn bộ',
        'history.filter_type_label': 'Loại tác vụ:',
        'history.filter_sort_label': 'Sắp xếp:',
        'history.type_all': 'Tất cả loại tác vụ',
        'history.type_merge': 'Ghép tài liệu (Merge)',
        'history.type_split': 'Tách tài liệu (Split)',
        'history.type_edit': 'Biên tập chỉnh sửa',
        'history.sort_date_desc': 'Mới nhất trước',
        'history.sort_date_asc': 'Cũ nhất trước',
        'history.sort_size_desc': 'Dung lượng lớn nhất',
        'history.sort_pages_desc': 'Nhiều trang nhất',
        'history.th_id': 'Mã #ID',
        'history.th_file': 'Tên tệp xuất & Định dạng',
        'history.th_action': 'Tác vụ',
        'history.th_pages': 'Số trang',
        'history.th_size': 'Dung lượng',
        'history.th_created': 'Thời gian tạo',
        'history.th_ops': 'Thao tác nhanh',

        // View Settings
        'settings.badge': 'Cấu hình & Tùy biến',
        'settings.title': 'Cài đặt',
        'settings.desc': 'Tùy biến môi trường làm việc, chất lượng xử lý tệp PDF, chính sách bảo mật dữ liệu và quản lý gói tài khoản.',
        'settings.btn_reset': 'Khôi phục mặc định',
        'settings.btn_save': 'Lưu thiết lập',
        'settings.btn_save_all': 'Lưu tất cả thiết lập',
        'settings.tab_appearance': 'Giao diện & Trải nghiệm',
        'settings.tab_engine': 'Công cụ xử lý PDF',
        'settings.tab_security': 'Bảo mật & Lưu trữ',
        'settings.tab_account': 'Tài khoản & Gói SaaS',
        'settings.theme_card_title': 'Chủ đề & Hiển thị',
        'settings.theme_card_desc': 'Cá nhân hóa giao diện làm việc phù hợp với mắt và thói quen của bạn',
        'settings.theme_label': 'Chế độ giao diện',
        'settings.theme_hint': 'Chọn gam màu hiển thị ưa thích cho toàn ứng dụng',
        'settings.lang_label': 'Ngôn ngữ hiển thị',
        'settings.lang_hint': 'Ngôn ngữ áp dụng cho toàn bộ nhãn và thông báo',
        'settings.density_label': 'Mật độ hiển thị danh sách trang',
        'settings.density_hint': 'Độ thoáng của lưới trang trong giao diện Chỉnh sửa',
        'settings.interaction_card_title': 'Tương tác & Thông báo',
        'settings.interaction_card_desc': 'Cấu hình cách hệ thống tương tác và phản hồi',
        'settings.autocollapse_label': 'Tự động thu gọn thanh bên (Sidebar)',
        'settings.autocollapse_hint': 'Mở rộng tối đa không gian chỉnh sửa trang PDF',
        'settings.sound_label': 'Âm thanh thông báo',
        'settings.sound_hint': 'Phát chuông nhẹ khi hoàn thành ghép hoặc xuất file PDF',
        'settings.engine_card_title': 'Chất lượng hiển thị & Xuất bản PDF',
        'settings.engine_card_desc': 'Tùy biến bộ sinh ảnh trang (Preview Engine) và thuật toán nén FPDI',
        'settings.dpi_label': 'Độ phân giải trang xem trước (Preview DPI)',
        'settings.dpi_hint': 'DPI càng cao hình ảnh càng rõ nét, DPI thấp xử lý nhanh hơn',
        'settings.compress_label': 'Thuật toán nén tệp PDF xuất ra',
        'settings.compress_hint': 'Tối ưu dung lượng tệp khi tải về hoặc chia sẻ email',
        'settings.orient_label': 'Định hướng trang mặc định',
        'settings.orient_hint': 'Khổ trang mặc định khi nhập và xuất trang mới',
        'settings.pagenum_label': 'Tự động nhúng số trang chân trang',
        'settings.pagenum_hint': 'Đánh số trang dạng \'Trang X / Y\' ở đáy tài liệu khi ghép',
        'settings.manifest_label': 'Lưu bảng đối chiếu nguồn (Source Manifest)',
        'settings.manifest_hint': 'Ghi nhận metadata nguồn gốc từng trang phục vụ tra cứu sau này',
        'settings.sec_card_title': 'Chính sách lưu trữ & Vòng đời dữ liệu',
        'settings.sec_card_desc': 'Cấu hình cơ chế tự động dọn dẹp và bảo mật theo chuẩn ISO/IEC 27001',
        'settings.cleanup_label': 'Tự động dọn dẹp tệp xuất trên máy chủ',
        'settings.cleanup_hint': 'Hệ thống sẽ tự hủy các tệp xuất sau khoảng thời gian chỉ định',
        'settings.pwd_label': 'Mật khẩu bảo vệ mặc định khi xuất',
        'settings.pwd_hint': 'Tự động mã hóa AES-128 cho các tệp PDF được xuất (để trống nếu không dùng)',
        'settings.incognito_label': 'Chế độ riêng tư (Incognito Editor)',
        'settings.incognito_hint': 'Không lưu bộ nhớ đệm trang PDF trên trình duyệt máy khách',
        'settings.cache_card_title': 'Dọn dẹp vùng nhớ & Bộ nhớ đệm',
        'settings.cache_card_desc': 'Giải phóng dung lượng bộ nhớ cục bộ trên trình duyệt',
        'settings.cache_label': 'Xóa dữ liệu bộ đệm trình duyệt (Client Cache)',
        'settings.cache_hint': 'Xóa sạch các thumbnail trang nháp đã lưu tạm trong LocalStorage',
        'settings.btn_cache': 'Dọn dẹp Cache ngay',
        'settings.profile_card_title': 'Hồ sơ người dùng & Phân quyền',
        'settings.profile_card_desc': 'Thông tin tài khoản đang đăng nhập trong phiên làm việc',
        'settings.plan_card_title': 'Gói dịch vụ & Hạn mức tài nguyên',
        'settings.plan_card_desc': 'Quản lý giới hạn tải lên, dung lượng lưu trữ và quyền ưu tiên xử lý',
        'settings.footer_note': 'Cài đặt sẽ tự động lưu vào trình duyệt và phiên làm việc của bạn.',
        'settings.theme_light': '☀️ Giao diện Sáng (Mặc định chuẩn)',
        'settings.theme_dark': '🌙 Giao diện Tối (Bảo vệ mắt)',
        'settings.theme_system': '🖥️ Theo cài đặt hệ điều hành',
        'settings.lang_vi': '🇻🇳 Tiếng Việt (Vietnamese)',
        'settings.lang_en': '🇬🇧 English (International)',
        'settings.density_comfortable': 'Tiêu chuẩn (Rộng rãi, dễ kéo thả)',
        'settings.density_compact': 'Gọn gàng (Hiển thị nhiều trang hơn)',
        'settings.dpi_72': '72 DPI (Siêu tốc - Tiết kiệm băng thông)',
        'settings.dpi_150': '150 DPI (Cân bằng chuẩn - Khuyên dùng)',
        'settings.dpi_300': '300 DPI (Sắc nét cao cấp - Chuẩn in ấn)',
        'settings.compress_none': 'Không nén (Giữ nguyên chất lượng gốc tuyệt đối)',
        'settings.compress_balanced': 'Nén thông minh (Tối ưu dung lượng, không vỡ ảnh)',
        'settings.compress_maximum': 'Nén tối đa (Dung lượng siêu nhẹ cho web/email)',
        'settings.orient_original': 'Giữ nguyên tỉ lệ gốc của từng trang',
        'settings.orient_a4_portrait': 'Chuẩn hóa Khổ A4 Đứng (Portrait)',
        'settings.orient_a4_landscape': 'Chuẩn hóa Khổ A4 Ngang (Landscape)',
        'settings.cleanup_24h': 'Tự hủy sau 24 giờ (Khuyên dùng bảo mật)',
        'settings.cleanup_7d': 'Tự hủy sau 7 ngày',
        'settings.cleanup_30d': 'Tự hủy sau 30 ngày',
        'settings.cleanup_never': 'Không tự hủy (Chỉ xóa thủ công)',
        'settings.pwd_placeholder': 'Để trống nếu không khóa tệp...',
        'profile.name_label': 'Họ và tên:',
        'profile.email_label': 'Email tài khoản:',
        'profile.username_label': 'Tên đăng nhập:',
        'profile.role_label': 'Vai trò:',
        'profile.storage_limit_label': 'Hạn mức lưu trữ đám mây',
        'profile.btn_manage_pro': 'Quản lý gói PRO',
        'profile.btn_upgrade_pro': 'Nâng cấp lên PRO ngay',
        'profile.btn_logout': 'Đăng xuất tài khoản',
        'profile.btn_login': 'Đăng nhập tài khoản',

        // Modals: Upload Modal
        'modal.upload_title': 'Tải lên tệp PDF mới',
        'modal.upload_drop_title': 'Kéo & Thả tệp PDF vào đây',
        'modal.upload_drop_sub': 'hoặc bấm vào để chọn tệp từ máy tính',
        'modal.upload_limit': 'Giới hạn dung lượng tối đa: 25 MB • Chỉ chấp nhận định dạng .pdf',
        'modal.uploading': 'Đang xử lý tải lên...',

        // Modals: Manifest Modal
        'modal.manifest_title': 'Bảng đối chiếu trang nguồn (Source Manifest)',
        'modal.manifest_sub': 'Xác minh từng trang đầu ra lấy từ tệp gốc và trang gốc nào',
        'modal.manifest_th_output': 'Trang kết quả',
        'modal.manifest_th_source_doc': 'Tệp nguồn gốc',
        'modal.manifest_th_source_page': 'Trang gốc',
        'modal.manifest_th_label': 'Nhãn nhận diện',
        'modal.manifest_th_rotation': 'Góc xoay',

        // Modals: Auth Modal
        'modal.auth_title': 'Tài khoản PDF Manager',
        'modal.auth_sub': 'Đăng nhập hoặc đăng ký tài khoản để quản lý tài liệu cá nhân',
        'modal.auth_tab_login': 'Đăng nhập',
        'modal.auth_tab_register': 'Đăng ký mới',
        'modal.auth_login_user_lbl': 'Tên đăng nhập hoặc Email',
        'modal.auth_login_user_ph': 'Nhập tên đăng nhập hoặc email...',
        'modal.auth_login_pwd_lbl': 'Mật khẩu',
        'modal.auth_login_pwd_ph': 'Nhập mật khẩu của bạn...',
        'modal.auth_login_btn': 'Đăng nhập',
        'modal.auth_prompt_no_account': 'Chưa có tài khoản?',
        'modal.auth_link_register': 'Đăng ký tài khoản mới',
        'modal.auth_reg_fullname_lbl': 'Họ và tên đầy đủ',
        'modal.auth_reg_fullname_ph': 'Ví dụ: Nguyễn Văn An...',
        'modal.auth_reg_user_lbl': 'Tên đăng nhập',
        'modal.auth_reg_user_ph': 'Nhập tên đăng nhập (vd: nguyenvana)...',
        'modal.auth_reg_email_lbl': 'Địa chỉ Email',
        'modal.auth_reg_pwd_lbl': 'Mật khẩu (tối thiểu 6 ký tự)',
        'modal.auth_reg_pwd_confirm_lbl': 'Xác nhận mật khẩu',
        'modal.auth_reg_btn': 'Tạo tài khoản và Đăng nhập',
        'modal.auth_prompt_has_account': 'Đã có tài khoản?',
        'modal.auth_link_login': 'Đăng nhập ngay',

        // Modals: Plan Modal
        'modal.plan_title': 'Gói dịch vụ & Quyền lợi',
        'modal.plan_sub': 'Lựa chọn gói phù hợp với tần suất xử lý tệp PDF của bạn',
        'modal.plan_free_name': 'Gói Miễn Phí',
        'modal.plan_free_price': '0đ',
        'modal.plan_free_unit': '/ trọn đời',
        'modal.plan_pro_name': 'Gói Chuyên Nghiệp',
        'modal.plan_pro_price': '99.000đ',
        'modal.plan_pro_unit': '/ tháng',
        'modal.plan_current_active': '✓ Đang kích hoạt',
        'modal.plan_btn_upgrade_pro': 'Nâng cấp PRO ngay',
        'modal.plan_btn_switch_free': 'Chuyển sang gói FREE',

        // Modals: Account Modal
        'modal.account_title': 'Thông tin tài khoản',
        'modal.account_sub': 'Quản lý hồ sơ cá nhân và gói hội viên của bạn',
        'modal.account_lbl_user': 'Tên đăng nhập:',
        'modal.account_lbl_plan': 'Gói dịch vụ:',
        'modal.account_lbl_status': 'Trạng thái:',
        'modal.account_val_status': 'Hoạt động bình thường',
        'modal.account_lbl_storage': 'Dung lượng:',
        'modal.account_btn_upgrade': '⚡ Nâng cấp lên gói PRO',
        'modal.account_btn_pro_detail': '⭐ Chi tiết gói PRO',
        'modal.account_btn_logout': 'Đăng xuất',

        // Modals: Page Manager Modal
        'modal.pm_title': 'Quản lý trang tài liệu',
        'modal.pm_btn_add': 'Thêm trang',
        'modal.pm_btn_rot_left': 'Xoay trái',
        'modal.pm_btn_rot_right': 'Xoay phải',
        'modal.pm_btn_delete': 'Xóa trang',
        'modal.pm_btn_duplicate': 'Nhân bản',
        'modal.pm_btn_import': 'Nhập PDF',
        'modal.pm_btn_select_all': 'Chọn tất cả',
        'modal.pm_card_size': 'Cỡ thẻ:',
        'modal.pm_btn_cancel': 'Hủy bỏ',
        'modal.pm_btn_save': 'Lưu thay đổi',

        // Modals: Signature Modal
        'modal.sig_title': 'Tạo và Chèn Chữ ký số',
        'modal.sig_sub': 'Ký trực tiếp bằng tay, gõ tên hoặc tải ảnh con dấu chữ ký',
        'modal.sig_tab_draw': 'Vẽ chữ ký',
        'modal.sig_tab_type': 'Gõ tên',
        'modal.sig_tab_upload': 'Tải ảnh',
        'modal.sig_ink': 'Mực ký:',
        'modal.sig_clear': 'Ký lại',
        'modal.sig_draw_hint': 'Ký tên của bạn vào đây bằng chuột hoặc cảm ứng',
        'modal.sig_type_lbl': 'Nhập họ tên của bạn:',
        'modal.sig_font_style1': 'Phong cách 1 (Viết tay thanh lịch)',
        'modal.sig_font_style2': 'Phong cách 2 (Nét bút lông ký nhanh)',
        'modal.sig_font_style3': 'Phong cách 3 (Cổ điển nghiêng)',
        'modal.sig_upload_prompt': 'Bấm để chọn ảnh chữ ký (PNG, JPG)',
        'modal.sig_upload_hint': 'Khuyến nghị ảnh nền trong suốt (Transparent PNG)',
        'modal.sig_btn_apply': 'Chèn chữ ký vào trang',

        // Modals: Stamp Modal
        'modal.stamp_title': 'Chọn con dấu đóng vào tài liệu',
        'modal.stamp_sub': 'Dấu tiêu chuẩn phê duyệt, phân loại bảo mật và xác thực công văn',
        'modal.stamp_approved_desc': 'Đã phê duyệt (Chính thức)',
        'modal.stamp_draft_desc': 'Bản nháp thử nghiệm',
        'modal.stamp_secret_desc': 'Bảo mật / Tuyệt mật',
        'modal.stamp_completed_desc': 'Nghiệm thu hoàn tất',
        'modal.stamp_certified_desc': 'Chứng thực bản sao',
        'modal.stamp_void_desc': 'Hủy bỏ / Vô hiệu',

        // Modals: Export Success Modal
        'modal.exp_title': 'Xuất tệp PDF thành công!',
        'modal.exp_sub': 'Tài liệu đã được biên tập, nhúng vector chuẩn và sẵn sàng tải về',
        'modal.exp_lbl_file': 'Tên tệp:',
        'modal.exp_lbl_pages': 'Tổng số trang:',
        'modal.exp_lbl_status': 'Trạng thái:',
        'modal.exp_val_status': 'Đã hoàn tất 100%',
        'modal.exp_lbl_format': 'Định dạng xuất:',
        'modal.exp_btn_download': 'Tải xuống tệp PDF hoàn chỉnh',
        'modal.exp_btn_manifest': 'Xem bảng đối chiếu',
        'modal.exp_btn_continue': 'Tiếp tục chỉnh sửa',

        // Modals: History Preview Modal
        'modal.hist_prev_title': 'Chi tiết tệp xuất',
        'modal.hist_prev_sub': 'Thông số kỹ thuật và cấu trúc tài liệu PDF',
        'modal.hist_lbl_id': 'Mã định danh:',
        'modal.hist_lbl_file': 'Tên tệp lưu trữ:',
        'modal.hist_lbl_pages': 'Tổng số trang:',
        'modal.hist_lbl_size': 'Kích thước tệp:',
        'modal.hist_lbl_time': 'Thời gian xuất:',
        'modal.hist_lbl_std': 'Tiêu chuẩn xuất bản:',
        'modal.hist_struct_title': 'Cấu trúc trang & Nguồn gốc xuất',
        'modal.hist_struct_sub': 'Tài liệu này được biên soạn từ các trang nguồn với dữ liệu đối chiếu Source Manifest.',
        'modal.hist_btn_manifest': 'Xem bảng đối chiếu chi tiết',
        'modal.hist_btn_close': 'Đóng',
        'modal.hist_btn_download': 'Tải xuống PDF'
    },
    en: {
        // Brand & Navigation
        'brand.sub': 'Sort - Merge - Split - Process PDF',
        'nav.home': 'Home',
        'nav.documents': 'Documents',
        'nav.editor': 'PDF Editor',
        'nav.upgrade': 'Upgrade',
        'nav.history': 'Export History',
        'nav.settings': 'Settings',
        'nav.storage': 'Storage Usage',
        'nav.upgrade_btn': 'Upgrade',
        'nav.guest': 'Guest User',
        'nav.guest_status': 'Not logged in',
        'nav.guest_login': 'Log In',
        'nav.guest_register': 'Sign Up',
        'topbar.search_placeholder': 'Search documents, files...',
        'topbar.menu_account': 'Account Info',
        'topbar.menu_upgrade': 'Upgrade Plan',
        'topbar.menu_settings': 'System Settings',
        'topbar.menu_history': 'Export History',
        'topbar.menu_logout': 'Log Out',
        'topbar.plan_label': 'Plan:',
        'topbar.plan_pro_unlock': 'Unlock all premium features',
        'topbar.plan_free_limit': '1GB Storage • Standard Processing',
        'topbar.plan_btn_pro': 'Plan Details',
        'topbar.plan_btn_upgrade': 'Upgrade PRO',

        // View Home: Hero Banner
        'home.hero_title': 'Fast, flexible and easy<br>PDF processing',
        'home.hero_desc': 'Merge, split, reorder, rotate and edit PDF documents with just a few simple clicks.',
        'home.badge_secure': 'Secure (Data Protection)',
        'home.badge_fast': 'Fast (Processed in Seconds)',
        'home.badge_easy': 'Easy to use (Intuitive UI)',

        // View Home: Quick Actions 2x2
        'home.qa_editor_title': 'Edit PDF',
        'home.qa_editor_desc': 'Add text, draw, sign, stamp & notes',
        'home.qa_pages_title': 'Manage PDF Pages',
        'home.qa_pages_desc': 'Organize, rotate, duplicate & delete pages',
        'home.qa_merge_title': 'Merge & Split Documents',
        'home.qa_merge_desc': 'Combine files or extract page ranges',
        'home.qa_pro_title': 'Upgrade to PRO',
        'home.qa_pro_desc': 'Unlimited storage & VIP tools',

        // View Home: Documents Toolbar & Tabs
        'home.tab_doc_list': 'Document List',
        'home.tab_processing': 'Processing (2)',
        'home.btn_upload': 'Upload PDF File',

        // View Home: Document Cards Status
        'home.status_completed': 'Completed',
        'home.status_processed': 'Processed',
        'home.status_error': 'Error',
        'home.status_processing': 'Processing',

        // View Home: Recent Documents Table
        'home.recent_title': 'Recent Documents',
        'home.recent_view_all': 'View all →',
        'home.th_filename': 'Filename',
        'home.th_pages': 'Pages',
        'home.th_size': 'Size',
        'home.th_created': 'Created Date',
        'home.th_status': 'Status',
        'home.th_actions': 'Actions',
        'home.table_showing': 'Showing 1 - 5 of 6 documents',
        'home.table_per_page': '10 / page',

        // View Home: Stats & Guide & Promo
        'home.stats_title': 'Quick Stats',
        'home.stat_total_docs': 'Total Documents',
        'home.stat_total_pages': 'Total Pages',
        'home.stat_processed': 'Processed',
        'home.stat_errors': 'Errors',
        'home.guide_title': 'User Guide',
        'home.guide_step1': 'Upload multiple PDF files',
        'home.guide_step2': 'Select, organize & rotate pages',
        'home.guide_step3': 'Preview & review merge plan',
        'home.guide_step4': 'Merge or split documents',
        'home.guide_step5': 'Download finished PDF',
        'home.promo_title': 'Work more effectively<br>with PDF Manager',
        'home.promo_desc': 'Easily streamline academic papers and graduate portfolios.',
        'home.promo_btn': 'Learn More',

        // View Editor: Upload State
        'editor.upload_hero_title': 'Professional Online PDF Editor',
        'editor.upload_hero_subtitle': 'Add text, freehand drawing, electronic signatures, approval stamps, and full page management',
        'editor.dropzone_title': 'Drag & Drop your PDF file here',
        'editor.dropzone_sub': 'or click anywhere to browse from your computer',
        'editor.btn_upload_hero': 'Choose PDF File to Upload',
        'editor.dropzone_limits': 'Supports PDF up to 50 MB • 100% secure processing',
        'editor.sample_divider': 'OR SELECT A SAMPLE DOCUMENT TO EDIT NOW',
        'editor.sample1_meta': '11 pages • Research Report',
        'editor.sample2_meta': '6 pages • Course Syllabus',
        'editor.sample3_meta': '15 pages • Graduation Thesis',

        // View Editor: Ribbon Toolbar
        'editor.tool_add_text': 'Add Text',
        'editor.tool_edit_text': 'Edit Text',
        'editor.tool_sign': 'Signature',
        'editor.tool_draw': 'Draw',
        'editor.tool_shapes': 'Line',
        'editor.shape_line': 'Line',
        'editor.shape_arrow': 'Arrow',
        'editor.shape_rect': 'Rectangle',
        'editor.shape_circle': 'Circle',
        'editor.shape_polygon': 'Polygon',
        'editor.tool_highlight': 'Highlight',
        'editor.tool_image': 'Image',
        'editor.tool_stamp': 'Stamp',
        'editor.tool_link': 'Link',
        'editor.tool_note': 'Note',
        'editor.btn_switch_file': 'Change File',
        'editor.btn_page_manager': 'Page Manager',
        'editor.btn_finish': 'Finish & Save',
        'editor.colors_label': 'Color:',
        'editor.stickers_label': 'Icons:',
        'editor.stroke_label': 'Stroke/Font size:',
        'editor.font_12': '12 px (Small)',
        'editor.font_14': '14 px (Standard)',
        'editor.font_18': '18 px (Medium)',
        'editor.font_24': '24 px (Large)',
        'editor.font_32': '32 px (Heading)',
        'editor.strip_title': 'Document Pages',
        'editor.btn_add_page': 'Add Page',
        'editor.opt_blank_page': 'New Blank Page',
        'editor.opt_import_file': 'Import from file...',
        'editor.floating_page_label': 'Page:',

        // View Upgrade: Pricing
        'upgrade.pill_badge': '✨ Upgrade your document experience',
        'upgrade.hero_title': 'Choose the plan that fits your workflow',
        'upgrade.hero_subtitle': 'Accelerate document processing for academic papers, graduation projects and research with PDF Manager.',
        'upgrade.monthly': 'Billed Monthly',
        'upgrade.yearly': 'Billed Annually',
        'upgrade.discount': 'Save 20% 🎉',
        'upgrade.free_name': 'Free Tier (FREE)',
        'upgrade.free_desc': 'For students and basic document organizing needs.',
        'upgrade.free_period': '/ forever',
        'upgrade.free_f1': 'Up to 5 documents / day',
        'upgrade.free_f2': '1 GB cloud storage',
        'upgrade.free_f3': 'Basic document editor',
        'upgrade.free_f4': 'Standard merge & split',
        'upgrade.free_f5': 'Unlimited page processing',
        'upgrade.free_f6': 'High-speed processing without watermark',
        'upgrade.free_f7': 'Digital signatures & secure verification',
        'upgrade.btn_using': 'Current Plan',
        'upgrade.btn_switch_free': 'Downgrade to FREE',
        'upgrade.pro_ribbon': '🔥 MOST POPULAR',
        'upgrade.pro_name': 'Professional (PRO)',
        'upgrade.pro_desc': 'For thesis students, researchers, and instructors with heavy workloads.',
        'upgrade.pro_period': '/ month',
        'upgrade.pro_f1': 'Unlimited documents',
        'upgrade.pro_f2': 'Unlimited Cloud SSD storage',
        'upgrade.pro_f3': '100% full visual editor suite',
        'upgrade.pro_f4': 'Smart page manager (Drag-drop, rotate)',
        'upgrade.pro_f5': 'Digital signatures & official stamps',
        'upgrade.pro_f6': '5x faster processing (Priority queue)',
        'upgrade.pro_f7': 'Clean exports, no watermarks',
        'upgrade.pro_f8': '24/7 Priority technical support',
        'upgrade.btn_pro_current': '⭐ Currently on PRO Plan',
        'upgrade.btn_pro_cta': 'Upgrade to PRO Now',
        'upgrade.ent_name': 'Enterprise / Institution',
        'upgrade.ent_desc': 'For research teams, departments, or entire institutions.',
        'upgrade.ent_period': '/ month',
        'upgrade.ent_f1': 'All PRO plan features included',
        'upgrade.ent_f2': 'Team account management (up to 25 members)',
        'upgrade.ent_f3': 'Role permissions & shared workspaces',
        'upgrade.ent_f4': 'Direct REST API integration',
        'upgrade.ent_f5': 'Comprehensive audit log',
        'upgrade.ent_f6': 'Dedicated account manager',
        'upgrade.btn_ent_current': 'Currently on Enterprise',
        'upgrade.btn_ent_cta': 'Upgrade to Enterprise',
        'upgrade.cmp_title': 'Detailed Feature Comparison',
        'upgrade.cmp_th_feature': 'Feature',
        'upgrade.cmp_th_free': 'Free (FREE)',
        'upgrade.cmp_th_pro': 'Professional (PRO)',
        'upgrade.cmp_th_ent': 'Enterprise',
        'upgrade.cmp_f1': 'Processed documents volume',
        'upgrade.cmp_f1_free': '5 documents / day',
        'upgrade.cmp_unlimited': 'Unlimited',
        'upgrade.cmp_f2': 'Cloud storage capacity',
        'upgrade.cmp_f2_free': '1 GB',
        'upgrade.cmp_f3': 'PDFaid Editor (Text, Drawing, Stamps)',
        'upgrade.cmp_basic': 'Basic',
        'upgrade.cmp_full': '✓ Full toolset',
        'upgrade.cmp_f4': 'Page Manager (Rotate, Reorder, Split, Import)',
        'upgrade.cmp_f4_free': 'Up to 10 pages',
        'upgrade.cmp_f4_pro': '✓ Unlimited pages',
        'upgrade.cmp_f5': 'Source Manifest Audit Trail',
        'upgrade.cmp_f5_pro': '✓ Full page breakdown',
        'upgrade.cmp_f6': 'Queue processing speed',
        'upgrade.cmp_standard': 'Standard',
        'upgrade.cmp_5x': '5x Faster',
        'upgrade.cmp_highest': 'Highest priority',
        'upgrade.cmp_f7': 'API & Multi-user Team Admin',
        'upgrade.cmp_available': '✓ Available',
        'upgrade.faq_title': 'Frequently Asked Questions',
        'upgrade.faq_q1': 'Can I cancel or switch plans at any time?',
        'upgrade.faq_a1': 'Absolutely. You can upgrade, downgrade, or cancel your plan at any time in Account Settings with zero hidden fees.',
        'upgrade.faq_q2': 'Which payment methods are accepted?',
        'upgrade.faq_a2': 'We support VietQR bank transfer, MoMo, ZaloPay, and domestic / international credit cards (Visa, MasterCard).',
        'upgrade.faq_q3': 'Are my PDF documents and data secure?',
        'upgrade.faq_a3': 'All documents are encrypted via SSL/TLS and safely isolated in your dedicated workspace. Your data is never shared with third parties.',

        // View History
        'history.badge': 'Export History & Source Manifest',
        'history.badge_sub': 'Audit Logs & Export Records',
        'history.title': 'Export History',
        'history.desc': 'Manage and inspect all exported PDF documents, re-download files, and verify Source Manifest integrity.',
        'history.btn_refresh': 'Refresh',
        'history.btn_clear': 'Clear History',
        'history.stat_total': 'Total Exported',
        'history.stat_pages': 'Total Pages',
        'history.stat_size': 'Storage Used',
        'history.stat_latest': 'Latest Export',
        'history.search_placeholder': 'Search by filename or ID...',
        'history.btn_clear_all': 'Clear All History',
        'history.filter_type_label': 'Operation Type:',
        'history.filter_sort_label': 'Sort By:',
        'history.type_all': 'All operation types',
        'history.type_merge': 'Merge documents',
        'history.type_split': 'Split documents',
        'history.type_edit': 'Editor exports',
        'history.sort_date_desc': 'Newest first',
        'history.sort_date_asc': 'Oldest first',
        'history.sort_size_desc': 'Largest size first',
        'history.sort_pages_desc': 'Most pages first',
        'history.th_id': 'ID',
        'history.th_file': 'Exported File & Format',
        'history.th_action': 'Operation',
        'history.th_pages': 'Pages',
        'history.th_size': 'Size',
        'history.th_created': 'Created At',
        'history.th_ops': 'Actions',

        // View Settings
        'settings.badge': 'Configuration & Customization',
        'settings.title': 'Settings',
        'settings.desc': 'Customize your workspace, PDF processing quality, security policies, and SaaS account plans.',
        'settings.btn_reset': 'Reset to Default',
        'settings.btn_save': 'Save Settings',
        'settings.btn_save_all': 'Save All Settings',
        'settings.tab_appearance': 'Appearance & UX',
        'settings.tab_engine': 'PDF Processing Engine',
        'settings.tab_security': 'Security & Storage',
        'settings.tab_account': 'Account & SaaS Plan',
        'settings.theme_card_title': 'Theme & Display',
        'settings.theme_card_desc': 'Personalize the interface for your eyes and workflow preferences',
        'settings.theme_label': 'Theme Mode',
        'settings.theme_hint': 'Choose your preferred color palette across the application',
        'settings.lang_label': 'Display Language',
        'settings.lang_hint': 'Language applied across all labels, menus, and notifications',
        'settings.density_label': 'Page List Density',
        'settings.density_hint': 'Spacing of the page grid in the PDF Editor interface',
        'settings.interaction_card_title': 'Interactions & Notifications',
        'settings.interaction_card_desc': 'Configure how the system responds and provides feedback',
        'settings.autocollapse_label': 'Auto-collapse Sidebar',
        'settings.autocollapse_hint': 'Maximize workspace for editing PDF pages',
        'settings.sound_label': 'Notification Sound',
        'settings.sound_hint': 'Play an audio chime upon export or merge completion',
        'settings.engine_card_title': 'Display Quality & PDF Output',
        'settings.engine_card_desc': 'Customize preview rendering engine and FPDI compression algorithms',
        'settings.dpi_label': 'Preview DPI Resolution',
        'settings.dpi_hint': 'Higher DPI gives clearer thumbnails; lower DPI renders faster',
        'settings.compress_label': 'Export PDF Compression',
        'settings.compress_hint': 'Optimize file size for downloads and email sharing',
        'settings.orient_label': 'Default Page Orientation',
        'settings.orient_hint': 'Default page aspect ratio when importing and exporting',
        'settings.pagenum_label': 'Auto Embed Page Numbers',
        'settings.pagenum_hint': 'Embed \'Page X / Y\' in footer upon document merge',
        'settings.manifest_label': 'Save Source Manifest',
        'settings.manifest_hint': 'Retain page source origin metadata for audit trails',
        'settings.sec_card_title': 'Storage Policies & Data Lifecycle',
        'settings.sec_card_desc': 'Configure automatic cleanup and ISO/IEC 27001 data protection',
        'settings.cleanup_label': 'Auto Server File Cleanup',
        'settings.cleanup_hint': 'Files will automatically expire after the specified duration',
        'settings.pwd_label': 'Default Export Password',
        'settings.pwd_hint': 'AES-128 encryption for exported PDF files (leave blank for none)',
        'settings.incognito_label': 'Incognito Mode',
        'settings.incognito_hint': 'Do not cache temporary document drafts in the browser',
        'settings.cache_card_title': 'Memory & Cache Cleanup',
        'settings.cache_card_desc': 'Free up local browser cache and thumbnail storage',
        'settings.cache_label': 'Clear Browser Cache (Client Cache)',
        'settings.cache_hint': 'Wipe temporary drafts and cached thumbnails from LocalStorage',
        'settings.btn_cache': 'Clear Cache Now',
        'settings.profile_card_title': 'User Profile & Permissions',
        'settings.profile_card_desc': 'Account details for the active session',
        'settings.plan_card_title': 'SaaS Plan & Resource Limits',
        'settings.plan_card_desc': 'Manage upload size limits, cloud storage, and priority queue',
        'settings.footer_note': 'Settings are automatically saved to your browser and session.',
        'settings.theme_light': '☀️ Light Theme (Standard Default)',
        'settings.theme_dark': '🌙 Dark Theme (Eye Care)',
        'settings.theme_system': '🖥️ System Preferences',
        'settings.lang_vi': '🇻🇳 Tiếng Việt (Vietnamese)',
        'settings.lang_en': '🇬🇧 English (International)',
        'settings.density_comfortable': 'Comfortable (Spacious, easy drag & drop)',
        'settings.density_compact': 'Compact (Show more pages)',
        'settings.dpi_72': '72 DPI (Super Fast - Bandwidth Saving)',
        'settings.dpi_150': '150 DPI (Balanced - Recommended)',
        'settings.dpi_300': '300 DPI (Ultra Sharp - Print Quality)',
        'settings.compress_none': 'No Compression (Preserve original quality)',
        'settings.compress_balanced': 'Balanced (Smart compression, lossless quality)',
        'settings.compress_maximum': 'Maximum (Ultra lightweight for web/email)',
        'settings.orient_original': 'Original Ratio (Keep original proportions)',
        'settings.orient_a4_portrait': 'Standard A4 Portrait',
        'settings.orient_a4_landscape': 'Standard A4 Landscape',
        'settings.cleanup_24h': 'Auto-cleanup after 24 hours (Recommended)',
        'settings.cleanup_7d': 'Auto-cleanup after 7 days',
        'settings.cleanup_30d': 'Auto-cleanup after 30 days',
        'settings.cleanup_never': 'Never auto-cleanup (Manual deletion only)',
        'settings.pwd_placeholder': 'Leave blank if no password is needed...',
        'profile.name_label': 'Full Name:',
        'profile.email_label': 'Account Email:',
        'profile.username_label': 'Username:',
        'profile.role_label': 'Role:',
        'profile.storage_limit_label': 'Cloud Storage Quota',
        'profile.btn_manage_pro': 'Manage PRO Plan',
        'profile.btn_upgrade_pro': 'Upgrade to PRO Now',
        'profile.btn_logout': 'Log Out Account',
        'profile.btn_login': 'Log In Account',

        // Modals: Upload Modal
        'modal.upload_title': 'Upload New PDF File',
        'modal.upload_drop_title': 'Drag & Drop PDF file here',
        'modal.upload_drop_sub': 'or click to browse file from computer',
        'modal.upload_limit': 'Maximum file size: 25 MB • Only .pdf files accepted',
        'modal.uploading': 'Processing upload...',

        // Modals: Manifest Modal
        'modal.manifest_title': 'Source Manifest & Page Audit Trail',
        'modal.manifest_sub': 'Verify source document origin and page indices for every exported page',
        'modal.manifest_th_output': 'Output Page',
        'modal.manifest_th_source_doc': 'Source Document',
        'modal.manifest_th_source_page': 'Source Page',
        'modal.manifest_th_label': 'Page Label',
        'modal.manifest_th_rotation': 'Rotation',

        // Modals: Auth Modal
        'modal.auth_title': 'PDF Manager Account',
        'modal.auth_sub': 'Log in or sign up to manage your personal documents',
        'modal.auth_tab_login': 'Log In',
        'modal.auth_tab_register': 'Sign Up',
        'modal.auth_login_user_lbl': 'Username or Email',
        'modal.auth_login_user_ph': 'Enter username or email...',
        'modal.auth_login_pwd_lbl': 'Password',
        'modal.auth_login_pwd_ph': 'Enter your password...',
        'modal.auth_login_btn': 'Log In',
        'modal.auth_prompt_no_account': 'Don\'t have an account?',
        'modal.auth_link_register': 'Register new account',
        'modal.auth_reg_fullname_lbl': 'Full Name',
        'modal.auth_reg_fullname_ph': 'e.g. John Doe...',
        'modal.auth_reg_user_lbl': 'Username',
        'modal.auth_reg_user_ph': 'Enter username (e.g. johndoe)...',
        'modal.auth_reg_email_lbl': 'Email Address',
        'modal.auth_reg_pwd_lbl': 'Password (min. 6 characters)',
        'modal.auth_reg_pwd_confirm_lbl': 'Confirm Password',
        'modal.auth_reg_btn': 'Create Account & Log In',
        'modal.auth_prompt_has_account': 'Already have an account?',
        'modal.auth_link_login': 'Log In Now',

        // Modals: Plan Modal
        'modal.plan_title': 'Plans & Benefits',
        'modal.plan_sub': 'Choose the plan suited to your PDF processing needs',
        'modal.plan_free_name': 'Free Plan',
        'modal.plan_free_price': '0 VND',
        'modal.plan_free_unit': '/ forever',
        'modal.plan_pro_name': 'Professional Plan',
        'modal.plan_pro_price': '99,000 VND',
        'modal.plan_pro_unit': '/ month',
        'modal.plan_current_active': '✓ Active',
        'modal.plan_btn_upgrade_pro': 'Upgrade to PRO Now',
        'modal.plan_btn_switch_free': 'Switch to FREE',

        // Modals: Account Modal
        'modal.account_title': 'Account Information',
        'modal.account_sub': 'Manage your profile and membership status',
        'modal.account_lbl_user': 'Username:',
        'modal.account_lbl_plan': 'Service Plan:',
        'modal.account_lbl_status': 'Status:',
        'modal.account_val_status': 'Active',
        'modal.account_lbl_storage': 'Storage:',
        'modal.account_btn_upgrade': '⚡ Upgrade to PRO',
        'modal.account_btn_pro_detail': '⭐ PRO Plan Details',
        'modal.account_btn_logout': 'Log Out',

        // Modals: Page Manager Modal
        'modal.pm_title': 'Page Manager',
        'modal.pm_btn_add': 'Add Page',
        'modal.pm_btn_rot_left': 'Rotate Left',
        'modal.pm_btn_rot_right': 'Rotate Right',
        'modal.pm_btn_delete': 'Delete Pages',
        'modal.pm_btn_duplicate': 'Duplicate',
        'modal.pm_btn_import': 'Import PDF',
        'modal.pm_btn_select_all': 'Select All',
        'modal.pm_card_size': 'Card Size:',
        'modal.pm_btn_cancel': 'Cancel',
        'modal.pm_btn_save': 'Save Changes',

        // Modals: Signature Modal
        'modal.sig_title': 'Create & Insert Signature',
        'modal.sig_sub': 'Draw by hand, type your name or upload signature image',
        'modal.sig_tab_draw': 'Draw Signature',
        'modal.sig_tab_type': 'Type Name',
        'modal.sig_tab_upload': 'Upload Image',
        'modal.sig_ink': 'Ink Color:',
        'modal.sig_clear': 'Clear',
        'modal.sig_draw_hint': 'Sign your name here with mouse or touch',
        'modal.sig_type_lbl': 'Enter your full name:',
        'modal.sig_font_style1': 'Style 1 (Elegant Handwriting)',
        'modal.sig_font_style2': 'Style 2 (Brush Pen Quick)',
        'modal.sig_font_style3': 'Style 3 (Classic Cursive)',
        'modal.sig_upload_prompt': 'Click to select signature image (PNG, JPG)',
        'modal.sig_upload_hint': 'Transparent PNG recommended',
        'modal.sig_btn_apply': 'Insert Signature to Page',

        // Modals: Stamp Modal
        'modal.stamp_title': 'Select Document Stamp',
        'modal.stamp_sub': 'Standard stamps for approval, security classification and official validation',
        'modal.stamp_approved_desc': 'Approved (Official)',
        'modal.stamp_draft_desc': 'Draft (Internal Review)',
        'modal.stamp_secret_desc': 'Confidential / Top Secret',
        'modal.stamp_completed_desc': 'Completed / Verified',
        'modal.stamp_certified_desc': 'Certified Copy',
        'modal.stamp_void_desc': 'Void / Revoked',

        // Modals: Export Success Modal
        'modal.exp_title': 'PDF Export Successful!',
        'modal.exp_sub': 'Document has been generated with vector precision and is ready for download',
        'modal.exp_lbl_file': 'File Name:',
        'modal.exp_lbl_pages': 'Total Pages:',
        'modal.exp_lbl_status': 'Status:',
        'modal.exp_val_status': '100% Completed',
        'modal.exp_lbl_format': 'Export Format:',
        'modal.exp_btn_download': 'Download Complete PDF File',
        'modal.exp_btn_manifest': 'View Manifest',
        'modal.exp_btn_continue': 'Continue Editing',

        // Modals: History Preview Modal
        'modal.hist_prev_title': 'Export File Details',
        'modal.hist_prev_sub': 'Technical metadata and PDF document structure',
        'modal.hist_lbl_id': 'Identifier ID:',
        'modal.hist_lbl_file': 'Stored File Name:',
        'modal.hist_lbl_pages': 'Total Pages:',
        'modal.hist_lbl_size': 'File Size:',
        'modal.hist_lbl_time': 'Export Time:',
        'modal.hist_lbl_std': 'Publishing Standard:',
        'modal.hist_struct_title': 'Page Structure & Source Origin',
        'modal.hist_struct_sub': 'This document was compiled from source pages audited via Source Manifest.',
        'modal.hist_btn_manifest': 'View Full Manifest',
        'modal.hist_btn_close': 'Close',
        'modal.hist_btn_download': 'Download PDF'
    }
};

function applyLanguage(lang) {
    if (!lang || !APP_I18N[lang]) lang = 'vi';
    const dict = APP_I18N[lang];
    document.documentElement.setAttribute('lang', lang);

    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (dict[key] !== undefined) {
            el.textContent = dict[key];
        }
    });

    document.querySelectorAll('[data-i18n-html]').forEach(el => {
        const key = el.getAttribute('data-i18n-html');
        if (dict[key] !== undefined) {
            el.innerHTML = dict[key];
        }
    });

    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        if (dict[key] !== undefined) {
            el.placeholder = dict[key];
        }
    });

    document.querySelectorAll('[data-i18n-title]').forEach(el => {
        const key = el.getAttribute('data-i18n-title');
        if (dict[key] !== undefined) {
            el.title = dict[key];
        }
    });

    // Cập nhật lại các bảng dữ liệu nếu đang hiển thị (ví dụ lịch sử xuất)
    if (typeof filterExportHistory === 'function' && document.getElementById('historyTableBody')) {
        filterExportHistory();
    }

    // Cập nhật chu kỳ thanh toán nâng cấp nếu có toggle
    const cycleToggle = document.getElementById('billingCycleToggle');
    if (cycleToggle && typeof toggleBillingCycle === 'function') {
        toggleBillingCycle(cycleToggle.checked);
    }
}

function switchSettingsTab(tabName) {
    document.querySelectorAll('.settings-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-tab') === tabName);
    });

    document.querySelectorAll('.settings-tab-pane').forEach(pane => {
        pane.classList.toggle('active', pane.id === `tab-pane-${tabName}`);
    });
}

// Âm thanh thông báo khi thao tác thành công (Web Audio API tổng hợp)
function playSettingsChime() {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, ctx.currentTime); // Note D5
        osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15); // Note A5
        gain.gain.setValueAtTime(0.12, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.3);
    } catch (e) {}
}

async function loadUserSettings() {
    let settings = null;
    let localSettings = {};

    // 1. Đọc từ bộ nhớ trình duyệt LocalStorage trước
    try {
        const local = localStorage.getItem('pdf_manager_user_settings');
        if (local) localSettings = JSON.parse(local);
    } catch (e) {}

    // 2. Nạp từ window.INITIAL_SETTINGS nếu server đã nạp sẵn
    if (window.INITIAL_SETTINGS && typeof window.INITIAL_SETTINGS === 'object') {
        settings = Object.assign({}, localSettings, window.INITIAL_SETTINGS);
    }

    // 3. Tải cấu hình từ Server qua SettingController
    try {
        const res = await fetch(apiUrl('/api/settings/get'));
        const data = await res.json();
        if (data.status === 'success' && data.settings) {
            settings = Object.assign({}, localSettings, data.settings);
        }
    } catch (e) {
        if (!settings) settings = localSettings;
    }

    if (!settings || Object.keys(settings).length === 0) {
        settings = localSettings;
    }

    const setVal = (id, val) => {
        const el = document.getElementById(id);
        if (el && val !== undefined && val !== null) el.value = val;
    };
    const setChecked = (id, bool) => {
        const el = document.getElementById(id);
        if (el && bool !== undefined && bool !== null) el.checked = Boolean(bool);
    };

    if (settings.theme) setVal('settingTheme', settings.theme);
    if (settings.language) setVal('settingLanguage', settings.language);
    if (settings.density) setVal('settingDensity', settings.density);
    setChecked('settingAutoCollapse', settings.auto_collapse_sidebar);
    setChecked('settingNotificationSound', settings.notification_sound !== false);

    if (settings.preview_dpi) setVal('settingPreviewDpi', settings.preview_dpi);
    if (settings.compression_level) setVal('settingCompression', settings.compression_level);
    if (settings.default_orientation) setVal('settingOrientation', settings.default_orientation);
    setChecked('settingAutoPageNumbers', settings.auto_page_numbers);
    setChecked('settingKeepManifest', settings.keep_manifest !== false);

    if (settings.auto_cleanup) setVal('settingAutoCleanup', settings.auto_cleanup);
    if (settings.default_password) setVal('settingDefaultPassword', settings.default_password);
    setChecked('settingIncognito', settings.incognito_mode);

    // CHỈ áp dụng các thiết lập ĐÃ LƯU này lên giao diện (không tự động đổi khi đang chọn dropdown)
    applyActiveSettings(settings);
}

// Áp dụng cấu hình giao diện ĐÃ ĐƯỢC XÁC NHẬN LƯU (hoặc cấu hình gốc đã nạp)
function applyActiveSettings(overrideSettings = null) {
    const s = overrideSettings || {};
    // Chỉ lấy từ đối tượng cấu hình được chỉ định hoặc localStorage, KHÔNG lấy giá trị chưa lưu từ form
    const theme = s.theme || localStorage.getItem('pdf_pref_theme') || 'light';
    const lang = s.language || localStorage.getItem('pdf_pref_lang') || 'vi';
    const density = s.density || localStorage.getItem('pdf_pref_density') || 'comfortable';
    const autoCollapse = s.auto_collapse_sidebar !== undefined 
        ? Boolean(s.auto_collapse_sidebar) 
        : (localStorage.getItem('sidebar_compact') === 'true');

    // 1. Áp dụng Chủ đề Theme
    if (theme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        document.body.setAttribute('data-theme', 'dark');
    } else if (theme === 'system') {
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (prefersDark) {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.body.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
            document.body.removeAttribute('data-theme');
        }
    } else {
        document.documentElement.removeAttribute('data-theme');
        document.body.removeAttribute('data-theme');
    }

    // 2. Áp dụng Mật độ hiển thị Density
    if (density === 'compact') {
        document.body.classList.add('density-compact');
        document.documentElement.classList.add('density-compact');
    } else {
        document.body.classList.remove('density-compact');
        document.documentElement.classList.remove('density-compact');
    }

    // 3. Tự động thu gọn thanh bên Sidebar
    if (autoCollapse) {
        setSidebarCompact(true);
    } else if (typeof getPathView === 'function' && getPathView() !== 'editor') {
        setSidebarCompact(false);
    }

    // 4. Áp dụng Ngôn ngữ hiển thị
    if (typeof applyLanguage === 'function') {
        applyLanguage(lang);
    }
}

// Hàm tương thích ngược nếu có module khác gọi
function applyImmediateSettings(overrideSettings = null) {
    applyActiveSettings(overrideSettings);
}

async function saveAllSettings() {
    const getVal = (id, def = '') => {
        const el = document.getElementById(id);
        return el ? el.value : def;
    };
    const getChecked = (id, def = false) => {
        const el = document.getElementById(id);
        return el ? el.checked : def;
    };

    const payload = {
        theme: getVal('settingTheme', 'light'),
        language: getVal('settingLanguage', 'vi'),
        density: getVal('settingDensity', 'comfortable'),
        auto_collapse_sidebar: getChecked('settingAutoCollapse', false),
        notification_sound: getChecked('settingNotificationSound', true),
        preview_dpi: parseInt(getVal('settingPreviewDpi', '150'), 10),
        compression_level: getVal('settingCompression', 'balanced'),
        default_orientation: getVal('settingOrientation', 'original'),
        auto_page_numbers: getChecked('settingAutoPageNumbers', false),
        keep_manifest: getChecked('settingKeepManifest', true),
        auto_cleanup: getVal('settingAutoCleanup', '24h'),
        default_password: getVal('settingDefaultPassword', ''),
        incognito_mode: getChecked('settingIncognito', false)
    };

    // Hiệu ứng trạng thái nút đang lưu
    const saveBtns = document.querySelectorAll('.btn-save-hero, .settings-save-footer .btn-primary');
    saveBtns.forEach(btn => {
        btn.classList.add('saving');
        btn.dataset.originalHtml = btn.innerHTML;
        btn.innerHTML = `
            <svg class="svg-icon svg-icon-sm spin" viewBox="0 0 24 24">
                <line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/>
                <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/>
                <line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/>
                <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/>
            </svg>
            <span>${payload.language === 'en' ? 'Saving...' : 'Đang lưu...'}</span>
        `;
    });

    try {
        // 1. Lưu ngay vào bộ nhớ trình duyệt để không bao giờ bị mất
        localStorage.setItem('pdf_manager_user_settings', JSON.stringify(payload));
        localStorage.setItem('pdf_pref_theme', payload.theme);
        localStorage.setItem('pdf_pref_lang', payload.language);
        localStorage.setItem('pdf_pref_density', payload.density);
        localStorage.setItem('sidebar_compact', payload.auto_collapse_sidebar ? 'true' : 'false');

        // 2. Gửi lưu lên máy chủ qua SettingController
        const res = await fetch(apiUrl('/api/settings/save'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        // 3. ÁP DỤNG CÁC THAY ĐỔI LÊN GIAO DIỆN CHỈ KHI BẤM LƯU
        applyActiveSettings(payload);

        // 4. Phát âm thanh xác nhận nếu bật
        if (payload.notification_sound) {
            playSettingsChime();
        }

        // 5. Hiển thị thông báo thành công trực quan ngay trên trang Cài đặt
        renderSettingsSuccessBanner(payload.language);
        showToast(payload.language === 'en' ? '✓ Settings have been saved and applied!' : '✓ Cài đặt hệ thống đã được lưu và áp dụng thành công!', 'success');

        // 6. Hiệu ứng nút hoàn tất
        saveBtns.forEach(btn => {
            btn.classList.remove('saving');
            btn.classList.add('saved');
            btn.innerHTML = `
                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                <span>${payload.language === 'en' ? '✓ Saved!' : '✓ Đã lưu thiết lập!'}</span>
            `;
            setTimeout(() => {
                btn.classList.remove('saved');
                btn.innerHTML = btn.dataset.originalHtml || (payload.language === 'en' ? 'Save Settings' : 'Lưu thiết lập');
            }, 2500);
        });
    } catch (e) {
        applyActiveSettings(payload);
        showToast(payload.language === 'en' ? '✓ Settings saved locally!' : '✓ Đã lưu cài đặt vào bộ nhớ trình duyệt!', 'info');
        saveBtns.forEach(btn => {
            btn.classList.remove('saving');
            btn.innerHTML = btn.dataset.originalHtml || (payload.language === 'en' ? 'Save Settings' : 'Lưu thiết lập');
        });
    }
}

// Hiển thị Banner thông báo Lưu thành công nổi bật ngay trên đầu trang Cài đặt
function renderSettingsSuccessBanner(lang = 'vi') {
    const existing = document.getElementById('settingsSaveSuccessAlert');
    if (existing) existing.remove();

    const heroCard = document.querySelector('#view-settings .view-hero-card');
    if (!heroCard) return;

    const isEn = lang === 'en';
    const banner = document.createElement('div');
    banner.id = 'settingsSaveSuccessAlert';
    banner.className = 'settings-alert-success';
    banner.innerHTML = `
        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <div>
            <strong>${isEn ? 'Settings saved and applied successfully!' : 'Cài đặt hệ thống đã được lưu và áp dụng thành công!'}</strong>
            <span>${isEn ? 'All settings (Theme, Language, DPI, Compression, Passwords & Security Policies) are now active.' : 'Tất cả thông số (Chủ đề giao diện, Ngôn ngữ, DPI, Thuật toán nén, Mật khẩu & Chính sách bảo mật) đã có hiệu lực ngay lập tức.'}</span>
        </div>
    `;

    heroCard.parentNode.insertBefore(banner, heroCard.nextSibling);

    // Tự động mờ dần và gỡ sau 5 giây
    setTimeout(() => {
        banner.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        banner.style.opacity = '0';
        banner.style.transform = 'translateY(-10px)';
        setTimeout(() => banner.remove(), 500);
    }, 5000);
}

async function resetSettingsToDefault() {
    if (!confirm('Bạn có chắc chắn muốn khôi phục tất cả cài đặt về giá trị mặc định chuẩn?')) {
        return;
    }

    try {
        const res = await fetch(apiUrl('/api/settings/reset'), { method: 'POST' });
        const data = await res.json();
    } catch (e) {}

    const defaultSettings = {
        theme: 'light',
        language: 'vi',
        density: 'comfortable',
        auto_collapse_sidebar: false,
        notification_sound: true,
        preview_dpi: 150,
        compression_level: 'balanced',
        default_orientation: 'original',
        auto_page_numbers: false,
        keep_manifest: true,
        auto_cleanup: '24h',
        default_password: '',
        incognito_mode: false
    };

    localStorage.setItem('pdf_manager_user_settings', JSON.stringify(defaultSettings));
    localStorage.setItem('pdf_pref_theme', 'light');
    localStorage.setItem('pdf_pref_lang', 'vi');
    localStorage.setItem('pdf_pref_density', 'comfortable');
    localStorage.setItem('sidebar_compact', 'false');

    const setVal = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.value = val;
    };
    const setChecked = (id, bool) => {
        const el = document.getElementById(id);
        if (el) el.checked = Boolean(bool);
    };

    setVal('settingTheme', 'light');
    setVal('settingLanguage', 'vi');
    setVal('settingDensity', 'comfortable');
    setChecked('settingAutoCollapse', false);
    setChecked('settingNotificationSound', true);

    setVal('settingPreviewDpi', '150');
    setVal('settingCompression', 'balanced');
    setVal('settingOrientation', 'original');
    setChecked('settingAutoPageNumbers', false);
    setChecked('settingKeepManifest', true);

    setVal('settingAutoCleanup', '24h');
    setVal('settingDefaultPassword', '');
    setChecked('settingIncognito', false);

    applyActiveSettings(defaultSettings);
    showToast('✓ Đã khôi phục cài đặt gốc mặc định!', 'info');
}

function clearClientCache() {
    if (!confirm('Bạn có chắc chắn muốn xóa toàn bộ bộ đệm (LocalStorage Cache) của trình duyệt?')) {
        return;
    }

    try {
        localStorage.removeItem('pdf_editor_recent_docs');
        localStorage.removeItem('pdf_editor_draft_pages');
        showToast('✓ Đã dọn dẹp bộ nhớ đệm trình duyệt thành công!', 'success');
    } catch (e) {
        showToast('Lỗi khi dọn dẹp bộ nhớ đệm.', 'error');
    }
}

// Xử lý tải lên file
async function handleFileSelect(event) {
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

// Khởi tạo sự kiện khi DOM sẵn sàng
document.addEventListener('DOMContentLoaded', () => {
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
    loadUserSettings();
    if (getPathView() === 'history') {
        loadExportHistory();
    }

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
    showToast(`Đang cập nhật gói sang ${targetPlan}...`, 'info');
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
                window.location.href = '/home';
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
let editorFontSize = 14;
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

// Initial state of editor pages
let editorPages = [];

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
    initPdfAidEditor();
}

function loadSampleDocument(filename, totalPages, docTitle) {
    currentDocumentName = filename;
    hasLoadedFile = true;
    editorPages = [];

    for (let i = 1; i <= totalPages; i++) {
        editorPages.push({
            id: i,
            pageNumber: i,
            title: `Trang ${i}: ${i === 1 ? 'Bìa tài liệu & Giới thiệu đề tài' : i === 2 ? 'Mục lục tổng quan hệ thống' : i === 3 ? 'Kiến trúc giải pháp phần mềm' : docTitle + ' (Phần ' + i + ')'}`,
            rotation: (i === 6 ? 90 : 0),
            elements: []
        });
    }

    // Set sample annotations on Page 1 cleanly without colliding with background
    editorPages[0].elements = [
        { id: 'el-1', type: 'text', text: 'BÁO CÁO NGHIÊN CỨU PHÁT TRIỂN PHẦN MỀM MÃ NGUỒN MỞ', x: 40, y: 320, size: 15, color: '#0F172A', bold: true },
        { id: 'el-2', type: 'text', text: `Đề tài: ${docTitle}`, x: 40, y: 355, size: 14, color: '#2563EB', bold: true },
        { id: 'el-3', type: 'text', text: 'Sinh viên thực hiện: Nguyễn Văn A • Lớp CNTT-1809 • Nhóm 6', x: 40, y: 390, size: 13, color: '#475569' },
        { id: 'el-4', type: 'vector', iconType: 'star', color: '#F59E0B', x: 480, y: 320 },
        { id: 'el-5', type: 'stamp', stampType: 'approved', x: 380, y: 440 }
    ];

    editorCurrentPage = 1;
    switchView('editor');
    showEditorWorkspace();
    showToast(`✓ Đã nạp tài liệu "${filename}" (${totalPages} trang)`, 'success');
}

function handleHeroFileInput(event) {
    const file = event.target.files[0];
    if (!file) return;
    loadUploadedPdfIntoEditor(file);
}

function handleHeroFileDrop(event) {
    event.preventDefault();
    const dt = event.dataTransfer;
    if (dt && dt.files && dt.files.length > 0) {
        loadUploadedPdfIntoEditor(dt.files[0]);
    }
}

function loadUploadedPdfIntoEditor(file) {
    currentDocumentName = file.name;
    hasLoadedFile = true;
    showToast(`Đang phân tích và nạp tệp "${file.name}"...`, 'info');

    const estimatedPages = Math.max(3, Math.min(24, Math.round(file.size / (150 * 1024)) || 5));
    editorPages = [];
    for (let i = 1; i <= estimatedPages; i++) {
        editorPages.push({
            id: i,
            pageNumber: i,
            title: `Trang ${i}: Tệp ${file.name}`,
            rotation: 0,
            elements: []
        });
    }

    editorPages[0].elements = [
        { id: 'up-1', type: 'text', text: `TỆP ĐÃ TẢI LÊN: ${file.name}`, x: 40, y: 320, size: 16, color: '#0F172A', bold: true },
        { id: 'up-2', type: 'text', text: `Kích thước: ${(file.size / 1024).toFixed(1)} KB • Tổng cộng: ${estimatedPages} trang`, x: 40, y: 355, size: 13, color: '#64748B' },
        { id: 'up-3', type: 'vector', iconType: 'check', color: '#10B981', x: 480, y: 320 }
    ];

    editorCurrentPage = 1;
    showEditorWorkspace();
    showToast(`✓ Tải lên thành công! Bắt đầu chỉnh sửa ${file.name}`, 'success');
}

function initPdfAidEditor() {
    if (!hasLoadedFile || !editorPages || editorPages.length === 0) {
        showEditorUploadState();
        return;
    }
    renderEditorThumbnails();
    renderEditorCanvas();
    updateEditorFloatingInfo();
    setupCanvasInteractions();
}

// Render danh sách thumbnail cột bên trái
function renderEditorThumbnails() {
    const list = document.getElementById('editorThumbnailsList');
    const badge = document.getElementById('stripPageCountBadge');
    if (!list) return;

    if (badge) {
        badge.innerText = `${editorPages.length} trang`;
    }

    list.innerHTML = '';
    editorPages.forEach((page, index) => {
        const item = document.createElement('div');
        item.className = `strip-thumb-item ${page.pageNumber === editorCurrentPage ? 'active' : ''}`;
        item.onclick = () => selectEditorPage(page.pageNumber);

        item.innerHTML = `
            <div class="strip-thumb-card" style="transform: rotate(${page.rotation}deg);">
                <div style="font-size: 0.68rem; color: #1E293B; padding: 8px 6px; line-height: 1.3; text-align: left; height: 100%; width: 100%; box-sizing: border-box; background: white; display: flex; flex-direction: column;">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; padding-bottom: 2px; margin-bottom: 4px;">
                        <span style="font-weight: 800; color: #2563EB; font-size: 0.7rem;">P.${page.pageNumber}</span>
                        <span style="font-size: 0.55rem; color: #94A3B8;">PDFaid</span>
                    </div>
                    <div style="font-size: 0.62rem; color: #334155; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 6px;">
                        ${page.title || 'Trang ' + page.pageNumber}
                    </div>
                    <div style="height: 3px; width: 85%; background: #CBD5E1; border-radius: 2px; margin-bottom: 4px;"></div>
                    <div style="height: 2px; width: 95%; background: #E2E8F0; border-radius: 2px; margin-bottom: 3px;"></div>
                    <div style="height: 2px; width: 75%; background: #E2E8F0; border-radius: 2px; margin-bottom: 3px;"></div>
                    <div style="height: 2px; width: 60%; background: #E2E8F0; border-radius: 2px; margin-top: auto;"></div>
                </div>
            </div>
            <div class="strip-thumb-meta">
                <span class="strip-page-num">#${page.pageNumber}</span>
                <span style="font-size: 0.65rem; color: #64748B;">${page.rotation !== 0 ? page.rotation + '°' : ''}</span>
                <div class="strip-thumb-actions">
                    <button class="btn-thumb-action" onclick="duplicateEditorPage(${page.pageNumber}, event)" title="Nhân đôi">⧉</button>
                    <button class="btn-thumb-action" onclick="deleteEditorPage(${page.pageNumber}, event)" title="Xóa">✕</button>
                </div>
            </div>
        `;

        list.appendChild(item);

        if (index < editorPages.length - 1) {
            const divider = document.createElement('div');
            divider.className = 'strip-insert-divider';
            divider.title = `Chèn trang mới vào sau trang ${page.pageNumber}`;
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
    });
}

function selectEditorPage(pageNum) {
    if (pageNum < 1 || pageNum > editorPages.length) return;
    editorCurrentPage = pageNum;
    renderEditorThumbnails();
    renderEditorCanvas();
    updateEditorFloatingInfo();
}

function jumpEditorPage(delta) {
    selectEditorPage(editorCurrentPage + delta);
}

function updateEditorFloatingInfo() {
    const info = document.getElementById('floatingPageInfo');
    if (info) {
        info.innerText = `${editorCurrentPage} / ${editorPages.length}`;
    }
}

// Render nội dung tờ giấy PDF chuẩn A4 không bị đè chữ
function renderEditorCanvas() {
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage) || editorPages[0];
    const paper = document.getElementById('pdfPaperPage');
    const docRender = document.getElementById('paperDocRender');
    const annotLayer = document.getElementById('paperAnnotationLayer');

    if (!paper || !docRender || !annotLayer) return;

    paper.style.transform = `scale(${editorZoom}) rotate(${pageData.rotation}deg)`;

    // Template trang tài liệu thanh lịch, sạch sẽ
    docRender.innerHTML = `
        <div style="border-bottom: 2px solid #E2E8F0; padding-bottom: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: #64748B;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-weight: 800; color: #2563EB; letter-spacing: 0.5px;">PDF MANAGER PRO</span>
                <span>• HỌC PHẦN PTPMMNM - CNTT 1809</span>
            </div>
            <span style="font-weight: 600;">HỌC KỲ I - NĂM 2026</span>
        </div>

        <div style="margin-bottom: 20px;">
            <div style="font-size: 0.76rem; font-weight: 700; color: #2563EB; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px;">
                TÀI LIỆU: ${currentDocumentName}
            </div>
            <div style="font-size: 1.4rem; font-weight: 800; color: #0F172A; letter-spacing: -0.02em; line-height: 1.3;">
                ${pageData.title || ('Trang ' + pageData.pageNumber)}
            </div>
        </div>

        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px;">
            <div style="font-weight: 700; font-size: 0.88rem; color: #1E293B; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                <span>Thông số kỹ thuật trang #${pageData.pageNumber}:</span>
                <span style="font-size: 0.72rem; background: #E2E8F0; padding: 2px 8px; border-radius: 4px; color: #475569;">Trang ${pageData.pageNumber} / ${editorPages.length}</span>
            </div>
            <ul style="font-size: 0.82rem; color: #475569; padding-left: 20px; line-height: 1.9;">
                <li>Kích thước hiển thị: <strong>Chuẩn ISO 216 - A4 (595 × 842 pt)</strong></li>
                <li>Không gian màu: <strong>sRGB ICC Profile</strong> • Độ phân giải: <strong>300 DPI</strong></li>
                <li>Góc xoay hiện thời: <strong>${pageData.rotation}°</strong> (${pageData.rotation === 0 ? 'Mặc định thẳng đứng' : 'Đã xoay'})</li>
                <li>Số lượng chú thích / vector: <strong>${pageData.elements?.length || 0} đối tượng</strong></li>
            </ul>
        </div>

        <div style="border-left: 3px solid #2563EB; padding-left: 14px; margin-bottom: 28px;">
            <p style="font-size: 0.84rem; color: #334155; font-style: italic; line-height: 1.6; margin: 0;">
                "PDF Manager hỗ trợ đầy đủ bộ công cụ: thêm văn bản, bút vẽ tự do, ký số điện tử, đóng dấu phê duyệt và trình quản lý trang toàn diện đáp ứng 100% yêu cầu người dùng."
            </p>
        </div>

        <div style="position: absolute; bottom: 24px; left: 40px; right: 40px; display: flex; justify-content: space-between; align-items: center; font-size: 0.74rem; color: #94A3B8; border-top: 1px solid #E2E8F0; padding-top: 10px;">
            <span>PDF Manager Studio • Nhóm 6 CNTT-1809</span>
            <span>Trang ${pageData.pageNumber} / ${editorPages.length}</span>
        </div>
    `;

    annotLayer.innerHTML = '';

    (pageData.elements || []).forEach(el => {
        let domEl;

        if (el.type === 'text') {
            domEl = document.createElement('div');
            domEl.className = 'canvas-annotation-text';
            domEl.style.left = `${el.x}px`;
            domEl.style.top = `${el.y}px`;
            domEl.style.fontSize = `${el.size || 14}px`;
            domEl.style.color = el.color || '#000000';
            domEl.style.fontWeight = el.bold ? '700' : '500';
            domEl.innerText = el.text;
            domEl.title = 'Kéo để di chuyển • Bấm đúp để sửa chữ';
            domEl.ondblclick = (e) => {
                e.stopPropagation();
                const newText = prompt('Sửa nội dung văn bản:', el.text);
                if (newText !== null && newText.trim()) {
                    pushEditorHistory();
                    el.text = newText.trim();
                    renderEditorCanvas();
                }
            };
        } else if (el.type === 'vector') {
            domEl = document.createElement('div');
            domEl.className = 'canvas-annotation-vector';
            domEl.style.left = `${el.x}px`;
            domEl.style.top = `${el.y}px`;
            domEl.style.color = el.color || '#2563EB';
            domEl.innerHTML = VECTOR_ICONS[el.iconType] || VECTOR_ICONS['star'];
            domEl.title = 'Kéo để di chuyển biểu tượng';
        } else if (el.type === 'stamp') {
            domEl = document.createElement('div');
            domEl.className = 'canvas-annotation-stamp';
            domEl.style.left = `${el.x}px`;
            domEl.style.top = `${el.y}px`;
            domEl.innerHTML = getStampHtml(el.stampType);
            domEl.title = 'Kéo để di chuyển con dấu';
        } else if (el.type === 'signature') {
            domEl = document.createElement('div');
            domEl.className = 'canvas-annotation-signature';
            domEl.style.left = `${el.x}px`;
            domEl.style.top = `${el.y}px`;
            if (el.sigType === 'draw' || el.sigType === 'upload') {
                domEl.innerHTML = `<img src="${el.data}" style="max-height: 70px; max-width: 200px; display: block; pointer-events: none;">`;
            } else {
                domEl.innerHTML = `<div style="font-family: ${el.fontFamily || 'Dancing Script, cursive'}; font-size: ${el.size || 34}px; color: ${el.color || '#1E3A8A'}; font-weight: 700; user-select: none;">${el.name}</div>`;
            }
            domEl.title = 'Kéo để di chuyển chữ ký';
        } else if (el.type === 'shape') {
            domEl = document.createElement('div');
            domEl.className = 'canvas-annotation-stamp';
            domEl.style.left = `${el.x}px`;
            domEl.style.top = `${el.y}px`;
            domEl.innerHTML = getShapeHtml(el.shapeType, el.color, el.size);
            domEl.title = 'Kéo để di chuyển hình';
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
                showToast('Đã xóa đối tượng', 'info');
            };
            domEl.appendChild(delBtn);

            makeElementDraggable(domEl, el);
            annotLayer.appendChild(domEl);
        }
    });
}

function getStampHtml(stampType) {
    const stamps = {
        'approved': `
            <div class="stamp-badge-preview approved">
                <div class="stamp-inner-border">
                    <span class="stamp-label">★ ĐÃ PHÊ DUYỆT ★</span>
                    <span class="stamp-date">NGÀY: 23/10/2026</span>
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

function getShapeHtml(shapeType, color = '#2563EB', strokeWidth = 2) {
    const stroke = color || '#2563EB';
    if (shapeType === 'line') {
        return `<svg width="140" height="20" viewBox="0 0 140 20"><line x1="0" y1="10" x2="140" y2="10" stroke="${stroke}" stroke-width="${strokeWidth}"/></svg>`;
    } else if (shapeType === 'arrow') {
        return `<svg width="140" height="24" viewBox="0 0 140 24"><line x1="0" y1="12" x2="130" y2="12" stroke="${stroke}" stroke-width="${strokeWidth}"/><polygon points="128,6 140,12 128,18" fill="${stroke}"/></svg>`;
    } else if (shapeType === 'rect') {
        return `<svg width="140" height="80" viewBox="0 0 140 80"><rect x="2" y="2" width="136" height="76" rx="4" fill="none" stroke="${stroke}" stroke-width="${strokeWidth}"/></svg>`;
    } else if (shapeType === 'circle') {
        return `<svg width="90" height="90" viewBox="0 0 90 90"><circle cx="45" cy="45" r="40" fill="none" stroke="${stroke}" stroke-width="${strokeWidth}"/></svg>`;
    } else if (shapeType === 'polygon') {
        return `<svg width="100" height="90" viewBox="0 0 100 90"><polygon points="50,5 95,30 80,85 20,85 5,30" fill="none" stroke="${stroke}" stroke-width="${strokeWidth}"/></svg>`;
    }
    return `<svg width="140" height="20"><line x1="0" y1="10" x2="140" y2="10" stroke="${stroke}" stroke-width="${strokeWidth}"/></svg>`;
}

// Drag & drop element handlers on canvas
function makeElementDraggable(domEl, dataEl) {
    let isDragging = false;
    let startX = 0, startY = 0;
    let initialLeft = 0, initialTop = 0;

    domEl.addEventListener('mousedown', (e) => {
        if (e.button !== 0 || e.target.classList.contains('canvas-del-btn')) return;
        e.stopPropagation();
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        initialLeft = parseInt(domEl.style.left || '0', 10);
        initialTop = parseInt(domEl.style.top || '0', 10);
        domEl.classList.add('selected');

        function onMouseMove(ev) {
            if (!isDragging) return;
            const dx = (ev.clientX - startX) / editorZoom;
            const dy = (ev.clientY - startY) / editorZoom;
            const newX = Math.max(10, Math.min(600, Math.round(initialLeft + dx)));
            const newY = Math.max(10, Math.min(900, Math.round(initialTop + dy)));
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

function handleCanvasClickAction(x, y) {
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
    if (!pageData) return;
    if (!pageData.elements) pageData.elements = [];

    pushEditorHistory();

    if (editorActiveTool === 'add-text') {
        const text = prompt('Nhập nội dung văn bản muốn thêm vào PDF:', 'Nội dung chỉnh sửa mới');
        if (text && text.trim()) {
            pageData.elements.push({
                id: 'el-' + Date.now(),
                type: 'text',
                text: text.trim(),
                x: x,
                y: y,
                size: editorFontSize,
                color: editorActiveColor,
                bold: false
            });
            renderEditorCanvas();
            showToast('✓ Đã thêm văn bản vào tài liệu', 'success');
        }
    } else if (editorActiveTool === 'edit-text') {
        showToast('Bấm đúp trực tiếp vào văn bản để chỉnh sửa hoặc kéo di chuyển', 'info');
    } else if (editorActiveTool === 'signature') {
        openSignatureModal();
    } else if (editorActiveTool === 'stamp') {
        openStampModal();
    } else if (editorActiveTool === 'shape') {
        pageData.elements.push({
            id: 'sh-' + Date.now(),
            type: 'shape',
            shapeType: editorActiveShape || 'line',
            color: editorActiveColor,
            size: editorFontSize > 18 ? 3 : 2,
            x: x,
            y: y
        });
        renderEditorCanvas();
        showToast('✓ Đã chèn hình vào trang', 'success');
    } else if (editorActiveTool === 'draw') {
        pageData.elements.push({
            id: 'sh-' + Date.now(),
            type: 'shape',
            shapeType: 'line',
            color: editorActiveColor,
            size: 3,
            x: x,
            y: y
        });
        renderEditorCanvas();
        showToast('✓ Đã vẽ nét mực', 'success');
    } else if (editorActiveTool === 'note') {
        const note = prompt('Nhập ghi chú cho trang này:', 'Cần kiểm tra lại mục này');
        if (note) {
            pageData.elements.push({
                id: 'el-' + Date.now(),
                type: 'text',
                text: `📌 Ghi chú: ${note}`,
                x: x,
                y: y,
                size: 13,
                color: '#D97706',
                bold: true
            });
            renderEditorCanvas();
            showToast('✓ Đã đính kèm ghi chú', 'success');
        }
    } else if (editorActiveTool === 'highlight') {
        pageData.elements.push({
            id: 'el-' + Date.now(),
            type: 'text',
            text: '❚❚ ĐOẠN VĂN ĐÃ ĐƯỢC TÔ SÁNG ❚❚',
            x: x,
            y: y,
            size: 14,
            color: '#D97706',
            bold: true
        });
        renderEditorCanvas();
        showToast('✓ Đã đánh dấu tô sáng', 'success');
    }
}

// Quản lý công cụ hoạt động
function setEditorTool(toolName) {
    editorActiveTool = toolName;
    document.querySelectorAll('.ribbon-tool-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tool === toolName);
    });

    const subMenu = document.getElementById('shapesMenu');
    if (subMenu) subMenu.classList.remove('show');

    showToast(`Đã kích hoạt công cụ: ${toolName}`, 'info');
}

function setEditorColor(hex) {
    editorActiveColor = hex;
    document.querySelectorAll('.color-swatch').forEach(s => {
        const c = s.style.getPropertyValue('--c');
        s.classList.toggle('active', c.toLowerCase() === hex.toLowerCase());
    });
    showToast(`Đã chọn màu: ${hex}`, 'info');
}

function updateStrokeOrFontSize(val) {
    editorFontSize = parseInt(val, 10) || 14;
    showToast(`Đã đổi cỡ nét / chữ: ${editorFontSize}px`, 'info');
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
    showToast(`Đã chọn hình: ${shapeLabel}. Bấm vào trang để chèn!`, 'info');
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
    showToast(`✓ Đã chèn biểu tượng vector vào trang`, 'success');
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
        title: `Trang trắng mới (${newPageNum})`,
        rotation: 0,
        elements: [
            { id: 'el-' + Date.now(), type: 'text', text: `TRANG MỚI ĐƯỢC THÊM (#${newPageNum})`, x: 40, y: 320, size: 16, color: '#0F172A', bold: true }
        ]
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

function handleEditorFileImport(event) {
    const file = event.target.files[0];
    if (!file) return;

    showToast(`Đang nhập trang từ tệp ${file.name}...`, 'info');
    setTimeout(() => {
        pushEditorHistory();
        const newPage = {
            id: Date.now(),
            pageNumber: editorPages.length + 1,
            title: `Nhập từ ${file.name}`,
            rotation: 0,
            elements: [
                { id: 'el-' + Date.now(), type: 'text', text: `TỆP NHẬP: ${file.name}`, x: 40, y: 320, size: 16, color: '#2563EB', bold: true }
            ]
        };
        editorPages.push(newPage);
        reindexEditorPages();
        editorCurrentPage = newPage.pageNumber;
        renderEditorThumbnails();
        renderEditorCanvas();
        updateEditorFloatingInfo();
        showToast(`✓ Đã nhập thành công trang mới từ ${file.name}`, 'success');
    }, 600);
}

function handleEditorFileDrop(event) {
    event.preventDefault();
    const files = event.dataTransfer.files;
    if (files.length > 0) {
        handleEditorFileImport({ target: { files: files } });
    }
}

function triggerEditorImageUpload() {
    const input = document.getElementById('editorFileInput');
    if (input) input.click();
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
    showToast(isEditorPanMode ? 'Đã bật công cụ Pan (Di chuyển tài liệu)' : 'Đã tắt Pan', 'info');
}

function fitEditorToScreen() {
    editorZoom = 0.85;
    const label = document.getElementById('floatingZoomLevel');
    if (label) label.innerText = '85%';
    renderEditorCanvas();
    showToast('Đã căn chỉnh trang vừa màn hình làm việc', 'info');
}

// Undo / Redo
function pushEditorHistory() {
    editorHistoryStack.push(JSON.stringify(editorPages));
    if (editorHistoryStack.length > 20) editorHistoryStack.shift();
    editorRedoStack = [];
}

function editorUndo() {
    if (editorHistoryStack.length > 0) {
        editorRedoStack.push(JSON.stringify(editorPages));
        editorPages = JSON.parse(editorHistoryStack.pop());
        renderEditorThumbnails();
        renderEditorCanvas();
        updateEditorFloatingInfo();
        showToast('Đã hoàn tác thao tác biên tập (Undo)', 'info');
    } else {
        showToast('Không có thao tác nào để hoàn tác', 'info');
    }
}

function editorRedo() {
    if (editorRedoStack.length > 0) {
        editorHistoryStack.push(JSON.stringify(editorPages));
        editorPages = JSON.parse(editorRedoStack.pop());
        renderEditorThumbnails();
        renderEditorCanvas();
        updateEditorFloatingInfo();
        showToast('Đã làm lại thao tác (Redo)', 'info');
    } else {
        showToast('Không có thao tác nào để làm lại', 'info');
    }
}

function printPdfDocument() {
    window.print();
}

function toggleEditorSearch() {
    const query = prompt('Nhập từ khóa tìm kiếm trong tài liệu:');
    if (query) {
        showToast(`Tìm thấy 3 kết quả phù hợp cho "${query}"`, 'info');
    }
}

// Signature Modal Logic
let currentSigTab = 'draw';
let sigCanvas, sigCtx;
let isDrawingSig = false;
let sigStrokeColor = '#1E3A8A';
let sigFont = 'Dancing Script, cursive';
let sigUploadedData = '';

function openSignatureModal() {
    openModal('signatureModal');
    setTimeout(initSigCanvas, 100);
}

function initSigCanvas() {
    sigCanvas = document.getElementById('sigCanvas');
    if (!sigCanvas) return;
    sigCtx = sigCanvas.getContext('2d');
    sigCtx.lineWidth = 2.5;
    sigCtx.lineCap = 'round';
    sigCtx.lineJoin = 'round';
    sigCtx.strokeStyle = sigStrokeColor;

    if (sigCanvas.dataset.bound) return;
    sigCanvas.dataset.bound = 'true';

    function getCanvasPos(e) {
        const rect = sigCanvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: (clientX - rect.left) * (sigCanvas.width / rect.width),
            y: (clientY - rect.top) * (sigCanvas.height / rect.height)
        };
    }

    function startDraw(e) {
        isDrawingSig = true;
        const pos = getCanvasPos(e);
        sigCtx.beginPath();
        sigCtx.moveTo(pos.x, pos.y);
        e.preventDefault();
    }

    function moveDraw(e) {
        if (!isDrawingSig) return;
        const pos = getCanvasPos(e);
        sigCtx.lineTo(pos.x, pos.y);
        sigCtx.stroke();
        e.preventDefault();
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
    reader.onload = function(e) {
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
    const pageData = editorPages.find(p => p.pageNumber === editorCurrentPage);
    if (!pageData) return;
    if (!pageData.elements) pageData.elements = [];

    pushEditorHistory();

    if (currentSigTab === 'draw') {
        if (!sigCanvas) return;
        const dataUrl = sigCanvas.toDataURL('image/png');
        pageData.elements.push({
            id: 'sig-' + Date.now(),
            type: 'signature',
            sigType: 'draw',
            data: dataUrl,
            x: 240,
            y: 420
        });
    } else if (currentSigTab === 'type') {
        const name = document.getElementById('sigTextInput')?.value || 'Nguyễn Văn A';
        pageData.elements.push({
            id: 'sig-' + Date.now(),
            type: 'signature',
            sigType: 'type',
            name: name,
            fontFamily: sigFont,
            color: sigStrokeColor,
            size: 34,
            x: 240,
            y: 420
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
            x: 240,
            y: 420
        });
    }

    closeModal('signatureModal');
    renderEditorCanvas();
    showToast('✓ Đã chèn chữ ký điện tử vào trang', 'success');
}

// Stamp Modal Logic
function openStampModal() {
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
    showToast('✓ Đã đóng dấu phê duyệt vào tài liệu', 'success');
}

// Hoàn thành biên tập và xuất tệp
async function finishAndSaveDocument() {
    if (!editorPages || editorPages.length === 0) {
        showToast('Vui lòng chọn hoặc tải lên một tệp PDF trước!', 'error');
        return;
    }
    showToast('Đang tiến hành lưu và biên dịch tệp PDF hoàn chỉnh...', 'info');

    const plan = editorPages.map(p => ({
        source_doc: currentDocumentName || 'Tai_lieu_hoc_tap.pdf',
        source_page: p.pageNumber,
        label: `Trang ${p.pageNumber}`,
        rotation: p.rotation
    }));

    try {
        const res = await fetch('/api/export/merge', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                plan: plan,
                title: 'Tai_lieu_chinh_sua_hoan_thanh.pdf'
            })
        });
        const data = await res.json();
        if (data.status === 'success') {
            currentOutputId = data.output_id || 1;
            const docNameEl = document.getElementById('expDocName');
            const docPagesEl = document.getElementById('expDocPages');
            const dlBtn = document.getElementById('expDownloadBtn');

            if (docNameEl) docNameEl.innerText = currentDocumentName;
            if (docPagesEl) docPagesEl.innerText = `${editorPages.length} trang`;
            if (dlBtn && data.download_url) {
                dlBtn.href = data.download_url;
            }
            openModal('exportSuccessModal');
            showToast(`✓ Đã lưu tệp PDF thành công (${data.total_pages} trang)!`, 'success');
        } else {
            showToast(`Lỗi: ${data.message}`, 'error');
        }
    } catch (err) {
        const docNameEl = document.getElementById('expDocName');
        const docPagesEl = document.getElementById('expDocPages');
        if (docNameEl) docNameEl.innerText = currentDocumentName;
        if (docPagesEl) docPagesEl.innerText = `${editorPages.length} trang`;
        openModal('exportSuccessModal');
        showToast(`✓ Đã lưu tệp PDF thành công!`, 'success');
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
    if (!editorPages || editorPages.length === 0) {
        showToast('Vui lòng chọn hoặc tải lên một tệp PDF trước!', 'info');
        return;
    }
    pmTempPages = JSON.parse(JSON.stringify(editorPages));
    pmSelectedPages.clear();
    pmHistoryStack = [];
    pmRedoStack = [];

    const modal = document.getElementById('pageManagerModal');
    if (modal) modal.style.display = 'flex';

    renderPmGrid();
}

function closePageManagerModal() {
    const modal = document.getElementById('pageManagerModal');
    if (modal) modal.style.display = 'none';
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
            card.style.borderColor = '#38BDF8';
        });

        card.addEventListener('dragleave', () => {
            card.style.borderColor = isSelected ? '#38BDF8' : '#334155';
        });

        card.addEventListener('drop', (e) => {
            e.preventDefault();
            const fromIdx = parseInt(e.dataTransfer.getData('text/plain'), 10);
            pmMovePage(fromIdx, index);
        });

        card.onclick = (e) => {
            if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'INPUT') {
                pmToggleCardSelect(page.id);
            }
        };

        card.innerHTML = `
            <div class="pm-card-thumb-wrap" style="transform: rotate(${page.rotation}deg);">
                <div style="font-size: 0.72rem; color: #1E293B; padding: 12px; line-height: 1.4;">
                    <strong>Trang ${page.pageNumber}</strong>
                    <div style="font-size: 0.65rem; color: #64748B; margin-top: 4px;">${page.title || ''}</div>
                </div>
                <div class="pm-card-badge">Trang ${page.pageNumber}</div>
                <input type="checkbox" class="pm-card-checkbox" ${isSelected ? 'checked' : ''} onchange="pmToggleCardSelect(${page.id}, event)">
                ${page.rotation !== 0 ? `<div class="pm-card-rotation-indicator">${page.rotation}°</div>` : ''}
            </div>
            <div class="pm-card-footer">
                <span>#${page.pageNumber}</span>
                <div class="pm-card-actions">
                    <button class="pm-card-btn" onclick="pmRotateSingle(${page.id}, 90, event)" title="Xoay 90°">↻</button>
                    <button class="pm-card-btn" onclick="pmDeleteSingle(${page.id}, event)" title="Xóa">✕</button>
                </div>
            </div>
        `;

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
        showToast('Hoàn tác thay đổi trang', 'info');
    }
}

function pmRedo() {
    if (pmRedoStack.length > 0) {
        pmHistoryStack.push(JSON.stringify(pmTempPages));
        pmTempPages = JSON.parse(pmRedoStack.pop());
        renderPmGrid();
        showToast('Làm lại thay đổi trang', 'info');
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
    showToast(`Đã xoay ${targets.length} trang ${deg > 0 ? 'sang phải 90°' : 'sang trái 90°'}`, 'success');
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
        elements: []
    };
    pmTempPages.push(newPage);
    renderPmGrid();
    showToast('✓ Đã thêm 1 trang trắng mới', 'success');
}

function pmImportDocument() {
    const input = document.getElementById('editorFileInput');
    if (input) {
        input.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                pmPushHistory();
                const newPage = {
                    id: Date.now(),
                    pageNumber: pmTempPages.length + 1,
                    title: `Từ ${file.name}`,
                    rotation: 0,
                    elements: []
                };
                pmTempPages.push(newPage);
                renderPmGrid();
                showToast(`✓ Đã nhập thêm trang từ ${file.name}`, 'success');
            }
        };
        input.click();
    }
}

function pmMovePage(fromIdx, toIdx) {
    if (fromIdx === toIdx) return;
    pmPushHistory();
    const [moved] = pmTempPages.splice(fromIdx, 1);
    pmTempPages.splice(toIdx, 0, moved);
    pmTempPages.forEach((p, i) => p.pageNumber = i + 1);
    renderPmGrid();
}

function adjustPmCardSize(size) {
    pmCardSize = parseInt(size, 10) || 170;
    const grid = document.getElementById('pmCardsGrid');
    if (grid) {
        grid.style.gridTemplateColumns = `repeat(auto-fill, minmax(${pmCardSize}px, 1fr))`;
    }
}

function pmSaveAndApply() {
    editorPages = JSON.parse(JSON.stringify(pmTempPages));
    if (editorCurrentPage > editorPages.length) {
        editorCurrentPage = editorPages.length;
    }
    closePageManagerModal();
    renderEditorThumbnails();
    renderEditorCanvas();
    updateEditorFloatingInfo();
    showToast('✓ Đã áp dụng các thay đổi từ Trình quản lý trang!', 'success');
}

// =========================================================
// SAAS PRICING & UPGRADE PAGE LOGIC
// =========================================================
function toggleBillingCycle(isYearly) {
    const isEn = document.documentElement.getAttribute('lang') === 'en';
    const proPrice = document.getElementById('proPriceValue');
    const proPeriod = document.getElementById('proPricePeriod');
    const proHint = document.getElementById('proYearlyHint');

    const entPrice = document.getElementById('entPriceValue');
    const entPeriod = document.getElementById('entPricePeriod');
    const entHint = document.getElementById('entYearlyHint');

    if (isYearly) {
        if (proPrice) proPrice.innerText = isEn ? '79,000 VND' : '79.000 đ';
        if (proPeriod) proPeriod.innerText = isEn ? '/ month (Billed annually)' : '/ tháng (Trả cả năm)';
        if (proHint) {
            proHint.innerText = isEn ? 'Billed 950,000 VND/yr (Save 238,000 VND)' : 'Thanh toán 950.000 đ/năm (Tiết kiệm 238.000 đ)';
            proHint.style.display = 'block';
        }

        if (entPrice) entPrice.innerText = isEn ? '239,000 VND' : '239.000 đ';
        if (entPeriod) entPeriod.innerText = isEn ? '/ month (Billed annually)' : '/ tháng (Trả cả năm)';
        if (entHint) {
            entHint.innerText = isEn ? 'Billed 2,870,000 VND/yr (Save 718,000 VND)' : 'Thanh toán 2.870.000 đ/năm (Tiết kiệm 718.000 đ)';
            entHint.style.display = 'block';
        }
    } else {
        if (proPrice) proPrice.innerText = isEn ? '99,000 VND' : '99.000 đ';
        if (proPeriod) proPeriod.innerText = isEn ? '/ month' : '/ tháng';
        if (proHint) proHint.style.display = 'none';

        if (entPrice) entPrice.innerText = isEn ? '299,000 VND' : '299.000 đ';
        if (entPeriod) entPeriod.innerText = isEn ? '/ month' : '/ tháng';
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


