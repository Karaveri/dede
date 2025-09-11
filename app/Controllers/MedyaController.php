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
        $q      = trim($_GET['q'] ?? '');
        $sayfa  = max(1, (int)($_GET['s'] ?? 1));
        $limit  = 24;
        $ofset  = ($sayfa - 1) * $limit;

        // Arama filtresi
        $where  = '';
        $params = [];
        if ($q !== '') {
            $where = 'WHERE yol LIKE :q OR mime LIKE :q';
            $params[':q'] = '%'.$q.'%';
        }

        // Sayı
        $st = DB::pdo()->prepare("SELECT COUNT(*) FROM medya $where");
        $st->execute($params);
        $toplam = (int)$st->fetchColumn();
        $sayfaSayisi = max(1, (int)ceil($toplam / $limit));

        // Liste
        $sql = "SELECT id, yol, yol_thumb, mime, genislik, yukseklik
                FROM medya $where
                ORDER BY id DESC
                LIMIT :l OFFSET :o";
        $st  = DB::pdo()->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v, PDO::PARAM_STR);
        $st->bindValue(':l', $limit, PDO::PARAM_INT);
        $st->bindValue(':o', $ofset, PDO::PARAM_INT);
        $st->execute();
        $medyalar = $st->fetchAll(PDO::FETCH_ASSOC);

        // >>> Layout otomatik (admin/sablon.php)
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

            // DB kaydı
            $sql = "INSERT INTO medya (yol, yol_thumb, mime, boyut, hash, genislik, yukseklik, created_at)
                    VALUES (:yol, :yol_thumb, :mime, :boyut, :hash, :w, :h, NOW())
                    ON DUPLICATE KEY UPDATE
                      yol_thumb = VALUES(yol_thumb),
                      mime      = VALUES(mime),
                      boyut     = VALUES(boyut),
                      genislik  = VALUES(genislik),
                      yukseklik = VALUES(yukseklik)";
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

            // TinyMCE "location" bekliyor
            echo json_encode(['ok'=>true, 'location'=> $yol, 'thumb' => $yolThumb, 'gd'=>$canGD], JSON_UNESCAPED_UNICODE);
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

}
