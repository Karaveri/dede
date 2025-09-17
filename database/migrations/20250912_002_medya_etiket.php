<?php declare(strict_types=1);

return [
  'id' => '20250912_002_medya_etiket',
  'up' => function(\PDO $pdo): void {
      // Gerekirse engine düzelt
      $engineOf = function(string $tab) use ($pdo): ?string {
          $q = $pdo->prepare("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
          $q->execute([$tab]);
          $e = $q->fetchColumn();
          return $e ? strtoupper((string)$e) : null;
      };
      foreach (['medya','etiketler'] as $t) {
          $e = $engineOf($t);
          if ($e && $e !== 'INNODB') {
              $pdo->exec("ALTER TABLE `{$t}` ENGINE=InnoDB");
          }
      }

      // Ebeveyn id sütunlarının tipini birebir al (örn. "int(10) unsigned" ya da "bigint(20)")
      $idType = function(string $tab) use ($pdo): string {
          $q = $pdo->prepare("
              SELECT COLUMN_TYPE
              FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'id'
              LIMIT 1
          ");
          $q->execute([$tab]);
          $t = $q->fetchColumn();
          return $t ? (string)$t : 'int(10) unsigned';
      };
      $medyaType  = $idType('medya');
      $etiketType = $idType('etiketler');

      // Köprü tabloyu oluştur
      $pdo->exec("
          CREATE TABLE IF NOT EXISTS medya_etiket (
              medya_id  {$medyaType} NOT NULL,
              etiket_id {$etiketType} NOT NULL,
              PRIMARY KEY (medya_id, etiket_id),
              KEY ix_medya_etiket__etiket (etiket_id, medya_id),
              CONSTRAINT fk_medya_etiket__medya
                  FOREIGN KEY (medya_id) REFERENCES medya(id)
                  ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT fk_medya_etiket__etiket
                  FOREIGN KEY (etiket_id) REFERENCES etiketler(id)
                  ON DELETE CASCADE ON UPDATE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
      ");
  },
  'down' => function(\PDO $pdo): void {
      $pdo->exec("DROP TABLE IF EXISTS medya_etiket;");
  },
];
