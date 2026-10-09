<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

class UnsupportedMediaTypeException extends HttpException
{
    public function __construct(string $message = 'Unsupported Media Type', string $errorCode = 'UNSUPPORTED_MEDIA_TYPE')
    {
        parent::__construct($message, $errorCode, 415);
    }
}
