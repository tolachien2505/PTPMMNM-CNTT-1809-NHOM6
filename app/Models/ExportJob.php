<?php

namespace App\Models;

/**
 * Model Tác vụ xuất tài liệu (Export Job)
 * Quản lý trạng thái xử lý độc lập giữa các lần xuất
 */
class ExportJob
{
    public int $id;
    public int $userId;
    public int $editPlanId;
    public string $jobType; // 'merge', 'split'
    public string $status;  // 'pending', 'processing', 'completed', 'failed'
    public int $progressPercent;
    public ?string $errorMessage;
    public ?string $startedAt;
    public ?string $completedAt;
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
