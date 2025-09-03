<?php
// database/migrations/20250830_001_schema_hardening.php
// MariaDB: AUTO_INCREMENT kolonunu generated column içinde kullanamayız.
// Bu nedenle slug_aktif yerine (slug, silindi) üzerinde kompozit UNIQUE kullanıyoruz.

return [
    'id' => '20250830_001_schema_hardening',

    'up' => function (\PDO $pdo): void {
        // Helpers (SHOW ... üzerinde bind KULLANMA)
        $hasColumn = function (\PDO $pdo, string $table, string $col): bool {
            $sql = "SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($col);
            return (bool) $pdo->query($sql)->fetch();
        };
        $hasIndex = function (\PDO $pdo, string $table, string $name): bool {
            $sql = "SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($name);
            return (bool) $pdo->query($sql)->fetch();
        };

        // --- kategoriler: slug_aktif denemesi olduysa temizle, sonra UNIQUE(slug, silindi)
        if ($hasIndex($pdo, 'kategoriler', 'ux_kategoriler_slug_aktif')) {
            $pdo->exec("ALTER TABLE `kategoriler` DROP INDEX `ux_kategoriler_slug_aktif`");
        }
        if ($hasColumn($pdo, 'kategoriler', 'slug_aktif')) {
            $pdo->exec("ALTER TABLE `kategoriler` DROP COLUMN `slug_aktif`");
        }
        if (!$hasIndex($pdo, 'kategoriler', 'ux_kategoriler_slug_silindi')) {
            $pdo->exec("ALTER TABLE `kategoriler` ADD UNIQUE KEY `ux_kategoriler_slug_silindi` (`slug`, `silindi`)");
        }

        // parent_id indexi ilk migrasyonda eklendi; burada **yeni isimle** ikinciyi açma.

        // --- sayfalar: slug_aktif denemesi olduysa temizle, sonra UNIQUE(slug, silindi)
        if ($hasIndex($pdo, 'sayfalar', 'ux_sayfalar_slug_aktif')) {
            $pdo->exec("ALTER TABLE `sayfalar` DROP INDEX `ux_sayfalar_slug_aktif`");
        }
        if ($hasColumn($pdo, 'sayfalar', 'slug_aktif')) {
            $pdo->exec("ALTER TABLE `sayfalar` DROP COLUMN `slug_aktif`");
        }

        // created_at/updated_at → yerel alanlara devretme ve sökme (varsa)
        if ($hasColumn($pdo, 'sayfalar', 'created_at')) {
            $pdo->exec("UPDATE `sayfalar` SET `olusturma_tarihi` = COALESCE(`olusturma_tarihi`, `created_at`)");
            $pdo->exec("ALTER TABLE `sayfalar` DROP COLUMN `created_at`");
        }
        if ($hasColumn($pdo, 'sayfalar', 'updated_at')) {
            $pdo->exec("UPDATE `sayfalar` SET `guncelleme_tarihi` = COALESCE(`updated_at`, `guncelleme_tarihi`)");
            $pdo->exec("ALTER TABLE `sayfalar` DROP COLUMN `updated_at`");
        }

        if (!$hasIndex($pdo, 'sayfalar', 'ux_sayfalar_slug_silindi')) {
            $pdo->exec("ALTER TABLE `sayfalar` ADD UNIQUE KEY `ux_sayfalar_slug_silindi` (`slug`, `silindi`)");
        }

        // --- yonlendirmeler: kolasyon + indexler (isim tutarlılığı)
        $pdo->exec("ALTER TABLE `yonlendirmeler` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$hasIndex($pdo, 'yonlendirmeler', 'ux_yonl_kaynak')) {
            $pdo->exec("ALTER TABLE `yonlendirmeler` ADD UNIQUE KEY `ux_yonl_kaynak` (`kaynak`)");
        }
        if (!$hasIndex($pdo, 'yonlendirmeler', 'ix_yonl_aktif')) {
            $pdo->exec("ALTER TABLE `yonlendirmeler` ADD INDEX `ix_yonl_aktif` (`aktif`)");
        }

        // --- kullanicilar
        if (!$hasIndex($pdo, 'kullanicilar', 'ux_kull_eposta')) {
            $pdo->exec("ALTER TABLE `kullanicilar` ADD UNIQUE KEY `ux_kull_eposta` (`eposta`)");
        }
        if (!$hasIndex($pdo, 'kullanicilar', 'ix_kull_rol')) {
            $pdo->exec("ALTER TABLE `kullanicilar` ADD INDEX `ix_kull_rol` (`rol_id`)");
        }
        if (!$hasIndex($pdo, 'kullanicilar', 'ix_kull_durum')) {
            $pdo->exec("ALTER TABLE `kullanicilar` ADD INDEX `ix_kull_durum` (`durum`)");
        }
        if (!$hasIndex($pdo, 'kullanicilar', 'ix_kull_banli')) {
            $pdo->exec("ALTER TABLE `kullanicilar` ADD INDEX `ix_kull_banli` (`banli`)");
        }
    },

    'down' => function (\PDO $pdo): void {
        // Kompozit unique'leri sök; slug_aktif yok (zaten kaldırdık)
        @$pdo->exec("ALTER TABLE `kategoriler` DROP INDEX `ux_kategoriler_slug_silindi`");
        @$pdo->exec("ALTER TABLE `sayfalar` DROP INDEX `ux_sayfalar_slug_silindi`");
        // yonlendirmeler ve kullanicilar indexleri temkinli olarak kalsın; ihtiyaç varsa eklersin
    },
];
