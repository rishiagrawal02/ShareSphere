<?php

declare(strict_types=1);

namespace App\Support;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;

class Logger
{
    private static ?LoggerInterface $instance = null;

    public static function get(): LoggerInterface
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $logPath = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        $logger = new MonologLogger('sharesphere');

        // File Handler with JSON formatting and daily rotation
        $fileHandler = new RotatingFileHandler(
            $logPath . '/app.log',
            14,
            Level::Debug
        );
        $fileHandler->setFormatter(new JsonFormatter());
        $logger->pushHandler($fileHandler);

        // Add secret redaction processor
        $logger->pushProcessor(new LogRedactor());

        self::$instance = $logger;
        return self::$instance;
    }

    public static function info(string $message, array $context = []): void
    {
        self::get()->info($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::get()->error($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::get()->warning($message, $context);
    }

    public static function setInstance(?LoggerInterface $logger): void
    {
        self::$instance = $logger;
    }
}
