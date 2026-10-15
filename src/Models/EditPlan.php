<?php

namespace App\Models;

/**
 * Model Kế hoạch chỉnh sửa (Edit Plan)
 * Lưu danh sách trang, thứ tự, góc xoay và trạng thái khóa khi gửi xử lý
 */
class EditPlan
{
    public int $id;
    public int $userId;
    public string $title;
    public array $planData; // Mảng cấu trúc trang đã sắp xếp
    public bool $isLocked;  // Đã chốt phiên, không thể bị sửa đổi bởi lần sau
    public string $createdAt;
    public string $updatedAt;

    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            if ($key === 'plan_data' && is_string($value)) {
                $this->planData = json_decode($value, true) ?: [];
            } elseif (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }
}
