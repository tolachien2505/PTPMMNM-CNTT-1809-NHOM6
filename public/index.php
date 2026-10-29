<?php
/**
 * PDF Manager - Front Controller (Laravel Standard Architecture)
 * Đề tài 12 - Học phần Phát triển phần mềm mã nguồn mở
 * Trường Đại học Đại Nam
 * 
 * Nhóm thực hiện:
 * - Đỗ Tiến Sơn (1871020508)
 * - Đỗ Anh Duy (1871020190)
 * - Vũ Văn Chiến (1871020088)
 */

define('LARAVEL_START', microtime(true));

// 1. Nạp Autoloader từ Composer
require __DIR__ . '/../vendor/autoload.php';

// 2. Khởi tạo Bootstrap & Kernel
require __DIR__ . '/../bootstrap/app.php';

// 3. Nạp Định tuyến (Routes)
require __DIR__ . '/../routes/api.php';
require __DIR__ . '/../routes/web.php';

// 4. Thực thi điều phối yêu cầu (Dispatch)
Route::dispatch();
