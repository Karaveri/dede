<?php
// Değişkenler: $baslik, $diller, $_token, BASE_URL
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 mb-0"><?= htmlspecialchars($baslik ?? 'Diller') ?></h1>
  <a href="<?= BASE_URL ?>/admin/diller/yeni" class="btn btn-primary">
    <span class="me-1">+</span> Yeni Dil
  </a>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Kod</th>
          <th>Ad</th>
          <th class="text-center">Aktif</th>
          <th class="text-center">Varsayılan</th>
          <th class="text-end">Sıra</th>
          <th class="text-end">İşlem</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!empty($diller)): foreach ($diller as $d): ?>
        <tr>
          <td><span class="badge bg-secondary-subtle border text-uppercase"><?= htmlspecialchars($d['kod']) ?></span></td>
          <td><?= htmlspecialchars($d['ad']) ?></td>
          <td class="text-center"><?= !empty($d['aktif']) ? 'Evet' : 'Hayır' ?></td>
          <td class="text-center"><?= !empty($d['varsayilan']) ? 'Evet' : 'Hayır' ?></td>
          <td class="text-end"><?= (int)($d['sira'] ?? 0) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/admin/diller/duzenle?kod=<?= urlencode($d['kod']) ?>">Düzenle</a>
            <button type="button"
              class="btn btn-sm btn-outline-danger ms-1 js-dil-sil"
              data-kod="<?= htmlspecialchars($d['kod']) ?>"
              data-token="<?= htmlspecialchars($_token ?? '') ?>">
              Sil
            </button>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="6" class="text-center text-muted py-4">Kayıt yok.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="<?= BASE_URL ?>/js/diller.js"></script>