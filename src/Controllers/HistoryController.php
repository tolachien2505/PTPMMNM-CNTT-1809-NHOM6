<?php

namespace App\Controllers;

use App\Utils\ResponseHelper;
use PDO;
use Exception;

/**
 * Controller Quản lý Lịch sử Xuất & Bảng đối chiếu nguồn đề (History Controller)
 * Quản lý danh sách các tệp PDF đã xuất, tra cứu nguồn gốc từng trang (manifest), tải file và xóa lịch sử
 */
class HistoryController
{
    private ?PDO $pdo;
    private string $storageDir;

    public function __construct(?PDO $pdo = null, string $storageDir = '')
    {
        $this->pdo = $pdo;
        $this->storageDir = $storageDir !== '' ? rtrim($storageDir, '/\\') : dirname(__DIR__, 2) . '/storage/exports';
    }

    /**
     * Lấy danh sách lịch sử các lần xuất của người dùng
     */
    public function list(int $userId): void
    {
        if (!$this->pdo) {
            ResponseHelper::json(['status' => 'success', 'history' => []]);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, user_id, filename, file_size, total_pages, source_manifest, created_at 
                FROM outputs 
                WHERE user_id = ? 
                ORDER BY id DESC 
                LIMIT 100
            ");
            $stmt->execute([$userId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ResponseHelper::json([
                'status' => 'success',
                'history' => $history
            ]);
        } catch (Exception $e) {
            ResponseHelper::error('Không thể tải lịch sử xuất: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Xóa 1 bản ghi lịch sử xuất và tệp vật lý tương ứng
     */
    public function delete(int $userId, int $outputId): void
    {
        if ($outputId <= 0) {
            ResponseHelper::error('Mã bản ghi xuất không hợp lệ.', 400);
            return;
        }

        if (!$this->pdo) {
            ResponseHelper::error('Không thể kết nối cơ sở dữ liệu.', 500);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT filename FROM outputs WHERE id = ? AND user_id = ?");
            $stmt->execute([$outputId, $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                ResponseHelper::error('Không tìm thấy tệp hoặc bạn không có quyền xóa tệp này.', 404);
                return;
            }

            // Xóa file vật lý trong thư mục storage nếu tồn tại
            $filePath = $this->storageDir . '/' . basename($row['filename']);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }

            // Xóa bản ghi trong cơ sở dữ liệu
            $delStmt = $this->pdo->prepare("DELETE FROM outputs WHERE id = ? AND user_id = ?");
            $delStmt->execute([$outputId, $userId]);

            ResponseHelper::json([
                'status' => 'success',
                'message' => 'Đã xóa bản ghi xuất thành công!'
            ]);
        } catch (Exception $e) {
            ResponseHelper::error('Lỗi khi xóa bản ghi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Xóa sạch toàn bộ lịch sử xuất của người dùng
     */
    public function clearAll(int $userId): void
    {
        if (!$this->pdo) {
            ResponseHelper::error('Không thể kết nối cơ sở dữ liệu.', 500);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT filename FROM outputs WHERE user_id = ?");
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $filePath = $this->storageDir . '/' . basename($row['filename']);
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            $delStmt = $this->pdo->prepare("DELETE FROM outputs WHERE user_id = ?");
            $delStmt->execute([$userId]);

            ResponseHelper::json([
                'status' => 'success',
                'message' => 'Đã làm sạch toàn bộ lịch sử xuất thành công!'
            ]);
        } catch (Exception $e) {
            ResponseHelper::error('Lỗi khi làm sạch lịch sử xuất: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Xem thông tin chi tiết và danh sách trang nguồn đối chiếu (manifest)
     */
    public function manifest(int $outputId): void
    {
        if ($outputId <= 0) {
            ResponseHelper::error('Mã bản ghi không hợp lệ.', 400);
            return;
        }

        if (!$this->pdo) {
            ResponseHelper::error('Không thể kết nối cơ sở dữ liệu.', 500);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT filename, source_manifest, total_pages, created_at FROM outputs WHERE id = ?");
            $stmt->execute([$outputId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                ResponseHelper::error('Không tìm thấy dữ liệu đối chiếu.', 404);
                return;
            }

            $manifestData = json_decode($row['source_manifest'] ?? '[]', true) ?: [];

            ResponseHelper::json([
                'status' => 'success',
                'filename' => $row['filename'],
                'total_pages' => (int)$row['total_pages'],
                'created_at' => $row['created_at'],
                'manifest' => $manifestData
            ]);
        } catch (Exception $e) {
            ResponseHelper::error('Lỗi khi tra cứu nguồn gốc bản xuất: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tải tệp PDF kết quả an toàn
     */
    public function download(string $filename): void
    {
        $safeName = basename($filename);
        $filePath = $this->storageDir . '/' . $safeName;

        if ($safeName !== '' && file_exists($filePath)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $safeName . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }

        http_response_code(404);
        echo "Tệp không tồn tại hoặc đã bị xóa.";
        exit;
    }
}
