<?php
namespace App\Core;
use App\Models\KullaniciModel;

final class Auth
{
    private const KEY = 'admin_id';

    private static function start(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    }

    public static function giris(string $email, string $sifre): bool {
        self::start();
        $kul = KullaniciModel::bulByEmail($email);
        if (!$kul) return false;

        if (!password_verify($sifre, $kul['sifre_hash'])) return false;
        if (($kul['durum'] ?? 'aktif') !== 'aktif') return false;
        if (!empty($kul['banli'])) return false;

        // Session fixation önlemi
        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION[self::KEY] = (int)$kul['id'];
        $_SESSION['rol_id']  = (int)$kul['rol_id'];
        $_SESSION['rol']     = $kul['rol_ad'] ?? self::rolFromId((int)$kul['rol_id']) ?? 'uye';
        // Geriye uyumluluk
        $_SESSION['uid']     = (int)$kul['id'];

        // Eski CSRF’yi geçersiz kıl
        unset($_SESSION['csrf'], $_SESSION['csrf_time']);

        return true;
    }

    public static function cikis(): void {
        self::start();
        unset($_SESSION[self::KEY], $_SESSION['uid'], $_SESSION['rol'], $_SESSION['rol_id']);
        session_destroy();
    }

    public static function uid(): ?int {
        self::start();
        return isset($_SESSION[self::KEY]) ? (int)$_SESSION[self::KEY] : null;
    }

    public static function rol(): ?string {
        self::start();
        $r = $_SESSION['rol'] ?? null;
        if ($r) return $r;
        $id = $_SESSION['rol_id'] ?? null;
        return $id ? (self::rolFromId((int)$id) ?? null) : null;
    }

    public static function girisliMi(): bool {
        self::start();
        return isset($_SESSION[self::KEY]) && (int)$_SESSION[self::KEY] > 0;
    }

    public static function zorunlu(): void {
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/admin/giris'); exit;
        }
    }

    public static function zorunluRol(array $roller): void {
        self::start();
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/admin/giris'); exit;
        }
        $mev = self::rol();
        $id  = $_SESSION['rol_id'] ?? null;
        $isimUyar = $mev && in_array($mev, $roller, true);
        $idUyar   = $id && in_array(self::rolFromId((int)$id), $roller, true);
        if (!$isimUyar && !$idUyar) {
            header('Location: ' . BASE_URL . '/admin/giris'); exit;
        }
    }

    public static function check(): bool
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) session_start();
        return isset($_SESSION[self::KEY]) && (int)$_SESSION[self::KEY] > 0;
    }

    public static function id(): ?int
    {
        return self::check() ? (int)$_SESSION[self::KEY] : null;
    }

    public static function girisliyseYonlendir(string $path = '/admin'): void
    {
        if (self::check()) {
            $url = rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
            if (!headers_sent()) header('Location: ' . $url);
            exit;
        }
    }

    private static function rolFromId(int $id): ?string {
        return match($id) {
            1 => 'admin',
            2 => 'editor',
            3 => 'yazar',
            default => 'uye',
        };
    }
}
