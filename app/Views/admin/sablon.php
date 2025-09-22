<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Yönetim</title>
  <script>
  (() => {
    const key   = 'theme'; // 'light' | 'dark' | 'auto'
    const pref  = localStorage.getItem(key) || 'auto';
    const sys   = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    const theme = (pref === 'auto') ? sys : pref;
    document.documentElement.setAttribute('data-bs-theme', theme);
  })();
  </script> 

  <!-- Bootstrap 5.3.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

  <!-- Admin CSS -->
  <link href="<?= BASE_URL ?>/css/yonetim.css" rel="stylesheet">

  <base href="<?= BASE_URL ?>/" />
  <?php $csrfToken = \App\Core\Csrf::token(); ?>
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <!-- Geri uyumluluk: eski JS helper'lar için -->
  <meta name="csrf" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="base" content="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'), ENT_QUOTES) ?>">
</head>
<body>

  <?php require __DIR__ . '/ust_cubuk.php'; ?>

  <div class="container-fluid">
    <div class="row">
      <aside class="col-lg-2 d-none d-lg-block bg-white border-end min-vh-100 p-0 d-flex flex-column">
        <?php require __DIR__ . '/sol_menu.php'; ?>
      </aside>
      <main class="col-12 col-lg-10 p-3">
        <?php require $gorunumYolu; ?>
      </main>
    </div>
  </div>

<!-- Silme / Onay Modali -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Onay gerekiyor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">
        <p id="confirmModalMsg" class="m-0">Bu işlemi yapmak istediğinize emin misiniz?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
        <button type="button" class="btn btn-danger" id="confirmModalOk">Evet, onaylıyorum</button>
      </div>
    </div>
  </div>
</div>

<!-- Toast container -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>

  <script src="<?= BASE_URL ?>/js/yonetim.js"></script>

  <!-- Tema menüsü: tıklama + otomatik mod dinleyicisi -->
<script>
(function () {
  const KEY = 'theme'; // 'light' | 'dark' | 'auto'

  const compute = (pref) =>
    pref === 'auto'
      ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
      : (pref === 'dark' ? 'dark' : 'light');

  function apply(pref, save=true){
    const theme = compute(pref);
    document.documentElement.setAttribute('data-bs-theme', theme);
    if (save) localStorage.setItem(KEY, pref);

    // aktif ikon
    document.querySelectorAll('.theme-dock .theme-ico').forEach(b=>{
      b.classList.toggle('active', b.getAttribute('data-set-theme') === pref);
    });
  }

  // İlk yük
  apply(localStorage.getItem(KEY) || 'auto', false);

  // Tıklama
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.theme-dock .theme-ico');
    if (!btn) return;
    apply(btn.getAttribute('data-set-theme'), true);
  });

  // Otomatikte sistem teması değişirse
  const mm = matchMedia('(prefers-color-scheme: dark)');
  mm.addEventListener?.('change', ()=>{
    if ((localStorage.getItem(KEY) || 'auto') === 'auto') apply('auto', false);
  });
})();
</script>

<?php
  // Geçerli yol (URL) — koşullu yükleme için
  $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';
?>

<?php // Kategoriler ekranlarında onay modali yüklü olsun ?>
<?php if (preg_match('#/admin/kategoriler(?:/|$)#', $reqPath)): ?>
  <?php require dirname(__FILE__) . '/partials/onay_modal.php'; ?>
<?php endif; ?>

<?php // Bootstrap bundle (yüklü değilse ekleyelim) ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<?php // Kategoriler JS: çöp/liste/form davranışları ?>
<?php if (preg_match('#/admin/kategoriler(?:/|$)#', $reqPath)): ?>
  <script src="<?= BASE_URL ?>/js/kategoriler.js?v=20250922"></script>
<?php endif; ?>

</body>
</html>
