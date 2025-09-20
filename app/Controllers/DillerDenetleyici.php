<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Csrf;
use App\Core\Validator;
use PDO;

class DillerDenetleyici extends Controller
{
    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::baglanti();
    }

    public function liste()
    {
        $stmt = $this->pdo->query("SELECT * FROM diller ORDER BY sira ASC, kod ASC");
        $diller = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->view('admin/diller/liste', [
            'baslik' => 'Diller',
            'diller' => $diller,
            '_token' => Csrf::token()
        ]);
    }

    public function form($kod = null)
    {
        // rota paramı yoksa ?kod=... olarak da kabul et
        if (!$kod && isset($_GET['kod'])) {
            $kod = trim((string)$_GET['kod']);
        }

        $kayit = null;
        if (!empty($kod)) {
            $stmt = $this->pdo->prepare("SELECT * FROM diller WHERE kod = ?");
            $stmt->execute([$kod]);
            $kayit = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$kayit) {
                return $this->redirect(BASE_URL . '/admin/diller');
            }
        }

        return $this->view('admin/diller/form', [
            'baslik' => $kayit ? ('Dili Düzenle: '.$kayit['kod']) : 'Yeni Dil',
            'kayit'  => $kayit,
            '_token' => Csrf::token()
        ]);
    }

    public function kaydet()
    {
        if (!Csrf::check()) {
            return $this->jsonErr('CSRF doğrulaması başarısız.');
        }

        // Normalize: kodu küçük harfe indir ve baş/son boşlukları temizle
        $rawKod = trim($_POST['kod'] ?? '');
        $data = [
            'kod'        => strtolower($rawKod),
            'ad'         => trim($_POST['ad'] ?? ''),
            'aktif'      => isset($_POST['aktif']) ? 1 : 0,
            'varsayilan' => isset($_POST['varsayilan']) ? 1 : 0,
            'sira'       => (int)($_POST['sira'] ?? 0),
        ];
        $mevcutKod = trim($_POST['_mevcut_kod'] ?? '');

        // 1) Kural tabanlı doğrulama
        $sonuc = Validator::make($data, [
            'kod'  => 'required|between:2,10',
            'ad'   => 'required|max:50',
            'sira' => 'integer',
        ]);
        $hatalar = $sonuc['errors'] ?? [];

        // 2) Özel regex: tr, en, en-us gibi (küçük harf + opsiyonel -xx)
        if (!preg_match('~^[a-z]{2}(?:-[a-z]{2})?$~', $data['kod'])) {
            $hatalar['kod'][] = 'Kod "tr", "en", "en-us" biçiminde olmalı (küçük harf).';
        }

        if (!empty($hatalar)) {
            return $this->jsonErr('Geçersiz alanlar', ['hatalar' => $hatalar]);
        }

		try {
		    // Transaction başlat
		    if (!$this->pdo->inTransaction()) {
		        $this->pdo->beginTransaction();
		    }

		    // Varsayılan seçildiyse diğerlerini sıfırla
		    if ($data['varsayilan'] === 1) {
		        $this->pdo->exec("UPDATE diller SET varsayilan = 0");
		    }

		    if ($mevcutKod !== '') {
		        // Kod değişiyorsa benzersizlik kontrolü
		        if ($mevcutKod !== $data['kod']) {
		            $var = $this->pdo->prepare("SELECT 1 FROM diller WHERE kod = ?");
		            $var->execute([$data['kod']]);
		            if ($var->fetch()) {
		                throw new \RuntimeException('Bu dil kodu zaten var.');
		            }
		        }
		        $upd = $this->pdo->prepare("
		            UPDATE diller
		               SET kod=:yeni_kod, ad=:ad, aktif=:aktif, varsayilan=:varsayilan, sira=:sira
		             WHERE kod=:eski_kod
		        ");
		        $upd->execute([
		            ':yeni_kod'    => $data['kod'],
		            ':ad'          => $data['ad'],
		            ':aktif'       => $data['aktif'],
		            ':varsayilan'  => $data['varsayilan'],
		            ':sira'        => $data['sira'],
		            ':eski_kod'    => $mevcutKod,
		        ]);
		    } else {
		        $ins = $this->pdo->prepare("
		            INSERT INTO diller (kod, ad, aktif, varsayilan, sira)
		            VALUES (:kod, :ad, :aktif, :varsayilan, :sira)
		        ");
		        $ins->execute([
		            ':kod'         => $data['kod'],
		            ':ad'          => $data['ad'],
		            ':aktif'       => $data['aktif'],
		            ':varsayilan'  => $data['varsayilan'],
		            ':sira'        => $data['sira'],
		        ]);
		    }

		    if ($this->pdo->inTransaction()) {
		        $this->pdo->commit();
		    }
			return $this->jsonOk([ 'mesaj' => 'Kaydedildi', 'veri'  => ['yonlendir' => BASE_URL . '/admin/diller'] ]);

		} catch (\Throwable $e) {
		    if ($this->pdo->inTransaction()) {
		        $this->pdo->rollBack();
		    }
		    return $this->jsonErr('Kayıt hatası: '.$e->getMessage());
		}
    }

    public function sil()
    {
        if (!Csrf::check()) {
            return $this->jsonErr('CSRF doğrulaması başarısız.');
        }
        $kod = strtolower(trim($_POST['kod'] ?? ''));
        if ($kod === '') {
            return $this->jsonErr('Kod gerekli.');
        }

        $stmt = $this->pdo->prepare("SELECT varsayilan FROM diller WHERE kod = ?");
        $stmt->execute([$kod]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $this->jsonErr('Kayıt bulunamadı.');
        }
        if ((int)$row['varsayilan'] === 1) {
            return $this->jsonErr('Varsayılan dil silinemez.');
        }

        $del = $this->pdo->prepare("DELETE FROM diller WHERE kod = ?");
        $del->execute([$kod]);
		return $this->jsonOk(['mesaj' => 'Silindi']);
    }

    public function apiListe()
    {
        $stmt = $this->pdo->query("SELECT kod, ad, aktif, varsayilan, sira FROM diller ORDER BY sira ASC, kod ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		return $this->jsonOk([
		    'mesaj' => 'Tamam',
		    'veri'  => ['diller' => $rows]
		]);
    }
}
