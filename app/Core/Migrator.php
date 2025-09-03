<?php declare(strict_types=1);
namespace App\Core;

use PDO;

final class Migrator
{
    private PDO $pdo;
    private string $path;

    public function __construct(PDO $pdo, string $migrationsPath)
    {
        $this->pdo  = $pdo;
        $this->path = rtrim($migrationsPath, '/');
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              name VARCHAR(255) NOT NULL UNIQUE,
              applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /** @return array<int,string> */
    private function applied(): array
    {
        return $this->pdo->query("SELECT name FROM migrations ORDER BY id")
                         ->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

    /** @return array<int,string> */
    private function files(): array
    {
        $files = glob($this->path . '/*.php') ?: [];
        sort($files);
        return $files;
    }

    public function up(?string $target = null): void
    {
        $applied = array_flip($this->applied());
        foreach ($this->files() as $file) {
            $name = basename($file);
            if (isset($applied[$name])) continue;
            $m = require $file; // anonymous class with up/down
            $this->pdo->beginTransaction();
            try {
                $m->up($this->pdo);
                $st = $this->pdo->prepare("INSERT INTO migrations (name,applied_at) VALUES (?,NOW())");
                $st->execute([$name]);
                $this->pdo->commit();
                echo "[UP]   $name\n";
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
            if ($target && $name === $target) break;
        }
    }

    public function down(int $steps = 1): void
    {
        $applied = $this->applied();
        for ($i = 0; $i < $steps && !empty($applied); $i++) {
            $name = array_pop($applied);
            $file = $this->path . '/' . $name;
            if (!is_file($file)) { echo "[SKIP] $name (file missing)\n"; continue; }
            $m = require $file;
            $this->pdo->beginTransaction();
            try {
                $m->down($this->pdo);
                $st = $this->pdo->prepare("DELETE FROM migrations WHERE name=?");
                $st->execute([$name]);
                $this->pdo->commit();
                echo "[DOWN] $name\n";
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }
    }

    public function status(): void
    {
        $applied = array_flip($this->applied());
        foreach ($this->files() as $file) {
            $name = basename($file);
            $mark = isset($applied[$name]) ? '✔' : '·';
            echo " $mark  $name\n";
        }
    }
}
