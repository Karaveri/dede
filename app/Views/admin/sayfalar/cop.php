<?php
/** @var array $sayfalar, $baslik */
$BASE = defined('BASE_URL') ? BASE_URL : '';
$csrf = \App\Core\Csrf::token();
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="m-0"><?= htmlspecialchars($baslik ?? 'Sayfalar – Çöp Kutusu') ?></h1>
    <a class="btn btn-outline-secondary" href="<?= $BASE ?>/admin/sayfalar">← Listeye Dön</a>
  </div>

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th style="width:36px"><input type="checkbox" id="secTum"></th>
          <th style="width:70px">ID</th>
          <th>Başlık</th>
          <th>Slug</th>
          <th style="width:110px">Durum</th>
          <th style="width:180px">Güncellenme</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($sayfalar ?? []) as $r): ?>
          <?php
            $id    = (int)($r['id'] ?? 0);
            $durum = (string)($r['durum'] ?? '');
            $etiket= in_array($durum, ['1','yayinda','yayında','aktif'], true) ? 'Aktif' : 'Taslak';
          ?>
          <tr data-id="<?= $id ?>">
            <td><input type="checkbox" class="sec-kayit" value="<?= $id ?>"></td>
            <td>#<?= $id ?></td>
            <td><?= htmlspecialchars($r['baslik'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['slug'] ?? '') ?></td>
            <td><span class="badge <?= $etiket==='Aktif' ? 'bg-success' : 'bg-secondary' ?>"><?= $etiket ?></span></td>
            <td><?= htmlspecialchars($r['guncelleme_tarihi'] ?? ($r['updated_at'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($sayfalar)): ?>
          <tr><td colspan="6" class="text-muted">Çöp kutusu boş.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="bg-warning-subtle border rounded p-2 d-flex gap-2 align-items-center mt-2">
    <input type="hidden" id="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

    <button type="button"
            class="btn btn-outline-primary btn-sm js-trash"
            data-url="<?= $BASE ?>/admin/sayfalar/geri-al"
            data-aksiyon="geri-al">
      Seçilenleri Geri Al
    </button>

    <button type="button"
            class="btn btn-outline-danger btn-sm js-trash"
            data-url="<?= $BASE ?>/admin/sayfalar/yok-et"
            data-aksiyon="kalici">
      Seçilenleri Kalıcı Sil
    </button>
  </div>
</div>
