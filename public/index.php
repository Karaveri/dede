<?php declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// === Güvenlik başlıkları ===
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' data: https:");
    // yeni:
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// === Global exception → JSON/HTML ayrımı ===
// === Basit dosya log yardımcıları ===
function app_log_dir(): string {
    $dir = dirname(__DIR__) . '/storage/logs';
    @mkdir($dir, 0775, true);
    return $dir;
}
function app_log_write(string $level, string $msg, array $ctx = []): void {
    // Prod’da yaz; dev’de istersen açık bırakabilirsin
    $isProd = defined('APP_ENV') && APP_ENV === 'prod';
    if (!$isProd) return;

    $file = app_log_dir() . '/app-' . date('Y-m-d') . '.log';
    $line = json_encode([
        'ts'    => date('c'),
        'lvl'   => strtoupper($level),
        'msg'   => $msg,
        'ctx'   => $ctx,
        'ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'uri'   => $_SERVER['REQUEST_METHOD'] . ' ' . ($_SERVER['REQUEST_URI'] ?? '/'),
    ], JSON_UNESCAPED_UNICODE);
    @file_put_contents($file, $line . PHP_EOL, FILE_APPEND);
}

// === Global exception → JSON/HTML ayrımı + LOG ===
set_exception_handler(function(\Throwable $e) {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xreq   = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    $ctype  = $_SERVER['CONTENT_TYPE'] ?? '';
    $wantsJson = stripos($accept, 'application/json') !== false
              || $xreq === 'xmlhttprequest'
              || $xreq === 'fetch'
              || stripos($ctype, 'application/json') !== false;

    // LOG
    app_log_write('error', $e->getMessage(), ['trace'=>$e->getTrace()]);

    http_response_code(500);

    if ($wantsJson) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'mesaj'=>'Sunucu hatası','kod'=>'SERVER_ERR'], JSON_UNESCAPED_UNICODE);
    } else {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            echo '<pre>'.$e->getMessage()."\n".$e->getTraceAsString().'</pre>';
        } else {
            echo '<h1>Sunucu Hatası</h1><p>Bir sorun oluştu.</p>';
        }
    }
    exit;
});

// === Konfigürasyon ===
require __DIR__ . '/../config/config.php'; // DB_HOST/NAME/... ve BASE_URL burada
require_once dirname(__DIR__) . '/app/helpers/slug_helper.php';
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Istanbul');

// === Yol sabitleri & autoload ===
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH',  BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
$helpersDir = dirname(__DIR__) . '/app/helpers';
foreach (glob($helpersDir . '/*.php') as $file) {
    require_once $file;
}
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;

    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require $file;
});

// === Log/ratelimit klasörlerini garanti altına al ===
$__base = dirname(__DIR__);
$__dirs = [
    $__base . '/storage/logs',
    $__base . '/storage/ratelimit',
];
foreach ($__dirs as $__d) {
    if (!is_dir($__d)) { @mkdir($__d, 0775, true); }
}
unset($__base, $__dirs, $__d);

// === Rotalar ===
require CONFIG_PATH . '/routes.php';

// === Çalıştır ===
use App\Core\Router;

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri    = $_SERVER['REQUEST_URI']   ?? '/';
    echo Router::dispatch($method, $uri);
} catch (\Throwable $e) {
    // LOG
    app_log_write('error', $e->getMessage(), ['trace'=>$e->getTrace()]);

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xreq   = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    $ctype  = $_SERVER['CONTENT_TYPE'] ?? '';
    $wantsJson = stripos($accept, 'application/json') !== false
              || $xreq === 'xmlhttprequest'
              || $xreq === 'fetch'
              || stripos($ctype, 'application/json') !== false;

    http_response_code(500);

    if ($wantsJson) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'    => false,
            'mesaj' => (defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : 'Sunucu hatası',
            'kod'   => 'SERVER_ERR'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            echo '<pre>'.$e->getMessage()."\n".$e->getTraceAsString().'</pre>';
        } else {
            require APP_PATH . '/Views/hata/genel.php';
        }
    }
}