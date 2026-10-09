<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Exceptions\BadRequestException;
use App\Http\Exceptions\PayloadTooLargeException;
use App\Http\Exceptions\UnsupportedMediaTypeException;

class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $headers;
    private array $cookies;
    private array $files;
    private array $body;
    private string $rawBody;
    private array $attributes = [];
    private string $ip;
    private string $requestId;

    public function __construct(
        string $method,
        string $path,
        array $query = [],
        array $headers = [],
        array $cookies = [],
        array $files = [],
        string $rawBody = '',
        array $body = [],
        string $ip = '127.0.0.1'
    ) {
        $this->method = strtoupper($method);
        $this->path = '/' . trim($path, '/');
        if ($this->path === '//') {
            $this->path = '/';
        }
        $this->query = $query;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->cookies = $cookies;
        $this->files = $files;
        $this->rawBody = $rawBody;
        $this->body = $body;
        $this->ip = $ip;
        $this->requestId = $this->getHeader('x-request-id') ?? ('req_' . bin2hex(random_bytes(8)));
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headerName = str_replace('_', '-', strtolower($key));
                $headers[$headerName] = $value;
            }
        }

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        $rawBody = (string) file_get_contents('php://input');

        // Body size check (Max 1MB for JSON payloads)
        $contentLength = (int) ($headers['content-length'] ?? strlen($rawBody));
        if ($contentLength > 1048576) {
            throw new PayloadTooLargeException('Request body exceeds 1 MB limit');
        }

        $contentType = $headers['content-type'] ?? '';
        $body = [];

        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if (str_starts_with($contentType, 'application/json')) {
                if ($rawBody !== '') {
                    $decoded = json_decode($rawBody, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new BadRequestException('Malformed JSON in request body');
                    }
                    $body = is_array($decoded) ? $decoded : [];
                }
            } elseif (str_starts_with($contentType, 'multipart/form-data') || str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
                $body = $_POST;
            } elseif ($rawBody !== '' && !empty($contentType)) {
                throw new UnsupportedMediaTypeException('Expected application/json or multipart/form-data');
            }
        }

        return new self($method, $path, $_GET, $headers, $_COOKIE, $_FILES, $rawBody, $body, $ip);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getCookie(string $name, ?string $default = null): ?string
    {
        return $this->cookies[$name] ?? $default;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function setRequestId(string $id): void
    {
        $this->requestId = $id;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
