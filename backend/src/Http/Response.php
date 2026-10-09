<?php

declare(strict_types=1);

namespace App\Http;

class Response
{
    private int $statusCode;
    private array $headers;
    private string $content;

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public static function json(array $data, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json; charset=UTF-8';
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate';
        
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return new self($json !== false ? $json : '{}', $statusCode, $headers);
    }

    public static function success(mixed $data = null, array $meta = [], int $statusCode = 200, array $headers = []): self
    {
        $payload = ['success' => true];
        if ($data !== null) {
            $payload['data'] = $data;
        }
        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }
        return self::json($payload, $statusCode, $headers);
    }

    public static function error(
        string $code,
        string $message,
        array $fields = [],
        int $statusCode = 400,
        ?string $requestId = null,
        array $headers = []
    ): self {
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        if (!empty($fields)) {
            $error['fields'] = $fields;
        }

        $payload = [
            'success' => false,
            'error' => $error,
        ];

        if ($requestId !== null) {
            $payload['request_id'] = $requestId;
        }

        return self::json($payload, $statusCode, $headers);
    }

    public static function noContent(array $headers = []): self
    {
        return new self('', 204, $headers);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        $lookup = strtolower($name);
        foreach ($this->headers as $k => $v) {
            if (strtolower((string) $k) === $lookup) {
                return (string) $v;
            }
        }
        return $default;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        echo $this->content;
    }
}
