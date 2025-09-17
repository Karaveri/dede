<?php declare(strict_types=1);

return [
  'id' => '20250914_001_medya_meta',
  'up' => function(\PDO $pdo): void {
      $colExists = function(string $name) use ($pdo): bool {
          $q = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='medya' AND COLUMN_NAME=?");
          $q->execute([$name]);
          return (bool)$q->fetchColumn();
      };

      if (!$colExists('alt_text')) {
          $pdo->exec("ALTER TABLE medya ADD COLUMN alt_text VARCHAR(255) NULL AFTER yukseklik");
      }
      if (!$colExists('title')) {
          $pdo->exec("ALTER TABLE medya ADD COLUMN title VARCHAR(150) NULL AFTER alt_text");
      }
  },
  'down' => function(\PDO $pdo): void {
      $colExists = function(string $name) use ($pdo): bool {
          $q = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='medya' AND COLUMN_NAME=?");
          $q->execute([$name]);
          return (bool)$q->fetchColumn();
      };
      if ($colExists('title'))    $pdo->exec("ALTER TABLE medya DROP COLUMN title");
      if ($colExists('alt_text')) $pdo->exec("ALTER TABLE medya DROP COLUMN alt_text");
  },
];
