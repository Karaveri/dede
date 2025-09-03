<?php
namespace App\Core;

final class Logger
{
    private static function path(): string {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return $dir . '/app-' . date('Y-m-d') . '.log';
    }

    private static function write(string $level, string $msg, array $ctx = []): void {
        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $msg,
            $ctx ? json_encode($ctx, JSON_UNESCAPED_UNICODE) : ''
        );
        @file_put_contents(self::path(), $line, FILE_APPEND);
    }

    public static function info(string $m, array $c=[]): void { self::write('info',  $m, $c); }
    public static function warn(string $m, array $c=[]): void { self::write('warn',  $m, $c); }
    public static function error(string $m, array $c=[]): void { self::write('error', $m, $c); }
}
