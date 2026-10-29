<?php
namespace App\Controllers;

use PDO;
use Exception;

class PaymentController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Lấy cấu hình ngân hàng hiện tại (Mask Token để đảm bảo an toàn bảo mật)
     */
    public function getBankSettings(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM bank_settings ORDER BY id ASC LIMIT 1");
        $bank = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bank) {
            // Mặc định khởi tạo nếu chưa có
            $default = [
                'bank_code' => 'ACB',
                'bank_name' => 'Ngân hàng TMCP Á Châu',
                'account_number' => '17449371',
                'account_name' => 'VU VAN CHIEN',
                'api_token' => '',
                'api_url' => 'https://api.sieuthicode.net/historyapiacbv2/',
                'memo_prefix' => 'PDFPRO',
                'note' => 'Nhận thanh toán gói PRO PDF Online tự động qua ACB'
            ];
            $stmtInsert = $this->pdo->prepare("INSERT INTO bank_settings (bank_code, bank_name, account_number, account_name, api_token, api_url, memo_prefix, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtInsert->execute([
                $default['bank_code'], $default['bank_name'], $default['account_number'],
                $default['account_name'], $default['api_token'], $default['api_url'],
                $default['memo_prefix'], $default['note']
            ]);
            $bank = $default;
        }

        // Token chỉ trả về dạng mask che giấu trừ khi rỗng
        $rawToken = $bank['api_token'] ?? '';
        $bank['has_token'] = !empty($rawToken);
        $bank['api_token_masked'] = !empty($rawToken) ? (substr($rawToken, 0, 4) . '••••••••' . substr($rawToken, -4)) : '';
        unset($bank['api_token']); // Không gửi raw token về client

        return $bank;
    }

    /**
     * Cập nhật thông tin ngân hàng và token vào Database
     */
    public function updateBankSettings(array $data): array
    {
        $bankCode = trim($data['bank_code'] ?? 'ACB');
        $bankName = trim($data['bank_name'] ?? 'Ngân hàng TMCP Á Châu');
        $accountNumber = trim($data['account_number'] ?? '17449371');
        $accountName = strtoupper(trim($data['account_name'] ?? 'VU VAN CHIEN'));
        $apiUrl = trim($data['api_url'] ?? 'https://api.sieuthicode.net/historyapiacbv2/');
        $memoPrefix = strtoupper(trim($data['memo_prefix'] ?? 'PDFPRO'));
        $note = trim($data['note'] ?? '');
        $newToken = trim($data['api_token'] ?? '');

        // Lấy record hiện tại
        $stmtCur = $this->pdo->query("SELECT id, api_token FROM bank_settings ORDER BY id ASC LIMIT 1");
        $cur = $stmtCur->fetch(PDO::FETCH_ASSOC);

        if ($cur) {
            // Nếu người dùng không nhập token mới hoặc để placeholder, giữ nguyên token cũ
            $tokenToSave = ($newToken !== '' && !str_contains($newToken, '••••')) ? $newToken : ($cur['api_token'] ?? '');
            
            $stmtUpdate = $this->pdo->prepare("UPDATE bank_settings SET 
                bank_code = ?, bank_name = ?, account_number = ?, account_name = ?, 
                api_token = ?, api_url = ?, memo_prefix = ?, note = ? WHERE id = ?");
            $stmtUpdate->execute([
                $bankCode, $bankName, $accountNumber, $accountName,
                $tokenToSave, $apiUrl, $memoPrefix, $note, $cur['id']
            ]);
        } else {
            $stmtInsert = $this->pdo->prepare("INSERT INTO bank_settings 
                (bank_code, bank_name, account_number, account_name, api_token, api_url, memo_prefix, note) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtInsert->execute([
                $bankCode, $bankName, $accountNumber, $accountName,
                $newToken, $apiUrl, $memoPrefix, $note
            ]);
        }

        return ['status' => 'success', 'message' => 'Đã lưu cấu hình ngân hàng thành công vào Database!'];
    }

    /**
     * Lấy token gốc từ DB để gọi API
     */
    private function getRawToken(): string
    {
        $stmt = $this->pdo->query("SELECT api_token FROM bank_settings ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return trim($row['api_token'] ?? '');
    }

    /**
     * Đồng bộ giao dịch tự động từ API Sieuthicode Bank
     */
    public function syncBankTransactions(): array
    {
        $stmtBank = $this->pdo->query("SELECT * FROM bank_settings ORDER BY id ASC LIMIT 1");
        $bank = $stmtBank->fetch(PDO::FETCH_ASSOC);

        if (!$bank || empty($bank['api_token'])) {
            return [
                'status' => 'warning',
                'synced' => 0,
                'message' => 'Chưa cấu hình Token API ngân hàng. Vui lòng vào Cấu hình Ngân hàng để nhập Token.'
            ];
        }

        $token = trim($bank['api_token']);
        $apiUrl = trim($bank['api_url'] ?? 'https://api.sieuthicode.net/historyapiacbv2/');
        if (!str_ends_with($apiUrl, '/')) {
            $apiUrl .= '/';
        }
        $fullUrl = $apiUrl . $token;
        $prefix = strtoupper(trim($bank['memo_prefix'] ?? 'PDFPRO'));

        // Gọi API bằng cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)']);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $httpCode !== 200 || empty($response)) {
            return [
                'status' => 'error',
                'synced' => 0,
                'message' => 'Không thể kết nối tới máy chủ ngân hàng (' . ($err ?: "HTTP $httpCode") . ')'
            ];
        }

        $result = json_decode($response, true);
        if (!$result || !isset($result['transactions']) || !is_array($result['transactions'])) {
            return [
                'status' => 'warning',
                'synced' => 0,
                'message' => 'API ngân hàng phản hồi nhưng không có danh sách transactions.'
            ];
        }

        $syncedCount = 0;
        $matchedUsers = [];

        foreach ($result['transactions'] as $data) {
            $comment = $data['description'] ?? '';
            $tranId = (string)($data['transactionID'] ?? ($data['refNo'] ?? ''));
            $amount = floatval($data['amount'] ?? 0);

            if (empty($tranId) || $amount <= 0) {
                continue;
            }

            // Kiểm tra xem giao dịch này đã ghi nhận chưa
            $stmtCheck = $this->pdo->prepare("SELECT id FROM invoices WHERE trans_id = ?");
            $stmtCheck->execute([$tranId]);
            if ($stmtCheck->fetch()) {
                continue; // Đã xử lý rồi, bỏ qua
            }

            // 1. Kiểm tra xem có hóa đơn chờ (status = 0) nào khớp với comment không
            $matchedPendingInv = null;
            $stmtPending = $this->pdo->query("SELECT * FROM invoices WHERE status = 0");
            while ($pInv = $stmtPending->fetch(PDO::FETCH_ASSOC)) {
                $code = trim($pInv['trans_id']);
                if (!empty($code) && stripos($comment, $code) !== false) {
                    $matchedPendingInv = $pInv;
                    break;
                }
            }

            if ($matchedPendingInv) {
                // Đánh dấu hóa đơn đã thanh toán thành công
                $now = time();
                $dateStr = date('Y-m-d H:i:s', $now);
                $stmtUpInv = $this->pdo->prepare("UPDATE invoices SET status = 1, updated_at = ? WHERE id = ?");
                $stmtUpInv->execute([$dateStr, $matchedPendingInv['id']]);

                // Gia hạn hoặc nâng cấp PRO 30 ngày cho user
                $stmtUp = $this->pdo->prepare("UPDATE users SET plan = 'PRO', expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?");
                $stmtUp->execute([$matchedPendingInv['user_id']]);

                $syncedCount++;
                $stmtU = $this->pdo->prepare("SELECT email FROM users WHERE id = ?");
                $stmtU->execute([$matchedPendingInv['user_id']]);
                $uEmail = $stmtU->fetchColumn() ?: ('User ' . $matchedPendingInv['user_id']);
                $matchedUsers[] = "{$uEmail} (Đơn #{$matchedPendingInv['trans_id']})";
                continue;
            }

            // 2. Tách user_id từ nội dung chuyển tiền (Cú pháp: PDFPRO 3 hoặc PDFPRO3 hoặc NAP 3)
            $userId = null;
            if (preg_match('/' . preg_quote($prefix, '/') . '\s*(\d+)/i', $comment, $matches)) {
                $userId = intval($matches[1]);
            } elseif (preg_match('/(?:NAP|PDF|PRO)\s*(\d+)/i', $comment, $matches)) {
                $userId = intval($matches[1]);
            }

            // Kiểm tra xem user có tồn tại không
            $userFound = null;
            if ($userId) {
                $stmtUser = $this->pdo->prepare("SELECT id, email, username FROM users WHERE id = ?");
                $stmtUser->execute([$userId]);
                $userFound = $stmtUser->fetch(PDO::FETCH_ASSOC);
            }

            // Nếu tìm thấy user hợp lệ
            if ($userFound) {
                $stmtInsert = $this->pdo->prepare("INSERT INTO invoices 
                    (trans_id, payment_method, user_id, description, amount, status, create_time, created_at) 
                    VALUES (?, ?, ?, ?, ?, 1, ?, ?)");
                
                $now = time();
                $dateStr = date('Y-m-d H:i:s', $now);
                $stmtInsert->execute([
                    $tranId,
                    $bank['bank_code'] ?? 'ACB',
                    $userFound['id'],
                    $comment,
                    $amount,
                    $now,
                    $dateStr
                ]);

                // TỰ ĐỘNG NÂNG CẤP GÓI PRO VÀ GIA HẠN 30 NGÀY CHO USER NÀY!
                $stmtUp = $this->pdo->prepare("UPDATE users SET plan = 'PRO', expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?");
                $stmtUp->execute([$userFound['id']]);

                $syncedCount++;
                $matchedUsers[] = $userFound['email'] . " (ID {$userFound['id']})";
            }
        }

        $msg = $syncedCount > 0 
            ? "Đã đồng bộ thành công {$syncedCount} hóa đơn mới! Nâng cấp PRO cho: " . implode(', ', $matchedUsers)
            : "Đồng bộ hoàn tất: Không phát sinh giao dịch mới nào.";

        return [
            'status' => 'success',
            'synced' => $syncedCount,
            'message' => $msg
        ];
    }

    /**
     * Lấy danh sách hóa đơn và toàn bộ chỉ số thống kê doanh thu
     */
    public function getInvoicesData(array $filters = [], string $period = 'month'): array
    {
        $searchUserId = trim($filters['user_id'] ?? '');
        $searchUsername = trim($filters['username'] ?? '');
        $searchTransId = trim($filters['trans_id'] ?? '');
        $searchBank = trim($filters['bank'] ?? 'all');
        $searchDate = trim($filters['date'] ?? '');
        $limit = max(5, min(100, intval($filters['limit'] ?? 10)));
        $page = max(1, intval($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        // Xây dựng câu WHERE
        $where = ["1=1"];
        $params = [];

        if ($searchUserId !== '') {
            $where[] = "i.user_id = ?";
            $params[] = intval($searchUserId);
        }

        if ($searchUsername !== '') {
            $where[] = "(u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)";
            $params[] = "%{$searchUsername}%";
            $params[] = "%{$searchUsername}%";
            $params[] = "%{$searchUsername}%";
        }

        if ($searchTransId !== '') {
            $where[] = "i.trans_id LIKE ?";
            $params[] = "%{$searchTransId}%";
        }

        if ($searchBank !== '' && $searchBank !== 'all') {
            $where[] = "i.payment_method = ?";
            $params[] = $searchBank;
        }

        if ($searchDate !== '') {
            $where[] = "DATE(i.created_at) = ?";
            $params[] = $searchDate;
        }

        $whereClause = implode(' AND ', $where);

        // Đếm tổng số bản ghi khớp
        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM invoices i LEFT JOIN users u ON i.user_id = u.id WHERE {$whereClause}");
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetchColumn();

        // Lấy danh sách hóa đơn
        $stmtList = $this->pdo->prepare("SELECT 
                i.id, i.trans_id, i.payment_method, i.user_id, i.description, 
                i.amount, i.status, i.create_time, i.created_at, i.updated_at,
                u.username, u.email, u.full_name, u.plan
            FROM invoices i 
            LEFT JOIN users u ON i.user_id = u.id 
            WHERE {$whereClause} 
            ORDER BY i.created_at DESC, i.id DESC 
            LIMIT {$limit} OFFSET {$offset}");
        $stmtList->execute($params);
        $invoices = $stmtList->fetchAll(PDO::FETCH_ASSOC);

        // 4 KPI Doanh thu: Toàn thời gian, Tháng này, Tuần này, Hôm nay
        $totalAllTime = floatval($this->pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 1")->fetchColumn());
        
        $currentMonth = date('Y-m');
        $stmtMonth = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 1 AND DATE_FORMAT(created_at, '%Y-%m') = ?");
        $stmtMonth->execute([$currentMonth]);
        $totalMonth = floatval($stmtMonth->fetchColumn());

        // Doanh thu trong tuần (từ Thứ 2 đến CN hiện tại)
        $mondayThisWeek = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $stmtWeek = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 1 AND created_at >= ?");
        $stmtWeek->execute([$mondayThisWeek]);
        $totalWeek = floatval($stmtWeek->fetchColumn());

        // Doanh thu hôm nay
        $todayStr = date('Y-m-d');
        $stmtToday = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 1 AND DATE(created_at) = ?");
        $stmtToday->execute([$todayStr]);
        $totalToday = floatval($stmtToday->fetchColumn());

        // Xử lý thống kê biểu đồ theo Option: '7days', 'month', 'year'
        $period = trim($filters['period'] ?? $period ?? 'month');
        if (!in_array($period, ['7days', 'month', 'year'])) {
            $period = 'month';
        }

        $chartData = [];
        $chartTitle = 'THỐNG KÊ NẠP TIỀN THÁNG ' . date('m/Y');

        if ($period === '7days') {
            $chartTitle = 'THỐNG KÊ DOANH THU 7 NGÀY QUA';
            for ($i = 6; $i >= 0; $i--) {
                $dayDate = date('Y-m-d', strtotime("-$i days"));
                $dayLabel = date('d/m', strtotime("-$i days"));
                $chartData[$dayDate] = [
                    'key' => $dayDate,
                    'label' => $dayLabel,
                    'full_label' => date('d/m/Y', strtotime("-$i days")),
                    'show_label' => true,
                    'amount' => 0
                ];
            }
            $startDate = date('Y-m-d 00:00:00', strtotime("-6 days"));
            $stmt7 = $this->pdo->prepare("SELECT DATE(created_at) as day_date, SUM(amount) as day_amount 
                FROM invoices WHERE status = 1 AND created_at >= ? GROUP BY DATE(created_at)");
            $stmt7->execute([$startDate]);
            foreach ($stmt7->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (isset($chartData[$r['day_date']])) {
                    $chartData[$r['day_date']]['amount'] = floatval($r['day_amount']);
                }
            }
        } elseif ($period === 'year') {
            $currentYear = date('Y');
            $chartTitle = 'THỐNG KÊ DOANH THU NĂM ' . $currentYear;
            for ($m = 1; $m <= 12; $m++) {
                $mKey = sprintf('%s-%02d', $currentYear, $m);
                $chartData[$mKey] = [
                    'key' => $mKey,
                    'label' => 'T' . $m,
                    'full_label' => 'Tháng ' . $m . '/' . $currentYear,
                    'show_label' => true,
                    'amount' => 0
                ];
            }
            $stmtYear = $this->pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as m_key, SUM(amount) as m_amount 
                FROM invoices WHERE status = 1 AND DATE_FORMAT(created_at, '%Y') = ? GROUP BY DATE_FORMAT(created_at, '%Y-%m')");
            $stmtYear->execute([$currentYear]);
            foreach ($stmtYear->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (isset($chartData[$r['m_key']])) {
                    $chartData[$r['m_key']]['amount'] = floatval($r['m_amount']);
                }
            }
        } else {
            // 'month' (default: Tháng hiện tại)
            $currentMonth = date('Y-m');
            $chartTitle = 'THỐNG KÊ NẠP TIỀN THÁNG ' . date('m/Y');
            $daysInMonth = (int)date('t');
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dayKey = sprintf('%s-%02d', $currentMonth, $d);
                $showLabel = ($d === 1 || $d % 5 === 0 || $d === $daysInMonth);
                $chartData[$dayKey] = [
                    'key' => $dayKey,
                    'label' => (string)$d,
                    'full_label' => sprintf('%02d/%s', $d, date('m/Y')),
                    'show_label' => $showLabel,
                    'amount' => 0
                ];
            }
            $stmtDays = $this->pdo->prepare("SELECT DATE(created_at) as day_date, SUM(amount) as day_amount 
                FROM invoices 
                WHERE status = 1 AND DATE_FORMAT(created_at, '%Y-%m') = ? 
                GROUP BY DATE(created_at)");
            $stmtDays->execute([$currentMonth]);
            foreach ($stmtDays->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (isset($chartData[$r['day_date']])) {
                    $chartData[$r['day_date']]['amount'] = floatval($r['day_amount']);
                }
            }
        }

        $maxVal = 0;
        foreach ($chartData as $item) {
            if ($item['amount'] > $maxVal) {
                $maxVal = $item['amount'];
            }
        }
        $chartMax = max(300000, ceil(($maxVal ?: 1) / 50000) * 50000);

        return [
            'status' => 'success',
            'invoices' => $invoices,
            'total_records' => $totalRecords,
            'page' => $page,
            'limit' => $limit,
            'stats' => [
                'all_time' => $totalAllTime,
                'month' => $totalMonth,
                'week' => $totalWeek,
                'today' => $totalToday,
                'month_name' => 'Tháng ' . date('m/Y'),
                'period' => $period,
                'chart_title' => $chartTitle,
                'chart_max' => $chartMax,
                'chart_data' => array_values($chartData),
                'daily_stats' => array_combine(array_keys($chartData), array_map(fn($item) => $item['amount'], $chartData))
            ]
        ];
    }

    /**
     * Sửa trạng thái hóa đơn
     */
    public function updateInvoice(int $id, array $data): array
    {
        $status = isset($data['status']) ? intval($data['status']) : 1;
        $amount = isset($data['amount']) ? floatval($data['amount']) : null;

        if ($amount !== null) {
            $stmt = $this->pdo->prepare("UPDATE invoices SET status = ?, amount = ? WHERE id = ?");
            $stmt->execute([$status, $amount, $id]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
        }

        return ['status' => 'success', 'message' => 'Cập nhật hóa đơn thành công!'];
    }

    /**
     * Xóa hóa đơn
     */
    public function deleteInvoice(int $id): array
    {
        $stmt = $this->pdo->prepare("DELETE FROM invoices WHERE id = ?");
        $stmt->execute([$id]);
        return ['status' => 'success', 'message' => 'Đã xóa hóa đơn thành công!'];
    }

    /**
     * Tạo hóa đơn nạp tiền mới cho User khi bấm chọn gói
     */
    public function createInvoice(int $userId, array $data): array
    {
        $plan = strtoupper(trim($data['plan'] ?? 'PRO'));
        $amount = floatval($data['amount'] ?? 99000);
        $cycle = trim($data['cycle'] ?? 'month');

        // Tạo mã giao dịch 5 ký tự ngẫu nhiên (ví dụ X6CWA, FUN4T)
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $transId = '';
        for ($i = 0; $i < 5; $i++) {
            $transId .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $bank = $this->getBankSettings();
        $prefix = $bank['memo_prefix'] ?? 'PDFPRO';
        $memo = "{$prefix} {$userId} {$transId}";
        $description = "Nâng cấp gói {$plan} - Mã {$transId}";

        $now = time();
        $dateStr = date('Y-m-d H:i:s', $now);

        $stmt = $this->pdo->prepare("INSERT INTO invoices 
            (trans_id, payment_method, user_id, description, amount, status, create_time, created_at) 
            VALUES (?, ?, ?, ?, ?, 0, ?, ?)");
        $stmt->execute([
            $transId,
            $bank['bank_code'] ?? 'ACB',
            $userId,
            $memo,
            $amount,
            $now,
            $dateStr
        ]);

        $invId = (int)$this->pdo->lastInsertId();
        $bankCode = $bank['bank_code'] ?? 'ACB';
        $accNum = $bank['account_number'] ?? '17449371';
        $accName = $bank['account_name'] ?? 'VU VAN CHIEN';
        $encodedMemo = rawurlencode($memo);
        $encodedName = rawurlencode($accName);
        $qrUrl = "https://api.vietqr.io/image/" . strtolower($bankCode) . "-{$accNum}-compact2.png?amount={$amount}&addInfo={$encodedMemo}&accountName={$encodedName}";

        return [
            'status' => 'success',
            'invoice' => [
                'id' => $invId,
                'trans_id' => $transId,
                'plan' => $plan,
                'amount' => $amount,
                'memo' => $memo,
                'bank_code' => $bankCode,
                'bank_name' => $bank['bank_name'] ?? 'Ngân hàng Á Châu',
                'account_number' => $accNum,
                'account_name' => $accName,
                'qr_url' => $qrUrl,
                'status' => 0,
                'created_at' => $dateStr
            ]
        ];
    }

    /**
     * Lấy danh sách hóa đơn của User hiện tại
     */
    public function getUserInvoices(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM invoices WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 20");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Kiểm tra trạng thái nạp của User (dùng cho polling ở trang /upgrade của client)
     */
    public function checkUserPaymentStatus(int $userId, ?string $transId = null): array
    {
        // Kiểm tra xem user hiện tại đã là PRO chưa
        $stmtUser = $this->pdo->prepare("SELECT id, plan, expires_at FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['status' => 'error', 'message' => 'Không tìm thấy người dùng'];
        }

        // Tự động kiểm tra sync nếu có cấu hình
        try {
            $this->syncBankTransactions();
        } catch (Exception $e) {}

        // Kiểm tra lại sau sync
        $stmtUser->execute([$userId]);
        $userUpdated = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $isPro = ($userUpdated['plan'] ?? '') === 'PRO';

        $isPaid = false;
        $inv = null;
        if ($transId) {
            $stmtInv = $this->pdo->prepare("SELECT * FROM invoices WHERE trans_id = ? AND user_id = ?");
            $stmtInv->execute([$transId, $userId]);
            $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);
            if ($inv && (int)$inv['status'] === 1) {
                $isPaid = true;
            }
        }

        if (!$inv) {
            // Lấy hóa đơn mới nhất của user
            $stmtLast = $this->pdo->prepare("SELECT * FROM invoices WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $stmtLast->execute([$userId]);
            $inv = $stmtLast->fetch(PDO::FETCH_ASSOC);
            if ($inv && (int)$inv['status'] === 1) {
                $isPaid = true;
            }
        }

        return [
            'status' => 'success',
            'is_pro' => $isPro,
            'is_paid' => $isPaid || $isPro,
            'plan' => $userUpdated['plan'] ?? 'FREE',
            'expires_at' => $userUpdated['expires_at'] ?? null,
            'last_invoice' => $inv
        ];
    }
}
