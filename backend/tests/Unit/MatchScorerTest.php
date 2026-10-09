<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Services\ExplanationBuilder;
use App\Services\MatchScorer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MatchScorer (M8.2 T-8.2-01 through T-8.2-09) and ExplanationBuilder.
 *
 * All tests use hardcoded candidate arrays — no DB or PostGIS required.
 */
class MatchScorerTest extends TestCase
{
    // ------------------------------------------------------------------
    // Helper: build a minimal candidate array
    // ------------------------------------------------------------------
    private function makeCandidate(
        float $itemFactor,
        string $matchType,
        float $distanceMeters,
        float $radiusKm,
        string $urgency,
        ?string $neededBy,
        int $available,
        int $outstanding,
        int $donationId = 1
    ): array {
        return [
            'item_compatibility_factor'   => $itemFactor,
            'category_match_type'         => $matchType,
            'distance_meters'             => $distanceMeters,
            'requirement_radius_km'       => $radiusKm,
            'requirement_urgency'         => $urgency,
            'requirement_needed_by'       => $neededBy,
            'donation_available_quantity' => $available,
            'requirement_outstanding'     => $outstanding,
            'donation_id'                 => $donationId,
            // Additional fields for ExplanationBuilder tests
            'donation_category_name'      => 'Clothing',
            'requirement_category_name'   => 'Clothing',
        ];
    }

    // ------------------------------------------------------------------
    // T-8.2-01 Weights sum to 1.00
    // ------------------------------------------------------------------
    public function testWeightsSumToOne(): void
    {
        $sum = MatchScorer::WEIGHT_ITEM
             + MatchScorer::WEIGHT_DIST
             + MatchScorer::WEIGHT_URG
             + MatchScorer::WEIGHT_QTY;

        $this->assertEqualsWithDelta(1.00, $sum, 0.0001, 'Weights must sum to exactly 1.00');
    }

    // ------------------------------------------------------------------
    // T-8.2-02 Perfect match scores 100
    // ------------------------------------------------------------------
    public function testPerfectMatchScores100(): void
    {
        // d=0, R=25km, critical, days_left=0, avail>=outstanding
        $cand = $this->makeCandidate(
            itemFactor:      100.0,
            matchType:       'exact',
            distanceMeters:  0.0,
            radiusKm:        25.0,
            urgency:         'critical',
            neededBy:        date('Y-m-d'),      // today = 0 days left
            available:       20,
            outstanding:     12
        );

        $result = MatchScorer::scoreCandidate($cand);

        $this->assertEqualsWithDelta(100.0, $result['score'], 0.01);
        $this->assertSame('High', $result['score_band']);
    }

    // ------------------------------------------------------------------
    // T-8.2-03 Distance edges: d=0, d=R, d>R (clamped to 0 for d>=R)
    // ------------------------------------------------------------------
    public function testSdistEdgeCases(): void
    {
        $base = [
            'item_compatibility_factor'   => 100.0,
            'category_match_type'         => 'exact',
            'requirement_radius_km'       => 25.0,
            'requirement_urgency'         => 'medium',
            'requirement_needed_by'       => null,
            'donation_available_quantity' => 10,
            'requirement_outstanding'     => 10,
            'donation_id'                 => 1,
        ];

        // d = 0 => S_dist = 100
        $r0 = MatchScorer::scoreCandidate(array_merge($base, ['distance_meters' => 0.0]));
        $this->assertEqualsWithDelta(100.0, $r0['components']['s_dist'], 0.01, 'd=0 should give s_dist=100');

        // d = R => S_dist = 0
        $rR = MatchScorer::scoreCandidate(array_merge($base, ['distance_meters' => 25000.0]));
        $this->assertEqualsWithDelta(0.0, $rR['components']['s_dist'], 0.01, 'd=R should give s_dist=0');

        // d > R => S_dist = 0 (clamped)
        $rOver = MatchScorer::scoreCandidate(array_merge($base, ['distance_meters' => 50000.0]));
        $this->assertEqualsWithDelta(0.0, $rOver['components']['s_dist'], 0.01, 'd>R should be clamped to 0');
    }

    // ------------------------------------------------------------------
    // T-8.2-04 Quantity fit
    // ------------------------------------------------------------------
    public function testSqtyEdgeCases(): void
    {
        $base = [
            'item_compatibility_factor'   => 100.0,
            'category_match_type'         => 'exact',
            'distance_meters'             => 0.0,
            'requirement_radius_km'       => 25.0,
            'requirement_urgency'         => 'medium',
            'requirement_needed_by'       => null,
            'donation_id'                 => 1,
        ];

        // avail 8 / outstanding 12 => 8/12 = 66.67
        $r1 = MatchScorer::scoreCandidate(array_merge($base, [
            'donation_available_quantity' => 8,
            'requirement_outstanding'     => 12,
        ]));
        $this->assertEqualsWithDelta(66.67, $r1['components']['s_qty'], 0.05);

        // avail 50 / outstanding 12 => 12/12 = 100 (min capping)
        $r2 = MatchScorer::scoreCandidate(array_merge($base, [
            'donation_available_quantity' => 50,
            'requirement_outstanding'     => 12,
        ]));
        $this->assertEqualsWithDelta(100.0, $r2['components']['s_qty'], 0.01);

        // avail 1 / outstanding 100 => 1/100 = 1.0
        $r3 = MatchScorer::scoreCandidate(array_merge($base, [
            'donation_available_quantity' => 1,
            'requirement_outstanding'     => 100,
        ]));
        $this->assertEqualsWithDelta(1.0, $r3['components']['s_qty'], 0.01);
    }

