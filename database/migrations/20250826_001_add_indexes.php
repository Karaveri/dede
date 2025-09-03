<?php
// database/migrations/20250826_001_add_indexes.php
// Bu migration yalnızca güvenli (idempotent) indeks ekler. UNIQUE(slug) eklenmez.

return [
    'id' => '20250826_001_add_indexes',

    'up' => function (\PDO $pdo): void {
        // Yardımcı: indeks var mı? (SHOW ... üzerinde bind KULLANMA)
        $hasIndex = function(\PDO $pdo, string $table, string $name): bool {
            $sql = "SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($name);
            return (bool) $pdo->query($sql)->fetch();
        };

        // kategoriler.parent_id -> sık kullanılan sorgular
        if (!$hasIndex($pdo, 'kategoriler', 'ix_kategoriler_parent')) {
            $pdo->exec("ALTER TABLE `kategoriler` ADD INDEX `ix_kategoriler_parent` (`parent_id`)");
        }

        // sayfalar.slug -> arama hızlandırma (benzersizlik bir sonraki migration'da slug_aktif ile sağlanacak)
        if (!$hasIndex($pdo, 'sayfalar', 'ix_sayfalar_slug')) {
            $pdo->exec("ALTER TABLE `sayfalar` ADD INDEX `ix_sayfalar_slug` (`slug`)");
        }

        // TEMİZLİK: varsa debug index'ini kaldır (1091'i engelle)
        if ($hasIndex($pdo, 'kategoriler', 'ix_test')) {
            $pdo->exec("ALTER TABLE `kategoriler` DROP INDEX `ix_test`");
        }
    },

    'down' => function (\PDO $pdo): void {
        // Temkinli geri alma
        @$pdo->exec("ALTER TABLE `kategoriler` DROP INDEX `ix_kategoriler_parent`");
        @$pdo->exec("ALTER TABLE `sayfalar`   DROP INDEX `ix_sayfalar_slug`");
    },
];
