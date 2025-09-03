<?php
// app/Views/giris/form.php
use App\Core\Csrf;
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$BASE  = rtrim(BASE_URL, '/');
$hata  = $_SESSION['hata'] ?? null; unset($_SESSION['hata']);
$mesaj = $_SESSION['mesaj'] ?? null; unset($_SESSION['mesaj']);

$csrf = Csrf::token();
?>

<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <title>Yönetim Girişi</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- CDN: Projenin layout’una bağımlı kalmadan CSS gelsin -->
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous">
  <style>
    body{background:#f6f7fb}
  </style>
</head>
<body>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5">
      <div class="card shadow-sm">
        <div class="card-body">
          <h1 class="h4 text-center mb-3">Yönetim Girişi</h1>

          <?php
          // RateLimit geri sayımı için kalan saniye
          if (session_status() !== PHP_SESSION_ACTIVE) session_start();
          $left = 0;
          if (!empty($_SESSION['rate_reset_at'])) {
              $left = max(0, (int)$_SESSION['rate_reset_at'] - time());
              if ($left <= 0) unset($_SESSION['rate_reset_at']);
          }
          ?>          

          <?php
          // Öncelik: RateLimit aktifse uyarıyı kalıcı göster + geri sayım verisini ata
          if ($left > 0) {
              $msg = 'Çok fazla deneme. Lütfen ' . $left . ' saniye sonra tekrar deneyin.';
              echo '<div class="alert alert-danger py-2 mb-3" id="login-rate-alert" data-left="'.(int)$left.'">'
                 . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
          }
          // RateLimit yoksa normal hata/mesajları göster
          else {
              if (!empty($hata)) {
                  echo '<div class="alert alert-danger py-2 mb-3">'.htmlspecialchars($hata, ENT_QUOTES, 'UTF-8').'</div>';
              }
              if (!empty($mesaj)) {
                  echo '<div class="alert alert-success py-2 mb-3">'.htmlspecialchars($mesaj, ENT_QUOTES, 'UTF-8').'</div>';
              }
              if (!empty($_SESSION['hata'])) {
                  echo '<div class="alert alert-danger py-2 mb-3">'.htmlspecialchars($_SESSION['hata'], ENT_QUOTES, 'UTF-8').'</div>';
                  unset($_SESSION['hata']);
              }
              if (!empty($_SESSION['mesaj'])) {
                  echo '<div class="alert alert-success py-2 mb-3">'.htmlspecialchars($_SESSION['mesaj'], ENT_QUOTES, 'UTF-8').'</div>';
                  unset($_SESSION['mesaj']);
              }
          }
          ?>

          <form method="post" action="<?= $BASE ?>/admin/giris" class="needs-validation" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-3">
              <label class="form-label">E-posta</label>
              <input type="email" name="email" class="form-control" required>
              <div class="invalid-feedback">E-posta gerekli.</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Şifre</label>
              <input type="password" name="sifre" class="form-control" required>
              <div class="invalid-feedback">Şifre gerekli.</div>
            </div>

            <button class="btn btn-primary w-100" type="submit">Giriş Yap</button>
            <script>
            (function () {
              var al = document.getElementById('login-rate-alert');
              if (!al) return;

              var left = parseInt(al.getAttribute('data-left') || '0', 10);
              if (!left) return; // RateLimit yoksa sayaç yok

              var form = document.querySelector('form[action*="/admin/giris"]');
              var btn  = form ? form.querySelector('button[type="submit"]') : null;
              if (btn) btn.disabled = true;

              function tick() {
                if (left <= 0) {
                  if (btn) btn.disabled = false;
                  al.textContent = 'Tekrar deneyebilirsiniz.';
                  return;
                }
                al.textContent = 'Çok fazla deneme. Lütfen ' + left + ' saniye sonra tekrar deneyin.';
                left--;
                setTimeout(tick, 1000);
              }
              tick();
            })();
            </script>          
          </form>
        </div>
        <div class="card-footer text-center small">&copy; <?= date('Y') ?> PHP Projem</div>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(f => f.addEventListener('submit', e => {
    if (!f.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
    f.classList.add('was-validated');
  }));
})();
</script>

</body>
</html>
