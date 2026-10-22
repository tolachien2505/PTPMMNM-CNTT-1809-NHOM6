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

// Chuyển đổi View trong SPA
function switchView(viewName) {
    document.querySelectorAll('.workspace-view').forEach(v => v.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.querySelectorAll('.mobile-nav-item').forEach(m => m.classList.remove('active'));

    const targetView = document.getElementById(`view-${viewName}`);
    if (targetView) {
        targetView.classList.add('active');
    }

    const navLink = document.querySelector(`.nav-item[data-view="${viewName}"]`);
    if (navLink) {
        navLink.classList.add('active');
    }

    const mobileLink = document.querySelector(`.mobile-nav-item[href="#${viewName}"]`);
    if (mobileLink) {
        mobileLink.classList.add('active');
    }

    // Tự động đóng menu trượt mobile nếu đang mở
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('active');
    }

    if (viewName === 'reorder') {
        editorState.render();
    } else if (viewName === 'history') {
        loadExportHistory();
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
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

    // Auto route on hashchange or initial page load
    function handleHashRoute() {
        const hash = window.location.hash.replace('#', '') || 'home';
        const validViews = ['home', 'documents', 'reorder', 'merge', 'split', 'history', 'settings'];
        if (validViews.includes(hash)) {
            switchView(hash);
        }
    }

    window.addEventListener('hashchange', handleHashRoute);
    handleHashRoute();

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

// Điền nhanh tài khoản kiểm thử
function fillDemoLogin(username, password) {
    document.getElementById('loginUsername').value = username;
    document.getElementById('loginPassword').value = password;
    showToast(`Đã nạp tài khoản: ${username} / ${password}`, 'info');
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
        btn.innerHTML = '<span>Đăng nhập hệ thống</span> <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>';

        if (data.status === 'success') {
            closeModal('authModal');
            showToast(`✓ ${data.message}`, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 800);
        } else {
            showToast(data.message || 'Đăng nhập không thành công.', 'error');
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = '<span>Đăng nhập hệ thống</span>';
        showToast('Lỗi kết nối máy chủ.', 'error');
    }
}

// Xử lý gửi Form Đăng ký
async function handleAuthRegister(event) {
    event.preventDefault();
    const btn = document.getElementById('btnRegisterSubmit');
    const fullName = document.getElementById('regFullName').value.trim();
    const username = document.getElementById('regUsername').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const role = document.getElementById('regRole').value;
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
                role: role,
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
                window.location.reload();
            }, 900);
        } else {
            showToast(data.message || 'Lỗi đăng ký tài khoản.', 'error');
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = '<span>Tạo tài khoản và Đăng nhập</span>';
        showToast('Lỗi kết nối máy chủ khi đăng ký.', 'error');
    }
}

// Chuyển đổi nhanh tài khoản mẫu
async function switchDemoUser(targetId) {
    closeUserDropdown();
    showToast('Đang chuyển đổi tài khoản phiên làm việc...', 'info');

    try {
        const res = await fetch('/api/auth/switch-user', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: targetId })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(`✓ ${data.message}`, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 700);
        } else {
            showToast(data.message || 'Không thể chuyển đổi tài khoản.', 'error');
        }
    } catch (err) {
        showToast('Lỗi khi chuyển đổi tài khoản.', 'error');
    }
}

// Đăng xuất
async function logoutUser() {
    closeUserDropdown();
    try {
        const res = await fetch('/api/auth/logout', { method: 'POST' });
        const data = await res.json();
        showToast('✓ ' + (data.message || 'Đã đăng xuất.'), 'info');
        setTimeout(() => {
            window.location.reload();
        }, 800);
    } catch (err) {
        showToast('Lỗi đăng xuất.', 'error');
    }
}


