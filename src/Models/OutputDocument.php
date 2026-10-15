<?php

namespace App\Models;

/**
 * Model Tài liệu đầu ra (Output Document)
 * Lưu trữ tệp xuất ra và manifest đối chiếu trang nguồn
 */
class OutputDocument
{
    public int $id;
    public int $exportJobId;
    public int $userId;
    public string $filename;
    public string $storedPath;
    public int $fileSize;
    public int $totalPages;
    public array $sourceManifest; // JSON danh sách trang nguồn đối chiếu
    public string $createdAt;

    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            if ($key === 'source_manifest' && is_string($value)) {
                $this->sourceManifest = json_decode($value, true) ?: [];
            } elseif (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }
}
