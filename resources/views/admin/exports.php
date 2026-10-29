                <div class="admin-page <?= $adminCurrentView === 'exports' ? 'active' : '' ?>" id="adminPage-exports">
                    <div class="admin-card">
                        <div class="admin-card-header">
                            <div>
                                <h3 class="admin-card-title">Toàn bộ Lịch sử Xuất & Bảng Đối Chiếu Nguồn</h3>
                                <p class="admin-card-subtitle">Theo dõi các tệp PDF đã xuất, xem Source Manifest và kiểm tra tính toàn vẹn</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">ID</th>
                                        <th>Tên tệp xuất kết quả</th>
                                        <th>Người thực hiện</th>
                                        <th>Dung lượng</th>
                                        <th>Số trang</th>
                                        <th>Thời gian xuất</th>
                                        <th style="text-align: right;">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($adminOutputsList)): ?>
                                    <tr><td colspan="7" class="text-center text-muted" style="padding: 30px;">Chưa có tệp xuất nào trên hệ thống</td></tr>
                                    <?php else: ?>
                                    <?php foreach ($adminOutputsList as $o): ?>
                                    <tr>
                                        <td style="font-weight: 700; color: var(--text-muted);"><?= $o['id'] ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <svg class="svg-icon" style="color: #16A34A; flex-shrink: 0;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                                <span style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($o['filename']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; font-size: 0.82rem;"><?= htmlspecialchars($o['user_name'] ?? 'Chưa rõ') ?></div>
                                            <div style="font-size: 0.72rem; color: var(--text-muted);"><?= htmlspecialchars($o['user_email'] ?? '') ?></div>
                                        </td>
                                        <td style="font-size: 0.8rem;"><?= formatDocSize((int)$o['file_size']) ?></td>
                                        <td style="font-size: 0.8rem; font-weight: 600;"><?= (int)$o['total_pages'] ?> trang</td>
                                        <td style="font-size: 0.75rem; color: var(--text-muted);"><?= formatDocDate($o['created_at']) ?></td>
                                        <td style="text-align: right;">
                                            <div class="admin-action-btn-group">
                                                <button type="button" class="btn-xs btn-outline" onclick="openManifestModal(<?= $o['id'] ?>)" title="Xem bảng đối chiếu trang nguồn">
                                                    Bảng đối chiếu
                                                </button>
                                                <a href="/api/download?file=<?= urlencode(basename($o['stored_path'] ?? $o['filename'])) ?>" class="btn-xs btn-primary" title="Tải xuống tệp PDF kết quả">
                                                    Tải PDF
                                                </a>
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

                <!-- ========================================== -->
                <!-- TRANG 4: QUẢN LÝ THANH TOÁN & DOANH THU    -->
                <!-- ========================================== -->
