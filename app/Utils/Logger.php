<?php

namespace App\Utils;

/**
 * Helper ghi nhật ký xử lý và lỗi (Audit & Error Logger)
 */
class Logger
{
    protected static string $logPath = __DIR__ . '/../../storage/logs/app.log';

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    protected static function write(string $level, string $message, array $context = []): void
    {
        $date = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = "[$date] [$level] $message$contextStr" . PHP_EOL;
        @file_put_contents(self::$logPath, $line, FILE_APPEND);
    }
}
