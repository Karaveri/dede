<?php declare(strict_types=1);
namespace App\Middleware;

final class Csrf
{
    /** @param array<int,string> $args */
    public function handle(array $args = []): ?string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') return null;
        $ok = \App\Core\Csrf::check();
        if ($ok) return null;
        // JSON döndür
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
        }
        return json_encode(['ok'=>false,'mesaj'=>'Güvenlik doğrulaması başarısız.','kod'=>'CSRF_RED'], JSON_UNESCAPED_UNICODE);
    }
}
