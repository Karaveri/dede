<?php
return new class {
    public function up(PDO $pdo): void {
        // idempotent guard
        $pdo->exec("ALTER TABLE kategoriler ADD UNIQUE KEY uq_kategoriler_slug (slug)");
        $pdo->exec("ALTER TABLE kategoriler ADD INDEX ix_kategoriler_parent (parent_id)");
        $pdo->exec("ALTER TABLE sayfalar   ADD UNIQUE KEY uq_sayfalar_slug   (slug)");
    }
    public function down(PDO $pdo): void {
        // drop if exists guards (MySQL 8+)
        @ $pdo->exec("ALTER TABLE kategoriler DROP INDEX uq_kategoriler_slug");
        @ $pdo->exec("ALTER TABLE kategoriler DROP INDEX ix_kategoriler_parent");
        @ $pdo->exec("ALTER TABLE sayfalar   DROP INDEX uq_sayfalar_slug");
    }
};
