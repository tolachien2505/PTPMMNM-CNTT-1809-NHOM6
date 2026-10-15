<?php

namespace App\Models;

/**
 * Model Trang nguồn (Source Page)
 * Quản lý định danh từng trang, nhãn kiểm thử (label) và hướng hiển thị
 */
class SourcePage
{
    public int $id;
    public int $documentId;
    public int $pageNumber; // 1-indexed
    public ?string $label;   // Nhãn nhận biết (A1, A2, B1...)
    public float $width;
    public float $height;
    public string $orientation; // 'portrait', 'landscape'
    public ?string $thumbnailPath;
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
