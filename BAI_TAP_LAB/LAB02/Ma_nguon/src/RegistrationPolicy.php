<?php
declare(strict_types=1);
final class RegistrationPolicy {
    public function decide(int $capacity, int $registered,
        bool $open, bool $waitingEnabled): string {
        // Kiểm tra dữ liệu không hợp lệ
        if ($capacity <= 0 || $registered < 0 || $registered > $capacity) {
            return 'rejected';
        }

        // Sự kiện đóng thì từ chối, kể cả còn chỗ
        if (!$open) {
            return 'rejected';
        }

        // Còn chỗ thì xác nhận
        if ($registered < $capacity) {
            return 'confirmed';
        }

        // Hết chỗ: nếu có danh sách chờ thì chờ, không thì từ chối
        return $waitingEnabled ? 'waiting' : 'rejected';
    }
}
