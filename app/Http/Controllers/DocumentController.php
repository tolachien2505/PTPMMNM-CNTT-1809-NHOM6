<?php

namespace App\Http\Controllers;

use App\Services\SecurityValidator;
use App\Services\StorageService;
use Exception;
use PDO;
use setasign\Fpdi\Fpdi;

/**
 * Controller Quản lý Tài liệu PDF (Laravel Standard)
 * Xử lý tải lên, liệt kê, xóa và truy xuất tài liệu theo quyền người dùng
 */
class DocumentController
{
    private ?PDO $pdo;
    private SecurityValidator $validator;
    private StorageService $storage;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
        $this->validator = new SecurityValidator();
        $this->storage = new StorageService();
    }

    /**
     * Danh sách tài liệu của người dùng hiện tại
     */
    public function index(): void
    {
        $user = currentUser();
        if (!$user) {
            response()->json(['status' => 'success', 'data' => []]);
        }

        $docs = [];
        if ($this->pdo) {
            $stmt = $this->pdo->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY id DESC");
            $stmt->execute([$user['id']]);
            $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        response()->json(['status' => 'success', 'data' => $docs]);
    }

    /**
     * Tải lên tài liệu PDF mới
     */
    public function upload(): void
    {
        $user = currentUser();
        if (!$user) {
            response()->json(['status' => 'error', 'message' => 'Vui lòng đăng nhập để lưu tài liệu lên máy chủ.'], 401);
        }
        if (!$this->pdo) {
            response()->json(['status' => 'error', 'message' => 'Không thể kết nối cơ sở dữ liệu.'], 503);
        }
        $userId = (int)$user['id'];

        if (!isset($_FILES['file'])) {
            response()->json(['status' => 'error', 'message' => 'Không tìm thấy tệp tải lên.'], 400);
        }

        $file = $_FILES['file'];
        $destPath = null;
        try {
            $this->validator->validateUpload($file);
            $origName = basename($file['name']);
            $storedName = 'doc_' . bin2hex(random_bytes(12)) . '.pdf';
            $destPath = dirname(__DIR__, 3) . '/storage/documents/' . $storedName;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                throw new Exception('Không thể lưu tài liệu lên máy chủ.');
            }

            $pageCount = 1;
            try {
                $f = new Fpdi();
                $pageCount = $f->setSourceFile($destPath);
            } catch (Exception $e) {
                $pageCount = 1;
            }

            $docId = time();
            if ($this->pdo) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO documents (user_id, original_name, stored_filename, file_size, total_pages, status)
                    VALUES (?, ?, ?, ?, ?, 'uploaded')
                ");
                $stmt->execute([$userId, $origName, $storedName, $file['size'], $pageCount]);
                $docId = $this->pdo->lastInsertId();
            }

            response()->json([
                'status' => 'success',
                'message' => 'Tải lên tài liệu thành công!',
                'document' => [
                    'id' => $docId,
                    'name' => $origName,
                    'pages' => $pageCount,
                    'size' => $file['size'],
                    'raw_url' => '/api/documents/raw?id=' . $docId
                ]
            ]);
        } catch (Exception $e) {
            if ($destPath && is_file($destPath)) @unlink($destPath);
            response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Xóa tài liệu theo quyền sở hữu
     */
    public function delete(): void
    {
        $user = currentUser();
        if (!$user) {
            response()->json(['status' => 'error', 'message' => 'Vui lòng đăng nhập để thực hiện thao tác xóa tài liệu.'], 401);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $docId = (int)($input['id'] ?? 0);
        $docIds = $input['ids'] ?? [];
        if ($docId > 0 && !in_array($docId, $docIds)) {
            $docIds[] = $docId;
        }

        if (empty($docIds)) {
            response()->json(['status' => 'error', 'message' => 'Vui lòng chọn tài liệu cần xóa.'], 400);
        }

        $deletedCount = 0;
        if ($this->pdo) {
            $inClause = implode(',', array_fill(0, count($docIds), '?'));
            $isAdmin = $user['is_admin'] ?? false;
            $params = $docIds;
            if (!$isAdmin) {
                $params[] = $user['id'];
                $whereClause = "id IN ($inClause) AND user_id = ?";
            } else {
                $whereClause = "id IN ($inClause)";
            }

            try {
                $stmt = $this->pdo->prepare("SELECT stored_filename FROM documents WHERE $whereClause");
                $stmt->execute($params);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $p = dirname(__DIR__, 3) . '/storage/documents/' . $row['stored_filename'];
                    if (file_exists($p)) @unlink($p);
                }

                $delStmt = $this->pdo->prepare("DELETE FROM documents WHERE $whereClause");
                $delStmt->execute($params);
                $deletedCount = $delStmt->rowCount();
            } catch (Exception $e) {
                response()->json(['status' => 'error', 'message' => 'Lỗi xóa tài liệu: ' . $e->getMessage()], 500);
            }
        }

        response()->json([
            'status' => 'success',
            'message' => "Đã xóa thành công {$deletedCount} tài liệu.",
            'deleted_count' => $deletedCount
        ]);
    }

    /**
     * Cập nhật trạng thái tài liệu
     */
    public function updateStatus(): void
    {
        $user = currentUser();
        if (!$user) {
            response()->json(['status' => 'error', 'message' => 'Vui lòng đăng nhập.'], 401);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $docId = (int)($input['id'] ?? 0);
        $status = trim($input['status'] ?? 'editing');

        $allowedStatuses = ['uploaded', 'editing', 'exported', 'error'];
        if (!in_array($status, $allowedStatuses)) {
            $status = 'editing';
        }

        if ($this->pdo && $docId > 0) {
            $isAdmin = $user['is_admin'] ?? false;
            if ($isAdmin) {
                $stmt = $this->pdo->prepare("UPDATE documents SET status = ? WHERE id = ?");
                $stmt->execute([$status, $docId]);
            } else {
                $stmt = $this->pdo->prepare("UPDATE documents SET status = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$status, $docId, $user['id']]);
            }
        }

        response()->json([
            'status' => 'success',
            'message' => 'Đã cập nhật trạng thái tài liệu.',
            'new_status' => $status
        ]);
    }

    /**
     * Đọc tệp PDF thô cho trình duyệt / PDF.js
     * Hỗ trợ nạp theo docId hoặc theo tên file mẫu / export
     */
    public function raw(): void
    {
        $user = currentUser();
        $docId = (int)($_GET['id'] ?? 0);
        $docName = basename($_GET['name'] ?? '');
        $filePath = null;

        if ($docId > 0 && $this->pdo) {
            if (!$user) {
                response()->json(['status' => 'error', 'message' => 'Vui lòng đăng nhập.'], 401);
            }
            $stmt = $this->pdo->prepare("SELECT stored_filename, original_name, user_id FROM documents WHERE id = ? AND (user_id = ? OR ? = 1)");
            $stmt->execute([$docId, $user['id'], !empty($user['is_admin']) ? 1 : 0]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($doc) {
                $p1 = dirname(__DIR__, 3) . '/storage/documents/' . $doc['stored_filename'];
                $p2 = dirname(__DIR__, 3) . '/storage/documents/' . ($doc['original_name'] ?? '');
                if (file_exists($p1)) {
                    $filePath = $p1;
                } elseif ($p2 && file_exists($p2)) {
                    $filePath = $p2;
                }
            }
        }

        if (!$filePath && !$docId && in_array($docName, ['Bai_giang_Tin_hoc.pdf', 'De_cuong_mon_hoc.pdf'], true)) {
            $filePath = dirname(__DIR__, 3) . '/storage/documents/' . $docName;
        }

        if ($filePath && file_exists($filePath)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Accept-Ranges: bytes');
            header('Cache-Control: private, no-store');
            readfile($filePath);
            exit;
        }

        response()->json(['status' => 'error', 'message' => 'Không tìm thấy tệp hoặc bạn không có quyền truy cập.'], 404);
    }
}
