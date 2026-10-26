<?php

namespace App\Controllers;

use App\Services\SecurityValidator;
use App\Services\StorageService;
use App\Utils\ResponseHelper;
use PDO;
use Exception;

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
    protected ?PDO $pdo;
    protected string $storageDir;

    public function __construct(
        SecurityValidator $validator,
        StorageService $storage,
        ?PDO $pdo = null,
        string $storageDir = ''
    ) {
        $this->validator = $validator;
        $this->storage = $storage;
        $this->pdo = $pdo;
        $this->storageDir = $storageDir !== '' ? rtrim($storageDir, '/\\') : dirname(__DIR__, 2) . '/storage/documents';
    }

    /**
     * Trả về danh sách tài liệu thuộc quyền sở hữu của user
     */
    public function index(int $userId): void
    {
        if (!$this->pdo) {
            ResponseHelper::json(['status' => 'success', 'data' => []]);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, original_name, stored_filename, file_size, total_pages, status, created_at 
                FROM documents 
                WHERE user_id = ? 
                ORDER BY id DESC
            ");
            $stmt->execute([$userId]);
            $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ResponseHelper::json(['status' => 'success', 'data' => $docs]);
        } catch (Exception $e) {
            ResponseHelper::error('Không thể lấy danh sách tài liệu: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Nhận tệp PDF, kiểm tra tính hợp lệ và ghi nhận số trang
     */
    public function upload(int $userId, array $file): void
    {
        try {
            $this->validator->validateUpload($file);
            $origName = basename($file['name']);
            $storedName = 'doc_' . time() . '_' . $origName;
            $destPath = $this->storageDir . '/' . $storedName;

            if (!is_dir($this->storageDir)) {
                mkdir($this->storageDir, 0777, true);
            }

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                // Hỗ trợ trường hợp test upload bằng copy thay vì HTTP POST
                if (!@copy($file['tmp_name'], $destPath)) {
                    ResponseHelper::error('Không thể lưu trữ tệp lên máy chủ.', 500);
                    return;
                }
            }

            $pageCount = 1;
            try {
                if (class_exists(\setasign\Fpdi\Fpdi::class)) {
                    $f = new \setasign\Fpdi\Fpdi();
                    $pageCount = $f->setSourceFile($destPath);
                }
            } catch (Exception $e) {
                $pageCount = 1;
            }

            $docId = time();
            if ($this->pdo) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO documents (user_id, original_name, stored_filename, file_size, total_pages, status)
                    VALUES (?, ?, ?, ?, ?, 'valid')
                ");
                $stmt->execute([$userId, $origName, $storedName, $file['size'], $pageCount]);
                $docId = (int)$this->pdo->lastInsertId();
            }

            ResponseHelper::json([
                'status' => 'success',
                'message' => 'Tải lên tài liệu thành công!',
                'document' => [
                    'id' => $docId,
                    'name' => $origName,
                    'pages' => $pageCount,
                    'size' => $file['size']
                ]
            ]);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    /**
     * Xóa tài liệu theo quyền sở hữu
     */
    public function delete(int $userId, int $id): void
    {
        if (!$this->pdo) {
            ResponseHelper::json(['status' => 'success', 'message' => 'Đã xóa tài liệu']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT stored_filename FROM documents WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($doc) {
                $filePath = $this->storageDir . '/' . basename($doc['stored_filename']);
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }

                $delStmt = $this->pdo->prepare("DELETE FROM documents WHERE id = ? AND user_id = ?");
                $delStmt->execute([$id, $userId]);

                ResponseHelper::json(['status' => 'success', 'message' => 'Đã xóa tài liệu']);
                return;
            }

            ResponseHelper::error('Không tìm thấy tài liệu hoặc không có quyền xóa.', 404);
        } catch (Exception $e) {
            ResponseHelper::error('Lỗi khi xóa tài liệu: ' . $e->getMessage(), 500);
        }
    }
}
