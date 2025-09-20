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
  <a href="<?= BASE_URL ?>/admin" class="list-group-item list-group-item-action<?= $aktif('/admin', true) ?>">Gösterge Paneli</a>
  <a href="<?= BASE_URL ?>/admin/sayfalar" class="list-group-item list-group-item-action<?= $aktif('/admin/sayfalar') ?>">Sayfalar</a>
  <a href="<?= BASE_URL ?>/admin/yazilar" class="list-group-item list-group-item-action<?= $aktif('/admin/yazilar') ?>">Yazılar</a>
  <a href="<?= BASE_URL ?>/admin/kategoriler" class="list-group-item list-group-item-action<?= $aktif('/admin/kategoriler') ?>">Kategoriler</a>
  <a href="<?= BASE_URL ?>/admin/yonlendirmeler" class="list-group-item list-group-item-action<?= $aktif('/admin/yonlendirmeler') ?>">Yönlendirmeler</a>
  <a href="<?= BASE_URL ?>/admin/diller" class="list-group-item list-group-item-action<?= $aktif('/admin/diller') ?>">Diller</a>
  <a href="<?= BASE_URL ?>/admin/medya" class="list-group-item list-group-item-action<?= $aktif('/admin/medya') ?>">Medya</a>
  <a href="<?= BASE_URL ?>/admin/uyeler" class="list-group-item list-group-item-action<?= $aktif('/admin/uyeler') ?>">Üyeler</a>
</div>  <!-- 🔚 list-group burada kapanıyor -->

<!-- 🔽 Dock artık list-group'un kardeşi -->
<div class="theme-dock">
  <button type="button" class="theme-ico" data-set-theme="light" title="Açık" aria-label="Açık">
    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
      <path d="M12 18a6 6 0 1 0 0-12 6 6 0 0 0 0 12Zm0-16a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V3a1 1 0 0 1 1-1Zm0 18a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1ZM3 11h1a1 1 0 1 1 0 2H3a1 1 0 1 1 0-2Zm16 0h1a1 1 0 1 1 0 2h-1a1 1 0 1 1 0-2ZM5.05 5.05a1 1 0 0 1 1.41 0l.71.71a1 1 0 0 1-1.41 1.41l-.71-.71a1 1 0 0 1 0-1.41Zm11.78 11.78a1 1 0 0 1 1.41 0l.71.71a1 1 0 0 1-1.41 1.41l-.71-.71a1 1 0 0 1 0-1.41ZM18.95 5.05a1 1 0 0 1 0 1.41l-.71.71a1 1 0 1 1-1.41-1.41l.71-.71a1 1 0 0 1 1.41 0ZM6.46 17.54a1 1 0 0 1 0 1.41l-.71.71a1 1 0 0 1-1.41-1.41l.71-.71a1 1 0 0 1 1.41 0Z"/>
    </svg>
  </button>

  <button type="button" class="theme-ico" data-set-theme="dark" title="Koyu" aria-label="Koyu">
    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>
    </svg>
  </button>

  <button type="button" class="theme-ico" data-set-theme="auto" title="Otomatik" aria-label="Otomatik">
    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
      <path d="M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10h-2V5H5v10H3V5Zm-2 12h22v2H1v-2Z"/>
    </svg>
  </button>
</div>
