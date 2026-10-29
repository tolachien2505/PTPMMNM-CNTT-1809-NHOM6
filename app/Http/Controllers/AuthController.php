<?php

namespace App\Controllers;

use App\Utils\ResponseHelper;
use PDO;
use Exception;

/**
 * Controller Xác thực Người dùng (Authentication Controller)
 * Quản lý Đăng nhập, Đăng ký, Đăng xuất, Lấy thông tin phiên và Chuyển đổi tài khoản thử nghiệm
 */
class AuthController
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * Đăng nhập người dùng
     */
    public function login(array $data): void
    {
        $loginInput = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');

        if (empty($loginInput) || empty($password)) {
            ResponseHelper::error('Vui lòng nhập đầy đủ tên đăng nhập (hoặc email) và mật khẩu.', 400);
            return;
        }

        if (!$this->pdo) {
            ResponseHelper::error('Không thể kết nối cơ sở dữ liệu.', 500);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, username, email, full_name, role, plan, password_hash 
                FROM users 
                WHERE username = ? OR email = ?
                LIMIT 1
            ");
            $stmt->execute([$loginInput, $loginInput]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                ResponseHelper::error('Tài khoản hoặc mật khẩu không chính xác.', 401);
                return;
            }

            // Kiểm tra mật khẩu (hỗ trợ hash bcrypt hoặc fallback test 123456)
            $isValidPassword = false;
            if (!empty($user['password_hash'])) {
                $isValidPassword = password_verify($password, $user['password_hash']);
            }
            if (!$isValidPassword && $password === '123456') {
                $isValidPassword = true;
            }

            if (!$isValidPassword) {
                ResponseHelper::error('Mật khẩu không chính xác. Vui lòng kiểm tra lại.', 401);
                return;
            }

            // Thiết lập Session (Chỉ 2 vai trò: user và admin)
            $isAdmin = ($user['role'] ?? '') === 'admin' || strtolower($user['email'] ?? '') === 'vvchien25@gmail.com';
            $roleKey = $isAdmin ? 'admin' : 'user';
            $roleLabel = $isAdmin ? 'Quản trị viên' : 'Người dùng';
            $plan = !empty($user['plan']) ? strtoupper($user['plan']) : 'FREE';

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $roleLabel;
            $_SESSION['role'] = $roleKey;
            $_SESSION['is_admin'] = $isAdmin;
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_plan'] = $plan;

            ResponseHelper::json([
                'status' => 'success',
                'message' => 'Đăng nhập thành công! Chào mừng ' . $user['full_name'],
                'user' => [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'name' => $user['full_name'],
                    'role' => $roleKey,
                    'role_label' => $roleLabel,
                    'is_admin' => $isAdmin,
                    'plan' => $plan,
                    'email' => $user['email']
                ]
            ]);
        } catch (Exception $e) {
            ResponseHelper::error('Lỗi đăng nhập: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Đăng ký tài khoản mới (không cần chọn vai trò, mặc định gói FREE)
     */
    public function register(array $data): void
    {
        $fullName = trim($data['full_name'] ?? '');
        $username = strtolower(trim($data['username'] ?? ''));
        $email = strtolower(trim($data['email'] ?? ''));
        $password = trim($data['password'] ?? '');

        if (empty($fullName) || empty($username) || empty($email) || empty($password)) {
            ResponseHelper::error('Vui lòng điền đầy đủ tất cả các trường thông tin.', 400);
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            ResponseHelper::error('Tên đăng nhập từ 3 - 30 ký tự, chỉ gồm chữ cái, số và dấu gạch dưới (_).', 400);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ResponseHelper::error('Định dạng email không hợp lệ.', 400);
            return;
        }

        if (strlen($password) < 6) {
            ResponseHelper::error('Mật khẩu bảo mật phải có ít nhất 6 ký tự.', 400);
            return;
        }

        $role = 'user';
        $plan = 'FREE';

        if (!$this->pdo) {
            ResponseHelper::error('Không thể kết nối cơ sở dữ liệu.', 500);
            return;
        }

        try {
            // Kiểm tra trùng username hoặc email
            $stmt = $this->pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if ($existing['username'] === $username) {
                    ResponseHelper::error('Tên đăng nhập này đã được sử dụng. Vui lòng chọn tên khác.', 400);
                } else {
                    ResponseHelper::error('Email này đã được đăng ký trong hệ thống.', 400);
                }
                return;
            }

            // Mã hóa mật khẩu
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            // Thêm người dùng mới với gói FREE
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password_hash, full_name, role, plan)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $email, $passwordHash, $fullName, $role, $plan]);
            $newId = (int)$this->pdo->lastInsertId();

            // Tự động đăng nhập
            $isAdmin = (strtolower($email) === 'vvchien25@gmail.com');
            $roleKey = $isAdmin ? 'admin' : 'user';
            $roleLabel = $isAdmin ? 'Quản trị viên' : 'Người dùng';

            $_SESSION['user_id'] = $newId;
            $_SESSION['user_name'] = $fullName;
            $_SESSION['user_role'] = $roleLabel;
            $_SESSION['role'] = $roleKey;
            $_SESSION['is_admin'] = $isAdmin;
            $_SESSION['user_email'] = $email;
            $_SESSION['username'] = $username;
            $_SESSION['user_plan'] = 'FREE';

            ResponseHelper::json([
                'status' => 'success',
                'message' => 'Đăng ký tài khoản thành công! Chào mừng ' . $fullName,
                'user' => [
                    'id' => $newId,
                    'username' => $username,
                    'name' => $fullName,
                    'role' => $roleKey,
                    'role_label' => $roleLabel,
                    'is_admin' => $isAdmin,
                    'plan' => 'FREE',
                    'email' => $email
                ]
            ], 201);
        } catch (Exception $e) {
            ResponseHelper::error('Lỗi đăng ký tài khoản: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Nâng cấp hoặc chuyển đổi gói (FREE / PRO)
     */
    public function upgradePlan(array $data): void
    {
        if (!isset($_SESSION['user_id'])) {
            ResponseHelper::error('Vui lòng đăng nhập để nâng cấp gói dịch vụ.', 401);
            return;
        }

        $newPlan = strtoupper(trim($data['plan'] ?? 'PRO'));
        if (!in_array($newPlan, ['FREE', 'PRO'])) {
            $newPlan = 'PRO';
        }

        if ($this->pdo) {
            $stmt = $this->pdo->prepare("UPDATE users SET plan = ? WHERE id = ?");
            $stmt->execute([$newPlan, $_SESSION['user_id']]);
        }

        $_SESSION['user_plan'] = $newPlan;

        ResponseHelper::json([
            'status' => 'success',
            'message' => 'Chúc mừng! Tài khoản đã chuyển sang gói ' . $newPlan . ' thành công.',
            'plan' => $newPlan,
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'] ?? 'Người dùng',
                'plan' => $newPlan,
                'email' => $_SESSION['user_email'] ?? ''
            ]
        ]);
    }

    /**
     * Lấy thông tin tài khoản hiện tại
     */
    public function currentUser(): void
    {
        if (!isset($_SESSION['user_id'])) {
            ResponseHelper::json([
                'status' => 'unauthenticated',
                'user' => null
            ]);
            return;
        }

        $isAdmin = !empty($_SESSION['is_admin']) || (($_SESSION['role'] ?? '') === 'admin') || (strtolower($_SESSION['user_email'] ?? '') === 'vvchien25@gmail.com');
        $roleKey = $isAdmin ? 'admin' : 'user';
        $roleLabel = $isAdmin ? 'Quản trị viên' : 'Người dùng';

        ResponseHelper::json([
            'status' => 'success',
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'] ?? 'Người dùng',
                'role' => $roleKey,
                'role_label' => $roleLabel,
                'is_admin' => $isAdmin,
                'plan' => $_SESSION['user_plan'] ?? 'FREE',
                'email' => $_SESSION['user_email'] ?? '',
                'username' => $_SESSION['username'] ?? ''
            ]
        ]);
    }

    /**
     * Đăng xuất phiên làm việc
     */
    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        if (session_id() !== '' || !headers_sent()) {
            @session_destroy();
        }

        ResponseHelper::json([
            'status' => 'success',
            'message' => 'Đã đăng xuất phiên làm việc thành công.'
        ]);
    }

    /**
     * Chuyển đổi nhanh tài khoản
     */
    public function switchUser(int $targetId): void
    {
        if (!$this->pdo) {
            ResponseHelper::error('Không thể kết nối cơ sở dữ liệu.', 500);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id, username, email, full_name, role, plan FROM users WHERE id = ?");
            $stmt->execute([$targetId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                ResponseHelper::error('Không tìm thấy tài khoản.', 404);
                return;
            }

            $isAdmin = ($user['role'] ?? '') === 'admin' || strtolower($user['email'] ?? '') === 'vvchien25@gmail.com';
            $roleKey = $isAdmin ? 'admin' : 'user';
            $roleLabel = $isAdmin ? 'Quản trị viên' : 'Người dùng';
            $plan = !empty($user['plan']) ? strtoupper($user['plan']) : 'FREE';

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $roleLabel;
            $_SESSION['role'] = $roleKey;
            $_SESSION['is_admin'] = $isAdmin;
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_plan'] = $plan;

            ResponseHelper::json([
                'status' => 'success',
                'message' => 'Đã chuyển sang tài khoản: ' . $user['full_name'],
                'user' => [
                    'id' => (int)$user['id'],
                    'name' => $user['full_name'],
                    'role' => $roleKey,
                    'role_label' => $roleLabel,
                    'is_admin' => $isAdmin,
                    'plan' => $plan,
                    'email' => $user['email']
                ]
            ]);
        } catch (Exception $e) {
            ResponseHelper::error('Lỗi chuyển đổi tài khoản: ' . $e->getMessage(), 500);
        }
    }
}
