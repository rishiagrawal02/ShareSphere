<?php

declare(strict_types=1);

/**
 * ShareSphere Route Access Policy Matrix (Phase 18 Milestone 18.1)
 *
 * Defines the expected access decision for each registered route against the 6 standard user personas:
 *   - anonymous: no session
 *   - donor: active donor
 *   - ngo-pending: active NGO, verification_status = 'pending'
 *   - ngo-verified: active NGO, verification_status = 'verified'
 *   - admin: active administrator
 *   - suspended: user with account_status = 'suspended'
 *
 * Values:
 *   - 'ALLOW': Request passes authentication & authorization guards
 *   - 401: Blocked by AuthMiddleware (UNAUTHENTICATED or ACCOUNT_SUSPENDED)
 *   - 403: Blocked by RBAC / NGO verification guard (ForbiddenException)
 *
 * Rule: Any route present in src/routes.php MUST have an entry here; if a route is added
 * without an entry, RoleRouteMatrixTest fails the build.
 */
return [
    // ─── Health & System ────────────────────────────────────────────────────
    'GET /api/health' => [
        'anonymous' => 'ALLOW', 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 'ALLOW',
    ],

    // ─── Auth ────────────────────────────────────────────────────────────────
    'GET /api/auth/csrf-token' => [
        'anonymous' => 'ALLOW', 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 'ALLOW',
    ],
    'POST /api/auth/register' => [
        'anonymous' => 'ALLOW', 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 'ALLOW',
    ],
    'POST /api/auth/login' => [
        'anonymous' => 'ALLOW', 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 'ALLOW',
    ],
    'POST /api/auth/logout' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/auth/me' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],

    // ─── Notifications ───────────────────────────────────────────────────────
    'GET /api/notifications' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'POST /api/notifications/{id}/read' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'POST /api/notifications/read-all' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],

    // ─── Profile & Documents ──────────────────────────────────────────────────
    'GET /api/profile' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'PATCH /api/profile' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/profile/ngo-documents' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'POST /api/profile/ngo-documents' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'DELETE /api/profile/ngo-documents/{id}' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'POST /api/profile/ngo-resubmit' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],

    // ─── Media Gateway ────────────────────────────────────────────────────────
    'GET /api/media/{kind}/{id}' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],

    // ─── Categories ───────────────────────────────────────────────────────────
    'GET /api/categories' => [
        'anonymous' => 'ALLOW', 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 'ALLOW',
    ],
    'GET /api/admin/categories' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'POST /api/admin/categories' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'PATCH /api/admin/categories/{id}' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'PUT /api/admin/categories/{id}/compatibility' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 'ALLOW', 'suspended' => 401,
    ],

    // ─── Admin NGO Verification ───────────────────────────────────────────────
    'GET /api/admin/ngos' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/admin/ngos/{id}' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'POST /api/admin/ngos/{id}/verify' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 'ALLOW', 'suspended' => 401,
    ],

    // ─── Donations ────────────────────────────────────────────────────────────
    'POST /api/donations' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 403, 'suspended' => 401,
    ],
    'GET /api/donations' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/donations/{id}' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'PATCH /api/donations/{id}' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 403, 'suspended' => 401,
    ],
    'DELETE /api/donations/{id}' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 403, 'suspended' => 401,
    ],
    'POST /api/donations/{id}/images' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 403, 'suspended' => 401,
    ],
    'DELETE /api/donations/{id}/images/{imageId}' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 403, 'suspended' => 401,
    ],

    // ─── Admin Donation Moderation ─────────────────────────────────────────────
    'POST /api/admin/donations/{id}/moderate' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 'ALLOW', 'suspended' => 401,
    ],

    // ─── NGO Requirements ─────────────────────────────────────────────────────
    'POST /api/requirements' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/requirements' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/requirements/{id}' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'PATCH /api/requirements/{id}' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'POST /api/requirements/{id}/close' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'DELETE /api/requirements/{id}' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],

    // ─── Smart Matching Engine ─────────────────────────────────────────────────
    'GET /api/matches' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/map/donations' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],

    // ─── Donation Requests & Allocations ──────────────────────────────────────
    'POST /api/requests' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/requests' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/requests/{id}' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'POST /api/requests/{id}/accept' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 403, 'suspended' => 401,
    ],
    'POST /api/requests/{id}/reject' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 403, 'suspended' => 401,
    ],
    'POST /api/requests/{id}/cancel' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],

    // ─── Pickup, OTP & Handover Completion ────────────────────────────────────
    'POST /api/pickups' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/pickups' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/pickups/{id}' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'POST /api/pickups/{id}/confirm' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'PATCH /api/pickups/{id}' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'POST /api/pickups/{id}/cancel' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'POST /api/pickups/{id}/otp' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'POST /api/pickups/{id}/verify-otp' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 403, 'suspended' => 401,
    ],
    'POST /api/pickups/{id}/confirm-receipt' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/donations/{id}/history' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],

    // ─── Admin Operations & Reports ───────────────────────────────────────────
    'GET /api/admin/users' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/admin/users/{id}' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'PATCH /api/admin/users/{id}/status' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/admin/reports/summary' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/admin/reports/trends' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/admin/reports/categories' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/admin/audit-logs' => [
        'anonymous' => 401, 'donor' => 403, 'ngo-pending' => 403, 'ngo-verified' => 403, 'admin' => 'ALLOW', 'suspended' => 401,
    ],
    'GET /api/dashboard' => [
        'anonymous' => 401, 'donor' => 'ALLOW', 'ngo-pending' => 'ALLOW', 'ngo-verified' => 'ALLOW', 'admin' => 'ALLOW', 'suspended' => 401,
    ],
];
