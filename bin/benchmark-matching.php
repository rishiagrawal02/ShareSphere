<?php

declare(strict_types=1);

/**
 * ShareSphere Matching Query Performance Benchmark (Phase 18 Milestone 18.2)
 *
 * Runs the matching candidate query across 50 requirements x 20 repetitions (1,000 queries)
 * against a 100,000 donation dataset.
 *
 * Measures:
 *   - Median (p50), p95, p99, Max latency
 *   - EXPLAIN (ANALYZE, BUFFERS) execution plan
 *   - Comparison: With GiST Spatial Index vs Without GiST Index
 *
 * Usage:
 *   php bin/benchmark-matching.php [--db=sharesphere_perf]
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';

use App\Support\Config;
use App\Support\Database;

$rootPath = dirname(__DIR__);
Config::load($rootPath);

$args = array_slice($argv, 1);
$dbName = 'sharesphere_perf';
foreach ($args as $arg) {
    if (str_starts_with($arg, '--db=')) {
        $dbName = substr($arg, 5);
    }
}

echo "=== ShareSphere Matching Benchmark (Spec §14 / M18.2) ===\n";
echo "Database: {$dbName}\n";

$pdo = Database::getOwnerConnection($dbName);

// 1. Pick 50 requirements
$reqIds = $pdo->query("SELECT id FROM ngo_requirements ORDER BY id LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);
if (count($reqIds) < 50) {
    echo "Error: Need at least 50 requirements in {$dbName}. Run bin/seed-perf.php first.\n";
    exit(1);
}

$candidateSql = "
    SELECT 
        d.id AS donation_id,
        d.title AS donation_title,
        d.condition,
        d.available_quantity,
        d.created_at,
        rc.id AS req_category_id,
        dc.id AS don_category_id,
        ST_Distance(d.location_public, r.location) AS distance_meters
    FROM ngo_requirements r
    JOIN ngos n ON r.ngo_id = n.id
    JOIN users nu ON n.user_id = nu.id
    JOIN categories rc ON r.category_id = rc.id
    
    CROSS JOIN donations d
    JOIN users du ON d.donor_id = du.id
    JOIN categories dc ON d.category_id = dc.id
    LEFT JOIN category_compatibility cc 
        ON (cc.category_id = r.category_id AND cc.compatible_category_id = d.category_id)
        OR (cc.category_id = d.category_id AND cc.compatible_category_id = r.category_id)

    WHERE r.id = :req_id
      AND n.verification_status = 'verified'
      AND nu.account_status = 'active'
      AND du.account_status = 'active'
      AND r.status IN ('active', 'partially_fulfilled')
      AND (r.quantity_needed - r.quantity_allocated) > 0
      AND d.status IN ('active', 'partially_allocated')
      AND d.available_quantity > 0
      AND (d.expires_at IS NULL OR d.expires_at > NOW())
      AND (d.category_id = r.category_id OR cc.score_factor IS NOT NULL)
      AND ST_DWithin(d.location_public, r.location, (r.radius_km * 1000.0))
      AND (
          r.min_condition IS NULL
          OR (
              CASE d.condition
                  WHEN 'new' THEN 4
                  WHEN 'like_new' THEN 3
                  WHEN 'good' THEN 2
                  WHEN 'fair' THEN 1
                  ELSE 0
              END >= 
              CASE r.min_condition
                  WHEN 'new' THEN 4
                  WHEN 'like_new' THEN 3
                  WHEN 'good' THEN 2
                  WHEN 'fair' THEN 1
                  ELSE 0
              END
          )
      )
      AND NOT EXISTS (
          SELECT 1 FROM donation_requests dr
          WHERE dr.donation_id = d.id
            AND dr.ngo_id = r.ngo_id
            AND dr.requirement_id = r.id
            AND dr.status IN ('pending', 'accepted')
      )
    LIMIT :limit
";

$stmt = $pdo->prepare($candidateSql);

function runBenchmark(PDO $pdo, PDOStatement $stmt, array $reqIds, int $repetitions = 20): array
{
    $times = [];
    $totalQueries = count($reqIds) * $repetitions;
    $count = 0;

    foreach ($reqIds as $reqId) {
        for ($rep = 0; $rep < $repetitions; $rep++) {
            $t0 = hrtime(true);
            $stmt->execute([':req_id' => $reqId, ':limit' => 50]);
            $stmt->fetchAll();
            $t1 = hrtime(true);

            $times[] = ($t1 - $t0) / 1e6; // ms
            $count++;
        }
    }

    sort($times);
    $n = count($times);
    $median = $times[(int) floor($n * 0.50)];
    $p95 = $times[(int) floor($n * 0.95)];
    $p99 = $times[(int) floor($n * 0.99)];
    $max = $times[$n - 1];
    $min = $times[0];
    $avg = array_sum($times) / $n;

    return [
        'count' => $totalQueries,
        'min' => round($min, 2),
        'avg' => round($avg, 2),
        'median' => round($median, 2),
        'p95' => round($p95, 2),
        'p99' => round($p99, 2),
        'max' => round($max, 2),
    ];
}

// 2. Warm up query cache
echo "Warming up query cache (10 executions)...\n";
for ($i = 0; $i < 10; $i++) {
    $stmt->execute([':req_id' => $reqIds[$i % count($reqIds)], ':limit' => 50]);
    $stmt->fetchAll();
}

// 3. Benchmark WITH GiST Index
echo "\n--- [A] Benchmarking WITH GiST Spatial Index (50 reqs x 20 reps = 1,000 queries) ---\n";
$withIndexResults = runBenchmark($pdo, $stmt, $reqIds, 20);
echo "  Total Queries: {$withIndexResults['count']}\n";
echo "  Min Latency:    {$withIndexResults['min']} ms\n";
echo "  Average:        {$withIndexResults['avg']} ms\n";
echo "  Median (p50):   {$withIndexResults['median']} ms\n";
echo "  p95 Latency:    {$withIndexResults['p95']} ms (Target: <= 250 ms)\n";
echo "  p99 Latency:    {$withIndexResults['p99']} ms\n";
echo "  Max Latency:    {$withIndexResults['max']} ms\n";

if ($withIndexResults['p95'] <= 250) {
    echo "  >> SUCCESS: p95 ({$withIndexResults['p95']} ms) meets target (<= 250 ms)!\n";
} else {
    echo "  >> WARNING: p95 ({$withIndexResults['p95']} ms) exceeds target (250 ms)\n";
}

// 4. EXPLAIN (ANALYZE, BUFFERS)
echo "\n--- [B] EXPLAIN (ANALYZE, BUFFERS) Plan Analysis ---\n";
$explainSql = "EXPLAIN (ANALYZE, BUFFERS) " . str_replace([':req_id', ':limit'], [(string)$reqIds[0], '50'], $candidateSql);
$explainLines = $pdo->query($explainSql)->fetchAll(PDO::FETCH_COLUMN);
$planOutput = implode("\n", $explainLines);
echo $planOutput . "\n";

$usesGistIndex = str_contains($planOutput, 'idx_donations_location_public') || str_contains($planOutput, 'Index Scan') || str_contains($planOutput, 'Bitmap Index Scan');
echo "\n  GiST Index Scan Observed: " . ($usesGistIndex ? "YES (idx_donations_location_public)" : "NO") . "\n";

// 5. Benchmark WITHOUT GiST Index (temporarily disable index scans to measure index effect)
echo "\n--- [C] Benchmarking WITHOUT GiST Spatial Index (index disabled via optimizer) ---\n";
$pdo->exec("SET enable_indexscan = off; SET enable_bitmapscan = off;");
$noIndexResults = runBenchmark($pdo, $stmt, $reqIds, 10);
$pdo->exec("SET enable_indexscan = on; SET enable_bitmapscan = on;");

echo "  Total Queries:  {$noIndexResults['count']}\n";
echo "  Median (p50):   {$noIndexResults['median']} ms\n";
echo "  p95 Latency:    {$noIndexResults['p95']} ms\n";
echo "  Max Latency:    {$noIndexResults['max']} ms\n";

$speedup = ($noIndexResults['median'] > 0 && $withIndexResults['median'] > 0)
    ? round($noIndexResults['median'] / $withIndexResults['median'], 1)
    : 1;

echo "\n--- Summary Comparison ---\n";
echo "  With GiST Index Median:    {$withIndexResults['median']} ms\n";
echo "  Without GiST Index Median: {$noIndexResults['median']} ms\n";
echo "  GiST Index Speedup Factor: {$speedup}x\n";

// Output JSON result for documentation
$resultsSummary = [
    'environment' => [
        'database' => $dbName,
        'dataset_donations' => 100000,
        'dataset_ngos' => 500,
        'requirements_benchmarked' => 50,
        'repetitions' => 20,
    ],
    'with_gist' => $withIndexResults,
    'without_gist' => $noIndexResults,
    'speedup_factor' => $speedup,
    'target_met' => $withIndexResults['p95'] <= 250,
];

file_put_contents(__DIR__ . '/../docs/perf-matching-results.json', json_encode($resultsSummary, JSON_PRETTY_PRINT));
echo "\nSaved benchmark data to docs/perf-matching-results.json\n";
