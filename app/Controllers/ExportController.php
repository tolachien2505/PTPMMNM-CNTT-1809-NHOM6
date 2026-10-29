<?php

namespace App\Controllers;

use App\Services\PdfProcessorService;
use App\Utils\ResponseHelper;

/**
 * Controller xử lý xuất tài liệu (Chức năng 3 & Chức năng 5)
 * - Ghép các trang thành một tài liệu hoặc tách thành các nhóm trang
 * - Theo dõi trạng thái từng lần xuất (export_jobs)
 * - Tải kết quả, tra cứu nguồn gốc từng trang xuất ra
 */
class ExportController
{
    protected PdfProcessorService $processor;

    public function __construct(PdfProcessorService $processor)
    {
        $this->processor = $processor;
    }

    public function merge(): void
    {
        // Thực hiện ghép trang theo kế hoạch
        ResponseHelper::json(['status' => 'success', 'job_id' => 1]);
    }

    public function split(): void
    {
        // Thực hiện tách trang theo nhóm
        ResponseHelper::json(['status' => 'success', 'job_id' => 2]);
    }

    public function history(): void
    {
        // Lịch sử các lần xuất của người dùng
        ResponseHelper::json(['status' => 'success', 'history' => []]);
    }

    public function download(int $outputId): void
    {
        // Tải kết quả an toàn (không lộ đường dẫn vật lý trên server)
    }

    public function manifest(int $outputId): void
    {
        // Xem danh sách trang nguồn đối chiếu của bản xuất
        ResponseHelper::json(['status' => 'success', 'manifest' => []]);
    }
}
