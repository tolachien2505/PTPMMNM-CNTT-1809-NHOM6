                <div class="admin-page <?= $adminCurrentView === 'documents' ? 'active' : '' ?>" id="adminPage-documents">
                    <?php
                    $totalDocs = count($adminDocsList ?? []);
                    $totalBytes = 0;
                    $totalPages = 0;
                    $todayDocs = 0;
                    $todayStr = date('Y-m-d');

                    foreach (($adminDocsList ?? []) as $d) {
                        $totalBytes += (int)($d['file_size'] ?? 0);
                        $totalPages += (int)($d['total_pages'] ?? 0);
                        if (!empty($d['created_at']) && strpos($d['created_at'], $todayStr) === 0) {
                            $todayDocs++;
                        }
                    }
                    $totalSizeFormatted = formatDocSize($totalBytes);

                    if (!function_exists('getUserInitials')) {
                        function getUserInitials(?string $name): string {
                            $n = trim($name ?? 'User');
                            if (empty($n)) return 'U';
                            $words = preg_split('/\s+/', $n);
                            if (count($words) >= 2) {
                                return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1));
                            }
                            return mb_strtoupper(mb_substr($n, 0, 2));
                        }
                    }
                    $avatarPalettes = ['av-blue', 'av-purple', 'av-emerald', 'av-amber', 'av-rose'];
                    ?>

                    <!-- 1. Top 4 Executive KPI Cards -->
                    <div class="admin-kpi-grid docs-kpi-grid">
                        <!-- Card 1: Total Documents -->
                        <div class="admin-kpi-card card-kpi-blue" title="Tổng số tệp PDF đã tải lên toàn hệ thống">
                            <div class="kpi-info-block">
                                <span class="kpi-label">Tổng Tài Liệu PDF</span>
                                <div class="kpi-value-row">
                                    <span class="kpi-value"><?= number_format($totalDocs, 0, ',', '.') ?></span>
                                    <span class="kpi-value-unit">tệp</span>
                                </div>
                            </div>
                            <div class="kpi-icon-box bg-blue-3d">
                                <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                        </div>

                        <!-- Card 2: Total Storage -->
                        <div class="admin-kpi-card card-kpi-purple" title="Tổng dung lượng lưu trữ tài liệu gốc trên máy chủ">
                            <div class="kpi-info-block">
                                <span class="kpi-label">Dung Lượng Lưu Trữ</span>
                                <div class="kpi-value-row">
                                    <span class="kpi-value"><?= $totalSizeFormatted ?></span>
                                </div>
                            </div>
                            <div class="kpi-icon-box bg-purple-3d">
                                <svg class="svg-icon" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                            </div>
                        </div>

                        <!-- Card 3: Total Pages -->
                        <div class="admin-kpi-card card-kpi-amber" title="Tổng số trang PDF đã được phân tích và lập chỉ mục">
                            <div class="kpi-info-block">
                                <span class="kpi-label">Tổng Số Trang PDF</span>
                                <div class="kpi-value-row">
                                    <span class="kpi-value"><?= number_format($totalPages, 0, ',', '.') ?></span>
                                    <span class="kpi-value-unit">trang</span>
                                </div>
                            </div>
                            <div class="kpi-icon-box bg-yellow-gradient">
                                <svg class="svg-icon" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                            </div>
                        </div>

                        <!-- Card 4: Uploaded Today -->
                        <div class="admin-kpi-card card-kpi-emerald" title="Số lượng tài liệu người dùng tải lên hôm nay">
                            <div class="kpi-info-block">
                                <span class="kpi-label">Tải Lên Hôm Nay</span>
                                <div class="kpi-value-row">
                                    <span class="kpi-value"><?= number_format($todayDocs, 0, ',', '.') ?></span>
                                    <span class="kpi-value-unit">tệp mới</span>
                                </div>
                            </div>
                            <div class="kpi-icon-box bg-emerald-3d">
                                <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Main Documents Card -->
                    <div class="admin-card docs-table-card">
                        <div class="docs-card-header">
                            <div class="docs-header-title-wrap">
                                <h3 class="admin-card-title">Quản lý Toàn bộ Tài liệu PDF Hệ thống</h3>
                                <p class="admin-card-subtitle">Xem trực tiếp, biên tập bằng Editor, tải xuống tệp gốc hoặc xóa bỏ tài liệu bất kỳ</p>
                            </div>
                            <div class="docs-header-stat">
                                <span class="doc-count-pill">
                                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    <span>Hiển thị: <strong id="docTableCount"><?= count($adminDocsList) ?></strong> tài liệu</span>
                                </span>
                            </div>
                        </div>

                        <!-- 3. Filter Bar (Unified & Sleek Toolbar) -->
                        <div class="docs-filter-toolbar">
                            <div class="docs-search-box">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" id="adminDocSearchInput" placeholder="Tìm kiếm tên tệp, chủ sở hữu, email tài khoản..." oninput="adminFilterDocs()">
                            </div>
                            <div class="docs-status-filter-wrap">
                                <select id="adminDocStatusFilter" class="form-control docs-status-select" onchange="adminFilterDocs()">
                                    <option value="all">Tất cả trạng thái</option>
                                    <option value="uploaded">✓ Sẵn sàng (Tệp gốc)</option>
                                    <option value="editing">⏱ Đang chỉnh sửa</option>
                                    <option value="exported">★ Đã xuất file</option>
                                    <option value="error">✕ Lỗi tệp</option>
                                </select>
                            </div>
                        </div>

                        <!-- 4. Documents Table (Compact, High-End SaaS) -->
                        <div class="table-responsive">
                            <table class="admin-table admin-docs-table">
                                <thead>
                                    <tr>
                                        <th style="width: 55px; text-align: center;">ID</th>
                                        <th>Tên tệp PDF</th>
                                        <th>Chủ sở hữu</th>
                                        <th>Dung lượng</th>
                                        <th>Số trang</th>
                                        <th>Trạng thái</th>
                                        <th>Thời gian tải</th>
                                        <th style="text-align: right; min-width: 220px;">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody id="adminDocsTableBody">
                                    <?php if (empty($adminDocsList)): ?>
                                    <tr><td colspan="8" class="text-center text-muted" style="padding: 36px;">Chưa có tài liệu nào trên hệ thống</td></tr>
                                    <?php else: ?>
                                    <?php 
                                    $idx = 0;
                                    foreach ($adminDocsList as $d): 
                                        $stInfo = formatDocStatus($d['status'] ?? 'uploaded');
                                        $avatarClass = $avatarPalettes[$idx % count($avatarPalettes)];
                                        $idx++;
                                        $ownerName = htmlspecialchars($d['owner_name'] ?? 'Chưa rõ');
                                        $ownerEmail = htmlspecialchars($d['owner_email'] ?? '');
                                        $initials = getUserInitials($d['owner_name'] ?? 'User');
                                        $createdTs = strtotime($d['created_at'] ?? 'now');
                                        $timeStr = date('H:i:s', $createdTs);
                                        $dateStr = date('d/m/Y', $createdTs);
                                        $escapedName = htmlspecialchars(addslashes($d['original_name']));
                                        $fileName = htmlspecialchars($d['original_name']);
                                    ?>
                                    <tr class="admin-doc-row" 
                                        data-doc-id="<?= $d['id'] ?>"
                                        data-status="<?= $stInfo['code'] ?>"
                                        data-search="<?= htmlspecialchars(strtolower($d['original_name'] . ' ' . ($d['owner_name'] ?? '') . ' ' . ($d['owner_email'] ?? ''))) ?>">
                                        <td style="text-align: center;">
                                            <span class="doc-id-pill">#<?= $d['id'] ?></span>
                                        </td>
                                        <td>
                                            <div class="doc-file-cell">
                                                <div class="doc-file-icon-box" title="Định dạng tệp PDF">
                                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                                    <span class="file-ext-tag">PDF</span>
                                                </div>
                                                <div class="doc-file-info">
                                                    <a href="javascript:void(0)" onclick="openDocInEditor(<?= $d['id'] ?>, '<?= $escapedName ?>')" class="doc-filename-link" title="<?= $fileName ?>">
                                                        <?= $fileName ?>
                                                    </a>
                                                    <span class="doc-meta-sub"><?= (int)$d['total_pages'] ?> trang • <?= formatDocSize((int)$d['file_size']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="doc-owner-cell">
                                                <div class="user-avatar-mini <?= $avatarClass ?>"><?= $initials ?></div>
                                                <div class="owner-cell-meta">
                                                    <span class="owner-name-title"><?= $ownerName ?></span>
                                                    <span class="owner-email-sub"><?= $ownerEmail ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="doc-size-pill"><?= formatDocSize((int)$d['file_size']) ?></span>
                                        </td>
                                        <td>
                                            <span class="doc-page-badge">
                                                <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                                                <span><?= (int)$d['total_pages'] ?> trang</span>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($stInfo['code'] === 'exported'): ?>
                                                <span class="badge-status-dot badge-dot-exported"><span class="dot"></span> Đã xuất</span>
                                            <?php elseif ($stInfo['code'] === 'editing'): ?>
                                                <span class="badge-status-dot badge-dot-editing"><span class="dot"></span> Đang sửa</span>
                                            <?php elseif ($stInfo['code'] === 'error'): ?>
                                                <span class="badge-status-dot badge-dot-error"><span class="dot"></span> Lỗi tệp</span>
                                            <?php else: ?>
                                                <span class="badge-status-dot badge-dot-ready"><span class="dot"></span> Sẵn sàng</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="doc-time-col">
                                            <div class="dt-cell">
                                                <span class="dt-time"><?= $timeStr ?></span>
                                                <span class="dt-date"><?= $dateStr ?></span>
                                            </div>
                                        </td>
                                        <td style="text-align: right;">
                                            <div class="admin-doc-action-btns">
                                                <button type="button" class="btn-doc-action btn-doc-edit" onclick="openDocInEditor(<?= $d['id'] ?>, '<?= $escapedName ?>')" title="Mở trực tiếp trong Trình Chỉnh Sửa PDF">
                                                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                    <span>Biên tập</span>
                                                </button>
                                                <a href="/api/documents/raw?id=<?= $d['id'] ?>" class="btn-doc-action btn-doc-download" download="<?= $fileName ?>" title="Tải xuống tệp PDF gốc">
                                                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                                    <span>Tải về</span>
                                                </a>
                                                <button type="button" class="btn-doc-action btn-doc-del" onclick="adminDeleteDoc(<?= $d['id'] ?>, '<?= $escapedName ?>')" title="Xóa vĩnh viễn tệp này khỏi hệ thống">
                                                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                    <span>Xóa</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
