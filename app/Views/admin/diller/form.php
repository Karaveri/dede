<?php
// Değişkenler: $baslik, $kayit, $_token, BASE_URL
$isEdit = isset($kayit) && $kayit;
$kod   = $isEdit ? ($kayit['kod'] ?? '') : '';
$ad    = $isEdit ? ($kayit['ad']  ?? '') : '';
$aktif = $isEdit ? (int)($kayit['aktif'] ?? 1) : 1;
$vars  = $isEdit ? (int)($kayit['varsayilan'] ?? 0) : 0;
$sira  = $isEdit ? (int)($kayit['sira'] ?? 0) : 0;
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 mb-0"><?= htmlspecialchars($baslik ?? ($isEdit ? 'Dili Düzenle' : 'Yeni Dil')) ?></h1>
  <a href="<?= BASE_URL ?>/admin/diller" class="btn btn-light border">Listeye dön</a>
</div>

<form id="dilForm" class="card shadow-sm p-3">
  <input type="hidden" name="_token" value="<?= htmlspecialchars($_token ?? '') ?>">
  <?php if($isEdit): ?>
    <input type="hidden" name="_mevcut_kod" value="<?= htmlspecialchars($kod) ?>">
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-3">
      <label class="form-label">Kod</label>
      <input type="text" name="kod" class="form-control" maxlength="10" value="<?= htmlspecialchars($kod) ?>" placeholder="tr, en-US" <?= $isEdit ? 'readonly' : '' ?>>
      <div class="form-text">Örn: <code>tr</code>, <code>en-US</code>.</div>
    </div>

    <div class="col-md-5">
      <label class="form-label">Ad</label>
      <input type="text" name="ad" class="form-control" maxlength="50" value="<?= htmlspecialchars($ad) ?>" placeholder="Türkçe">
    </div>

    <div class="col-md-2">
      <label class="form-label">Sıra</label>
      <input type="number" name="sira" class="form-control" value="<?= (int)$sira ?>">
    </div>

    <div class="col-md-2 d-flex align-items-end">
      <div class="form-check me-3">
        <input class="form-check-input" type="checkbox" name="aktif" id="fAktif" <?= $aktif ? 'checked' : '' ?>>
        <label class="form-check-label" for="fAktif">Aktif</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="varsayilan" id="fVars" <?= $vars ? 'checked' : '' ?>>
        <label class="form-check-label" for="fVars">Varsayılan</label>
      </div>
    </div>
  </div>

  <div class="mt-3 text-end">
    <button class="btn btn-primary" type="submit">Kaydet</button>
  </div>
</form>

<script src="<?= BASE_URL ?>/js/diller.js"></script>
