<?php
namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Auth;
use App\Core\Router;
use App\Core\View;
use App\Core\Controller;          // <<< eklendi
use App\Models\KategoriModel;
use \App\Traits\DurumTrait;
use App\Helpers\Validator;
use App\Core\DB;
use function \slugify;
use function \slug_benzersiz;
use function \slug_guvenli;

class KategorilerDenetleyici extends AdminController
{

    protected $model;
    protected array $roller = ['admin','editor','yazar']; // (İstersen bırakma, varsayılan zaten böyle)

    public function __construct()
    {
        parent::__construct(); // Auth/rol kontrolü tek noktada
        $this->model = new \App\Models\KategoriModel();
    }

    // GET /admin/kategoriler?q=&p=&g=agac
    public function index()
    {
        Auth::zorunluRol(['admin','editor','yazar']);
        // CSRF (layout'taki <meta name="csrf-token"> için tetikleme)
        Csrf::token();

        $q     = trim($_GET['q'] ?? '');
        $p     = max(1, (int)($_GET['p'] ?? 1));
        $limit = 20;

        // Modelden veri
        [$kategoriler, $toplam, $sayfa, $lim] = $this->model->listele($q, $p, $limit);

        // — ÜST KATEGORİ AD HARİTASI (id => ad) —
        // (listele() bazen 'parent_id' bazen 'ust_id' döndürebilir; ikisini de oku)
        $parentIdsSet = [];
        foreach ($kategoriler as $r) {
            $pid = 0;
            if (array_key_exists('parent_id', $r)) {
                $pid = (int)$r['parent_id'];
            } elseif (array_key_exists('ust_id', $r)) {
                $pid = (int)$r['ust_id'];
            } elseif (array_key_exists('parent', $r)) {
                $pid = (int)$r['parent'];
            }
            if ($pid > 0) $parentIdsSet[$pid] = true;
        }

        $ustMap = [];
        if (!empty($parentIdsSet)) {
            $ids = array_keys($parentIdsSet);
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $st  = \App\Core\DB::pdo()->prepare("SELECT id, ad FROM kategoriler WHERE id IN ($in)");
            $st->execute($ids);
            $ustMap = $st->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];
        }

