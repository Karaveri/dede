<?php
// Geçerli path (query’siz)
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$base = rtrim(BASE_URL, '/');
// Uygulama içi yol (BASE_URL’i düşür)
$here = preg_replace('#^' . preg_quote($base, '#') . '#', '', $path) ?: '/';

// Aktif sınıfı: dashboard tam eşleşme, diğerleri prefix eşleşme
$aktif = function (string $route, bool $exact = false) use ($here): string {
    $r = rtrim($route, '/');
    if ($exact)  return ($here === $r || $here === $r . '/') ? ' active' : '';
    return ($here === $r || strpos($here, $r . '/') === 0) ? ' active' : '';
};
?>
<div class="list-group list-group-flush sol-menu">
  <a href="<?= BASE_URL ?>/admin"
     class="list-group-item list-group-item-action<?= $aktif('/admin', true) ?>">Gösterge Paneli</a>

  <a href="<?= BASE_URL ?>/admin/sayfalar"
     class="list-group-item list-group-item-action<?= $aktif('/admin/sayfalar') ?>">Sayfalar</a>

  <a href="<?= BASE_URL ?>/admin/yazilar"
     class="list-group-item list-group-item-action<?= $aktif('/admin/yazilar') ?>">Yazılar</a>

  <a href="<?= BASE_URL ?>/admin/kategoriler"
     class="list-group-item list-group-item-action<?= $aktif('/admin/kategoriler') ?>">Kategoriler</a>

  <a href="<?= BASE_URL ?>/admin/yonlendirmeler"
     class="list-group-item list-group-item-action<?= $aktif('/admin/yonlendirmeler') ?>">Yönlendirmeler</a>     

  <a href="<?= BASE_URL ?>/admin/medya"
     class="list-group-item list-group-item-action<?= $aktif('/admin/medya') ?>">Medya</a>

  <a href="<?= BASE_URL ?>/admin/uyeler"
     class="list-group-item list-group-item-action<?= $aktif('/admin/uyeler') ?>">Üyeler</a>
</div>
