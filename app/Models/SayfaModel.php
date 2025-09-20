<?php
namespace App\Models;

use PDO;
use App\Core\DB;

class SayfaModel
{

// Not: benzersizliği sadece silinmemiş kayıtlar arasında kontrol ediyoruz (silindi = 0).
// Bu, (slug, silindi) UNIQUE indeksini verimli kullanır.
public function slugVarMi(string $slug, int $haricId = 0): bool
{
    $pdo = \App\Core\DB::pdo();

    $sql = "SELECT 1
            FROM sayfalar
            WHERE silindi = 0
              AND slug = :slug";
    if ($haricId > 0) {
        $sql .= " AND id <> :id";
    }
    $sql .= " LIMIT 1";

    $st = $pdo->prepare($sql);
    $st->bindValue(':slug', $slug, \PDO::PARAM_STR);
    if ($haricId > 0) {
        $st->bindValue(':id', $haricId, \PDO::PARAM_INT);
    }
    $st->execute();

    return (bool)$st->fetchColumn();
}

    public function ekle(array $d): bool {
      $st = $this->db->prepare("
        INSERT INTO sayfalar (baslik, slug, ozet, icerik, durum, meta_baslik, meta_aciklama, olusturma_tarihi, guncelleme_tarihi)
        VALUES (:baslik, :slug, :ozet, :icerik, :durum, :meta_baslik, :meta_aciklama, NOW(), NOW())
      ");
      return $st->execute([
        ':baslik'=>$d['baslik'], ':slug'=>$d['slug'], ':ozet'=>$d['ozet'],
        ':icerik'=>$d['icerik'], ':durum'=>$d['durum'],
        ':meta_baslik'=>$d['meta_baslik'], ':meta_aciklama'=>$d['meta_aciklama'],
      ]);
    }

    public function guncelle(int $id, array $d): bool {
      $st = $this->db->prepare("
        UPDATE sayfalar
           SET baslik=:baslik, slug=:slug, ozet=:ozet, icerik=:icerik, durum=:durum,
               meta_baslik=:meta_baslik, meta_aciklama=:meta_aciklama, guncelleme_tarihi=NOW()
         WHERE id=:id
      ");
      return $st->execute([
        ':baslik'=>$d['baslik'], ':slug'=>$d['slug'], ':ozet'=>$d['ozet'],
        ':icerik'=>$d['icerik'], ':durum'=>$d['durum'],
        ':meta_baslik'=>$d['meta_baslik'], ':meta_aciklama'=>$d['meta_aciklama'],
        ':id'=>$id
      ]);
    }

    public static function sil(int $id): bool {
        $q = DB::pdo()->prepare("DELETE FROM sayfalar WHERE id=?");
        return $q->execute([$id]);
    }

    // [rows, toplam, sayfa, limit]
    public function listele(array $filtre = []): array
    {
        $sayfa  = max(1, (int)($filtre['sayfa'] ?? 1));
        $limit  = max(1, min(100, (int)($filtre['limit'] ?? 20)));
        $offset = ($sayfa - 1) * $limit;

        $ara = trim($filtre['ara'] ?? '');
        $kosulSql = '';
        $params = [];

        if ($ara !== '') {
            $kosulSql = "WHERE baslik LIKE :ara OR slug LIKE :ara";
            $params[':ara'] = "%{$ara}%";
        }

        $toplamSql = "SELECT COUNT(*) FROM sayfalar {$kosulSql}";
        $stmt = $this->db->prepare($toplamSql);
        $stmt->execute($params);
        $toplam = (int)$stmt->fetchColumn();

        $sql = "SELECT id, baslik, slug, ozet, durum
                FROM sayfalar
                {$kosulSql}
                ORDER BY id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [$rows, $toplam, $sayfa, $limit];
    }

    // Sayfalarda durum 'aktif' | 'taslak' STRING tutulsun (yonetim.js bunu da anlıyor)
    public function durumDegistir(int $id, $deger) {
        $hedef = in_array($deger, ['aktif','taslak'], true) ? (string)$deger : ((int)$deger ? 'aktif':'taslak');
        $st = \App\Core\DB::pdo()->prepare("UPDATE sayfalar SET durum = :d WHERE id = :i");
        $st->execute([':d'=>$hedef, ':i'=>$id]);
        return $hedef; // 'aktif'|'taslak'
    }

    public function topluDurum(array $ids, $deger) {
        if (!$ids) return 'taslak';
        $hedef = in_array($deger, ['aktif','taslak'], true) ? (string)$deger : ((int)$deger ? 'aktif':'taslak');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = \App\Core\DB::pdo()->prepare("UPDATE sayfalar SET durum = ? WHERE id IN ($in)");
        $st->execute(array_merge([$hedef], $ids));
        return $hedef;
    }

    public function slugUygunMu(string $slug, int $haricId = 0): bool
    {
        $sql = "SELECT COUNT(*) FROM sayfalar WHERE slug = :slug" . ($haricId ? " AND id <> :id" : "");
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        if ($haricId) $stmt->bindValue(':id', $haricId, PDO::PARAM_INT);
        $stmt->execute();
        return ((int)$stmt->fetchColumn()) === 0;
    }

    // $durum: 'yayinda' | 'taslak' | null (tümü)
    public function adminListe(?string $durum, int $limit, int $offset): array
    {
        $pdo = \App\Core\DB::pdo();
        $sql = "SELECT s.id, s.baslik, s.slug, s.durum,
                       COALESCE(s.guncelleme_tarihi, s.updated_at, s.olusturma_tarihi, s.created_at) AS son_degisim
                FROM sayfalar AS s
                WHERE s.silindi = 0";
        $params = [];
        if ($durum !== null) { $sql .= " AND s.durum = :durum"; $params[':durum'] = $durum; }
        $sql .= " ORDER BY s.id DESC LIMIT " . (int)$offset . ", " . (int)$limit;

        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function adminToplam(?string $durum): int
    {
        $pdo = \App\Core\DB::pdo();
        $sql = "SELECT COUNT(*) FROM sayfalar AS s WHERE s.silindi = 0";
        $params = [];
        if ($durum !== null) { $sql .= " AND s.durum = :durum"; $params[':durum'] = $durum; }
        $st = $pdo->prepare($sql); $st->execute($params);
        return (int)$st->fetchColumn();
    }

    // === Çöp kutusu ===
    public function copListe(int $limit, int $offset): array
    {
        $pdo = \App\Core\DB::pdo();
        $sql = "SELECT s.id, s.baslik, s.slug, s.durum,
                       COALESCE(s.guncelleme_tarihi, s.updated_at, s.olusturma_tarihi, s.created_at) AS son_degisim
                FROM sayfalar AS s
                WHERE s.silindi = 1
                ORDER BY s.id DESC
                LIMIT " . (int)$offset . ", " . (int)$limit;
        $st = $pdo->query($sql);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function copToplam(): int
    {
        $pdo = \App\Core\DB::pdo();
        return (int)$pdo->query("SELECT COUNT(*) FROM sayfalar AS s WHERE s.silindi = 1")->fetchColumn();
    }

    // === Public tek sayfa (slug) ===
    public function tekBySlug(string $slug): ?array
    {
        $pdo = \App\Core\DB::pdo();
        $sql = "SELECT s.id, s.baslik, s.slug, s.icerik, s.ozet,
                       s.kapak_gorsel, s.meta_baslik, s.meta_aciklama,
                       COALESCE(s.guncelleme_tarihi, s.updated_at, s.olusturma_tarihi, s.created_at) AS son_degisim
                FROM sayfalar AS s
                WHERE s.silindi = 0 AND s.durum = 'yayinda' AND s.slug = :slug
                LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute([':slug'=>$slug]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Aktif dilleri getir */
    public function aktifDiller(): array {
        $pdo = \App\Core\DB::pdo();
        return $pdo->query("SELECT kod, ad, aktif, varsayilan FROM diller WHERE aktif=1 ORDER BY sira, kod")
                   ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Bir sayfanın tüm çevirilerini getir (dil_kod=>row sözlüğü) */
    public function ceviriler(int $sayfaId): array {
        $pdo = \App\Core\DB::pdo();
        $st = $pdo->prepare("SELECT * FROM sayfa_ceviri WHERE sayfa_id = ?");
        $st->execute([$sayfaId]);
        $map = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) $map[$r['dil_kod']] = $r;
        return $map;
    }

    /** Upsert tek dil */
    public function ceviriUpsert(int $sayfaId, string $dil, array $r): void {
        $pdo   = \App\Core\DB::pdo();
        $baslik = trim($r['baslik'] ?? '');
        $slug   = trim($r['slug']   ?? '');
        if ($slug === '' && $baslik !== '') $slug = slugify($baslik);

        $st = $pdo->prepare("
            INSERT INTO sayfa_ceviri (sayfa_id, dil_kod, baslik, slug, icerik, ozet, meta_baslik, meta_aciklama)
            VALUES (:sid, :dk, :baslik, :slug, :icerik, :ozet, :mb, :ma)
            ON DUPLICATE KEY UPDATE
                baslik = VALUES(baslik),
                slug   = VALUES(slug),
                icerik = VALUES(icerik),
                ozet   = VALUES(ozet),
                meta_baslik = VALUES(meta_baslik),
                meta_aciklama = VALUES(meta_aciklama)
        ");
        $st->execute([
            ':sid'   => $sayfaId,
            ':dk'    => strtolower($dil),
            ':baslik'=> $baslik,
            ':slug'  => $slug,
            ':icerik'=> $r['icerik'] ?? null,
            ':ozet'  => $r['ozet'] ?? null,
            ':mb'    => $r['meta_baslik'] ?? null,
            ':ma'    => $r['meta_aciklama'] ?? null,
        ]);
    }

}
