<?php
/**
 * API Routes (Laravel Architecture)
 * Định tuyến toàn bộ RESTful API endpoints của hệ thống PDF Manager
 */

use App\Controllers\AuthController;
use App\Controllers\PaymentController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExportController;

// 1. Xác thực & Tài khoản (Authentication)
Route::post('/api/auth/login', function() {
    $auth = new AuthController(Database::getPdo());
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $auth->login($input);
});

Route::post('/api/auth/register', function() {
    $auth = new AuthController(Database::getPdo());
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $auth->register($input);
});

Route::any('/api/auth/logout', function() {
    $auth = new AuthController(Database::getPdo());
    $auth->logout();
});

Route::post('/api/auth/plan', function() {
    $auth = new AuthController(Database::getPdo());
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $auth->upgradePlan($input);
});

Route::get('/api/auth/me', function() {
    $auth = new AuthController(Database::getPdo());
    $auth->currentUser();
});

Route::post('/api/auth/switch-user', function() {
    $auth = new AuthController(Database::getPdo());
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $auth->switchUser((int)($input['user_id'] ?? 1));
});

// Cài đặt tài khoản & Cấu hình người dùng (User Profile & System Settings)
Route::post('/api/user/profile', function() {
    $auth = new AuthController(Database::getPdo());
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $auth->updateProfile($input);
});

Route::post('/api/user/change-password', function() {
    $auth = new AuthController(Database::getPdo());
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $auth->changePassword($input);
});

Route::get('/api/user/settings', function() {
    $auth = new AuthController(Database::getPdo());
    $auth->getSettings();
});

Route::post('/api/user/settings', function() {
    $auth = new AuthController(Database::getPdo());
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $auth->saveSettings($input);
});

// Helper kiểm tra Chế độ Bảo trì Hệ thống cho Client APIs
$checkMaintenance = function() {
    $maintFile = dirname(__DIR__) . '/storage/maintenance.json';
    if (file_exists($maintFile)) {
        $maintData = json_decode(@file_get_contents($maintFile), true);
        if (is_array($maintData) && !empty($maintData['maintenance_mode'])) {
            $user = currentUser();
            $isAdmin = !empty($user['is_admin']);
            if (!$isAdmin) {
                $msg = !empty($maintData['message']) 
                    ? $maintData['message'] 
                    : 'Hệ thống đang tiến hành nâng cấp & bảo trì định kỳ. Quý khách vui lòng thử lại sau ít phút!';
                response()->json([
                    'status' => 'error',
                    'message' => $msg,
                    'maintenance' => true
                ], 503);
            }
        }
    }
};

// 2. Tài liệu PDF (Documents)
Route::get('/api/documents', function() use ($checkMaintenance) {
    $checkMaintenance();
    (new DocumentController(Database::getPdo()))->index();
});
Route::post('/api/documents/upload', function() use ($checkMaintenance) {
    $checkMaintenance();
    (new DocumentController(Database::getPdo()))->upload();
});
Route::post('/api/documents/delete', function() use ($checkMaintenance) {
    $checkMaintenance();
    (new DocumentController(Database::getPdo()))->delete();
});
Route::post('/api/documents/status', function() use ($checkMaintenance) {
    $checkMaintenance();
    (new DocumentController(Database::getPdo()))->updateStatus();
});
Route::get('/api/documents/raw', function() use ($checkMaintenance) {
    $checkMaintenance();
    (new DocumentController(Database::getPdo()))->raw();
});

// 3. Xuất bản PDF (Export & Process)
Route::post('/api/export/save', function() use ($checkMaintenance) {
    $checkMaintenance();
    (new ExportController(Database::getPdo()))->save();
});
Route::post('/api/export/merge', function() use ($checkMaintenance) {
    $checkMaintenance();
    (new ExportController(Database::getPdo()))->merge();
});
Route::post('/api/export/split', function() use ($checkMaintenance) {
    $checkMaintenance();
    (new ExportController(Database::getPdo()))->split();
});
Route::get('/api/export/history', function() use ($checkMaintenance) {
    $checkMaintenance();
    (new ExportController(Database::getPdo()))->history();
});
Route::get('/api/export/manifest', function() use ($checkMaintenance) {
    $checkMaintenance();
    (new ExportController(Database::getPdo()))->manifest();
});
Route::post('/api/export/delete', function() use ($checkMaintenance) {
    $checkMaintenance();
    (new ExportController(Database::getPdo()))->delete();
});
Route::post('/api/export/clear-all', function() use ($checkMaintenance) {
    $checkMaintenance();
    (new ExportController(Database::getPdo()))->clearAll();
});
Route::get('/api/export/preview', function() use ($checkMaintenance) {
    $checkMaintenance();
    (new ExportController(Database::getPdo()))->preview();
});
Route::get('/api/download', function() use ($checkMaintenance) {
    $checkMaintenance();
    (new ExportController(Database::getPdo()))->download();
});

