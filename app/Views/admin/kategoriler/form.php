<?php
use App\Core\Csrf;
$mesaj = $_SESSION['mesaj'] ?? null; unset($_SESSION['mesaj']);
$hata  = $_SESSION['hata']  ?? null; unset($_SESSION['hata']);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h5 m-0"><?= isset($kategori) ? 'Kategori Düzenle' : 'Yeni Kategori' ?></h1>
  <a href="<?= BASE_URL ?>/admin/kategoriler" class="btn btn-sm btn-outline-secondary">Geri</a>
</div>

<?php if ($mesaj): ?><div class="alert alert-success py-2"><?=$mesaj?></div><?php endif; ?>
<?php if ($hata):  ?><div class="alert alert-danger  py-2"><?=$hata?></div><?php endif; ?>

<?php
$actionUrl = isset($kategori)
  ? BASE_URL . '/admin/kategoriler/guncelle'
  : BASE_URL . '/admin/kategoriler/kaydet';
?>
<form action="<?php echo htmlspecialchars($actionUrl, ENT_QUOTES); ?>" method="post" class="needs-validation" novalidate>
  <?= \App\Core\Csrf::input(); ?>
  <?php if (isset($kategori)): ?>
    <input type="hidden" name="id" value="<?= (int)$kategori['id'] ?>">
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-md-6">
      <label for="ad" class="form-label">Ad</label>
      <input type="text" class="form-control" id="kategoriAd" name="ad" required value="<?= isset($kategori) ? htmlspecialchars($kategori['ad']) : '' ?>">
      <div class="invalid-feedback">Ad gerekli.</div>
    </div>

    <div class="col-12 col-md-6">
      <label for="slug" class="form-label">Slug</label>
      <input type="text"
             class="form-control"
             id="kategoriSlug"
             name="slug"
             value="<?= isset($kategori) ? htmlspecialchars($kategori['slug']) : '' ?>"
             placeholder="Boş bırakırsan otomatik oluşur"
             data-check-url="<?= $BASE ?>/admin/kategoriler/slug-kontrol"
             data-id="<?= isset($kategori) ? (int)$kategori['id'] : 0 ?>">
      <div class="invalid-feedback">Bu slug kullanılıyor.</div>
      <div class="form-text">Küçük harf, harf-rakam ve tire kullan.</div>
    </div>

    <div class="col-12 col-md-6">
      <label for="ust_id" class="form-label">Üst Kategori</label>
      <select class="form-select" id="ust_id" name="ust_id">
        <option value="">— Yok —</option>
        <?php foreach (($ustKategoriler ?? []) as $e): ?>
          <?php if (isset($kategori) && (int)$e['id'] === (int)$kategori['id']) continue; ?>
          <option value="<?= (int)$e['id'] ?>" <?= isset($kategori) && (int)($kategori['parent_id'] ?? 0) === (int)$e['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($e['ad']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12">
      <label for="aciklama" class="form-label">Açıklama</label>
      <textarea class="form-control" id="aciklama" name="aciklama" rows="3" placeholder="İsteğe bağlı."><?= isset($kategori) ? htmlspecialchars($kategori['aciklama'] ?? '') : '' ?></textarea>
    </div>

    <div class="col-12">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" value="1" id="durum" name="durum" <?= isset($kategori) ? ((int)($kategori['durum'] ?? 1)===1?'checked':'') : 'checked' ?>>
        <label class="form-check-label" for="durum">Aktif</label>
      </div>
    </div>
  </div>

  <div class="mt-3 d-flex gap-2">
    <button type="submit" class="btn btn-primary"><?= isset($kategori) ? 'Güncelle' : 'Kaydet' ?></button>
    <a href="<?= BASE_URL ?>/admin/kategoriler" class="btn btn-outline-secondary">Vazgeç</a>
  </div>
</form>

<script>
(()=>{ const forms=document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(f=>f.addEventListener('submit',e=>{
    if(!f.checkValidity()){e.preventDefault();e.stopPropagation()} f.classList.add('was-validated');
  }));
})();
</script>