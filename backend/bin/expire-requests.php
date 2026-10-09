<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\AllocationService;
use App\Support\Config;

$rootPath = dirname(__DIR__, 2);
Config::load($rootPath);

$service = new AllocationService();
$count = $service->expirePendingRequests();

echo "Expired {$count} pending request(s) older than 72 hours.\n";
