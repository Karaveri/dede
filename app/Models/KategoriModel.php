<?php

namespace App\Models;
use App\Core\Database;
use PDO;

class KategoriModel
{
    private PDO $db;

    public function __construct() { $this->db = Database::baglanti(); }

    public function durumGuncelleCoklu(array $ids, int $durum): bool
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) return false;

        $in  = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE kategoriler
                   SET durum = ?, guncelleme_tarihi = NOW()
                 WHERE id IN ($in)";

        $st = $this->db->prepare($sql);
        $params = array_merge([$durum], $ids);
        return $st->execute($params);
    }

     /* -------- Liste / Say / Arama / Sayfalama -------- */
    public function listele(string $q = '', int $p = 1, int $limit = 10): array
    {
        $p     = max(1, $p);
        $limit = max(1, min(200, $limit));
        $ofset = ($p - 1) * $limit;

        $where  = 'WHERE k.silindi = 0';
        $params = [];

        if ($q !== '') {
            $where .= ' AND (k.ad LIKE :q OR k.slug LIKE :q)';
            $params[':q'] = "%{$q}%";
        }

        // DİKKAT: ozet YOK; aciklama var.
        $sql = "SELECT
                    k.id,
                    k.ad,
                    k.slug,
                    k.aciklama,
                    k.durum,
                    k.parent_id,
                    k.olusturma_tarihi,
                    k.guncelleme_tarihi,
                    DATE_FORMAT(k.guncelleme_tarihi, '%d.%m.%Y %H:%i') AS tarih, -- opsiyonel, görünüm kolay okur
                    u.ad AS ust_ad
                FROM kategoriler k
                LEFT JOIN kategoriler u ON u.id = k.parent_id
                $where
                ORDER BY k.id DESC
                LIMIT :l OFFSET :o";

        $st = $this->db->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':l', $limit, \PDO::PARAM_INT);
        $st->bindValue(':o', $ofset, \PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // toplam sayım
        $topSql = "SELECT COUNT(*) FROM kategoriler k $where";
        $st2 = $this->db->prepare($topSql);
        foreach ($params as $k => $v) $st2->bindValue($k, $v);
        $st2->execute();
        $toplam = (int)$st2->fetchColumn();

        return [$rows, $toplam, $p, $limit];
    }

    // KATEGORİ SAYISI (soft-delete filtreli)
    public function say(string $q = ''): int
    {
        $where  = 'WHERE silindi = 0';
        $params = [];

        if ($q !== '') {
            $where .= ' AND (ad LIKE :q OR slug LIKE :q)';
            $params[':q'] = "%{$q}%";
        }

        $st = $this->db->prepare("SELECT COUNT(*) FROM kategoriler $where");
        foreach ($params as $k => $v) { $st->bindValue($k, $v); }
        $st->execute();

        return (int)$st->fetchColumn();
    }

    /* -------- Ağaç için tümünü getir (parent_id dahil) -------- */
    public function tumunuDetayli(): array
    {
        $sql = "SELECT id, parent_id, ad, slug, aciklama, durum
                FROM kategoriler
                WHERE silindi = 0
                ORDER BY parent_id IS NULL DESC, parent_id ASC, ad ASC";
        return $this->db->query($sql)->fetchAll();
    }

    /* -------- Tek / Seçenek -------- */
    public function tekGetir(int $id): ?array
    {
        $s = $this->db->prepare('SELECT * FROM kategoriler WHERE id = ? AND silindi = 0 LIMIT 1');
        $s->execute([$id]);
        $r = $s->fetch();
        return $r ?: null;
    }

    public function tumunuGetir(): array
    {
        return $this->db
            ->query('SELECT id, ad FROM kategoriler WHERE silindi = 0 ORDER BY ad ASC')
            ->fetchAll();
    }

    // 1) Slug benzersizliği
    public function slugUygunMu(string $slug, int $haricId = 0): bool
    {
        $sql = "SELECT COUNT(*) FROM kategoriler WHERE slug = :slug"
             . ($haricId ? " AND id <> :id" : "");
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':slug', $slug, \PDO::PARAM_STR);
        if ($haricId) $stmt->bindValue(':id', $haricId, \PDO::PARAM_INT);
        $stmt->execute();
        return ((int)$stmt->fetchColumn()) === 0;
    }

    // 2) Ekle (INSERT)
    public function ekle(array $d): int
    {
        $parent = $d['parent_id'] ?? ($d['ust_id'] ?? null);
        $parent = (int)$parent ?: null;

        $sql = "INSERT INTO kategoriler
                (parent_id, ad, slug, aciklama, durum, olusturma_tarihi, guncelleme_tarihi, silindi)
                VALUES (:parent_id, :ad, :slug, :aciklama, :durum, NOW(), NOW(), 0)";
        $st = $this->db->prepare($sql);
        $st->bindValue(':parent_id', $parent, is_null($parent) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $st->bindValue(':ad',   $d['ad']);
        $st->bindValue(':slug', $d['slug']);
        $st->bindValue(
            ':aciklama',
            isset($d['aciklama']) && $d['aciklama'] !== '' ? $d['aciklama'] : null,
            isset($d['aciklama']) && $d['aciklama'] !== '' ? \PDO::PARAM_STR : \PDO::PARAM_NULL
        );
        $st->bindValue(':durum', !empty($d['durum']) ? 1 : 0, \PDO::PARAM_INT);
        $st->execute();
        return (int)$this->db->lastInsertId();
    }

    // 3) Güncelle (UPDATE)
    public function guncelle(int $id, array $d): bool
    {
        $parent = $d['parent_id'] ?? ($d['ust_id'] ?? null);
        $parent = (int)$parent ?: null;

        $sql = "UPDATE kategoriler SET
                  parent_id = :parent_id,
                  ad        = :ad,
                  slug      = :slug,
                  aciklama  = :aciklama,
                  durum     = :durum,
                  guncelleme_tarihi = NOW()
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->bindValue(':parent_id', $parent, is_null($parent) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $st->bindValue(':ad',   $d['ad']);
        $st->bindValue(':slug', $d['slug']);
        $st->bindValue(
            ':aciklama',
            isset($d['aciklama']) && $d['aciklama'] !== '' ? $d['aciklama'] : null,
            isset($d['aciklama']) && $d['aciklama'] !== '' ? \PDO::PARAM_STR : \PDO::PARAM_NULL
        );
        $st->bindValue(':durum', !empty($d['durum']) ? 1 : 0, \PDO::PARAM_INT);
        $st->bindValue(':id', $id, \PDO::PARAM_INT);
        return $st->execute();
    }

    public function sil(int $id): bool
    {
        $s = $this->db->prepare('UPDATE kategoriler SET silindi = 1, guncelleme_tarihi = NOW() WHERE id = :id');
        return $s->execute([':id' => $id]);
    }

    public function topluSil(array $ids): bool
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) return true;
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $s   = $this->db->prepare("UPDATE kategoriler SET silindi = 1, guncelleme_tarihi = NOW() WHERE id IN ($in)");
        return $s->execute($ids);
    }

    // --- Çöp kutusu listeleme (silindi = 1)
    public function copListele(string $q = '', int $p = 1, int $limit = 10): array
    {
        $p = max(1, $p);
        $ofset = ($p - 1) * $limit;

        $where  = 'WHERE k.silindi = 1';
        $params = [];
        if ($q !== '') {
            $where .= ' AND (k.ad LIKE :q OR k.slug LIKE :q)';
            $params[':q'] = "%{$q}%";
        }

        $sql = "SELECT
                    k.id,
                    k.ad,
                    k.slug,
                    k.aciklama,
                    k.durum,
                    k.parent_id,
                    k.olusturma_tarihi,
                    k.guncelleme_tarihi,
                    DATE_FORMAT(COALESCE(k.guncelleme_tarihi, k.olusturma_tarihi), '%d.%m.%Y %H:%i') AS tarih,
                    COALESCE(k.guncelleme_tarihi, k.olusturma_tarihi) AS son_degisim,
                    u.ad AS ust_ad
                FROM kategoriler k
                LEFT JOIN kategoriler u ON u.id = k.parent_id
                $where
                ORDER BY k.id DESC
                LIMIT :l OFFSET :o";

        $st = $this->db->prepare($sql);
        foreach ($params as $k => $v) { $st->bindValue($k, $v); }
        $st->bindValue(':l', $limit, \PDO::PARAM_INT);
        $st->bindValue(':o', $ofset, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Üst kategori haritası (id => ad); çöp dahil tüm kategoriler
    public function ustMap(): array
    {
        $st = $this->db->query("SELECT id, ad FROM kategoriler");
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        return $rows ? array_column($rows, 'ad', 'id') : [];
    }

    public function copSay(string $q = ''): int
    {
        $where  = 'WHERE silindi = 1';
        $params = [];
        if ($q !== '') {
            $where .= ' AND (ad LIKE :q OR slug LIKE :q)';
            $params[':q'] = "%{$q}%";
        }
        $st = $this->db->prepare("SELECT COUNT(*) FROM kategoriler $where");
        foreach ($params as $k => $v) { $st->bindValue($k, $v); }
        $st->execute();
        return (int)$st->fetchColumn();
    }

    public function hardDestroyMany(array $ids): int
    {
        if (!$ids) return 0;
        $place = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("DELETE FROM kategoriler WHERE id IN ($place)");
        $st->execute($ids);
        return $st->rowCount();
    }

    // --- Toplu geri al
    public function restoreMany(array $ids): int
    {
        if (!$ids) return 0;
        $place = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("UPDATE kategoriler SET silindi = 0, guncelleme_tarihi = NOW() WHERE id IN ($place)");
        $st->execute($ids);
        return $st->rowCount();
    }

    // Kategorilerde durum 0|1
    public function durumDegistir(int $id, $deger) {
        $hedef = (string)$deger === 'aktif' ? 1 : ((string)$deger === 'taslak' ? 0 : (int)$deger);
        $hedef = $hedef ? 1 : 0;
        $st = \App\Core\DB::pdo()->prepare("UPDATE kategoriler SET durum = :d WHERE id = :i");
        $st->execute([':d'=>$hedef, ':i'=>$id]);
        return $hedef; // 1|0
    }

    public function topluDurum(array $ids, $deger)
    {
        if (!$ids) return 0;
        $hedef = (string)$deger === 'aktif' ? 1 : ((string)$deger === 'taslak' ? 0 : (int)$deger);
        $hedef = $hedef ? 1 : 0;

        $in  = implode(',', array_fill(0, count($ids), '?'));
        $st  = \App\Core\DB::pdo()->prepare("UPDATE kategoriler SET durum = ? WHERE id IN ($in)");
        $st->execute(array_merge([$hedef], $ids));

        return $hedef; // 1|0
    }

    public function destroyMany(array $ids): int
    {
        if (!$ids) return 0;
        $in = implode(',', array_fill(0, count($ids), '?'));
        // soft delete
        $st = \App\Core\DB::pdo()->prepare("UPDATE kategoriler SET silindi = 1 WHERE id IN ($in)");
        $st->execute($ids);
        return $st->rowCount();
    }

// === Admin liste (opsiyonel durum) ===
// $durum: 1 | 0 | null (tümü)
public function adminListe(?int $durum, int $limit, int $offset): array
{
    $pdo = \App\Core\DB::pdo();
    $sql = "SELECT k.id, k.parent_id, k.ad, k.slug, k.durum,
                   COALESCE(k.guncelleme_tarihi, k.olusturma_tarihi) AS son_degisim
            FROM kategoriler AS k
            WHERE k.silindi = 0";
    $params = [];
    if ($durum !== null) { $sql .= " AND k.durum = :durum"; $params[':durum'] = (int)$durum; }
    $sql .= " ORDER BY k.id DESC LIMIT " . (int)$offset . ", " . (int)$limit;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(\PDO::FETCH_ASSOC);
}

public function adminToplam(?int $durum): int
{
    $pdo = \App\Core\DB::pdo();
    $sql = "SELECT COUNT(*) FROM kategoriler AS k WHERE k.silindi = 0";
    $params = [];
    if ($durum !== null) { $sql .= " AND k.durum = :durum"; $params[':durum'] = (int)$durum; }
    $st = $pdo->prepare($sql); $st->execute($params);
    return (int)$st->fetchColumn();
}

public function copToplam(): int
{
    $pdo = \App\Core\DB::pdo();
    return (int)$pdo->query("SELECT COUNT(*) FROM kategoriler AS k WHERE k.silindi = 1")->fetchColumn();
}

// === Public tek kategori (slug) ===
public function tekBySlug(string $slug): ?array
{
    $pdo = \App\Core\DB::pdo();
    $sql = "SELECT k.id, k.parent_id, k.ad, k.slug, k.aciklama,
                   COALESCE(k.guncelleme_tarihi, k.olusturma_tarihi) AS son_degisim
            FROM kategoriler AS k
            WHERE k.silindi = 0 AND k.durum = 1 AND k.slug = :slug
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':slug'=>$slug]);
    $row = $st->fetch(\PDO::FETCH_ASSOC);
    return $row ?: null;
}

// === Slug tekillik (kendi id’ni hariç tut) ===
public function slugVarMi(string $slug, int $haricId = 0): bool
{
    $pdo = \App\Core\DB::pdo();
    $sql = "SELECT 1 FROM kategoriler k WHERE k.silindi = 0 AND k.slug = :slug";
    $params = [':slug'=>$slug];
    if ($haricId > 0) { $sql .= " AND k.id <> :id"; $params[':id'] = $haricId; }
    $sql .= " LIMIT 1";
    $st = $pdo->prepare($sql); $st->execute($params);
    return (bool)$st->fetchColumn();
}

}