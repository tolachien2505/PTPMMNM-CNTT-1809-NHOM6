<?php

namespace App\Services;

use App\Utils\RotatableFpdi;
use Exception;
use PDO;

/**
 * Service xử lý PDF cốt lõi (Core PDF Engine)
 * Đảm bảo:
 * - Yêu cầu 1: Quản lý danh sách trang, thứ tự thao tác. Mỗi trang đầu ra gắn đúng tệp, số trang gốc và góc xoay.
 * - Yêu cầu 2: Độc lập các lần xuất, bảo toàn tệp gốc, xử lý lỗi không ghi đè hoặc trả kết quả dở dang.
 */
class PdfProcessorService
{
    protected SecurityValidator $validator;
    protected StorageService $storage;
    protected ?PDO $pdo = null;

    public function __construct(SecurityValidator $validator, StorageService $storage, ?PDO $pdo = null)
    {
        $this->validator = $validator;
        $this->storage = $storage;
        $this->pdo = $pdo;
    }

    /**
     * Kiểm tra kế hoạch xử lý trước khi tạo tệp (Pre-flight Validation)
     */
    public function validatePlan(array $planItems): array
    {
        $errors = [];
        if (empty($planItems)) {
            $errors[] = "Kế hoạch xử lý không có trang nào được chọn.";
            return $errors;
        }

        foreach ($planItems as $index => $item) {
            $hasDoc = isset($item['doc_id']) || isset($item['source_doc']);
            $hasPage = isset($item['page_number']) || isset($item['source_page']);

            if (!$hasDoc || !$hasPage) {
                $errors[] = "Mục thứ " . ($index + 1) . " thiếu thông tin tệp hoặc số trang nguồn.";
                continue;
            }

            // Kiểm tra góc xoay hợp lệ (0, 90, 180, 270)
            $rotation = (int)($item['rotation'] ?? 0);
            if (!in_array($rotation, [0, 90, 180, 270], true)) {
                $errors[] = "Góc xoay không hợp lệ ({$rotation}°) tại mục thứ " . ($index + 1);
            }
        }

        return $errors;
    }

