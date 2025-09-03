<?php
// database/migrations/2025_08_30_schema_hardening.php
use PDO;

return new class {
    private function hasColumn(PDO $pdo, string $table, string $col): bool {
        $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c");
        $st->execute([':c'=>$col]);
        return (bool)$st->fetch();
    }
    private function hasIndex(PDO $pdo, string $table, string $name): bool {
        $st = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = :n");
        $st->execute([':n'=>$name]);
        return (bool)$st->fetch();
    }

    public function up(PDO $pdo): void {
        // 1) kategoriler: aktif slug benzersizliği + parent_id index garantisi
        if (!$this->hasColumn($pdo,'kategoriler','slug_aktif')) {
            $pdo->exec("
                ALTER TABLE kategoriler
                ADD COLUMN slug_aktif VARCHAR(191)
                GENERATED ALWAYS AS (CASE WHEN COALESCE(silindi,0)=0 THEN slug ELSE CONCAT(slug,'#',id) END) STORED
            ");
        }
        if (!$this->hasIndex($pdo,'kategoriler','ux_kategoriler_slug_aktif')) {
            $pdo->exec("ALTER TABLE kategoriler ADD UNIQUE KEY ux_kategoriler_slug_aktif (slug_aktif)");
        }
        if (!$this->hasIndex($pdo,'kategoriler','ix_kategoriler_parent_id')) {
            $pdo->exec("ALTER TABLE kategoriler ADD INDEX ix_kategoriler_parent_id (parent_id)");
        }

        // 2) sayfalar: çift zaman alanlarını sadeleştir + aktif slug benzersizliği
        // Zaman alanlarını devret
        if ($this->hasColumn($pdo,'sayfalar','created_at')) {
            $pdo->exec("UPDATE sayfalar SET olusturma_tarihi = COALESCE(olusturma_tarihi, created_at)");
        }
        if ($this->hasColumn($pdo,'sayfalar','updated_at')) {
            $pdo->exec("UPDATE sayfalar SET guncelleme_tarihi = COALESCE(updated_at, guncelleme_tarihi)");
        }
        // Sök
        if ($this->hasColumn($pdo,'sayfalar','created_at')) {
            $pdo->exec("ALTER TABLE sayfalar DROP COLUMN created_at");
        }
        if ($this->hasColumn($pdo,'sayfalar','updated_at')) {
            $pdo->exec("ALTER TABLE sayfalar DROP COLUMN updated_at");
        }
        // Slug benzersizliği (soft-delete uyumlu)
        if (!$this->hasColumn($pdo,'sayfalar','slug_aktif')) {
            $pdo->exec("
                ALTER TABLE sayfalar
                ADD COLUMN slug_aktif VARCHAR(191)
                GENERATED ALWAYS AS (CASE WHEN COALESCE(silindi,0)=0 THEN slug ELSE CONCAT(slug,'#',id) END) STORED
            ");
        }
        if (!$this->hasIndex($pdo,'sayfalar','ux_sayfalar_slug_aktif')) {
            $pdo->exec("ALTER TABLE sayfalar ADD UNIQUE KEY ux_sayfalar_slug_aktif (slug_aktif)");
        }

        // 3) yonlendirmeler: birlik kolasyon + benzersiz kaynak + yardımcı indeks
        $pdo->exec("ALTER TABLE yonlendirmeler CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$this->hasIndex($pdo,'yonlendirmeler','ux_yonl_kaynak')) {
            $pdo->exec("ALTER TABLE yonlendirmeler ADD UNIQUE KEY ux_yonl_kaynak (kaynak)");
        }
        if (!$this->hasIndex($pdo,'yonlendirmeler','ix_yonl_aktif')) {
            $pdo->exec("ALTER TABLE yonlendirmeler ADD INDEX ix_yonl_aktif (aktif)");
        }

        // 4) kullanicilar: eposta benzersiz + sık sorgulara indeks
        if (!$this->hasIndex($pdo,'kullanicilar','ux_kull_eposta')) {
            $pdo->exec("ALTER TABLE kullanicilar ADD UNIQUE KEY ux_kull_eposta (eposta)");
        }
        if (!$this->hasIndex($pdo,'kullanicilar','ix_kull_rol')) {
            $pdo->exec("ALTER TABLE kullanicilar ADD INDEX ix_kull_rol (rol_id)");
        }
        if (!$this->hasIndex($pdo,'kullanicilar','ix_kull_durum')) {
            $pdo->exec("ALTER TABLE kullanicilar ADD INDEX ix_kull_durum (durum)");
        }
        if (!$this->hasIndex($pdo,'kullanicilar','ix_kull_banli')) {
            $pdo->exec("ALTER TABLE kullanicilar ADD INDEX ix_kull_banli (banli)");
        }
    }

    public function down(PDO $pdo): void {
        // Geri alma (temkinli)
        @ $pdo->exec("ALTER TABLE kategoriler DROP INDEX ux_kategoriler_slug_aktif");
        @ $pdo->exec("ALTER TABLE kategoriler DROP COLUMN slug_aktif");
        @ $pdo->exec("ALTER TABLE sayfalar DROP INDEX ux_sayfalar_slug_aktif");
        @ $pdo->exec("ALTER TABLE sayfalar DROP COLUMN slug_aktif");
        // sayfalar created_at/updated_at geri eklemek istemiyorsak down’da atlıyoruz.
        @ $pdo->exec("ALTER TABLE yonlendirmeler DROP INDEX ux_yonl_kaynak");
        @ $pdo->exec("ALTER TABLE yonlendirmeler DROP INDEX ix_yonl_aktif");
        @ $pdo->exec("ALTER TABLE kullanicilar DROP INDEX ux_kull_eposta");
        @ $pdo->exec("ALTER TABLE kullanicilar DROP INDEX ix_kull_rol");
        @ $pdo->exec("ALTER TABLE kullanicilar DROP INDEX ix_kull_durum");
        @ $pdo->exec("ALTER TABLE kullanicilar DROP INDEX ix_kull_banli");
    }
};
