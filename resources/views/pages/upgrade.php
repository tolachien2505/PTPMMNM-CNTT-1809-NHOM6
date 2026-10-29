            <div id="view-upgrade" class="workspace-view <?= $currentView === 'upgrade' ? 'active' : '' ?>">
                <div class="pricing-container">

                    <!-- 1. Top Active Plan & Resource Status Banner -->
                    <div class="user-plan-status-banner">
                        <div class="banner-plan-left">
                            <div class="plan-badge-box <?= $userPlan === 'PRO' ? 'badge-pro-gradient' : 'badge-free-style' ?>">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                <span class="plan-badge-text"><?= htmlspecialchars($userPlan) ?></span>
                            </div>
                            <div class="plan-status-meta">
                                <span class="plan-expiry-text">
                                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <?= htmlspecialchars($userExpiresText ?? 'Miễn phí vĩnh viễn') ?>
                                </span>
                            </div>
                        </div>

                        <!-- 3 Resource Usage Meters -->
                        <div class="banner-meters-group">
                            <!-- Meter 1: Uploaded Documents -->
                            <div class="resource-meter-item" title="Số tài liệu đã tải lên hệ thống">
                                <div class="meter-info-line">
                                    <span class="meter-label">
                                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        <span>Tài liệu</span>
                                    </span>
                                    <span class="meter-values">
                                        <strong><?= $totalUserDocs ?></strong> / <?= $userPlan === 'PRO' ? 'Không giới hạn' : '10 tệp' ?>
                                        <span class="meter-pct">(<?= $userPlan === 'PRO' ? '<1%' : min(100, round(($totalUserDocs / 10) * 100)) . '%' ?>)</span>
                                    </span>
                                </div>
                                <div class="meter-bar-track">
                                    <div class="meter-bar-fill" style="width: <?= $userPlan === 'PRO' ? min(100, max(2, round(($totalUserDocs / 100) * 100))) : min(100, round(($totalUserDocs / 10) * 100)) ?>%;"></div>
                                </div>
                            </div>

                            <!-- Meter 2: Cloud Storage -->
                            <div class="resource-meter-item" title="Dung lượng lưu trữ đám mây đã sử dụng">
                                <div class="meter-info-line">
                                    <span class="meter-label">
                                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
                                        <span>Lưu trữ</span>
                                    </span>
                                    <span class="meter-values">
                                        <strong><?= $userStorageMb ?> MB</strong> / <?= $userPlan === 'PRO' ? '2.048 MB' : '100 MB' ?>
                                        <span class="meter-pct">(<?= $storagePercent ?>%)</span>
                                    </span>
                                </div>
                                <div class="meter-bar-track">
                                    <div class="meter-bar-fill meter-fill-storage" style="width: <?= min(100, max(2, $storagePercent)) ?>%;"></div>
                                </div>
                            </div>

                            <!-- Meter 3: Daily Downloads -->
                            <div class="resource-meter-item" title="Lượt tải tệp trong ngày hôm nay">
                                <div class="meter-info-line">
                                    <span class="meter-label">
                                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                        <span>Lượt tải</span>
                                    </span>
                                    <span class="meter-values">
                                        <strong>0</strong> / <?= $userPlan === 'PRO' ? 'Không giới hạn' : '3 lượt/ngày' ?>
                                    </span>
                                </div>
                                <div class="meter-bar-track">
                                    <div class="meter-bar-fill meter-fill-downloads" style="width: <?= $userPlan === 'PRO' ? '5%' : '0%' ?>;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons on Right -->
                        <div class="banner-actions-right">
                            <?php if ($userPlan === 'PRO'): ?>
                                <button type="button" class="btn-banner-action btn-banner-renew" onclick="openVietQrModal('PRO', 99000)">
                                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                    <span>Gia hạn</span>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-banner-action btn-banner-upgrade" onclick="openVietQrModal('PRO', 99000)">
                                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                    <span>Nâng cấp</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 2. Pricing Cards Grid (2 Columns Centered: BASIC & PRO) -->
                    <div class="pricing-cards-grid pricing-cards-2col">

                        <!-- GÓI 1: BASIC / FREE -->
                        <div class="pricing-card <?= ($userPlan === 'FREE') ? 'current-tier' : '' ?>">
                            <?php if ($userPlan === 'FREE'): ?>
                                <div class="card-current-badge">Hiện tại</div>
                            <?php endif; ?>
                            <div class="card-plan-header">
                                <div class="card-tag-pill tag-basic">BASIC</div>
                                <p class="card-tier-desc">Lý tưởng cho nhu cầu đọc & chỉnh sửa tài liệu cơ bản.</p>
                                <div class="card-tier-price">
                                    <span class="price-val">0đ</span>
                                    <span class="price-period">/vĩnh viễn</span>
                                </div>
                            </div>

                            <div class="card-features-heading">TÍNH NĂNG</div>
                            <div class="card-plan-features">
                                <div class="feature-item"><span class="feature-icon check">✓</span> Tối đa 10 file tải lên</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Dung lượng lưu trữ 100 MB</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Chỉnh sửa PDF cơ bản (tối đa 20 trang)</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Lưu trữ file trong 7 ngày</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Tải xuống tối đa 3 lượt / ngày</div>
                                <div class="feature-item disabled"><span class="feature-icon cross">✕</span> Không hỗ trợ công cụ nâng cao</div>
                                <div class="feature-item disabled"><span class="feature-icon cross">✕</span> Không có hàng đợi ưu tiên tốc độ</div>
                            </div>

                            <div class="card-plan-action">
                                <?php if ($userPlan === 'FREE'): ?>
                                    <button type="button" class="btn-tier-action btn-tier-current" disabled>Đang sử dụng</button>
                                <?php else: ?>
                                    <button type="button" class="btn-tier-action btn-tier-secondary" onclick="changePlan('FREE')">Chuyển về gói FREE</button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- GÓI 2: PRO (99K/NGÀY - POPULAR & HIGHLIGHTED) -->
                        <div class="pricing-card popular <?= ($userPlan === 'PRO') ? 'current-tier' : '' ?>">
                            <?php if ($userPlan === 'PRO'): ?>
                                <div class="card-current-badge">Hiện tại</div>
                            <?php else: ?>
                                <div class="card-popular-badge">Khuyên dùng</div>
                            <?php endif; ?>

                            <div class="card-plan-header">
                                <div class="card-tag-pill tag-pro">PRO</div>
                                <p class="card-tier-desc">Chỉnh sửa toàn diện, xử lý cường độ cao và không giới hạn.</p>
                                <div class="card-tier-price">
                                    <span class="price-val" id="proPriceValue">99.000đ</span>
                                    <span class="price-period" id="proPricePeriod">/Tháng</span>
                                </div>
                            </div>

                            <div class="card-features-heading">TÍNH NĂNG</div>
                            <div class="card-plan-features">
                                <div class="feature-item"><span class="feature-icon check">✓</span> Không giới hạn số file tải lên</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Dung lượng lưu trữ 2 GB Cloud SSD</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Chỉnh sửa toàn diện, không giới hạn trang</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Lưu trữ file vĩnh viễn</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Tải xuống không giới hạn lượt</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Hàng đợi ưu tiên siêu tốc</div>
                                <div class="feature-item"><span class="feature-icon check">✓</span> Hỗ trợ kỹ thuật ưu tiên 24/7</div>
                            </div>

                            <div class="card-plan-action">
                                <?php if ($userPlan === 'PRO'): ?>
                                    <button type="button" class="btn-tier-action btn-tier-renew" onclick="openVietQrModal('PRO', 99000)">Gia hạn gói</button>
                                <?php else: ?>
                                    <button type="button" class="btn-tier-action btn-tier-primary" onclick="openVietQrModal('PRO', 99000)">Chọn gói PRO</button>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                    <!-- 4. Payment History Table matching Screenshot 1 -->
                    <div class="user-payment-history-card">
                        <div class="history-card-header">
                            <div class="history-title-group">
                                <svg class="svg-icon svg-icon-sm text-primary" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <h3 class="history-section-title">Lịch sử thanh toán</h3>
                            </div>
                            <button type="button" class="btn-refresh-history" onclick="refreshUserInvoices()" id="btnRefreshInvoices">
                                <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                <span>Làm mới</span>
                            </button>
                        </div>

                        <!-- Table on Desktop, Cards on Mobile -->
                        <div class="history-table-responsive">
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th>MÃ</th>
                                        <th>GÓI</th>
                                        <th>SỐ TIỀN</th>
                                        <th>NGÀY TẠO</th>
                                        <th>PHƯƠNG THỨC</th>
                                        <th>TRẠNG THÁI</th>
                                        <th style="text-align: right;">THAO TÁC</th>
                                    </tr>
                                </thead>
                                <tbody id="userInvoiceTableBody">
                                    <?php if (empty($userInvoices)): ?>
                                        <tr class="empty-row">
                                            <td colspan="7" class="text-center text-muted py-4">Chưa có giao dịch thanh toán nào được ghi nhận.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($userInvoices as $inv): 
                                            $isPaid = ((int)$inv['status'] === 1);
                                            $isFailed = ((int)$inv['status'] === 2);
                                            $transCode = htmlspecialchars($inv['trans_id']);
                                            $amountFmt = number_format($inv['amount'], 0, ',', '.') . 'đ';
                                            $dateFmt = !empty($inv['created_at']) ? date('H:i:s d/m/Y', strtotime($inv['created_at'])) : date('H:i:s d/m/Y', $inv['create_time']);
                                        ?>
                                        <tr id="userInvRow_<?= $inv['id'] ?>">
                                            <td>
                                                <span class="user-inv-code"><?= $transCode ?></span>
                                            </td>
                                            <td>
                                                <div class="user-inv-plan-title">
                                                    <strong>Chuyên nghiệp (PRO)</strong>
                                                    <span class="inv-plan-sub">Tự động kích hoạt</span>
                                                </div>
                                            </td>
                                            <td>
                                                <strong class="user-inv-amount"><?= $amountFmt ?></strong>
                                            </td>
                                            <td class="user-inv-time">
                                                <?= $dateFmt ?>
                                            </td>
                                            <td class="user-inv-method">
                                                <span>Chuyển khoản VietQR</span>
                                            </td>
                                            <td>
                                                <?php if ($isPaid): ?>
                                                    <span class="inv-status-badge badge-success">Đã thanh toán</span>
                                                <?php elseif ($isFailed): ?>
                                                    <span class="inv-status-badge badge-failed">Thất bại</span>
                                                <?php else: ?>
                                                    <span class="inv-status-badge badge-waiting">Chờ thanh toán</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <?php if (!$isPaid && !$isFailed): ?>
                                                    <button type="button" class="btn-user-view-qr" onclick="openVietQrModal('PRO', <?= (int)$inv['amount'] ?>, '<?= $transCode ?>', '<?= htmlspecialchars($inv['description']) ?>')" title="Xem mã VietQR để hoàn tất thanh toán">
                                                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                                                        <span>Xem QR</span>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted text-xs">Đã hoàn tất</span>
                                                <?php endif; ?>
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
