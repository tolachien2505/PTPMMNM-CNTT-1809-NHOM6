<?php

namespace App\Services;

use Exception;

/**
 * Service kiểm tra an toàn và bảo mật (Security & Validation)
 * Đảm bảo:
 * - Kiểm tra định dạng (MIME type thực tế), dung lượng tối đa và thời gian chạy
 * - Phân quyền truy cập tài liệu: Người dùng chỉ được truy cập tệp của chính họ
 * - Tuyệt đối không lộ đường dẫn lưu trữ nội bộ máy chủ
 * - Ngăn chặn Command Injection (không cho phép nhận lệnh tùy ý)
 */
class SecurityValidator
{
    protected int $maxSizeBytes;
    protected array $allowedMimeTypes;

    public function __construct(int $maxSizeBytes = 26214400, array $allowedMimeTypes = ['application/pdf'])
    {
        $this->maxSizeBytes = $maxSizeBytes;
        $this->allowedMimeTypes = $allowedMimeTypes;
    }

    /**
     * Xác thực tệp tải lên
     */
    public function validateUpload(array $file): bool
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception("Tệp tải lên không hợp lệ.");
        }

        if ($file['size'] > $this->maxSizeBytes) {
            throw new Exception("Dung lượng tệp vượt quá giới hạn cho phép.");
        }

        if ($file['size'] === 0) {
            throw new Exception("Tệp rỗng (0 bytes).");
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $this->allowedMimeTypes, true)) {
            throw new Exception("Định dạng tệp không được hỗ trợ (chỉ chấp nhận PDF).");
        }

        return true;
    }

    /**
     * Kiểm tra quyền truy cập tài liệu của người dùng
     */
    public function checkOwnership(int $userId, int $documentUserId): bool
    {
        if ($userId !== $documentUserId) {
            throw new Exception("Bạn không có quyền truy cập hoặc chỉnh sửa tài liệu này.");
        }
        return true;
    }

    /**
     * Làm sạch đường dẫn để chống Path Traversal
     */
    public function sanitizeFileName(string $filename): string
    {
        return basename($filename);
    }
}
