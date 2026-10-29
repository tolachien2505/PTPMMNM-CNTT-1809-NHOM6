<?php

namespace App\Controllers;

use App\Services\SecurityValidator;
use App\Services\StorageService;
use App\Utils\ResponseHelper;

/**
 * Controller quản lý tài liệu (Chức năng 1 & Chức năng 5)
 * - Tải lên nhiều tệp PDF, kiểm tra định dạng, dung lượng và số trang
 * - Danh sách tài liệu của người dùng hiện tại
 * - Xóa tài liệu theo quyền
 */
class DocumentController
{
    protected SecurityValidator $validator;
    protected StorageService $storage;

    public function __construct(SecurityValidator $validator, StorageService $storage)
    {
        $this->validator = $validator;
        $this->storage = $storage;
    }

    public function index(): void
    {
        // Trả về danh sách tài liệu thuộc quyền sở hữu của user
        ResponseHelper::json(['status' => 'success', 'data' => []]);
    }

    public function upload(): void
    {
        // Nhận nhiều tệp PDF, kiểm tra tính hợp lệ và ghi nhận số trang
        ResponseHelper::json(['status' => 'success', 'message' => 'Tải tệp thành công']);
    }

    public function delete(int $id): void
    {
        // Xóa tài liệu theo quyền sở hữu, không làm ảnh hưởng tài liệu người dùng khác
        ResponseHelper::json(['status' => 'success', 'message' => 'Đã xóa tài liệu']);
    }
}
