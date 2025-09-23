<?php
// Beklenen değişkenler: $action, $placeholder, $listUrl, $trashUrl, $newUrl, $newLabel
$BASE = defined('BASE_URL') ? BASE_URL : '';
$qVal = htmlspecialchars($_GET['q'] ?? ($q ?? ''), ENT_QUOTES, 'UTF-8');
?>
<form action="<?= $action ?>" method="get" class="w-90 d-flex align-items-center gap-2">
  <div class="input-group" style="min-width:260px; max-width:420px;">
    <input name="q" value="<?= $qVal ?>" type="search" class="form-control" placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>">
    <button class="btn btn-outline-secondary" type="submit">Ara</button>
  </div>

  <a href="<?= $listUrl  ?>"  class="btn btn-outline-dark liste">Liste</a>
  <a class="btn btn-outline-secondary copkutu" href="<?= $trashUrl ?>" id="copLink" data-count-url="<?= $trashUrl ?>/say">
    Çöp
    <i class="badge text-bg-danger ms-1" id="copBadge">0</i>
  </a>
  <a href="<?= $newUrl  ?>"  class="btn btn-primary yenikat"><?= htmlspecialchars($newLabel, ENT_QUOTES, 'UTF-8') ?></a>
</form>

<!-- Onay Modali (yonetim.js ile uyumlu) -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Onay</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body" id="confirmModalMsg">Bu işlemi onaylıyor musunuz?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
        <button type="button" class="btn btn-primary" id="confirmModalOk">Onayla</button>
      </div>
    </div>
  </div>
</div>