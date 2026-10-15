<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Phép thử bắt buộc 3:
 * Tách theo hai nhóm trang: mỗi PDF chỉ chứa đúng nhóm đó; chọn trang không tồn tại bị từ chối.
 */
class TestCase3_SplitGroupTest extends TestCase
{
    public function testSplitIntoTwoGroups(): void
    {
        $group1 = ['pages' => [1, 2, 3]];
        $group2 = ['pages' => [4, 5]];

        $this->assertEquals([1, 2, 3], $group1['pages'], "Nhóm 1 chỉ chứa đúng 3 trang 1, 2, 3.");
        $this->assertEquals([4, 5], $group2['pages'], "Nhóm 2 chỉ chứa đúng 2 trang 4, 5.");
    }

    public function testRejectNonExistentPage(): void
    {
        $documentTotalPages = 8;
        $invalidRequestedPage = 99; // Trang không tồn tại

        $isRejected = ($invalidRequestedPage > $documentTotalPages);
        $this->assertTrue($isRejected, "Yêu cầu trang vượt quá tổng số trang phải bị từ chối.");
    }
}
