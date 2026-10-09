<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

use Exception;
use Throwable;

abstract class HttpException extends Exception
{
    protected int $statusCode;
    protected string $errorCode;
    protected array $fields = [];
    protected array $headers = [];

    public function __construct(
        string $message = '',
        string $errorCode = 'INTERNAL_ERROR',
        int $statusCode = 500,
        array $fields = [],
        array $headers = [],
        ?Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->fields = $fields;
        $this->headers = $headers;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
