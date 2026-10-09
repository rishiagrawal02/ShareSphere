<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Support\LogRedactor;
use PHPUnit\Framework\TestCase;

class LogRedactorTest extends TestCase
{
    private LogRedactor $redactor;

    protected function setUp(): void
    {
        $this->redactor = new LogRedactor();
    }

    public function testRedactionOfSensitiveFields(): void
    {
        $input = [
            'user_id' => 123,
            'email' => 'user@example.com',
            'password' => 'SuperSecret123!',
            'password_confirmation' => 'SuperSecret123!',
            'otp' => '123456',
            'nested' => [
                'token' => 'jwt_token_here',
                'csrf_token' => 'csrf_123',
                'public_data' => 'hello',
            ],
        ];

        $redacted = $this->redactor->redactArray($input);

        $this->assertSame(123, $redacted['user_id']);
        $this->assertSame('user@example.com', $redacted['email']);
        $this->assertSame('[REDACTED]', $redacted['password']);
        $this->assertSame('[REDACTED]', $redacted['password_confirmation']);
        $this->assertSame('[REDACTED]', $redacted['otp']);
        $this->assertSame('[REDACTED]', $redacted['nested']['token']);
        $this->assertSame('[REDACTED]', $redacted['nested']['csrf_token']);
        $this->assertSame('hello', $redacted['nested']['public_data']);
    }
}
