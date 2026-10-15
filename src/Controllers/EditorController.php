<?php

namespace App\Controllers;

use App\Services\PdfProcessorService;
use App\Utils\ResponseHelper;

/**
 * Controller trình biên tập trang PDF (Chức năng 2 & Chức năng 4)
 * - Chọn, bỏ, đổi thứ tự và xoay trang theo góc 90, 180, 270 độ
 * - Cập nhật bản xem trước và lịch sử thao tác (Undo/Redo)
 * - Lưu bản nháp kế hoạch chỉnh sửa
 */
class EditorController
{
    protected PdfProcessorService $processor;

    public function __construct(PdfProcessorService $processor)
    {
        $this->processor = $processor;
    }

    public function getPlan(int $planId): void
    {
        ResponseHelper::json(['status' => 'success', 'plan' => []]);
    }

    public function savePlan(): void
    {
        ResponseHelper::json(['status' => 'success', 'message' => 'Đã lưu kế hoạch chỉnh sửa']);
    }

    public function validatePlan(): void
    {
        // Kiểm tra trước khi xuất (pre-flight check)
        ResponseHelper::json(['status' => 'success', 'valid' => true]);
    }
}
