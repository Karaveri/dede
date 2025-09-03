<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function baglanti(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host    = DB_HOST;
        $db      = DB_NAME;
        $user    = DB_USER;
        $pass    = DB_PASS;
        $charset = DB_CHARSET;
        $port    = defined('DB_PORT') ? (int)DB_PORT : 3306;

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            self::$pdo = new PDO($dsn, $user, $pass, $opt);
        } catch (PDOException $e) {
            // Prod'da logla (opsiyonel)
            if (defined('APP_ENV') && APP_ENV === 'prod') {
                $dir = dirname(__DIR__, 2) . '/storage/logs';
                @mkdir($dir, 0775, true);
                $line = json_encode([
                    'ts'  => date('c'),
                    'lvl' => 'ERROR',
                    'msg' => 'DB connect failed',
                    'err' => $e->getMessage(),
                    'host'=> $host,
                    'port'=> $port,
                ], JSON_UNESCAPED_UNICODE);
                @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line . PHP_EOL, FILE_APPEND);
            }
            throw new \RuntimeException('Veritabanına bağlanılamadı: ' . $e->getMessage());
        }

        return self::$pdo;
    }
}
