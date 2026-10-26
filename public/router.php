<?php
// Router script for PHP built-in server (php -S)

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$filePath = __DIR__ . $uri;

// If the requested resource is an existing file (CSS, JS, images, downloads, etc.), serve it directly
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false;
}

// Otherwise route all page views and API requests to index.php
require_once __DIR__ . '/index.php';
