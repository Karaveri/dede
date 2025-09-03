<?php declare(strict_types=1);
namespace App\Middleware;

/**
 * Dosya-temelli basit rate limit (sabit pencere).
 * - IP tespiti (X-Forwarded-For desteği)
 * - Path bazlı anahtar (query hariç) + HTTP method
 * - Dosya kilidi (flock) ile yarış durumlarını engelleme
 * - 429 yanıtında Retry-After ve X-RateLimit-* başlıkları
 * - JSON/HTML farkındalığı (Accept / X-Requested-With)
 */
final class RateLimit
{
    /** @param array{0?:string,1?:string} $args [maxPerWindow, windowSeconds] */
    public function handle(array $args = []): ?string
    {
        $max = isset($args[0]) ? max(1, (int)$args[0]) : 60; // pencere başına izin
        $win = isset($args[1]) ? max(1, (int)$args[1]) : 60; // pencere süresi (sn)

        // ---- Bucket anahtarı (IP + METHOD + PATH) ----
        $ip = $this->clientIp();

        $rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base    = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/');
        if ($base && str_starts_with($rawPath, $base)) {
            $path = substr($rawPath, strlen($base)) ?: '/';
        } else {
            $path = $rawPath;
        }

        $method    = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $bucketKey = sha1($ip . '|' . $method . ' ' . $path);

        // ---- Depo dosyası ----
        $dir = dirname(__DIR__, 2) . '/storage/ratelimit';
        @mkdir($dir, 0775, true);
        $file = $dir . '/' . $bucketKey . '.json';

        $now  = time();
        $data = ['start' => $now, 'count' => 0];

        // ---- Güvenli okuma/yazma (flock ile) ----
        $fp = @fopen($file, 'c+'); // yoksa oluştur
        if ($fp === false) {
            // depoya erişilemiyorsa engellemek yerine geçişe izin ver
            return null;
        }
        try {
            if (!flock($fp, LOCK_EX)) { fclose($fp); return null; }

            // mevcut pencere oku
            $raw = stream_get_contents($fp);
            if ($raw !== false && $raw !== '') {
                $prev = json_decode($raw, true);
                if (is_array($prev) && isset($prev['start'], $prev['count'])) {
                    $data = $prev;
                }
            }

            // pencere yenilemesi
            if ($now - (int)$data['start'] >= $win) {
                $data = ['start' => $now, 'count' => 0];
            }

            // sayacı artır
            $data['count']++;

            // dosyayı yeniden yaz
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        } catch (\Throwable $e) {
            @flock($fp, LOCK_UN);
            @fclose($fp);
            return null;
        }

        // ---- Limit kontrolü ----
        if ($data['count'] > $max) {
            $reset  = max(0, $win - ($now - (int)$data['start']));
            $isJson = $this->wantsJson();

            // HTML isteklerinde login POST'u için flash + redirect
            if (!$isJson && strtoupper($method) === 'POST' && $path === '/admin/giris') {
                if (session_status() !== \PHP_SESSION_ACTIVE) session_start();

                // Mesaj + reset zamanı (persist)
                $_SESSION['hata']           = 'Çok fazla deneme. Lütfen ' . $reset . ' saniye sonra tekrar deneyin.';
                $_SESSION['rate_reset_at']  = time() + $reset; // <<< eklendi

                if (!headers_sent()) {
                    header('Retry-After: ' . $reset);
                    header('Cache-Control: no-store');
                    header('Location: ' . rtrim(BASE_URL, '/') . '/admin/giris?rl=1', true, 303); // küçük ipucu paramı
                }
                return '';
            }

            // Diğer durumlarda standart 429 yanıtı
            if (!headers_sent()) {
                http_response_code(429);
                header('Retry-After: ' . $reset);
                header('X-RateLimit-Limit: ' . $max);
                header('X-RateLimit-Remaining: 0');
                header('X-RateLimit-Reset: ' . $reset);
                header('Cache-Control: no-store');
                header('Content-Type: ' . ($isJson ? 'application/json; charset=utf-8'
                                                   : 'text/html; charset=utf-8')); // <- DOĞRU
            }

            $msg = 'Çok fazla deneme. Lütfen ' . $reset . ' saniye sonra tekrar deneyin.';
            $payload = ['ok'=>false,'mesaj'=>$msg,'kod'=>'RATE_LIMIT','reset'=>$reset,'limit'=>$max,'kalan'=>0];

            return $isJson
                ? json_encode($payload, JSON_UNESCAPED_UNICODE)
                : '<!doctype html><meta charset="utf-8"><title>429 - Çok fazla istek</title>'
                  . '<div style="font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial">'
                  . '<h1>429</h1><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p></div>';
        }

        // Limit aşılmadı → middleware akışına devam
        return null;
    }

    // ---- Yardımcılar ----
    private function clientIp(): string
    {
        // Proxy arkasında ise ilk X-Forwarded-For IP’yi al
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff) {
            $ip = trim(explode(',', $xff)[0]);
            if ($ip !== '') return $ip;
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xreq   = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        return stripos($accept, 'application/json') !== false
            || $xreq === 'xmlhttprequest'
            || $xreq === 'fetch';
    }
}
