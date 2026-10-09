<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

class DonationStatus
{
    public const DRAFT               = 'draft';
    public const ACTIVE              = 'active';
    public const PARTIALLY_ALLOCATED = 'partially_allocated';
    public const FULLY_ALLOCATED     = 'fully_allocated';
    public const COMPLETED           = 'completed';
    public const CLOSED              = 'closed';
    public const REMOVED             = 'removed';

    /**
     * Pure function to derive canonical donation status from quantity balances.
     */
    public static function derive(int $totalQuantity, int $availableQuantity, bool $hasOpenAllocations = false): string
    {
        if ($totalQuantity <= 0) {
            throw new InvalidArgumentException('Total quantity must be greater than zero');
        }

        if ($availableQuantity < 0) {
            throw new InvalidArgumentException('Available quantity cannot be negative');
        }

        if ($availableQuantity > $totalQuantity) {
            throw new InvalidArgumentException('Available quantity cannot exceed total quantity');
        }

        if ($availableQuantity === 0) {
            return self::FULLY_ALLOCATED;
        }

        if ($availableQuantity === $totalQuantity) {
            return self::ACTIVE;
        }

        return self::PARTIALLY_ALLOCATED;
    }
}
