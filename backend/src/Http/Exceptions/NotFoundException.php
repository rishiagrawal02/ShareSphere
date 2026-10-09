<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Resource not found', string $errorCode = 'NOT_FOUND')
    {
        parent::__construct($message, $errorCode, 404);
    }
}
