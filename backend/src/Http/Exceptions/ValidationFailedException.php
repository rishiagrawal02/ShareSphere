<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

class ValidationFailedException extends HttpException
{
    public function __construct(array $fields = [], string $message = 'Validation failed')
    {
        parent::__construct($message, 'VALIDATION_FAILED', 422, $fields);
    }
}
