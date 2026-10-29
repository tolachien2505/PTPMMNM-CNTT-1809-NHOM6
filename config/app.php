<?php

return [
    'name' => 'PDF Manager',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => (bool)(getenv('APP_DEBUG') ?: false),
    'url' => getenv('APP_URL') ?: 'http://localhost:8000',
    'timezone' => 'Asia/Ho_Chi_Minh',
    'locale' => 'vi',
];
