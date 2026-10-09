<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

class RequestIdMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $headerId = $request->getHeader('x-request-id');
        $requestId = (!empty($headerId) && preg_match('/^[a-zA-Z0-9_-]{8,64}$/', $headerId))
            ? $headerId
            : ('req_' . bin2hex(random_bytes(8)));

        $request->setRequestId($requestId);

        /** @var Response $response */
        $response = $next($request);
        $response->setHeader('X-Request-Id', $requestId);

        return $response;
    }
}
