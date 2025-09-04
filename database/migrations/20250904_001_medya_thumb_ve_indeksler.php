<?php declare(strict_types=1);
use PDO;

return [
  'id' => '20250904_001_medya_thumb_ve_indeksler',
  'up' => function(PDO $pdo): void {
      $hasCol = function(string $col) use ($pdo): bool {
          $q = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='medya' AND COLUMN_NAME=?");
          $q->execute([$col]);
          return (bool)$q->fetchColumn();
      };
      $hasIdx = function(string $name) use ($pdo): bool {
          $q = $pdo->prepare("SHOW INDEX FROM medya WHERE Key_name=?");
          $q->execute([$name]);
          return (bool)$q->fetch(PDO::FETCH_ASSOC);
      };

      if (!$hasCol('yol_thumb'))   $pdo->exec("ALTER TABLE medya ADD COLUMN yol_thumb VARCHAR(255) NULL AFTER yol");
      if (!$hasCol('hash'))        $pdo->exec("ALTER TABLE medya ADD COLUMN hash CHAR(40) NULL AFTER boyut");
      if (!$hasCol('genislik'))    $pdo->exec("ALTER TABLE medya ADD COLUMN genislik INT NULL AFTER hash");
      if (!$hasCol('yukseklik'))   $pdo->exec("ALTER TABLE medya ADD COLUMN yukseklik INT NULL AFTER genislik");
      if (!$hasCol('created_at'))  $pdo->exec("ALTER TABLE medya ADD COLUMN created_at DATETIME NULL");

      if (!$hasIdx('ux_medya_yol'))         $pdo->exec("ALTER TABLE medya ADD UNIQUE KEY ux_medya_yol (yol)");
      if (!$hasIdx('ux_medya_hash'))        $pdo->exec("ALTER TABLE medya ADD UNIQUE KEY ux_medya_hash (hash)");
      if (!$hasIdx('ix_medya_created_at'))  $pdo->exec("ALTER TABLE medya ADD INDEX ix_medya_created_at (created_at)");
  },
  'down' => function(PDO $pdo): void {
      $pdo->exec("ALTER TABLE medya DROP INDEX ux_medya_yol");
      $pdo->exec("ALTER TABLE medya DROP INDEX ux_medya_hash");
      $pdo->exec("ALTER TABLE medya DROP INDEX ix_medya_created_at");
  },
];
