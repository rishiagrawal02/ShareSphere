<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

class ConflictException extends HttpException
{
    public function __construct(string $message = 'Conflict occurred', string $errorCode = 'CONFLICT', array $fields = [])
    {
        parent::__construct($message, $errorCode, 409, $fields);
    }
}
