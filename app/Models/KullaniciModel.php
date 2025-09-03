<?php
namespace App\Models;
use App\Core\DB;
use PDO;

final class KullaniciModel
{
    public static function bulByEmail(string $eposta): ?array {
        $sql = 'SELECT k.*, r.ad AS rol_ad
		        FROM kullanicilar k
		        LEFT JOIN roller r ON r.id = k.rol_id
		        WHERE k.eposta = ?
		        LIMIT 1';
        $q = DB::pdo()->prepare($sql);
        $q->execute([$eposta]);
        $r = $q->fetch();
        return $r ?: null;
    }
}