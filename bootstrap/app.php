<?php
/**
 * Application Bootstrap & Kernel (Laravel Architecture)
 * Quản lý khởi tạo hệ thống, nạp môi trường, kết nối CSDL và Router Engine
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// 1. Nạp .env cấu hình
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

// 2. Database Connection Manager (3-tier Resilient Fallback)
class Database
{
    private static ?PDO $instance = null;

    public static function getPdo(): ?PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $config = require dirname(__DIR__) . '/config/database.php';
        $my = $config['connections']['mysql'];

        // Cố gắng 1: Kết nối Remote MySQL theo .env (103.200.23.68 / ctoolnet_pdfonline123)
        try {
            self::$instance = new PDO(
                "mysql:host={$my['host']};port={$my['port']};dbname={$my['database']};charset={$my['charset']}",
                $my['username'],
                $my['password'],
                $my['options']
            );
            return self::$instance;
        } catch (Exception $e) {
            // Cố gắng 2: Fallback MySQL Local nếu server remote mất mạng tạm thời
            try {
                $loc = $config['connections']['mysql_local'];
                self::$instance = new PDO(
                    "mysql:host={$loc['host']};port={$loc['port']};dbname={$loc['database']};charset={$loc['charset']}",
                    $loc['username'],
                    $loc['password'],
                    $loc['options']
                );
                return self::$instance;
            } catch (Exception $locEx) {
                // Cố gắng 3: Fallback CSDL SQLite cục bộ
                $sqPath = $config['connections']['sqlite']['database'];
                if (file_exists($sqPath)) {
                    try {
                        self::$instance = new PDO("sqlite:" . $sqPath);
                        self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        return self::$instance;
                    } catch (Exception $sqEx) {}
                }
            }
        }

        return self::$instance;
    }
}

// 3. User & Auth Context
function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $email = $_SESSION['user_email'] ?? '';
    $isAdmin = (
        (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) ||
        (($_SESSION['role'] ?? '') === 'admin') ||
        (strtolower($email) === 'vvchien25@gmail.com')
    );

    if ($isAdmin) {
        $_SESSION['is_admin'] = true;
        $_SESSION['role'] = 'admin';
        $_SESSION['user_role'] = 'Quản trị viên';
    }

    return [
        'id' => (int)$_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'Người dùng',
        'email' => $email,
        'username' => $_SESSION['username'] ?? '',
        'role' => $isAdmin ? 'admin' : 'user',
        'role_label' => $isAdmin ? 'Quản trị viên' : 'Người dùng',
        'is_admin' => $isAdmin,
        'plan' => $_SESSION['user_plan'] ?? 'FREE'
    ];
}

// 4. View Format Helpers
if (!function_exists('formatDocStatus')) {
    function formatDocStatus(?string $status): array {
        $st = strtolower(trim($status ?? 'uploaded'));
        if (in_array($st, ['error', 'corrupted', 'oversize', 'failed'])) {
            return ['class' => 'danger', 'text' => 'Lỗi tệp', 'code' => 'error'];
        }
        if (in_array($st, ['editing', 'draft', 'processing'])) {
            return ['class' => 'warning', 'text' => 'Đang chỉnh sửa', 'code' => 'editing'];
        }
        if (in_array($st, ['exported', 'completed', 'success'])) {
            return ['class' => 'success', 'text' => 'Đã xuất file', 'code' => 'exported'];
        }
        return ['class' => 'primary', 'text' => 'Sẵn sàng', 'code' => 'uploaded'];
    }
}

if (!function_exists('formatDocSize')) {
    function formatDocSize(int $bytes): string {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}

if (!function_exists('formatDocDate')) {
    function formatDocDate(?string $datetime): string {
        if (!$datetime) return date('d/m/Y H:i');
        return date('d/m/Y H:i', strtotime($datetime));
    }
}

// 5. View Rendering Helper
function view(string $viewName, array $data = []): void
{
    extract($data);
    $basePath = dirname(__DIR__) . '/resources/views/';
    $filePath = $basePath . str_replace('.', '/', $viewName) . '.php';
    if (file_exists($filePath)) {
        require $filePath;
    } else {
        http_response_code(404);
        echo "View [{$viewName}] không tồn tại tại {$filePath}";
    }
    exit;
}

// 5. Response Helper Class
class Response
{
    public function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public function download(string $filePath, ?string $filename = null): void
    {
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo "Tệp không tồn tại.";
            exit;
        }
        $filename = $filename ?: basename($filePath);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

function response(): Response
{
    return new Response();
}

// 6. Router Engine (Laravel Route::get, Route::post syntax)
class Route
{
    private static array $routes = [];

    public static function get(string $uri, $action): void
    {
        self::addRoute('GET', $uri, $action);
    }

    public static function post(string $uri, $action): void
    {
        self::addRoute('POST', $uri, $action);
    }

    public static function any(string $uri, $action): void
    {
        self::addRoute('ANY', $uri, $action);
    }

    private static function addRoute(string $method, string $uri, $action): void
    {
        $uri = '/' . trim($uri, '/');
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }
        self::$routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action
        ];
    }

    public static function dispatch(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $requestUri = '/' . trim($requestUri, '/');
        if ($requestUri !== '/') {
            $requestUri = rtrim($requestUri, '/');
        }

        foreach (self::$routes as $route) {
            $methodMatch = ($route['method'] === 'ANY' || $route['method'] === $requestMethod);
            if (!$methodMatch) continue;

            $routeUri = $route['uri'];
            if ($routeUri === $requestUri) {
                self::executeAction($route['action']);
                return;
            }
        }

        // If not matched, default to home view or 404
        if (str_starts_with($requestUri, '/api/')) {
            response()->json(['status' => 'error', 'message' => "Endpoint API không tồn tại: {$requestUri}"], 404);
        } else {
            // Client side SPA fallback
            view('layouts.app');
        }
    }

    private static function executeAction($action): void
    {
        if (is_callable($action)) {
            call_user_func($action);
            return;
        }

        if (is_array($action)) {
            [$class, $method] = $action;
            $pdo = Database::getPdo();
            $controller = new $class($pdo);
            $controller->$method();
            return;
        }

        if (is_string($action) && str_contains($action, '@')) {
            [$class, $method] = explode('@', $action);
            $pdo = Database::getPdo();
            $controller = new $class($pdo);
            $controller->$method();
            return;
        }
    }
}
