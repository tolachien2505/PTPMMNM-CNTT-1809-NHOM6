<?php

use PHPUnit\Framework\TestCase;
use App\Controllers\SettingController;
use App\Controllers\HistoryController;
use App\Controllers\ExportController;
use App\Services\PdfProcessorService;
use App\Services\SecurityValidator;
use App\Services\StorageService;

class TestCase6_ControllersSyncTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Tạo cấu trúc bảng outputs mô phỏng
        $this->pdo->exec("
            CREATE TABLE outputs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                filename TEXT NOT NULL,
                file_size INTEGER DEFAULT 0,
                total_pages INTEGER DEFAULT 1,
                source_manifest TEXT DEFAULT '[]',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function testSettingControllerDefaults(): void
    {
        $controller = new SettingController($this->pdo);
        $this->assertIsArray(SettingController::DEFAULT_SETTINGS);
        $this->assertArrayHasKey('theme', SettingController::DEFAULT_SETTINGS);
        $this->assertArrayHasKey('preview_dpi', SettingController::DEFAULT_SETTINGS);
        $this->assertArrayHasKey('compression_level', SettingController::DEFAULT_SETTINGS);
        $this->assertArrayHasKey('auto_cleanup', SettingController::DEFAULT_SETTINGS);
        $this->assertEquals('light', SettingController::DEFAULT_SETTINGS['theme']);
    }

    public function testHistoryControllerDatabaseIntegration(): void
    {
        // Thêm dữ liệu mẫu vào outputs
        $manifest = json_encode([
            ['page' => 1, 'source_file' => 'doc1.pdf', 'source_page' => 1]
        ]);
        $stmt = $this->pdo->prepare("
            INSERT INTO outputs (user_id, filename, file_size, total_pages, source_manifest) 
            VALUES (1, 'export_sample.pdf', 10240, 1, ?)
        ");
        $stmt->execute([$manifest]);
        $insertedId = (int)$this->pdo->lastInsertId();

        $this->assertGreaterThan(0, $insertedId);

        // Kiểm tra tra cứu dữ liệu
        $checkStmt = $this->pdo->prepare("SELECT * FROM outputs WHERE id = ?");
        $checkStmt->execute([$insertedId]);
        $record = $checkStmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($record);
        $this->assertEquals('export_sample.pdf', $record['filename']);
        $this->assertEquals(10240, (int)$record['file_size']);
        $this->assertEquals(1, (int)$record['total_pages']);
    }

    public function testControllersInstantiation(): void
    {
        $validator = new SecurityValidator();
        $storage = new StorageService();
        $processor = new PdfProcessorService($validator, $storage, $this->pdo);

        $historyController = new HistoryController($this->pdo, sys_get_temp_dir());
        $settingController = new SettingController($this->pdo);
        $exportController = new ExportController($processor, $this->pdo, sys_get_temp_dir());

        $this->assertInstanceOf(HistoryController::class, $historyController);
        $this->assertInstanceOf(SettingController::class, $settingController);
        $this->assertInstanceOf(ExportController::class, $exportController);
    }

    public function testSettingControllerLanguageSwitching(): void
    {
        $controller = new SettingController($this->pdo);
        $settingsVi = $controller->getSettings();
        $this->assertArrayHasKey('language', $settingsVi);

        // Giả lập lưu cài đặt sang tiếng Anh 'en'
        $payloadEn = array_merge($settingsVi, ['language' => 'en', 'theme' => 'dark']);
        $saveResult = $controller->saveSettings($payloadEn);

        $this->assertIsArray($saveResult);
        $this->assertEquals('success', $saveResult['status'] ?? '');
        $this->assertEquals('en', $saveResult['settings']['language'] ?? '');
        $this->assertEquals('dark', $saveResult['settings']['theme'] ?? '');

        // Lấy lại cài đặt đã lưu
        $updatedSettings = $controller->getSettings();
        $this->assertEquals('en', $updatedSettings['language'] ?? '');
        $this->assertEquals('dark', $updatedSettings['theme'] ?? '');

        // Trả lại cài đặt tiếng Việt
        $payloadVi = array_merge($updatedSettings, ['language' => 'vi', 'theme' => 'light']);
        $controller->saveSettings($payloadVi);
        $this->assertEquals('vi', $controller->getSettings()['language'] ?? '');
    }
}
