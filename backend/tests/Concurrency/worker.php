<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Http\Exceptions\ConflictException;
use App\Services\AllocationService;
use App\Support\Config;
use App\Support\Database;

Config::load(dirname(__DIR__, 2));

$_ENV['DB_NAME'] = getenv('DB_NAME') ?: 'sharesphere_test';
$_ENV['DB_PORT'] = getenv('DB_PORT') ?: '5433';
$_SERVER['DB_NAME'] = $_ENV['DB_NAME'];
$_SERVER['DB_PORT'] = $_ENV['DB_PORT'];
Database::resetInstance();

$action = $argv[1] ?? 'create';
$userId = (int) ($argv[2] ?? 0);
$donationId = (int) ($argv[3] ?? 0);
$requirementId = isset($argv[4]) && $argv[4] !== '' && $argv[4] !== 'null' ? (int) $argv[4] : null;
$qty = (int) ($argv[5] ?? 0);
$requestId = (int) ($argv[6] ?? 0);

$pdo = Database::getConnection();
$service = new AllocationService($pdo);

try {
    if ($action === 'create') {
        $data = [
            'donation_id'        => $donationId,
            'requirement_id'     => $requirementId,
            'requested_quantity' => $qty,
        ];
        $res = $service->createRequest($userId, $data);
        echo "SUCCESS:" . $res['id'] . "\n";
    } elseif ($action === 'accept') {
        $res = $service->acceptRequest($userId, $requestId);
        echo "SUCCESS:" . $res['status'] . "\n";
    } elseif ($action === 'reject') {
        $res = $service->rejectRequest($userId, $requestId);
        echo "SUCCESS:" . $res['status'] . "\n";
    } elseif ($action === 'cancel') {
        $res = $service->cancelRequest($userId, $requestId);
        echo "SUCCESS:" . $res['status'] . "\n";
    }
} catch (ConflictException $e) {
    echo "CONFLICT:" . $e->getErrorCode() . "\n";
} catch (Throwable $e) {
    echo "ERROR:" . $e->getMessage() . "\n";
}
