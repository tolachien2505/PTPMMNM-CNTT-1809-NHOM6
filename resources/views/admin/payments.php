                <div class="admin-page <?= $adminCurrentView === 'payments' ? 'active' : '' ?>" id="adminPage-payments">
                    <!-- Actions Header (Removed subtitle as requested) -->
                    <div class="payments-top-actions">
                        <div class="payments-title-group">
                            <h2 class="payments-section-title">Thanh Toán & Doanh Thu Gói Dịch Vụ</h2>
                        </div>
                        <div class="payments-btn-group">
                            <button type="button" class="btn-outline btn-sync-acb" onclick="adminSyncBankTransactions()" id="btnSyncBank" title="Đồng bộ ngay các giao dịch mới nhất từ ACB/Sieuthicode">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                                <span>Đồng bộ giao dịch ACB</span>
                            </button>
                            <button type="button" class="btn-primary btn-config-bank" onclick="openBankSettingsModal()" title="Cấu hình tài khoản ngân hàng và Token API">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                <span>Cấu hình</span>
                            </button>
                        </div>
                    </div>

                    <!-- Top Overview Section: Left 2x2 KPI Cards + Right Monthly Revenue Bar Chart -->
                    <div class="payments-overview-split">
                        <!-- 4 Summary KPI Cards in 2x2 Grid -->
                        <div class="payment-stats-grid">
                            <div class="payment-stat-card card-alltime">
                                <div class="stat-meta">
                                    <div class="stat-amount" id="statAllTime"><?= number_format($adminInvoicesData['stats']['all_time'] ?? $adminStats['total_revenue'] ?? 0, 0, ',', '.') ?>đ</div>
                                    <div class="stat-label">Toàn thời gian</div>
                                </div>
                                <div class="stat-icon-circle bg-red-gradient">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                </div>
                            </div>

                            <div class="payment-stat-card card-month">
                                <div class="stat-meta">
                                    <div class="stat-amount" id="statMonth"><?= number_format($adminInvoicesData['stats']['month'] ?? 0, 0, ',', '.') ?>đ</div>
                                    <div class="stat-label"><?= $adminInvoicesData['stats']['month_name'] ?? ('Tháng ' . date('m/Y')) ?></div>
                                </div>
                                <div class="stat-icon-circle bg-blue-gradient">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                </div>
                            </div>

                            <div class="payment-stat-card card-week">
                                <div class="stat-meta">
                                    <div class="stat-amount" id="statWeek"><?= number_format($adminInvoicesData['stats']['week'] ?? 0, 0, ',', '.') ?>đ</div>
                                    <div class="stat-label">Trong tuần</div>
                                </div>
                                <div class="stat-icon-circle bg-yellow-gradient">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                </div>
                            </div>

                            <div class="payment-stat-card card-today">
                                <div class="stat-meta">
                                    <div class="stat-amount" id="statToday"><?= number_format($adminInvoicesData['stats']['today'] ?? 0, 0, ',', '.') ?>đ</div>
                                    <div class="stat-label">Hôm nay</div>
                                </div>
                                <div class="stat-icon-circle bg-purple-gradient">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                </div>
                            </div>
                        </div>

                        <!-- Revenue Bar Chart with Options: Tháng 10, 1 Năm, 7 Ngày -->
                        <div class="admin-card chart-container-card">
                            <div class="chart-card-header">
                                <div class="chart-title">
                                    <span class="chart-accent-bar"></span>
                                    <span id="chartPeriodTitle"><?= htmlspecialchars($adminInvoicesData['stats']['chart_title'] ?? ('THỐNG KÊ NẠP TIỀN ' . mb_strtoupper($adminInvoicesData['stats']['month_name'] ?? ('THÁNG ' . date('m/Y'))))) ?></span>
                                </div>
                                <div class="chart-header-right">
                                    <div class="chart-legend">
                                        <span class="legend-color-box"></span>
                                        <span class="legend-text">Nạp tự động</span>
                                    </div>
                                    <!-- Option selector buttons: Tháng 10, 1 Năm, 7 Ngày -->
                                    <div class="chart-period-tabs">
                                        <button type="button" class="btn-period-tab active" data-period="month" onclick="switchAdminChartPeriod('month', this)">Tháng 10</button>
                                        <button type="button" class="btn-period-tab" data-period="year" onclick="switchAdminChartPeriod('year', this)">1 Năm</button>
                                        <button type="button" class="btn-period-tab" data-period="7days" onclick="switchAdminChartPeriod('7days', this)">7 Ngày</button>
                                    </div>
                                </div>
                            </div>

                            <?php 
                            $chartDataList = $adminInvoicesData['stats']['chart_data'] ?? [];
                            $chartMax = $adminInvoicesData['stats']['chart_max'] ?? 300000;
                            $step = $chartMax / 5;
                            $yTicks = [
                                $chartMax,
                                round($chartMax - $step),
                                round($chartMax - $step * 2),
                                round($chartMax - $step * 3),
                                round($chartMax - $step * 4),
                                0
                            ];
                            ?>
                            <div class="payment-chart-with-axis">
                                <!-- Y Axis Labels -->
                                <div class="chart-y-axis">
                                    <?php foreach ($yTicks as $tick): ?>
                                    <span class="y-tick-label"><?= number_format($tick, 0, ',', '.') ?>đ</span>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Plot area with gridlines and bars -->
                                <div class="chart-plot-area">
                                    <div class="chart-gridlines">
                                        <?php foreach ($yTicks as $tick): ?>
                                        <div class="chart-gridline"></div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="payment-chart-bars" id="paymentChartBars">
                                        <?php foreach ($chartDataList as $item): 
                                            $amount = floatval($item['amount'] ?? 0);
                                            $label = $item['label'] ?? '';
                                            $fullLabel = $item['full_label'] ?? $label;
                                            $showLabel = !empty($item['show_label']);
                                            $pct = min(100, round(($amount / ($chartMax ?: 1)) * 100, 1));
                                        ?>
                                        <div class="chart-bar-col" title="<?= htmlspecialchars($fullLabel) ?>: <?= number_format($amount, 0, ',', '.') ?>đ">
                                            <div class="bar-value-tooltip"><?= number_format($amount, 0, ',', '.') ?>đ</div>
                                            <div class="bar-track">
                                                <div class="bar-fill <?= $amount > 0 ? 'active' : '' ?>" style="height: <?= $pct ?>%;"></div>
                                            </div>
                                            <?php if ($showLabel): ?>
                                                <span class="bar-label-clean"><?= htmlspecialchars($label) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Invoices Table Section (Refined, Compact & SaaS-Grade) -->
                    <div class="admin-card invoice-list-card">
                        <div class="invoice-table-header">
                            <div class="invoice-title">
                                <span class="chart-accent-bar"></span>
                                <span>LỊCH SỬ HÓA ĐƠN NẠP TIỀN</span>
                            </div>
                        </div>

                        <!-- Compact & Clean Filter Form -->
                        <div class="invoice-filter-bar">
                            <div class="filter-input-wrap">
                                <input type="number" class="filter-input" id="filterInvUserId" placeholder="ID thành viên" onkeydown="if(event.key==='Enter') adminFilterInvoices()">
                            </div>
                            <div class="filter-input-wrap">
                                <input type="text" class="filter-input" id="filterInvUsername" placeholder="Username / Email" onkeydown="if(event.key==='Enter') adminFilterInvoices()">
                            </div>
                            <div class="filter-input-wrap">
                                <input type="text" class="filter-input font-mono" id="filterInvTransId" placeholder="Mã giao dịch" onkeydown="if(event.key==='Enter') adminFilterInvoices()">
                            </div>
                            <div class="filter-input-wrap">
                                <select class="filter-input filter-select" id="filterInvBank" onchange="adminFilterInvoices()">
                                    <option value="all">Tất cả ngân hàng</option>
                                    <option value="ACB">ACB — Ngân hàng Á Châu</option>
                                    <option value="Vietcombank">Vietcombank</option>
                                    <option value="MBBank">MBBank</option>
                                    <option value="TPBank">TPBank</option>
                                    <option value="VPBank">VPBank</option>
                                </select>
                            </div>
                            <div class="filter-input-wrap">
                                <input type="text" onfocus="(this.type='date')" onblur="if(!this.value)this.type='text'" class="filter-input" id="filterInvDate" placeholder="Chọn ngày" onchange="adminFilterInvoices()">
                            </div>
                            <div class="filter-btn-wrap">
                                <button type="button" class="btn-filter-search" onclick="adminFilterInvoices()">
                                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                    <span>Tìm kiếm</span>
                                </button>
                                <button type="button" class="btn-filter-reset" onclick="adminResetInvoiceFilters()">
                                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    <span>Xóa lọc</span>
                                </button>
                            </div>
                        </div>

                        <!-- Secondary Controls -->
                        <div class="invoice-controls-row">
                            <div class="control-show">
                                <span>Hiển thị:</span>
                                <select id="filterInvLimit" onchange="adminFilterInvoices()" class="control-select">
                                    <option value="10">10 dòng</option>
                                    <option value="25">25 dòng</option>
                                    <option value="50">50 dòng</option>
                                    <option value="100">100 dòng</option>
                                </select>
                            </div>
                            <div class="control-sort">
                                <span>Sắp xếp:</span>
                                <select id="filterInvSort" class="control-select" onchange="adminFilterInvoices()">
                                    <option value="desc">Mới nhất trước</option>
                                    <option value="asc">Cũ nhất trước</option>
                                </select>
                            </div>
                        </div>

                        <!-- Compact & Refined Table -->
                        <div class="table-responsive">
                            <table class="admin-table invoice-table">
                                <thead>
                                    <tr>
                                        <th style="width: 80px; text-align: center;">Thao tác</th>
                                        <th>Thành viên</th>
                                        <th>Mã GD</th>
                                        <th>Trạng thái</th>
                                        <th>Số tiền</th>
                                        <th>Thực nhận</th>
                                        <th>Ngân hàng</th>
                                        <th>Thời gian</th>
                                        <th>Cập nhật</th>
                                    </tr>
                                </thead>
                                <tbody id="invoiceTableBody">
                                    <?php 
                                    $invoicesList = $adminInvoicesData['invoices'] ?? [];
                                    if (empty($invoicesList)): 
                                    ?>
                                    <tr><td colspan="9" class="text-center text-muted" style="padding: 24px;">Chưa có giao dịch hóa đơn nào</td></tr>
                                    <?php else: ?>
                                    <?php foreach ($invoicesList as $inv): 
                                        $userEmail = htmlspecialchars($inv['email'] ?? $inv['username'] ?? 'User');
                                    ?>
                                    <tr id="invRow_<?= $inv['id'] ?>">
                                        <td style="text-align: center;">
                                            <div class="inv-action-btns">
                                                <button type="button" class="btn-inv-action btn-inv-edit" onclick="openEditInvoiceModal(<?= $inv['id'] ?>, <?= $inv['status'] ?>, <?= $inv['amount'] ?>)" title="Chỉnh sửa hóa đơn">
                                                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </button>
                                                <button type="button" class="btn-inv-action btn-inv-del" onclick="adminDeleteInvoice(<?= $inv['id'] ?>)" title="Xóa hóa đơn">
                                                    <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="inv-user-cell">
                                                <span class="user-id-pill">#<?= $inv['user_id'] ?></span>
                                                <a href="javascript:void(0)" class="inv-username-link" title="<?= $userEmail ?>">
                                                    <?= $userEmail ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="inv-code-pill"><?= htmlspecialchars($inv['trans_id']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ((int)$inv['status'] === 1): ?>
                                                <span class="badge-status-dot badge-dot-paid"><span class="dot"></span> Đã thanh toán</span>
                                            <?php else: ?>
                                                <span class="badge-status-dot badge-dot-pending"><span class="dot"></span> Chờ thanh toán</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong class="inv-money-green"><?= number_format($inv['amount'], 0, ',', '.') ?>đ</strong>
                                        </td>
                                        <td>
                                            <strong class="inv-money-green"><?= number_format($inv['amount'], 0, ',', '.') ?>đ</strong>
                                        </td>
                                        <td>
                                            <span class="inv-bank-tag"><?= htmlspecialchars($inv['payment_method']) ?></span>
                                        </td>
                                        <td class="inv-time-col">
                                            <div class="dt-cell">
                                                <span class="dt-time"><?= date('H:i:s', $inv['create_time'] ?: strtotime($inv['created_at'])) ?></span>
                                                <span class="dt-date"><?= date('d/m/Y', $inv['create_time'] ?: strtotime($inv['created_at'])) ?></span>
                                            </div>
                                        </td>
                                        <td class="inv-time-col">
                                            <div class="dt-cell">
                                                <?php $upTs = !empty($inv['updated_at']) ? strtotime($inv['updated_at']) : strtotime($inv['created_at']); ?>
                                                <span class="dt-time"><?= date('H:i:s', $upTs) ?></span>
                                                <span class="dt-date"><?= date('d/m/Y', $upTs) ?></span>
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
