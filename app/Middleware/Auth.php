<?php declare(strict_types=1);
namespace App\Middleware;

final class Auth
{
    /** @param array<string> $args */
    public function handle(array $args = []): ?string
    {
        if (\App\Core\Auth::check()) {
            return null;
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xreq   = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        $wantsJson = stripos($accept, 'application/json') !== false
                  || $xreq === 'xmlhttprequest'
                  || $xreq === 'fetch';

        if ($wantsJson) {
            if (!headers_sent()) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store');
            }
            return json_encode(['ok'=>false, 'kod'=>'AUTH', 'mesaj'=>'Giriş gerekli'], JSON_UNESCAPED_UNICODE);
        }

        $rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH) ?: '/';
        $base    = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/');
        $path    = $rawPath;
        if ($base && str_starts_with($rawPath, $base)) {
            $path = substr($rawPath, strlen($base)) ?: '/';
        }
        if ($path === '/admin/giris') {
            return null;
        }

        if (!headers_sent()) {
            header('Cache-Control: no-store');
            header('Location: ' . rtrim(BASE_URL, '/') . '/admin/giris', true, 302);
        }
        return '';
    }
}
