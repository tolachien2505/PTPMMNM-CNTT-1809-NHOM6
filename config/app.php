<?php

return [
    'app_name' => getenv('APP_NAME') ?: 'PDF Manager',
    'app_env' => getenv('APP_ENV') ?: 'development',
    'app_debug' => filter_var(getenv('APP_DEBUG') ?: true, FILTER_VALIDATE_BOOLEAN),
    'app_url' => getenv('APP_URL') ?: 'http://localhost:8000',

    // Giới hạn hệ thống (Rubric: an toàn, giới hạn tệp và thời gian)
    'max_file_size_bytes' => (int)(getenv('MAX_FILE_SIZE_MB') ?: 25) * 1024 * 1024,
    'max_execution_time_seconds' => (int)(getenv('MAX_EXECUTION_TIME') ?: 60),
    'allowed_extensions' => ['pdf'],
    'allowed_mime_types' => ['application/pdf'],

    // Giới hạn số trang tối đa trong 1 phiên làm việc
    'max_total_pages' => 200,

    // Cấu hình đường dẫn lưu trữ
    'paths' => [
        'storage_documents' => __DIR__ . '/../storage/documents',
        'storage_exports'   => __DIR__ . '/../storage/exports',
        'storage_previews'  => __DIR__ . '/../storage/previews',
        'storage_logs'      => __DIR__ . '/../storage/logs',
        'temp_upload'       => __DIR__ . '/../public/uploads',
    ],
];
