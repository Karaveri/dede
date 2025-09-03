<?php
// ZORUNLU: $bulkDurumUrl, $bulkSilUrl, $passiveAction, $trashLinkUrl
$BASE      = defined('BASE_URL') ? BASE_URL : '';
$isSayfa   = str_contains($bulkDurumUrl, '/sayfalar/');
$isKategori= str_contains($bulkDurumUrl, '/kategoriler/');

$aktifBtnId = $isSayfa ? 'bulkAktifBtnSayfa'  : 'bulkAktifBtn';
$pasifBtnId = $isSayfa ? 'bulkTaslakBtnSayfa': 'bulkPasifBtn';
$pasifText  = $isSayfa ? 'Seçilenleri Taslak Yap' : 'Seçilenleri Pasif Yap';
?>
<div  class="w-100 d-flex align-items-center gap-2 flex-wrap">

  <?php if ($isSayfa): ?>
    <!-- SAYFALAR: ortak .js-bulk akışı -->
    <button type="button"
            class="btn btn-sm btn-success js-bulk"
            data-aksiyon="aktif"
            data-url="<?= htmlspecialchars($bulkDurumUrl, ENT_QUOTES, 'UTF-8') ?>">
      Seçilenleri Aktif Yap
    </button>

    <button type="button"
            class="btn btn-sm btn-outline-secondary js-bulk"
            data-aksiyon="taslak"
            data-url="<?= htmlspecialchars($bulkDurumUrl, ENT_QUOTES, 'UTF-8') ?>">
      Seçilenleri Taslak Yap
    </button>

    <button type="button"
            class="btn btn-sm btn-danger js-bulk"
            data-aksiyon="sil"
            data-url="<?= htmlspecialchars($bulkSilUrl, ENT_QUOTES, 'UTF-8') ?>">
      Seçilenleri Sil
    </button>

  <?php else: ?>
    <!-- KATEGORİLER (ve diğerleri): genel .js-bulk dinleyicisi -->
    <button type="button"
            class="btn btn-sm btn-success js-bulk"
            data-aksiyon="aktif"
            data-url="<?= htmlspecialchars($bulkDurumUrl, ENT_QUOTES, 'UTF-8') ?>">
      Seçilenleri Aktif Yap
    </button>

    <button type="button"
            class="btn btn-sm btn-outline-secondary js-bulk"
            data-aksiyon="pasif"
            data-url="<?= htmlspecialchars($bulkDurumUrl, ENT_QUOTES, 'UTF-8') ?>">
      <?= $pasifText ?>
    </button>

    <button type="button"
            class="btn btn-sm btn-danger js-bulk"
            data-aksiyon="sil"
            data-url="<?= htmlspecialchars($bulkSilUrl, ENT_QUOTES, 'UTF-8') ?>">
      Seçilenleri Sil
    </button>
  <?php endif; ?>

  <a class="btn btn-sm btn-outline-dark ms-auto"
     href="<?= htmlspecialchars($trashLinkUrl, ENT_QUOTES, 'UTF-8') ?>">
    Çöp Kutusu
  </a>
</div>
