<?php

declare(strict_types=1);

namespace App\Support\Clock;

use DateTimeImmutable;
use DateTimeZone;

class FrozenClock implements ClockInterface
{
    private DateTimeImmutable $time;

    public function __construct(string|DateTimeImmutable $time = 'now')
    {
        if (is_string($time)) {
            $this->time = new DateTimeImmutable($time, new DateTimeZone('UTC'));
        } else {
            $this->time = $time;
        }
    }

    public function setTo(string|DateTimeImmutable $time): void
    {
        if (is_string($time)) {
            $this->time = new DateTimeImmutable($time, new DateTimeZone('UTC'));
        } else {
            $this->time = $time;
        }
    }

    public function advance(string $interval): void
    {
        $this->time = $this->time->modify($interval);
    }

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }

    public function timestamp(): int
    {
        return $this->time->getTimestamp();
    }
}
