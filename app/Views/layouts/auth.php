<?php
// app/Views/layouts/auth.php
// Sadece giriş/şifre sıfırlama gibi sayfalar için yalın layout.

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$title = isset($title) ? $title . ' - Yönetim' : 'Yönetim - Giriş';
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- Bootstrap CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <meta name="csrf-token" content="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

  <style>
    body { background:#ffe4c4; }
  </style>
</head>
<body>

<?php
// Giriş formu görünümünün yolu controller tarafından $gorunumYolu ile verilir.
if (!isset($gorunumYolu) || !is_file($gorunumYolu)) {
  echo '<div class="container py-5"><div class="alert alert-warning">Görünüm bulunamadı.</div></div>';
} else {
  require $gorunumYolu;
}
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
</body>
</html>
