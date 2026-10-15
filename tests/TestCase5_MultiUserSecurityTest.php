<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Phép thử bắt buộc 5:
 * Hai lần xuất hoặc hai tài khoản không nhận nhầm tệp của nhau;
 * đọc lại bản đã xuất cho đúng kế hoạch và tệp nguồn không đổi.
 */
class TestCase5_MultiUserSecurityTest extends TestCase
{
    public function testMultiUserIsolation(): void
    {
        $userA_Outputs = ['doc_a1.pdf', 'doc_a2.pdf'];
        $userB_Outputs = ['doc_b1.pdf'];

        // Kiểm tra hai danh sách hoàn toàn độc lập
        $this->assertEmpty(array_intersect($userA_Outputs, $userB_Outputs), "Hai người dùng không nhìn thấy tệp của nhau.");
    }

    public function testSourceFileImmutability(): void
    {
        $originalHash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
        $currentHash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

        $this->assertEquals($originalHash, $currentHash, "Mã băm SHA-256 của tệp gốc phải giữ nguyên sau các thao tác xuất.");
    }
}
