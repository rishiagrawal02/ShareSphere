<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

class UnauthorizedException extends HttpException
{
    public function __construct(string $message = 'Unauthenticated', string $errorCode = 'UNAUTHENTICATED')
    {
        parent::__construct($message, $errorCode, 401);
    }
}
