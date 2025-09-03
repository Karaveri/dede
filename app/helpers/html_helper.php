<?php
// Basit allow-list HTML temizleyici (DOMDocument)
if (!function_exists('html_saf')) {
    function html_saf(string $html): string
    {
        $allowTags = [
            'p','br','hr','h1','h2','h3','h4','h5','h6',
            'ul','ol','li','blockquote','strong','em','b','i','u','code','pre',
            'a','img','figure','figcaption','table','thead','tbody','tr','th','td'
        ];
        $allowAttrs = [
            'a'   => ['href','title','rel','target'],
            'img' => ['src','alt','title','width','height','loading'],
            '*'   => ['class'] // sınıfı korumak isteyebilirsin
        ];
        // boşsa hızlı dön
        if (trim($html) === '') return '';

        $doc = new DOMDocument();
        // libxml hatalarını gizle
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);

        // Tüm elementleri dolaş
        foreach (iterator_to_array($doc->getElementsByTagName('*')) as $el) {
            $tag = strtolower($el->nodeName);
            if (!in_array($tag, $allowTags, true)) {
                // izinli değilse sadece içeriğini bırak
                $el->parentNode?->replaceChild($doc->createTextNode($el->textContent ?? ''), $el);
                continue;
            }
            // izinli ise attribute temizle
            if ($el->hasAttributes()) {
                $toRemove = [];
                foreach ($el->attributes as $attr) {
                    $name = strtolower($attr->nodeName);
                    $ok = in_array($name, $allowAttrs[$tag] ?? [], true)
                       || in_array($name, $allowAttrs['*'] ?? [], true);
                    // javascript: / data: (img hariç) yasak
                    $val = trim($attr->nodeValue ?? '');
                    $isBadProto = stripos($val, 'javascript:') === 0
                               || (stripos($val, 'data:') === 0 && $tag !== 'img');
                    if (!$ok || $isBadProto) $toRemove[] = $name;
                }
                foreach ($toRemove as $n) { $el->removeAttribute($n); }
            }
        }

        // a rel/target zırhı
        foreach ($xpath->query('//a') as $a) {
            $href = $a->getAttribute('href');
            if ($href && preg_match('~^https?://~i', $href)) {
                if (!$a->getAttribute('rel'))    $a->setAttribute('rel','nofollow noopener noreferrer');
                if (!$a->getAttribute('target')) $a->setAttribute('target','_blank');
            }
        }

        // img lazy
        foreach ($xpath->query('//img') as $img) {
            if (!$img->getAttribute('loading')) $img->setAttribute('loading','lazy');
        }

        return trim($doc->saveHTML());
    }
}
