<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Phép thử bắt buộc 1:
 * Ghép A2, B1, A1: tệp kết quả có đúng ba trang theo thứ tự và nhãn tương ứng.
 */
class TestCase1_MergeTest extends TestCase
{
    public function testMergeSpecificPagesInOrder(): void
    {
        $plan = [
            ['source_doc' => 'Tai_lieu_hoc_tap.pdf', 'source_page' => 2, 'label' => 'A2'],
            ['source_doc' => 'De_cuong_mon_hoc.pdf', 'source_page' => 1, 'label' => 'B1'],
            ['source_doc' => 'Tai_lieu_hoc_tap.pdf', 'source_page' => 1, 'label' => 'A1'],
        ];

        $this->assertCount(3, $plan, "Kế hoạch phải có đúng 3 trang.");
        $this->assertEquals('A2', $plan[0]['label'], "Trang thứ nhất phải là A2.");
        $this->assertEquals('B1', $plan[1]['label'], "Trang thứ hai phải là B1.");
        $this->assertEquals('A1', $plan[2]['label'], "Trang thứ ba phải là A1.");
    }
}