        // Görünüm
        return $this->view('admin/kategoriler/liste', [
            'baslik'      => 'Kategoriler',
            'kategoriler' => $kategoriler,
            'q'           => $q,
            'p'           => $sayfa,
            'limit'       => $lim,
            'toplam'      => $toplam,
            'g'           => '',        // ağaç görünümü kullanmıyorsan boş kalabilir
            'ustMap'      => $ustMap,   // <<< YENİ: görünümde üst adını yazacağız
        ]);
    }

    private function csrfYoksa403(): void
    {
        if (!\App\Core\Csrf::check()) {
            $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
            return;
        }
    }

    public function durum(): string
    {
        // CSRF
        if (!\App\Core\Csrf::check()) {
            return $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
        }

        $id = (int)($_POST['id'] ?? 0);
        $isAjax = $this->isAjax();
        if ($id <= 0) {
            if ($isAjax) return $this->jsonErr('Geçersiz id.', 'BAD_ID', [], 422);
            $this->flash('hata', 'Geçersiz id.');
            return $this->redirect('/admin/kategoriler');
        }

        // Mevcut durumu oku (kategoriler.durum = 0/1)
        $pdo = \App\Core\DB::pdo();
        $q = $pdo->prepare("SELECT durum FROM kategoriler WHERE id = :id AND silindi = 0 LIMIT 1");
        $q->execute([':id' => $id]);
        $mevcut = $q->fetchColumn();
        if ($mevcut === false) {
            if ($isAjax) return $this->jsonErr('Kayıt bulunamadı.', 'NOT_FOUND', [], 404);
            $this->flash('hata', 'Kayıt bulunamadı.');
            return $this->redirect('/admin/kategoriler');
        }

        $yeni = ((int)$mevcut === 1) ? 0 : 1;
        $u = $pdo->prepare("UPDATE kategoriler SET durum = :d, guncelleme_tarihi = NOW() WHERE id = :id");
        $ok = $u->execute([':d' => $yeni, ':id' => $id]);

        if ($isAjax) {
            return $ok
                ? $this->jsonOk(['durum' => $yeni, 'durum_str' => ($yeni ? 'aktif' : 'pasif'), 'ids' => [$id]])
                : $this->jsonErr('Güncelleme hatası.', 'DB_ERR', [], 500);
        }

        $this->flash($ok ? 'mesaj' : 'hata', $ok ? 'Durum güncellendi.' : 'Güncelleme hatası.');
        return $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/kategoriler');
    }

    public function durumToplu(): string
    {
        if (!\App\Core\Csrf::check()) {
            return $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), fn($v)=>$v>0)));
        if (!$ids) {
            return $this->jsonErr('Seçim yok.', 'NO_SELECTION', [], 422);
        }

        // '1'|'on'|'true'|'aktif' ... → 1 ; aksi → 0
        $raw = $_POST['durum'] ?? null;
        $val = is_string($raw) ? mb_strtolower(trim($raw)) : $raw;
        $aktifSet = ['1','on','true','aktif','yayinda','yayında','evet','yes'];
        $yeniInt  = (int) (in_array($val, $aktifSet, true) || $val === 1 || $val === true);

        // Model varsa kullan; yoksa doğrudan SQL
        if (isset($this->model) && method_exists($this->model, 'topluDurum')) {
            $this->model->topluDurum($ids, $yeniInt);
        } else {
            $pdo = \App\Core\DB::pdo();
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $st  = $pdo->prepare("UPDATE kategoriler SET durum = ?, guncelleme_tarihi = NOW() WHERE id IN ($in)");
            $st->execute(array_merge([$yeniInt], $ids));
        }

        return $this->jsonOk([
            'durum'     => $yeniInt,
            'durum_str' => $yeniInt ? 'aktif' : 'pasif',
            'ids'       => $ids
        ]);
    }

    public function silToplu(): string
    {
        // AJAX: her zaman JSON döner
        if (!\App\Core\Csrf::check()) {
            return $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
        }

        $ids = array_map('intval', $_POST['ids'] ?? []);
        $ids = array_values(array_filter($ids));
        if (!$ids) {
            return $this->jsonErr('Seçim yok.', 'NO_SELECTION', [], 422);
        }

        try {
            // Model adları projende farklı olabilir; ikisini de destekle
            if (method_exists($this->model, 'destroyMany')) {
                $adet = (int)$this->model->destroyMany($ids);         // soft delete
            } elseif (method_exists($this->model, 'topluSil')) {
                $adet = (int)$this->model->topluSil($ids);             // soft delete
            } else {
                // Güvenli geri dönüş: doğrudan SQL
                $pdo = \App\Core\DB::pdo();
                $in  = implode(',', array_fill(0, count($ids), '?'));
                $st  = $pdo->prepare("UPDATE kategoriler
                                      SET silindi = 1, guncelleme_tarihi = NOW()
                                      WHERE id IN ($in)");
                $st->execute($ids);
                $adet = $st->rowCount();
            }

            return $this->jsonOk(['silinen' => $adet, 'ids' => $ids]);
        } catch (\Throwable $e) {
            return $this->jsonErr('Sunucu hatası', 'SERVER_ERR', ['detay'=>[$e->getMessage()]], 500);
        }
    }

    public function durumTekil(): string
    {
        return $this->durum();
    }

    // POST /admin/kategoriler/geri-al
    public function geriAl(): string
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xreq   = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        $isAjax = str_contains($accept, 'application/json') || $xreq === 'xmlhttprequest' || $xreq === 'fetch';

        // CSRF
        if (!\App\Core\Csrf::check()) {
            if ($isAjax) return $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
            $_SESSION['hata'] = 'Güvenlik doğrulaması başarısız.';
            $this->redirect(BASE_URL . '/admin/kategoriler/cop'); // exit
        }

        // İdleri topla
        $ids = [];
        if (!empty($_POST['id']))  $ids[] = (int)$_POST['id'];
        if (!empty($_POST['ids'])) $ids = array_map('intval', (array)$_POST['ids']);
        $ids = array_values(array_unique(array_filter($ids)));

        if (!$ids) {
            if ($isAjax) return $this->jsonErr('Seçim yok.', 'NO_SELECTION', [], 422);
            $_SESSION['hata'] = 'Geri alınacak kayıt seçilmedi.';
            $this->redirect(BASE_URL . '/admin/kategoriler/cop'); // exit
        }

        try {
            $adet = $this->model->restoreMany($ids);
            if ($isAjax) return $this->jsonOk(['geri_alinan' => $adet, 'ids' => $ids]);

            $_SESSION['mesaj'] = $adet . ' kayıt geri alındı.';
            $this->redirect(BASE_URL . '/admin/kategoriler/cop'); // exit
        } catch (\Throwable $e) {
            if ($isAjax) return $this->jsonErr('Sunucu hatası', 'SERVER_ERR', ['detay'=>[$e->getMessage()]], 500);
            $_SESSION['hata'] = 'Sunucu hatası.';
            $this->redirect(BASE_URL . '/admin/kategoriler/cop'); // exit
        }

        // redirect çağrısı çıkış yaptığı için buraya normalde düşülmez
        return '';
    }

    // GET /admin/kategoriler/olustur
    public function olustur(): string
    {
        $ustKategoriler = $this->model->tumunuGetir();
        return $this->view('admin/kategoriler/form', [
            'baslik'         => 'Yeni Kategori',
            'ustKategoriler' => $ustKategoriler,
            'kategori'       => null,
        ]);
    }

    // GET /admin/kategoriler/duzenle?id=...
    public function duzenle(): string
    {
        $id = (int)($_GET['id'] ?? 0);
        $kategori = $this->model->tekGetir($id);
        if (!$kategori) { http_response_code(404); return 'Kategori bulunamadı'; }

        $ustKategoriler = $this->model->tumunuGetir();
        return $this->view('admin/kategoriler/form', [       // <<< form.php
            'baslik'         => 'Kategori Düzenle',
            'ustKategoriler' => $ustKategoriler,
            'kategori'       => $kategori,
        ]);
    }

    // POST /admin/kategoriler/kaydet
    public function kaydet(): void
    {
        if (!Csrf::dogrula($_POST['csrf'] ?? null)) {
            $_SESSION['hata'] = 'Güvenlik doğrulaması başarısız.';
            header('Location: ' . BASE_URL . '/admin/kategoriler/olustur'); exit;
        }

        $ad        = trim($_POST['ad'] ?? '');
        $slug      = trim($_POST['slug'] ?? '');
        $durum     = isset($_POST['durum']) ? 1 : 0;
        $aciklama  = trim($_POST['aciklama'] ?? '');
        $parent_id = (int)($_POST['parent_id'] ?? $_POST['ust_id'] ?? 0) ?: null;

        if ($slug === '') {
            $slug = \slugify($ad);
        }

        if ($ad === '') {
            $_SESSION['hata'] = 'Ad zorunludur.';
            header('Location: ' . BASE_URL . '/admin/kategoriler/olustur'); exit;
        }

        // SLUG — ortak politika (slug_helper.php)
        if ($slug !== '') {
            // Kullanıcının girdiği slug: normalize + benzersiz
            $slug = \slug_benzersiz(slugify($slug), 'kategoriler', 0);
        } else {
            // Ad alanından üret: normalize + benzersiz
            $slug = \slug_guvenli($ad, 'kategoriler', 0);
        }

        try {
            $this->model->ekle([
                'parent_id' => $parent_id,
                'ad'        => $ad,
                'slug'      => $slug,
                'aciklama'  => $aciklama !== '' ? $aciklama : null,
                'durum'     => $durum,
            ]);
            $_SESSION['mesaj'] = 'Kategori eklendi.';
            header('Location: ' . BASE_URL . '/admin/kategoriler'); exit;
        } catch (\Throwable $e) {
            $_SESSION['hata'] = 'Kaydetme hatası: '.$e->getMessage();
            header('Location: ' . BASE_URL . '/admin/kategoriler/olustur'); exit;
        }
    }

    // POST /admin/kategoriler/guncelle
    public function guncelle(): string
    {
        $isAjax = $this->isAjax();
        // 0) AJAX tespiti (ilk satırda!)
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xreq   = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        $isAjax = str_contains($accept, 'application/json') || $xreq === 'xmlhttprequest' || $xreq === 'fetch';

        // 1) CSRF
        if (!\App\Core\Csrf::check()) {
            if ($isAjax) {
                return $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
            }
            $this->flash('hata', 'Güvenlik doğrulaması başarısız.');
            return $this->redirect('/admin/kategoriler'); // <<< DÖN
        }

        // id>0 → UPDATE, aksi halde INSERT (POST yoksa GET’ten de dene)
        $id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
        $parent_id = $_POST['parent_id'] ?? ($_POST['ust_id'] ?? null);
        $parent_id = (int)$parent_id ?: null;

        $ad   = trim((string)($_POST['ad'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        // "ozet" de gelmiş olabilir → aciklama'ya normalize
        $aciklama = isset($_POST['aciklama']) ? trim((string)$_POST['aciklama'])
                     : (isset($_POST['ozet']) ? trim((string)$_POST['ozet']) : '');

        // Durum normalize: 'aktif'|'pasif'|1|0|'on'|'true'...
        $raw       = $_POST['durum'] ?? null;
        $val       = is_string($raw) ? mb_strtolower(trim($raw)) : $raw;
        $aktifSet  = ['1','on','true','aktif','yayinda','yayında','evet','yes'];
        $durumInt  = (int) (in_array($val, $aktifSet, true) || $val === 1 || $val === true);

        // --- parent_id döngü/suç ortaklığı kontrolü ---
        if ($id > 0 && $parent_id) {
            if ($parent_id === $id) {
                // Kendini ebeveyn yapmaya çalışıyorsa iptal
                $parent_id = null;   // istersen hata da döndürebilirsin
            } else {
                // Ata zincirini yürü ve kendine ulaşıyorsa iptal et
                $pdo = \App\Core\DB::pdo();
                $pid = $parent_id;
                $hop = 0; $max = 50;
                while ($pid && $hop++ < $max) {
                    if ($pid === $id) { $parent_id = null; break; }
                    $st = $pdo->prepare("SELECT parent_id FROM kategoriler WHERE id = ? LIMIT 1");
                    $st->execute([$pid]);
                    $pid = (int)($st->fetchColumn() ?: 0);
                }
            }
        }        

        // 3) Doğrulama
        $vr = \App\Core\Validator::make(
            [
                'ad'        => $ad,
                'slug'      => $slug,
                'aciklama'  => $aciklama,
                'durum'     => (string)$durumInt, // '0' | '1'
            ],
            [
                'ad'        => 'required|between:2,100',
                'slug'      => 'required|slug|between:2,120', // pattern:slug değil, doğrudan slug kuralı
                'aciklama'  => 'max:200',
                'durum'     => 'in:0,1',
            ]
        );
        if (!$vr['ok']) {
            if ($isAjax) {
                return $this->jsonErr('Form hataları mevcut.', 'VALIDATION', $vr['errors'] ?? [], 422);
            }
            $this->flash('hata', 'Form hataları mevcut.');
            return $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/kategoriler'); // <<< DÖN
        }

        // 4) Slug benzersizliği (UPDATE’te kendi kaydını hariç tut)
        $haricId = $id > 0 ? $id : 0;
        if (!$this->model->slugUygunMu($slug, $haricId)) {
            if ($isAjax) {
                return $this->jsonErr('Bu slug zaten kullanımda.', 'SLUG_DUP', ['slug' => ['Bu slug zaten kullanımda.']], 422);
            }
            $this->flash('hata', 'Bu slug zaten kullanımda.');
            return $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/kategoriler'); // <<< DÖN
        }

        // 5) INSERT / UPDATE (Model imzalarına uygun)
        if ($id > 0) {
            $__stmt = \App\Core\DB::pdo()->prepare("SELECT slug FROM kategoriler WHERE id = :id LIMIT 1");
            $__stmt->execute([':id' => $id]);
            $eskiSlug = (string)($__stmt->fetchColumn() ?: '');          
            $ok = $this->model->guncelle($id, [
                'parent_id' => $parent_id,
                'ad'        => $ad,
                'slug'      => $slug,
                'aciklama'  => $aciklama,
                'durum'     => $durumInt,  // 1|0
            ]);
            if (!$ok) {
                return $this->jsonErr('Güncelleme başarısız.', 'DB_ERR', [], 500);
            }

            // --- SLUG DEĞİŞTİYSE 301 YÖNLENDİRME EKLE/GÜNCELLE (UPDATE başarılıysa) ---
            try {
                if (!empty($eskiSlug) && $eskiSlug !== $slug) {
                    $kaynak = '/kategori/' . ltrim($eskiSlug, '/');
                    $hedef  = '/kategori/' . ltrim($slug, '/');

                    $__check = \App\Core\DB::pdo()->prepare("SELECT id, hedef FROM yonlendirmeler WHERE kaynak = :k LIMIT 1");
                    $__check->execute([':k' => $kaynak]);
                    $__row = $__check->fetch(\PDO::FETCH_ASSOC);

                    if ($__row) {
                        if ((string)$__row['hedef'] !== $hedef) {
                            $__upd = \App\Core\DB::pdo()->prepare("UPDATE yonlendirmeler SET hedef = :h, tip = 301, aktif = 1 WHERE id = :id");
                            $__upd->execute([':h' => $hedef, ':id' => (int)$__row['id']]);
                        }
                    } else {
                        $__ins = \App\Core\DB::pdo()->prepare("INSERT INTO yonlendirmeler (kaynak, hedef, tip, aktif, olusturuldu) VALUES (:k, :h, 301, 1, NOW())");
                        $__ins->execute([':k' => $kaynak, ':h' => $hedef]);
                    }
                }
            } catch (\Throwable $e) {
                // yönlendirme hatası iş akışını bozmasın
            }        

            if ($isAjax) {
                return $this->jsonOk([
                    'mesaj'     => 'Kategori güncellendi.',
                    'id'        => (int)$id,
                    'durum'     => $durumInt,
                    'durum_str' => $durumInt ? 'aktif' : 'pasif',
                    'ids'       => [(int)$id],
                ]);
            }
            $this->flash('mesaj', 'Kategori güncellendi.');
            return $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/kategoriler'); // <<< DÖN
        }

        // INSERT
        $yeniId = $this->model->ekle([
            'parent_id' => $parent_id,
            'ad'        => $ad,
            'slug'      => $slug,
            'aciklama'  => $aciklama !== '' ? $aciklama : null,
            'durum'     => $durumInt,      // 1|0
        ]);
        if ($yeniId <= 0) {
            return $this->jsonErr('Kaydetme başarısız.', 'DB_ERR', [], 500);
        }

        if ($isAjax) {
            return $this->jsonOk(['mesaj' => 'Kategori oluşturuldu.', 'id' => (int)$yeniId, 'durum' => $durumInt, 'ids' => [(int)$yeniId]], 201);
        }
        $this->flash('mesaj', 'Kategori oluşturuldu.');
        return $this->redirect('/admin/kategoriler'); // <<< DÖN
    }

    // POST /admin/kategoriler/sil (tekli veya toplu)
    public function sil(): string
    {
        // AJAX tespiti
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xreq   = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        $isAjax = str_contains($accept, 'application/json') || $xreq === 'xmlhttprequest' || $xreq === 'fetch';

        if (!\App\Core\Csrf::check()) {
            if ($isAjax) return $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
            $_SESSION['hata'] = 'Güvenlik doğrulaması başarısız.';
            $this->redirect(BASE_URL . '/admin/kategoriler'); // exit
        }

        try {
            $ids = [];
            if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
                $ids = array_values(array_filter(array_map('intval', $_POST['ids'])));
            } elseif (!empty($_POST['id'])) {
                $ids = [(int)$_POST['id']];
            }

            if (!$ids) {
                if ($isAjax) return $this->jsonErr('Seçim yok.', 'NO_SELECTION', [], 422);
                $_SESSION['hata'] = 'Silinecek kayıt seçilmedi.';
                $this->redirect(BASE_URL . '/admin/kategoriler'); // exit
            }

            // Toplu/tekil soft delete
            if (count($ids) > 1) {
                if (method_exists($this->model, 'destroyMany')) {
                    $adet = (int)$this->model->destroyMany($ids);
                } elseif (method_exists($this->model, 'topluSil')) {
                    $adet = (int)$this->model->topluSil($ids);
                } else {
                    $pdo = \App\Core\DB::pdo();
                    $in  = implode(',', array_fill(0, count($ids), '?'));
                    $st  = $pdo->prepare("UPDATE kategoriler
                                          SET silindi = 1, guncelleme_tarihi = NOW()
                                          WHERE id IN ($in)");
                    $st->execute($ids);
                    $adet = $st->rowCount();
                }
            } else {
                $id = $ids[0];
                if (method_exists($this->model, 'sil')) {
                    $this->model->sil($id);
                    $adet = 1;
                } else {
                    $pdo = \App\Core\DB::pdo();
                    $st  = $pdo->prepare("UPDATE kategoriler
                                          SET silindi = 1, guncelleme_tarihi = NOW()
                                          WHERE id = ?");
                    $st->execute([$id]);
                    $adet = $st->rowCount();
                }
            }

            if ($isAjax) return $this->jsonOk(['silinen' => $adet, 'ids' => $ids]);

            $_SESSION['mesaj'] = (count($ids) > 1)
                ? 'Seçili kategoriler silindi.'
                : 'Kategori silindi.';
            $this->redirect(BASE_URL . '/admin/kategoriler'); // exit

        } catch (\Throwable $e) {
            if ($isAjax) return $this->jsonErr('Silme hatası', 'SERVER_ERR', ['detay'=>[$e->getMessage()]], 500);
            $_SESSION['hata'] = 'Silme hatası: ' . $e->getMessage();
            $this->redirect(BASE_URL . '/admin/kategoriler'); // exit
        }

        return '';
    }

    public function slugKontrol(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? null);
            if (!$token || !\App\Core\Csrf::dogrula($token)) {
                $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
                return;
            }

            $slugRaw = trim($_POST['slug'] ?? '');
            $id      = (int)($_POST['id'] ?? 0);

            if ($slugRaw === '') {
                $this->jsonOk(['uygun' => false, 'mesaj' => 'Slug boş']);
                return;
            }

            $norm = slugify($slugRaw);
            if ($norm === '') {
                $this->jsonOk(['uygun' => false, 'mesaj' => 'Geçersiz slug']);
                return;
            }

            if (function_exists('slug_is_reserved') && slug_is_reserved($norm)) {
                $this->jsonOk(['uygun' => false, 'mesaj' => 'Bu slug rezerve, lütfen değiştirin']);
                return;
            }
            $varMi = $this->model->slugVarMi($norm, $id);
            $this->jsonOk(['uygun' => !$varMi]);
            return;
        } catch (\Throwable $e) {
            $this->jsonErr('Sunucu hatası', 'SERVER_ERR', ['detay'=>[$e->getMessage()]], 500);
            return;
        }
    }

    private function slugUret(string $metin): string
    {
        $tr = ['ç','ğ','ı','ö','ş','ü','Ç','Ğ','İ','Ö','Ş','Ü'];
        $en = ['c','g','i','o','s','u','C','G','I','O','S','U'];
        $metin = str_replace($tr, $en, $metin);
        $metin = strtolower($metin);
        $metin = preg_replace('~[^a-z0-9]+~', '-', $metin);
        $metin = trim($metin, '-');
        return $metin ?: 'kategori';
    }

    // GET /admin/kategoriler/cop
    public function cop(): string
    {
        $q     = trim($_GET['q'] ?? '');
        $p     = max(1, (int)($_GET['p'] ?? 1));
        $limit = 10;

        $kategoriler = $this->model->copListele($q, $p, $limit);
        $toplamKayit = $this->model->copSay($q);
        $sayfaSayisi = (int)ceil($toplamKayit / $limit);

        // Tek görünüm: liste.php, 'mod' => 'cop' bayrağıyla
        return $this->view('admin/kategoriler/liste', [
            'kategoriler' => $kategoriler,
            'q'           => $q,
            'p'           => $p,
            'sayfaSayisi' => $sayfaSayisi,
            'baslik'      => 'Kategoriler (Çöp Kutusu)',
            'mod'         => 'cop',
            'ustMap'      => $this->model->ustMap(),   // <<< eklendi (fallback için)
        ]);
    }

    // POST /admin/kategoriler/cop/geri-al  (JSON)
    public function copGeriAl(): string
    {
        if (!\App\Core\Csrf::check()) {
            return $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
        }
        $ids = array_map('intval', $_POST['ids'] ?? []);
        $ids = array_values(array_filter($ids));
        if (!$ids) {
            return $this->jsonErr('Seçim yok.', 'NO_SELECTION', [], 422);
        }
        try {
            $adet = (int)$this->model->restoreMany($ids);
            return $this->jsonOk(['geri_alinan' => $adet, 'ids' => $ids]);
        } catch (\Throwable $e) {
            return $this->jsonErr('Sunucu hatası', 'SERVER_ERR', ['detay'=>[$e->getMessage()]], 500);
        }
    }

    // POST /admin/kategoriler/cop/kalici-sil  (JSON)
    public function copKaliciSil(): string
    {
        if (!\App\Core\Csrf::check()) {
            return $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
        }
        $ids = [];
        if (!empty($_POST['id']))  $ids[] = (int)$_POST['id'];
        if (!empty($_POST['ids'])) $ids = array_merge($ids, array_map('intval', (array)$_POST['ids']));
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) {
            return $this->jsonErr('Seçim yok.', 'NO_SELECTION', [], 422);
        }
        try {
            if (method_exists($this->model, 'hardDestroyMany')) {
                $adet = (int)$this->model->hardDestroyMany($ids);
            } else {
                $pdo = \App\Core\DB::pdo();
                $in  = implode(',', array_fill(0, count($ids), '?'));
                $st  = $pdo->prepare("DELETE FROM kategoriler WHERE id IN ($in)");
                $st->execute($ids);
                $adet = $st->rowCount();
            }
            return $this->jsonOk(['silinen' => $adet, 'ids' => $ids]);
        } catch (\Throwable $e) {
            return $this->jsonErr('Sunucu hatası', 'SERVER_ERR', ['detay'=>[$e->getMessage()]], 500);
        }
    }

    public function copSayJson(): string
    {
        try {
            if (method_exists($this->model, 'copSay')) {
                $n = (int)$this->model->copSay('');
            } else {
                $pdo = \App\Core\DB::pdo();
                $n   = (int)$pdo->query("SELECT COUNT(*) FROM kategoriler WHERE silindi = 1")->fetchColumn();
            }
            return $this->jsonOk(['n' => $n]);
        } catch (\Throwable $e) {
            return $this->jsonErr('Sunucu hatası', 'SERVER_ERR', [], 500);
        }
    }

    // ama JSON uçları varken gerekmez. Tutmak istersen aşağıdaki güncel haliyle tut.
    public function yokEt(): string
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xreq   = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        $isAjax = str_contains($accept, 'application/json') || $xreq === 'xmlhttprequest' || $xreq === 'fetch';

        if (!\App\Core\Csrf::check()) {
            if ($isAjax) return $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
            $_SESSION['hata'] = 'Güvenlik doğrulaması başarısız.';
            $this->redirect(BASE_URL . '/admin/kategoriler/cop');
        }

        $ids = [];
        if (!empty($_POST['id']))  $ids[] = (int)$_POST['id'];
        if (!empty($_POST['ids'])) $ids = array_merge($ids, array_map('intval', (array)$_POST['ids']));
        $ids = array_values(array_unique(array_filter($ids)));

        if (!$ids) {
            if ($isAjax) return $this->jsonErr('Kalıcı silinecek kayıt seçilmedi.', 'NO_SELECTION', [], 422);
            $_SESSION['hata'] = 'Kalıcı silinecek kayıt seçilmedi.';
            $this->redirect(BASE_URL . '/admin/kategoriler/cop');
        }

        try {
            if (method_exists($this->model, 'hardDestroyMany')) {
                $adet = (int)$this->model->hardDestroyMany($ids);
            } else {
                $pdo = \App\Core\DB::pdo();
                $in  = implode(',', array_fill(0, count($ids), '?'));
                $st  = $pdo->prepare("DELETE FROM kategoriler WHERE id IN ($in)");
                $st->execute($ids);
                $adet = $st->rowCount();
            }
            if ($isAjax) return $this->jsonOk(['silinen' => $adet, 'ids' => $ids]);

            $_SESSION['mesaj'] = $adet . ' kayıt kalıcı olarak silindi.';
            $this->redirect(BASE_URL . '/admin/kategoriler/cop');
        } catch (\Throwable $e) {
            if ($isAjax) return $this->jsonErr('Sunucu hatası', 'SERVER_ERR', ['detay'=>[$e->getMessage()]], 500);
            $_SESSION['hata'] = 'Silme hatası: ' . $e->getMessage();
            $this->redirect(BASE_URL . '/admin/kategoriler/cop');
        }
        return '';
    }

}