            <div id="view-documents" class="workspace-view <?= $currentView === 'documents' ? 'active' : '' ?>">
                <!-- Action Buttons Top Bar (Clean & Streamlined) -->
                <div class="docs-action-bar">
                    <div class="docs-header-actions">
                        <button class="btn-primary btn-upload-doc-main" onclick="openUploadModal()">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            <span>Tải lên tệp PDF</span>
                        </button>
                        <button class="btn-outline text-danger" onclick="deleteSelectedDocuments()" id="btnDeleteSelectedDocs">
                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            <span>Xóa đã chọn</span>
                        </button>
                    </div>
                </div>

                <!-- Quick Stats Cards Strip -->
                <div class="docs-stats-strip">
                    <div class="docs-stat-card">
                        <div class="docs-stat-icon blue">
                            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div class="docs-stat-meta">
                            <div class="docs-stat-val" id="docsTotalCount"><?= $totalUserDocs ?></div>
                            <div class="docs-stat-lbl">Tổng số tài liệu</div>
                        </div>
                    </div>
                    <div class="docs-stat-card">
                        <div class="docs-stat-icon green">
                            <svg class="svg-icon" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                        </div>
                        <div class="docs-stat-meta">
                            <div class="docs-stat-val" id="docsTotalPages"><?= $totalUserPages ?></div>
                            <div class="docs-stat-lbl">Tổng số trang</div>
                        </div>
                    </div>
                    <div class="docs-stat-card">
                        <div class="docs-stat-icon purple">
                            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                        </div>
                        <div class="docs-stat-meta">
                            <div class="docs-stat-val"><?= $userStorageMb ?> MB</div>
                            <div class="docs-stat-lbl">Dung lượng đã dùng</div>
                        </div>
                    </div>
                    <div class="docs-stat-card">
                        <div class="docs-stat-icon orange">
                            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </div>
                        <div class="docs-stat-meta">
                            <div class="docs-stat-val"><?= $totalUploadedDocs ?></div>
                            <div class="docs-stat-lbl">Tệp sẵn sàng sửa</div>
                        </div>
                    </div>
                </div>

                <!-- Main Documents Management Card -->
                <div class="docs-management-card">
                    <!-- Controls Bar: Search & Status Filters -->
                    <div class="docs-controls-bar">
                        <div class="docs-search-wrapper">
                            <svg class="svg-icon svg-icon-sm docs-search-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" id="docsSearchInput" class="docs-search-input" placeholder="Tìm kiếm theo tên tài liệu..." oninput="filterDocumentsTable()">
                        </div>

                        <div class="docs-filter-tabs">
                            <button type="button" class="docs-tab-btn active" data-status="all" onclick="filterDocsByStatus('all', this)">
                                <span class="tab-label">Tất cả</span>
                                <span class="tab-badge" id="badgeAllCount"><?= $totalUserDocs ?></span>
                            </button>
                            <button type="button" class="docs-tab-btn" data-status="uploaded" onclick="filterDocsByStatus('uploaded', this)">
                                <span class="tab-label">Tệp gốc</span>
                                <span class="tab-badge" id="badgeUploadedCount"><?= $totalUploadedDocs ?></span>
                            </button>
                            <button type="button" class="docs-tab-btn" data-status="editing" onclick="filterDocsByStatus('editing', this)">
                                <span class="tab-label">Đang chỉnh sửa</span>
                                <span class="tab-badge" id="badgeEditingCount"><?= $totalEditingDocs ?></span>
                            </button>
                            <button type="button" class="docs-tab-btn" data-status="exported" onclick="filterDocsByStatus('exported', this)">
                                <span class="tab-label">Đã xuất file</span>
                                <span class="tab-badge" id="badgeExportedCount"><?= $totalExportedDocs ?></span>
                            </button>
                            <button type="button" class="docs-tab-btn" data-status="error" onclick="filterDocsByStatus('error', this)">
                                <span class="tab-label">Lỗi</span>
                                <span class="tab-badge" id="badgeErrorCount"><?= $totalErrorDocs ?></span>
                            </button>
                        </div>
                    </div>

                    <!-- Full Table of Documents -->
                    <div class="table-responsive">
                        <table class="custom-table" id="allDocsTable">
                            <thead>
                                <tr>
                                    <th width="36" class="col-checkbox"><input type="checkbox" id="checkAllDocumentsMaster" onchange="toggleSelectAllDocuments(this)"></th>
                                    <th class="col-name">Tên tệp tài liệu</th>
                                    <th class="col-pages">Số trang</th>
                                    <th class="col-size">Dung lượng</th>
                                    <th class="col-date">Ngày tạo</th>
                                    <th class="col-status">Trạng thái</th>
                                    <th class="col-actions" style="text-align: right; padding-right: 20px;">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody id="allDocsTableBody">
                                <?php if (empty($userDocs)): ?>
                                <tr class="empty-docs-row">
                                    <td colspan="7">
                                        <div class="empty-state-box">
                                            <div class="empty-state-icon" style="background: rgba(37, 99, 235, 0.1); color: var(--primary);">
                                                <svg class="svg-icon svg-icon-lg" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                            </div>
                                            <?php if ($isLoggedIn): ?>
                                            <h4>Chưa có tài liệu PDF nào</h4>
                                            <p>Tải lên tệp PDF đầu tiên của bạn để bắt đầu chỉnh sửa, ghép hoặc tách trang.</p>
                                            <button type="button" class="btn-primary" onclick="openUploadModal()">
                                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                                <span>Tải lên tệp PDF ngay</span>
                                            </button>
                                            <?php else: ?>
                                            <h4>Bạn chưa đăng nhập tài khoản</h4>
                                            <p>Vui lòng đăng nhập hoặc đăng ký tài khoản để xem, quản lý và bảo vệ dữ liệu tệp PDF cá nhân của bạn.</p>
                                            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                                <button type="button" class="btn-primary" onclick="openAuthModal('login')">
                                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                                                    <span>Đăng nhập ngay</span>
                                                </button>
                                                <button type="button" class="btn-outline" onclick="openAuthModal('register')">
                                                    <span>Đăng ký miễn phí</span>
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($userDocs as $doc): 
                                    $dName = htmlspecialchars($doc['original_name']);
                                    $dPages = (int)($doc['total_pages'] ?? 1);
                                    $dSize = formatDocSize((int)($doc['file_size'] ?? 0));
                                    $dDate = formatDocDate($doc['created_at'] ?? null);
                                    $statusInfo = formatDocStatus($doc['status'] ?? 'valid');
                                    $statusClass = $statusInfo['class'];
                                    $statusText = $statusInfo['text'];
                                    $statusCode = $statusInfo['code'];
                                ?>
                                <tr class="doc-row-item recent-doc-row" data-id="<?= $doc['id'] ?>" data-doc-id="<?= $doc['id'] ?>" data-name="<?= strtolower($dName) ?>" data-status="<?= $statusCode ?>">
                                    <td class="col-checkbox"><input type="checkbox" class="doc-select-checkbox" value="<?= $doc['id'] ?>"></td>
                                    <td class="col-name">
                                        <div class="file-cell">
                                            <div class="file-icon-pdf-badge">PDF</div>
                                            <div class="file-meta-wrap">
                                                <span class="doc-table-filename" title="<?= $dName ?>"><?= $dName ?></span>
                                                <div class="file-mobile-meta">
                                                    <span class="meta-tag"><?= $dPages ?> trang</span>
                                                    <span class="meta-sep">•</span>
                                                    <span class="meta-tag"><?= $dSize ?></span>
                                                    <span class="meta-sep">•</span>
                                                    <span class="meta-tag"><?= $dDate ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="col-pages"><strong><?= $dPages ?></strong> trang</td>
                                    <td class="col-size"><?= $dSize ?></td>
                                    <td class="col-date"><?= $dDate ?></td>
                                    <td class="col-status"><span class="status-pill <?= $statusClass ?>"><span class="status-dot"></span> <?= $statusText ?></span></td>
                                    <td class="col-actions">
                                        <div class="actions-cell" style="justify-content: flex-end;">
                                            <button class="btn-table-action btn-edit-doc btn-edit-doc-action" title="Chỉnh sửa trong Trình biên tập" onclick="openDocInEditor(<?= $doc['id'] ?>, '<?= addslashes($dName) ?>')">
                                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                <span>Chỉnh sửa</span>
                                            </button>
                                            <a href="/api/documents/raw?id=<?= $doc['id'] ?>" class="btn-table-action" title="Tải xuống tệp gốc" download="<?= $dName ?>">
                                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                            </a>
                                            <button class="btn-table-action text-danger" title="Xóa tài liệu" onclick="deleteDocItem(<?= $doc['id'] ?>, '<?= addslashes($dName) ?>')">
                                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-footer">
                        <div class="table-footer-info" id="docsTableCounter">
                            Hiển thị <strong><?= count($userDocs) ?></strong> / <strong><?= $totalUserDocs ?></strong> tài liệu
                        </div>
                        <div class="pagination-controls">
                            <button type="button" class="page-btn page-btn-nav" disabled title="Trang trước">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                            </button>
                            <button type="button" class="page-btn active">1</button>
                            <button type="button" class="page-btn page-btn-nav" disabled title="Trang sau">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                        </div>
                        <div class="table-footer-meta">
                            <span class="table-per-page-badge">Trang 1 / 1</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VIEW: CHỈNH SỬA PDF (PDFAID STYLE 100%) -->
