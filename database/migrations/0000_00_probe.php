<?php
return [
  'id'   => '0000_00_probe',
  'up'   => function (\PDO $pdo): void { $pdo->query('SELECT 1'); },
  'down' => function (\PDO $pdo): void {},
];
