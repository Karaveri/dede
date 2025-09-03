<?php
error_reporting(E_ALL); ini_set('display_errors', '1');
require __DIR__ . '/../config/config.php';
try {
    $port = defined('DB_PORT') ? (int)DB_PORT : 3306;
    $dsn  = 'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "OK: Baglandi<br>";
    echo "Server: " . $pdo->query('select version()')->fetchColumn();
} catch (Throwable $e) {
    echo "ERR: " . $e->getMessage();
}