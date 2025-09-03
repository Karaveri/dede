<?php
namespace App\Core;

final class Router
{
    /**
     * routes[METHOD][PATH] = ['handler' => callable|array|string, 'mw' => string[]]
     */
    private static array $routes = [
        'GET'     => [],
        'POST'    => [],
        'HEAD'    => [],
        'OPTIONS' => [],
    ];

    // İsteğe bağlı middleware eşlemesi
    private static array $middlewareMap = [
        'auth' => \App\Middleware\Auth::class,
        'csrf' => \App\Middleware\Csrf::class,
        'rate' => \App\Middleware\RateLimit::class,
    ];

    // Grup/prefix yığını
    private static array $groupPrefix = [];   // her eleman "/admin" gibi normalize prefix
    private static array $groupMw     = [];   // her eleman ['auth','csrf'] gibi mw listesi

    // --- Kayıt metotları ----------------------------------------------------

    public static function get(string $path, $handler, array $mw = []): void
    { self::map(['GET'], $path, $handler, $mw); }

    public static function post(string $path, $handler, array $mw = []): void
    { self::map(['POST'], $path, $handler, $mw); }

    public static function head(string $path, $handler, array $mw = []): void
    { self::map(['HEAD'], $path, $handler, $mw); }

    public static function options(string $path, $handler, array $mw = []): void
    { self::map(['OPTIONS'], $path, $handler, $mw); }

    /**
     * Birden çok methodu aynı handler'a bağla
     * @param string[] $methods
     */
    public static function map(array $methods, string $path, $handler, array $mw = []): void
    {
        $p = self::withGroupPrefix($path);
        $mws = array_merge(self::currentGroupMw(), $mw);
        foreach ($methods as $m) {
            $key = strtoupper($m);
            if (!isset(self::$routes[$key])) self::$routes[$key] = [];
            self::$routes[$key][$p] = ['handler' => $handler, 'mw' => $mws];
        }
    }

    /**
     * Grup/prefix: örn.
     * Router::group('/admin', ['auth'], function () {
     *   Router::get('/sayfalar', [Ctrl::class,'liste']);
     *   Router::post('/sayfalar/ekle', [Ctrl::class,'ekle'], ['csrf']);
     * });
     */
    public static function group(string $prefix, array $mw, callable $cb): void
    {
        self::$groupPrefix[] = self::norm($prefix);
        self::$groupMw[]     = $mw;
        try { $cb(); } finally {
            array_pop(self::$groupPrefix);
            array_pop(self::$groupMw);
        }
    }

    // --- Çalıştırma ---------------------------------------------------------

