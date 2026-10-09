<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Exceptions\UnauthorizedException;
use App\Http\Request;
use App\Http\Response;
use App\Support\CsrfGuard;

/**
 * CSRF protection middleware.
 *
 * Safe HTTP methods (GET, HEAD, OPTIONS, TRACE) are unconditionally allowed.
 * All other methods require a valid X-CSRF-Token header (or _csrf_token body field).
 */
class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];

    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return $next($request);
        }

        // Accept token from header (preferred) or body field
        $token = $request->getHeader('x-csrf-token')
            ?? $request->get('_csrf_token', '');

        CsrfGuard::verify((string) $token);

        return $next($request);
    }
}
