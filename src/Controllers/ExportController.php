<?php

namespace App\Controllers;

use App\Services\PdfProcessorService;
use App\Utils\ResponseHelper;
use PDO;
use Exception;

/**
 * Controller xử lý xuất tài liệu (Chức năng 3 & Chức năng 5)
 * - Ghép các trang thành một tài liệu hoặc tách thành các nhóm trang
 * - Theo dõi trạng thái từng lần xuất (export_jobs)
 * - Tải kết quả, tra cứu nguồn gốc từng trang xuất ra (manifest)
 */
class ExportController
{
    protected PdfProcessorService $processor;
    protected ?PDO $pdo;
    protected string $storageDir;

    public function __construct(PdfProcessorService $processor, ?PDO $pdo = null, string $storageDir = '')
    {
        $this->processor = $processor;
        $this->pdo = $pdo;
        $this->storageDir = $storageDir !== '' ? rtrim($storageDir, '/\\') : dirname(__DIR__, 2) . '/storage/exports';
    }

    /**
     * Thực hiện ghép trang theo kế hoạch
     */
    public function merge(int $userId, array $plan, string $title = 'Tai_lieu_ghep.pdf'): void
    {
        try {
            $effectiveUserId = $userId > 0 ? $userId : 1;
            $result = $this->processor->mergePages($effectiveUserId, 1, $plan, $title);
            ResponseHelper::json($result);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    /**
     * Thực hiện tách trang theo nhóm
     */
    public function split(int $userId, array $groups, string $sourceDoc = 'Tai_lieu_hoc_tap.pdf'): void
    {
        try {
            $effectiveUserId = $userId > 0 ? $userId : 1;
            $result = $this->processor->splitGroups($effectiveUserId, $groups, $sourceDoc);
            ResponseHelper::json($result);
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    /**
     * Lịch sử các lần xuất của người dùng
     */
    public function history(int $userId): void
    {
        if (!$this->pdo) {
            ResponseHelper::json(['status' => 'success', 'history' => []]);
            return;
        }

        try {
            $effectiveUserId = $userId > 0 ? $userId : 1;
            $stmt = $this->pdo->prepare("
                SELECT id, user_id, filename, file_size, total_pages, source_manifest, created_at 
                FROM outputs 
                WHERE user_id = ? 
                ORDER BY id DESC 
                LIMIT 100
            ");
            $stmt->execute([$effectiveUserId]);
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
     * Xóa 1 bản ghi xuất và tệp vật lý tương ứng
     */
    public function delete(int $userId, int $outputId): void
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
            $stmt = $this->pdo->prepare("SELECT filename FROM outputs WHERE id = ? AND user_id = ?");
            $stmt->execute([$outputId, $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                ResponseHelper::error('Không tìm thấy tệp hoặc bạn không có quyền xóa tệp này.', 404);
                return;
            }

            $filePath = $this->storageDir . '/' . basename($row['filename']);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }

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
                'message' => 'Đã làm sạch toàn bộ lịch sử xuất!'
            ]);
        } catch (Exception $e) {
            ResponseHelper::error('Lỗi khi làm sạch lịch sử: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Xem danh sách trang nguồn đối chiếu của bản xuất
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
            ResponseHelper::error('Lỗi khi tra cứu đối chiếu nguồn: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tải kết quả an toàn (không lộ đường dẫn vật lý trên server)
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
