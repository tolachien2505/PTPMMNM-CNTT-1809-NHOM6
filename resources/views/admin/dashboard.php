                <div class="admin-page <?= $adminCurrentView === 'dashboard' ? 'active' : '' ?>" id="adminPage-dashboard">
                    <?php
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

                    <!-- 4 Clean & Premium KPI Cards -->
                    <div class="admin-kpi-grid">
                        <!-- Card 1: Users -->
                        <div class="admin-kpi-card card-kpi-blue" onclick="switchAdminPage('users', event)" title="Xem Quản lý người dùng">
                            <div class="kpi-info-block">
                                <span class="kpi-label">Tổng Người Dùng</span>
                                <div class="kpi-value-row">
                                    <span class="kpi-value" id="kpiTotalUsers"><?= $adminStats['total_users'] ?></span>
                                </div>
                            </div>
                            <div class="kpi-icon-box bg-blue-3d">
                                <svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                        </div>

                        <!-- Card 2: Documents -->
                        <div class="admin-kpi-card card-kpi-emerald" onclick="switchAdminPage('documents', event)" title="Xem Toàn bộ tài liệu PDF">
                            <div class="kpi-info-block">
                                <span class="kpi-label">Tổng Tài Liệu PDF</span>
                                <div class="kpi-value-row">
                                    <span class="kpi-value" id="kpiTotalDocs"><?= $adminStats['total_docs'] ?></span>
                                </div>
                            </div>
                            <div class="kpi-icon-box bg-emerald-3d">
                                <svg class="svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                        </div>

                        <!-- Card 3: Storage -->
                        <div class="admin-kpi-card card-kpi-purple" onclick="switchAdminPage('maintenance', event)" title="Xem chi tiết Dung lượng & Bảo trì">
                            <div class="kpi-info-block">
                                <span class="kpi-label">Dung Lượng Đã Dùng</span>
                                <div class="kpi-value-row">
                                    <span class="kpi-value" id="kpiTotalStorage"><?= $adminStorageMb ?> <span class="kpi-value-unit">MB</span></span>
                                </div>
                            </div>
                            <div class="kpi-icon-box bg-purple-3d">
                                <svg class="svg-icon" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                            </div>
                        </div>

                        <!-- Card 4: Revenue -->
                        <div class="admin-kpi-card card-kpi-amber" onclick="switchAdminPage('payments', event)" title="Xem Chi tiết Doanh thu & Lịch sử nạp tiền">
                            <div class="kpi-info-block">
                                <span class="kpi-label">Doanh Thu</span>
                                <div class="kpi-value-row">
                                    <span class="kpi-value text-revenue-glow" id="kpiTotalRevenue"><?= number_format($adminStats['total_revenue'] ?? 0, 0, ',', '.') ?>đ</span>
                                </div>
                            </div>
                            <div class="kpi-icon-box bg-amber-3d">
                                <svg class="svg-icon" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/><path d="M12 15h2"/></svg>
                            </div>
                        </div>
                    </div>

                    <!-- 2 Side-by-Side Premium Cards -->
                    <div class="admin-dashboard-split">
                        <!-- Recent Users Card -->
                        <div class="admin-card card-recent-section">
                            <div class="admin-card-header">
                                <div class="card-header-title-wrap">
                                    <span class="card-accent-pill bg-blue"></span>
                                    <div>
                                        <h3 class="admin-card-title">Người dùng mới đăng ký</h3>
                                        <p class="admin-card-subtitle">5 tài khoản được tạo gần nhất trong hệ thống</p>
                                    </div>
                                </div>
                                <button type="button" class="btn-card-action" onclick="switchAdminPage('users', event)">
                                    <span>Xem tất cả</span>
                                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="admin-table dashboard-table dashboard-users-table">
                                    <thead>
                                        <tr>
                                            <th>Tên người dùng</th>
                                            <th>Vai trò</th>
                                            <th>Gói</th>
                                            <th>Ngày tạo</th>
                                        </tr>
                                    </thead>
                                    <tbody id="adminRecentUsersBody">
                                        <?php if (empty($adminUsersList)): ?>
                                        <tr><td colspan="4" class="text-center text-muted" style="padding: 24px;">Chưa có người dùng nào</td></tr>
                                        <?php else: ?>
                                        <?php foreach (array_slice($adminUsersList, 0, 5) as $u): 
                                            $initials = getUserInitials($u['full_name'] ?? $u['username']);
                                            $palette = $avatarPalettes[($u['id'] ?? 1) % 5];
                                            $isAdm = ($u['role'] ?? '') === 'admin';
                                            $isPro = strtoupper($u['plan'] ?? '') === 'PRO';
                                        ?>
                                        <tr class="table-hover-row">
                                            <td>
                                                <div class="user-chip-inline">
                                                    <div class="user-avatar-circle <?= $palette ?>"><?= $initials ?></div>
                                                    <div class="user-meta-info">
                                                        <div class="user-name-row">
                                                            <span class="user-full-name"><?= htmlspecialchars($u['full_name'] ?: $u['username']) ?></span>
                                                            <span class="admin-plan-pill mobile-only-plan <?= $isPro ? 'plan-pro' : 'plan-free' ?>"><?= $isPro ? 'PRO' : 'FREE' ?></span>
                                                        </div>
                                                        <span class="user-email-sub"><?= htmlspecialchars($u['email']) ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="admin-role-pill <?= $isAdm ? 'role-admin' : 'role-user' ?>">
                                                    <span class="pill-dot"></span>
                                                    <span><?= $isAdm ? 'Quản trị viên' : 'Người dùng' ?></span>
                                                </span>
                                            </td>
                                            <td class="desktop-only-plan-col">
                                                <span class="admin-plan-pill <?= $isPro ? 'plan-pro' : 'plan-free' ?>">
                                                    <?= $isPro ? 'PRO' : 'FREE' ?>
                                                </span>
                                            </td>
                                            <td class="date-col-text">
                                                <?= formatDocDate($u['created_at']) ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Recent Documents Card -->
                        <div class="admin-card card-recent-section">
                            <div class="admin-card-header">
                                <div class="card-header-title-wrap">
                                    <span class="card-accent-pill bg-emerald"></span>
                                    <div>
                                        <h3 class="admin-card-title">Tài liệu mới tải lên</h3>
                                        <p class="admin-card-subtitle">5 tài liệu PDF gần nhất lưu trên máy chủ</p>
                                    </div>
                                </div>
                                <button type="button" class="btn-card-action" onclick="switchAdminPage('documents', event)">
                                    <span>Xem tất cả</span>
                                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="admin-table dashboard-table dashboard-docs-table">
                                    <thead>
                                        <tr>
                                            <th>Tên tệp</th>
                                            <th>Chủ sở hữu</th>
                                            <th>Dung lượng</th>
                                            <th>Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody id="adminRecentDocsBody">
                                        <?php if (empty($adminDocsList)): ?>
                                        <tr><td colspan="4" class="text-center text-muted" style="padding: 24px;">Chưa có tài liệu nào</td></tr>
                                        <?php else: ?>
                                        <?php foreach (array_slice($adminDocsList, 0, 5) as $d): 
                                            $stInfo = formatDocStatus($d['status'] ?? 'uploaded');
                                        ?>
                                        <tr class="table-hover-row">
                                            <td>
                                                <div class="doc-file-inline">
                                                    <div class="doc-pdf-badge">PDF</div>
                                                    <div class="doc-meta-info">
                                                        <span class="doc-orig-name" title="<?= htmlspecialchars($d['original_name']) ?>">
                                                            <?= htmlspecialchars($d['original_name']) ?>
                                                        </span>
                                                        <span class="doc-page-sub"><?= (int)$d['total_pages'] ?> trang <span class="doc-sub-extra">• <?= formatDocSize((int)$d['file_size']) ?> • <?= htmlspecialchars($d['owner_name'] ?? 'Chưa rõ') ?></span></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="desktop-only-owner-col">
                                                <span class="doc-owner-chip"><?= htmlspecialchars($d['owner_name'] ?? 'Chưa rõ') ?></span>
                                            </td>
                                            <td class="size-col-text desktop-only-size-col">
                                                <span class="badge-size-pill"><?= formatDocSize((int)$d['file_size']) ?></span>
                                            </td>
                                            <td>
                                                <span class="doc-status-pill status-<?= $stInfo['code'] ?>">
                                                    <span class="pill-dot"></span>
                                                    <span><?= $stInfo['text'] ?></span>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
