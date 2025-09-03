<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;          // << EKLE
use App\Core\Csrf;
use PDO;

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
        $sql = "SELECT id, yol, mime, genislik, yukseklik
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

        $s = DB::pdo()->prepare('SELECT yol FROM medya WHERE id = ? LIMIT 1');
        $s->execute([$id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            DB::pdo()->prepare('DELETE FROM medya WHERE id = ?')->execute([$id]);
            $abs = dirname(__DIR__, 2) . '/public' . $row['yol'];
            if (is_file($abs)) @unlink($abs);
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
        $s   = DB::pdo()->prepare("SELECT id, yol FROM medya WHERE id IN ($in)");
        $s->execute($ids);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);

        $del = DB::pdo()->prepare("DELETE FROM medya WHERE id IN ($in)");
        $del->execute($ids);

        $base = dirname(__DIR__, 2) . '/public';
        foreach ($rows as $r) {
            $abs = $base . $r['yol'];
            if (is_file($abs)) @unlink($abs);
        }
        $_SESSION['mesaj'] = count($rows).' görsel silindi.';
        header('Location: '.BASE_URL.'/admin/medya');
    }

    // POST /admin/medya/yukle — TinyMCE upload (DB kaydı ile)
    public function upload(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'method_not_allowed']); return;
            }
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();

            // CSRF (header + post + cookie), OTURUMDAKİ değerle karşılaştır
            $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            $postToken   = $_POST['csrf'] ?? $_POST['csrf_token'] ?? '';
            $cookieToken = $_COOKIE['XSRF-TOKEN'] ?? $_COOKIE['csrf_token'] ?? '';
            $incoming    = $headerToken ?: $postToken ?: $cookieToken;
            $sessionTok  = $_SESSION['csrf'] ?? $_SESSION['csrf_token'] ?? '';
            if (!$incoming || !$sessionTok || !hash_equals((string)$sessionTok, (string)$incoming)) {
                http_response_code(403);
                echo json_encode(['error' => 'csrf']); return;
            }

            if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'no_file']); return;
            }

            $f   = $_FILES['file'];
            $max = 8 * 1024 * 1024;
            if (($f['size'] ?? 0) <= 0 || $f['size'] > $max) {
                http_response_code(413);
                echo json_encode(['error' => 'file_too_large']); return;
            }

            // MIME
            $fi   = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fi, $f['tmp_name']);
            finfo_close($fi);
            $allow = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
            if (!isset($allow[$mime])) {
                http_response_code(415);
                echo json_encode(['error' => 'unsupported_type', 'mime' => $mime]); return;
            }

            // Güvenli dosya adı
            $nameOnly = pathinfo($f['name'], PATHINFO_FILENAME);
            $nameOnly = preg_replace('~[^a-z0-9]+~i', '-', $nameOnly);
            $nameOnly = trim($nameOnly, '-') ?: 'image';
            $ext      = $allow[$mime];
            $uniq     = bin2hex(random_bytes(3));
            $filename = $nameOnly.'-'.date('Ymd-His').'-'.$uniq.'.'.$ext;

            // Kaydet
            $publicDir = dirname(__DIR__, 2) . '/public';
            $targetDir = $publicDir . '/uploads/editor';
            if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) {
                throw new Exception('upload_dir_unwritable');
            }
            if (!is_writable($targetDir)) {
                throw new Exception('upload_dir_unwritable');
            }
            $targetPath = $targetDir . '/' . $filename;
            if (!move_uploaded_file($f['tmp_name'], $targetPath)) {
                throw new Exception('move_failed');
            }

            // URL ve DB kaydı
            $yol = '/uploads/editor/'.$filename;
            $boyut = (int)@filesize($targetPath);
            $hash  = @hash_file('sha256', $targetPath) ?: '';
            $w = null; $h = null;
            if (($info = @getimagesize($targetPath))) { $w = $info[0] ?? null; $h = $info[1] ?? null; }

            $ins = DB::pdo()->prepare("
                INSERT INTO medya (yol, mime, boyut, hash, genislik, yukseklik, created_at)
                VALUES (:yol, :mime, :boyut, :hash, :w, :h, NOW())
                ON DUPLICATE KEY UPDATE
                  mime = VALUES(mime),
                  boyut = VALUES(boyut),
                  hash = VALUES(hash),
                  genislik = VALUES(genislik),
                  yukseklik = VALUES(yukseklik)
            ");
            $ins->execute([
                ':yol'  => $yol,
                ':mime' => $mime,
                ':boyut'=> $boyut,
                ':hash' => $hash,
                ':w'    => $w,
                ':h'    => $h,
            ]);

            $baseUrl = defined('BASE_URL')
                ? rtrim(BASE_URL, '/')
                : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                   . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

            $ver = filemtime($targetPath) ?: time();
            echo json_encode(['location' => $baseUrl.$yol.'?v='.$ver], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
        }
    }

    private function csrfYoksa403(): void
    {
        if (!\App\Core\Csrf::check()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>false,'kod'=>'CSRF_RED','mesaj'=>'Güvenlik doğrulaması başarısız.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    private function json(array $data, int $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return null;
    }

    private function jerr(int $code, string $tag, string $msg)
    {
        return $this->json(['ok' => false, 'where' => $tag, 'message' => $msg], $code);
    }
}
