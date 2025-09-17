<?php
// app/Views/admin/layout.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$base    = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';

$is = function (string $prefix) use ($reqPath): string {
    $p = $prefix;
    if ($p !== '/' && substr($p, -1) === '/') $p = rtrim($p, '/');
    $pattern = '#^' . preg_quote($p, '#') . '(?:/|$)#';
    return preg_match($pattern, $reqPath) ? 'active text-white' : '';
};

$mesaj = $_SESSION['mesaj'] ?? null; unset($_SESSION['mesaj']);
$hata  = $_SESSION['hata']  ?? null; unset($_SESSION['hata']);
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= isset($title) ? htmlspecialchars($title) . ' - Yönetim' : 'Yönetim' ?></title>
  <meta name="base" content="<?= htmlspecialchars(rtrim(BASE_URL, '/'), ENT_QUOTES) ?>">
  <meta name="csrf" content="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES) ?>">
  <!-- Bootstrap CDN (yol derdi yok) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

  <style>
    body { background:#f7f7f9; }
    .sidebar { min-height: 100vh; background:#fff; border-right:1px solid #e9ecef; }
    .sidebar .list-group-item { border:0; border-bottom:1px solid #eef2f5; }
    .sidebar .list-group-item.active { background:#0d6efd; }
    .content { padding:16px; }
    @media (max-width: 991.98px) { .sidebar { min-height:auto; } }
  </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark">
  <div class="container-fluid">
    <span class="navbar-brand mb-0 h1">Yönetim</span>
    <a href="<?= $base ?>/admin" class="btn btn-sm btn-outline-light">Gösterge Paneli</a>
  </div>
</nav>

<div class="container-fluid">
  <div class="row">
    <!-- SOL MENÜ (ORTAK) -->
    <aside class="col-12 col-lg-2 sidebar p-0">
      <div class="list-group list-group-flush">
        <a class="list-group-item list-group-item-action <?= (rtrim($reqPath,'/') === rtrim($base.'/admin','/') ? 'active text-white' : '') ?>"
            href="<?= $base ?>/admin">Gösterge Paneli</a>

        <a class="list-group-item list-group-item-action <?= $is($base.'/admin/sayfalar') ?>"
           href="<?= $base ?>/admin/sayfalar">Sayfalar</a>

        <a class="list-group-item list-group-item-action <?= $is($base.'/admin/yazilar') ?>"
           href="<?= $base ?>/admin/yazilar">Yazılar</a>

        <a class="list-group-item list-group-item-action <?= $is($base.'/admin/kategoriler') ?>"
           href="<?= $base ?>/admin/kategoriler">Kategoriler</a>

        <a class="list-group-item list-group-item-action <?= $is($base.'/admin/medya') ?>"
           href="<?= $base ?>/admin/medya">Medya</a>

        <a class="list-group-item list-group-item-action <?= $is($base.'/admin/uyeler') ?>"
           href="<?= $base ?>/admin/uyeler">Üyeler</a>
      </div>
    </aside>

    <!-- İÇERİK -->
    <main class="col-12 col-lg-10 content">
      <?php if ($mesaj): ?><div class="alert alert-success"><?= htmlspecialchars($mesaj) ?></div><?php endif; ?>
      <?php if ($hata):  ?><div class="alert alert-danger"><?= htmlspecialchars($hata)  ?></div><?php endif; ?>

      <?php
      if (!isset($gorunumYolu) || !is_file($gorunumYolu)) {
        echo '<div class="alert alert-warning">Görünüm bulunamadı.</div>';
      } else {
        require $gorunumYolu;
      }
      ?>
    </main>
  </div>
</div>

<!-- Bootstrap JS CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
</script>

</body>
</html>
