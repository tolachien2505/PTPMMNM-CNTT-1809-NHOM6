                <div class="admin-page <?= $adminCurrentView === 'users' ? 'active' : '' ?>" id="adminPage-users">
                    <div class="admin-card user-management-card">
                        <!-- Card Header -->
                        <div class="admin-card-header users-page-header">
                            <div>
                                <h3 class="admin-card-title">Danh sách người dùng</h3>
                                <p class="admin-card-subtitle">Quản lý phân quyền tài khoản, gói dịch vụ, trạng thái và bảo mật hệ thống</p>
                            </div>
                            <div class="admin-header-badges">
                                <span class="header-stat-pill pill-total">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                    <strong id="usersCountTotal"><?= count($adminUsersList) ?></strong> Người dùng
                                </span>
                                <span class="header-stat-pill pill-admin">
                                    <strong><?= $adminStats['total_admins'] ?? 1 ?></strong> Admin
                                </span>
                                <span class="header-stat-pill pill-pro">
                                    <strong><?= $adminStats['total_pro_users'] ?? 0 ?></strong> PRO
                                </span>
                            </div>
                        </div>

                        <!-- Filter Controls Bar -->
                        <div class="admin-filter-bar users-filter-bar">
                            <div class="admin-search-input-box">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" id="adminUserSearchInput" placeholder="Tìm kiếm theo Tên, Username hoặc Email..." oninput="adminFilterUsers()">
                            </div>
                            <div class="admin-filter-select-group">
                                <select id="adminUserRoleFilter" class="admin-select" onchange="adminFilterUsers()">
                                    <option value="all">Tất cả vai trò</option>
                                    <option value="admin">Quản trị viên (Admin)</option>
                                    <option value="user">Người dùng (User)</option>
                                </select>
                                <select id="adminUserPlanFilter" class="admin-select" onchange="adminFilterUsers()">
                                    <option value="all">Tất cả gói</option>
                                    <option value="PRO">Gói VIP (PRO)</option>
                                    <option value="FREE">Miễn phí (FREE)</option>
                                </select>
                                <select id="adminUserStatusFilter" class="admin-select" onchange="adminFilterUsers()">
                                    <option value="all">Tất cả trạng thái</option>
                                    <option value="active">Hoạt động</option>
                                    <option value="pending">Chờ xác minh</option>
                                    <option value="banned">Bị khóa</option>
                                </select>
                            </div>
                        </div>

                        <!-- 1. DESKTOP VIEW: BẢNG NGƯỜI DÙNG HIỆN ĐẠI (CHỈNH CHU THEO SCREENSHOT 3) -->
                        <div class="table-responsive users-desktop-table-wrap">
                            <table class="admin-table users-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;" class="col-id">#</th>
                                        <th>NGƯỜI DÙNG</th>
                                        <th>EMAIL</th>
                                        <th>VAI TRÒ</th>
                                        <th>GÓI</th>
                                        <th>TÀI LIỆU</th>
                                        <th>HẾT HẠN</th>
                                        <th>TRẠNG THÁI</th>
                                        <th style="text-align: right; width: 100px;">THAO TÁC</th>
                                    </tr>
                                </thead>
                                <tbody id="adminUsersTableBody">
                                    <?php foreach ($adminUsersList as $u): 
                                        $uMb = round(((int)($u['total_bytes'] ?? 0)) / (1024 * 1024), 2);
                                        $isSelf = ($u['id'] == ($currentUser['id'] ?? 0));
                                        $status = $u['status'] ?? 'active';
                                        $expFormatted = !empty($u['expires_at']) ? date('d/m/Y', strtotime($u['expires_at'])) : 'Vĩnh viễn';
                                        
                                        // JSON dữ liệu để nạp modal sửa nhanh
                                        $userJsonData = htmlspecialchars(json_encode([
                                            'id' => (int)$u['id'],
                                            'full_name' => $u['full_name'],
                                            'username' => $u['username'],
                                            'email' => $u['email'],
                                            'phone' => $u['phone'] ?? '',
                                            'role' => $u['role'],
                                            'plan' => $u['plan'] ?? 'FREE',
                                            'status' => $status,
                                            'email_verified' => (int)($u['email_verified'] ?? 1),
                                            'expires_at' => $u['expires_at'] ?? ''
                                        ]), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr class="admin-user-row desktop-row" 
                                        data-user-id="<?= $u['id'] ?>"
                                        data-role="<?= htmlspecialchars($u['role']) ?>"
                                        data-plan="<?= htmlspecialchars($u['plan'] ?? 'FREE') ?>"
                                        data-status="<?= htmlspecialchars($status) ?>"
                                        data-search="<?= htmlspecialchars(strtolower($u['full_name'] . ' ' . $u['username'] . ' ' . $u['email'] . ' ' . ($u['phone'] ?? ''))) ?>">
                                        <td class="col-id font-mono font-bold text-muted"><?= $u['id'] ?></td>
                                        <td>
                                            <div class="user-profile-cell">
                                                <div class="avatar-sm <?= $u['role'] === 'admin' ? 'avatar-admin' : 'avatar-user' ?>">
                                                    <?= mb_substr($u['full_name'], 0, 2) ?>
                                                </div>
                                                <div class="user-meta-group">
                                                    <div class="user-fullname">
                                                        <span class="user-name-text"><?= htmlspecialchars($u['full_name']) ?></span>
                                                        <?php if ($isSelf): ?>
                                                        <span class="badge-self">Bạn</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="user-username">@<?= htmlspecialchars($u['username']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="col-email">
                                            <div class="email-text"><?= htmlspecialchars($u['email']) ?></div>
                                            <?php if (!empty($u['phone'])): ?>
                                            <div class="phone-text"><?= htmlspecialchars($u['phone']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="admin-role-badge <?= $u['role'] ?>" id="userRoleBadge-<?= $u['id'] ?>">
                                                <?= $u['role'] === 'admin' ? 'Quản trị viên' : 'Người dùng' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="admin-plan-badge <?= strtolower($u['plan'] ?? 'free') ?>" id="userPlanBadge-<?= $u['id'] ?>">
                                                <?= htmlspecialchars($u['plan'] ?? 'FREE') ?>
                                            </span>
                                        </td>
                                        <td class="col-docs">
                                            <span class="docs-count"><strong><?= (int)($u['total_docs'] ?? 0) ?></strong> tệp</span>
                                            <small class="storage-text">(<?= $uMb ?> MB)</small>
                                        </td>
                                        <td class="col-expire font-mono text-muted">
                                            <span id="userExpireText-<?= $u['id'] ?>"><?= $expFormatted ?></span>
                                        </td>
                                        <td>
                                            <span class="user-status-badge status-<?= $status ?>" id="userStatusBadge-<?= $u['id'] ?>">
                                                <span class="status-dot"></span>
                                                <span class="status-text">
                                                    <?php 
                                                        if ($status === 'banned') echo 'Bị khóa';
                                                        elseif ($status === 'pending') echo 'Chờ xác minh';
                                                        else echo 'Hoạt động';
                                                    ?>
                                                </span>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <div class="admin-action-icon-group">
                                                <!-- Nút Sửa người dùng (Mở Modal Screenshot 4) -->
                                                <button type="button" class="btn-action-icon btn-edit-user" 
                                                    id="btnEditUser-<?= $u['id'] ?>"
                                                    data-user-json='<?= $userJsonData ?>'
                                                    onclick="adminOpenEditUserModal(this)"
                                                    title="Chỉnh sửa chi tiết người dùng">
                                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </button>

                                                <!-- Nút Xóa tài khoản -->
                                                <?php if (!$isSelf): ?>
                                                <button type="button" class="btn-action-icon btn-del-user" 
                                                    onclick="adminDeleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name'])) ?>')" 
                                                    title="Xóa tài khoản này">
                                                    <svg class="svg-icon" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- 2. MOBILE VIEW: DANH SÁCH THẺ NGƯỜI DÙNG TIỆN DỤNG TRÊN MÀN HÌNH ĐIỆN THOẠI (<= 768px) -->
                        <div class="users-mobile-list" id="adminUsersMobileList">
                            <?php foreach ($adminUsersList as $u): 
                                $uMb = round(((int)($u['total_bytes'] ?? 0)) / (1024 * 1024), 2);
                                $isSelf = ($u['id'] == ($currentUser['id'] ?? 0));
                                $status = $u['status'] ?? 'active';
                                $expFormatted = !empty($u['expires_at']) ? date('d/m/Y', strtotime($u['expires_at'])) : 'Vĩnh viễn';
                                
                                $userJsonData = htmlspecialchars(json_encode([
                                    'id' => (int)$u['id'],
                                    'full_name' => $u['full_name'],
                                    'username' => $u['username'],
                                    'email' => $u['email'],
                                    'phone' => $u['phone'] ?? '',
                                    'role' => $u['role'],
                                    'plan' => $u['plan'] ?? 'FREE',
                                    'status' => $status,
                                    'email_verified' => (int)($u['email_verified'] ?? 1),
                                    'expires_at' => $u['expires_at'] ?? ''
                                ]), ENT_QUOTES, 'UTF-8');
                            ?>
                            <div class="admin-user-row user-card-mobile" 
                                data-user-id="<?= $u['id'] ?>"
                                data-role="<?= htmlspecialchars($u['role']) ?>"
                                data-plan="<?= htmlspecialchars($u['plan'] ?? 'FREE') ?>"
                                data-status="<?= htmlspecialchars($status) ?>"
                                data-search="<?= htmlspecialchars(strtolower($u['full_name'] . ' ' . $u['username'] . ' ' . $u['email'] . ' ' . ($u['phone'] ?? ''))) ?>">
                                
                                <!-- Card Header: Avatar, Name, ID & Actions -->
                                <div class="user-card-top">
                                    <div class="user-card-identity">
                                        <div class="avatar-sm <?= $u['role'] === 'admin' ? 'avatar-admin' : 'avatar-user' ?>">
                                            <?= mb_substr($u['full_name'], 0, 2) ?>
                                        </div>
                                        <div class="user-card-names">
                                            <div class="user-fullname">
                                                <span class="user-name-text"><?= htmlspecialchars($u['full_name']) ?></span>
                                                <span class="user-id-pill">#<?= $u['id'] ?></span>
                                                <?php if ($isSelf): ?>
                                                <span class="badge-self">Bạn</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="user-username">@<?= htmlspecialchars($u['username']) ?></div>
                                        </div>
                                    </div>

                                    <!-- Quick Actions -->
                                    <div class="admin-action-icon-group">
                                        <button type="button" class="btn-action-icon btn-edit-user" 
                                            id="btnEditUserMob-<?= $u['id'] ?>"
                                            data-user-json='<?= $userJsonData ?>'
                                            onclick="adminOpenEditUserModal(this)"
                                            title="Sửa người dùng">
                                            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                        <?php if (!$isSelf): ?>
                                        <button type="button" class="btn-action-icon btn-del-user" 
                                            onclick="adminDeleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name'])) ?>')" 
                                            title="Xóa người dùng">
                                            <svg class="svg-icon" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Card Body: Email, Phone, Docs -->
                                <div class="user-card-body">
                                    <div class="user-card-info-item">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                        <span class="user-card-info-val"><?= htmlspecialchars($u['email']) ?></span>
                                    </div>
                                    <?php if (!empty($u['phone'])): ?>
                                    <div class="user-card-info-item">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                        <span class="user-card-info-val"><?= htmlspecialchars($u['phone']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="user-card-info-item">
                                        <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        <span class="user-card-info-val"><strong><?= (int)($u['total_docs'] ?? 0) ?></strong> tệp (<?= $uMb ?> MB)</span>
                                    </div>
                                </div>

                                <!-- Card Footer: Badges -->
                                <div class="user-card-footer">
                                    <div class="user-card-badges">
                                        <span class="admin-role-badge <?= $u['role'] ?>" id="userRoleBadgeMob-<?= $u['id'] ?>">
                                            <?= $u['role'] === 'admin' ? 'Quản trị viên' : 'Người dùng' ?>
                                        </span>
                                        <span class="admin-plan-badge <?= strtolower($u['plan'] ?? 'free') ?>" id="userPlanBadgeMob-<?= $u['id'] ?>">
                                            <?= htmlspecialchars($u['plan'] ?? 'FREE') ?>
                                        </span>
                                        <span class="user-status-badge status-<?= $status ?>" id="userStatusBadgeMob-<?= $u['id'] ?>">
                                            <span class="status-dot"></span>
                                            <span class="status-text">
                                                <?php 
                                                    if ($status === 'banned') echo 'Bị khóa';
                                                    elseif ($status === 'pending') echo 'Chờ xác minh';
                                                    else echo 'Hoạt động';
                                                ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="user-card-expire">
                                        <small>Hết hạn:</small> <span id="userExpireTextMob-<?= $u['id'] ?>"><?= $expFormatted ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                    </div>
                </div>