    /**
     * Tìm đường dẫn tệp tài liệu nguồn theo doc_id hoặc tên tệp
     */
    public function resolveDocumentPath(mixed $docIdentifier): ?string
    {
        $storageDir = __DIR__ . '/../../storage/documents';

        if (is_numeric($docIdentifier) && $this->pdo) {
            $stmt = $this->pdo->prepare("SELECT stored_filename, original_name FROM documents WHERE id = ?");
            $stmt->execute([(int)$docIdentifier]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($doc) {
                // Kiểm tra theo original_name hoặc stored_filename
                $p1 = $storageDir . '/' . $doc['original_name'];
                if (file_exists($p1)) return $p1;
                $p2 = $storageDir . '/' . $doc['stored_filename'];
                if (file_exists($p2)) return $p2;
            }
        }

        if (is_string($docIdentifier)) {
            $path = $storageDir . '/' . basename($docIdentifier);
            if (file_exists($path)) return $path;
        }

        return null;
    }

    /**
     * Ghép các trang thành một tài liệu PDF mới (Core Engine with FPDI)
     */
    public function mergePages(int $userId, int $planId, array $planItems, string $outputTitle = 'Tai_lieu_ghep.pdf'): array
    {
        // 1. Kiểm tra tính hợp lệ Pre-flight
        $validationErrors = $this->validatePlan($planItems);
        if (!empty($validationErrors)) {
            throw new Exception("Kế hoạch không hợp lệ: " . implode("; ", $validationErrors));
        }

        $exportDir = __DIR__ . '/../../storage/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0777, true);
        }

        // Tên file tạm thời (Atomic write - đảm bảo không trả kết quả dở dang nếu lỗi)
        $tempFilename = 'temp_merge_' . uniqid('', true) . '.pdf';
        $tempFilePath = $exportDir . '/' . $tempFilename;

        $finalFilename = 'xuat_ghep_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.pdf';
        $finalFilePath = $exportDir . '/' . $finalFilename;

        $pdf = new RotatableFpdi();
        $pdf->SetAutoPageBreak(false);

        $manifest = [];
        $outputPageNum = 1;

        try {
            foreach ($planItems as $item) {
                // Bỏ qua trang nếu người dùng chọn không giữ (keep = false)
                if (isset($item['keep']) && $item['keep'] === false) {
                    continue;
                }
                if (isset($item['is_selected']) && $item['is_selected'] === false) {
                    continue;
                }

                $docId = $item['doc_id'] ?? $item['source_doc'] ?? null;
                $pageNum = (int)($item['page_number'] ?? $item['source_page'] ?? 1);
                $rotation = (int)($item['rotation'] ?? 0);
                $label = $item['label'] ?? ('P' . $outputPageNum);

                $sourceFilePath = $this->resolveDocumentPath($docId);
                if (!$sourceFilePath || !file_exists($sourceFilePath)) {
                    throw new Exception("Không tìm thấy tệp nguồn cho tài liệu ID hoặc tên: '{$docId}'");
                }

                // Kiểm tra tệp rỗng hoặc hỏng
                if (filesize($sourceFilePath) === 0) {
                    throw new Exception("Tệp nguồn '{$docId}' là tệp rỗng (0 bytes).");
                }

                // Import trang với FPDI
                $pdf->setSourceFile($sourceFilePath);
                $tplIdx = $pdf->importPage($pageNum);
                $size = $pdf->getTemplateSize($tplIdx);

                $orientation = $size['orientation'];
                $w = $size['width'];
                $h = $size['height'];

                if ($rotation == 90 || $rotation == 270) {
                    $newOrientation = ($orientation === 'P') ? 'L' : 'P';
                    $pdf->AddPage($newOrientation, [$h, $w]);
                    $pdf->Rotate($rotation, $h / 2, $w / 2);
                    $pdf->useTemplate($tplIdx, ($h - $w) / 2, ($w - $h) / 2);
                    $pdf->Rotate(0);
                } else if ($rotation == 180) {
                    $pdf->AddPage($orientation, [$w, $h]);
                    $pdf->Rotate(180, $w / 2, $h / 2);
                    $pdf->useTemplate($tplIdx);
                    $pdf->Rotate(0);
                } else {
                    $pdf->AddPage($orientation, [$w, $h]);
                    $pdf->useTemplate($tplIdx);
                }

                $manifest[] = [
                    'output_page' => $outputPageNum,
                    'source_doc' => basename($sourceFilePath),
                    'source_page' => $pageNum,
                    'label' => $label,
                    'rotation' => $rotation
                ];

                $outputPageNum++;
            }

            if (empty($manifest)) {
                throw new Exception("Không có trang hợp lệ nào được thêm vào tài liệu xuất.");
            }

            // Ghi file tạm
            $pdf->Output('F', $tempFilePath);

            // Kiểm tra tính toàn vẹn file tạm
            if (!file_exists($tempFilePath) || filesize($tempFilePath) === 0) {
                throw new Exception("Lỗi ghi nhận file đầu ra: Tệp tạm rỗng hoặc không tồn tại.");
            }

            // Đổi tên nguyên tử thành file chính thức
            rename($tempFilePath, $finalFilePath);
            $finalFileSize = filesize($finalFilePath);

            // Ghi nhận vào cơ sở dữ liệu nếu có PDO
            $outputId = time();
            if ($this->pdo) {
                // Tạo export_job
                $stmtJob = $this->pdo->prepare("
                    INSERT INTO export_jobs (user_id, edit_plan_id, job_type, status, progress_percent, started_at, completed_at)
                    VALUES (?, ?, 'merge', 'completed', 100, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                $stmtJob->execute([$userId, $planId > 0 ? $planId : 1]);
                $jobId = (int)$this->pdo->lastInsertId();

                // Tạo output
                $stmtOut = $this->pdo->prepare("
                    INSERT INTO outputs (export_job_id, user_id, filename, stored_path, file_size, total_pages, source_manifest)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtOut->execute([
                    $jobId,
                    $userId,
                    $outputTitle ?: $finalFilename,
                    'storage/exports/' . $finalFilename,
                    $finalFileSize,
                    count($manifest),
                    json_encode($manifest, JSON_UNESCAPED_UNICODE)
                ]);
                $outputId = (int)$this->pdo->lastInsertId();
            }

            return [
                'status' => 'success',
                'output_id' => $outputId,
                'output_file' => $finalFilename,
                'download_url' => '/api/download?file=' . urlencode($finalFilename),
                'total_pages' => count($manifest),
                'file_size' => $finalFileSize,
                'manifest' => $manifest
            ];
        } catch (Exception $e) {
            // Xóa file tạm nếu phát sinh lỗi (Safe Failure)
            if (file_exists($tempFilePath)) {
                @unlink($tempFilePath);
            }
            throw $e;
        }
    }

    /**
     * Tách các trang thành nhiều nhóm PDF riêng biệt
     */
    public function splitGroups(int $userId, array $groups, string $sourceDoc): array
    {
        $sourceFilePath = $this->resolveDocumentPath($sourceDoc);
        if (!$sourceFilePath || !file_exists($sourceFilePath)) {
            throw new Exception("Không tìm thấy tài liệu nguồn: '{$sourceDoc}'");
        }

        $exportDir = __DIR__ . '/../../storage/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0777, true);
        }

        $generatedFiles = [];

        foreach ($groups as $gIdx => $group) {
            $pages = $group['pages'] ?? [];
            if (empty($pages)) continue;

            $groupName = $group['name'] ?? ('Nhom_' . ($gIdx + 1));
            $finalFilename = 'tach_' . $groupName . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(2)) . '.pdf';
            $finalFilePath = $exportDir . '/' . $finalFilename;

            $pdf = new RotatableFpdi();
            $pageCount = $pdf->setSourceFile($sourceFilePath);

            // Kiểm tra từng trang
            foreach ($pages as $p) {
                if ($p < 1 || $p > $pageCount) {
                    throw new Exception("Trang số {$p} không tồn tại trong tài liệu nguồn (tổng số trang là {$pageCount}).");
                }
                $tplIdx = $pdf->importPage($p);
                $size = $pdf->getTemplateSize($tplIdx);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplIdx);
            }

            $pdf->Output('F', $finalFilePath);

            $generatedFiles[] = [
                'group' => $groupName,
                'file' => $finalFilename,
                'download_url' => '/api/download?file=' . urlencode($finalFilename),
                'pages' => $pages,
                'total_pages' => count($pages),
                'file_size' => filesize($finalFilePath)
            ];
        }

        return [
            'status' => 'success',
            'generated_files' => $generatedFiles
        ];
    }
}
