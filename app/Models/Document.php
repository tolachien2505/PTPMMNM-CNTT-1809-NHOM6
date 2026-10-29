<?php

namespace App\Models;

/**
 * Model Tài liệu PDF gốc
 * Lưu trữ thông tin tài liệu đã tải lên, đảm bảo tệp gốc bất biến (immutable)
 */
class Document
{
    public int $id;
    public int $userId;
    public string $originalName;
    public string $storedFilename;
    public int $fileSize;
    public int $totalPages;
    public string $status; // 'valid', 'empty', 'corrupted', 'oversize'
    public ?string $errorMessage;
    public ?string $sha256Hash;
    public string $createdAt;

    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }
}
