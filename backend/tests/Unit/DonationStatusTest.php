<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Support\DonationStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DonationStatusTest extends TestCase
{
    public function testDeriveActiveWhenAvailableEqualsTotal(): void
    {
        $status = DonationStatus::derive(20, 20);
        $this->assertSame('active', $status);
    }

    public function testDerivePartiallyAllocated(): void
    {
        $status = DonationStatus::derive(20, 8);
        $this->assertSame('partially_allocated', $status);
    }

    public function testDeriveFullyAllocated(): void
    {
        $status = DonationStatus::derive(20, 0);
        $this->assertSame('fully_allocated', $status);
    }

    public function testDeriveThrowsOnNegativeAvailable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DonationStatus::derive(20, -1);
    }

    public function testDeriveThrowsOnAvailableGreaterThanTotal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DonationStatus::derive(20, 25);
    }

    public function testDeriveThrowsOnZeroTotal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DonationStatus::derive(0, 0);
    }
}
