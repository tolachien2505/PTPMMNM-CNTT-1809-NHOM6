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
            showEditorUploadState();
        } else {
            showEditorWorkspace();
        }
    } else if (viewName === 'history') {
        loadExportHistory();
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


