<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Exceptions\ConflictException;

class PickupStateMachine
{
    public const STATE_PROPOSED   = 'proposed';
    public const STATE_SCHEDULED  = 'scheduled';
    public const STATE_OTP_ISSUED = 'otp_issued';
    public const STATE_COLLECTED  = 'collected';
    public const STATE_COMPLETED  = 'completed';
    public const STATE_CANCELLED  = 'cancelled';

    /**
     * Allowed state transitions map.
     */
    private const TRANSITIONS = [
        self::STATE_PROPOSED => [
            self::STATE_SCHEDULED,
            self::STATE_PROPOSED, // rescheduling while proposed
            self::STATE_CANCELLED,
        ],
        self::STATE_SCHEDULED => [
            self::STATE_OTP_ISSUED,
            self::STATE_PROPOSED,  // rescheduling resets to proposed
            self::STATE_CANCELLED,
        ],
        self::STATE_OTP_ISSUED => [
            self::STATE_OTP_ISSUED, // reissue OTP
            self::STATE_COLLECTED,  // OTP verified
            self::STATE_PROPOSED,   // rescheduling resets to proposed & invalidates OTP
            self::STATE_CANCELLED,
        ],
        self::STATE_COLLECTED => [
            self::STATE_COMPLETED,  // NGO confirms receipt
        ],
        self::STATE_COMPLETED => [], // terminal
        self::STATE_CANCELLED => [], // terminal
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertCanTransition(string $from, string $to): void
    {
        if (!self::canTransition($from, $to)) {
            throw new ConflictException(
                "Cannot transition pickup from '{$from}' to '{$to}'",
                'INVALID_PICKUP_TRANSITION'
            );
        }
    }
}
