<?php
use App\Core\Router;  // ← TEK KEZ OLSUN
use App\Controllers\GirisController;
use App\Controllers\YonetimDenetleyici;
use App\Controllers\SayfalarController;
use App\Controllers\KategorilerDenetleyici;
use App\Controllers\MedyaController;

// ---- Giriş / Çıkış 
Router::get('/', function () {
    header('Location: ' . BASE_URL . '/admin/giris', true, 302);
    return ''; // akışı sonlandır
});
//Router::get('/', function () { header('Location: '.BASE_URL.'/admin/giris'); return ''; });


Router::get('/admin/giris', [GirisController::class, 'form']);              // <-- middleware YOK
Router::post('/admin/giris', [GirisController::class, 'post'], ['csrf','rate:5,600']); // <-- sadece POST'ta rate


Router::get('/admin/cikis', [GirisController::class, 'cikis'], ['auth']); 
Router::get('/admin',       [YonetimDenetleyici::class, 'ana'], ['auth']); 

// ---- Sayfalar
Router::get ('/admin/sayfalar',                    [SayfalarController::class, 'index'],     ['auth']);
Router::get ('/admin/sayfalar/duzenle',            [SayfalarController::class, 'duzenle'],   ['auth']);
Router::post('/admin/sayfalar/guncelle',           [SayfalarController::class, 'guncelle'],  ['auth','csrf','rate:60,60']);
Router::post('/admin/sayfalar/durum',              [SayfalarController::class, 'durum'],         ['auth','csrf','rate:120,60']);
Router::post('/admin/sayfalar/durum-toplu',        [SayfalarController::class, 'durumToplu'],    ['auth','csrf','rate:120,60']);
Router::post('/admin/sayfalar/slug-kontrol',       [SayfalarController::class, 'slugKontrol'],   ['auth','csrf','rate:180,60']);
Router::post('/admin/sayfalar/sil',                [SayfalarController::class, 'sil'],       ['auth','csrf','rate:60,60']);
Router::post('/admin/sayfalar/sil-toplu',          [App\Controllers\SayfalarController::class, 'silToplu'],     ['auth','csrf','rate:60,60']);
Router::post('/admin/sayfalar/geri-al',            [SayfalarController::class, 'geriAl'],    ['auth','csrf','rate:60,60']);
Router::post('/admin/sayfalar/kalici-sil',         [SayfalarController::class, 'yokEt'], ['auth','csrf','rate:30,60']);
Router::get('/admin/sayfalar/olustur', 		       [SayfalarController::class, 'olustur'], ['auth']);
Router::post('/admin/sayfalar/kaydet', 		       [SayfalarController::class, 'kaydet'], ['auth','csrf','rate:60,60']);
Router::post('/admin/sayfalar/yok-et',  	       [SayfalarController::class, 'yokEt'], ['auth','csrf','rate:30,60']);
Router::post('/admin/sayfalar/durum-tekil',        [SayfalarController::class, 'durum'], ['auth','csrf','rate:120,60']);
Router::post('/admin/sayfalar/geri-al-toplu',      [SayfalarController::class, 'geriAl'],    ['auth','csrf','rate:60,60']); // aynı metoda düşer
Router::get ('/admin/sayfalar/cop',                [SayfalarController::class, 'cop'],       ['auth']);
Router::get('/admin/sayfalar/cop/say',             [SayfalarController::class, 'copSayJson'],    ['auth']);
Router::post('/admin/sayfalar/cop/geri-al',        [SayfalarController::class, 'geriAl'], ['auth','csrf','rate:60,60']);
Router::post('/admin/sayfalar/cop/kalici-sil',     [SayfalarController::class, 'yokEt'],  ['auth','csrf','rate:30,60']);

