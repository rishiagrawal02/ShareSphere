<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Support\Config;

class SmokeTest extends TestCase
{
    public function testEnvironmentAndFrameworkSanity(): void
    {
        $this->assertTrue(true, 'Basic test runner assertion passes');
    }

    public function testConfigGetterWithFallback(): void
    {
        $val = Config::get('NON_EXISTENT_KEY_FOR_TEST', 'default_val');
        $this->assertSame('default_val', $val);
    }
}
