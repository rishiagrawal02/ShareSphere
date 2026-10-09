<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

class TooManyRequestsException extends HttpException
{
    public function __construct(string $message = 'Too Many Requests', string $errorCode = 'RATE_LIMITED', int $retryAfterSeconds = 60)
    {
        parent::__construct($message, $errorCode, 429, [], ['Retry-After' => (string)$retryAfterSeconds]);
    }
}
