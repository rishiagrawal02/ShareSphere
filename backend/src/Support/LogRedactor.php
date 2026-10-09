<?php

declare(strict_types=1);

namespace App\Support;

use Monolog\LogRecord;

class LogRedactor
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'new_password_confirmation',
        'otp',
        'token',
        'csrf',
        'csrf_token',
        'authorization',
        'cookie',
        'secret',
        'app_key',
        'otp_hmac',
        'db_pass',
        'db_owner_pass',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->redactArray($record->context);
        $extra = $this->redactArray($record->extra);

        return $record->with(context: $context, extra: $extra);
    }

    public function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redactArray($value);
            }
        }
        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '_'], '', $key));
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            $normalizedSensitive = strtolower(str_replace(['-', '_'], '', $sensitive));
            if ($normalized === $normalizedSensitive || str_contains($normalized, $normalizedSensitive)) {
                return true;
            }
        }
        return false;
    }
}
