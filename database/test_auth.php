<?php
require __DIR__ . '/../vendor/autoload.php';

$pdo = new PDO('sqlite:' . __DIR__ . '/../database/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$auth = new App\Controllers\AuthController($pdo);

echo "Testing Register:" . PHP_EOL;
$auth->register([
    'full_name' => 'Le Van Test',
    'username' => 'sinhvien_test_' . time(),
    'email' => 'test_' . time() . '@dnu.edu.vn',
    'role' => 'student',
    'password' => 'secret123'
]);
echo PHP_EOL;
