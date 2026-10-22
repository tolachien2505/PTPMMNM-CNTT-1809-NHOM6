<?php

namespace App\Models;

/**
 * Model Người dùng
 * Đảm bảo phân quyền và cô lập tài nguyên giữa các tài khoản
 */
class User
{
    public int $id;
    public string $username;
    public string $email;
    public ?string $passwordHash = null;
    public string $fullName;
    public string $role; // 'admin', 'lecturer', 'student'
    public string $createdAt;

    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }
}