    // ------------------------------------------------------------------
    // T-8.2-05 Urgency boost
    // ------------------------------------------------------------------
    public function testUrgencyBoost(): void
    {
        $base = [
            'item_compatibility_factor'   => 100.0,
            'category_match_type'         => 'exact',
            'distance_meters'             => 0.0,
            'requirement_radius_km'       => 25.0,
            'donation_available_quantity' => 10,
            'requirement_outstanding'     => 10,
            'donation_id'                 => 1,
        ];

        // high + 3 days left => 75 + 25*(1-3/14) = 75 + 19.64 = 94.64, cap 100
        $in3Days = date('Y-m-d', strtotime('+3 days'));
        $r1 = MatchScorer::scoreCandidate(array_merge($base, [
            'requirement_urgency'   => 'high',
            'requirement_needed_by' => $in3Days,
        ]));
        $this->assertGreaterThan(75.0, $r1['components']['s_urg']);
        $this->assertLessThanOrEqual(100.0, $r1['components']['s_urg']);

        // high + no date => base 75 exactly
        $r2 = MatchScorer::scoreCandidate(array_merge($base, [
            'requirement_urgency'   => 'high',
            'requirement_needed_by' => null,
        ]));
        $this->assertEqualsWithDelta(75.0, $r2['components']['s_urg'], 0.01);

        // low + 20 days => beyond 14-day window, no boost => 25
        $in20Days = date('Y-m-d', strtotime('+20 days'));
        $r3 = MatchScorer::scoreCandidate(array_merge($base, [
            'requirement_urgency'   => 'low',
            'requirement_needed_by' => $in20Days,
        ]));
        $this->assertEqualsWithDelta(25.0, $r3['components']['s_urg'], 0.01);
    }

    // ------------------------------------------------------------------
    // T-8.2-06 Golden set ranking order
    // The golden set is documented in docs/matching.md §4.
    // Expected order (by score DESC): 1, 2, 4, 3, 9, 5, 6, 10, 7, 8
    // ------------------------------------------------------------------
    public function testGoldenSetRankingOrder(): void
    {
        $now = time();

        $fixtures = [
            // id => [item, dist_m, radius_km, urgency, days_left, avail, outstanding]
            1  => [100.0, 0.0,    25.0, 'critical', 0,    20, 12],
            2  => [100.0, 5000.0, 25.0, 'high',     null, 12, 12],
            3  => [100.0, 10000.0, 25.0, 'medium',  null, 6,  12],
            4  => [60.0,  5000.0, 25.0, 'high',     null, 20, 10],
            5  => [100.0, 20000.0, 25.0, 'low',     null, 8,  12],
            6  => [60.0,  15000.0, 25.0, 'medium',  7,    5,  12],
            7  => [100.0, 24000.0, 25.0, 'low',     null, 100, 1],
            8  => [60.0,  20000.0, 25.0, 'low',     null, 3,  12],
            9  => [100.0, 2000.0, 5.0,  'medium',   14,   1,  100],
            10 => [60.0,  4000.0, 5.0,  'critical', 3,    50, 50],
        ];

        $candidates = [];
        foreach ($fixtures as $id => [$item, $dist, $radius, $urgency, $daysLeft, $avail, $outstanding]) {
            $neededBy = null;
            if ($daysLeft !== null) {
                $neededBy = date('Y-m-d', $now + ($daysLeft * 86400));
            }
            $cand = $this->makeCandidate(
                $item, 'exact', $dist, $radius, $urgency, $neededBy, $avail, $outstanding, $id
            );
            $scored = MatchScorer::scoreCandidate($cand, $now);
            $candidates[$id] = array_merge($cand, $scored, ['donation_id' => $id]);
        }

        MatchScorer::sortCandidates($candidates);
        $orderedIds = array_column($candidates, 'donation_id');

        // Expected order (by score DESC, then distance ASC, then id ASC):
        // Scores: 1=100, 2=87, 4=76, 3=66.5, 9=63.15, 10=62, 7=56.2, 5=56, 6=51.75, 8=35.75
        // (see docs/matching.md §4 for hand-calculation)
        $expectedOrder = [1, 2, 4, 3, 9, 10, 7, 5, 6, 8];
        $this->assertSame($expectedOrder, $orderedIds, 'Golden set ranking order mismatch');
    }