// 4. Thanh toán Client (VietQR & Check Status)
Route::get('/api/payment/bank-info', function() use ($checkMaintenance) {
    $checkMaintenance();
    $pay = new PaymentController(Database::getPdo());
    response()->json(['status' => 'success', 'data' => $pay->getBankSettings()]);
});

Route::post('/api/payment/create-invoice', function() use ($checkMaintenance) {
    $checkMaintenance();
    $user = currentUser();
    if (!$user) {
        response()->json(['status' => 'error', 'message' => 'Vui lòng đăng nhập để nâng cấp gói dịch vụ.'], 401);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $pay = new PaymentController(Database::getPdo());
    $res = $pay->createInvoice((int)$user['id'], $input);
    response()->json($res);
});

Route::get('/api/payment/user-invoices', function() use ($checkMaintenance) {
    $checkMaintenance();
    $user = currentUser();
    if (!$user) {
        response()->json(['status' => 'error', 'message' => 'Vui lòng đăng nhập để xem lịch sử.'], 401);
    }
    $pay = new PaymentController(Database::getPdo());
    response()->json(['status' => 'success', 'invoices' => $pay->getUserInvoices((int)$user['id'])]);
});

Route::get('/api/payment/check-status', function() {
    $user = currentUser();
    $userId = $user ? (int)$user['id'] : 0;
    $transId = $_GET['trans_id'] ?? null;
    if (!$userId) {
        response()->json(['status' => 'error', 'message' => 'Chưa đăng nhập']);
    }
    $pay = new PaymentController(Database::getPdo());
    response()->json($pay->checkUserPaymentStatus($userId, $transId));
});

// 5. Quản trị hệ thống (Admin Portal APIs)
Route::get('/api/admin/stats', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Bạn không có quyền truy cập API Admin.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->stats();
});

Route::get('/api/admin/users', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->users();
});

Route::post('/api/admin/users/role', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->changeUserRole();
});

Route::post('/api/admin/users/plan', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->changeUserPlan();
});

Route::post('/api/admin/users/delete', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->deleteUser();
});

Route::post('/api/admin/users/update', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->updateUser();
});

Route::get('/api/admin/documents', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->documents();
});

Route::post('/api/admin/documents/delete', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->deleteDocuments();
});

Route::get('/api/admin/exports', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->exports();
});

Route::post('/api/admin/cleanup', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->cleanup();
});

// Quản trị Cấp bậc Gói dịch vụ (/admin/plans)
Route::get('/api/admin/plans', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->plans();
});

Route::post('/api/admin/plans/save', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->savePlan();
});

Route::post('/api/admin/plans/toggle', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->togglePlan();
});

Route::post('/api/admin/plans/delete', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->deletePlan();
});

// Giám sát & Bảo trì Hệ thống (/admin/maintenance)
Route::get('/api/admin/maintenance/status', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->maintenanceStatus();
});

Route::post('/api/admin/maintenance/clean-cache', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->cleanPreviews();
});

Route::post('/api/admin/maintenance/mode', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $admin = new AdminController(Database::getPdo());
    $admin->saveMaintenanceMode();
});

// 6. Quản trị Doanh thu & Ngân hàng ACB (Admin Payments)
Route::get('/api/admin/bank/settings', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $adminPay = new AdminPaymentController(Database::getPdo());
    $adminPay->getBankSettings();
});

Route::post('/api/admin/bank/settings', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $adminPay = new AdminPaymentController(Database::getPdo());
    $adminPay->updateBankSettings();
});

Route::any('/api/admin/bank/sync', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $adminPay = new AdminPaymentController(Database::getPdo());
    $adminPay->syncBankTransactions();
});

Route::get('/api/admin/invoices', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $adminPay = new AdminPaymentController(Database::getPdo());
    $adminPay->getInvoices();
});

Route::post('/api/admin/invoices/update', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $adminPay = new AdminPaymentController(Database::getPdo());
    $adminPay->updateInvoice();
});

Route::post('/api/admin/invoices/delete', function() {
    $user = currentUser();
    if (!$user || !$user['is_admin']) {
        response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập.'], 403);
    }
    $adminPay = new AdminPaymentController(Database::getPdo());
    $adminPay->deleteInvoice();
});
