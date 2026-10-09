<?php

declare(strict_types=1);

use App\Controllers\HealthController;
use App\Http\Router;

/**
 * ShareSphere Route Definitions
 * @param Router $router
 */
return function (Router $router): void {
    // Health & System
    $router->get('/api/health', [HealthController::class, 'check']);
};
