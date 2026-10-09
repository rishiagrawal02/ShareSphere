<?php

declare(strict_types=1);

namespace App\Support;

class PickupResource
{
    /**
     * Format pickup data with role-based location privacy (D-9).
     */
    public static function format(array $pickup, string $role): array
    {
        $state = (string) ($pickup['state'] ?? 'proposed');
        $isDonorOrAdmin = in_array($role, ['donor', 'admin'], true);
        $isNgoConfirmed = in_array($state, ['scheduled', 'otp_issued', 'collected', 'completed'], true);

        $out = [
            'id'               => (int) $pickup['id'],
            'allocation_id'    => (int) $pickup['allocation_id'],
            'proposed_by'      => (int) $pickup['proposed_by'],
            'scheduled_at'     => self::formatIsoDate($pickup['scheduled_at'] ?? null),
            'location_details' => $pickup['location_details'] ?? null,
            'contact_note'     => $pickup['contact_note'] ?? null,
            'state'            => $state,
            'otp_attempts'     => (int) ($pickup['otp_attempts'] ?? 0),
            'otp_locked'       => (bool) ($pickup['otp_locked'] ?? false),
            'otp_issue_count'  => (int) ($pickup['otp_issue_count'] ?? 0),
            'otp_expires_at'   => self::formatIsoDate($pickup['otp_expires_at'] ?? null),
            'collected_at'     => self::formatIsoDate($pickup['collected_at'] ?? null),
            'completed_at'     => self::formatIsoDate($pickup['completed_at'] ?? null),
            'created_at'       => self::formatIsoDate($pickup['created_at'] ?? null),
            'updated_at'       => self::formatIsoDate($pickup['updated_at'] ?? null),
            'donation'         => [
                'id'                 => (int) ($pickup['donation_id'] ?? 0),
                'title'              => $pickup['donation_title'] ?? null,
                'total_quantity'     => isset($pickup['total_quantity']) ? (int) $pickup['total_quantity'] : null,
                'available_quantity' => isset($pickup['available_quantity']) ? (int) $pickup['available_quantity'] : null,
                'status'             => $pickup['donation_status'] ?? null,
                'latitude_public'    => isset($pickup['latitude_public']) ? (float) $pickup['latitude_public'] : null,
                'longitude_public'   => isset($pickup['longitude_public']) ? (float) $pickup['longitude_public'] : null,
            ],
            'allocation'       => [
                'id'                 => (int) ($pickup['allocation_id'] ?? 0),
                'allocated_quantity' => (int) ($pickup['allocated_quantity'] ?? 0),
                'status'             => $pickup['allocation_status'] ?? null,
            ],
            'ngo'              => [
                'id'                => (int) ($pickup['ngo_id'] ?? 0),
                'organization_name' => $pickup['organization_name'] ?? null,
            ],
        ];

        // Privacy rule: Exact location only visible to donor, admin, or verified NGO on scheduled/otp_issued/collected/completed pickup
        if ($isDonorOrAdmin || $isNgoConfirmed) {
            $out['donation']['address_text'] = $pickup['address_text'] ?? null;
            $out['donation']['latitude_exact'] = isset($pickup['latitude_exact']) ? (float) $pickup['latitude_exact'] : null;
            $out['donation']['longitude_exact'] = isset($pickup['longitude_exact']) ? (float) $pickup['longitude_exact'] : null;
        }

        return $out;
    }

    public static function formatCollection(array $pickups, string $role): array
    {
        return array_map(fn($p) => self::format($p, $role), $pickups);
    }

    private static function formatIsoDate(?string $dateStr): ?string
    {
        if ($dateStr === null || $dateStr === '') {
            return null;
        }
        $ts = strtotime($dateStr);
        if ($ts === false) {
            return $dateStr;
        }
        return gmdate('Y-m-d\TH:i:s\Z', $ts);
    }
}
