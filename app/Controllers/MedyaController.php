<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Csrf;
use App\Services\Image;
use PDO;
use Exception; // <<< EKLE

class MedyaController extends AdminController
{
    protected array $roller = ['admin','editor','yazar'];

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): string
    {
        $pdo    = DB::pdo();
        $q      = trim($_GET['q'] ?? '');
        $sayfa  = max(1, (int)($_GET['s'] ?? 1));
        $limit  = 24;
        $ofset  = ($sayfa - 1) * $limit;

        // Etiket parametreleri
        $tagsCsv = trim($_GET['tags'] ?? '');
        $mode    = (($_GET['mode'] ?? 'any') === 'all') ? 'all' : 'any';
        $tag     = trim($_GET['tag'] ?? ''); // geri uyumluluk

        // SQL parçaları
        $join  = '';
        $where = [];
        $param = [];
        $group = '';

        // Arama
        if ($q !== '') {
            $where[]      = '(m.yol LIKE :q1 OR m.mime LIKE :q2)';
            $param[':q1'] = "%{$q}%";
            $param[':q2'] = "%{$q}%";
        }

        // Çoklu etiket (tags=csv) + geri uyumluluk (tag)
        $slugs = [];
        if ($tagsCsv !== '') {
            foreach (array_filter(array_map('trim', explode(',', $tagsCsv))) as $s) {
                if ($s !== '') $slugs[] = $s;
            }
        }
        if ($tag !== '') $slugs[] = $tag;
        $slugs = array_values(array_unique($slugs));

        if (!empty($slugs)) {
            if ($mode === 'all') {
                // Hepsi: JOIN + HAVING COUNT(DISTINCT) = N
                $join .= ' JOIN medya_etiket mef ON mef.medya_id = m.id
                           JOIN etiketler    ef  ON ef.id = mef.etiket_id ';
                $inPh = [];
                foreach ($slugs as $i => $slug) {
                    $ph = ":t{$i}";
                    $inPh[] = $ph;
                    $param[$ph] = $slug;
                }
                $where[] = 'ef.slug IN ('.implode(',', $inPh).')';
                $group   = ' GROUP BY m.id HAVING COUNT(DISTINCT ef.slug) = '.count($slugs);
            } else {
                // En az biri: EXISTS
                $inPh = [];
                foreach ($slugs as $i => $slug) {
                    $ph = ":t{$i}";
                    $inPh[] = $ph;
                    $param[$ph] = $slug;
                }
                $where[] =
                    'EXISTS (SELECT 1 FROM medya_etiket me
                             JOIN etiketler e ON e.id = me.etiket_id
                             WHERE me.medya_id = m.id AND e.slug IN ('.implode(',', $inPh).'))';
            }
        }

        // WHERE oluştur
        $whereSql = '';
        if (!empty($where)) $whereSql = ' WHERE '.implode(' AND ', $where);

        // ---- Toplam kayıt (alt sorgu ile güvenli sayım)
        $sqlCount = 'SELECT COUNT(*) FROM (
                       SELECT m.id
                       FROM medya m'.$join.$whereSql.$group.'
                     ) as sub';
        $stc = $pdo->prepare($sqlCount);
        foreach ($param as $k => $v) $stc->bindValue($k, $v, PDO::PARAM_STR);
        $stc->execute();
        $toplam = (int)$stc->fetchColumn();
        $sayfaSayisi = max(1, (int)ceil($toplam / $limit));

        // ---- Liste
        $sqlList = 'SELECT m.id, m.yol, m.yol_thumb, m.mime, m.genislik, m.yukseklik
                    FROM medya m'.$join.$whereSql.$group.'
                    ORDER BY m.id DESC
                    LIMIT :l OFFSET :o';
        $st  = $pdo->prepare($sqlList);
        foreach ($param as $k => $v) $st->bindValue($k, $v, PDO::PARAM_STR);
        $st->bindValue(':l', $limit, PDO::PARAM_INT);
        $st->bindValue(':o', $ofset, PDO::PARAM_INT);
        $st->execute();
        $medyalar = $st->fetchAll(PDO::FETCH_ASSOC);

        return $this->view('admin/medya/index', [
            'baslik'      => 'Medya Kütüphanesi',
            'medyalar'    => $medyalar,
            'sayfaSayisi' => $sayfaSayisi,
            'q'           => $q,
            'sayfa'       => $sayfa,
        ]);
    }

    // POST /admin/medya/sil  — Tek sil
    public function sil(): void
    {
        // ---- CSRF DOĞRULAMA (manuel, sınıfa bağlı değil)
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $incoming = $_POST['csrf'] ?? $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $session  = $_SESSION['csrf'] ?? $_SESSION['csrf_token'] ?? '';
        if (!$incoming || !$session || !hash_equals((string)$session, (string)$incoming)) {
            $_SESSION['hata'] = 'Güvenlik doğrulaması başarısız.';
            header('Location: '.BASE_URL.'/admin/medya'); return;
        }
        // ----

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { header('Location: '.BASE_URL.'/admin/medya'); return; }

        $s = DB::pdo()->prepare('SELECT yol, yol_thumb FROM medya WHERE id = ? LIMIT 1');
        $s->execute([$id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            DB::pdo()->prepare('DELETE FROM medya WHERE id = ?')->execute([$id]);
            $root = dirname(__DIR__, 2) . '/public';

            // asıl dosya
            $abs = $root . $row['yol'];
            if (is_file($abs)) @unlink($abs);

            // thumb temizliği
            $tp = !empty($row['yol_thumb'])
                ? $root . $row['yol_thumb']
                : $root . preg_replace('~^/uploads/editor/~', '/uploads/editor/thumbs/', $row['yol']);
            if ($tp && is_file($tp)) @unlink($tp);
            $_SESSION['mesaj'] = 'Görsel silindi.';
        }
        header('Location: '.BASE_URL.'/admin/medya');
    }

    // POST /admin/medya/toplu-sil  — Çoklu sil
    public function topluSil(): void
    {
        // ---- CSRF DOĞRULAMA (manuel)
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $incoming = $_POST['csrf'] ?? $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $session  = $_SESSION['csrf'] ?? $_SESSION['csrf_token'] ?? '';
        if (!$incoming || !$session || !hash_equals((string)$session, (string)$incoming)) {
            $_SESSION['hata'] = 'Güvenlik doğrulaması başarısız.';
            header('Location: '.BASE_URL.'/admin/medya'); return;
        }
        // ----

        $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
        if (!$ids) { header('Location: '.BASE_URL.'/admin/medya'); return; }

        $in  = implode(',', array_fill(0, count($ids), '?'));
        $s   = DB::pdo()->prepare("SELECT id, yol, yol_thumb FROM medya WHERE id IN ($in)");
        $s->execute($ids);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);

        $del = DB::pdo()->prepare("DELETE FROM medya WHERE id IN ($in)");
        $del->execute($ids);

        $base = dirname(__DIR__, 2) . '/public';
        foreach ($rows as $r) {
            $abs = $base . $r['yol'];
            if (is_file($abs)) @unlink($abs);
            $tp = !empty($r['yol_thumb'])
                ? $base . $r['yol_thumb']
                : $base . preg_replace('~^/uploads/editor/~', '/uploads/editor/thumbs/', $r['yol']);
            if ($tp && is_file($tp)) @unlink($tp);            
        }
        $_SESSION['mesaj'] = count($rows).' görsel silindi.';
        header('Location: '.BASE_URL.'/admin/medya');
    }

    // POST /admin/medya/yukle — TinyMCE upload (DB kaydı ile)
    public function upload(): void
    {
        // JSON döneceğiz
        header('Content-Type: application/json; charset=utf-8');

        // CSRF
        if (!\App\Core\Csrf::check()) {
            http_response_code(403);
            echo json_encode(['ok'=>false,'kod'=>'CSRF_RED','mesaj'=>'Güvenlik doğrulaması başarısız.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            if (empty($_FILES) || (!isset($_FILES['file']) && !isset($_FILES['dosya']))) {
                throw new \RuntimeException('dosya_bulunamadi');
            }
            $f = $_FILES['file'] ?? $_FILES['dosya'];

            if (!isset($f['error']) || is_array($f['error'])) {
                throw new \RuntimeException('gecersiz_yukleme');
            }
            if ($f['error'] !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('yukleme_hatasi_'.$f['error']);
            }

            // Boyut sınırı
            $maxBytes = defined('MEDYA_MAX_BYTES') ? (int)MEDYA_MAX_BYTES : 8*1024*1024;
            if ((int)$f['size'] > $maxBytes) {
                throw new \RuntimeException('dosya_cok_buyuk');
            }

            // MIME doğrulama (config’teki whitelist)
            $allow = defined('MEDYA_IZINLI_MIMES') ? MEDYA_IZINLI_MIMES : [
                'image/jpeg'=>['jpg','jpeg'],
                'image/png' =>['png'],
                'image/webp'=>['webp'],
            ];
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            $mime  = $finfo ? @finfo_file($finfo, $f['tmp_name']) : null;
            if ($finfo) @finfo_close($finfo);
            if (!$mime || !isset($allow[$mime])) {
                throw new \RuntimeException('izin_verilmeyen_tur');
            }
            // SVG güvenlik sebebiyle reddediliyor (zaten whitelistte yok)
            if ($mime === 'image/svg+xml') {
                throw new \RuntimeException('svg_desteklenmiyor');
            }

            // Güvenli isim
            $nameOnly = preg_replace('~[^a-z0-9]+~i', '-', pathinfo($f['name'], PATHINFO_FILENAME));
            $nameOnly = trim($nameOnly, '-') ?: 'image';
            $origExt  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));

            // Yetenekler
            $canGD   = \extension_loaded('gd') && \function_exists('imagecreatetruecolor') && \function_exists('imagecreatefromstring');
            $canWebp = \function_exists('imagewebp');
            $preferWebp = (defined('MEDYA_KAYDET_WEBP') ? MEDYA_KAYDET_WEBP : true) && $canGD && $canWebp && $mime !== 'image/gif';

            // Hedef uzantı
            $ext = $preferWebp ? 'webp' : \App\Services\Image::pickExtension($mime, $origExt);

            $uniq     = bin2hex(random_bytes(3));
            $filename = $nameOnly.'-'.date('Ymd-His').'-'.$uniq.'.'.$ext;

            // Klasörler
            $uploadsRoot = defined('MEDYA_KLASOR') ? rtrim(MEDYA_KLASOR, '/\\') : (dirname(__DIR__, 2) . '/public/uploads');
            $dirFull     = $uploadsRoot . '/editor';
            $dirThumbs   = $uploadsRoot . '/editor/thumbs';
            if (!is_dir($dirFull)   && !mkdir($dirFull,   0775, true)) throw new \RuntimeException('upload_dir_full');
            if (!is_dir($dirThumbs) && !mkdir($dirThumbs, 0775, true)) throw new \RuntimeException('upload_dir_thumbs');
            if (!is_writable($dirFull) || !is_writable($dirThumbs))     throw new \RuntimeException('upload_dir_yazilamaz');

            $targetPath = $dirFull . '/' . $filename;
            if (!move_uploaded_file($f['tmp_name'], $targetPath)) {
                throw new \RuntimeException('dosya_tasinamadi');
            }

            // EXIF strip (JPEG kaynaklarda işe yarar; WEBP'ye geçmeden önce)
            \App\Services\Image::stripJpegExifIfPossible($targetPath);

            $maxDim = 2560;
            $finalMime = $mime;

            if ($canGD && $mime !== 'image/gif') {
                [$w,$h] = @\getimagesize($targetPath) ?: [0,0];
                // Hedef boyut: büyükse küçült, küçükse aynı kalsın → ama yine de yeniden kaydet/encode et
                $ratio = ($w && $h) ? min($maxDim / max(1,$w), $maxDim / max(1,$h)) : 1.0;
                $nw = ($w && $h) ? max(1, (int)floor($w * min(1.0,$ratio))) : 0;
                $nh = ($w && $h) ? max(1, (int)floor($h * min(1.0,$ratio))) : 0;

                $src = \imagecreatefromstring(@file_get_contents($targetPath));
                if ($src && $nw && $nh) {
                    $dst = \imagecreatetruecolor($nw, $nh);
                    \imagealphablending($dst, false);
                    \imagesavealpha($dst, true);
                    \imagecopyresampled($dst, $src, 0,0,0,0, $nw,$nh, $w,$h);

                    if ($preferWebp) {
                        // Her durumda WEBP'ye encode et
                        \imagewebp($dst, $targetPath, defined('MEDYA_WEBP_KALITE') ? MEDYA_WEBP_KALITE : 82);
                        $finalMime = 'image/webp';
                    } else {
                        // Eski davranış
                        if ($mime === 'image/jpeg') {
                            \imagejpeg($dst, $targetPath, 82);
                        } elseif ($mime === 'image/png') {
                            \imagepng($dst, $targetPath, 6);
                        } elseif (\function_exists('imagewebp')) {
                            \imagewebp($dst, $targetPath, 82);
                            $finalMime = 'image/webp';
                        } else {
                            \imagepng($dst, $targetPath, 6);
                        }
                    }
                    \imagedestroy($dst);
                    \imagedestroy($src);
                } else {
                    // Ölçüler okunamadıysa en azından WEBP re-encode dene
                    if ($preferWebp) {
                        $src = \imagecreatefromstring(@file_get_contents($targetPath));
                        if ($src) {
                            \imagewebp($src, $targetPath, defined('MEDYA_WEBP_KALITE') ? MEDYA_WEBP_KALITE : 82);
                            \imagedestroy($src);
                            $finalMime = 'image/webp';
                        }
                    }
                }
            }

            // Thumbnail (GIF hariç)
            $thumbName = $filename; // aynı ad, /thumbs/ altında
            $thumbPath = $dirThumbs . '/' . $thumbName;
            $yol       = '/uploads/editor/' . $filename;
            $yolThumb  = null;

            if ($canGD && $mime !== 'image/gif') {
                [$w,$h] = @\getimagesize($targetPath) ?: [0,0];
                $tSize = 320;
                if ($w && $h) {
                    $ratio = min($tSize / $w, $tSize / $h);
                    $tw = max(1, (int)floor($w * $ratio));
                    $th = max(1, (int)floor($h * $ratio));
                    $src = \imagecreatefromstring(@file_get_contents($targetPath));
                    if ($src) {
                        $dst = \imagecreatetruecolor($tw, $th);
                        \imagealphablending($dst, false);
                        \imagesavealpha($dst, true);
                        \imagecopyresampled($dst, $src, 0,0,0,0, $tw,$th, $w,$h);

                        if ($preferWebp) {
                            \imagewebp($dst, $thumbPath, defined('MEDYA_WEBP_KALITE') ? MEDYA_WEBP_KALITE : 82);
                        } else {
                            if ($mime === 'image/jpeg')       \imagejpeg($dst, $thumbPath, 82);
                            elseif ($mime === 'image/png')    \imagepng($dst, $thumbPath, 6);
                            elseif (\function_exists('imagewebp')) \imagewebp($dst, $thumbPath, 82);
                            else                                \imagepng($dst, $thumbPath, 6);
                        }

                        \imagedestroy($dst);
                        \imagedestroy($src);
                        $yolThumb = '/uploads/editor/thumbs/' . $thumbName;
                    }
                }
            }

            // Meta
            $boyut = (int)@filesize($targetPath);
            $hash  = @sha1_file($targetPath) ?: null;
            [$W,$H] = @\getimagesize($targetPath) ?: [null,null];

            // --- ÇAKIŞMA KONTROLÜ: aynı yol veya aynı hash varsa uyar ve diski geri al ---
            $pdo = \App\Core\DB::pdo();
            $chk = $pdo->prepare("SELECT id FROM medya WHERE yol = :y OR (hash IS NOT NULL AND hash = :h) LIMIT 1");
            $chk->execute([':y' => $yol, ':h' => $hash]);
            $dupeId = (int)$chk->fetchColumn();
            if ($dupeId > 0) {
                // Diske yazılmış dosyaları geri sil (sessiz)
                if (is_file($targetPath)) @unlink($targetPath);
                if (!empty($thumbPath) && is_file($thumbPath)) @unlink($thumbPath);

                http_response_code(409);
                echo json_encode([
                    'ok'    => false,
                    'kod'   => 'ZATEN_VAR',
                    'mesaj' => 'Aynı dosya adı veya aynı içerik zaten kayıtlı.',
                    'id'    => $dupeId
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            // --- /ÇAKIŞMA KONTROLÜ ---

            // DB kaydı
            $sql = "INSERT INTO medya (yol, yol_thumb, mime, boyut, hash, genislik, yukseklik, created_at)
                    VALUES (:yol, :yol_thumb, :mime, :boyut, :hash, :w, :h, NOW())
                    ON DUPLICATE KEY UPDATE
                      yol       = VALUES(yol),         -- <<< yeni dosya adıyla yol güncellensin
                      yol_thumb = VALUES(yol_thumb),
                      mime      = VALUES(mime),
                      boyut     = VALUES(boyut),
                      genislik  = VALUES(genislik),
                      yukseklik = VALUES(yukseklik),
                      created_at = NOW()               -- <<< en üste çıksın
            ";
            $st = \App\Core\DB::pdo()->prepare($sql);
            $st->execute([
                ':yol'       => $yol,
                ':yol_thumb' => $yolThumb,
                ':mime'      => $finalMime, // <<<< BURASI
                ':boyut'     => $boyut,
                ':hash'      => $hash,
                ':w'         => $W,
                ':h'         => $H,
            ]);

            // INSERT sonrası id (duplicate'te lastInsertId() 0 olabilir)
            $mid = (int)\App\Core\DB::pdo()->lastInsertId();
            if ($mid <= 0) {
                // Mevcut kaydı çek: hash veya yol ile
                $sel = \App\Core\DB::pdo()->prepare("SELECT id FROM medya WHERE hash = :hash OR yol = :yol ORDER BY id DESC LIMIT 1");
                $sel->execute([':hash' => $hash, ':yol' => $yol]);
                $mid = (int)$sel->fetchColumn();
            }

            // Tek ve sağlam JSON cevap (id dahil)
            echo json_encode([
                'ok'       => true,
                'id'       => $mid,
                'location' => $yol,
                'thumb'    => $yolThumb,
                'gd'       => $canGD
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'kod'=>'UPLOAD_ERR','mesaj'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    private function csrfYoksa403(): void
    {
        if (!\App\Core\Csrf::check()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            while (ob_get_level()) { ob_end_clean(); }
            echo json_encode(['ok'=>false,'kod'=>'CSRF_RED','mesaj'=>'Güvenlik doğrulaması başarısız.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    private function jerr(int $code, string $tag, string $msg)
    {
        return $this->json(['ok' => false, 'where' => $tag, 'message' => $msg], $code);
    }

    // POST /admin/medya/thumb-fix — Eksik küçük görselleri üret
    public function thumbFix(): void
    {
        // CSRF
        if (!\App\Core\Csrf::check()) {
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            $_SESSION['hata'] = 'Güvenlik doğrulaması başarısız.';
            header('Location: '.BASE_URL.'/admin/medya'); return;
        }

        // GD zorunlu
        $canGD = \extension_loaded('gd') && \function_exists('imagecreatetruecolor') && \function_exists('imagecreatefromstring');
        if (!$canGD) {
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            $_SESSION['hata'] = 'GD kapalı olduğu için küçük görseller üretilemedi.';
            header('Location: '.BASE_URL.'/admin/medya'); return;
        }

        $pdo  = DB::pdo();
        $root = dirname(__DIR__, 2) . '/public';

        // Eksik thumb olanlardan mak. 200 kayıt
        $st = $pdo->query("
            SELECT id, yol, yol_thumb, mime
            FROM medya
            WHERE (yol_thumb IS NULL OR yol_thumb = '')
              AND mime IN ('image/jpeg','image/png','image/webp')
            ORDER BY id DESC
            LIMIT 200
        ");
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $done = 0; $skip = 0;
        foreach ($rows as $r) {
            $srcAbs = $root . $r['yol'];
            if (!is_file($srcAbs)) { $skip++; continue; }

            [$w,$h] = @\getimagesize($srcAbs) ?: [0,0];
            if (!$w || !$h) { $skip++; continue; }

            $tSize = 320;
            $ratio = min($tSize / $w, $tSize / $h);
            $tw = max(1, (int)floor($w * $ratio));
            $th = max(1, (int)floor($h * $ratio));

            $src = @\imagecreatefromstring(@file_get_contents($srcAbs));
            if (!$src) { $skip++; continue; }

            $dst = \imagecreatetruecolor($tw, $th);
            \imagealphablending($dst, false);
            \imagesavealpha($dst, true);
            \imagecopyresampled($dst, $src, 0,0,0,0, $tw,$th, $w,$h);

            $thumbRel = preg_replace('~^/uploads/editor/~', '/uploads/editor/thumbs/', $r['yol']);
            $thumbAbs = $root . $thumbRel;
            if (!is_dir(dirname($thumbAbs))) @mkdir(dirname($thumbAbs), 0775, true);

            if ($r['mime']==='image/jpeg')      \imagejpeg($dst, $thumbAbs, 82);
            elseif ($r['mime']==='image/png')   \imagepng($dst, $thumbAbs, 6);
            else                                \imagewebp($dst, $thumbAbs, 82);

            \imagedestroy($dst); \imagedestroy($src);

            $upd = $pdo->prepare("UPDATE medya SET yol_thumb=:t WHERE id=:id");
            $upd->execute([':t'=>$thumbRel, ':id'=>$r['id']]);
            $done++;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['mesaj'] = "{$done} küçük görsel üretildi, {$skip} kayıt atlandı.";
        header('Location: '.BASE_URL.'/admin/medya');
    }

    // === API: liste/arama
    public function apiListe() /* no : void */ 
    {
        $pdo = \App\Core\DB::pdo();
        $q    = trim($_GET['q']   ?? '');
        $tagsCsv = trim($_GET['tags'] ?? '');
        $mode    = (($_GET['mode'] ?? 'any') === 'all') ? 'all' : 'any';
        $tag     = trim($_GET['tag'] ?? ''); // geriye dönük uyumluluk
        $sayfa = max(1, (int)($_GET['sayfa'] ?? 1));
        $limit = min(100, max(12, (int)($_GET['limit'] ?? 36)));
        $ofset = ($sayfa - 1) * $limit;

        $join  = '';
        $where = [];
        $param = [];
        $group = '';

        if ($q !== '') {
            $where[] = '(m.yol LIKE :q OR m.mime LIKE :q)';
            $param[':q'] = "%{$q}%";
        }
        
        // Çoklu etiket desteği (tags=csv) + geri uyumluluk (tag)
        $slugs = [];
        if ($tagsCsv !== '') {
            foreach (array_filter(array_map('trim', explode(',', $tagsCsv))) as $s) {
                if ($s !== '') $slugs[] = $s;
            }
        }
        if ($tag !== '') { $slugs[] = $tag; }
        $slugs = array_values(array_unique($slugs));

        if (!empty($slugs)) {
            if ($mode === 'all') {
                // Tümü: JOIN + HAVING COUNT(DISTINCT) = N
                $join .= ' JOIN medya_etiket mef ON mef.medya_id = m.id
                           JOIN etiketler ef ON ef.id = mef.etiket_id ';
                $inPh = [];
                foreach ($slugs as $i => $slug) {
                    $ph = ":t{$i}";
                    $inPh[] = $ph;
                    $param[$ph] = $slug;
                }
                $where[] = 'ef.slug IN ('.implode(',', $inPh).')';
                $group   = ' GROUP BY m.id HAVING COUNT(DISTINCT ef.slug) = '.count($slugs).' ';
            } else {
                // En az biri: alt sorgu
                $inPh = [];
                foreach ($slugs as $i => $slug) {
                    $ph = ":t{$i}";
                    $inPh[] = $ph;
                    $param[$ph] = $slug;
                }
                $where[] = 'm.id IN (
                    SELECT me2.medya_id
                    FROM medya_etiket me2
                    JOIN etiketler e2 ON e2.id = me2.etiket_id
                    WHERE e2.slug IN ('.implode(',', $inPh).')
                )';
            }
        }

        $wsql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
        $sql  = "SELECT m.id, m.yol, m.yol_thumb, m.mime, m.boyut, m.genislik, m.yukseklik, m.created_at
                 FROM medya m
                 {$join}
                 {$wsql}
                 {$group}
                 ORDER BY m.created_at DESC
                 LIMIT :limit OFFSET :ofset";
        $stmt = $pdo->prepare($sql);
        foreach ($param as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':ofset', $ofset, \PDO::PARAM_INT);
        $stmt->execute();
        $kayitlar = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $etiketStmt = $pdo->prepare("
            SELECT e.slug, e.ad
            FROM medya_etiket me
            JOIN etiketler e ON e.id = me.etiket_id
            WHERE me.medya_id = :mid
            ORDER BY e.ad ASC
        ");
        foreach ($kayitlar as &$k) {
            $etiketStmt->execute([':mid' => $k['id']]);
            $k['etiketler'] = $etiketStmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        return $this->jsonOk(['kayitlar' => $kayitlar, 'sayfa' => $sayfa]);
    }

    // === API: etiket bulutu
    public function apiEtiketler()
    {
        $pdo = \App\Core\DB::pdo();
        $mid = (int)($_GET['mid'] ?? 0);
        if ($mid > 0) {
            $st = $pdo->prepare("
                SELECT e.slug, e.ad
                FROM medya_etiket me
                JOIN etiketler e ON e.id = me.etiket_id
                WHERE me.medya_id = :mid
                ORDER BY e.ad ASC
            ");
            $st->execute([':mid' => $mid]);
            return $this->jsonOk(['mid' => $mid, 'etiketler' => $st->fetchAll(\PDO::FETCH_ASSOC)]);
        }        
        $q = trim($_GET['q'] ?? '');

        $w = '';
        $p = [];
        if ($q !== '') {
            $w = 'WHERE (e.ad LIKE :q OR e.slug LIKE :q)';
            $p[':q'] = "%{$q}%";
        }

        $sql = "
            SELECT e.slug, e.ad, COUNT(me.medya_id) AS adet
            FROM etiketler e
            LEFT JOIN medya_etiket me ON me.etiket_id = e.id
            {$w}
            GROUP BY e.id, e.slug, e.ad
            ORDER BY adet DESC, e.ad ASC
            LIMIT 100
        ";
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return $this->jsonOk(['etiketler' => $st->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    // === API: etiket eşitle
    public function apiEtiketle()
    {
        $g = json_decode(file_get_contents('php://input'), true) ?: [];
        $mid = (int)($g['medya_id'] ?? 0);
        $girilen = $g['etiketler'] ?? [];

        $mod = strtolower(trim((string)($g['mod'] ?? 'replace'))); // 'append' | 'replace'
        $append = ($mod === 'append');        

        if (!$mid || (!is_array($girilen) && !is_string($girilen))) {
            return $this->jsonErr('GEÇERSİZ_VERİ');
        }
        if (is_string($girilen)) {
            $girilen = array_filter(array_map('trim', explode(',', $girilen)));
        }

        // Sadece ekleme modunda ve girilen boşsa, mevcutları koru
        if ($append && empty($girilen)) {
            $etiketSt = $pdo->prepare("
                SELECT e.slug, e.ad
                FROM medya_etiket me
                JOIN etiketler e ON e.id = me.etiket_id
                WHERE me.medya_id = :mid
                ORDER BY e.ad ASC
            ");
            $etiketSt->execute([':mid' => $mid]);
            return $this->jsonOk([
                'medya_id'  => $mid,
                'etiketler' => $etiketSt->fetchAll(\PDO::FETCH_ASSOC),
            ]);
        }        

        $etiketler = [];
        foreach ($girilen as $ad) {
            if ($ad === '' || mb_strlen($ad) > 100) continue;
            $slug = $this->slugEtiket($ad);
            if ($slug === '') continue;
            $etiketler[$slug] = $ad;
        }

        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            $chk = $pdo->prepare("SELECT id FROM medya WHERE id = :id");
            $chk->execute([':id' => $mid]);
            if (!$chk->fetch()) throw new \RuntimeException('MEDYA_YOK');

            $getId = $pdo->prepare("SELECT id FROM etiketler WHERE slug = :slug");
            $ins   = $pdo->prepare("INSERT INTO etiketler (slug, ad) VALUES (:slug, :ad)");

            $yeniIds = [];
            foreach ($etiketler as $slug => $ad) {
                $getId->execute([':slug' => $slug]);
                $id = $getId->fetchColumn();
                if (!$id) {
                    $ins->execute([':slug' => $slug, ':ad' => $ad]);
                    $id = (int)$pdo->lastInsertId();
                }
                $yeniIds[] = (int)$id;
            }

            $mevcut = $pdo->prepare("SELECT etiket_id FROM medya_etiket WHERE medya_id = :mid");
            $mevcut->execute([':mid' => $mid]);
            $mevcutIds = array_map('intval', array_column($mevcut->fetchAll(\PDO::FETCH_ASSOC), 'etiket_id'));

            if ($append) {
                // Ekle modunda SİLME YOK → sadece yeni ilişkileri ekle
                $silinecek = [];
                $eklenecek = array_diff($yeniIds, $mevcutIds);
            } else {
                // Eşitle (replace) modunda tam eşitle
                $silinecek = array_diff($mevcutIds, $yeniIds);
                $eklenecek = array_diff($yeniIds, $mevcutIds);
            }

            if (!empty($silinecek)) {
                $in = implode(',', array_fill(0, count($silinecek), '?'));
                $del = $pdo->prepare("DELETE FROM medya_etiket WHERE medya_id = ? AND etiket_id IN ($in)");
                $del->execute(array_merge([$mid], array_values($silinecek)));
            }

            if ($eklenecek) {
                $insMe = $pdo->prepare("INSERT IGNORE INTO medya_etiket (medya_id, etiket_id) VALUES (:mid, :eid)");
                foreach ($eklenecek as $eid) {
                    $insMe->execute([':mid' => $mid, ':eid' => $eid]);
                }
            }

            $pdo->commit();

            $etiketSt = $pdo->prepare("
                SELECT e.slug, e.ad
                FROM medya_etiket me
                JOIN etiketler e ON e.id = me.etiket_id
                WHERE me.medya_id = :mid
                ORDER BY e.ad ASC
            ");
            $etiketSt->execute([':mid' => $mid]);

            return $this->jsonOk([
                'medya_id'  => $mid,
                'etiketler' => $etiketSt->fetchAll(\PDO::FETCH_ASSOC),
            ]);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return $this->jsonErr($e->getMessage());
        }
    }

    /** Basit slug üretici — projedeki helper varsa onu kullan */
    private function slugEtiket(string $ad): string
    {
        // Eğer global slugify() mevcutsa onu kullan
        if (function_exists('slugify')) {
            return slugify($ad);
        }
        // Fallback (TR karakter haritası basit)
        $tr = ['ç','ğ','ı','ö','ş','ü','Ç','Ğ','İ','Ö','Ş','Ü'];
        $en = ['c','g','i','o','s','u','C','G','I','O','S','U'];
        $s = str_replace($tr, $en, $ad);
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('~[^a-z0-9]+~u', '-', $s);
        $s = trim($s, '-');
        return substr($s, 0, 64);
    }

    // === API: meta (GET) — alt_text ve title'ı getir
    public function apiMetaGet() /* no : void */
    {
        try {
            $pdo = \App\Core\DB::pdo();
            $mid = (int)($_GET['mid'] ?? 0);
            if ($mid <= 0) return $this->jsonErr('GEÇERSİZ_ID');

            $st = $pdo->prepare("SELECT id, alt_text, title FROM medya WHERE id = :id");
            $st->execute([':id' => $mid]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);

            if (!$row) return $this->jsonErr('BULUNAMADI');
            return $this->jsonOk(['medya' => $row]);
        } catch (\Throwable $e) {
            return $this->jsonErr('META_GET_HATA', ['hata' => $e->getMessage()]);
        }
    }

    // === API: meta (POST) — alt_text ve title'ı güncelle
    public function apiMetaGuncelle() /* no : void */
    {
        try {
            $pdo = \App\Core\DB::pdo();

            // JSON ya da form-data kabul et
            $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
            $raw = file_get_contents('php://input');
            $in  = (stripos($ct, 'application/json') !== false && $raw)
                 ? (json_decode($raw, true) ?: [])
                 : $_POST;

            $id    = (int)($in['medya_id'] ?? 0);
            $alt   = isset($in['alt_text']) ? trim((string)$in['alt_text']) : '';
            $title = isset($in['title'])    ? trim((string)$in['title'])    : '';
            $yeniAdRaw = isset($in['yeni_ad']) ? trim((string)$in['yeni_ad']) : '';

            if ($id <= 0) return $this->jsonErr('GEÇERSİZ_ID');

            // Uzunluk ve NULL normalizasyonu
            $alt   = ($alt === '')   ? null : mb_substr($alt,   0, 255);
            $title = ($title === '') ? null : mb_substr($title, 0, 150);

            $st = $pdo->prepare("UPDATE medya SET alt_text = :alt, title = :title WHERE id = :id");
            $st->bindValue(':alt',   $alt,   is_null($alt)   ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
            $st->bindValue(':title', $title, is_null($title) ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
            $st->bindValue(':id',    $id, \PDO::PARAM_INT);
            $st->execute();

            // ---- Opsiyonel: dosya adını değiştir ----
            $newYol = null;
            $newThumb = null;

            if ($yeniAdRaw !== '') {
                // 1) Slug üret (TR → ASCII, boşluk→tire)
                $map = ['ş'=>'s','Ş'=>'s','ı'=>'i','İ'=>'i','ç'=>'c','Ç'=>'c','ğ'=>'g','Ğ'=>'g','ü'=>'u','Ü'=>'u','ö'=>'o','Ö'=>'o'];
                $y = strtr($yeniAdRaw, $map);
                $y = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$y);
                $y = strtolower($y);
                $y = preg_replace('~[^a-z0-9]+~','-',$y);
                $y = trim($y, '-');
                if ($y === '') return $this->jsonErr('GEÇERSİZ_AD');

                // 2) Mevcut kayıt
                $q = $pdo->prepare("SELECT yol, yol_thumb FROM medya WHERE id = :id");
                $q->execute([':id' => $id]);
                $row = $q->fetch(\PDO::FETCH_ASSOC);
                if (!$row) return $this->jsonErr('BULUNAMADI');

                $rootPublic = realpath(dirname(__DIR__,2).'/public');

                // Ana dosyanın göreli yolu (uploads/… kısmı)
                $relMain = ltrim((string)$row['yol'], '/');
                $absMain = $rootPublic . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relMain);
                $dirMain = dirname($relMain);
                $extMain = pathinfo($relMain, PATHINFO_EXTENSION);

                // Thumb (varsa)
                $relTh   = $row['yol_thumb'] ? ltrim((string)$row['yol_thumb'], '/') : null;
                $absTh   = $relTh ? ($rootPublic . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relTh)) : null;
                $dirTh   = $relTh ? dirname($relTh) : null;
                $extTh   = $relTh ? pathinfo($relTh, PATHINFO_EXTENSION) : null;

                // 3) Benzersiz aday isim oluştur (DB + FS çakışması kontrol)
                $candidate = $y; $i = 1;
                $existsInDb = function($path) use ($pdo, $id) {
                    $s = $pdo->prepare("SELECT COUNT(1) FROM medya WHERE yol = :y AND id <> :id");
                    $s->execute([':y'=>$path, ':id'=>$id]);
                    return (int)$s->fetchColumn() > 0;
                };

                do {
                    $newMainRel = $dirMain . '/' . $candidate . '.' . $extMain;
                    $newMainAbs = $rootPublic . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $newMainRel);
                    $newMainDb  = '/' . $newMainRel;

                    $fsOk = !file_exists($newMainAbs);
                    $dbOk = !$existsInDb($newMainDb);
                    if ($fsOk && $dbOk) break;

                    $candidate = $y . '-' . (++$i);
                } while (true);

                if (!@rename($absMain, $newMainAbs)) {
                    return $this->jsonErr('DOSYA_TASINAMADI');
                }

                // Thumb varsa aynı ada taşı
                if ($absTh && $dirTh && $extTh) {
                    $newThRel = $dirTh . '/' . $candidate . '.' . $extTh;
                    $newThAbs = $rootPublic . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $newThRel);
                    @rename($absTh, $newThAbs);
                    $newThumb = '/' . $newThRel;
                }

                // DB güncelle
                $pdo->prepare("UPDATE medya SET yol = :y, yol_thumb = :t WHERE id = :id")
                    ->execute([
                        ':y'  => $newMainDb,
                        ':t'  => $newThumb,
                        ':id' => $id
                    ]);

                $newYol   = $newMainDb;
            }

            // İstemci yeni yolu kullanabilsin diye döndür
            return $this->jsonOk(['ok' => true, 'yol' => $newYol]);            

        } catch (\Throwable $e) {
            return $this->jsonErr('META_KAYIT_HATA', ['hata' => $e->getMessage()]);
        }
    }

}
