<?php
namespace App\Core;

final class Csrf
{
    private const KEY = 'csrf';
    private const TTL = 7200; // saniye; 0 yaparsan süre kontrolü pasif olur

    private static function tokenFromRequest(): ?string
    {
        // 1) Header varyasyonları
        foreach (['HTTP_X_CSRF_TOKEN', 'HTTP_X_CSRF', 'HTTP_X_XSRF_TOKEN'] as $h) {
            if (!empty($_SERVER[$h])) {
                return trim((string) $_SERVER[$h]);
            }
        }

        // 2) Form alanı (POST)
        if (isset($_POST['csrf']))        return trim((string) $_POST['csrf']);
        if (isset($_POST['csrf_token']))  return trim((string) $_POST['csrf_token']);

        // 3) Query string (GET)
        if (isset($_GET['csrf']))         return trim((string) $_GET['csrf']);
        if (isset($_GET['csrf_token']))   return trim((string) $_GET['csrf_token']);

        // 4) JSON body (Content-Type: application/json)
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') !== false) {
            static $jsonBody = null; // php://input bir kez okunmalı
            if ($jsonBody === null) {
                $raw = file_get_contents('php://input');
                $jsonBody = json_decode($raw ?: '', true);
            }
            if (is_array($jsonBody)) {
                if (isset($jsonBody['csrf']))       return trim((string) $jsonBody['csrf']);
                if (isset($jsonBody['csrf_token'])) return trim((string) $jsonBody['csrf_token']);
            }
        }

        return null;
    }

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();

        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
            $_SESSION[self::KEY . '_time'] = time();
        } elseif (self::TTL > 0 && !empty($_SESSION[self::KEY . '_time'])) {
            if (time() - ($_SESSION[self::KEY . '_time'] ?? 0) > self::TTL) {
                $_SESSION[self::KEY] = bin2hex(random_bytes(32));
                $_SESSION[self::KEY . '_time'] = time();
            }
        }
        return $_SESSION[self::KEY];
    }

    public static function input(string $name = 'csrf'): string
    {
        $t = self::token();
        return '<input type="hidden" name="'.htmlspecialchars($name, ENT_QUOTES).'" value="'.htmlspecialchars($t, ENT_QUOTES).'">';
    }

    public static function dogrula(?string $val): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty($_SESSION[self::KEY])) return false;

        $ok = is_string($val) && hash_equals($_SESSION[self::KEY], $val);

        if ($ok && self::TTL > 0) {
            $ts = $_SESSION[self::KEY . '_time'] ?? 0;
            if (!$ts || time() - $ts > self::TTL) $ok = false;
        }
        return $ok;
    }

    public static function check(?string $val = null): bool
    {
        if ($val === null) {
            $val = self::tokenFromRequest();
        }
        return self::dogrula($val);
    }

    public static function tokenUret(): string
    {
        // Eski çağrıları kırmamak için token()'a alias
        return self::token();
    }

    public static function verify(?string $val = null): bool { return self::check($val); }
    public static function validate(?string $val = null): bool { return self::check($val); }
}