    public static function dispatch(string $method, string $uri): string
    {
        // PATH çıkar (query hariç), BASE_URL kırp, decode et, normalize et
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $base = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');
        if ($base && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        $path = self::norm(rawurldecode($path));
        $method = strtoupper($method);

        // Trailing slash kanonik: "/x/" → "/x" (sadece kök hariç)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $canonical = rtrim($path, '/');
            $query = parse_url($uri, PHP_URL_QUERY);
            $locBase = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/');
            $loc = $locBase . $canonical . ($query ? ('?' . $query) : '');
            if (!headers_sent()) {
                http_response_code(301);
                header('Location: ' . $loc);
                header('Cache-Control: no-store');
            }
            return '';
        }

        // HEAD → GET fallback (RFC: body yok)
        $lookupMethod = ($method === 'HEAD') ? 'GET' : $method;

        // --- ERKEN 301/302 YÖNLENDİRME KONTROLÜ (GET/HEAD) ---
        if ($lookupMethod === 'GET') {
            try {
                $pdo = \App\Core\Database::baglanti();

                // Kaynağı normalize ederek kaydetmiştik, burada da aynı normalized $path kullanıyoruz
                $st = $pdo->prepare("SELECT hedef, tip FROM yonlendirmeler WHERE aktif = 1 AND kaynak = :k LIMIT 1");
                $st->execute([':k' => $path]);
                $row = $st->fetch(\PDO::FETCH_ASSOC);

                if ($row && !headers_sent()) {
                    $hedef = (string)($row['hedef'] ?? '');
                    $tip   = (int)($row['tip']   ?? 301);
                    if ($hedef !== '') {
                        // Hedef absolute ise dokunma, değilse BASE_URL ile birleştir
                        $isAbs = preg_match('~^https?://~i', $hedef);
                        $base  = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/');
                        $loc   = $isAbs ? $hedef : ($base . (str_starts_with($hedef, '/') ? $hedef : ('/' . $hedef)));

                        http_response_code(($tip >= 300 && $tip < 400) ? $tip : 301);
                        header('Location: ' . $loc);
                        header('Cache-Control: no-store');
                        // HEAD ise gövde döndürmeyelim
                        return '';
                    }
                }
            } catch (\Throwable $e) {
                // Sessiz düş: yönlendirme tablosunda sorun varsa routing normal devam etsin
            }
        }

        $entry = self::$routes[$lookupMethod][$path] ?? null;

        // OPTIONS otomatik yanıtlama (özel handler yoksa)
        if ($method === 'OPTIONS' && !$entry) {
            $allow = self::allowedForPath($path);
            if ($allow) {
                if (!headers_sent()) {
                    header('Allow: ' . implode(', ', $allow));
                    header('Cache-Control: no-store');
                }
                http_response_code(204);
                return '';
            }
        }

        // 405 Method Not Allowed (aynı path başka methodlarla kayıtlıysa)
        if (!$entry) {
            $allow = self::allowedForPath($path);
            if ($allow) {
                if (!headers_sent()) {
                    header('Allow: ' . implode(', ', $allow));
                    header('Cache-Control: no-store');
                    http_response_code(405);
                    header('Content-Type: ' . (self::wantsJson() ? 'application/json; charset=utf-8'
                                                                  : 'text/html; charset=utf-8'));
                }
                return self::wantsJson()
                    ? json_encode(['ok'=>false,'kod'=>'METHOD_NOT_ALLOWED','mesaj'=>'Bu path için yöntem desteklenmiyor'], JSON_UNESCAPED_UNICODE)
                    : '<!doctype html><meta charset="utf-8"><title>405</title><h1>405 - Method Not Allowed</h1>';
            }

            // 404 Not Found
            if (!headers_sent()) {
                http_response_code(404);
                header('Content-Type: ' . (self::wantsJson() ? 'application/json; charset=utf-8'
                                                              : 'text/html; charset=utf-8'));
            }
            return self::wantsJson()
                ? json_encode(['ok'=>false,'kod'=>'NOT_FOUND','mesaj'=>'Sayfa bulunamadı'], JSON_UNESCAPED_UNICODE)
                : '404';
        }

        // Middleware zinciri
        foreach (($entry['mw'] ?? []) as $mwItem) {
            $name = $mwItem; $args = [];
            if (is_string($mwItem) && strpos($mwItem, ':') !== false) {
                [$name, $argStr] = explode(':', $mwItem, 2);
                $args = array_map('trim', explode(',', $argStr));
            }
            if (!isset(self::$middlewareMap[$name])) continue;
            $class = self::$middlewareMap[$name];
            $m = new $class();
            $resp = $m->handle($args);              // string|null
            if (is_string($resp)) return $resp;     // kısa devre
        }

        // Handler çağrısı
        $handler = $entry['handler'];
        $out = null;

        if (is_callable($handler)) {
            $out = $handler();
        } elseif (is_array($handler) && count($handler) === 2) {
            [$class, $methodName] = $handler;
            $obj = new $class();
            $out = $obj->$methodName();
        } elseif (is_string($handler) && str_contains($handler, '@')) {
            [$class, $methodName] = explode('@', $handler, 2);
            $obj = new $class();
            $out = $obj->$methodName();
        } else {
            throw new \RuntimeException('Geçersiz route handler');
        }

        // HEAD isteğinde body döndürme
        if ($method === 'HEAD') return '';

        return is_string($out) ? $out : (string)($out ?? '');
    }

    // --- Yardımcılar --------------------------------------------------------

    private static function norm(string $p): string
    {
        $p = '/' . trim($p, '/');
        // "///" gibi durumları sadeleştir
        $p = preg_replace('#/+#', '/', $p) ?: '/';
        return $p === '//' ? '/' : $p;
    }

    private static function withGroupPrefix(string $p): string
    {
        $prefix = '';
        if (!empty(self::$groupPrefix)) {
            // birden fazla grup iç içe olabilir → birleştir
            $prefix = implode('', self::$groupPrefix);
        }
        return self::norm($prefix . '/' . ltrim($p, '/'));
    }

    /** @return string[] */
    private static function currentGroupMw(): array
    {
        $out = [];
        foreach (self::$groupMw as $mw) {
            if ($mw) $out = array_merge($out, $mw);
        }
        return $out;
    }

    /** Bu PATH için izin verilen method listesi (Allow başlığı için) */
    private static function allowedForPath(string $path): array
    {
        $allow = [];
        foreach (self::$routes as $m => $table) {
            if (isset($table[$path])) $allow[] = $m;
        }
        // GET varsa HEAD de fiilen desteklenir
        if (in_array('GET', $allow, true) && !in_array('HEAD', $allow, true)) {
            $allow[] = 'HEAD';
        }
        sort($allow);
        return $allow;
    }

    private static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xreq   = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        return stripos($accept, 'application/json') !== false
            || $xreq === 'xmlhttprequest'
            || $xreq === 'fetch';
    }
}
