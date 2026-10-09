<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Forbidden', string $errorCode = 'FORBIDDEN')
    {
        parent::__construct($message, $errorCode, 403);
    }
}
