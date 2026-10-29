            <!-- VIEW 5: LỊCH SỬ XUẤT & ĐỐI CHIẾU NGUỒN -->
            <div id="view-history" class="workspace-view <?= $currentView === 'history' ? 'active' : '' ?>">
                <div class="history-page-wrapper">
                    <!-- HEADER SECTION -->
                    <div class="history-header-card">
                        <div class="history-header-main">
                            <div class="history-title-wrap">
                                <div class="history-title-icon">
                                    <svg class="svg-icon" viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12 6 12 12 16 14"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="history-title">Lịch sử xuất & Bảng đối chiếu nguồn</h2>
                                    <p class="history-subtitle">Quản lý các tệp PDF đã xuất, tra cứu minh bạch nguồn gốc từng trang, xem trước hoặc tải về.</p>
                                </div>
                            </div>
                            
                            <div class="history-header-actions">
                                <div class="history-search-input-wrap">
                                    <svg class="svg-icon svg-icon-sm search-icon" viewBox="0 0 24 24">
                                        <circle cx="11" cy="11" r="8"/>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                    </svg>
                                    <input type="text" id="historySearchInput" placeholder="Tìm kiếm tệp xuất, ID..." oninput="filterHistoryTable(this.value)">
                                    <button class="clear-search-btn" id="historyClearSearch" onclick="clearHistorySearch()" style="display: none;" title="Xóa tìm kiếm">✕</button>
                                </div>

                                <button class="btn-refresh-history" onclick="loadExportHistory()" title="Tải lại danh sách">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10"/></svg>
                                    <span>Làm mới</span>
                                </button>

                                <button class="btn-clear-all-history" onclick="confirmClearAllHistory()" title="Xóa toàn bộ lịch sử xuất">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                    <span class="desktop-only-inline">Xóa tất cả</span>
                                </button>
                            </div>
                        </div>

                        <!-- STATS SUMMARY CARDS -->
                        <div class="history-stats-grid">
                            <div class="history-stat-card">
                                <div class="stat-card-icon icon-blue">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 12 18 15 15"/><line x1="12" y1="9" x2="12" y2="18"/></svg>
                                </div>
                                <div class="stat-card-content">
                                    <span class="stat-card-label">Tổng tệp xuất</span>
                                    <span class="stat-card-value" id="historyStatTotalCount">--</span>
                                </div>
                            </div>

                            <div class="history-stat-card">
                                <div class="stat-card-icon icon-purple">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                                </div>
                                <div class="stat-card-content">
                                    <span class="stat-card-label">Tổng dung lượng</span>
                                    <span class="stat-card-value" id="historyStatTotalSize">-- MB</span>
                                </div>
                            </div>

                            <div class="history-stat-card">
                                <div class="stat-card-icon icon-emerald">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                                </div>
                                <div class="stat-card-content">
                                    <span class="stat-card-label">Tổng số trang</span>
                                    <span class="stat-card-value" id="historyStatTotalPages">-- trang</span>
                                </div>
                            </div>

                            <div class="history-stat-card">
                                <div class="stat-card-icon icon-amber">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                </div>
                                <div class="stat-card-content">
                                    <span class="stat-card-label">Gói tài khoản</span>
                                    <span class="stat-card-value" id="historyStatPlan">--</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CONTENT CONTAINER -->
                    <div class="history-content-card">
                        <!-- DESKTOP TABLE VIEW -->
                        <div class="history-table-container desktop-history-view">
                            <table class="history-custom-table">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">Mã ID</th>
                                        <th>Tên tệp xuất</th>
                                        <th style="width: 120px; text-align: center;">Số trang</th>
                                        <th style="width: 130px; text-align: center;">Dung lượng</th>
                                        <th style="width: 170px;">Thời gian xuất</th>
                                        <th style="width: 220px; text-align: right;">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody id="historyTableBody">
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 36px 20px;">
                                            <div class="history-loading-pulse">
                                                <div class="history-spinner"></div>
                                                <span>Đang tải dữ liệu lịch sử xuất...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- MOBILE CARD LIST VIEW -->
                        <div class="history-mobile-list" id="historyMobileList">
                            <div class="history-loading-pulse" style="padding: 30px; text-align: center;">
                                <div class="history-spinner"></div>
                                <span>Đang tải dữ liệu...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL XEM TRƯỚC PDF (PREVIEW) -->
            <div id="previewPdfModal" class="modal-overlay" style="display: none;">
                <div class="modal-content modal-content-preview-pdf">
                    <div class="modal-header">
                        <div style="display: flex; align-items: center; gap: 10px; overflow: hidden;">
                            <div class="file-icon-pdf">PDF</div>
                            <h3 id="previewPdfModalTitle" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 1rem; margin: 0;">Xem trước tệp PDF</h3>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <a id="previewPdfModalDownloadBtn" href="#" class="btn-sm btn-primary" title="Tải về tệp này">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                <span>Tải về</span>
                            </a>
                            <button class="btn-close" onclick="closePreviewPdfModal()" title="Đóng">✕</button>
                        </div>
                    </div>
                    <div class="modal-body preview-pdf-body">
                        <iframe id="previewPdfIframe" src="about:blank" style="width: 100%; height: 100%; border: none; border-radius: 6px;"></iframe>
                    </div>
                </div>
            </div>
