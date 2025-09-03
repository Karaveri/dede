<?php

// Metinden URL-dostu slug üretir
if (!function_exists('slugify')) {
    function slugify(string $s): string {
        $s = trim($s);

        // TR karakterleri (JS simpleSlugify ile aynı eşlem)
        $map = [
            'Ğ'=>'g','Ü'=>'u','Ş'=>'s','İ'=>'i','I'=>'i','Ö'=>'o','Ç'=>'c',
            'ğ'=>'g','ü'=>'u','ş'=>'s','ı'=>'i','ö'=>'o','ç'=>'c'
        ];
        $s = strtr($s, $map);

        // Diakritik kaldırma (NFD → Mn temizle) ya da iconv ile transliterasyon
        if (class_exists('\Normalizer')) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
            // \p{Mn} = Nonspacing_Mark (aksanlar)
            $s = preg_replace('/\p{Mn}+/u', '', $s);
        } elseif (function_exists('iconv')) {
            $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($tmp !== false) $s = $tmp;
        }

        $s = mb_strtolower($s, 'UTF-8');

        // JS: .replace(/[^a-z0-9\- ]/g, ' ')
        $s = preg_replace('/[^a-z0-9\- ]+/u', ' ', $s);

        $s = trim($s);
        // JS: .replace(/\s+/g, '-')
        $s = preg_replace('/\s+/', '-', $s);
        // JS: .replace(/\-+/g, '-')
        $s = preg_replace('/-+/', '-', $s);

        return trim($s, '-');
    }
}

// Rezerve (yasak) slug kelimeleri
if (!function_exists('slug_is_reserved')) {
    function slug_is_reserved(string $slug): bool {
        // Config'ten al; yoksa varsayılan listeyi kullan
        $list = defined('RESERVED_SLUGS') && is_array(RESERVED_SLUGS)
            ? RESERVED_SLUGS
            : [
                'admin','login','logout','register','kayit','giris',
                'api','assets','uploads','media','medya','sitemap','robots',
                'feed','rss','search','arama','index','default'
            ];

        // Karşılaştırma güvenli olsun diye listeyi normalize et
        static $normalized = null;
        if ($normalized === null) {
            $normalized = array_values(array_unique(array_map(
                fn($s) => trim(mb_strtolower((string)$s, 'UTF-8')),
                $list
            )));
        }

        $slug = trim(mb_strtolower($slug, 'UTF-8'));
        return in_array($slug, $normalized, true);
    }
}

// Veritabanında benzersiz hale getirir
if (!function_exists('slug_benzersiz')) {
    function slug_benzersiz(string $slug, string $tablo, int $haricId = 0, string $kolon = 'slug'): string {
        if (slug_is_reserved($slug)) {
            $slug .= '-1';
        }

        $pdo = \App\Core\Database::baglanti();

        $orijinal = $slug;
        $i = 1;

        $sql = "SELECT id FROM {$tablo} WHERE {$kolon} = :slug"
             . ($haricId > 0 ? " AND id <> :id" : "")
             . " LIMIT 1";
        $st = $pdo->prepare($sql);

        while (true) {
            $params = [':slug' => $slug];
            if ($haricId > 0) $params[':id'] = $haricId;

            $st->execute($params);
            $varMi = $st->fetchColumn();

            if (!$varMi) break;

            $i++;
            $slug = $orijinal . '-' . $i;
        }

        return $slug;
    }
}

// Tek adımda güvenli üretim (metinden üret + benzersizleştir)
if (!function_exists('slug_guvenli')) {
    function slug_guvenli(string $metin, string $tablo, int $haricId = 0, string $kolon = 'slug'): string {
        $base = slugify($metin);
        return slug_benzersiz($base, $tablo, $haricId, $kolon);
    }
}
