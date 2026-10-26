<?php

namespace App\Controllers;

use App\Utils\ResponseHelper;
use PDO;
use Exception;

/**
 * Controller Quản lý Cài đặt Hệ thống (Settings Controller)
 * Quản lý các cấu hình giao diện, thông số nén, xuất file, bảo mật và lưu trữ
 * Hỗ trợ lưu trữ bền vững qua tệp cấu hình JSON theo từng User ID và Session
 */
class SettingController
{
    private ?PDO $pdo;
    private string $storageDir;

    public const DEFAULT_SETTINGS = [
        'theme' => 'light',
        'language' => 'vi',
        'density' => 'comfortable',
        'auto_collapse_sidebar' => false,
        'notification_sound' => true,
        'preview_dpi' => 150,
        'compression_level' => 'balanced',
        'default_orientation' => 'original',
        'auto_page_numbers' => false,
        'keep_manifest' => true,
        'auto_cleanup' => '24h',
        'default_password' => '',
        'incognito_mode' => false
    ];

    public function __construct(?PDO $pdo = null, string $storageDir = '')
    {
        $this->pdo = $pdo;
        $this->storageDir = $storageDir !== '' ? rtrim($storageDir, '/\\') : dirname(__DIR__, 2) . '/storage/settings';
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0777, true);
        }
    }

    /**
     * Xác định User ID hiện tại từ tham số hoặc Session
     */
    private function resolveUserId(?int $userId = null): int
    {
        if ($userId !== null && $userId > 0) {
            return $userId;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return (int)($_SESSION['user_id'] ?? 1);
    }

    /**
     * Đường dẫn file cấu hình bền vững của người dùng
     */
    private function getSettingsFilePath(int $userId): string
    {
        return $this->storageDir . '/user_' . $userId . '.json';
    }

    /**
     * Lấy mảng cấu hình hiện tại của người dùng (kết hợp Session + File lưu trữ)
     */
    public function getSettingsData(?int $userId = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $effectiveUserId = $this->resolveUserId($userId);
        $settings = null;

        // 1. Kiểm tra Session trước
        if (!empty($_SESSION['user_settings']) && is_array($_SESSION['user_settings'])) {
            $settings = $_SESSION['user_settings'];
        }

        // 2. Nếu Session trống, nạp từ file JSON bền vững trên server
        if (!$settings) {
            $filePath = $this->getSettingsFilePath($effectiveUserId);
            if (file_exists($filePath)) {
                $content = @file_get_contents($filePath);
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $settings = $decoded;
                    $_SESSION['user_settings'] = $settings;
                }
            }
        }

        // 3. Nếu vẫn trống, thử nạp cấu hình chung (global fallback)
        if (!$settings) {
            $globalPath = $this->storageDir . '/settings_global.json';
            if (file_exists($globalPath)) {
                $content = @file_get_contents($globalPath);
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $settings = $decoded;
                }
            }
        }

        return array_merge(self::DEFAULT_SETTINGS, is_array($settings) ? $settings : []);
    }

    /**
     * Lấy mảng cấu hình hiện tại (Alias tiện ích cho getSettingsData)
     */
    public function getSettings(?int $userId = null): array
    {
        return $this->getSettingsData($userId);
    }

    /**
     * Lấy danh sách cấu hình hiện tại của người dùng (API Response JSON)
     */
    public function get(?int $userId = null): void
    {
        $merged = $this->getSettingsData($userId);

        ResponseHelper::json([
            'status' => 'success',
            'settings' => $merged
        ]);
    }

    /**
     * Xử lý lưu mảng cài đặt vào Session và file JSON, trả về kết quả mảng
     */
    public function saveSettings(array $data, ?int $userId = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $effectiveUserId = $this->resolveUserId($userId);

        // Lấy cấu hình hiện tại để merge
        $current = $_SESSION['user_settings'] ?? self::DEFAULT_SETTINGS;
        if (!is_array($current)) {
            $current = self::DEFAULT_SETTINGS;
        }

        // Lọc và chuẩn hóa dữ liệu đầu vào theo từng kiểu dữ liệu
        $sanitized = [];
        foreach (self::DEFAULT_SETTINGS as $key => $defaultVal) {
            if (array_key_exists($key, $data)) {
                if (is_bool($defaultVal)) {
                    $sanitized[$key] = filter_var($data[$key], FILTER_VALIDATE_BOOLEAN);
                } elseif (is_int($defaultVal)) {
                    $sanitized[$key] = (int)$data[$key];
                } else {
                    $sanitized[$key] = trim((string)$data[$key]);
                }
            } else {
                $sanitized[$key] = $current[$key] ?? $defaultVal;
            }
        }

        // Lưu vào Session
        $_SESSION['user_settings'] = $sanitized;

        // Lưu bền vững vào file JSON theo User ID trên đĩa
        $filePath = $this->getSettingsFilePath($effectiveUserId);
        @file_put_contents($filePath, json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Cập nhật file cấu hình global dự phòng
        $globalPath = $this->storageDir . '/settings_global.json';
        @file_put_contents($globalPath, json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [
            'status' => 'success',
            'message' => 'Cài đặt hệ thống đã được lưu thành công!',
            'settings' => $sanitized
        ];
    }

    /**
     * Lưu cài đặt mới của người dùng (API Response JSON)
     */
    public function save(array $data, ?int $userId = null): void
    {
        $res = $this->saveSettings($data, $userId);
        ResponseHelper::json($res);
    }

    /**
     * Khôi phục cài đặt gốc mặc định
     */
    public function reset(?int $userId = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $effectiveUserId = $this->resolveUserId($userId);
        $_SESSION['user_settings'] = self::DEFAULT_SETTINGS;

        // Ghi đè file lưu trữ bằng giá trị mặc định chuẩn
        $filePath = $this->getSettingsFilePath($effectiveUserId);
        @file_put_contents($filePath, json_encode(self::DEFAULT_SETTINGS, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        ResponseHelper::json([
            'status' => 'success',
            'message' => 'Đã khôi phục cài đặt gốc thành công!',
            'settings' => self::DEFAULT_SETTINGS
        ]);
    }
}
