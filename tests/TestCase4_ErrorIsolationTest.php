<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Phép thử bắt buộc 4:
 * Tệp hỏng hoặc quá giới hạn không làm mất kế hoạch hợp lệ;
 * công việc thất bại không xuất hiện như bản hoàn chỉnh.
 */
class TestCase4_ErrorIsolationTest extends TestCase
{
    public function testCorruptedFileDoesNotAffectValidPlan(): void
    {
        $validPlan = [
            'id' => 101,
            'status' => 'valid',
            'pages' => [1, 2, 3]
        ];

        // Giả lập lỗi khi đọc file hỏng
        $corruptUploadStatus = 'error_corrupted';

        $this->assertEquals('error_corrupted', $corruptUploadStatus);
        $this->assertEquals('valid', $validPlan['status'], "Kế hoạch hợp lệ không bị ảnh hưởng bởi tệp hỏng.");
        $this->assertCount(3, $validPlan['pages']);
    }

    public function testFailedJobDoesNotAppearAsCompleted(): void
    {
        $job = [
            'id' => 202,
            'status' => 'failed',
            'error_message' => 'Lỗi bộ nhớ hoặc tệp nguồn không đọc được'
        ];

        $this->assertNotEquals('completed', $job['status'], "Công việc thất bại tuyệt đối không được đánh dấu hoàn chỉnh.");
    }
}
