<?php
namespace App\Core;

abstract class Controller
{
    protected function isAjax(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xreq   = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        $ctype  = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($accept, 'application/json')
            || $xreq === 'xmlhttprequest'
            || $xreq === 'fetch'
            || str_contains($ctype, 'application/json');
    }

    protected function json(array $data, int $status = 200): string
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    protected function jsonOk(array $data = [], int $status = 200): string
    {
        return $this->json(['ok' => true] + $data, $status);
    }

    protected function jsonErr(string $mesaj, string $kod = 'ERR', array $hatalar = [], int $status = 400): string
    {
        $payload = ['ok'=>false,'mesaj'=>$mesaj,'kod'=>$kod];
        if ($hatalar) $payload['hatalar'] = $hatalar;
        return $this->json($payload, $status);
    }

    protected function flash(string $tip, string $mesaj): void
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) session_start();
        $_SESSION[$tip] = $mesaj;
    }

protected function redirect(string $to, int $code = 302): string
{
    // Mutlak URL değilse BASE_URL ile birleştir
    if (!preg_match('~^https?://~i', $to)) {
        $base = rtrim(BASE_URL, '/');
        $path = ltrim($to, '/');

        // Eğer yol zaten BASE_URL ile başlıyorsa, tekrar ekleme
        if (!str_starts_with('/' . $path, $base)) {
            $to = $base . '/' . $path;   // /fevzi/public + /admin/kategoriler
        } else {
            $to = '/' . $path;           // zaten /fevzi/public/... ise olduğu gibi
        }
    }
    if (!headers_sent()) header('Location: ' . $to, true, $code);
    return '';
}


    protected function view(string $path, array $data = []): string
    {
        return View::render($path, $data);
    }
}
