<?php
/**
 * Migration: Thêm trường password_hash cho bảng users (SQLite & MySQL)
 */

echo "=== MIGRATION AUTH USERS ===" . PHP_EOL;

// 1. SQLite
try {
    $sqlitePath = __DIR__ . '/database.sqlite';
    if (file_exists($sqlitePath)) {
        $sqlite = new PDO("sqlite:" . $sqlitePath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $cols = $sqlite->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('password_hash', $cols)) {
            $sqlite->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL");
            echo "[✓] Đã thêm cột password_hash vào SQLite" . PHP_EOL;
        }

        $defaultHash = password_hash('123456', PASSWORD_BCRYPT);
        $stmt = $sqlite->prepare("UPDATE users SET password_hash = ? WHERE password_hash IS NULL OR password_hash = ''");
        $stmt->execute([$defaultHash]);
        echo "[✓] Đã cập nhật mật khẩu mặc định (123456) cho các tài khoản SQLite" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "[!] Lỗi SQLite: " . $e->getMessage() . PHP_EOL;
}

// 2. MySQL
try {
    $mysql = new PDO("mysql:host=127.0.0.1;port=3306;dbname=pdf_manager;charset=utf8mb4", "root", "");
    $mysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $cols = $mysql->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('password_hash', $cols)) {
        $mysql->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");
        echo "[✓] Đã thêm cột password_hash vào MySQL pdf_manager" . PHP_EOL;
    }

    $defaultHash = password_hash('123456', PASSWORD_BCRYPT);
    $stmt = $mysql->prepare("UPDATE users SET password_hash = ? WHERE password_hash IS NULL OR password_hash = ''");
    $stmt->execute([$defaultHash]);
    echo "[✓] Đã cập nhật mật khẩu mặc định (123456) cho các tài khoản MySQL" . PHP_EOL;
} catch (Exception $e) {
    echo "[!] Lỗi MySQL: " . $e->getMessage() . PHP_EOL;
}

echo "=== HOÀN TẤT MIGRATION ===" . PHP_EOL;
