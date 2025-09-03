<?php
namespace App\Core;

final class View
{
    public static function render(string $path, array $data = []): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        extract($data, EXTR_SKIP);

        $baseLower = dirname(__DIR__) . '/views/';
        $baseUpper = dirname(__DIR__) . '/Views/';
        $base = is_dir($baseLower) ? $baseLower : $baseUpper;

        $safe = ltrim(str_replace(['..','\\'], ['', '/'], $path), '/');
        $file = $base . $safe . '.php';

        if (!is_file($file)) {
            http_response_code(500);
            return '<div class="alert alert-warning">Görünüm bulunamadı: '
                 . htmlspecialchars($safe . '.php', ENT_QUOTES, 'UTF-8')
                 . '</div>';
        }

        ob_start();

        if (str_starts_with($safe, 'admin/')) {
            $gorunumYolu = $file; // sablon içinde require edilecek içerik
            $csrfToken   = \App\Core\Csrf::token(); // sablon.php meta için
            require $base . 'admin/sablon.php';
        } else {
            require $file;
        }

        return (string)ob_get_clean();
    }
}
