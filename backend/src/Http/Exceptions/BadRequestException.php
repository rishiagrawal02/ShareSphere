<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

class BadRequestException extends HttpException
{
    public function __construct(string $message = 'Bad Request', string $errorCode = 'BAD_REQUEST', array $fields = [])
    {
        parent::__construct($message, $errorCode, 400, $fields);
    }
}
