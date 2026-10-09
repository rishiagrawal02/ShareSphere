<?php

declare(strict_types=1);

namespace App\Services;

class ExplanationBuilder
{
    /**
     * Build an array of human-readable explanation strings for a scored match candidate.
     */
    public static function buildReasons(array $candidate): array
    {
        $reasons = [];

        // 1. Category reason
        if (($candidate['category_match_type'] ?? '') === 'exact') {
            $reasons[] = 'Exact category match (' . ($candidate['donation_category_name'] ?? 'Identical') . ')';
        } else {
            $reasons[] = 'Compatible category match (' . ($candidate['donation_category_name'] ?? 'Compatible') . ')';
        }

        // 2. Distance reason
        $distKm = round(((float) ($candidate['distance_meters'] ?? 0)) / 1000.0, 1);
        $radiusKm = round((float) ($candidate['requirement_radius_km'] ?? 25.0), 1);
        $reasons[] = "Located {$distKm} km away (within your {$radiusKm} km radius)";

        // 3. Urgency reason
        $urgency = ucfirst(strtolower((string) ($candidate['requirement_urgency'] ?? 'medium')));
        $reasons[] = "{$urgency} urgency requirement";

        if (!empty($candidate['requirement_needed_by'])) {
            $days = (int) ceil((strtotime((string) $candidate['requirement_needed_by']) - time()) / 86400);
            if ($days >= 0 && $days <= 14) {
                $reasons[] = "Needed by deadline approaching ({$days} day" . ($days === 1 ? '' : 's') . ' left)';
            }
        }

        // 4. Quantity reason
        $available   = (int) ($candidate['donation_available_quantity'] ?? 0);
        $outstanding = (int) ($candidate['requirement_outstanding'] ?? 0);
        if ($available >= $outstanding && $outstanding > 0) {
            $reasons[] = "Fully satisfies requested quantity ({$outstanding} unit" . ($outstanding === 1 ? '' : 's') . ')';
        } elseif ($available > 0 && $outstanding > 0) {
            $fit = min($available, $outstanding);
            $reasons[] = "Can fulfil {$fit} of {$outstanding} requested units";
        }

        return $reasons;
    }
}
