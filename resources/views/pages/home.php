            <div id="view-home" class="workspace-view <?= $currentView === 'home' ? 'active' : '' ?>">
                <!-- TOP ROW: Hero Banner (Left) + 4 Quick Actions in 2x2 Grid (Right) -->
                <div class="top-dashboard-row">
                    <!-- Hero Banner -->
                    <section class="hero-banner">
                        <div class="hero-text">
                            <h2>Xử lý PDF nhanh chóng,<br>linh hoạt và dễ dàng</h2>
                            <p>Ghép, tách, sắp xếp, xoay và chỉnh sửa tài liệu PDF chỉ với vài thao tác đơn giản.</p>
                            <div class="feature-badges">
                                <span class="feature-badge-item">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                                    An toàn (Bảo mật dữ liệu)
                                </span>
                                <span class="feature-badge-item">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                    Nhanh chóng (Xử lý trong vài giây)
                                </span>
                                <span class="feature-badge-item">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    Dễ sử dụng (Giao diện trực quan)
                                </span>
                            </div>
                        </div>
                        <div class="hero-illu">
                            <div class="hero-3d-pdf">
                                <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.8">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="9" y1="13" x2="15" y2="13"/>
                                    <line x1="9" y1="17" x2="13" y2="17"/>
                                </svg>
                                <div class="pdf-stamp">PDF</div>
                            </div>
                        </div>
                    </section>

                    <!-- Quick Actions 2x2 Grid -->
                    <section class="quick-actions-2x2">
                        <!-- Card 1: Chỉnh sửa PDF chuyên nghiệp -->
                        <a href="/editor" class="action-card" onclick="switchView('editor', event)">
                            <div class="action-main">
                                <div class="action-icon-box blue">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </div>
                                <div class="action-details">
                                    <h3>Chỉnh sửa PDF</h3>
                                    <p>Thêm chữ, vẽ, ký số, con dấu & ghi chú</p>
                                </div>
                            </div>
                            <span class="action-arrow">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        </a>

                        <!-- Card 2: Quản lý & Sắp xếp trang -->
                        <a href="/editor" class="action-card" onclick="switchView('editor', event); setTimeout(openPageManagerModal, 200);">
                            <div class="action-main">
                                <div class="action-icon-box green">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                                </div>
                                <div class="action-details">
                                    <h3>Quản lý trang PDF</h3>
                                    <p>Sắp xếp, xoay góc, nhân bản & xóa trang</p>
                                </div>
                            </div>
                            <span class="action-arrow">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        </a>

                        <!-- Card 3: Ghép & Tách tài liệu -->
                        <a href="/editor" class="action-card" onclick="switchView('editor', event); setTimeout(openPageManagerModal, 200);">
                            <div class="action-main">
                                <div class="action-icon-box purple">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                </div>
                                <div class="action-details">
                                    <h3>Ghép & Tách tài liệu</h3>
                                    <p>Nhập file ghép nối hoặc trích xuất nhóm trang</p>
                                </div>
                            </div>
                            <span class="action-arrow">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        </a>

                        <!-- Card 4: Nâng cấp gói PRO -->
                        <a href="/upgrade" class="action-card" onclick="switchView('upgrade', event)">
                            <div class="action-main">
                                <div class="action-icon-box orange">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                </div>
                                <div class="action-details">
                                    <h3>Nâng cấp gói PRO</h3>
                                    <p>Dung lượng không giới hạn & công cụ VIP</p>
                                </div>
                            </div>
                            <span class="action-arrow">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        </a>
                    </section>
                </div>

                <!-- MIDDLE SECTION: Documents Workspace (Left) + Quick Stats & Guide (Right) -->
                <div class="dashboard-grid" id="documentsSection">
                    <!-- Left Column -->
                    <div class="dashboard-main-col">
                        <!-- Toolbar & Tabs -->
                        <div class="section-toolbar">
                            <div class="tabs-group">
                                <button class="tab-btn active" id="tabDocList" onclick="toggleDocTab('list')">Danh sách tài liệu (<?= $totalUserDocs ?>)</button>
                                <button class="tab-btn" id="tabProcessing" onclick="toggleDocTab('processing')">Đang xử lý (0)</button>
                            </div>
                            <div class="toolbar-actions">
                                <button class="btn-primary" onclick="openUploadModal()">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    <span>Tải lên tệp PDF</span>
                                </button>
                                <a href="/documents" class="btn-outline" onclick="switchView('documents', event)" title="Quản lý toàn bộ tài liệu">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                                </a>
                            </div>
                        </div>

                        <!-- 6 Document Cards Row Rendered DYNAMICALLY from User's Real Documents -->
                        <div class="document-cards-row" id="docCardsContainer">
                            <?php if (empty($userDocs)): ?>
                            <div class="empty-cards-notice" style="grid-column: 1 / -1; background: var(--bg-card); border: 2px dashed var(--border); border-radius: 12px; padding: 36px 20px; text-align: center; width: 100%;">
                                <div style="font-size: 2.2rem; margin-bottom: 8px;">📂</div>
                                <?php if ($isLoggedIn): ?>
                                <h4 style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Chưa có tài liệu PDF nào</h4>
                                <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 16px;">Tải lên tệp PDF đầu tiên của bạn để bắt đầu chỉnh sửa, ghép hoặc tách trang</p>
                                <button type="button" class="btn-primary" onclick="openUploadModal()" style="display: inline-flex; align-items: center; gap: 8px; margin: 0 auto;">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    <span>Tải lên tệp PDF ngay</span>
                                </button>
                                <?php else: ?>
                                <h4 style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Bạn chưa đăng nhập tài khoản</h4>
                                <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 16px;">Đăng nhập hoặc đăng ký tài khoản để tải lên, lưu trữ và bảo vệ dữ liệu PDF của bạn</p>
                                <div style="display: inline-flex; gap: 10px; align-items: center; justify-content: center; flex-wrap: wrap;">
                                    <button type="button" class="btn-primary" onclick="openAuthModal('login')" style="display: inline-flex; align-items: center; gap: 8px;">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                                        <span>Đăng nhập ngay</span>
                                    </button>
                                    <button type="button" class="btn-outline" onclick="openAuthModal('register')">
                                        <span>Đăng ký miễn phí</span>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <?php foreach (array_slice($userDocs, 0, 6) as $doc): 
                                $dName = htmlspecialchars($doc['original_name']);
                                $dPages = (int)($doc['total_pages'] ?? 1);
                                $dSize = formatDocSize((int)($doc['file_size'] ?? 0));
                                $dStatus = $doc['status'] ?? 'valid';
                                $statusClass = $dStatus === 'error' ? 'danger' : ($dStatus === 'processing' ? 'info' : 'success');
                                $statusText = $dStatus === 'error' ? 'Lỗi' : ($dStatus === 'processing' ? 'Đang xử lý' : 'Hoàn tất');
                            ?>
                            <div class="doc-card" onclick="openDocInEditor(<?= $doc['id'] ?>, '<?= addslashes($dName) ?>')" title="Bấm để mở chỉnh sửa: <?= $dName ?>">
                                <div class="doc-thumbnail-realistic">
                                    <div class="doc-thumb-header"></div>
                                    <div class="doc-thumb-line"></div>
                                    <div class="doc-thumb-line medium"></div>
                                    <div class="doc-thumb-image-box">
                                        <svg class="svg-icon svg-icon-sm" style="color: #94A3B8;" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    </div>
                                    <span class="pdf-mini-tag">PDF</span>
                                </div>
                                <div class="doc-name" title="<?= $dName ?>"><?= $dName ?></div>
                                <div class="doc-info"><?= $dPages ?> trang • <?= $dSize ?></div>
                                <span class="status-pill <?= $statusClass ?>"><span class="status-dot"></span> <?= $statusText ?></span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Recent Documents Table (Real User Documents) -->
                        <div class="table-section">
                            <div class="table-header">
                                <h3 class="table-title">Tài liệu gần đây</h3>
                                <a href="/documents" class="storage-upgrade" style="margin: 0;" onclick="switchView('documents', event)">Xem tất cả (<?= $totalUserDocs ?>) →</a>
                            </div>
                            <div class="table-responsive">
                            <table class="custom-table recent-docs-table">
                                <thead>
                                    <tr>
                                        <th width="32" class="col-checkbox"><input type="checkbox" id="checkAllDocs"></th>
                                        <th class="col-name">Tên tệp</th>
                                        <th class="col-pages">Số trang</th>
                                        <th class="col-size">Dung lượng</th>
                                        <th class="col-date">Ngày tạo</th>
                                        <th class="col-status">Trạng thái</th>
                                        <th class="col-actions">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($userDocs)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px 20px;">
                                            <?php if ($isLoggedIn): ?>
                                            Chưa có tài liệu nào trong tài khoản. Hãy tải lên tệp PDF để quản lý.
                                            <?php else: ?>
                                            <span>Bạn chưa đăng nhập. Vui lòng <a href="javascript:void(0)" onclick="openAuthModal('login')" style="color: var(--primary); font-weight: 600; text-decoration: underline;">Đăng nhập tài khoản</a> để xem và quản lý danh sách tài liệu cá nhân của bạn.</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach (array_slice($userDocs, 0, 5) as $doc): 
                                        $dName = htmlspecialchars($doc['original_name']);
                                        $dPages = (int)($doc['total_pages'] ?? 1);
                                        $dSize = formatDocSize((int)($doc['file_size'] ?? 0));
                                        $dDate = formatDocDate($doc['created_at'] ?? null);
                                        $statusInfo = formatDocStatus($doc['status'] ?? 'valid');
                                        $statusClass = $statusInfo['class'];
                                        $statusText = $statusInfo['text'];
                                        $statusCode = $statusInfo['code'];
                                    ?>
                                    <tr class="recent-doc-row" data-doc-id="<?= $doc['id'] ?>" data-status="<?= $statusCode ?>">
                                        <td class="col-checkbox"><input type="checkbox" class="doc-checkbox" value="<?= $doc['id'] ?>"></td>
                                        <td class="col-name">
                                            <div class="file-cell">
                                                <div class="file-icon-pdf-badge">PDF</div>
                                                <div class="file-meta-wrap">
                                                    <span class="file-main-name" title="<?= $dName ?>"><?= $dName ?></span>
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
                                        <td class="col-pages"><?= $dPages ?></td>
                                        <td class="col-size"><?= $dSize ?></td>
                                        <td class="col-date"><?= $dDate ?></td>
                                        <td class="col-status"><span class="status-pill <?= $statusClass ?>"><span class="status-dot"></span> <?= $statusText ?></span></td>
                                        <td class="col-actions">
                                            <div class="actions-cell">
                                                <button class="btn-table-action btn-edit-doc-action" title="Chỉnh sửa PDF" onclick="openDocInEditor(<?= $doc['id'] ?>, '<?= addslashes($dName) ?>')">
                                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                    <span class="btn-label-mobile">Sửa</span>
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
                                <div class="table-footer-count">Hiển thị <?= min(5, $totalUserDocs) ?> / <?= $totalUserDocs ?> tài liệu</div>
                                <div class="pagination-controls">
                                    <button class="page-btn active">1</button>
                                </div>
                                <div class="table-footer-perpage">10 / trang</div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Real Quick Stats & Guide -->
                    <div class="dashboard-side-col">
                        <!-- Quick Stats 2x2 with Real User Stats -->
                        <div class="side-card">
                            <div class="side-card-header">
                                <h4 class="side-card-title">Thống kê nhanh</h4>
                                <span class="side-card-badge">Trực tiếp</span>
                            </div>
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-icon-sq blue">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    </div>
                                    <div class="stat-number" id="quickStatTotalDocs"><?= $totalUserDocs ?></div>
                                    <div class="stat-label">Tổng tài liệu</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon-sq purple">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                                    </div>
                                    <div class="stat-number" id="quickStatTotalPages"><?= $totalUserPages ?></div>
                                    <div class="stat-label">Tổng số trang</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon-sq green">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                    </div>
                                    <div class="stat-number" id="quickStatExportedDocs"><?= $totalExportedDocs ?></div>
                                    <div class="stat-label">Đã xuất file</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon-sq orange">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </div>
                                    <div class="stat-number" id="quickStatEditingDocs"><?= $totalEditingDocs ?></div>
                                    <div class="stat-label">Đang chỉnh sửa</div>
                                </div>
                            </div>
                        </div>

                        <!-- Guide List: Quy trình chỉnh sửa PDF chuyên nghiệp -->
                        <div class="side-card guide-side-card">
                            <div class="side-card-header">
                                <h4 class="side-card-title">Hướng dẫn quy trình</h4>
                                <span class="guide-version-tag">4 bước</span>
                            </div>
                            <div class="guide-steps-list">
                                <div class="guide-step-item">
                                    <div class="guide-step-indicator">
                                        <span class="step-num-badge">1</span>
                                        <div class="guide-step-line"></div>
                                    </div>
                                    <div class="guide-step-content">
                                        <div class="guide-step-title">Tải lên tệp PDF</div>
                                        <div class="guide-step-desc">Kéo thả tệp hoặc chọn từ thiết bị của bạn.</div>
                                    </div>
                                </div>
                                <div class="guide-step-item">
                                    <div class="guide-step-indicator">
                                        <span class="step-num-badge">2</span>
                                        <div class="guide-step-line"></div>
                                    </div>
                                    <div class="guide-step-content">
                                        <div class="guide-step-title">Biên tập nội dung trực quan</div>
                                        <div class="guide-step-desc">Thêm chữ, ghi chú, vẽ và chèn chữ ký số.</div>
                                    </div>
                                </div>
                                <div class="guide-step-item">
                                    <div class="guide-step-indicator">
                                        <span class="step-num-badge">3</span>
                                        <div class="guide-step-line"></div>
                                    </div>
                                    <div class="guide-step-content">
                                        <div class="guide-step-title">Sắp xếp & Quản lý trang</div>
                                        <div class="guide-step-desc">Ghép nối, xoay hướng, hoán đổi hoặc xóa trang.</div>
                                    </div>
                                </div>
                                <div class="guide-step-item">
                                    <div class="guide-step-indicator">
                                        <span class="step-num-badge">4</span>
                                    </div>
                                    <div class="guide-step-content">
                                        <div class="guide-step-title">Hoàn tất & Xuất file</div>
                                        <div class="guide-step-desc">Lưu và tải ngay tệp PDF thành phẩm chất lượng cao.</div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn-guide-action" onclick="switchView('editor')">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                <span>Mở trình biên tập ngay</span>
                            </button>
                        </div>

                        <!-- Promo / Feature Highlight Card -->
                        <div class="promo-card">
                            <div class="promo-badge-pill">Bản nâng cao</div>
                            <h4>Công cụ PDF Online<br>Toàn diện & Bảo mật</h4>
                            <p>Xử lý tức thì trên trình duyệt mà không làm lộ dữ liệu tài liệu cá nhân.</p>
                            <button type="button" class="btn-promo" onclick="switchView('upgrade')">
                                <span>Khám phá tính năng</span>
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                            </button>
                            <svg class="promo-folder-svg" viewBox="0 0 100 80" fill="none">
                                <path d="M10 20C10 14.4772 14.4772 10 20 10H38L48 22H80C85.5228 22 90 26.4772 90 32V65C90 70.5228 85.5228 75 80 75H20C14.4772 75 10 70.5228 10 65V20Z" fill="#93C5FD"/>
                                <path d="M15 30C15 25.5817 18.5817 22 23 22H77C81.4183 22 85 25.5817 85 30V65C85 69.4183 81.4183 73 77 73H23C18.5817 73 15 69.4183 15 65V30Z" fill="#3B82F6"/>
                                <rect x="30" y="32" width="40" height="24" rx="4" fill="white"/>
                                <rect x="36" y="38" width="18" height="3" rx="1.5" fill="#DC2626"/>
                                <rect x="36" y="44" width="28" height="2" rx="1" fill="#CBD5E1"/>
                                <rect x="36" y="48" width="24" height="2" rx="1" fill="#CBD5E1"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VIEW 2: QUẢN LÝ TÀI LIỆU RIÊNG BIỆT (DEDICATED DOCUMENTS PAGE) -->
