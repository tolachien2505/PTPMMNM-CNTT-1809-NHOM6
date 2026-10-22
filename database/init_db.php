<?php
/**
 * Script khởi tạo SQLite Database từ schema.sql và seed.sql
 */

$dbPath = __DIR__ . '/database.sqlite';
$schemaFile = __DIR__ . '/schema.sql';
$seedFile = __DIR__ . '/seed.sql';

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Đang tạo bảng từ schema.sql..." . PHP_EOL;
    $schemaSql = file_get_contents($schemaFile);
    $pdo->exec($schemaSql);

    // Kiểm tra xem đã có dữ liệu chưa
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();

    if ($userCount == 0 && file_exists($seedFile)) {
        echo "Đang nạp dữ liệu mẫu từ seed.sql..." . PHP_EOL;
        $seedSql = file_get_contents($seedFile);
        $pdo->exec($seedSql);
    }

    echo "=== KHỞI TẠO CƠ SỞ DỮ LIỆU SQLITE THÀNH CÔNG ===" . PHP_EOL;
    echo "Đường dẫn file CSDL: " . $dbPath . PHP_EOL;
} catch (Exception $e) {
    echo "Lỗi khởi tạo CSDL: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
