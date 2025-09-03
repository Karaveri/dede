<?php
// Bu dosya zaten yüklendiyse çık
if (defined('APP_CONFIG_LOADED')) return;
define('APP_CONFIG_LOADED', 1);

/* ---- Çevre / Hata Ayarları ---- */
// Ortam: 'dev' | 'prod'
defined('APP_ENV')    || define('APP_ENV', 'dev');

// Debug bayrağı: prod'da otomatik kapalı
defined('APP_DEBUG')  || define('APP_DEBUG', APP_ENV !== 'prod');

// Eski kodlarla uyum: DEBUG = APP_DEBUG
defined('DEBUG')      || define('DEBUG', APP_DEBUG);

// PHP hata görünürlüğü (dev: açık, prod: kapalı)
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

/* ---- Uygulama ---- */
// Projen path-bazlı çalışıyor; BASE_URL'i tek yerde sabitle
// Canlıya çıktığında gerekirse /fevzi/public yerine kendi alt dizinin yaz
defined('BASE_URL')   || define('BASE_URL', '/fevzi/public');

/* ---- Veritabanı ---- */
defined('DB_HOST')    || define('DB_HOST', '127.0.0.1');
defined('DB_NAME')    || define('DB_NAME', 'fevzi');
defined('DB_USER')    || define('DB_USER', 'root');
defined('DB_PASS')    || define('DB_PASS', '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');
defined('DB_PORT')    || define('DB_PORT', 3306);

/* ---- Rezerve (yasak) slug'lar ---- */
if (!defined('RESERVED_SLUGS')) {
    define('RESERVED_SLUGS', [
        'admin','login','logout','register','kayit','giris',
        'api','assets','uploads','media','medya','sitemap','robots',
        'feed','rss','search','arama','index','default'
    ]);
}

/* ---- Oturum/çerez güvenliği ---- */
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if (!headers_sent()) {
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
    }
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