    // ------------------------------------------------------------------
    // T-8.2-07 Tie-break deterministic
    // ------------------------------------------------------------------
    public function testTieBreakIsDeterministic(): void
    {
        $base = [
            'item_compatibility_factor'   => 100.0,
            'category_match_type'         => 'exact',
            'requirement_radius_km'       => 25.0,
            'requirement_urgency'         => 'medium',
            'requirement_needed_by'       => null,
            'donation_available_quantity' => 10,
            'requirement_outstanding'     => 10,
        ];

        // Two candidates with identical scores; different donation IDs — lower ID wins
        $a = array_merge($base, ['distance_meters' => 5000.0, 'donation_id' => 2]);
        $b = array_merge($base, ['distance_meters' => 5000.0, 'donation_id' => 1]);

        $scoreA = MatchScorer::scoreCandidate($a);
        $scoreB = MatchScorer::scoreCandidate($b);
        $this->assertEqualsWithDelta($scoreA['score'], $scoreB['score'], 0.01, 'Scores should be equal for tie-break test');

        $candidates = [
            array_merge($a, $scoreA),
            array_merge($b, $scoreB),
        ];
        MatchScorer::sortCandidates($candidates);
        $this->assertSame(1, (int) $candidates[0]['donation_id'], 'Lower donation_id should win tie-break');
        $this->assertSame(2, (int) $candidates[1]['donation_id']);

        // Run sort twice to verify determinism
        $candidates2 = $candidates;
        MatchScorer::sortCandidates($candidates2);
        $this->assertSame(
            array_column($candidates, 'donation_id'),
            array_column($candidates2, 'donation_id'),
            'Sort must be deterministic across repeated calls'
        );
    }

    // ------------------------------------------------------------------
    // T-8.2-08 Fuzz: 1000 random inputs always yield scores in [0,100], no NaN/INF
    // ------------------------------------------------------------------
    public function testFuzzOutputSanity(): void
    {
        srand(42);
        for ($i = 0; $i < 1000; $i++) {
            $cand = $this->makeCandidate(
                itemFactor:     (float) rand(0, 100),
                matchType:      rand(0, 1) ? 'exact' : 'compatible',
                distanceMeters: (float) rand(0, 100000),
                radiusKm:       (float) max(1, rand(1, 500)),
                urgency:        ['low', 'medium', 'high', 'critical'][rand(0, 3)],
                neededBy:       rand(0, 1) ? date('Y-m-d', time() + rand(-5, 30) * 86400) : null,
                available:      rand(0, 100),
                outstanding:    rand(1, 100)
            );

            $result = MatchScorer::scoreCandidate($cand);

            $score = $result['score'];
            $this->assertFalse(is_nan($score), "Score is NaN on iteration {$i}");
            $this->assertFalse(is_infinite($score), "Score is INF on iteration {$i}");
            $this->assertGreaterThanOrEqual(0.0, $score, "Score < 0 on iteration {$i}");
            $this->assertLessThanOrEqual(100.0, $score, "Score > 100 on iteration {$i}");

            foreach ($result['components'] as $key => $val) {
                $this->assertFalse(is_nan($val), "{$key} is NaN on iteration {$i}");
                $this->assertGreaterThanOrEqual(0.0, $val);
                $this->assertLessThanOrEqual(100.0, $val);
            }
        }
    }

    // ------------------------------------------------------------------
    // ExplanationBuilder tests
    // ------------------------------------------------------------------

    public function testExplanationBuilderExactCategory(): void
    {
        $cand = $this->makeCandidate(100.0, 'exact', 4200.0, 25.0, 'high', null, 12, 12);
        $reasons = ExplanationBuilder::buildReasons($cand);

        $this->assertNotEmpty($reasons);
        $this->assertStringContainsString('Exact category', $reasons[0]);
        $this->assertStringContainsString('4.2 km', $reasons[1]);
        $this->assertStringContainsString('High urgency', $reasons[2]);
        $this->assertStringContainsString('Fully satisfies', $reasons[3]);
    }

    public function testExplanationBuilderCompatibleCategoryPartialFit(): void
    {
        $cand = $this->makeCandidate(60.0, 'compatible', 8000.0, 25.0, 'medium', null, 5, 12);
        $reasons = ExplanationBuilder::buildReasons($cand);

        $this->assertStringContainsString('Compatible', $reasons[0]);
        $this->assertStringContainsString('Can fulfil 5 of 12', $reasons[3] ?? $reasons[2]);
    }

    public function testExplanationBuilderDeadlineBoost(): void
    {
        $in3Days = date('Y-m-d', strtotime('+3 days'));
        $cand = $this->makeCandidate(100.0, 'exact', 1000.0, 25.0, 'high', $in3Days, 10, 10);
        $reasons = ExplanationBuilder::buildReasons($cand);

        $found = false;
        foreach ($reasons as $r) {
            if (str_contains($r, 'Needed by deadline')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected a deadline-approaching reason');
    }
}
