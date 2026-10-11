<?php

declare(strict_types=1);

/**
 * ShareSphere E2E Test Fixture & Seeder (Phase 18 Milestone 18.3)
 *
 * Prepares a clean state for Playwright tests:
 *   - Truncates test tables
 *   - Seeds core categories & compatibility
 *   - Seeds standard roles with known test passwords:
 *       * admin@sharesphere.test (role: admin)
 *       * donor@sharesphere.test (role: donor)
 *       * ngo@sharesphere.test (role: ngo, verified)
 *       * pending-ngo@sharesphere.test (role: ngo, pending)
 *
 * Usage:
 *   php bin/e2e-seed.php [--db=sharesphere_test]
 */

require_once __DIR__ . '/../backend/vendor/autoload.php';

use App\Support\Config;
use App\Support\Database;

$rootPath = dirname(__DIR__);
Config::load($rootPath);

$args = array_slice($argv, 1);
$dbName = null;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--db=')) {
        $dbName = substr($arg, 5);
    }
}

$targetDb = $dbName ?? Config::get('E2E_DB_NAME', Config::get('DB_NAME', 'sharesphere_dev'));

$pdo = Database::getOwnerConnection($targetDb);

echo "Preparing E2E Database: {$targetDb}...\n";

// 1. Truncate tables
$pdo->exec("
    TRUNCATE TABLE 
        audit_logs,
        donation_requests, 
        donation_images, 
        donations, 
        ngo_requirements, 
        ngo_documents, 
        ngos, 
        users, 
        categories,
        category_compatibility 
    CASCADE
");

// 2. Seed Categories
$categories = [
    ['Clothing & Apparel', 'Adult and children clothes and shoes', true],
    ['Groceries & Food', 'Non-perishable food grains, rice, pulses', true],
    ['Educational Books', 'School books, notebooks, reference material', true],
    ['Blankets & Bedding', 'Warm blankets, bedding sets, quilts', true],
    ['Medical Supplies', 'First aid kits, mobility equipment', true],
    ['Furniture', 'Desks, study tables, chairs', true],
];

$stmtCat = $pdo->prepare("INSERT INTO categories (name, description, is_active) VALUES (:name, :desc, :active) RETURNING id");
$catMap = [];
foreach ($categories as $cat) {
    $stmtCat->execute([':name' => $cat[0], ':desc' => $cat[1], ':active' => $cat[2]]);
    $catMap[$cat[0]] = (int) $stmtCat->fetchColumn();
}

// Category Compatibility
if (isset($catMap['Clothing & Apparel']) && isset($catMap['Blankets & Bedding'])) {
    $c1 = $catMap['Clothing & Apparel'];
    $c2 = $catMap['Blankets & Bedding'];
    $pdo->exec("INSERT INTO category_compatibility (category_id, compatible_category_id, score_factor) VALUES ({$c1}, {$c2}, 65) ON CONFLICT DO NOTHING");
}

// 3. Seed Users
$adminPass = password_hash('AdminPass123!', PASSWORD_BCRYPT);
$donorPass = password_hash('DonorPass123!', PASSWORD_BCRYPT);
$ngoPass   = password_hash('NgoPass123!', PASSWORD_BCRYPT);

$insertUser = $pdo->prepare("
    INSERT INTO users (name, email, password_hash, role, account_status)
    VALUES (:name, :email, :pass, :role, 'active')
    RETURNING id
");

// Admin
$insertUser->execute([
    ':name' => 'E2E System Admin',
    ':email' => 'admin@sharesphere.test',
    ':pass' => $adminPass,
    ':role' => 'admin',
]);
$adminId = (int) $insertUser->fetchColumn();

// Donor
$insertUser->execute([
    ':name' => 'E2E Test Donor',
    ':email' => 'donor@sharesphere.test',
    ':pass' => $donorPass,
    ':role' => 'donor',
]);
$donorId = (int) $insertUser->fetchColumn();

// Verified NGO User
$insertUser->execute([
    ':name' => 'Hope Care Foundation',
    ':email' => 'ngo@sharesphere.test',
    ':pass' => $ngoPass,
    ':role' => 'ngo',
]);
$ngoUserId = (int) $insertUser->fetchColumn();

// Insert Verified NGO profile
$insertNgo = $pdo->prepare("
    INSERT INTO ngos (user_id, organization_name, registration_number, address_text, location, verification_status)
    VALUES (:user_id, 'Hope Care Foundation', 'REG-E2E-HOPE', 'Kothrud, Pune, Maharashtra', ST_SetSRID(ST_MakePoint(73.8167, 18.5074), 4326)::geography, 'verified')
    RETURNING id
");
$insertNgo->execute([':user_id' => $ngoUserId]);
$verifiedNgoId = (int) $insertNgo->fetchColumn();

// Pending NGO User
$insertUser->execute([
    ':name' => 'Seva Relief Trust',
    ':email' => 'pending-ngo@sharesphere.test',
    ':pass' => $ngoPass,
    ':role' => 'ngo',
]);
$pendingNgoUserId = (int) $insertUser->fetchColumn();

// Insert Pending NGO profile
$insertNgoPending = $pdo->prepare("
    INSERT INTO ngos (user_id, organization_name, registration_number, address_text, location, verification_status)
    VALUES (:user_id, 'Seva Relief Trust', 'REG-E2E-PENDING', 'Shivajinagar, Pune, Maharashtra', ST_SetSRID(ST_MakePoint(73.8567, 18.5204), 4326)::geography, 'pending')
    RETURNING id
");
$insertNgoPending->execute([':user_id' => $pendingNgoUserId]);
$pendingNgoId = (int) $insertNgoPending->fetchColumn();

echo "E2E Fixtures seeded successfully!\n";
echo "  Admin:       admin@sharesphere.test / AdminPass123!\n";
echo "  Donor:       donor@sharesphere.test / DonorPass123!\n";
echo "  NGO Verified: ngo@sharesphere.test / NgoPass123!\n";
echo "  NGO Pending:  pending-ngo@sharesphere.test / NgoPass123!\n";
