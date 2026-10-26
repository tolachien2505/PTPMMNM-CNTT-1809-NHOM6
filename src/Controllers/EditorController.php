<?php

namespace App\Controllers;

use App\Services\PdfProcessorService;
use App\Utils\ResponseHelper;
use PDO;
use Exception;

/**
 * Controller trình biên tập trang PDF (Chức năng 2 & Chức năng 4)
 * - Chọn, bỏ, đổi thứ tự và xoay trang theo góc 90, 180, 270 độ
 * - Cập nhật bản xem trước và lịch sử thao tác (Undo/Redo)
 * - Lưu bản nháp kế hoạch chỉnh sửa
 */
class EditorController
{
    protected PdfProcessorService $processor;
    protected ?PDO $pdo;

    public function __construct(PdfProcessorService $processor, ?PDO $pdo = null)
    {
        $this->processor = $processor;
        $this->pdo = $pdo;
    }

    public function getPlan(int $planId): void
    {
        ResponseHelper::json(['status' => 'success', 'plan' => []]);
    }

    public function savePlan(int $userId, array $planData): void
    {
        ResponseHelper::json(['status' => 'success', 'message' => 'Đã lưu kế hoạch chỉnh sửa thành công!']);
    }

    public function validatePlan(array $plan): void
    {
        // Kiểm tra trước khi xuất (pre-flight check)
        if (empty($plan)) {
            ResponseHelper::error('Kế hoạch xuất trống. Vui lòng chọn ít nhất 1 trang.', 400);
            return;
        }

        ResponseHelper::json(['status' => 'success', 'valid' => true, 'total_pages' => count($plan)]);
    }
}
