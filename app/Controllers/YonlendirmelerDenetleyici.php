<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;

class YonlendirmelerDenetleyici extends AdminController
{
	protected $db;   // <-- ekle
    protected array $roller = ['admin','editor','yazar'];

    public function __construct()
    {
        parent::__construct();
        // Base Controller'da constructor olmayabilir; burada güvenli şekilde $this->db'yi kur.
        if (!isset($this->db) || $this->db === null) {
            // 1) \App\Core\Database::baglanti() varsa onu kullan
            if (class_exists('\App\Core\Database') && method_exists('\App\Core\Database', 'baglanti')) {
                $this->db = \App\Core\DB::pdo();
            } else {
                // 2) \App\Core\DB varsa yaygın metot adlarını sırayla dene
                $dbClass = '\App\Core\DB';
                if (class_exists($dbClass)) {
                    foreach (['baglanti', 'get', 'connect', 'connection'] as $m) {
                        if (method_exists($dbClass, $m)) {
                            $this->db = $dbClass::$m();
                            break;
                        }
                    }
                }
            }
        }

        if (!$this->db) {
            // Buraya düşüyorsa çekirdek DB sınıf/metodu adı farklıdır.
            // Çekirdek DB sınıfındaki gerçek static metot adını bu sınıfa uyarlamak gerekir.
            throw new \RuntimeException('Veritabanı bağlantısı kurulamadı: Çekirdek DB sınıf/metodu bulunamadı.');
        }
    }

    // GET /admin/yonlendirmeler
    public function index(): string
    {
        $q      = trim($_GET['q'] ?? '');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        if ($q !== '') {
            $sql = "FROM yonlendirmeler WHERE kaynak LIKE :q OR hedef LIKE :q";
            $cnt = $this->db->prepare("SELECT COUNT(*) ".$sql);
            $cnt->execute([':q' => '%'.$q.'%']);
            $total = (int)$cnt->fetchColumn();

            $st = $this->db->prepare("SELECT id, kaynak, hedef, tip, aktif, olusturuldu ".$sql." ORDER BY id DESC LIMIT :lim OFFSET :off");
            $st->bindValue(':q', '%'.$q.'%', \PDO::PARAM_STR);
            $st->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $st->bindValue(':off', $offset, \PDO::PARAM_INT);
            $st->execute();
        } else {
            $total = (int)$this->db->query("SELECT COUNT(*) FROM yonlendirmeler")->fetchColumn();
            $st = $this->db->prepare("SELECT id, kaynak, hedef, tip, aktif, olusturuldu FROM yonlendirmeler ORDER BY id DESC LIMIT :lim OFFSET :off");
            $st->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $st->bindValue(':off', $offset, \PDO::PARAM_INT);
            $st->execute();
        }

        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        $pages = max(1, (int)ceil($total / $limit));

        return $this->view('admin/yonlendirmeler/liste', [
            'baslik' => 'Yönlendirmeler',
            'liste'  => $rows,
            'q'      => $q,
            'page'   => $page,
            'pages'  => $pages,
            'limit'  => $limit,
            'total'  => $total,
        ]);
    }

    // POST /admin/yonlendirmeler/durum  (aktif/pasif toggle)
    public function durum(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? null);
            if (!$token || !Csrf::dogrula($token)) {
                echo json_encode(['ok' => false, 'mesaj' => 'CSRF doğrulaması başarısız.']); return;
            }

            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { echo json_encode(['ok' => false, 'mesaj' => 'Geçersiz id']); return; }

            $q = $this->db->prepare("SELECT aktif FROM yonlendirmeler WHERE id = :id LIMIT 1");
            $q->execute([':id' => $id]);
            $aktif = $q->fetchColumn();
            if ($aktif === false) { echo json_encode(['ok' => false, 'mesaj' => 'Kayıt bulunamadı']); return; }

            $yeni = ((int)$aktif === 1) ? 0 : 1;

            $u = $this->db->prepare("UPDATE yonlendirmeler SET aktif = :a WHERE id = :id");
            $ok = $u->execute([':a' => $yeni, ':id' => $id]);

            echo json_encode(['ok' => $ok, 'durum' => $yeni, 'ids' => [$id]]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'mesaj' => 'Sunucu hatası', 'detay' => $e->getMessage()]);
        }
    }

    // POST /admin/yonlendirmeler/sil  (kalıcı sil)
    public function sil(): void
    {
        try {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? null);
            if (!Csrf::dogrula($token)) {
                $_SESSION['hata'] = 'Güvenlik doğrulaması başarısız.';
                header('Location: ' . BASE_URL . '/admin/yonlendirmeler'); exit;
            }

            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $_SESSION['hata'] = 'Geçersiz id';
                header('Location: ' . BASE_URL . '/admin/yonlendirmeler'); exit;
            }

            $d = $this->db->prepare("DELETE FROM yonlendirmeler WHERE id = :id");
            $ok = $d->execute([':id' => $id]);

            $_SESSION[$ok ? 'mesaj' : 'hata'] = $ok ? 'Yönlendirme silindi.' : 'Silme hatası.';
            header('Location: ' . BASE_URL . '/admin/yonlendirmeler'); exit;
        } catch (\Throwable $e) {
            $_SESSION['hata'] = 'Sunucu hatası: ' . $e->getMessage();
            header('Location: ' . BASE_URL . '/admin/yonlendirmeler'); exit;
        }
    }

    public function topluSil(): void
    {
        try {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? null);
            if (!Csrf::dogrula($token)) {
                $_SESSION['hata'] = 'Güvenlik doğrulaması başarısız.';
                header('Location: ' . BASE_URL . '/admin/yonlendirmeler'); exit;
            }

            $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
            if (empty($ids)) {
                $_SESSION['hata'] = 'Seçim yapmadınız.';
                header('Location: ' . BASE_URL . '/admin/yonlendirmeler'); exit;
            }

            // IN listesi
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $st  = $this->db->prepare("DELETE FROM yonlendirmeler WHERE id IN ($in)");
            $ok  = $st->execute($ids);

            $_SESSION[$ok ? 'mesaj' : 'hata'] = $ok ? 'Seçili yönlendirmeler silindi.' : 'Silme hatası.';
            header('Location: ' . BASE_URL . '/admin/yonlendirmeler'); exit;
        } catch (\Throwable $e) {
            $_SESSION['hata'] = 'Sunucu hatası: '.$e->getMessage();
            header('Location: ' . BASE_URL . '/admin/yonlendirmeler'); exit;
        }
    }
    
}
