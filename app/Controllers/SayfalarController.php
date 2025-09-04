<?php
namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Auth;
use App\Core\Controller;
use App\Models\SayfaModel;
use App\Helpers\Validator;
use \App\Traits\DurumTrait;
use App\Core\DB;

class SayfalarController extends AdminController
{
    private SayfaModel $model;
    protected array $roller = ['admin','editor','yazar'];

    public function __construct()
    {
        parent::__construct();
        $this->model = new SayfaModel();
    }

    // GET /admin/sayfalar?q=&p=&g=agac
    // app/Controllers/SayfalarController.php  içinde
    public function index(): string
    {
        $q     = trim($_GET['q'] ?? '');
        $p     = max(1, (int)($_GET['p'] ?? 1));
        $limit = 10;
        $ofset = ($p - 1) * $limit;

        // Sadece silinmeyenler
        $where  = "WHERE silindi = 0";
        $params = [];

        if ($q !== '') {
            $where .= " AND (baslik LIKE :q OR slug LIKE :q OR ozet LIKE :q)";
            $params[':q'] = "%{$q}%";
        }
        $durum = trim((string)($_GET['durum'] ?? ''));
        if ($durum === 'yayinda' || $durum === 'taslak') {
            $where .= " AND durum = :durum";
            $params[':durum'] = $durum;
        }
        // Toplam kayıt
        $st = DB::pdo()->prepare("SELECT COUNT(*) FROM sayfalar $where");
        foreach ($params as $k=>$v) $st->bindValue($k,$v,\PDO::PARAM_STR);
        $st->execute();
        $toplamKayit = (int)$st->fetchColumn();

        // Liste  (NOT: $where burada da kullanılmalı)
        $sql = "SELECT id, baslik, slug, durum, ozet, icerik,
                       meta_baslik, meta_aciklama,
                       olusturma_tarihi, guncelleme_tarihi,
                       COALESCE(guncelleme_tarihi, olusturma_tarihi) AS son_degisim
                FROM sayfalar
                $where
                ORDER BY id DESC
                LIMIT :l OFFSET :o";
        $st = DB::pdo()->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v,\PDO::PARAM_STR);
        $st->bindValue(':l',$limit,\PDO::PARAM_INT);
        $st->bindValue(':o',$ofset,\PDO::PARAM_INT);
        $st->execute();
        $sayfalar = $st->fetchAll(\PDO::FETCH_ASSOC);

        return $this->view('admin/sayfalar/index', [
            'baslik'      => 'Sayfalar',
            'sayfalar'    => $sayfalar,
            'sayfaSayisi' => max(1, (int)ceil($toplamKayit / $limit)),
            'p'           => $p,
            'q'           => $q,
            'mod'         => '',   // normal liste
        ]);
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

        $raw  = $_POST['durum'] ?? null;
        $v    = is_string($raw) ? mb_strtolower(trim($raw)) : $raw;
        $yes  = ['1','on','true','aktif','yayinda','yayında','evet','yes'];
        $yeni = in_array($v, $yes, true) ? 'yayinda' : 'taslak';

