<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Exceptions\HttpException;
use Throwable;

class ErrorHandler
{
    public static function handle(Throwable $e, ?Request $request = null): Response
    {
        $requestId = $request?->getRequestId() ?? ('req_' . bin2hex(random_bytes(8)));

        if ($e instanceof HttpException) {
            return Response::error(
                $e->getErrorCode(),
                $e->getMessage(),
                $e->getFields(),
                $e->getStatusCode(),
                $requestId,
                $e->getHeaders()
            );
        }

        // Log uncaught internal server exceptions with full stack trace
        error_log(sprintf(
            "[%s] Uncaught Exception: %s in %s:%d\nStack trace:\n%s",
            $requestId,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        // Mask internal exception details to client
        return Response::error(
            'INTERNAL_ERROR',
            'An unexpected internal server error occurred.',
            [],
            500,
            $requestId
        );
    }
}
