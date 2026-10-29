<?php

namespace App\Http\Controllers;

use PDO;
use Exception;

/**
 * Controller Quản trị Hệ thống (Admin Portal Controller)
 * Quản lý thống kê toàn diện, tài khoản người dùng, tài liệu hệ thống và tệp xuất
 */
class AdminController
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * Thống kê KPI và môi trường vận hành
     */
    public function stats(): void
    {
        $totalUsers = 0;
        $adminCount = 0;
        $userCount = 0;
        $proUsers = 0;
        $freeUsers = 0;
        $totalDocs = 0;
        $totalStorageBytes = 0;
        $totalDocPages = 0;
        $totalOutputs = 0;
        $totalJobs = 0;
        $totalRevenue = 0;
        $totalInvoices = 0;

        if ($this->pdo) {
            try {
                $uStmt = $this->pdo->query("SELECT role, plan FROM users");
                while ($r = $uStmt->fetch(PDO::FETCH_ASSOC)) {
                    $totalUsers++;
                    if (($r['role'] ?? '') === 'admin') $adminCount++;
                    else $userCount++;
                    if (($r['plan'] ?? '') === 'PRO') $proUsers++;
                    else $freeUsers++;
                }

                $dStmt = $this->pdo->query("SELECT file_size, total_pages, status FROM documents");
                while ($d = $dStmt->fetch(PDO::FETCH_ASSOC)) {
                    $totalDocs++;
                    $totalStorageBytes += (int)($d['file_size'] ?? 0);
                    $totalDocPages += (int)($d['total_pages'] ?? 1);
                }

                $oStmt = $this->pdo->query("SELECT COUNT(*) AS c FROM outputs");
                $totalOutputs = (int)($oStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

                $jStmt = $this->pdo->query("SELECT COUNT(*) AS c FROM export_jobs");
                $totalJobs = (int)($jStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

                try {
                    $revStmt = $this->pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 1");
                    $totalRevenue = floatval($revStmt->fetchColumn());
                    $invStmt = $this->pdo->query("SELECT COUNT(*) FROM invoices");
                    $totalInvoices = (int)$invStmt->fetchColumn();
                } catch (Exception $e) {}
            } catch (Exception $e) {}
        }

        response()->json([
            'status' => 'success',
            'stats' => [
                'total_users' => $totalUsers,
                'admin_count' => $adminCount,
                'user_count' => $userCount,
                'pro_users' => $proUsers,
                'free_users' => $freeUsers,
                'total_docs' => $totalDocs,
                'total_doc_pages' => $totalDocPages,
                'total_storage_mb' => round($totalStorageBytes / (1024 * 1024), 2),
                'total_outputs' => $totalOutputs,
                'total_jobs' => $totalJobs,
                'total_revenue' => $totalRevenue,
                'total_revenue_formatted' => number_format($totalRevenue, 0, ',', '.') . 'đ',
                'total_invoices' => $totalInvoices,
                'server_php_version' => PHP_VERSION,
                'server_time' => date('d/m/Y H:i:s')
            ]
        ]);
    }

    /**
     * Danh sách toàn bộ người dùng
     */
    public function users(): void
    {
        $users = [];
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query("
                    SELECT 
                        u.id, u.username, u.email, u.full_name, u.phone, u.role, u.plan, u.status, u.email_verified, u.expires_at, u.created_at,
                        COUNT(d.id) AS total_docs,
                        COALESCE(SUM(d.file_size), 0) AS total_bytes
                    FROM users u
                    LEFT JOIN documents d ON u.id = d.user_id
                    GROUP BY u.id
                    ORDER BY (u.role = 'admin') DESC, u.id DESC
                ");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($users as &$u) {
                    $u['is_admin'] = ($u['role'] === 'admin');
                    $u['role_label'] = ($u['role'] === 'admin') ? 'Quản trị viên' : 'Người dùng';
                    $u['storage_mb'] = round(((int)$u['total_bytes']) / (1024 * 1024), 2);
                }
            } catch (Exception $e) {}
        }
        response()->json(['status' => 'success', 'users' => $users]);
    }

    /**
     * Đổi vai trò người dùng (user <-> admin)
     */
    public function changeUserRole(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $targetUserId = (int)($input['user_id'] ?? 0);
        $newRole = trim($input['role'] ?? 'user');
        if (!in_array($newRole, ['user', 'admin'])) {
            $newRole = 'user';
        }

        if ($targetUserId <= 0) {
            response()->json(['status' => 'error', 'message' => 'ID người dùng không hợp lệ.'], 400);
        }

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$newRole, $targetUserId]);
                $roleLabel = ($newRole === 'admin') ? 'Quản trị viên' : 'Người dùng';
                response()->json([
                    'status' => 'success',
                    'message' => "Đã cập nhật vai trò: {$roleLabel}",
                    'role' => $newRole,
                    'role_label' => $roleLabel
                ]);
            } catch (Exception $e) {
                response()->json(['status' => 'error', 'message' => 'Lỗi cập nhật vai trò: ' . $e->getMessage()], 500);
            }
        }
    }

    /**
     * Cấp / đổi gói dịch vụ người dùng (FREE <-> PRO)
     */
    public function changeUserPlan(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $targetUserId = (int)($input['user_id'] ?? 0);
        $newPlan = strtoupper(trim($input['plan'] ?? 'PRO'));
        if (!in_array($newPlan, ['FREE', 'PRO'])) {
            $newPlan = 'PRO';
        }

        if ($targetUserId <= 0) {
            response()->json(['status' => 'error', 'message' => 'ID người dùng không hợp lệ.'], 400);
        }

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare("UPDATE users SET plan = ? WHERE id = ?");
                $stmt->execute([$newPlan, $targetUserId]);
                response()->json([
                    'status' => 'success',
                    'message' => "Đã cập nhật gói dịch vụ {$newPlan} cho người dùng.",
                    'plan' => $newPlan
                ]);
            } catch (Exception $e) {
                response()->json(['status' => 'error', 'message' => 'Lỗi cập nhật gói: ' . $e->getMessage()], 500);
            }
        }
    }

    /**
     * Xóa tài khoản người dùng
     */
    public function deleteUser(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $targetUserId = (int)($input['user_id'] ?? 0);

        if ($targetUserId <= 0) {
            response()->json(['status' => 'error', 'message' => 'ID người dùng không hợp lệ.'], 400);
        }

        if ($targetUserId === (int)($_SESSION['user_id'] ?? 0)) {
            response()->json(['status' => 'error', 'message' => 'Bạn không thể tự xóa tài khoản của chính mình.'], 400);
        }

        if ($this->pdo) {
            try {
                $fStmt = $this->pdo->prepare("SELECT stored_filename FROM documents WHERE user_id = ?");
                $fStmt->execute([$targetUserId]);
                while ($f = $fStmt->fetch(PDO::FETCH_ASSOC)) {
                    $p = dirname(__DIR__, 3) . '/storage/documents/' . $f['stored_filename'];
                    if (file_exists($p)) @unlink($p);
                }

                $delStmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
                $delStmt->execute([$targetUserId]);

                response()->json([
                    'status' => 'success',
                    'message' => 'Đã xóa người dùng và toàn bộ dữ liệu liên quan thành công.'
                ]);
            } catch (Exception $e) {
                response()->json(['status' => 'error', 'message' => 'Lỗi xóa người dùng: ' . $e->getMessage()], 500);
            }
        }
    }

    /**
     * Cập nhật thông tin toàn diện người dùng từ Modal Quản trị (Screenshot 4)
     */
    public function updateUser(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $targetUserId = (int)($input['user_id'] ?? 0);

        if ($targetUserId <= 0) {
            response()->json(['status' => 'error', 'message' => 'ID người dùng không hợp lệ.'], 400);
        }

        $fullName = trim($input['full_name'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $role = trim($input['role'] ?? 'user');
        if (!in_array($role, ['user', 'admin'])) $role = 'user';
        $plan = strtoupper(trim($input['plan'] ?? 'FREE'));
        if (!in_array($plan, ['FREE', 'PRO'])) $plan = 'FREE';
        $expiresAt = !empty($input['expires_at']) ? trim($input['expires_at']) : null;
        $status = trim($input['status'] ?? 'active');
        if (!in_array($status, ['active', 'pending', 'banned'])) $status = 'active';
        $emailVerified = !empty($input['email_verified']) ? 1 : 0;
        $newPassword = trim($input['new_password'] ?? '');

        if (empty($fullName)) {
            response()->json(['status' => 'error', 'message' => 'Họ và tên không được để trống.'], 400);
        }

        if ($this->pdo) {
            try {
                if (!empty($newPassword)) {
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $this->pdo->prepare("
                        UPDATE users 
                        SET full_name = ?, phone = ?, role = ?, plan = ?, expires_at = ?, status = ?, email_verified = ?, password_hash = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$fullName, $phone ?: null, $role, $plan, $expiresAt, $status, $emailVerified, $passwordHash, $targetUserId]);
                } else {
                    $stmt = $this->pdo->prepare("
                        UPDATE users 
                        SET full_name = ?, phone = ?, role = ?, plan = ?, expires_at = ?, status = ?, email_verified = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$fullName, $phone ?: null, $role, $plan, $expiresAt, $status, $emailVerified, $targetUserId]);
                }

                $roleLabel = ($role === 'admin') ? 'Quản trị viên' : 'Người dùng';

                response()->json([
                    'status' => 'success',
                    'message' => 'Cập nhật thông tin người dùng thành công.',
                    'user' => [
                        'id' => $targetUserId,
                        'full_name' => $fullName,
                        'phone' => $phone,
                        'role' => $role,
                        'role_label' => $roleLabel,
                        'plan' => $plan,
                        'expires_at' => $expiresAt,
                        'status' => $status,
                        'email_verified' => $emailVerified
                    ]
                ]);
            } catch (Exception $e) {
                response()->json(['status' => 'error', 'message' => 'Lỗi cập nhật người dùng: ' . $e->getMessage()], 500);
            }
        }
    }

    /**
     * Danh sách toàn bộ tài liệu hệ thống
     */
    public function documents(): void
    {
        $docs = [];
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query("
                    SELECT 
                        d.id, d.original_name, d.stored_filename, d.file_size, d.total_pages, d.status, d.created_at,
                        u.id AS owner_id, u.full_name AS owner_name, u.email AS owner_email, u.role AS owner_role
                    FROM documents d
                    LEFT JOIN users u ON d.user_id = u.id
                    ORDER BY d.id DESC
                ");
                $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }
        response()->json(['status' => 'success', 'documents' => $docs]);
    }

    /**
     * Xóa tài liệu bất kỳ bởi Admin
     */
    public function deleteDocuments(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $docId = (int)($input['id'] ?? 0);
        $docIds = $input['ids'] ?? [];
        if ($docId > 0 && !in_array($docId, $docIds)) {
            $docIds[] = $docId;
        }

        if (empty($docIds)) {
            response()->json(['status' => 'error', 'message' => 'Vui lòng chọn tài liệu cần xóa.'], 400);
        }

        $deletedCount = 0;
        if ($this->pdo) {
            $inClause = implode(',', array_fill(0, count($docIds), '?'));
            try {
                $stmt = $this->pdo->prepare("SELECT stored_filename FROM documents WHERE id IN ($inClause)");
                $stmt->execute($docIds);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $p = dirname(__DIR__, 3) . '/storage/documents/' . $row['stored_filename'];
                    if (file_exists($p)) @unlink($p);
                }

                $delStmt = $this->pdo->prepare("DELETE FROM documents WHERE id IN ($inClause)");
                $delStmt->execute($docIds);
                $deletedCount = $delStmt->rowCount();
            } catch (Exception $e) {
                response()->json(['status' => 'error', 'message' => 'Lỗi xóa tài liệu: ' . $e->getMessage()], 500);
            }
        }

        response()->json([
            'status' => 'success',
            'message' => "Admin đã xóa thành công {$deletedCount} tài liệu.",
            'deleted_count' => $deletedCount
        ]);
    }

    /**
     * Toàn bộ lịch sử xuất và bảng đối chiếu nguồn
     */
    public function exports(): void
    {
        $outputs = [];
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query("
                    SELECT 
                        o.id, o.filename, o.stored_path, o.file_size, o.total_pages, o.created_at, o.source_manifest,
                        u.full_name AS user_name, u.email AS user_email
                    FROM outputs o
                    LEFT JOIN users u ON o.user_id = u.id
                    ORDER BY o.id DESC
                ");
                $outputs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }
        response()->json(['status' => 'success', 'outputs' => $outputs]);
    }

    /**
     * Quét và dọn sạch tệp mồ côi (Cleanup Orphan Files)
     */
    public function cleanup(): void
    {
        $cleanedDocs = 0;
        $cleanedExports = 0;
        $freedBytes = 0;

        if ($this->pdo) {
            try {
                $activeDocFiles = $this->pdo->query("SELECT stored_filename FROM documents")->fetchAll(PDO::FETCH_COLUMN);
                $activeExportFiles = $this->pdo->query("SELECT stored_path, filename FROM outputs")->fetchAll(PDO::FETCH_ASSOC);
                $validExportNames = [];
                foreach ($activeExportFiles as $ae) {
                    $validExportNames[] = basename($ae['stored_path']);
                    $validExportNames[] = basename($ae['filename']);
                }

                $docDir = dirname(__DIR__, 3) . '/storage/documents';
                if (is_dir($docDir)) {
                    foreach (scandir($docDir) as $f) {
                        if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
                        if (!in_array($f, $activeDocFiles)) {
                            $fp = $docDir . '/' . $f;
                            $freedBytes += filesize($fp);
                            @unlink($fp);
                            $cleanedDocs++;
                        }
                    }
                }

                $expDir = dirname(__DIR__, 3) . '/storage/exports';
                if (is_dir($expDir)) {
                    foreach (scandir($expDir) as $f) {
                        if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
                        if (!in_array($f, $validExportNames)) {
                            $fp = $expDir . '/' . $f;
                            $freedBytes += filesize($fp);
                            @unlink($fp);
                            $cleanedExports++;
                        }
                    }
                }
            } catch (Exception $e) {}
        }

        $freedMb = round($freedBytes / (1024 * 1024), 2);
        response()->json([
            'status' => 'success',
            'message' => "Đã dọn sạch {$cleanedDocs} tệp tài liệu và {$cleanedExports} tệp xuất mồ côi (Giải phóng {$freedMb} MB).",
            'cleaned_docs' => $cleanedDocs,
            'cleaned_exports' => $cleanedExports,
            'freed_mb' => $freedMb
        ]);
    }

    /**
     * Danh sách gói dịch vụ và KPI gói (/admin/plans)
     */
    public function plans(): void
    {
        $plans = [];
        $totalPlans = 0;
        $activePlans = 0;
        $inactivePlans = 0;
        $popularPlan = 'PRO';

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query("SELECT * FROM service_plans WHERE plan_code IN ('FREE', 'PRO') ORDER BY sort_order ASC, tier_level ASC");
                $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($plans as &$p) {
                    $totalPlans++;
                    if ((int)$p['is_active'] === 1) $activePlans++;
                    else $inactivePlans++;

                    if (!empty($p['badge_text'])) {
                        $popularPlan = $p['name'] . " (" . $p['plan_code'] . ")";
                    }

                    $p['price_formatted'] = number_format((float)$p['price'], 0, ',', '.') . 'đ';
                    $p['duration_label'] = ((int)$p['duration_days'] <= 0) ? 'Vĩnh viễn' : ($p['duration_days'] . ' ngày');
                    $p['storage_label'] = ((int)$p['max_storage_mb'] < 0) ? 'Không giới hạn' : ($p['max_storage_mb'] . ' MB');
                    $p['upload_label'] = $p['max_upload_size_mb'] . ' MB';
                    $p['pages_label'] = ((int)$p['max_pages_per_file'] < 0) ? 'Không giới hạn' : ($p['max_pages_per_file'] . ' trang');
                    $p['dpi_label'] = $p['max_dpi'] . ' DPI';
                    $p['allowed_features_arr'] = json_decode($p['allowed_features'] ?? '{}', true) ?: [];
                }
            } catch (Exception $e) {}
        }

        response()->json([
            'status' => 'success',
            'plans' => $plans,
            'stats' => [
                'total_plans' => $totalPlans,
                'active_plans' => $activePlans,
                'inactive_plans' => $inactivePlans,
                'popular_plan' => $popularPlan
            ]
        ]);
    }

    /**
     * Thêm mới hoặc cập nhật gói dịch vụ
     */
    public function savePlan(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = (int)($input['id'] ?? 0);
        $planCode = strtoupper(trim($input['plan_code'] ?? ''));
        $name = trim($input['name'] ?? '');
        $price = max(0, (float)($input['price'] ?? 0));
        $durationDays = max(0, (int)($input['duration_days'] ?? 30));
        $tierLevel = max(0, (int)($input['tier_level'] ?? 0));
        $maxStorageMb = (int)($input['max_storage_mb'] ?? 100);
        $maxUploadSizeMb = max(1, (int)($input['max_upload_size_mb'] ?? 15));
        $maxPagesPerFile = (int)($input['max_pages_per_file'] ?? 50);
        $maxDailyExports = (int)($input['max_daily_exports'] ?? 20);
        $maxDpi = in_array((int)($input['max_dpi'] ?? 150), [150, 200, 300]) ? (int)$input['max_dpi'] : 150;
        $featuresText = trim($input['features_text'] ?? '');
        $badgeText = trim($input['badge_text'] ?? '') ?: null;
        $sortOrder = (int)($input['sort_order'] ?? 0);
        $isActive = !empty($input['is_active']) ? 1 : 0;

        $allowedFeatures = $input['allowed_features'] ?? [];
        if (!is_array($allowedFeatures)) {
            $allowedFeatures = json_decode($allowedFeatures, true) ?: [];
        }
        $allowedFeaturesJson = json_encode($allowedFeatures, JSON_UNESCAPED_UNICODE);

        if (empty($planCode) || empty($name)) {
            response()->json(['status' => 'error', 'message' => 'Mã gói và Tên gói không được để trống.'], 400);
            return;
        }

        if (!$this->pdo) {
            response()->json(['status' => 'error', 'message' => 'Lỗi kết nối cơ sở dữ liệu.'], 500);
            return;
        }

        try {
            if ($id > 0) {
                // Update
                $stmt = $this->pdo->prepare("
                    UPDATE service_plans SET
                        plan_code = ?, name = ?, price = ?, duration_days = ?, tier_level = ?,
                        max_storage_mb = ?, max_upload_size_mb = ?, max_pages_per_file = ?, max_daily_exports = ?,
                        max_dpi = ?, features_text = ?, allowed_features = ?, badge_text = ?, sort_order = ?, is_active = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $planCode, $name, $price, $durationDays, $tierLevel,
                    $maxStorageMb, $maxUploadSizeMb, $maxPagesPerFile, $maxDailyExports,
                    $maxDpi, $featuresText, $allowedFeaturesJson, $badgeText, $sortOrder, $isActive,
                    $id
                ]);
                $msg = "Đã cập nhật thành công gói dịch vụ {$name} ({$planCode})!";
            } else {
                // Insert
                $stmt = $this->pdo->prepare("
                    INSERT INTO service_plans (
                        plan_code, name, price, duration_days, tier_level,
                        max_storage_mb, max_upload_size_mb, max_pages_per_file, max_daily_exports,
                        max_dpi, features_text, allowed_features, badge_text, sort_order, is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $planCode, $name, $price, $durationDays, $tierLevel,
                    $maxStorageMb, $maxUploadSizeMb, $maxPagesPerFile, $maxDailyExports,
                    $maxDpi, $featuresText, $allowedFeaturesJson, $badgeText, $sortOrder, $isActive
                ]);
                $id = (int)$this->pdo->lastInsertId();
                $msg = "Đã tạo thành công gói dịch vụ mới {$name} ({$planCode})!";
            }

            response()->json([
                'status' => 'success',
                'message' => $msg,
                'id' => $id
            ]);
        } catch (Exception $e) {
            response()->json(['status' => 'error', 'message' => 'Lỗi lưu gói: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Bật / Tắt trạng thái gói nhanh
     */
    public function togglePlan(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            response()->json(['status' => 'error', 'message' => 'ID gói không hợp lệ.'], 400);
            return;
        }

        if (!$this->pdo) {
            response()->json(['status' => 'error', 'message' => 'Lỗi CSDL.'], 500);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT plan_code, is_active, name FROM service_plans WHERE id = ?");
            $stmt->execute([$id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$plan) {
                response()->json(['status' => 'error', 'message' => 'Không tìm thấy gói.'], 404);
                return;
            }

            if ($plan['plan_code'] === 'FREE' && (int)$plan['is_active'] === 1) {
                response()->json(['status' => 'error', 'message' => 'Gói FREE mặc định không thể tắt.'], 400);
                return;
            }

            $newStatus = ((int)$plan['is_active'] === 1) ? 0 : 1;
            $upStmt = $this->pdo->prepare("UPDATE service_plans SET is_active = ? WHERE id = ?");
            $upStmt->execute([$newStatus, $id]);

            $statusText = ($newStatus === 1) ? 'kích hoạt' : 'tạm tắt';
            response()->json([
                'status' => 'success',
                'message' => "Đã {$statusText} gói {$plan['name']} thành công.",
                'is_active' => $newStatus
            ]);
        } catch (Exception $e) {
            response()->json(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Xóa gói dịch vụ an toàn
     */
    public function deletePlan(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            response()->json(['status' => 'error', 'message' => 'ID gói không hợp lệ.'], 400);
            return;
        }

        if (!$this->pdo) {
            response()->json(['status' => 'error', 'message' => 'Lỗi CSDL.'], 500);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT plan_code, name FROM service_plans WHERE id = ?");
            $stmt->execute([$id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$plan) {
                response()->json(['status' => 'error', 'message' => 'Gói không tồn tại.'], 404);
                return;
            }

            if ($plan['plan_code'] === 'FREE') {
                response()->json(['status' => 'error', 'message' => 'Không thể xóa gói FREE mặc định của hệ thống.'], 400);
                return;
            }

            // Kiểm tra người dùng đang dùng
            $uStmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE plan = ?");
            $uStmt->execute([$plan['plan_code']]);
            $userCount = (int)$uStmt->fetchColumn();
            if ($userCount > 0) {
                response()->json([
                    'status' => 'error',
                    'message' => "Không thể xóa gói do hiện có {$userCount} người dùng đang thuộc gói này. Bạn có thể chọn Tắt gói để ẩn khỏi danh sách đăng ký mới."
                ], 400);
                return;
            }

            $delStmt = $this->pdo->prepare("DELETE FROM service_plans WHERE id = ?");
            $delStmt->execute([$id]);

            response()->json([
                'status' => 'success',
                'message' => "Đã xóa hoàn toàn gói {$plan['name']}."
            ]);
        } catch (Exception $e) {
            response()->json(['status' => 'error', 'message' => 'Lỗi xóa gói: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Thống kê dung lượng ổ đĩa và thư mục bảo trì (/admin/maintenance)
     */
    public function maintenanceStatus(): void
    {
        $baseDir = dirname(__DIR__, 3) . '/storage';

        // 1. Thống kê ổ đĩa máy chủ
        $diskPath = file_exists($baseDir) ? $baseDir : dirname(__DIR__, 3);
        $totalBytes = @disk_total_space($diskPath) ?: (100 * 1024 * 1024 * 1024);
        $freeBytes = @disk_free_space($diskPath) ?: (60 * 1024 * 1024 * 1024);
        $usedBytes = max(0, $totalBytes - $freeBytes);
        $usedPercent = $totalBytes > 0 ? round(($usedBytes / $totalBytes) * 100, 1) : 0;

        // 2. Thống kê từng thư mục
        $scanFolder = function(string $dir): array {
            $count = 0;
            $bytes = 0;
            if (is_dir($dir)) {
                $files = scandir($dir);
                foreach ($files as $f) {
                    if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
                    $fp = $dir . '/' . $f;
                    if (is_file($fp)) {
                        $count++;
                        $bytes += filesize($fp);
                    }
                }
            }
            return [
                'count' => $count,
                'bytes' => $bytes,
                'mb' => round($bytes / (1024 * 1024), 2)
            ];
        };

        $docsStat = $scanFolder($baseDir . '/documents');
        $exportsStat = $scanFolder($baseDir . '/exports');
        $previewsStat = $scanFolder($baseDir . '/previews');
        
        $tempDir = $baseDir . '/temp';
        if (!is_dir($tempDir)) @mkdir($tempDir, 0777, true);
        $tempStat = $scanFolder($tempDir);

        // 3. Quét số tệp mồ côi (Orphans)
        $orphanDocs = 0;
        $orphanExports = 0;
        $orphanBytes = 0;
        if ($this->pdo) {
            try {
                $activeDocs = $this->pdo->query("SELECT stored_filename FROM documents")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $activeExp = $this->pdo->query("SELECT stored_path, filename FROM outputs")->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $validExpNames = [];
                foreach ($activeExp as $ae) {
                    $validExpNames[] = basename($ae['stored_path']);
                    $validExpNames[] = basename($ae['filename']);
                }

                $docDir = $baseDir . '/documents';
                if (is_dir($docDir)) {
                    foreach (scandir($docDir) as $f) {
                        if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
                        if (!in_array($f, $activeDocs)) {
                            $orphanDocs++;
                            $orphanBytes += @filesize($docDir . '/' . $f);
                        }
                    }
                }

                $expDir = $baseDir . '/exports';
                if (is_dir($expDir)) {
                    foreach (scandir($expDir) as $f) {
                        if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
                        if (!in_array($f, $validExpNames)) {
                            $orphanExports++;
                            $orphanBytes += @filesize($expDir . '/' . $f);
                        }
                    }
                }
            } catch (Exception $e) {}
        }

        // 4. Đọc trạng thái bảo trì hệ thống
        $maintFile = $baseDir . '/maintenance.json';
        $maintConfig = [
            'maintenance_mode' => false,
            'message' => 'Hệ thống đang tiến hành nâng cấp & bảo trì định kỳ. Quý khách vui lòng quay lại sau ít phút!',
            'updated_at' => date('d/m/Y H:i:s')
        ];
        if (file_exists($maintFile)) {
            $readData = json_decode(file_get_contents($maintFile), true);
            if (is_array($readData)) {
                $maintConfig = array_merge($maintConfig, $readData);
            }
        }

        response()->json([
            'status' => 'success',
            'disk' => [
                'total_gb' => round($totalBytes / (1024 * 1024 * 1024), 1),
                'free_gb' => round($freeBytes / (1024 * 1024 * 1024), 1),
                'used_gb' => round($usedBytes / (1024 * 1024 * 1024), 1),
                'used_percent' => $usedPercent
            ],
            'folders' => [
                'documents' => $docsStat,
                'exports' => $exportsStat,
                'previews' => $previewsStat,
                'temp' => $tempStat
            ],
            'orphans' => [
                'count' => $orphanDocs + $orphanExports,
                'orphan_docs' => $orphanDocs,
                'orphan_exports' => $orphanExports,
                'mb' => round($orphanBytes / (1024 * 1024), 2)
            ],
            'maintenance' => $maintConfig
        ]);
    }

    /**
     * Dọn sạch bộ nhớ tạm ảnh xem trước (Preview Cache Cleaner)
     */
    public function cleanPreviews(): void
    {
        $previewDir = dirname(__DIR__, 3) . '/storage/previews';
        $cleaned = 0;
        $freedBytes = 0;

        if (is_dir($previewDir)) {
            foreach (scandir($previewDir) as $f) {
                if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
                $fp = $previewDir . '/' . $f;
                if (is_file($fp)) {
                    $freedBytes += filesize($fp);
                    @unlink($fp);
                    $cleaned++;
                }
            }
        }

        $freedMb = round($freedBytes / (1024 * 1024), 2);
        response()->json([
            'status' => 'success',
            'message' => "Đã dọn sạch {$cleaned} tệp bộ nhớ tạm xem trước (Giải phóng {$freedMb} MB).",
            'cleaned_count' => $cleaned,
            'freed_mb' => $freedMb
        ]);
    }

    /**
     * Bật / Tắt chế độ bảo trì hệ thống (Maintenance Mode)
     */
    public function saveMaintenanceMode(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $mode = !empty($input['maintenance_mode']) ? true : false;
        $message = trim($input['message'] ?? '');
        if (empty($message)) {
            $message = 'Hệ thống đang tiến hành nâng cấp & bảo trì định kỳ. Quý khách vui lòng quay lại sau ít phút!';
        }

        $baseDir = dirname(__DIR__, 3) . '/storage';
        if (!is_dir($baseDir)) @mkdir($baseDir, 0777, true);
        $maintFile = $baseDir . '/maintenance.json';

        $data = [
            'maintenance_mode' => $mode,
            'message' => $message,
            'updated_at' => date('d/m/Y H:i:s'),
            'updated_by' => $_SESSION['user_name'] ?? 'Admin'
        ];

        file_put_contents($maintFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $stText = $mode ? 'ĐÃ BẬT' : 'ĐÃ TẮT';
        response()->json([
            'status' => 'success',
            'message' => "Cập nhật thành công: Chế độ bảo trì hệ thống {$stText}.",
            'data' => $data
        ]);
    }
}