        try {
            $pdo = \App\Core\DB::pdo();
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE sayfalar
                    SET durum = ?, guncelleme_tarihi = NOW()
                    WHERE silindi = 0 AND id IN ($in)";
            $st  = $pdo->prepare($sql);
            $ok  = $st->execute(array_merge([$yeni], $ids));
            if (!$ok) {
                return $this->jsonErr('Güncelleme hatası.', 'DB_ERR', [], 500);
            }
            return $this->jsonOk(['durum' => $yeni, 'ids' => $ids]);
        } catch (\Throwable $e) {
            return $this->jsonErr('İşlem hatası: '.$e->getMessage(), 'DB_EX', [], 500);
        }
    }

    public function silToplu(): string
    {
        if (!\App\Core\Csrf::check()) {
            return $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
        }
        $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), fn($v)=>$v>0));
        if (!$ids) {
            return $this->jsonErr('Seçili kayıt yok.', 'NO_SELECTION', [], 422);
        }
        try {
            $pdo = \App\Core\DB::pdo();
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE sayfalar
                    SET silindi = 1, guncelleme_tarihi = NOW()
                    WHERE silindi = 0 AND id IN ($in)";
            $st  = $pdo->prepare($sql);
            $ok  = $st->execute($ids);
            if (!$ok) {
                return $this->jsonErr('Silme sırasında bir hata oluştu.', 'DB_ERR', [], 500);
            }
            return $this->jsonOk(['silinen' => (int)$st->rowCount(), 'ids' => $ids]);
        } catch (\Throwable $e) {
            return $this->jsonErr('İşlem hatası: '.$e->getMessage(), 'DB_EX', [], 500);
        }
    }

    // GET /admin/sayfalar/olustur
    public function olustur(): void
    {
        $gorunumYolu = __DIR__ . '/../views/admin/sayfalar/form.php';
        require __DIR__ . '/../views/admin/sablon.php';
    }

    public function kaydet(): void
    {
        // CSRF (csrf | csrf_token | X-CSRF-TOKEN)
        $__incoming = $_POST['csrf'] ?? $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Csrf::dogrula($__incoming)) {
            $_SESSION['hata'] = 'Güvenlik doğrulaması başarısız.';
            header('Location: ' . BASE_URL . '/admin/sayfalar/olustur'); exit;
        }

        // Veriler
        $baslik        = trim($_POST['baslik'] ?? '');
        $ozet          = trim($_POST['ozet'] ?? '');
        $icerik        = trim($_POST['icerik'] ?? '');
        $icerik        = \html_saf($icerik);
        $slugInput     = trim($_POST['slug'] ?? '');
        $durumRaw = $_POST['durum'] ?? null;
        $val      = is_string($durumRaw) ? mb_strtolower(trim($durumRaw), 'UTF-8') : $durumRaw;
        $yaySet   = ['1','on','true','aktif','yayinda','yayında','evet','yes'];
        $durum    = in_array($val, $yaySet, true) ? 'yayinda' : 'taslak';
        $meta_baslik   = trim($_POST['meta_baslik'] ?? '');
        $meta_aciklama = trim($_POST['meta_aciklama'] ?? '');

        // META varsayılanları
        if ($meta_baslik === '' && $baslik !== '') {
            // ~60 karaktere kısalt (SEO title için pratik sınır)
            $meta_baslik = rtrim(mb_strimwidth($baslik, 0, 60, '…', 'UTF-8'));
        }
        if ($meta_aciklama === '') {
            $kaynak = $ozet !== '' ? $ozet : strip_tags($icerik);
            // ~160 karaktere kısalt (meta description)
            $meta_aciklama = rtrim(mb_strimwidth($kaynak, 0, 160, '…', 'UTF-8'));
        }

        // --- SLUG (yeni ortak politika) ---
        if ($slugInput !== '') {
            // Kullanıcı verdi ise normalize + benzersiz
            $slug = slug_benzersiz(slugify($slugInput), 'sayfalar', 0);
        } else {
            // Başlıktan üret + benzersiz
            $slug = slug_guvenli($baslik, 'sayfalar', 0);
        }

        if ($baslik === '') {
            $_SESSION['hata'] = 'Başlık zorunludur.';
            header('Location: ' . BASE_URL . '/admin/sayfalar/olustur'); exit;
        }

        // --- Özet kuralları ---
        if (mb_strlen($ozet, 'UTF-8') > 200) {
            $_SESSION['hata'] = 'Özet en fazla 200 karakter olmalı.';
            header('Location: ' . BASE_URL . '/admin/sayfalar/olustur'); exit;
        }
        // Meta açıklama boşsa özeti kullan (160’a kırp)
        if ($meta_aciklama === '' && $ozet !== '') {
            $meta_aciklama = rtrim(mb_strimwidth(strip_tags($ozet), 0, 160, '…', 'UTF-8'));
        }

        // --- SLUG (ortak politika) ---
        // Oluşturmada haricId her zaman 0 olmalı
        if ($slugInput !== '') {
            $slug = slug_benzersiz(slugify($slugInput), 'sayfalar', 0);
        } else {
            $slug = slug_guvenli($baslik, 'sayfalar', 0);
        }

        $rules = [
            'baslik'        => 'required|max:200',
            'slug'          => 'slug|max:220',           // pattern:slug değil → slug
            'ozet'          => 'max:200',
            'meta_baslik'   => 'max:255',
            'meta_aciklama' => 'max:255',
            'durum'         => 'in:yayinda,taslak',      // normalize edilmiş değerler
        ];

        // Doğrulamayı normalize edilmiş veriyle yap
        $toValidate = [
            'baslik'        => $baslik,
            'slug'          => $slug,
            'ozet'          => $ozet,
            'meta_baslik'   => $meta_baslik,
            'meta_aciklama' => $meta_aciklama,
            'durum'         => $durum,   // ← kritik satır
        ];
        $v = \App\Core\Validator::make($toValidate, $rules);
        if (!$v['ok']) {
            // AJAX form kullanmıyoruz; flash ile geri dön
            $_SESSION['hata'] = reset($v['errors']); // ilk hata
            header('Location: ' . BASE_URL . '/admin/sayfalar/olustur'); exit;
        }

        // Kayıt
        $sql = "INSERT INTO sayfalar (baslik, ozet, icerik, slug, durum, meta_baslik, meta_aciklama, olusturma_tarihi, guncelleme_tarihi)
                VALUES (:baslik, :ozet, :icerik, :slug, :durum, :meta_baslik, :meta_aciklama, NOW(), NOW())";
        $s = DB::pdo()->prepare($sql);
        $ok = $s->execute([
            ':baslik' => $baslik,
            ':ozet'   => $ozet,
            ':icerik' => $icerik,
            ':slug'   => $slug,
            ':durum'  => $durum,
            ':meta_baslik' => $meta_baslik,
            ':meta_aciklama'=> $meta_aciklama,
        ]);

        $_SESSION[$ok ? 'mesaj' : 'hata'] = $ok ? 'Sayfa oluşturuldu.' : 'Kayıt sırasında bir hata oluştu.';
        header('Location: ' . BASE_URL . '/admin/sayfalar'); exit;
    }

    public function guncelle(): void
    {
        // CSRF
        $__incoming = $_POST['csrf'] ?? $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Csrf::dogrula($__incoming)) {
            $_SESSION['hata'] = 'Güvenlik doğrulaması başarısız.';
            header('Location: ' . BASE_URL . '/admin/sayfalar'); exit;
        }

        $id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) { 
            $_SESSION['hata'] = 'Düzenlenecek kayıt bulunamadı (ID eksik).';
            header('Location: ' . BASE_URL . '/admin/sayfalar'); 
            exit; 
        }

        // Ekle: mevcut (eski) slug'ı al
        $__stmt = DB::pdo()->prepare("SELECT slug FROM sayfalar WHERE id = :id LIMIT 1");
        $__stmt->execute([':id' => $id]);
        $eskiSlug = (string)$__stmt->fetchColumn();

        $baslik        = trim($_POST['baslik'] ?? '');
        $ozet          = trim($_POST['ozet'] ?? '');
        $icerik        = trim($_POST['icerik'] ?? '');
        $icerik        = \html_saf($icerik);
        $slugInput     = trim($_POST['slug'] ?? '');
        $durumRaw      = $_POST['durum'] ?? 'yayinda';
        $durum         = in_array((string)$durumRaw, ['1','yayinda','aktif'], true) ? 'yayinda' : 'taslak';
        $meta_baslik   = trim($_POST['meta_baslik'] ?? '');
        $meta_aciklama = trim($_POST['meta_aciklama'] ?? '');

        if ($baslik === '') {
            $_SESSION['hata'] = 'Başlık zorunludur.';
            header('Location: ' . BASE_URL . '/admin/sayfalar/duzenle/' . $id); exit;
        }

        // --- Özet & META kuralları ---
        if (mb_strlen($ozet, 'UTF-8') > 200) {
            $_SESSION['hata'] = 'Özet en fazla 200 karakter olmalı.';
            header('Location: ' . BASE_URL . '/admin/sayfalar/duzenle/' . $id); exit;
        }

        // META varsayılanları (boş bırakıldıysa otomatik doldur)
        if ($meta_baslik === '' && $baslik !== '') {
            $meta_baslik = rtrim(mb_strimwidth($baslik, 0, 60, '…', 'UTF-8'));
        }
        if ($meta_aciklama === '') {
            $kaynak = $ozet !== '' ? $ozet : strip_tags($icerik);
            $meta_aciklama = rtrim(mb_strimwidth($kaynak, 0, 160, '…', 'UTF-8'));
        }

        // Slug üret + tekilleştir (kendi ID’sini ignore et)
        $slug = $slugInput !== '' ? $slugInput : $baslik;
        $slug = strtolower(trim(preg_replace('~[^a-z0-9]+~i', '-', iconv('UTF-8','ASCII//TRANSLIT',$slug)), '-'));
        $slug = $this->benzersizSlug($slug, $id);

        $rules = [
            'baslik'        => 'required|max:200',
            'slug'          => 'slug|max:220',           // pattern:slug değil, slug
            'ozet'          => 'max:200',
            'meta_baslik'   => 'max:255',
            'meta_aciklama' => 'max:255',
            'durum'         => 'in:yayinda,taslak',      // değişkene uygun değerler
        ];
        $v = \App\Core\Validator::make($_POST, $rules);
        if (!$v['ok']) {
            $errs = $v['errors'] ?? [];
            $first = is_array($errs) ? reset($errs) : null;               
            $msg = (is_array($first) && $first) ? (string)reset($first)   
                                                : 'Form hataları mevcut.';
            $_SESSION['hata'] = $msg;
            $id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
            header('Location: ' . BASE_URL . '/admin/sayfalar/duzenle?id=' . $id); exit;
        }

        // Güncelle
        $sql = "UPDATE sayfalar
                SET baslik=:baslik, ozet=:ozet, icerik=:icerik, slug=:slug, durum=:durum,
                    meta_baslik=:meta_baslik, meta_aciklama=:meta_aciklama, guncelleme_tarihi=NOW()
                WHERE id=:id";
        $s = DB::pdo()->prepare($sql);
        $ok = $s->execute([
            ':baslik' => $baslik,
            ':ozet'   => $ozet,
            ':icerik' => $icerik,
            ':slug'   => $slug,
            ':durum'  => $durum,
            ':meta_baslik' => $meta_baslik,
            ':meta_aciklama'=> $meta_aciklama,
            ':id'     => $id,
        ]);

        // --- Slug değiştiyse 301 yönlendirmeyi güncelle/ekle (UPDATE başarılıysa) ---
        if ($ok && !empty($eskiSlug) && $eskiSlug !== $slug) {
            $kaynak = '/' . ltrim($eskiSlug, '/');
            $hedef  = '/' . ltrim($slug, '/');

            $__check = DB::pdo()->prepare("SELECT id, hedef FROM yonlendirmeler WHERE kaynak = :k LIMIT 1");
            $__check->execute([':k' => $kaynak]);
            $__row = $__check->fetch(\PDO::FETCH_ASSOC);

            if ($__row) {
                if ((string)$__row['hedef'] !== $hedef) {
                    $__upd = DB::pdo()->prepare("UPDATE yonlendirmeler SET hedef = :h, tip = 301, aktif = 1 WHERE id = :id");
                    $__upd->execute([':h' => $hedef, ':id' => (int)$__row['id']]);
                }
            } else {
                $__ins = DB::pdo()->prepare("INSERT INTO yonlendirmeler (kaynak, hedef, tip, aktif, olusturuldu) VALUES (:k, :h, 301, 1, NOW())");
                $__ins->execute([':k' => $kaynak, ':h' => $hedef]);
            }
        }       

        $_SESSION[$ok ? 'mesaj' : 'hata'] = $ok ? 'Sayfa güncellendi.' : 'Güncelleme sırasında bir hata oluştu.';
        header('Location: ' . BASE_URL . '/admin/sayfalar'); exit;
    }

    // --------------- SOFT SİL (tek/çok) ---------------
    public function sil(): string
    {
        if (!\App\Core\Csrf::check()) {
            $_SESSION['hata'] = 'Güvenlik doğrulaması başarısız.';
            $this->redirect(BASE_URL . '/admin/sayfalar'); return '';
        }

        // Hem POST hem GET’ten ID’yi tolere et (form id alanı yoksa yolda kalmasın)
        $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), fn($v)=>$v>0));
        $id  = (int)($_POST['id'] ?? 0);

        try {
            $pdo = \App\Core\DB::pdo();

            if ($ids) {
                $in  = implode(',', array_fill(0, count($ids), '?'));
                $sql = "UPDATE sayfalar SET silindi = 1, guncelleme_tarihi = NOW() WHERE id IN ($in)";
                $st  = $pdo->prepare($sql);
                $st->execute($ids);
                $_SESSION['mesaj'] = count($ids).' kayıt çöp kutusuna taşındı.';
            } elseif ($id > 0) {
                $st = $pdo->prepare("UPDATE sayfalar SET silindi = 1, guncelleme_tarihi = NOW() WHERE id = :id");
                $st->execute([':id'=>$id]);
                $_SESSION['mesaj'] = 'Kayıt çöp kutusuna taşındı.';
            } else {
                $_SESSION['hata'] = 'Seçili kayıt yok.';
            }
        } catch (\Throwable $e) {
            $_SESSION['hata'] = 'Silme hatası: '.$e->getMessage();
        }

        $this->redirect(BASE_URL . '/admin/sayfalar'); return '';
    }

    // GET /admin/sayfalar/duzenle?id=...
    public function duzenle(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { $this->jsonErr('Sayfa bulunamadı', 'NOT_FOUND', [], 404); return; }

        // 1) Silinmemiş sayfayı al: NULL, 0, '0' ve '' hepsi "silinmemiş" kabul
        $sql = "SELECT * FROM sayfalar
                WHERE id = :id
                  AND (silindi IS NULL OR silindi = 0 OR silindi = '0' OR silindi = '')
                LIMIT 1";
        $st = DB::pdo()->prepare($sql);
        $st->execute([':id' => $id]);
        $sayfa = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$sayfa) {
            $chk = DB::pdo()->prepare("SELECT silindi FROM sayfalar WHERE id = :id LIMIT 1");
            $chk->execute([':id' => $id]);
            $silindiDeger = $chk->fetchColumn();

            if ($silindiDeger !== false) {
                $_SESSION['hata'] = 'Bu sayfa çöp kutusunda. Lütfen geri yükleyip sonra düzenleyin.';
                header('Location: ' . BASE_URL . '/admin/sayfalar/cop'); exit;
            }

            $this->jsonErr('Sayfa bulunamadı', 'NOT_FOUND', [], 404); return;
        }

            $gorunumYolu = __DIR__ . '/../views/admin/sayfalar/form.php';
            require __DIR__ . '/../views/admin/sablon.php';
    }

    // POST /admin/sayfalar/toplu-durum  (ids[], durum='aktif'|'taslak')
    public function topluDurum(): string
    {
        return $this->durumToplu();
    }

    // POST /admin/sayfalar/durum  (listeden tek tıkla)
    public function durum(): string
    {
        // AJAX tespiti
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xreq   = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        $isAjax = str_contains($accept, 'application/json') || $xreq === 'xmlhttprequest' || $xreq === 'fetch';

        // CSRF
        if (!\App\Core\Csrf::check()) {
            return $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            return $this->jsonErr('Geçersiz id.', 'BAD_ID', [], 422);
        }

        $pdo = \App\Core\DB::pdo();
        // Durum alanı sizde metin: 'yayinda' | 'taslak' (bazı eski kayıtlarda 1/0 olabilir; ikisini de ele al)
        $q = $pdo->prepare("SELECT durum FROM sayfalar WHERE id = :id AND silindi = 0 LIMIT 1");
        $q->execute([':id' => $id]);
        $mevcut = $q->fetchColumn();

        if ($mevcut === false) {
            return $this->jsonErr('Kayıt bulunamadı.', 'NOT_FOUND', [], 404);
        }

        // Yeni değeri hesapla (her iki formatı da tanı)
        $val  = is_string($mevcut) ? mb_strtolower(trim($mevcut)) : $mevcut;
        $yes  = ['1','true','on','aktif','yayinda','yayında','evet','yes'];
        $isOn = in_array((string)$val, $yes, true) || $val === 1 || $val === true;

        $yeniStr = $isOn ? 'taslak' : 'yayinda';       // DB’ye yazılacak metin
        $yeniInt = $yeniStr === 'yayinda' ? 1 : 0;     // UI’nin kolay yorumu

        $u = $pdo->prepare("UPDATE sayfalar SET durum = :d, guncelleme_tarihi = NOW() WHERE id = :id");
        $ok = $u->execute([':d' => $yeniStr, ':id' => $id]);

        if (!$ok) {
            return $this->jsonErr('Güncelleme hatası.', 'DB_ERR', [], 500);
        }

        // Her iki alanı da döndür: durum (0/1), durum_str ('yayinda'|'taslak')
        return $this->jsonOk([
            'mesaj'     => 'Durum güncellendi.',
            'durum'     => $yeniInt,                                  // 1 | 0
            'durum_str' => ($yeniStr === 'yayinda') ? 'aktif' : 'taslak', // UI uyumlu
            'ids'       => [$id],
        ]);
    }

    public function slugKontrol(): string
    {
        if (!\App\Core\Csrf::check()) {
            return $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
        }

        $id   = (int)($_POST['id'] ?? 0);            // düzenle sayfasında kendi kaydını hariç tutmak için
        $slug = trim((string)($_POST['slug'] ?? ''));

        if ($slug === '') {
            return $this->jsonOk(['uygun' => false, 'mesaj' => 'Slug boş']);
        }

        // Şekil kontrolü
        if (!preg_match('~^[a-z0-9]+(?:-[a-z0-9]+)*$~', $slug)) {
            return $this->jsonOk(['uygun' => false, 'mesaj' => 'Geçersiz slug']);
        }

        // Rezerv kontrol (helpers/slug_helper.php)
        if (function_exists('slug_is_reserved') && slug_is_reserved($slug)) {
            return $this->jsonOk(['uygun' => false, 'mesaj' => 'Bu slug rezerve, lütfen değiştirin']);
        }

        // Eşsizlik
        $pdo = \App\Core\DB::pdo();
        if ($id > 0) {
            $s = $pdo->prepare("SELECT 1 FROM sayfalar WHERE slug = :slug AND silindi = 0 AND id <> :id LIMIT 1");
            $s->execute([':slug'=>$slug, ':id'=>$id]);
        } else {
            $s = $pdo->prepare("SELECT 1 FROM sayfalar WHERE slug = :slug AND silindi = 0 LIMIT 1");
            $s->execute([':slug'=>$slug]);
        }
        $varMi = (bool)$s->fetchColumn();

        return $this->jsonOk(['uygun' => !$varMi]);
    }

    // GET /admin/sayfalar/cop
    public function cop(): string
    {
        $q     = trim($_GET['q'] ?? '');
        $p     = max(1, (int)($_GET['p'] ?? 1));
        $limit = 10;
        $ofset = ($p-1)*$limit;

        $where  = "WHERE silindi = 1";
        $params = [];
        if ($q !== '') {
            $where .= " AND (baslik LIKE :q OR slug LIKE :q)";
            $params[':q'] = "%{$q}%";
        }

        $sql = "SELECT id, baslik, ozet, slug, durum, olusturma_tarihi, guncelleme_tarihi,
                       DATE_FORMAT(COALESCE(guncelleme_tarihi, olusturma_tarihi), '%d.%m.%Y %H:%i') AS tarih
                FROM sayfalar
                $where
                ORDER BY id DESC
                LIMIT :l OFFSET :o";
        $st = DB::pdo()->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->bindValue(':l',$limit,\PDO::PARAM_INT);
        $st->bindValue(':o',$ofset,\PDO::PARAM_INT);
        $st->execute();
        $sayfalar = $st->fetchAll(\PDO::FETCH_ASSOC);

        $st2 = DB::pdo()->prepare("SELECT COUNT(*) FROM sayfalar $where");
        foreach ($params as $k=>$v) $st2->bindValue($k,$v);
        $st2->execute();
        $sayfaSayisi = (int)ceil(((int)$st2->fetchColumn()) / $limit);

        return $this->view('admin/sayfalar/index', [
            'baslik'      => 'Sayfalar (Çöp Kutusu)',
            'sayfalar'    => $sayfalar,
            'sayfaSayisi' => $sayfaSayisi,
            'p'           => $p,
            'q'           => $q,
            'mod'         => 'cop',
        ]);
    }

    public function copSayJson(): string
    {
        try {
            if (method_exists($this->model, 'copSay')) {
                $n = (int)$this->model->copSay('');
            } else {
                $pdo = \App\Core\DB::pdo();
                $n   = (int)$pdo->query("SELECT COUNT(*) FROM sayfalar WHERE silindi = 1")->fetchColumn();
            }
            return $this->jsonOk(['n' => $n]);
        } catch (\Throwable $e) {
            return $this->jsonErr('Sunucu hatası', 'SERVER_ERR', [], 500);
        }
    }

    // POST /admin/sayfalar/geri-al  (AJAX ise JSON, değilse redirect)
    public function geriAl(): string
    {
        $isAjax = method_exists($this, 'isAjax') ? $this->isAjax() : (
            stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || in_array(strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), ['xmlhttprequest','fetch'], true)
        );

        if (!\App\Core\Csrf::check()) {
            if ($isAjax) return $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
            $_SESSION['hata'] = 'Güvenlik doğrulaması başarısız.';
            $this->redirect(BASE_URL . '/admin/sayfalar/cop'); return '';
        }

        // id + ids[] toplanır
        $ids = [];
        if (!empty($_POST['id']))  $ids[] = (int)$_POST['id'];
        if (!empty($_POST['ids'])) $ids = array_merge($ids, array_map('intval', (array)$_POST['ids']));
        $ids = array_values(array_unique(array_filter($ids, fn($v)=>$v>0)));

        if (!$ids) {
            if ($isAjax) return $this->jsonErr('Seçim yok.', 'NO_SELECTION', [], 422);
            $_SESSION['hata'] = 'Seçim yok.';
            $this->redirect(BASE_URL . '/admin/sayfalar/cop'); return '';
        }

        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = \App\Core\DB::pdo()->prepare("UPDATE sayfalar SET silindi = 0, guncelleme_tarihi = NOW() WHERE id IN ($in)");
            $st->execute($ids);
            $n = (int)$st->rowCount();
        } catch (\Throwable $e) {
            if ($isAjax) return $this->jsonErr('İşlem hatası: '.$e->getMessage(), 'DB_EX', [], 500);
            $_SESSION['hata'] = 'İşlem hatası.';
            $this->redirect(BASE_URL . '/admin/sayfalar/cop'); return '';
        }

        if ($isAjax) return $this->jsonOk(['ok'=>true, 'ids'=>$ids, 'n'=>$n]);
        $_SESSION['mesaj'] = $n.' kayıt geri alındı.';
        $this->redirect(BASE_URL . '/admin/sayfalar/cop'); return '';
    }

    // POST /admin/sayfalar/yok-et  (AJAX ise JSON, değilse redirect)
    public function yokEt(): string
    {
        $isAjax = method_exists($this, 'isAjax') ? $this->isAjax() : (
            stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || in_array(strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), ['xmlhttprequest','fetch'], true)
        );

        if (!\App\Core\Csrf::check()) {
            if ($isAjax) return $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
            $_SESSION['hata'] = 'Güvenlik doğrulaması başarısız.';
            $this->redirect(BASE_URL . '/admin/sayfalar/cop'); return '';
        }

        $ids = [];
        if (!empty($_POST['id']))  $ids[] = (int)$_POST['id'];
        if (!empty($_POST['ids'])) $ids = array_merge($ids, array_map('intval', (array)$_POST['ids']));
        $ids = array_values(array_unique(array_filter($ids, fn($v)=>$v>0)));

        if (!$ids) {
            if ($isAjax) return $this->jsonErr('Seçim yok.', 'NO_SELECTION', [], 422);
            $_SESSION['hata'] = 'Seçim yok.';
            $this->redirect(BASE_URL . '/admin/sayfalar/cop'); return '';
        }

        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = \App\Core\DB::pdo()->prepare("DELETE FROM sayfalar WHERE id IN ($in)");
            $st->execute($ids);
            $n = (int)$st->rowCount();
        } catch (\Throwable $e) {
            if ($isAjax) return $this->jsonErr('İşlem hatası: '.$e->getMessage(), 'DB_EX', [], 500);
            $_SESSION['hata'] = 'İşlem hatası.';
            $this->redirect(BASE_URL . '/admin/sayfalar/cop'); return '';
        }

        if ($isAjax) return $this->jsonOk(['ok'=>true, 'ids'=>$ids, 'n'=>$n]);
        $_SESSION['mesaj'] = $n.' kayıt kalıcı silindi.';
        $this->redirect(BASE_URL . '/admin/sayfalar/cop'); return '';
    }

    /* ---------- Yardımcılar ---------- */
    private function slugify(string $metin): string
    {
        $tr = ['ç','ğ','ı','ö','ş','ü','Ç','Ğ','İ','Ö','Ş','Ü'];
        $en = ['c','g','i','o','s','u','C','G','I','O','S','U'];
        $metin = str_replace($tr, $en, $metin);
        $metin = strtolower($metin);
        $metin = preg_replace('~[^a-z0-9]+~', '-', $metin);
        $metin = trim($metin, '-');
        return $metin ?: 'sayfa';
    }

    private function slugVarMi(string $slug, int $ignoreId = 0): bool
    {
        $sql = 'SELECT COUNT(*) FROM sayfalar WHERE slug = :slug AND silindi = 0';
        $par = [':slug' => $slug];
        if ($ignoreId > 0) { $sql .= ' AND id != :id'; $par[':id'] = $ignoreId; }
        $s = DB::pdo()->prepare($sql); $s->execute($par);
        return (bool)$s->fetchColumn();
    }

    private function csrfYoksa403(): void
    {
        if (!\App\Core\Csrf::check()) {
            $this->jsonErr('Güvenlik doğrulaması başarısız.', 'CSRF_RED', [], 403);
            return;
        }
    }

    private function benzersizSlug(string $slug, int $ignoreId = 0): string
    {
        $orijinal = $slug; $i = 1;
        while ($this->slugVarMi($slug, $ignoreId)) { $slug = $orijinal.'-'.$i; $i++; }
        return $slug;
    }
}
