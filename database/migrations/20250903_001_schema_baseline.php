<?php
return [
  'id' => '20250903_001_schema_baseline',

  'up' => function (\PDO $pdo): void {
      // Yardımcı: index/unique var mı? (SHOW ... üzerinde bind KULLANMA)
      $hasIndex = function(\PDO $pdo, string $table, string $name): bool {
          $sql = "SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($name);
          return (bool) $pdo->query($sql)->fetch();
      };

      // --- sayfalar: INDEX(silindi, durum)  (sorgu hızlandırma)
      // Not: UNIQUE(slug_aktif) zaten 20250830_001_schema_hardening'de var.
      if (!$hasIndex($pdo, 'sayfalar', 'ix_sayfalar_silindi_durum')) {
          $pdo->exec("ALTER TABLE `sayfalar` ADD INDEX `ix_sayfalar_silindi_durum` (`silindi`, `durum`)");
      }

      // --- kategoriler: INDEX(parent_id) (aynı isimli tek index)
      if (!$hasIndex($pdo, 'kategoriler', 'ix_kategoriler_parent')) {
          $pdo->exec("ALTER TABLE `kategoriler` ADD INDEX `ix_kategoriler_parent` (`parent_id`)");
      }

      // --- kategoriler: FK(parent_id) → kategoriler.id  (yoksa ekle)
      $fkExists = (function(\PDO $pdo): bool {
          $sql = "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = DATABASE()
                    AND CONSTRAINT_NAME = 'fk_kategoriler_parent_self_1'";
          return (int)$pdo->query($sql)->fetchColumn() > 0;
      })($pdo);

      if (!$fkExists) {
          // Tür/NULL uyumu (FK gereksinimi)
          $pdo->exec("ALTER TABLE `kategoriler`
                      MODIFY COLUMN `id`        INT(10) UNSIGNED NOT NULL,
                      MODIFY COLUMN `parent_id` INT(10) UNSIGNED NULL");

          if (!$hasIndex($pdo, 'kategoriler', 'ix_kategoriler_parent')) {
              $pdo->exec("ALTER TABLE `kategoriler` ADD INDEX `ix_kategoriler_parent` (`parent_id`)");
          }

          $pdo->exec("ALTER TABLE `kategoriler`
                      ADD CONSTRAINT `fk_kategoriler_parent_self_1`
                      FOREIGN KEY (`parent_id`) REFERENCES `kategoriler`(`id`)
                      ON UPDATE CASCADE ON DELETE SET NULL");
      }

      // --- yonlendirmeler: UNIQUE(kaynak) (isim tutarlılığı)
      if (!$hasIndex($pdo, 'yonlendirmeler', 'ux_yonl_kaynak')) {
          $pdo->exec("ALTER TABLE `yonlendirmeler` ADD UNIQUE KEY `ux_yonl_kaynak` (`kaynak`)");
      }

      // Bilinçli olarak eklenmeyenler:
      // * sayfalar/kategoriler: UNIQUE(slug, silindi) — slug_aktif stratejisiyle çakışır.
  },

  'down' => function (\PDO $pdo): void {
      // Temkinli geri alma (baseline minimal)
      @$pdo->exec("ALTER TABLE `sayfalar` DROP INDEX `ix_sayfalar_silindi_durum`");
      // 'ix_kategoriler_parent', FK ve 'ux_yonl_kaynak' diğer migration'larla uyum için burada sökülmüyor.
  },
];
