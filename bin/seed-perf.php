<?php

declare(strict_types=1);

/**
 * ShareSphere Performance Dataset Seeder (Phase 18 Milestone 18.2)
 *
 * Efficiently seeds:
 *   - 1,000 Donors (users)
 *   - 500 NGOs (users + ngos with verified status and spatial coordinates)
 *   - 500 Requirements (mixed categories, urgencies, radii 15-50km)
 *   - 100,000 Donations (random coordinates within ~120km Pune box, mixed statuses/categories)
 *
 * Uses set-based PostgreSQL SQL (generate_series) for maximum throughput (< 30 seconds).
 * Runs VACUUM ANALYZE afterwards.
 *
 * Usage:
 *   php bin/seed-perf.php [--db=sharesphere_perf] [--count=100000] [--ngos=500]
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';

use App\Support\Config;
use App\Support\Database;

$rootPath = dirname(__DIR__);
Config::load($rootPath);

// Parse arguments
$args = array_slice($argv, 1);
$targetDb = null;
$donationCount = 100000;
$ngoCount = 500;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--db=')) {
        $targetDb = substr($arg, 5);
    } elseif (str_starts_with($arg, '--count=')) {
        $donationCount = (int) substr($arg, 8);
    } elseif (str_starts_with($arg, '--ngos=')) {
        $ngoCount = (int) substr($arg, 7);
    }
}

$dbName = $targetDb ?? Config::get('PERF_DB_NAME', 'sharesphere_perf');

echo "=== ShareSphere Performance Seeder (M18.2) ===\n";
echo "Target Database: {$dbName}\n";
echo "Target Donations: {$donationCount}\n";
echo "Target NGOs: {$ngoCount}\n\n";

// Step 1: Ensure database exists
$defaultPdo = Database::getOwnerConnection();
$stmt = $defaultPdo->prepare("SELECT 1 FROM pg_database WHERE datname = :dbname");
$stmt->execute(['dbname' => $dbName]);
if (!$stmt->fetchColumn()) {
    echo "Creating database {$dbName}...\n";
    $defaultPdo->exec("CREATE DATABASE \"{$dbName}\"");
}

// Step 2: Connect to target DB as owner
$pdo = Database::getOwnerConnection($dbName);
try {
    $pdo->exec("CREATE EXTENSION IF NOT EXISTS postgis");
    $pdo->exec("CREATE EXTENSION IF NOT EXISTS citext");
} catch (\Throwable $e) {
    // Extensions already created or require superuser
}

// Step 3: Run migrations if tables don't exist
$hasTables = $pdo->query("SELECT to_regclass('public.donations')")->fetchColumn();
if (!$hasTables) {
    echo "Applying migrations to {$dbName}...\n";
    $migrationsDir = $rootPath . '/db/migrations';
    $files = glob($migrationsDir . '/*.up.sql');
    sort($files);
    foreach ($files as $file) {
        $sql = file_get_contents($file);
        $pdo->exec($sql);
    }
    echo "Migrations applied successfully.\n";
}

$startTime = microtime(true);

// Step 4: Truncate existing test/perf data
echo "Clearing existing data...\n";
$pdo->exec("
    TRUNCATE TABLE donation_requests, donations, ngo_requirements, ngos, users, categories CASCADE
");

// Step 5: Seed Core Categories
echo "Seeding categories...\n";
$categories = [
    ['Clothing & Apparel', 'Adult and children clothing, shoes, jackets'],
    ['Groceries & Non-Perishable Food', 'Dry food items, rice, pulses, canned goods'],
    ['Educational Books & Stationary', 'School textbooks, notebooks, study material'],
    ['Medical Equipment & Supplies', 'First aid kits, wheelchairs, sanitizers'],
    ['Electronics & Appliances', 'Computers, tablets, chargers, appliances'],
    ['Furniture & Fixtures', 'Beds, desks, chairs, storage cabinets'],
    ['Toys & Recreation', 'Children board games, sports equipment, toys'],
    ['Blankets & Bedding', 'Winter blankets, bedsheets, pillows'],
];

$insertCat = $pdo->prepare("
    INSERT INTO categories (name, description, is_active)
    VALUES (:name, :desc, true)
    RETURNING id
");
$catIds = [];
foreach ($categories as $cat) {
    $insertCat->execute(['name' => $cat[0], 'desc' => $cat[1]]);
    $catIds[] = (int) $insertCat->fetchColumn();
}
$catIdsList = implode(',', $catIds);

// Seed basic compatibility between Clothes and Bedding
$pdo->exec("
    INSERT INTO category_compatibility (category_id, compatible_category_id, score_factor)
    VALUES ({$catIds[0]}, {$catIds[7]}, 0.85)
    ON CONFLICT DO NOTHING
");

// Step 6: Bulk Seed Donors (1,000 Donors)
echo "Bulk inserting 1,000 donors...\n";
$dummyHash = password_hash('PerfPass123!', PASSWORD_BCRYPT);
$pdo->exec("
    INSERT INTO users (name, email, password_hash, role, account_status)
    SELECT 
        'Perf Donor ' || i,
        'perf-donor-' || i || '@sharesphere.test',
        '{$dummyHash}',
        'donor',
        'active'
    FROM generate_series(1, 1000) AS i
");

// Retrieve donor ID range
$donorMinMax = $pdo->query("SELECT MIN(id) as min_id, MAX(id) as max_id FROM users WHERE role = 'donor'")->fetch();
$minDonorId = (int) $donorMinMax['min_id'];
$maxDonorId = (int) $donorMinMax['max_id'];

// Step 7: Bulk Seed NGOs (500 NGOs + Users)
echo "Bulk inserting {$ngoCount} NGOs with Pune spatial coordinates...\n";
$pdo->exec("
    WITH new_users AS (
        INSERT INTO users (name, email, password_hash, role, account_status)
        SELECT 
            'Perf NGO Org ' || i,
            'perf-ngo-' || i || '@sharesphere.test',
            '{$dummyHash}',
            'ngo',
            'active'
        FROM generate_series(1, {$ngoCount}) AS i
        RETURNING id, name
    )
    INSERT INTO ngos (user_id, organization_name, registration_number, address_text, location, verification_status)
    SELECT 
        id,
        name,
        'REG-PERF-' || LPAD(id::text, 6, '0'),
        'Pune Central Sector ' || (id % 20),
        ST_SetSRID(ST_MakePoint(73.3 + (random() * 1.1), 18.0 + (random() * 1.1)), 4326)::geography,
        'verified'
    FROM new_users
");

// Step 8: Bulk Seed NGO Requirements (500 Requirements)
echo "Bulk inserting {$ngoCount} requirements...\n";
$catArraySql = "ARRAY[{$catIdsList}]";
$pdo->exec("
    INSERT INTO ngo_requirements (
        ngo_id, category_id, title, description, 
        quantity_needed, quantity_allocated, quantity_fulfilled, 
        urgency, min_condition, location, radius_km, status
    )
    SELECT 
        n.id,
        ({$catArraySql})[floor(random() * " . count($catIds) . " + 1)::int],
        'Urgent Need for ' || n.organization_name,
        'Standard requirement posted for community relief operations in Pune',
        floor(random() * 40 + 10)::int,
        0,
        0,
        (ARRAY['low', 'medium', 'high', 'critical'])[floor(random() * 4 + 1)::int],
        (ARRAY['new', 'like_new', 'good', 'fair'])[floor(random() * 4 + 1)::int],
        n.location,
        floor(random() * 35 + 15)::numeric, -- 15 to 50 km radius
        'active'
    FROM ngos n
");

// Step 9: Bulk Seed 100,000 Donations (Set-based via generate_series)
echo "Bulk inserting {$donationCount} donations across Pune ~120km bounding box...\n";
$insertDonationsSql = "
    INSERT INTO donations (
        donor_id, category_id, title, description, condition, 
        total_quantity, available_quantity, status, address_text, 
        location, location_public, expires_at, created_at, updated_at
    )
    SELECT
        floor(random() * ({$maxDonorId} - {$minDonorId} + 1) + {$minDonorId})::bigint,
        ({$catArraySql})[floor(random() * " . count($catIds) . " + 1)::int],
        'Donation Item #' || i,
        'Quality donation item available for verified non-profit organizations in Maharashtra',
        (ARRAY['new', 'like_new', 'good', 'fair'])[floor(random() * 4 + 1)::int],
        total_qty,
        CASE 
            WHEN stat = 'active' THEN total_qty
            WHEN stat = 'partially_allocated' THEN floor(total_qty / 2)::int
            ELSE 0
        END,
        stat,
        'Pune Regional Area ' || (i % 100),
        pt,
        pt,
        NOW() + INTERVAL '30 days',
        NOW() - (random() * INTERVAL '15 days'),
        NOW()
    FROM (
        SELECT 
            i,
            floor(random() * 20 + 2)::int AS total_qty,
            CASE 
                WHEN random() < 0.85 THEN 'active'
                WHEN random() < 0.95 THEN 'partially_allocated'
                ELSE 'closed'
            END AS stat,
            ST_SetSRID(ST_MakePoint(73.3 + (random() * 1.1), 18.0 + (random() * 1.1)), 4326)::geography AS pt
        FROM generate_series(1, {$donationCount}) AS i
    ) s
";

$pdo->exec($insertDonationsSql);

// Step 10: Run VACUUM ANALYZE to update PostgreSQL optimizer statistics
echo "Running VACUUM ANALYZE on all seeded tables...\n";
$pdo->exec("VACUUM ANALYZE donations");
$pdo->exec("VACUUM ANALYZE ngo_requirements");
$pdo->exec("VACUUM ANALYZE ngos");
$pdo->exec("VACUUM ANALYZE users");
$pdo->exec("VACUUM ANALYZE categories");

$duration = round(microtime(true) - $startTime, 2);

// Verification Counts
$totalDonations = (int) $pdo->query("SELECT count(*) FROM donations")->fetchColumn();
$totalNgos = (int) $pdo->query("SELECT count(*) FROM ngos")->fetchColumn();
$totalReqs = (int) $pdo->query("SELECT count(*) FROM ngo_requirements")->fetchColumn();
$activeDonations = (int) $pdo->query("SELECT count(*) FROM donations WHERE status = 'active'")->fetchColumn();

echo "\n=== Seeding Completed Successfully! ===\n";
echo "Total Donations: {$totalDonations}\n";
echo "Active Donations: {$activeDonations}\n";
echo "Total NGOs: {$totalNgos}\n";
echo "Total Requirements: {$totalReqs}\n";
echo "Time Taken: {$duration} seconds\n";
