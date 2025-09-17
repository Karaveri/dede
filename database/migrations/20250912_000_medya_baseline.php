<?php declare(strict_types=1);

return [
  'id' => '20250912_000_medya_baseline',
  'up' => function(\PDO $pdo): void {
      // tablo yoksa oluştur
      $q = $pdo->query("
        SELECT COUNT(*) 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() AND table_name = 'medya'
      ");
      $exists = (int)$q->fetchColumn() > 0;
      if ($exists) return;

      $pdo->exec("
        CREATE TABLE medya (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          yol VARCHAR(255) NOT NULL,
          yol_thumb VARCHAR(255) NULL,
          mime VARCHAR(50) NOT NULL,
          boyut INT NOT NULL,
          hash CHAR(40) NULL,
          genislik INT NULL,
          yukseklik INT NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY ux_medya_yol (yol),
          UNIQUE KEY ux_medya_hash (hash),
          KEY ix_medya_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
      ");
  },
  'down' => function(\PDO $pdo): void {
      $pdo->exec("DROP TABLE IF EXISTS medya;");
  },
];
