<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

class PayloadTooLargeException extends HttpException
{
    public function __construct(string $message = 'Payload too large', string $errorCode = 'PAYLOAD_TOO_LARGE')
    {
        parent::__construct($message, $errorCode, 413);
    }
}
