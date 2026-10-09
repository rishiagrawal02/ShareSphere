#!/usr/bin/env php
<?php

/**
 * ShareSphere – Transactional Email Outbox Worker
 *
 * Usage:
 *   php bin/send-outbox.php [--batch=25] [--loop] [--sleep=5]
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Adapters\Mail\SmtpMailer;
use App\Services\Outbox;
use App\Support\Config;
use App\Support\Database;

$rootPath = dirname(__DIR__);
Config::load($rootPath);

$opts = getopt('', [
    'batch::',
    'loop',
    'sleep::',
]);

$batchSize = isset($opts['batch']) ? (int) $opts['batch'] : 25;
$isLoop    = isset($opts['loop']);
$sleepSec  = isset($opts['sleep']) ? (int) $opts['sleep'] : 5;

$pdo    = Database::getConnection();
$mailer = new SmtpMailer();
$outbox = new Outbox($pdo);

echo "[" . date('Y-m-d H:i:s') . "] ShareSphere Email Outbox Worker started...\n";

do {
    try {
        $sent = $outbox->sendPending($mailer, $batchSize);
        if ($sent > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Processed and delivered {$sent} email(s).\n";
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "[" . date('Y-m-d H:i:s') . "] Error during outbox processing: " . $e->getMessage() . "\n");
    }

    if ($isLoop) {
        sleep($sleepSec);
    }
} while ($isLoop);

echo "[" . date('Y-m-d H:i:s') . "] Worker finished.\n";
exit(0);
