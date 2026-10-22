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
                SELECT id, username, email, full_name, role, password_hash 
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
                ResponseHelper::error('Mật khẩu không chính xác. Mật khẩu mẫu: 123456', 401);
                return;
            }

            // Thiết lập Session
            $roleLabel = match ($user['role']) {
                'admin' => 'Quản trị viên',
                'lecturer' => 'Giảng viên',
                default => 'Sinh viên',
            };

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $roleLabel;
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['username'] = $user['username'];

            ResponseHelper::json([
                'status' => 'success',
                'message' => 'Đăng nhập thành công! Chào mừng ' . $user['full_name'],
                'user' => [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'name' => $user['full_name'],
                    'role' => $roleLabel,
                    'email' => $user['email']
                ]
            ]);
        } catch (Exception $e) {
            ResponseHelper::error('Lỗi đăng nhập: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Đăng ký tài khoản mới
     */
    public function register(array $data): void
    {
        $fullName = trim($data['full_name'] ?? '');
        $username = strtolower(trim($data['username'] ?? ''));
        $email = strtolower(trim($data['email'] ?? ''));
        $password = trim($data['password'] ?? '');
        $role = trim($data['role'] ?? 'student');

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

        if (!in_array($role, ['student', 'lecturer', 'admin'])) {
            $role = 'student';
        }

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

            // Thêm người dùng mới
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password_hash, full_name, role)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $email, $passwordHash, $fullName, $role]);
            $newId = (int)$this->pdo->lastInsertId();

            $roleLabel = match ($role) {
                'admin' => 'Quản trị viên',
                'lecturer' => 'Giảng viên',
                default => 'Sinh viên',
            };

            // Tự động đăng nhập
            $_SESSION['user_id'] = $newId;
            $_SESSION['user_name'] = $fullName;
            $_SESSION['user_role'] = $roleLabel;
            $_SESSION['user_email'] = $email;
            $_SESSION['username'] = $username;

            ResponseHelper::json([
                'status' => 'success',
                'message' => 'Đăng ký tài khoản thành công! Bạn đã được tự động đăng nhập.',
                'user' => [
                    'id' => $newId,
                    'username' => $username,
                    'name' => $fullName,
                    'role' => $roleLabel,
                    'email' => $email
                ]
            ], 201);
        } catch (Exception $e) {
            ResponseHelper::error('Lỗi đăng ký tài khoản: ' . $e->getMessage(), 500);
        }
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

        ResponseHelper::json([
            'status' => 'success',
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'] ?? 'Người dùng',
                'role' => $_SESSION['user_role'] ?? 'Sinh viên',
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
        if (session_id() !== '' || headers_sent() === false) {
            session_destroy();
        }

        ResponseHelper::json([
            'status' => 'success',
            'message' => 'Đã đăng xuất phiên làm việc thành công.'
        ]);
    }

    /**
     * Chuyển đổi nhanh giữa các tài khoản mẫu (phục vụ kiểm thử phân quyền đề tài)
     */
    public function switchUser(int $targetId): void
    {
        if (!$this->pdo) {
            ResponseHelper::error('Không thể kết nối cơ sở dữ liệu.', 500);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id, username, email, full_name, role FROM users WHERE id = ?");
            $stmt->execute([$targetId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                ResponseHelper::error('Không tìm thấy tài khoản mẫu.', 404);
                return;
            }

            $roleLabel = match ($user['role']) {
                'admin' => 'Quản trị viên',
                'lecturer' => 'Giảng viên',
                default => 'Sinh viên',
            };

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $roleLabel;
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['username'] = $user['username'];

            ResponseHelper::json([
                'status' => 'success',
                'message' => 'Đã chuyển sang tài khoản: ' . $user['full_name'] . ' (' . $roleLabel . ')',
                'user' => [
                    'id' => (int)$user['id'],
                    'name' => $user['full_name'],
                    'role' => $roleLabel,
                    'email' => $user['email']
                ]
            ]);
        } catch (Exception $e) {
            ResponseHelper::error('Lỗi chuyển đổi tài khoản: ' . $e->getMessage(), 500);
        }
    }
}
