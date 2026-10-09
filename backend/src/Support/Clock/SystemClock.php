<?php

declare(strict_types=1);

namespace App\Support\Clock;

use DateTimeImmutable;
use DateTimeZone;

class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function timestamp(): int
    {
        return time();
    }
}
