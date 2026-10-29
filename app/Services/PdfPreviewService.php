<?php

namespace App\Services;

/**
 * Service quản lý bản xem trước (Preview Service)
 * Quản lý thumbnail từng trang, hiển thị tên tệp và số trang gốc
 */
class PdfPreviewService
{
    /**
     * Tạo hoặc lấy ảnh xem trước của trang PDF
     */
    public function getPagePreview(int $documentId, int $pageNumber, int $rotation = 0): array
    {
        return [
            'document_id' => $documentId,
            'page_number' => $pageNumber,
            'rotation'    => $rotation,
            'preview_url' => "/api/preview?doc_id={$documentId}&page={$pageNumber}&rot={$rotation}",
        ];
    }
}
