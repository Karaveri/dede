<?php declare(strict_types=1);

return [
  'id' => '20250912_001_etiketler',
  'up' => function(\PDO $pdo): void {
      // Etiket ana tablosu
      $pdo->exec("
          CREATE TABLE IF NOT EXISTS etiketler (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              slug VARCHAR(64) NOT NULL,
              ad   VARCHAR(100) NOT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY ux_etiketler_slug (slug)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
      ");
  },
  'down' => function(\PDO $pdo): void {
      // Medya_etiket önceki migration’da düşürüleceği için burada sadece etiketler
      $pdo->exec("DROP TABLE IF EXISTS etiketler;");
  },
];
