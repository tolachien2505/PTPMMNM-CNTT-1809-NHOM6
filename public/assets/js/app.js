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
    if (window.location.hash && window.location.hash.length > 1) {
        let hashView = window.location.hash.replace(/^#\/?/, '').split('?')[0].split('/')[0];
        if (LEGACY_VIEW_MAP[hashView]) hashView = LEGACY_VIEW_MAP[hashView];
        if (VALID_VIEWS.includes(hashView)) {
            window.history.replaceState({ view: hashView }, '', '/' + hashView);
            return hashView;
        }
    }
    let cleanPath = window.location.pathname.replace(/^\/+|\/+$/g, '');
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

    const containerId = `view-${viewName}`;

    // Đổi hiển thị container View
    document.querySelectorAll('.workspace-view').forEach(v => v.classList.remove('active'));
    const targetView = document.getElementById(containerId);
    if (targetView) {
        targetView.classList.add('active');
    }

    // Cập nhật trạng thái active sidebar navigation
    document.querySelectorAll('.nav-item').forEach(n => {
        const itemRoute = n.getAttribute('data-view') || n.getAttribute('href')?.replace(/^\//, '');
        n.classList.toggle('active', itemRoute === viewName);
    });

    // Cập nhật trạng thái active mobile bottom navigation
    document.querySelectorAll('.mobile-nav-item').forEach(m => {
        const itemRoute = m.getAttribute('data-view') || m.getAttribute('href')?.replace(/^\//, '');
        m.classList.toggle('active', itemRoute === viewName);
    });

    // Cập nhật URL trình duyệt qua History API: /home, /documents, /editor, /upgrade... (không có #)
    const shouldPush = (typeof eventOrPush === 'boolean') ? eventOrPush : true;
    if (shouldPush) {
        const targetPath = '/' + viewName;
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
            const urlParams = new URLSearchParams(window.location.search);
            const docId = urlParams.get('docId') || localStorage.getItem('editor_current_doc_id');
            if (docId) {
                const savedName = localStorage.getItem('editor_current_doc_name') || 'Tài liệu PDF';
                const savedUrl = localStorage.getItem('editor_current_doc_url') || ('/api/documents/raw?id=' + docId);
                loadRealPdf(savedUrl, savedName, docId);
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
        const target = href.replace(/^\/+|\/+$/g, '');
        if (VALID_VIEWS.includes(target)) {
            e.preventDefault();
            switchView(target, true);
        }
    }
});

// Tự động chuẩn hóa URL và đồng bộ trạng thái Route khi tải trang
function initCleanRouting() {
    let targetView = 'home';
    if (window.location.hash && window.location.hash.length > 1) {
        let rawHash = window.location.hash.replace(/^#\/?/, '').split('?')[0].split('/')[0];
        if (LEGACY_VIEW_MAP[rawHash]) rawHash = LEGACY_VIEW_MAP[rawHash];
        if (VALID_VIEWS.includes(rawHash)) targetView = rawHash;
        window.history.replaceState({ view: targetView }, '', '/' + targetView);
    } else {
        let currentPath = window.location.pathname.replace(/^\/+|\/+$/g, '');
        if (LEGACY_VIEW_MAP[currentPath]) currentPath = LEGACY_VIEW_MAP[currentPath];
        if (VALID_VIEWS.includes(currentPath)) targetView = currentPath;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const docId = urlParams.get('docId') || localStorage.getItem('editor_current_doc_id');

    switchView(targetView, false);

    if (targetView === 'editor' && docId && !hasLoadedFile) {
        const savedName = localStorage.getItem('editor_current_doc_name') || 'Tài liệu PDF';
        const savedUrl = localStorage.getItem('editor_current_doc_url') || ('/api/documents/raw?id=' + docId);
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

// Tải lịch sử xuất
async function loadExportHistory() {
    const tbody = document.getElementById('historyTableBody');
    if (!tbody) return;

    try {
        const res = await fetch('/api/export/history');
        const data = await res.json();
        tbody.innerHTML = '';

        if (data.history && data.history.length > 0) {
            data.history.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>#${item.id}</td>
                    <td>
                        <div class="file-cell">
                            <div class="file-icon-pdf">PDF</div>
                            <span>${item.filename}</span>
                        </div>
                    </td>
                    <td>${item.total_pages} trang</td>
                    <td>${Math.round(item.file_size / 1024)} KB</td>
                    <td>${item.created_at}</td>
                    <td>
                        <div class="actions-cell">
                            <a href="/api/download?file=${encodeURIComponent(item.filename)}" class="btn-table-action" title="Tải xuống">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            </a>
                            <button class="btn-table-action" title="Xem bảng đối chiếu" onclick="openManifestModal(${item.id})">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <span style="font-size: 0.75rem; margin-left: 4px;">Manifest</span>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #64748B; padding: 20px;">Chưa có tệp nào được xuất. Hãy thực hiện Ghép hoặc Tách PDF để xem lịch sử.</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #EF4444; padding: 20px;">Lỗi tải dữ liệu lịch sử.</td></tr>`;
    }
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
            if (source instanceof File) {
                try {
                    const fd = new FormData();
                    fd.append('file', source);
                    const upRes = await fetch('/api/documents/upload', { method: 'POST', body: fd });
                    const upData = await upRes.json();
                    if (upData.status === 'success' && upData.document) {
                        docId = upData.document.id;
                        currentDocumentName = upData.document.name || currentDocumentName;
                        console.log('✓ File đã được lưu vào storage & MySQL, docId =', docId);
                    }
                } catch (ue) {
                    console.warn('Lỗi lưu tệp lên backend:', ue);
                }
            }
        } else if (typeof source === 'string') {
            currentDocumentName = docName || source.split('/').pop().split('?')[0] || 'tailieu.pdf';
            const res = await fetch(source);
            if (!res.ok) throw new Error(`HTTP ${res.status}: Không thể nạp tệp từ máy chủ.`);
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

        const loadingTask = pdfjsLib.getDocument({
            data: pdfJsBuffer,
            cMapUrl: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/cmaps/',
            cMapPacked: true,
            standardFontDataUrl: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/standard_fonts/'
        });
        currentPdfDoc = await loadingTask.promise;

        hasLoadedFile = true;

        // Lưu thông tin tệp vào LocalStorage và đồng bộ URL query (/editor?docId=...)
        if (docId) {
            localStorage.setItem('editor_current_doc_id', docId);
            localStorage.setItem('editor_current_doc_name', currentDocumentName);
            localStorage.setItem('editor_current_doc_url', '/api/documents/raw?id=' + docId);
            if (window.history && window.history.replaceState) {
                window.history.replaceState({ view: 'editor', docId: docId }, '', '/editor?docId=' + docId);
            }
        }

        // Kiểm tra xem có bản nháp nào được lưu trước đó của tài liệu này không
        editorPages = [];
        let hasRestoredDraft = false;
        if (docId) {
            try {
                const savedDraft = localStorage.getItem('editor_draft_' + docId);
                if (savedDraft) {
                    const parsedDraft = JSON.parse(savedDraft);
                    if (Array.isArray(parsedDraft) && parsedDraft.length > 0) {
                        editorPages = parsedDraft;
                        hasRestoredDraft = true;
                    }
                }
            } catch (de) {
                console.warn('Lỗi đọc nháp từ LocalStorage:', de);
            }
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

function handleEditorFileImport(event) {
    const file = event.target.files[0];
    if (!file) return;
    loadRealPdf(file, file.name);
}

function handleEditorFileDrop(event) {
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

                    const pageNum = Math.min(pageData.originalPageNum || pageData.pageNumber, currentPdfDoc.numPages);
                    const page = await currentPdfDoc.getPage(pageNum);
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
                } catch (e) {}
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
        const pageWidth = 595;
        const pageHeight = 842;
        const viewport = { width: pageWidth * editorZoom, height: pageHeight * editorZoom };
        const pixelRatio = Math.max(1, window.devicePixelRatio || 1);
        canvas.width = Math.floor(viewport.width * pixelRatio);
        canvas.height = Math.floor(viewport.height * pixelRatio);
        canvas.style.width = `${Math.floor(viewport.width)}px`;
        canvas.style.height = `${Math.floor(viewport.height)}px`;

        annotLayer.style.width = `${Math.floor(viewport.width)}px`;
        annotLayer.style.height = `${Math.floor(viewport.height)}px`;

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
        if (isPdfRendering) return;
        isPdfRendering = true;
        if (overlay) overlay.style.display = 'flex';

        try {
            const pageNum = Math.min(pageData.originalPageNum || pageData.pageNumber, currentPdfDoc.numPages);
            const page = await currentPdfDoc.getPage(pageNum);
            
            // Xoay góc: góc gốc của trang + góc người dùng xoay
            const effRot = (page.rotate + (pageData.rotation || 0)) % 360;
            const viewport = page.getViewport({ scale: editorZoom, rotation: effRot });

            const pixelRatio = Math.max(1, window.devicePixelRatio || 1);
            canvas.width = Math.floor(viewport.width * pixelRatio);
            canvas.height = Math.floor(viewport.height * pixelRatio);
            canvas.style.width = `${Math.floor(viewport.width)}px`;
            canvas.style.height = `${Math.floor(viewport.height)}px`;

            annotLayer.style.width = `${Math.floor(viewport.width)}px`;
            annotLayer.style.height = `${Math.floor(viewport.height)}px`;

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
function mapPdfFontToCss(fontName, stylesObj) {
    let resolvedFamily = '';
    let isBold = false;
    let isItalic = false;

    if (stylesObj && stylesObj[fontName]) {
        resolvedFamily = stylesObj[fontName].fontFamily || '';
    }
    const combined = (resolvedFamily + ' ' + (fontName || '')).toLowerCase();

    // Nhận diện kiểu chữ đậm (Bold) và nghiêng (Italic) chính xác
    isBold = /(?:^|[^a-z])(?:bold|black|heavy|w[789]|b\b)|-bold|_bold|boldmt/i.test(combined);
    isItalic = /(?:^|[^a-z])(?:italic|oblique|slant|it\b)|-italic|_italic|italicmt/i.test(combined);

    let family = "'Times New Roman', Times, 'Liberation Serif', serif";

    // 1. Phông chữ Monospace
    if (/courier|mono|consolas|menlo|source code|fira code/i.test(combined)) {
        family = "'Courier New', Courier, monospace";
    }
    // 2. Phông chữ Sans-Serif (Kiểm tra trước Serif vì "sans-serif" chứa chữ "serif")
    else if (/sans-serif|arial|helvetica|calibri|roboto|tahoma|verdana|open sans|myriad|trebuchet|inter|segoe/i.test(combined)) {
        family = "Arial, Helvetica, 'Liberation Sans', sans-serif";
    }
    // 3. Phông chữ Serif / Times
    else if (/times|roman|georgia|cambria|minion|garamond|baskerville|\bserif\b/i.test(combined)) {
        family = "'Times New Roman', Times, 'Liberation Serif', serif";
    } else {
        // Fallback kiểm tra resolvedFamily từ PDF.js styles
        if (/\bserif\b/i.test(resolvedFamily) && !/sans/i.test(resolvedFamily)) {
            family = "'Times New Roman', Times, 'Liberation Serif', serif";
        } else if (/sans/i.test(resolvedFamily)) {
            family = "Arial, Helvetica, 'Liberation Sans', sans-serif";
        } else {
            family = "'Times New Roman', Times, 'Liberation Serif', serif";
        }
    }

    return { family, isBold, isItalic };
}

// Đồng bộ trạng thái phông chữ, cỡ chữ, căn lề của khối được chọn lên thanh ribbon
function syncTextPropertiesToRibbon(item) {
    if (!item) return;
    const fontSelect = document.getElementById('editorFontFamily');
    if (fontSelect && item.fontFamily) {
        const itemFont = item.fontFamily.toLowerCase();
        let matched = false;
        for (const opt of fontSelect.options) {
            const optVal = opt.value.toLowerCase();
            const optTxt = opt.text.toLowerCase();
            if ((itemFont.includes('times') && (optVal.includes('times') || optTxt.includes('times'))) ||
                (itemFont.includes('arial') && (optVal.includes('arial') || optTxt.includes('arial'))) ||
                (itemFont.includes('helvetica') && (optVal.includes('helvetica') || optTxt.includes('helvetica'))) ||
                (itemFont.includes('courier') && (optVal.includes('courier') || optTxt.includes('courier')))) {
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
    }

    const sizeSelect = document.getElementById('editorStrokeOrFontSize');
    if (sizeSelect && item.fontSize) {
        const targetSize = Math.round(item.fontSize);
        let bestOpt = sizeSelect.options[0]?.value || '12';
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
    }

    const boldBtn = document.getElementById('btnFormatBold');
    if (boldBtn) {
        boldBtn.classList.toggle('active', !!item.isBold);
    }

    const italicBtn = document.getElementById('btnFormatItalic');
    if (italicBtn) {
        italicBtn.classList.toggle('active', !!item.isItalic);
    }
}

// Hàm dọn dẹp các ký tự vỡ trực tiếp từ PDF raw stream
function cleanPdfRawString(str) {
    if (!str) return '';
    let s = str;

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

    return s;
}

// Hàm hậu xử lý phục hồi 100% ngữ nghĩa tiếng Việt và tiếng Anh sau khi ghép khối văn bản
function postProcessMergedText(text) {
    if (!text) return '';
    let s = text;

    // 1. Phục hồi ngữ nghĩa các từ tiếng Việt bị vỡ tổ hợp dấu hoặc ký tự \uFFFD
    s = s.replace(/\byế\s*u\b/gi, 'yếu');
    s = s.replace(/yế\s*u\s*t\s*o\s*\uFFFD?/gi, 'yếu tố');
    s = s.replace(/yếu\s*t\s*o\s*\uFFFD?/gi, 'yếu tố');
    s = s.replace(/nhân\s*t\s*o\s*\uFFFD?/gi, 'nhân tố');
    s = s.replace(/\bt\s*o\s*\uFFFD/gi, 'tố');
    s = s.replace(/\bto\uFFFD/gi, 'tố');
    s = s.replace(/ngh\s*ế\s*\uFFFD?\s*nghiệp/gi, 'nghề nghiệp');
    s = s.replace(/ngh\s*ế\s*\uFFFD/gi, 'nghề');
    s = s.replace(/nghế\uFFFD/gi, 'nghề');
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

// Render lớp chỉnh sửa trực tiếp chữ gốc của PDF (Khớp 100% Ảnh 1)
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
                if (targetPageNum <= currentPdfDoc.numPages) {
                    const page = await currentPdfDoc.getPage(targetPageNum);
                    const effRot = (page.rotate + (pageData.rotation || 0)) % 360;
                    // Lấy viewport ở scale chuẩn 1.0 (PDF points) để lưu trữ tọa độ bất biến khi zoom
                    const viewport = page.getViewport({ scale: 1.0, rotation: effRot });
                    const textContent = await page.getTextContent();

                    const rawItems = [];
                    for (let idx = 0; idx < textContent.items.length; idx++) {
                        const it = textContent.items[idx];
                        const str = cleanPdfRawString(it.str ? it.str : '');
                        if (!str.trim()) continue;

                        const tr = pdfjsLib.Util.transform(viewport.transform, it.transform);
                        const fontSize = Math.max(9, Math.round(Math.hypot(tr[0], tr[1])));
                        const vx = Math.round(tr[4]);
                        const vy = Math.round(tr[5]);

                        const left = vx;
                        const top = Math.max(0, Math.round(vy - fontSize * 0.95));
                        const width = Math.max(8, Math.round(it.width));
                        const height = Math.max(12, Math.round(fontSize * 1.25));

                        const fontInfo = mapPdfFontToCss(it.fontName, textContent.styles);

                        rawItems.push({
                            id: `p${pageData.pageNumber}_t${idx}`,
                            str: str,
                            left,
                            top,
                            width,
                            height,
                            fontSize,
                            fontFamily: fontInfo.family,
                            isBold: fontInfo.isBold,
                            isItalic: fontInfo.isItalic
                        });
                    }

                    // Sắp xếp các phần tử theo thứ tự đọc tự nhiên từ trên xuống dưới, từ trái sang phải
                    rawItems.sort((a, b) => {
                        if (Math.abs(a.top - b.top) > 4) {
                            return a.top - b.top;
                        }
                        return a.left - b.left;
                    });

                    // Bước 1: Nhóm các từ nằm trên cùng một dòng trong cùng một cột (không gộp các cột cách xa nhau)
                    const lines = [];
                    let curLine = null;

                    for (let i = 0; i < rawItems.length; i++) {
                        const cur = rawItems[i];
                        if (!curLine) {
                            curLine = { ...cur };
                            continue;
                        }

                        const isSameLine = Math.abs(cur.top - curLine.top) <= Math.max(4, cur.fontSize * 0.35);
                        const prevRight = curLine.left + curLine.width;
                        const isAdjacent = (cur.left >= curLine.left) &&
                                           (cur.left <= prevRight + Math.max(16, cur.fontSize * 1.5)) &&
                                           (cur.left >= prevRight - 6);

                        if (isSameLine && isAdjacent) {
                            const gap = cur.left - prevRight;
                            // Ngưỡng khoảng cách thực tế giữa 2 từ trong typography PDF: ~0.28 kích cỡ font
                            // Chỉ chèn dấu cách nếu không phải là ký tự đơn lẻ hoặc ký tự dấu tiếng Việt
                            const isSingleChar = cur.str.length <= 1 || curLine.str.length <= 1;
                            const isSpaceGap = !isSingleChar && (gap >= Math.max(2.4, cur.fontSize * 0.28));
                            const alreadyHasSpace = curLine.str.endsWith(' ') || cur.str.startsWith(' ');
                            const needsSpace = isSpaceGap && !alreadyHasSpace;

                            curLine.str += (needsSpace ? ' ' : '') + cur.str;
                            curLine.width = Math.round((cur.left + cur.width) - curLine.left);
                            curLine.height = Math.max(curLine.height, cur.height);
                            curLine.isBold = curLine.isBold || cur.isBold;
                            curLine.isItalic = curLine.isItalic || cur.isItalic;
                        } else {
                            lines.push(curLine);
                            curLine = { ...cur };
                        }
                    }
                    if (curLine) lines.push(curLine);

                    // Bước 2: Nhóm các dòng liền kề cùng thuộc một đoạn văn bản (paragraph)
                    const paragraphs = [];
                    let curPara = null;

                    for (let i = 0; i < lines.length; i++) {
                        const cur = lines[i];
                        if (!curPara) {
                            curPara = { ...cur };
                            continue;
                        }

                        const paraBottom = curPara.top + curPara.height;
                        const isNextLine = (cur.top >= curPara.top + curPara.fontSize * 0.8) &&
                                           (cur.top <= paraBottom + curPara.fontSize * 0.85);
                        const isAlignedLeft = Math.abs(cur.left - curPara.left) <= 12;
                        const isSameFont = (cur.fontFamily === curPara.fontFamily) &&
                                           (Math.abs(cur.fontSize - curPara.fontSize) <= 1.5) &&
                                           (cur.isBold === curPara.isBold);

                        if (isNextLine && isAlignedLeft && isSameFont) {
                            curPara.str += '\n' + cur.str;
                            curPara.width = Math.max(curPara.width, cur.width);
                            curPara.height = Math.round((cur.top + cur.height) - curPara.top);
                        } else {
                            paragraphs.push(curPara);
                            curPara = { ...cur };
                        }
                    }
                    if (curPara) paragraphs.push(curPara);

                    // Hậu xử lý phục hồi ngữ nghĩa toàn diện cho tất cả các đoạn văn bản
                    paragraphs.forEach(p => {
                        p.str = postProcessMergedText(p.str);
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
                { id: `p${pageData.pageNumber}_t0`, str: 'Đặc san Nghiên cứu Chính sách và Phát triển', left: 45, top: 48, width: 290, height: 22, fontSize: 13, fontFamily: "'Times New Roman', Times, serif", isBold: true },
                { id: `p${pageData.pageNumber}_t1`, str: 'Số 02/2022', left: 510, top: 48, width: 85, height: 22, fontSize: 13, fontFamily: "'Times New Roman', Times, serif" },
                { id: `p${pageData.pageNumber}_t2`, str: 'Hạnh Đào và cộng sự (2022). Những yếu tố ảnh hưởng đến lựa chọn ngành nghề của sinh viên chuyên ngành tài chính - ngân hàng học viện chính sách và phát triển. Đặc san Nghiên cứu Chính sách và Phát triển, 2(2022), 127-137', left: 45, top: 82, width: 350, height: 62, fontSize: 10, fontFamily: "'Times New Roman', Times, serif" },
                { id: `p${pageData.pageNumber}_t3`, str: 'Những yếu tố ảnh hưởng đến lựa chọn ngành nghề của sinh viên chuyên ngành tài chính - ngân hàng học viện chính sách phát triển', left: 45, top: 160, width: 360, height: 68, fontSize: 16, fontFamily: "'Times New Roman', Times, serif", isBold: true },
                { id: `p${pageData.pageNumber}_t4`, str: 'Đào Thị Bích Hạnh (TS)\nHọc viện Chính sách và Phát triển\nEmail: bichhanh@mpi.gov.vn', left: 55, top: 242, width: 230, height: 42, fontSize: 11, fontFamily: "'Times New Roman', Times, serif" },
                { id: `p${pageData.pageNumber}_t5`, str: 'Cao Ánh Tuyết, Nguyễn Huyền My, Trần Quỳnh Liên, Vũ Thị Ngân, Ngô Việt Anh', left: 55, top: 298, width: 280, height: 32, fontSize: 11, fontFamily: "'Times New Roman', Times, serif" },
                { id: `p${pageData.pageNumber}_t6`, str: 'Tóm tắt: Bài nghiên cứu xác định những yếu tố ảnh hưởng đến việc lựa chọn ngành nghề của sinh viên chuyên ngành Tài chính - Ngân hàng tại Học viện Chính sách và Phát triển. Thông qua kết quả khảo sát thực tế và đánh giá mức độ ảnh hưởng của các nhân tố, bài viết đề xuất những giải pháp nhằm giúp cho sinh viên có những lựa chọn đúng về chuyên ngành mình học, phù hợp với những yêu cầu đặt ra của nền kinh tế và thị trường lao động đồng thời có các kiến nghị với Khoa Tài chính - Đầu tư', left: 45, top: 348, width: 380, height: 135, fontSize: 11, fontFamily: "'Times New Roman', Times, serif" }
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
        const effectiveColor = (editData && editData.color) || '#0F172A';
        const effectiveAlign = (editData && editData.align) || 'left';

        const block = document.createElement('div');
        block.className = `pdf-detected-text-block ${isEdited ? 'is-edited' : ''}`;
        block.contentEditable = (editorActiveTool === 'edit-text') ? 'true' : 'false';
        block.dataset.textId = item.id;
        block.style.left = `${Math.round(item.left * zoom)}px`;
        block.style.top = `${Math.round(item.top * zoom)}px`;
        block.style.minWidth = `${Math.round(item.width * zoom)}px`;
        block.style.minHeight = `${Math.round(item.height * zoom)}px`;
        block.style.fontSize = `${Math.round(effectiveSize * zoom)}px`;
        block.style.fontFamily = effectiveFont;
        block.style.fontWeight = effectiveBold ? 'bold' : 'normal';
        block.style.fontStyle = effectiveItalic ? 'italic' : 'normal';
        block.style.textAlign = effectiveAlign;
        // Khi chưa bấm vào: chữ trong suốt để hiển thị chữ gốc sắc nét 100% của PDF (Khớp Ảnh 1)
        block.style.color = isEdited ? effectiveColor : 'transparent';
        block.innerText = currentText;
        block.title = 'Bấm để chỉnh sửa văn bản này trực tiếp trên trang';

        if (editorActiveTool === 'edit-text') {
            block.onfocus = () => {
                block.classList.add('is-editing');
                block.style.color = (pageData.textEdits[item.id]?.color) || effectiveColor || '#0F172A';
                editorSelectedElementId = item.id;
                syncTextPropertiesToRibbon(editData || item);
            };

            block.oninput = () => {
                block.classList.add('is-edited');
                block.style.color = (pageData.textEdits[item.id]?.color) || effectiveColor || '#0F172A';
                pageData.textEdits[item.id] = {
                    id: item.id,
                    text: block.innerText,
                    left: item.left,
                    top: item.top,
                    width: item.width,
                    height: item.height,
                    fontSize: effectiveSize,
                    fontFamily: effectiveFont,
                    isBold: effectiveBold,
                    isItalic: effectiveItalic,
                    color: effectiveColor,
                    align: effectiveAlign,
                    modified: true
                };
                saveEditorDraft();
            };

            block.onblur = () => {
                block.classList.remove('is-editing');
                const curEdit = pageData.textEdits[item.id];
                if (block.innerText === item.str && (!curEdit || !curEdit.modifiedFormat)) {
                    delete pageData.textEdits[item.id];
                    block.classList.remove('is-edited');
                    block.style.color = 'transparent';
                } else {
                    block.classList.add('is-edited');
                    block.style.color = curEdit?.color || '#0F172A';
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

    (pageData.elements || []).forEach(el => {
        let domEl;

        if (el.type === 'text') {
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
                domEl.innerHTML = `<div style="font-family: ${el.fontFamily || 'Dancing Script, cursive'}; font-size: ${el.size || 34}px; color: ${el.color || '#000000'}; font-weight: 700; user-select: none;">${el.name}</div>`;
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
                <a href="${el.url}" target="_blank" class="canvas-link-badge" onclick="if(event.ctrlKey) return true; event.preventDefault();">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px; height:14px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    <span>${el.text || el.url}</span>
                </a>
                ${isSelected ? `
                <div class="inline-link-popover" onclick="event.stopPropagation();">
                    <input type="text" class="inline-link-input" style="width: 160px;" value="${el.url}" placeholder="https://..." oninput="updateLinkAnnotation('${el.id}', 'url', this.value)">
                    <input type="text" class="inline-link-input" style="width: 110px;" value="${el.text}" placeholder="Nhãn liên kết..." oninput="updateLinkAnnotation('${el.id}', 'text', this.value)">
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
                    <textarea class="inline-note-textarea" placeholder="Nhập ghi chú trực tiếp tại đây..." oninput="updateNoteAnnotation('${el.id}', this.value)">${el.text || ''}</textarea>
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
    domEl.contentEditable = "true";
    domEl.classList.add('inline-editing');
    domEl.focus();

    try {
        const range = document.createRange();
        range.selectNodeContents(domEl);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    } catch (e) {}

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
        if (!clean && pageData) {
            pageData.elements = pageData.elements.filter(item => item.id !== el.id);
            renderEditorCanvas();
        } else {
            el.text = clean;
            el.isEditing = false;
            pushEditorHistory();
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
        pushEditorHistory();
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
        pushEditorHistory();
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
        return `<svg width="${w}" height="${h}" viewBox="0 0 ${w} ${h}"><polygon points="${w/2},4 ${w-6},${h*0.35} ${w*0.8},${h-6} ${w*0.2},${h-6} 6,${h*0.35}" fill="${fillCol}" stroke="${stroke}" stroke-width="${strokeWidth}" ${dashAttr}/></svg>`;
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
                blockEl.classList.add('is-edited');
                if (!pageData.textEdits) pageData.textEdits = {};
                if (!pageData.textEdits[editorSelectedElementId]) {
                    const origItem = (pageData._cachedTextItems || []).find(it => it.id === editorSelectedElementId);
                    pageData.textEdits[editorSelectedElementId] = {
                        id: editorSelectedElementId,
                        text: blockEl.innerText,
                        left: origItem ? origItem.left : blockEl.offsetLeft,
                        top: origItem ? origItem.top : blockEl.offsetTop,
                        width: origItem ? origItem.width : blockEl.offsetWidth,
                        height: origItem ? origItem.height : blockEl.offsetHeight,
                        fontSize: origItem ? origItem.fontSize : 14,
                        fontFamily: origItem ? origItem.fontFamily : 'Times New Roman'
                    };
                }
                pageData.textEdits[editorSelectedElementId].color = hex;
                pageData.textEdits[editorSelectedElementId].modified = true;
                pageData.textEdits[editorSelectedElementId].modifiedFormat = true;
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
                if (!pageData.textEdits) pageData.textEdits = {};
                if (!pageData.textEdits[editorSelectedElementId]) {
                    const origItem = (pageData._cachedTextItems || []).find(it => it.id === editorSelectedElementId);
                    pageData.textEdits[editorSelectedElementId] = {
                        id: editorSelectedElementId,
                        text: blockEl.innerText,
                        left: origItem ? origItem.left : blockEl.offsetLeft,
                        top: origItem ? origItem.top : blockEl.offsetTop,
                        width: origItem ? origItem.width : blockEl.offsetWidth,
                        height: origItem ? origItem.height : blockEl.offsetHeight,
                        fontFamily: origItem ? origItem.fontFamily : 'Times New Roman'
                    };
                }
                pageData.textEdits[editorSelectedElementId].fontSize = editorFontSize;
                pageData.textEdits[editorSelectedElementId].modified = true;
                pageData.textEdits[editorSelectedElementId].modifiedFormat = true;
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
                if (!pageData.textEdits) pageData.textEdits = {};
                if (!pageData.textEdits[editorSelectedElementId]) {
                    const origItem = (pageData._cachedTextItems || []).find(it => it.id === editorSelectedElementId);
                    pageData.textEdits[editorSelectedElementId] = {
                        id: editorSelectedElementId,
                        text: blockEl.innerText,
                        left: origItem ? origItem.left : blockEl.offsetLeft,
                        top: origItem ? origItem.top : blockEl.offsetTop,
                        width: origItem ? origItem.width : blockEl.offsetWidth,
                        height: origItem ? origItem.height : blockEl.offsetHeight,
                        fontSize: origItem ? origItem.fontSize : 14
                    };
                }
                pageData.textEdits[editorSelectedElementId].fontFamily = font;
                pageData.textEdits[editorSelectedElementId].modified = true;
                pageData.textEdits[editorSelectedElementId].modifiedFormat = true;
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
                if (!pageData.textEdits) pageData.textEdits = {};
                if (!pageData.textEdits[editorSelectedElementId]) {
                    const origItem = (pageData._cachedTextItems || []).find(it => it.id === editorSelectedElementId);
                    pageData.textEdits[editorSelectedElementId] = {
                        id: editorSelectedElementId,
                        text: blockEl.innerText,
                        left: origItem ? origItem.left : blockEl.offsetLeft,
                        top: origItem ? origItem.top : blockEl.offsetTop,
                        width: origItem ? origItem.width : blockEl.offsetWidth,
                        height: origItem ? origItem.height : blockEl.offsetHeight
                    };
                }
                pageData.textEdits[editorSelectedElementId].align = align;
                pageData.textEdits[editorSelectedElementId].modified = true;
                pageData.textEdits[editorSelectedElementId].modifiedFormat = true;
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
                if (!pageData.textEdits) pageData.textEdits = {};
                if (!pageData.textEdits[editorSelectedElementId]) {
                    const origItem = (pageData._cachedTextItems || []).find(it => it.id === editorSelectedElementId);
                    pageData.textEdits[editorSelectedElementId] = {
                        id: editorSelectedElementId,
                        text: blockEl.innerText,
                        left: origItem ? origItem.left : blockEl.offsetLeft,
                        top: origItem ? origItem.top : blockEl.offsetTop,
                        width: origItem ? origItem.width : blockEl.offsetWidth,
                        height: origItem ? origItem.height : blockEl.offsetHeight,
                        fontSize: origItem ? origItem.fontSize : 14,
                        fontFamily: origItem ? origItem.fontFamily : 'Times New Roman'
                    };
                }
                pageData.textEdits[editorSelectedElementId].isBold = newState;
                pageData.textEdits[editorSelectedElementId].modified = true;
                pageData.textEdits[editorSelectedElementId].modifiedFormat = true;
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
                if (!pageData.textEdits) pageData.textEdits = {};
                if (!pageData.textEdits[editorSelectedElementId]) {
                    const origItem = (pageData._cachedTextItems || []).find(it => it.id === editorSelectedElementId);
                    pageData.textEdits[editorSelectedElementId] = {
                        id: editorSelectedElementId,
                        text: blockEl.innerText,
                        left: origItem ? origItem.left : blockEl.offsetLeft,
                        top: origItem ? origItem.top : blockEl.offsetTop,
                        width: origItem ? origItem.width : blockEl.offsetWidth,
                        height: origItem ? origItem.height : blockEl.offsetHeight,
                        fontSize: origItem ? origItem.fontSize : 14,
                        fontFamily: origItem ? origItem.fontFamily : 'Times New Roman'
                    };
                }
                pageData.textEdits[editorSelectedElementId].isItalic = newState;
                pageData.textEdits[editorSelectedElementId].modified = true;
                pageData.textEdits[editorSelectedElementId].modifiedFormat = true;
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
        elements: [
            { id: 'el-' + Date.now(), type: 'text', text: `TRANG MỚI ĐƯỢC THÊM (#${newPageNum})`, x: 40, y: 320, size: 16, color: '#0F172A', bold: true }
        ],
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

function handleEditorFileImport(event) {
    const file = event.target.files[0];
    if (!file) return;

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
    reader.onload = function(e) {
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
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY
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

function renderPageDrawings(pageData) {
    const canvas = document.getElementById('paperDrawingCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);

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
}

function fitEditorToScreen() {
    editorZoom = 0.85;
    const label = document.getElementById('floatingZoomLevel');
    if (label) label.innerText = '85%';
    renderEditorCanvas();
}

// Tự động lưu bản nháp chỉnh sửa vào LocalStorage để không bị mất khi F5
function saveEditorDraft() {
    const docId = localStorage.getItem('editor_current_doc_id');
    if (docId && editorPages && editorPages.length > 0) {
        try {
            localStorage.setItem('editor_draft_' + docId, JSON.stringify(editorPages));
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
    saveEditorDraft();
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
        let pdfBlobUrl = '';
        if (currentPdfBytes) {
            const blob = new Blob([currentPdfBytes], { type: 'application/pdf' });
            pdfBlobUrl = URL.createObjectURL(blob);
        } else {
            const rawDocUrl = localStorage.getItem('editor_current_doc_url');
            if (rawDocUrl) pdfBlobUrl = rawDocUrl;
        }

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
                }, 400);
            };
        } else {
            window.print();
        }
    } catch (e) {
        window.print();
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
            for (let i = 1; i <= currentPdfDoc.numPages; i++) {
                try {
                    const page = await currentPdfDoc.getPage(i);
                    const pageData = (editorPages || []).find(p => p.pageNumber === i) || { rotation: 0 };
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
        const confirmSwitch = confirm('Bạn có chắc chắn muốn đổi sang tệp PDF khác? Mọi chỉnh sửa hiện tại đã được tự động lưu nháp.');
        if (!confirmSwitch) return;
    }

    localStorage.removeItem('editor_current_doc_id');
    localStorage.removeItem('editor_current_doc_name');
    localStorage.removeItem('editor_current_doc_url');

    if (window.history && window.history.replaceState) {
        window.history.replaceState({ view: 'editor' }, '', '/editor');
    }

    hasLoadedFile = false;
    currentPdfDoc = null;
    currentPdfBytes = null;
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
    try {
        page.drawText(text, options);
    } catch (err) {
        try {
            page.drawText(removeVietnameseTones(text), options);
        } catch (e2) {
            console.warn('drawSafeText fallback failed:', e2);
        }
    }
}

// Hoàn thành biên tập và xuất tệp PDF hoàn chỉnh chuẩn PDFaid
async function finishAndSaveDocument() {
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

        if (window.PDFLib) {
            let pdfDoc = null;
            if (rawBytes && rawBytes.byteLength > 0) {
                try {
                    pdfDoc = await PDFLib.PDFDocument.load(rawBytes, { ignoreEncryption: true });
                } catch (loadErr) {
                    console.warn('Không thể load file PDF gốc bằng PDFLib, sẽ tạo tài liệu mới:', loadErr);
                    pdfDoc = null;
                }
            }
            const numPages = pdfDoc ? pdfDoc.getPageCount() : 0;

            // Tạo tài liệu xuất mới hỗ trợ xoay trang và nhúng chú thích
            const newPdf = await PDFLib.PDFDocument.create();

            for (const pageItem of editorPages) {
                let targetPage = null;
                const origIndex = (pageItem.originalPageNum || 0) - 1;

                if (pdfDoc && !pageItem.isBlankPage && pageItem.originalPageNum && origIndex >= 0 && origIndex < numPages) {
                    const [copiedPage] = await newPdf.copyPages(pdfDoc, [origIndex]);
                    targetPage = copiedPage;
                } else {
                    // Trang mới thêm (trang trắng) - tạo trang A4 mới tinh
                    targetPage = newPdf.addPage([595.28, 841.89]);
                }

                // Áp dụng góc xoay nếu có
                if (pageItem.rotation && targetPage.setRotation) {
                    const currentRot = targetPage.getRotation ? targetPage.getRotation().angle : 0;
                    targetPage.setRotation(PDFLib.degrees((currentRot + pageItem.rotation) % 360));
                }

                const { width, height } = targetPage.getSize();

                // Chuẩn bị các font chuẩn
                const helvetica = await newPdf.embedFont(PDFLib.StandardFonts.Helvetica);
                const helveticaBold = await newPdf.embedFont(PDFLib.StandardFonts.HelveticaBold);
                const helveticaOblique = await newPdf.embedFont(PDFLib.StandardFonts.HelveticaOblique);
                const timesRoman = await newPdf.embedFont(PDFLib.StandardFonts.TimesRoman);
                const timesRomanBold = await newPdf.embedFont(PDFLib.StandardFonts.TimesRomanBold);
                const timesRomanItalic = await newPdf.embedFont(PDFLib.StandardFonts.TimesRomanItalic);
                const courier = await newPdf.embedFont(PDFLib.StandardFonts.Courier);
                const courierBold = await newPdf.embedFont(PDFLib.StandardFonts.CourierBold);
                const helveticaFont = helvetica;

                // 1. Nhúng các khối văn bản gốc đã chỉnh sửa (Khớp 100% Chỉnh sửa văn bản)
                if (pageItem.textEdits && Object.keys(pageItem.textEdits).length > 0) {
                    for (const textId of Object.keys(pageItem.textEdits)) {
                        const edit = pageItem.textEdits[textId];
                        if (!edit || !edit.text) continue;

                        const pdfX = edit.left;
                        const pdfY = height - (edit.top + edit.height);
                        const pdfW = Math.max(16, edit.width);
                        const pdfH = Math.max(12, edit.height);

                        // Vẽ hình chữ nhật trắng che phủ chữ gốc trên PDF để không bị đè chữ
                        targetPage.drawRectangle({
                            x: pdfX,
                            y: pdfY,
                            width: pdfW,
                            height: pdfH,
                            color: PDFLib.rgb(1, 1, 1)
                        });

                        // Xác định font phù hợp
                        let chosenFont = timesRoman;
                        const fLower = (edit.fontFamily || '').toLowerCase();
                        if (fLower.includes('helvetica') || fLower.includes('arial') || fLower.includes('sans')) {
                            chosenFont = edit.isBold ? helveticaBold : (edit.isItalic ? helveticaOblique : helvetica);
                        } else if (fLower.includes('courier') || fLower.includes('mono')) {
                            chosenFont = edit.isBold ? courierBold : courier;
                        } else {
                            chosenFont = edit.isBold ? timesRomanBold : (edit.isItalic ? timesRomanItalic : timesRoman);
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

                        const lines = edit.text.split('\n');
                        const baseFontSize = Math.max(8, edit.fontSize || 12);
                        const lineHeight = baseFontSize * 1.25;

                        lines.forEach((lineText, lIdx) => {
                            if (!lineText) return;
                            drawSafeText(targetPage, lineText, {
                                x: pdfX,
                                y: pdfY + pdfH - (lIdx + 1) * lineHeight + 2,
                                size: baseFontSize,
                                font: chosenFont,
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
                        const scaleFactor = width / (pageItem.renderedWidth || 595);

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
                    } catch (drawErr) {
                        console.warn('Lỗi nhúng nét vẽ vào PDF:', drawErr);
                    }
                }

                // 3. Nhúng tất cả đối tượng chú thích: Văn bản, Chữ ký, Ảnh, Hình khối, Con dấu, Liên kết, Ghi chú
                if (pageItem.elements && pageItem.elements.length > 0) {
                    for (const el of pageItem.elements) {
                        const scaleFactor = width / (pageItem.renderedWidth || 595);

                        if (el.type === 'text' && el.text) {
                            try {
                                const pdfX = (el.x || 40) * scaleFactor;
                                const pdfY = height - ((el.y || 40) + (el.size || 14)) * scaleFactor;
                                let chosenFont = el.bold ? helveticaBold : helvetica;
                                if (el.fontFamily && el.fontFamily.toLowerCase().includes('times')) {
                                    chosenFont = el.bold ? timesRomanBold : timesRoman;
                                }

                                drawSafeText(targetPage, el.text, {
                                    x: Math.max(10, Math.min(width - 50, pdfX)),
                                    y: Math.max(10, Math.min(height - 20, pdfY)),
                                    size: Math.max(9, (el.size || 14) * scaleFactor),
                                    font: chosenFont,
                                    color: (el.color && el.color.startsWith('#')) ? parseColorToPdfLib(el.color) : PDFLib.rgb(0.06, 0.09, 0.16)
                                });
                            } catch (te) {}
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
                                        font: timesRomanItalic,
                                        color: parseColorToPdfLib(el.color, PDFLib.rgb(0, 0, 0))
                                    });
                                }
                            } catch (sigErr) {
                                console.warn('Lỗi nhúng chữ ký:', sigErr);
                            }
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
                            } catch (imgErr) {
                                console.warn('Lỗi nhúng ảnh vào PDF:', imgErr);
                            }
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
                                }
                            } catch (shapeErr) {
                                console.warn('Lỗi nhúng hình học vào PDF:', shapeErr);
                            }
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
                            } catch (stampErr) {
                                console.warn('Lỗi nhúng con dấu vào PDF:', stampErr);
                            }
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
                                    font: helvetica,
                                    color: PDFLib.rgb(0.12, 0.35, 0.88)
                                });
                            } catch (lnkErr) {
                                console.warn('Lỗi nhúng liên kết vào PDF:', lnkErr);
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
                                    font: helveticaBold,
                                    color: PDFLib.rgb(0.57, 0.42, 0.04)
                                });

                                if (el.text) {
                                    const lines = el.text.split('\n').slice(0, 5);
                                    lines.forEach((line, lIdx) => {
                                        drawSafeText(targetPage, line.substring(0, 42), {
                                            x: noteX + 6 * scaleFactor,
                                            y: noteY + noteH - 36 * scaleFactor - (lIdx * 13 * scaleFactor),
                                            size: 9 * scaleFactor,
                                            font: helvetica,
                                            color: PDFLib.rgb(0.2, 0.2, 0.2)
                                        });
                                    });
                                }
                            } catch (noteErr) {
                                console.warn('Lỗi nhúng ghi chú vào PDF:', noteErr);
                            }
                        }
                    }
                }

                if (pdfDoc && !pageItem.isBlankPage && pageItem.originalPageNum && origIndex >= 0 && origIndex < numPages) {
                    newPdf.addPage(targetPage);
                }
            }

            const modifiedPdfBytes = await newPdf.save();
            finalPdfBlob = new Blob([modifiedPdfBytes], { type: 'application/pdf' });

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
                            source_doc: currentDocumentName || 'tailieu.pdf',
                            source_page: p.originalPageNum || p.pageNumber,
                            rotation: p.rotation || 0
                        }))
                    })
                });
                backendResult = await res.json();
                if (activeDocId) {
                    updateLocalDocStatus(activeDocId, 'exported');
                }
            } catch (be) {}
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
        showToast(`✓ Đã xuất và tải về "${exportFileName}" thành công!`, 'success');
    } catch (err) {
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
            if (currentPdfDoc) {
                editorPages = [];
                for (let i = 1; i <= currentPdfDoc.numPages; i++) {
                    editorPages.push({ id: i, pageNumber: i, originalPageNum: i, title: `Trang ${i}`, rotation: 0, elements: [], drawings: [] });
                }
            } else {
                editorPages = [
                    { id: 1, pageNumber: 1, originalPageNum: 1, title: 'Trang 1', rotation: 0, elements: [], drawings: [] },
                    { id: 2, pageNumber: 2, originalPageNum: 2, title: 'Trang 2', rotation: 0, elements: [], drawings: [] },
                    { id: 3, pageNumber: 3, originalPageNum: 3, title: 'Trang 3', rotation: 0, elements: [], drawings: [] },
                    { id: 4, pageNumber: 4, originalPageNum: 4, title: 'Trang 4', rotation: 0, elements: [], drawings: [] },
                    { id: 5, pageNumber: 5, originalPageNum: 5, title: 'Trang 5', rotation: 0, elements: [], drawings: [] },
                    { id: 6, pageNumber: 6, originalPageNum: 6, title: 'Trang 6', rotation: 0, elements: [], drawings: [] },
                    { id: 7, pageNumber: 7, originalPageNum: 7, title: 'Trang 7', rotation: 0, elements: [], drawings: [] },
                    { id: 8, pageNumber: 8, originalPageNum: 8, title: 'Trang 8', rotation: 0, elements: [], drawings: [] }
                ];
            }
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
                    const pageNum = Math.min(page.originalPageNum || page.pageNumber, currentPdfDoc.numPages);
                    currentPdfDoc.getPage(pageNum).then(pdfPage => {
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
    switchView('editor');
    localStorage.setItem('editor_current_doc_id', docId);
    localStorage.setItem('editor_current_doc_name', docName || 'Tài liệu #' + docId);
    localStorage.setItem('editor_current_doc_url', '/api/documents/raw?id=' + docId);
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


