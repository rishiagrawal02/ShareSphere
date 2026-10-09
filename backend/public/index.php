<?php

declare(strict_types=1);

// Prevent PHP server signature leaking
if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
}
@ini_set('expose_php', '0');

require_once __DIR__ . '/../vendor/autoload.php';

use App\Support\Config;
use App\Support\Database;

// Handle CORS
$allowedOrigin = $_ENV['CORS_ALLOWED_ORIGIN'] ?? 'http://localhost:5173';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === $allowedOrigin || $allowedOrigin === '*') {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

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

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Basic routing for Milestone 0.3
if ($uri === '/api/health') {
    if ($method !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => 'Method not allowed. Allowed methods: GET'
            ]
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT 1');
        $dbOk = $stmt !== false;

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'status' => 'ok',
                'db' => $dbOk,
                'time' => gmdate('Y-m-d\TH:i:s\Z')
            ]
        ], JSON_UNESCAPED_SLASHES);
    } catch (\Throwable $e) {
        error_log('Database health check failed: ' . $e->getMessage());
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'SERVICE_UNAVAILABLE',
                'message' => 'Database connection unavailable'
            ]
        ], JSON_UNESCAPED_SLASHES);
    }
    exit;
}

// 404 for unknown endpoints
http_response_code(404);
echo json_encode([
    'success' => false,
    'error' => [
        'code' => 'NOT_FOUND',
        'message' => 'Route not found: ' . $uri
    ]
], JSON_UNESCAPED_SLASHES);
