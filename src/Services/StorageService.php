<?php

namespace App\Services;

/**
 * Service quản lý lưu trữ tệp (Storage Service)
 * Quản lý kho lưu trữ tệp gốc, tệp xuất, thumbnail và tệp tạm
 */
class StorageService
{
    protected string $docPath;
    protected string $exportPath;
    protected string $previewPath;

    public function __construct(string $docPath = '', string $exportPath = '', string $previewPath = '')
    {
        $this->docPath = $docPath ?: __DIR__ . '/../../storage/documents';
        $this->exportPath = $exportPath ?: __DIR__ . '/../../storage/exports';
        $this->previewPath = $previewPath ?: __DIR__ . '/../../storage/previews';
    }

    /**
     * Tạo định danh duy nhất và lưu trữ tệp an toàn
     */
    public function storeOriginalFile(string $tempPath, string $originalName): string
    {
        $uniqueName = uniqid('doc_', true) . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $destination = $this->docPath . DIRECTORY_SEPARATOR . $uniqueName;
        move_uploaded_file($tempPath, $destination);
        return $uniqueName;
    }

    public function getDocumentPath(string $storedFilename): string
    {
        return $this->docPath . DIRECTORY_SEPARATOR . basename($storedFilename);
    }

    public function getExportPath(string $storedFilename): string
    {
        return $this->exportPath . DIRECTORY_SEPARATOR . basename($storedFilename);
    }
}
