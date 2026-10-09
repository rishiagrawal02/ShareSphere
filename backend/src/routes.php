<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HealthController;
use App\Http\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

/**
 * ShareSphere Route Definitions
 * @param Router $router
 */
return function (Router $router): void {
    // ─── Health & System ────────────────────────────────────────────────────
    $router->get('/api/health', [HealthController::class, 'check']);

    // ─── Auth ────────────────────────────────────────────────────────────────
    // CSRF token endpoint — safe read, no CSRF check needed on this one
    $router->get('/api/auth/csrf-token', [AuthController::class, 'csrfToken']);

    // Public auth mutations (CSRF-protected)
    $router->post('/api/auth/register', [AuthController::class, 'register'], [CsrfMiddleware::class]);
    $router->post('/api/auth/login',    [AuthController::class, 'login'],    [CsrfMiddleware::class]);
    $router->post('/api/auth/logout',   [AuthController::class, 'logout'],   [AuthMiddleware::class, CsrfMiddleware::class]);

    // Authenticated user profile
    $router->get('/api/auth/me', [AuthController::class, 'me'], [AuthMiddleware::class]);

    // ─── Notifications ───────────────────────────────────────────────────────
    $router->get('/api/notifications', [App\Controllers\NotificationController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/api/notifications/{id}/read', [App\Controllers\NotificationController::class, 'markRead'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/api/notifications/read-all', [App\Controllers\NotificationController::class, 'markAllRead'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // ─── Profile & Documents ──────────────────────────────────────────────────
    $router->get('/api/profile', [App\Controllers\ProfileController::class, 'show'], [AuthMiddleware::class]);
    $router->patch('/api/profile', [App\Controllers\ProfileController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/api/profile/ngo-documents', [App\Controllers\ProfileController::class, 'listDocuments'], [AuthMiddleware::class]);
    $router->post('/api/profile/ngo-documents', [App\Controllers\ProfileController::class, 'uploadDocument'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->delete('/api/profile/ngo-documents/{id}', [App\Controllers\ProfileController::class, 'deleteDocument'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/api/profile/ngo-resubmit', [App\Controllers\ProfileController::class, 'resubmit'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // ─── Media Gateway ────────────────────────────────────────────────────────
    $router->get('/api/media/{kind}/{id}', [App\Controllers\MediaController::class, 'stream'], [AuthMiddleware::class]);

    // ─── Categories ───────────────────────────────────────────────────────────
    $router->get('/api/categories', [App\Controllers\CategoryController::class, 'index']);

    // Admin Categories
    $router->get('/api/admin/categories', [App\Controllers\AdminCategoryController::class, 'index'], [AuthMiddleware::class, new \App\Middleware\RoleMiddleware('admin')]);
    $router->post('/api/admin/categories', [App\Controllers\AdminCategoryController::class, 'store'], [AuthMiddleware::class, new \App\Middleware\RoleMiddleware('admin'), CsrfMiddleware::class]);
    $router->patch('/api/admin/categories/{id}', [App\Controllers\AdminCategoryController::class, 'update'], [AuthMiddleware::class, new \App\Middleware\RoleMiddleware('admin'), CsrfMiddleware::class]);
    $router->put('/api/admin/categories/{id}/compatibility', [App\Controllers\AdminCategoryController::class, 'setCompatibility'], [AuthMiddleware::class, new \App\Middleware\RoleMiddleware('admin'), CsrfMiddleware::class]);

    // ─── Admin NGO Verification ───────────────────────────────────────────────
    $router->get('/api/admin/ngos', [App\Controllers\AdminNgoController::class, 'index'], [AuthMiddleware::class, new \App\Middleware\RoleMiddleware('admin')]);
    $router->get('/api/admin/ngos/{id}', [App\Controllers\AdminNgoController::class, 'show'], [AuthMiddleware::class, new \App\Middleware\RoleMiddleware('admin')]);
    $router->post('/api/admin/ngos/{id}/verify', [App\Controllers\AdminNgoController::class, 'verify'], [AuthMiddleware::class, new \App\Middleware\RoleMiddleware('admin'), CsrfMiddleware::class]);

    // ─── Donations ────────────────────────────────────────────────────────────
    $router->post('/api/donations', [App\Controllers\DonationController::class, 'store'], [AuthMiddleware::class, new \App\Middleware\RoleMiddleware('donor'), CsrfMiddleware::class]);
    $router->get('/api/donations', [App\Controllers\DonationController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/api/donations/{id}', [App\Controllers\DonationController::class, 'show'], [AuthMiddleware::class]);
    $router->patch('/api/donations/{id}', [App\Controllers\DonationController::class, 'update'], [AuthMiddleware::class, new \App\Middleware\RoleMiddleware('donor'), CsrfMiddleware::class]);
    $router->delete('/api/donations/{id}', [App\Controllers\DonationController::class, 'destroy'], [AuthMiddleware::class, new \App\Middleware\RoleMiddleware('donor'), CsrfMiddleware::class]);
    $router->post('/api/donations/{id}/images', [App\Controllers\DonationController::class, 'uploadImages'], [AuthMiddleware::class, new \App\Middleware\RoleMiddleware('donor'), CsrfMiddleware::class]);
    $router->delete('/api/donations/{id}/images/{imageId}', [App\Controllers\DonationController::class, 'deleteImage'], [AuthMiddleware::class, new \App\Middleware\RoleMiddleware('donor'), CsrfMiddleware::class]);

    // Admin Donation Moderation
    $router->post('/api/admin/donations/{id}/moderate', [App\Controllers\AdminDonationController::class, 'moderate'], [AuthMiddleware::class, new \App\Middleware\RoleMiddleware('admin'), CsrfMiddleware::class]);
};
