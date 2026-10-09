<?php

declare(strict_types=1);

namespace App\Services;

class MatchScorer
{
    public const WEIGHT_ITEM = 0.35;
    public const WEIGHT_DIST = 0.30;
    public const WEIGHT_URG  = 0.20;
    public const WEIGHT_QTY  = 0.15;

    public const URGENCY_BASE_SCORES = [
        'low'      => 25.0,
        'medium'   => 50.0,
        'high'     => 75.0,
        'critical' => 100.0,
    ];

    /**
     * Compute weighted score and individual components for a match candidate.
     */
    public static function scoreCandidate(array $candidate, ?int $nowTimestamp = null): array
    {
        $now = $nowTimestamp ?? time();

        // 1. S_item (0..100)
        $sItem = (float) ($candidate['item_compatibility_factor'] ?? 100.0);
        $sItem = max(0.0, min(100.0, $sItem));

        // 2. S_dist (0..100)
        $distanceMeters = (float) ($candidate['distance_meters'] ?? 0.0);
        $radiusMeters   = (float) ($candidate['requirement_radius_km'] ?? 25.0) * 1000.0;
        if ($radiusMeters <= 0) {
            $sDist = 0.0;
        } else {
            $ratio = $distanceMeters / $radiusMeters;
            $sDist = max(0.0, min(100.0, 100.0 * (1.0 - $ratio)));
        }

        // 3. S_urg (0..100)
        $urgencyKey = strtolower((string) ($candidate['requirement_urgency'] ?? 'medium'));
        $baseUrg    = self::URGENCY_BASE_SCORES[$urgencyKey] ?? 50.0;
        $boost      = 0.0;

        if (!empty($candidate['requirement_needed_by'])) {
            $neededByTs = strtotime((string) $candidate['requirement_needed_by']);
            if ($neededByTs !== false) {
                $daysLeft = (int) ceil(($neededByTs - $now) / 86400);
                if ($daysLeft >= 0 && $daysLeft <= 14) {
                    $boost = 25.0 * (1.0 - ($daysLeft / 14.0));
                }
            }
        }
        $sUrg = max(0.0, min(100.0, $baseUrg + $boost));

        // 4. S_qty (0..100)
        $available   = (int) ($candidate['donation_available_quantity'] ?? 0);
        $outstanding = (int) ($candidate['requirement_outstanding'] ?? 1);
        if ($outstanding <= 0) {
            $sQty = 100.0;
        } else {
            $fit = min($available, $outstanding);
            $sQty = max(0.0, min(100.0, 100.0 * ($fit / (float) $outstanding)));
        }

        // Weighted total
        $totalScore = (self::WEIGHT_ITEM * $sItem)
                    + (self::WEIGHT_DIST * $sDist)
                    + (self::WEIGHT_URG * $sUrg)
                    + (self::WEIGHT_QTY * $sQty);

        $totalScore = round(max(0.0, min(100.0, $totalScore)), 2);

        $band = match (true) {
            $totalScore >= 75.0 => 'High',
            $totalScore >= 50.0 => 'Medium',
            default             => 'Low',
        };

        return [
            'score'      => $totalScore,
            'score_band' => $band,
            'components' => [
                's_item' => round($sItem, 2),
                's_dist' => round($sDist, 2),
                's_urg'  => round($sUrg, 2),
                's_qty'  => round($sQty, 2),
            ],
        ];
    }

    /**
     * Sort candidates by: score DESC, distance ASC, donation_id ASC (deterministic tie-break)
     */
    public static function sortCandidates(array &$candidates): void
    {
        usort($candidates, function (array $a, array $b) {
            if ($b['score'] !== $a['score']) {
                return $b['score'] <=> $a['score'];
            }
            $distA = (float) ($a['distance_meters'] ?? 0);
            $distB = (float) ($b['distance_meters'] ?? 0);
            if ($distA !== $distB) {
                return $distA <=> $distB;
            }
            $idA = (int) ($a['donation_id'] ?? 0);
            $idB = (int) ($b['donation_id'] ?? 0);
            return $idA <=> $idB;
        });
    }
}
