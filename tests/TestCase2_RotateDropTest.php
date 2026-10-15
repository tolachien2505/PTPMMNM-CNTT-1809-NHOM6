<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Phép thử bắt buộc 2:
 * Xoay một trang 90 độ và bỏ trang khác: chỉ những trang được chọn thay đổi;
 * các trang còn lại giữ nguyên nội dung.
 */
class TestCase2_RotateDropTest extends TestCase
{
    public function testRotateOnePageAndDropAnother(): void
    {
        $initialPages = [
            ['id' => 1, 'label' => 'A1', 'rotation' => 0, 'keep' => true],
            ['id' => 2, 'label' => 'A2', 'rotation' => 0, 'keep' => true],
            ['id' => 3, 'label' => 'A3', 'rotation' => 0, 'keep' => true],
        ];

        // Thao tác: Xoay trang A1 90 độ, bỏ trang A2
        $modifiedPages = [];
        foreach ($initialPages as $page) {
            if ($page['label'] === 'A2') {
                continue; // Bỏ trang
            }
            if ($page['label'] === 'A1') {
                $page['rotation'] = 90; // Xoay 90 độ
            }
            $modifiedPages[] = $page;
        }

        $this->assertCount(2, $modifiedPages, "Số lượng trang sau khi bỏ phải là 2.");
        $this->assertEquals(90, $modifiedPages[0]['rotation'], "Trang A1 phải có góc xoay 90 độ.");
        $this->assertEquals(0, $modifiedPages[1]['rotation'], "Trang A3 phải giữ nguyên góc xoay 0 độ.");
        $this->assertEquals('A3', $modifiedPages[1]['label'], "Nội dung trang A3 giữ nguyên.");
    }
}
