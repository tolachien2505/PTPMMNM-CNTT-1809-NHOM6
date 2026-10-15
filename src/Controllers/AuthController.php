<?php

namespace App\Controllers;

use App\Utils\ResponseHelper;

/**
 * Controller xác thực người dùng (Authentication Controller)
 * Hỗ trợ chuyển đổi hoặc đăng nhập giữa các tài khoản người dùng để kiểm thử cô lập dữ liệu
 */
class AuthController
{
    public function login(): void
    {
        ResponseHelper::json(['status' => 'success', 'message' => 'Đăng nhập thành công']);
    }

    public function currentUser(): void
    {
        // Trả về thông tin người dùng phiên hiện tại
        ResponseHelper::json([
            'status' => 'success',
            'user' => [
                'id' => 1,
                'name' => 'Nguyễn Văn A',
                'role' => 'Sinh viên',
                'email' => 'nguyenvana@dnu.edu.vn'
            ]
        ]);
    }

    public function logout(): void
    {
        ResponseHelper::json(['status' => 'success', 'message' => 'Đã đăng xuất']);
    }
}
