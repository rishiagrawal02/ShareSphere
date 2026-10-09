<?php

declare(strict_types=1);

// Prevent PHP server signature leaking
if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
}
@ini_set('expose_php', '0');

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\ErrorHandler;
use App\Http\Request;
use App\Http\Router;
use App\Support\Config;

// Load environment configuration
$rootPath = dirname(__DIR__, 2);
try {
    Config::load($rootPath);
} catch (\Throwable $e) {
    error_log('Config load error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'CONFIG_ERROR',
            'message' => 'Configuration error occurred'
        ]
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// Handle preflight OPTIONS request
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    $allowedOrigin = Config::get('CORS_ALLOWED_ORIGIN', 'http://localhost:5173');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === $allowedOrigin || $allowedOrigin === '*') {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token, X-Request-ID');
    }
    http_response_code(204);
    exit;
}

$request = null;
try {
    $request = Request::fromGlobals();

    $router = new Router();
    $registerRoutes = require __DIR__ . '/../src/routes.php';
    $registerRoutes($router);

    $response = $router->dispatch($request);
    $response->send();
} catch (\Throwable $e) {
    $response = ErrorHandler::handle($e, $request);
    $response->send();
}
