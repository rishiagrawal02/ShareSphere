#!/usr/bin/env php
<?php

/**
 * ShareSphere – CLI admin / NGO user creator
 *
 * Usage:
 *   php bin/create-admin.php --email=admin@example.com --name="Admin User" --role=admin
 *   php bin/create-admin.php --email=ngo@example.com  --name="Help NGO"   --role=ngo
 *
 * Options:
 *   --email     (required) Email address for the new account
 *   --name      (required) Display name
 *   --role      (required) One of: admin, ngo, donor
 *   --password  (optional) Password (prompted interactively if omitted)
 *   --phone     (optional) Phone number
 *   --status    (optional) Account status: active|pending_approval|suspended  (default: active)
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Support\Config;
use App\Support\Database;
use App\Repositories\UserRepository;

// ── Bootstrap ──────────────────────────────────────────────────────────────
$dotenv = dirname(__DIR__) . '/.env';
if (file_exists($dotenv)) {
    $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
        putenv(trim($key) . '=' . trim($val));
    }
}

// ── Parse CLI arguments ────────────────────────────────────────────────────
$opts = getopt('', [
    'email:',
    'name:',
    'role:',
    'password::',
    'phone::',
    'status::',
]);

$email  = $opts['email']  ?? null;
$name   = $opts['name']   ?? null;
$role   = $opts['role']   ?? null;
$phone  = $opts['phone']  ?? null;
$status = $opts['status'] ?? 'active';

$validRoles   = ['admin', 'ngo', 'donor'];
$validStatuses = ['active', 'pending_approval', 'suspended'];

$errors = [];

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = '--email must be a valid email address.';
}
if (empty($name) || strlen(trim($name)) < 2) {
    $errors[] = '--name must be at least 2 characters.';
}
if (empty($role) || !in_array($role, $validRoles, true)) {
    $errors[] = '--role must be one of: ' . implode(', ', $validRoles) . '.';
}
if (!in_array($status, $validStatuses, true)) {
    $errors[] = '--status must be one of: ' . implode(', ', $validStatuses) . '.';
}

if (!empty($errors)) {
    foreach ($errors as $err) {
        fwrite(STDERR, "Error: $err\n");
    }
    exit(1);
}

// ── Password (interactive prompt if not supplied) ──────────────────────────
$password = $opts['password'] ?? null;

if (empty($password)) {
    // Try to read silently
    if (PHP_OS_FAMILY === 'Windows') {
        fwrite(STDOUT, "Password (input hidden): ");
        // On Windows there is no built-in way to silence input; use a helper
        $password = trim((string) shell_exec('powershell -Command "$p = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"'));
    } else {
        fwrite(STDOUT, "Password (input hidden): ");
        shell_exec('stty -echo');
        $password = trim((string) fgets(STDIN));
        shell_exec('stty echo');
        fwrite(STDOUT, "\n");
    }
}

if (strlen($password) < 10) {
    fwrite(STDERR, "Error: Password must be at least 10 characters.\n");
    exit(1);
}

// ── Create user ────────────────────────────────────────────────────────────
try {
    $userRepo = new UserRepository(Database::getConnection());

    $existing = $userRepo->findByEmail((string) $email);
    if ($existing !== null) {
        fwrite(STDERR, "Error: A user with email '{$email}' already exists (ID: {$existing['id']}, role: {$existing['role']}).\n");
        exit(1);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $user = $userRepo->create([
        'name'           => trim((string) $name),
        'email'          => strtolower(trim((string) $email)),
        'password_hash'  => $hash,
        'role'           => $role,
        'account_status' => $status,
        'phone'          => $phone ?? null,
    ]);

    echo "\n✅ User created successfully:\n";
    echo "   ID     : {$user['id']}\n";
    echo "   Name   : {$user['name']}\n";
    echo "   Email  : {$user['email']}\n";
    echo "   Role   : {$user['role']}\n";
    echo "   Status : {$user['account_status']}\n";
    echo "   Created: {$user['created_at']}\n\n";

    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
