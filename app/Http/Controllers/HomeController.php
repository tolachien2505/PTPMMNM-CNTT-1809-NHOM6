<?php

namespace App\Http\Controllers;

use App\Controllers\PaymentController;
use Exception;
use PDO;

/**
 * Controller Trang Chủ & Giao diện Ứng dụng (Laravel Standard)
 * Khởi tạo dữ liệu người dùng, quản trị viên và điều phối giao diện
 */
class HomeController
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function render(string $route = 'home', string $adminSub = 'dashboard'): void
    {
        $currentUser = currentUser();
        $isLoggedIn = !empty($currentUser);
        $isAdmin = $isLoggedIn && ($currentUser['is_admin'] === true);

        // Đọc trạng thái bảo trì hệ thống từ storage/maintenance.json
        $maintFile = dirname(__DIR__, 3) . '/storage/maintenance.json';
        $isMaintenanceActive = false;
        $maintMessage = 'Hệ thống đang tiến hành nâng cấp & bảo trì định kỳ. Quý khách vui lòng quay lại sau ít phút!';
        if (file_exists($maintFile)) {
            $maintData = json_decode(@file_get_contents($maintFile), true);
            if (is_array($maintData) && !empty($maintData['maintenance_mode'])) {
                $isMaintenanceActive = true;
                if (!empty($maintData['message'])) {
                    $maintMessage = trim($maintData['message']);
                }
            }
        }

        // Định tuyến giao diện
        $validRoutes = ['home', 'documents', 'editor', 'upgrade', 'history', 'settings', 'admin'];
        $cleanRoute = in_array($route, $validRoutes) ? $route : 'home';
        $isPortalAdmin = false;
        $adminCurrentView = 'dashboard';
        $autoOpenAdminLogin = false;

        if ($cleanRoute === 'admin') {
            if ($isAdmin) {
                $isPortalAdmin = true;
                $currentView = 'admin';
                $validAdminPages = ['dashboard', 'users', 'documents', 'exports', 'payments', 'plans', 'maintenance'];
                $adminCurrentView = in_array($adminSub, $validAdminPages) ? $adminSub : 'dashboard';
            } else {
                $isPortalAdmin = false;
                $currentView = 'home';
                $autoOpenAdminLogin = true;
            }
        } else {
            $currentView = $cleanRoute;
        }

        $userPlan = $currentUser['plan'] ?? 'FREE';

        // Helper functions cho View
        if (!function_exists('formatDocStatus')) {
            function formatDocStatus(?string $status): array {
                $st = strtolower(trim($status ?? 'uploaded'));
                if (in_array($st, ['error', 'corrupted', 'oversize', 'failed'])) {
                    return ['class' => 'danger', 'text' => 'Lỗi tệp', 'code' => 'error'];
                }
                if (in_array($st, ['editing', 'draft', 'processing'])) {
                    return ['class' => 'warning', 'text' => 'Đang chỉnh sửa', 'code' => 'editing'];
                }
                if (in_array($st, ['exported', 'completed', 'success'])) {
                    return ['class' => 'success', 'text' => 'Đã xuất file', 'code' => 'exported'];
                }
                return ['class' => 'primary', 'text' => 'Sẵn sàng', 'code' => 'uploaded'];
            }
        }

        if (!function_exists('formatDocSize')) {
            function formatDocSize(int $bytes): string {
                if ($bytes < 1024) return $bytes . ' B';
                if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
                return round($bytes / 1048576, 1) . ' MB';
            }
        }

        if (!function_exists('formatDocDate')) {
            function formatDocDate(?string $datetime): string {
                if (!$datetime) return date('d/m/Y H:i');
                return date('d/m/Y H:i', strtotime($datetime));
            }
        }

        // Tính toán dữ liệu tài liệu người dùng
        $userDocs = [];
        $totalUserDocs = 0;
        $totalUserPages = 0;
        $totalUserBytes = 0;
        $totalUploadedDocs = 0;
        $totalEditingDocs = 0;
        $totalExportedDocs = 0;
        $totalErrorDocs = 0;

        if ($this->pdo && $isLoggedIn) {
            try {
                $stmt = $this->pdo->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY id DESC");
                $stmt->execute([$currentUser['id']]);
                $userDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalUserDocs = count($userDocs);
                foreach ($userDocs as $doc) {
                    $totalUserPages += (int)($doc['total_pages'] ?? 1);
                    $totalUserBytes += (int)($doc['file_size'] ?? 0);
                    $stInfo = formatDocStatus($doc['status'] ?? 'uploaded');
                    if ($stInfo['code'] === 'error') $totalErrorDocs++;
                    elseif ($stInfo['code'] === 'editing') $totalEditingDocs++;
                    elseif ($stInfo['code'] === 'exported') $totalExportedDocs++;
                    else $totalUploadedDocs++;
                }
            } catch (Exception $e) {
                $userDocs = [];
            }
        }

        $isPro = ($userPlan === 'PRO');
        $userStorageLimitMb = $isPro ? 2048 : 100;
        $userStorageMb = round($totalUserBytes / (1024 * 1024), 1);
        $storagePercent = $isLoggedIn ? max(2, min(100, round(($userStorageMb / $userStorageLimitMb) * 100, 1))) : 0;
        $storageLimitMb = $userStorageLimitMb;

        // Tính toán thời hạn gói của người dùng
        $userRemainingDays = 0;
        $userExpiresText = 'Miễn phí vĩnh viễn';
        if ($isLoggedIn && !empty($currentUser['expires_at'])) {
            $expTs = strtotime($currentUser['expires_at']);
            $diffDays = (int)ceil(($expTs - time()) / 86400);
            $userRemainingDays = max(0, $diffDays);
            if ($userRemainingDays > 0) {
                $userExpiresText = "Còn {$userRemainingDays} ngày";
            } else {
                $userExpiresText = "Hết hạn — 0 ngày còn lại";
            }
        } elseif ($isPro) {
            $userRemainingDays = 30;
            $userExpiresText = "Còn 30 ngày";
        }

        // Lấy lịch sử hóa đơn của chính người dùng hiện tại
        $userInvoices = [];
        $bankSettings = [];
        if ($this->pdo) {
            try {
                $payCtrl = new PaymentController($this->pdo);
                $bankSettings = $payCtrl->getBankSettings();
                if ($isLoggedIn) {
                    $stmtInv = $this->pdo->prepare("SELECT * FROM invoices WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 20");
                    $stmtInv->execute([$currentUser['id']]);
                    $userInvoices = $stmtInv->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (Exception $e) {}
        }

        // Dữ liệu quản trị hệ thống (Admin)
        $adminStats = [
            'total_users' => 0,
            'total_admins' => 0,
            'total_normal_users' => 0,
            'total_pro_users' => 0,
            'total_docs' => 0,
            'total_bytes' => 0,
            'total_doc_pages' => 0,
            'total_outputs' => 0,
            'total_jobs' => 0,
            'total_revenue' => 0,
            'total_invoices' => 0
        ];
        $adminUsersList = [];
        $adminDocsList = [];
        $adminOutputsList = [];
        $adminBankSettings = [];
        $adminInvoicesData = ['invoices' => [], 'total_records' => 0, 'stats' => ['all_time' => 0, 'month' => 0, 'week' => 0, 'today' => 0, 'daily_stats' => []]];

        if ($this->pdo && $isAdmin) {
            try {
                $uStmt = $this->pdo->query("SELECT role, plan FROM users");
                while ($ru = $uStmt->fetch(PDO::FETCH_ASSOC)) {
                    $adminStats['total_users']++;
                    if (($ru['role'] ?? '') === 'admin') $adminStats['total_admins']++;
                    else $adminStats['total_normal_users']++;
                    if (($ru['plan'] ?? '') === 'PRO') $adminStats['total_pro_users']++;
                }

                $dStmt = $this->pdo->query("SELECT file_size, total_pages, status FROM documents");
                while ($rd = $dStmt->fetch(PDO::FETCH_ASSOC)) {
                    $adminStats['total_docs']++;
                    $adminStats['total_bytes'] += (int)($rd['file_size'] ?? 0);
                    $adminStats['total_doc_pages'] += (int)($rd['total_pages'] ?? 1);
                }

                $adminStats['total_outputs'] = (int)($this->pdo->query("SELECT COUNT(*) AS c FROM outputs")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
                $adminStats['total_jobs'] = (int)($this->pdo->query("SELECT COUNT(*) AS c FROM export_jobs")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

                try {
                    $adminStats['total_revenue'] = floatval($this->pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 1")->fetchColumn());
                    $adminStats['total_invoices'] = (int)($this->pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn());
                    $payCtrl = new PaymentController($this->pdo);
                    $adminBankSettings = $payCtrl->getBankSettings();
                    $adminInvoicesData = $payCtrl->getInvoicesData(['limit' => 10]);
                } catch (Exception $payEx) {}

                $stmtUsers = $this->pdo->query("
                    SELECT 
                        u.id, u.username, u.email, u.full_name, u.phone, u.role, u.plan, u.status, u.email_verified, u.expires_at, u.created_at,
                        COUNT(d.id) AS total_docs,
                        COALESCE(SUM(d.file_size), 0) AS total_bytes
                    FROM users u
                    LEFT JOIN documents d ON u.id = d.user_id
                    GROUP BY u.id
                    ORDER BY (u.role = 'admin') DESC, u.id DESC
                ");
                $adminUsersList = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

                $stmtDocs = $this->pdo->query("
                    SELECT 
                        d.id, d.original_name, d.stored_filename, d.file_size, d.total_pages, d.status, d.created_at,
                        u.id AS owner_id, u.full_name AS owner_name, u.email AS owner_email, u.role AS owner_role
                    FROM documents d
                    LEFT JOIN users u ON d.user_id = u.id
                    ORDER BY d.id DESC
                ");
                $adminDocsList = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

                $stmtOut = $this->pdo->query("
                    SELECT 
                        o.id, o.filename, o.stored_path, o.file_size, o.total_pages, o.created_at, o.source_manifest,
                        u.full_name AS user_name, u.email AS user_email
                    FROM outputs o
                    LEFT JOIN users u ON o.user_id = u.id
                    ORDER BY o.id DESC
                    LIMIT 50
                ");
                $adminOutputsList = $stmtOut->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }

        $adminStorageMb = round(($adminStats['total_bytes'] ?? 0) / (1024 * 1024), 2);

        view('layouts.app', compact(
            'currentUser',
            'isLoggedIn',
            'isAdmin',
            'isPortalAdmin',
            'currentView',
            'adminCurrentView',
            'userPlan',
            'userDocs',
            'totalUserDocs',
            'totalUserPages',
            'totalUserBytes',
            'totalUploadedDocs',
            'totalEditingDocs',
            'totalExportedDocs',
            'totalErrorDocs',
            'userStorageMb',
            'userStorageLimitMb',
            'storageLimitMb',
            'storagePercent',
            'userRemainingDays',
            'userExpiresText',
            'userInvoices',
            'bankSettings',
            'adminStats',
            'adminUsersList',
            'adminDocsList',
            'adminOutputsList',
            'adminBankSettings',
            'adminInvoicesData',
            'adminStorageMb',
            'isMaintenanceActive',
            'maintMessage',
            'autoOpenAdminLogin'
        ));
    }
}
