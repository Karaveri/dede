<?php
use App\Core\Database;

// Eski -> yeni yol için yönlendirme kaydet
if (!function_exists('yonlendir_kaydet')) {
    /**
     * @param string $kaynak Örn: "/eski-yol"
     * @param string $hedef  Örn: "/yeni-yol" ya da "https://..."
     * @param int    $tip    301 varsayılan
     * @return bool
     */
    function yonlendir_kaydet(string $kaynak, string $hedef, int $tip = 301): bool
    {
        $kaynak = '/' . ltrim($kaynak, '/');
        // hedef absolute değilse slash ile normalize et
        if (!preg_match('~^https?://~i', $hedef)) {
            $hedef = '/' . ltrim($hedef, '/');
        }
        if ($kaynak === $hedef) return false;

        $pdo = Database::baglanti();

        // Aynı kaynak için aynı hedef zaten varsa tekrar eklemeyelim
        $chk = $pdo->prepare("SELECT id FROM yonlendirmeler WHERE kaynak = :k AND hedef = :h AND aktif = 1 LIMIT 1");
        $chk->execute([':k' => $kaynak, ':h' => $hedef]);
        if ($chk->fetchColumn()) return true;

        $st = $pdo->prepare("
            INSERT INTO yonlendirmeler (kaynak, hedef, tip, aktif, olusturuldu)
            VALUES (:k, :h, :t, 1, NOW())
        ");
        return $st->execute([':k' => $kaynak, ':h' => $hedef, ':t' => $tip]);
    }
}
