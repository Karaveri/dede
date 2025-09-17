<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Yönetim</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/css/yonetim.css" rel="stylesheet">
  <base href="<?= BASE_URL ?>/" />
  <?php $csrfToken = \App\Core\Csrf::token(); ?>
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <!-- Geri uyumluluk: eski JS helper'lar için -->
  <meta name="csrf" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="base" content="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'), ENT_QUOTES) ?>">
</head>
<body class="bg-light">

  <?php require __DIR__ . '/ust_cubuk.php'; ?>

  <div class="container-fluid">
    <div class="row">
      <aside class="col-lg-2 d-none d-lg-block bg-white border-end min-vh-100 p-0">
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= BASE_URL ?>/js/yonetim.js"></script>
</body>
</html>
