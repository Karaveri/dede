<?php
/** @var array $liste */
/** @var string $baslik */
$csrf = $_SESSION['csrf'] ?? '';
$BASE = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="container">
    <h1 class="mb-3"><?= htmlspecialchars($baslik) ?></h1>
    <form method="get" class="mb-3" action="<?= $BASE ?>/admin/yonlendirmeler" style="display:flex;gap:.5rem;max-width:640px">
      <input type="text" class="form-control" name="q"
             value="<?= isset($q) ? htmlspecialchars($q, ENT_QUOTES, 'UTF-8') : '' ?>"
             placeholder="Kaynak veya hedef slug ara">
      <button class="btn btn-outline-secondary araust" type="submit">Ara</button>
      <?php if (!empty($q)): ?>
        <a class="btn btn-outline-light" href="<?= $BASE ?>/admin/yonlendirmeler">Temizle</a>
      <?php endif; ?>
    </form>

    <form method="post" action="<?= $BASE ?>/admin/yonlendirmeler/toplu-sil" onsubmit="return confirm('Seçili kayıtlar silinsin mi?')">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

	    <?php if (!empty($_SESSION['mesaj'])): ?>
	        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['mesaj']) ?></div>
	        <?php unset($_SESSION['mesaj']); endif; ?>
	    <?php if (!empty($_SESSION['hata'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['hata']) ?></div>
        <?php unset($_SESSION['hata']); endif; ?>

    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th style="width:36px"><input type="checkbox" id="chk-all"></th>
                    <th style="width:70px">ID</th>
                    <th>Kaynak (eski)</th>
                    <th>Hedef (yeni)</th>
                    <th style="width:80px">Tip</th>
                    <th style="width:140px">Tarih</th>
                    <th style="width:120px">Durum</th>
                    <th style="width:120px">İşlem</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($liste as $r): ?>
                <tr data-id="<?= (int)$r['id'] ?>">
                    <td><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" class="chk-row"></td>
                    <td>#<?= (int)$r['id'] ?></td>
                    <td><?= htmlspecialchars($r['kaynak']) ?></td>
                    <td><?= htmlspecialchars($r['hedef']) ?></td>
                    <td><?= (int)$r['tip'] ?></td>
                    <td><?= htmlspecialchars($r['olusturuldu']) ?></td>
                    <td>
                        <button
                            type="button"
                            class="btn btn-sm toggle-durum <?= (int)$r['aktif'] === 1 ? 'btn-success' : 'btn-secondary' ?>"
                            data-id="<?= (int)$r['id'] ?>"
                            data-csrf="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <?= (int)$r['aktif'] === 1 ? 'Aktif' : 'Pasif' ?>
                        </button>
                    </td>
                    <td>
                        <form method="post" action="<?= BASE_URL ?>/admin/yonlendirmeler/sil" onsubmit="return confirm('Silinsin mi?')">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($liste)): ?>
                <tr><td colspan="7" class="text-muted">Kayıt yok.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-2">
        <button type="submit" class="btn btn-danger btn-sm">Seçilileri Sil</button>

        <?php if (($pages ?? 1) > 1): ?>
            <nav>
              <ul class="pagination mb-0">
                <?php
                  $mk = function($p) use($BASE,$q){ $qs = $q!=='' ? '&q='.urlencode($q) : ''; return $BASE.'/admin/yonlendirmeler?page='.$p.$qs; };
                  $cur = $page ?? 1; $max = $pages ?? 1;
                ?>
                <li class="page-item <?= $cur<=1?'disabled':'' ?>"><a class="page-link" href="<?= $mk(max(1,$cur-1)) ?>">«</a></li>
                <?php for($p=max(1,$cur-2); $p<=min($max,$cur+2); $p++): ?>
                  <li class="page-item <?= $p===$cur?'active':'' ?>"><a class="page-link" href="<?= $mk($p) ?>"><?= $p ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= $cur>=$max?'disabled':'' ?>"><a class="page-link" href="<?= $mk(min($max,$cur+1)) ?>">»</a></li>
              </ul>
            </nav>
        <?php endif; ?>
    </div>
</form>

<script>
// Tümünü seç
document.getElementById('chk-all')?.addEventListener('change', e => {
  document.querySelectorAll('.chk-row').forEach(ch => ch.checked = e.target.checked);
});
</script>    
</div>

<script>
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.toggle-durum');
    if (!btn) return;

    const id = btn.dataset.id;
    const csrf = btn.dataset.csrf;

    try {
        const res = await fetch('<?= BASE_URL ?>/admin/yonlendirmeler/durum', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
            body: new URLSearchParams({ id })
        });
        const data = await res.json();
        if (!data.ok) { alert(data.mesaj || 'Güncelleme hatası'); return; }

        // UI güncelle
        if (String(data.durum) === '1') {
            btn.classList.remove('btn-secondary'); btn.classList.add('btn-success');
            btn.textContent = 'Aktif';
        } else {
            btn.classList.remove('btn-success'); btn.classList.add('btn-secondary');
            btn.textContent = 'Pasif';
        }
    } catch (err) {
        alert('İstek hatası: ' + err);
    }
});
</script>

