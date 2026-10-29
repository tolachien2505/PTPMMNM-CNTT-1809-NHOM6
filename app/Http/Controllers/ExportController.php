<?php

namespace App\Http\Controllers;

use App\Services\PdfProcessorService;
use App\Services\SecurityValidator;
use App\Services\StorageService;
use Exception;
use PDO;

/**
 * Controller Xuất bản Tệp PDF (Laravel Standard)
 * Xử lý lưu tệp biên tập hoàn chỉnh, ghép/tách PDF, lịch sử và đối chiếu nguồn
 */
class ExportController
{
    private ?PDO $pdo;
    private PdfProcessorService $processor;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: \Database::getPdo();
        $validator = new SecurityValidator();
        $storage = new StorageService();
        $this->processor = new PdfProcessorService($validator, $storage, $this->pdo);
    }

    /**
     * Lưu trực tiếp tệp PDF đã chỉnh sửa từ Canvas / pdf-lib
     */
    public function save(): void
    {
        $user = currentUser();
        if (!$user) response()->json(['status' => 'error', 'message' => 'Vui lòng đăng nhập để lưu bản xuất.'], 401);
        if (!$this->pdo) response()->json(['status' => 'error', 'message' => 'Không thể kết nối cơ sở dữ liệu.'], 503);
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $title = basename($input['title'] ?? 'Tai_lieu_chinh_sua.pdf');
        $pdfBase64 = $input['pdf_base64'] ?? '';
        $pageCount = (int)($input['total_pages'] ?? 1);
        $manifest = $input['manifest'] ?? [];

        if (empty($pdfBase64)) {
            response()->json(['status' => 'error', 'message' => 'Dữ liệu PDF không hợp lệ.'], 400);
        }

        $cleanBase64 = preg_replace('#^data:application/pdf;base64,#', '', $pdfBase64);
        $binary = base64_decode($cleanBase64, true);

        if (!$binary || strlen($binary) < 50 || strlen($binary) > 50 * 1024 * 1024 || !str_starts_with($binary, '%PDF-')) {
            response()->json(['status' => 'error', 'message' => 'Dữ liệu tệp PDF bị lỗi hoặc rỗng.'], 400);
        }

        $docId = (int)($input['doc_id'] ?? 0);
        if ($docId > 0) {
            $owner = $this->pdo->prepare('SELECT id FROM documents WHERE id = ? AND (user_id = ? OR ? = 1)');
            $owner->execute([$docId, $user['id'], !empty($user['is_admin']) ? 1 : 0]);
            if (!$owner->fetch()) response()->json(['status' => 'error', 'message' => 'Không có quyền truy cập tài liệu nguồn.'], 404);
        }
        $filename = 'export_' . bin2hex(random_bytes(12)) . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $title);
        if (!str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }
        $destPath = dirname(__DIR__, 3) . '/storage/exports/' . $filename;

        if (file_put_contents($destPath, $binary) === false) {
            response()->json(['status' => 'error', 'message' => 'Không thể lưu tệp lên máy chủ.'], 500);
        }

        $outputId = time();
        $fileSize = strlen($binary);

        if ($this->pdo) {
            $userId = $user['id'];
            try {
                $this->pdo->beginTransaction();
                $stmt = $this->pdo->prepare("
                    INSERT INTO outputs (export_job_id, user_id, filename, stored_path, file_size, total_pages, source_manifest)
                    VALUES (NULL, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $title, $destPath, $fileSize, $pageCount, json_encode($manifest, JSON_UNESCAPED_UNICODE)]);
                $outputId = $this->pdo->lastInsertId();

                if ($docId > 0) {
                    $upStmt = $this->pdo->prepare("UPDATE documents SET status = 'exported' WHERE id = ? AND (user_id = ? OR ? = 1)");
                    $upStmt->execute([$docId, $userId, !empty($user['is_admin']) ? 1 : 0]);
                }
                $this->pdo->commit();
            } catch (Exception $e) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                @unlink($destPath);
                error_log('PDF export save failed: ' . $e->getMessage());
                response()->json(['status' => 'error', 'message' => 'Không thể lưu thông tin bản xuất.'], 500);
            }
        }

        response()->json([
            'status' => 'success',
            'message' => 'Xuất tệp PDF thành công!',
            'output_id' => $outputId,
            'filename' => $title,
            'download_url' => '/api/download?id=' . urlencode((string)$outputId),
            'page_count' => $pageCount,
            'size' => $fileSize
        ]);
    }

    /**
     * Ghép nối nhiều tài liệu PDF
     */
    public function merge(): void
    {
        $user = currentUser();
        if (!$user) {
            response()->json(['status' => 'error', 'message' => 'Vui lòng đăng nhập.'], 401);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $docIds = $input['doc_ids'] ?? [];
        $title = $input['title'] ?? 'Tai_lieu_ghep.pdf';

        if (count($docIds) < 2) {
            response()->json(['status' => 'error', 'message' => 'Cần chọn ít nhất 2 tài liệu để ghép.'], 400);
        }

        try {
            $result = $this->processor->mergeDocuments($docIds, $user['id'], $title);
            response()->json($result);
        } catch (Exception $e) {
            response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Tách tài liệu PDF theo dải trang
     */
    public function split(): void
    {
        $user = currentUser();
        if (!$user) {
            response()->json(['status' => 'error', 'message' => 'Vui lòng đăng nhập.'], 401);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $docId = (int)($input['doc_id'] ?? 0);
        $ranges = $input['ranges'] ?? [];

        if ($docId <= 0 || empty($ranges)) {
            response()->json(['status' => 'error', 'message' => 'Thông tin tách trang không hợp lệ.'], 400);
        }

        try {
            $result = $this->processor->splitDocument($docId, $user['id'], $ranges);
            response()->json($result);
        } catch (Exception $e) {
            response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Lịch sử xuất tệp của người dùng
     */
    public function history(): void
    {
        $user = currentUser();
        if (!$user) response()->json(['status' => 'error', 'message' => 'Vui lòng đăng nhập.'], 401);
        $history = [];
        $totalBytes = 0;
        $totalPages = 0;

        if ($this->pdo) {
            $userId = (int)$user['id'];
            $stmt = $this->pdo->prepare("SELECT * FROM outputs WHERE user_id = ? ORDER BY id DESC");
            $stmt->execute([$userId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($history as $h) {
                $totalBytes += (int)($h['file_size'] ?? 0);
                $totalPages += (int)($h['total_pages'] ?? 0);
            }
        }

        $stats = [
            'total_count' => count($history),
            'total_size_mb' => round($totalBytes / (1024 * 1024), 2),
            'total_pages' => $totalPages,
            'plan' => $user['plan'] ?? 'FREE'
        ];

        response()->json([
            'status' => 'success',
            'data' => $history,
            'history' => $history,
            'stats' => $stats
        ]);
    }

    /**
     * Bảng đối chiếu nguồn tài liệu (Source Manifest)
     */
    public function manifest(): void
    {
        $user = currentUser();
        if (!$user) response()->json(['status' => 'error', 'message' => 'Vui lòng đăng nhập.'], 401);
        $outputId = (int)($_GET['id'] ?? 0);
        $manifest = [];
        $filename = '';
        $totalPages = 0;
        if ($outputId > 0 && $this->pdo) {
            $stmt = $this->pdo->prepare("SELECT filename, total_pages, source_manifest FROM outputs WHERE id = ? AND user_id = ?");
            $stmt->execute([$outputId, $user['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $filename = $row['filename'];
                $totalPages = (int)$row['total_pages'];
                $manifest = json_decode($row['source_manifest'] ?? '[]', true) ?: [];
            }
        }
        response()->json([
            'status' => 'success',
            'filename' => $filename,
            'total_pages' => $totalPages,
            'manifest' => $manifest
        ]);
    }

    /**
     * Tải xuống tệp PDF hoàn chỉnh (hỗ trợ cả id và filename)
     */
    public function download(): void
    {
        $user = currentUser();
        if (!$user) response()->json(['status' => 'error', 'message' => 'Vui lòng đăng nhập.'], 401);
        $outputId = (int)($_GET['id'] ?? 0);
        $fileParam = trim($_GET['file'] ?? '');

        if ($outputId > 0 && $this->pdo) {
            $stmt = $this->pdo->prepare("SELECT filename, stored_path FROM outputs WHERE id = ? AND user_id = ?");
            $stmt->execute([$outputId, $user['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['stored_path']) && file_exists($row['stored_path'])) {
                $dlName = $row['filename'] ?: basename($row['stored_path']);
                if (!str_ends_with(strtolower($dlName), '.pdf')) $dlName .= '.pdf';
                response()->download($row['stored_path'], $dlName);
                return;
            }
        }

        if ($fileParam) {
            $filename = basename($fileParam);
            // Tìm trong CSDL theo filename
            if ($this->pdo) {
                $stmt = $this->pdo->prepare("SELECT filename, stored_path FROM outputs WHERE user_id = ? AND (filename = ? OR stored_path LIKE ?) ORDER BY id DESC");
                $stmt->execute([$user['id'], $filename, '%' . $filename]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (!empty($row['stored_path']) && ($row['filename'] === $filename || basename($row['stored_path']) === $filename) && file_exists($row['stored_path'])) {
                        response()->download($row['stored_path'], $row['filename'] ?: $filename);
                        return;
                    }
                }
            }
        }

        response()->json(['status' => 'error', 'message' => 'Tệp không tồn tại hoặc đã bị xóa.'], 404);
    }

    /**
     * Xem trước tệp PDF trực tiếp trên trình duyệt
     */
    public function preview(): void
    {
        $user = currentUser();
        if (!$user) response()->json(['status' => 'error', 'message' => 'Vui lòng đăng nhập.'], 401);
        $outputId = (int)($_GET['id'] ?? 0);
        if ($outputId > 0 && $this->pdo) {
            $stmt = $this->pdo->prepare("SELECT stored_path, filename FROM outputs WHERE id = ? AND user_id = ?");
            $stmt->execute([$outputId, $user['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['stored_path']) && file_exists($row['stored_path'])) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . ($row['filename'] ?: 'document.pdf') . '"');
                header('Content-Length: ' . filesize($row['stored_path']));
                readfile($row['stored_path']);
                exit;
            }
        }
        response()->json(['status' => 'error', 'message' => 'Không tìm thấy tệp để xem trước.'], 404);
    }

    /**
     * Xóa 1 tệp khỏi lịch sử xuất
     */
    public function delete(): void
    {
        $user = currentUser();
        if (!$user) response()->json(['status' => 'error', 'message' => 'Vui lòng đăng nhập.'], 401);
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $outputId = (int)($input['id'] ?? 0);
        if ($outputId > 0 && $this->pdo) {
            $stmt = $this->pdo->prepare("SELECT stored_path FROM outputs WHERE id = ? AND user_id = ?");
            $stmt->execute([$outputId, $user['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) response()->json(['status' => 'error', 'message' => 'Không tìm thấy tệp.'], 404);
            if ($row && !empty($row['stored_path']) && file_exists($row['stored_path'])) {
                @unlink($row['stored_path']);
            }
            $del = $this->pdo->prepare("DELETE FROM outputs WHERE id = ? AND user_id = ?");
            $del->execute([$outputId, $user['id']]);
            response()->json(['status' => 'success', 'message' => 'Đã xóa tệp khỏi lịch sử xuất thành công.']);
            return;
        }
        response()->json(['status' => 'error', 'message' => 'Mã tệp không hợp lệ.'], 400);
    }

    /**
     * Xóa toàn bộ lịch sử xuất
     */
    public function clearAll(): void
    {
        $user = currentUser();
        if (!$user) response()->json(['status' => 'error', 'message' => 'Vui lòng đăng nhập.'], 401);
        if ($this->pdo) {
            $userId = (int)$user['id'];
            $stmt = $this->pdo->prepare("SELECT stored_path FROM outputs WHERE user_id = ?");
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                if (!empty($r['stored_path']) && file_exists($r['stored_path'])) {
                    @unlink($r['stored_path']);
                }
            }
            $del = $this->pdo->prepare("DELETE FROM outputs WHERE user_id = ?");
            $del->execute([$userId]);
            response()->json(['status' => 'success', 'message' => 'Đã xóa sạch toàn bộ lịch sử xuất tệp.']);
            return;
        }
        response()->json(['status' => 'error', 'message' => 'Không thể kết nối cơ sở dữ liệu.'], 500);
    }
}
