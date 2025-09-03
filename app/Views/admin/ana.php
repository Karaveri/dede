<?php
// app/Views/admin/ana.php
$mesaj = $_SESSION['mesaj'] ?? null; unset($_SESSION['mesaj']);
$hata  = $_SESSION['hata']  ?? null; unset($_SESSION['hata']);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 m-0">Gösterge Paneli</h1>
  <span class="text-muted small">
    <?= htmlspecialchars($_SESSION['uye_email'] ?? 'admin@panel') ?>
  </span>
</div>

<?php if ($mesaj): ?><div class="alert alert-success py-2"><?= htmlspecialchars($mesaj) ?></div><?php endif; ?>
<?php if ($hata):  ?><div class="alert alert-danger  py-2"><?= htmlspecialchars($hata)  ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-sm-6 col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fw-semibold">Hoş geldiniz 👋</div>
        <div class="text-muted small">Soldaki menüden modüllere geçebilirsiniz.</div>
      </div>
    </div>
  </div>
</div>