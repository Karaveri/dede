<?php
namespace App\Core;
use PDO;

final class DB
{
    // Tek doğruluk kaynağı: Database::baglanti()
    public static function pdo(): PDO {
        return Database::baglanti();
    }
}
