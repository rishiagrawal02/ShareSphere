<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Exceptions\ServiceUnavailableException;
use App\Support\Database;

class HealthController
{
    public function check(Request $request): Response
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query('SELECT 1');
            $dbOk = $stmt !== false;

            return Response::success([
                'status' => 'ok',
                'db' => $dbOk,
                'time' => gmdate('Y-m-d\TH:i:s\Z'),
            ]);
        } catch (\Throwable $e) {
            error_log('Health check failed: ' . $e->getMessage());
            throw new ServiceUnavailableException('Database connection unavailable');
        }
    }
}
