<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

class ServiceUnavailableException extends HttpException
{
    public function __construct(string $message = 'Service temporarily unavailable', string $errorCode = 'SERVICE_UNAVAILABLE')
    {
        parent::__construct($message, $errorCode, 503);
    }
}