// ---- Kategoriler
Router::get ('/admin/kategoriler',                 [KategorilerDenetleyici::class, 'index'],     ['auth']);
Router::post('/admin/kategoriler/guncelle',        [KategorilerDenetleyici::class, 'guncelle'],  ['auth','csrf','rate:60,60']);
Router::post('/admin/kategoriler/durum',           [KategorilerDenetleyici::class, 'durum'],     ['auth','csrf','rate:120,60']);
Router::post('/admin/kategoriler/durum-toplu',     [KategorilerDenetleyici::class, 'durumToplu'],['auth','csrf','rate:120,60']);
Router::post('/admin/kategoriler/durum-tekil',     [KategorilerDenetleyici::class, 'durumTekil'],['auth','csrf','rate:120,60']);
Router::get ('/admin/kategoriler/cop',             [KategorilerDenetleyici::class, 'cop'],       ['auth']);
Router::post('/admin/kategoriler/cop/geri-al',     [KategorilerDenetleyici::class, 'copGeriAl'], ['auth','csrf','rate:60,60']);
Router::post('/admin/kategoriler/yok-et',          [KategorilerDenetleyici::class, 'yokEt'],     ['auth','csrf','rate:30,60']); // kalıcı sil daha sıkı
Router::post('/admin/kategoriler/cop/kalici-sil',  [KategorilerDenetleyici::class, 'copKaliciSil'], ['auth','csrf','rate:30,60']);
Router::get('/admin/kategoriler/cop/say',          [KategorilerDenetleyici::class, 'copSayJsoz'], ['auth']);
Router::post('/admin/kategoriler/sil',             [KategorilerDenetleyici::class, 'sil'],       ['auth','csrf','rate:60,60']);
Router::post('/admin/kategoriler/sil-toplu',       [KategorilerDenetleyici::class, 'silToplu'],  ['auth','csrf','rate:60,60']);
Router::post('/admin/kategoriler/geri-al',         [KategorilerDenetleyici::class, 'geriAl'],    ['auth','csrf','rate:60,60']);
Router::post('/admin/kategoriler/slug-kontrol',    [KategorilerDenetleyici::class, 'slugKontrol'], ['auth','csrf','rate:180,60']);
Router::get('/admin/kategoriler/olustur',   	   [KategorilerDenetleyici::class, 'olustur'], ['auth']);
Router::post('/admin/kategoriler/kaydet',   	   [KategorilerDenetleyici::class, 'kaydet'], ['auth','csrf','rate:60,60']);
Router::get('/admin/kategoriler/duzenle',   	   [KategorilerDenetleyici::class, 'duzenle'], ['auth']);   // ?id=...
Router::post('/admin/kategoriler/geri-al-toplu',   [KategorilerDenetleyici::class, 'copGeriAl'], ['auth','csrf','rate:60,60']); // alias

// ---- Medya
Router::get ('/admin/medya',            [MedyaController::class, 'index'],     ['auth']);
Router::post('/admin/medya/sil',        [MedyaController::class, 'sil'],       ['auth','csrf','rate:120,60']);     // sık ama hafif
Router::post('/admin/medya/toplu-sil',  [MedyaController::class, 'topluSil'],  ['auth','csrf','rate:120,60']);
Router::post('/admin/medya/yukle',      [MedyaController::class, 'upload'],    ['auth','csrf','rate:30,60']);      // upload daha sıkı
Router::post('/admin/medya/thumb-fix', [MedyaController::class, 'thumbFix'], ['auth','csrf','rate:30,300']);

// Yönlendirmeler (admin)
Router::get('/admin/yonlendirmeler',            [App\Controllers\YonlendirmelerDenetleyici::class, 'index'],    ['auth']);
Router::post('/admin/yonlendirmeler/durum',     [App\Controllers\YonlendirmelerDenetleyici::class, 'durum'],    ['auth','csrf','rate:120,60']);
Router::post('/admin/yonlendirmeler/sil',       [App\Controllers\YonlendirmelerDenetleyici::class, 'sil'],      ['auth','csrf','rate:60,60']);
Router::post('/admin/yonlendirmeler/toplu-sil', [App\Controllers\YonlendirmelerDenetleyici::class, 'topluSil'], ['auth','csrf','rate:60,60']);

// robots.txt
Router::get('/robots.txt', function () {
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: max-age=86400, public');
    }
    $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/');
    echo "User-agent: *\n";
    echo "Disallow: /admin/\n";
    echo "Sitemap: {$base}/sitemap.xml\n";
    return '';
});

// sitemap.xml
Router::get('/sitemap.xml', function () {
    $pdo  = \App\Core\DB::pdo();
    $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/');

    // Yayında ve silinmemiş sayfalar
    $st = $pdo->query("SELECT slug, COALESCE(updated_at, guncelleme_tarihi, olusturma_tarihi, NOW()) AS lm
                       FROM sayfalar WHERE silindi = 0 AND durum = 'yayinda'");
    $sayfalar = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    // (Opsiyonel) Kategoriler (yayında ve silinmemiş)
    $kt = $pdo->query("SELECT slug, COALESCE(guncelleme_tarihi, olusturma_tarihi, NOW()) AS lm
                       FROM kategoriler WHERE silindi = 0 AND durum = 1");
    $kategoriler = $kt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $xml  = [];
    $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    // Ana sayfa (istersen yorumla)
    $xml[] = '  <url><loc>'.$base.'/'.'</loc><changefreq>daily</changefreq><priority>1.0</priority></url>';

    foreach ($sayfalar as $p) {
        $loc = $base . '/' . ltrim($p['slug'], '/');
        $lm  = date('c', strtotime($p['lm'] ?? 'now'));
        $xml[] = "  <url><loc>{$loc}</loc><lastmod>{$lm}</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>";
    }
    foreach ($kategoriler as $k) {
        $loc = $base . '/kategori/' . ltrim($k['slug'], '/');
        $lm  = date('c', strtotime($k['lm'] ?? 'now'));
        $xml[] = "  <url><loc>{$loc}</loc><lastmod>{$lm}</lastmod><changefreq>weekly</changefreq><priority>0.6</priority></url>";
    }

    $xml[] = '</urlset>';

    if (!headers_sent()) {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: max-age=3600, public');
    }
    echo implode("\n", $xml);
    return '';
});
