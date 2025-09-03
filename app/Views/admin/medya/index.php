<?php
// app/Views/admin/medya/index.php
use App\Core\Csrf;

$mesaj = $_SESSION['mesaj'] ?? null; unset($_SESSION['mesaj']);
$hata  = $_SESSION['hata']  ?? null; unset($_SESSION['hata']);

$q           = $_GET['q'] ?? '';
$sayfa       = max(1, (int)($_GET['s'] ?? 1));
$sayfaSayisi = $sayfaSayisi ?? 1;

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$csrfVal = $_SESSION['csrf'] ?? $_SESSION['csrf_token'] ?? (\App\Core\Csrf::token());
$BASE = rtrim(BASE_URL, '/');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h5 m-0">Medya Kütüphanesi</h1>
</div>

<?php if ($mesaj): ?><div class="alert alert-success py-2"><?= htmlspecialchars($mesaj) ?></div><?php endif; ?>
<?php if ($hata):  ?><div class="alert alert-danger  py-2"><?= htmlspecialchars($hata)  ?></div><?php endif; ?>

<form method="get" action="<?= BASE_URL ?>/admin/medya" class="row g-2 mb-3">
  <div class="col-auto">
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control form-control-sm" placeholder="Ara (yol, mime)">
  </div>
  <div class="col-auto">
    <button class="btn btn-sm btn-secondary">Ara</button>
  </div>
</form>

<!-- TEK FORM: toplu + tekil silme bu formdan yönetilir -->
<form id="medyaForm" method="post" action="<?= BASE_URL ?>/admin/medya/toplu-sil">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfVal, ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfVal, ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="id" id="hiddenSingleId" value="">

  <div class="d-flex align-items-center gap-2 mb-2">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" id="secHepsi">
      <label class="form-check-label" for="secHepsi">Tümünü seç</label>
    </div>
    <!-- Toplu silme artık modal açar -->
    <button type="button" class="btn btn-sm btn-danger" id="bulkDeleteBtn">Seçili olanları sil</button>
    <span class="text-muted small">Silmek görünümü bozar; görsel sayfalarda kullanılıyorsa orada da kırık olur.</span>
  </div>

  <?php if (empty($medyalar)): ?>
    <div class="alert alert-info">Kayıt yok.</div>
  <?php else: ?>
    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-6 g-3">
      <?php foreach ($medyalar as $m): ?>
        <div class="col">
          <div class="card h-100">
            <!-- Küçük görsele tıklayınca modal önizleme -->
            <a href="#" class="ratio ratio-1x1 media-thumb" data-src="<?= $BASE . $m['yol'] ?>">
              <img src="<?= $BASE . $m['yol'] ?>" alt="" class="card-img-top" style="object-fit:cover;">
            </a>
            <div class="card-body p-2">
              <div class="small text-truncate" title="<?= htmlspecialchars($m['yol']) ?>">
                <?= htmlspecialchars(basename($m['yol'])) ?>
              </div>
              <div class="text-muted small">
                <?= htmlspecialchars($m['mime']) ?>
                <?php if (($m['genislik'] ?? null) && ($m['yukseklik'] ?? null)): ?>
                  • <?= (int)$m['genislik'] ?>×<?= (int)$m['yukseklik'] ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center p-2">
              <div class="form-check m-0">
                <input class="form-check-input chk" type="checkbox" name="ids[]" value="<?= (int)$m['id'] ?>">
              </div>
              <!-- Tekil silme: modal ile onay -->
              <button type="button"
                      class="btn btn-sm btn-outline-danger btn-sil-tek"
                      data-id="<?= (int)$m['id'] ?>"
                      data-name="<?= htmlspecialchars(basename($m['yol'])) ?>">
                Sil
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Sayfalama -->
    <nav class="mt-3">
      <ul class="pagination pagination-sm">
        <?php
        $base = BASE_URL.'/admin/medya?q='.urlencode($q).'&s=';
        $onceki = max(1, $sayfa-1);
        $sonraki= max(1, min($sayfaSayisi, $sayfa+1));
        ?>
        <li class="page-item <?= $sayfa<=1?'disabled':'' ?>"><a class="page-link" href="<?= $base.$onceki ?>">&laquo;</a></li>
        <li class="page-item disabled"><a class="page-link" href="#"><?= $sayfa ?> / <?= $sayfaSayisi ?></a></li>
        <li class="page-item <?= $sayfa>=$sayfaSayisi?'disabled':'' ?>"><a class="page-link" href="<?= $base.$sonraki ?>">&raquo;</a></li>
      </ul>
    </nav>
  <?php endif; ?>
</form>

<!-- ÖNİZLEME MODALI -->
<div class="modal fade" id="mediaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Önizleme</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">
        <img id="mediaModalImg" src="" alt="" class="img-fluid w-100">
      </div>
      <div class="modal-footer gap-2">
        <input type="text" id="mediaUrl" class="form-control form-control-sm" readonly>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="copyBtn">Bağlantıyı kopyala</button>
        <a id="openNewTab" target="_blank" class="btn btn-sm btn-primary">Yeni sekmede aç</a>
      </div>
    </div>
  </div>
</div>

<!-- SİLME ONAY MODALI -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="confirmForm">
      <div class="modal-header">
        <h5 class="modal-title">Silme Onayı</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">
        <p id="confirmText" class="mb-0"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
        <button type="submit" class="btn btn-danger" id="confirmBtn">Sil</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Tümünü seç
  const secHepsi = document.getElementById('secHepsi');
  if (secHepsi) {
    secHepsi.addEventListener('change', function(){
      document.querySelectorAll('.chk').forEach(ch => ch.checked = !!this.checked);
    });
  }

  // Önizleme modalı
  document.querySelectorAll('.media-thumb').forEach(a => {
    a.addEventListener('click', (e) => {
      e.preventDefault();
      const src = a.dataset.src;
      document.getElementById('mediaModalImg').src = src;
      document.getElementById('mediaUrl').value  = src;
      document.getElementById('openNewTab').href = src;
      const m = bootstrap.Modal.getOrCreateInstance(document.getElementById('mediaModal'));
      m.show();
    });
  });

  // Kopyala
  document.getElementById('copyBtn')?.addEventListener('click', () => {
    const inp = document.getElementById('mediaUrl');
    inp.select(); inp.setSelectionRange(0, 99999);
    try { navigator.clipboard?.writeText(inp.value); } catch(e){}
  });

  // Silme onayı modalı
  const medyaForm      = document.getElementById('medyaForm');
  const hiddenSingleId = document.getElementById('hiddenSingleId');
  const confirmModalEl = document.getElementById('confirmDeleteModal');
  const confirmText    = document.getElementById('confirmText');
  const confirmForm    = document.getElementById('confirmForm');
  const confirmBtn     = document.getElementById('confirmBtn');
  let deleteMode = null;
  const confirmModal = new bootstrap.Modal(confirmModalEl);

  // Tekil silme
  document.querySelectorAll('.btn-sil-tek').forEach(btn => {
    btn.addEventListener('click', () => {
      deleteMode = 'single';
      const id   = btn.dataset.id;
      const name = btn.dataset.name || ('#' + id);
      hiddenSingleId.value = id;
      confirmText.textContent = `"${name}" adlı görsel silinecek. Bu işlem geri alınamaz.`;
      confirmBtn.disabled = false;
      confirmModal.show();
    });
  });

  // Toplu silme
  document.getElementById('bulkDeleteBtn')?.addEventListener('click', () => {
    const checked = Array.from(document.querySelectorAll('.chk')).filter(ch => ch.checked);
    if (checked.length === 0) {
      deleteMode = null;
      confirmText.textContent = 'Seçili görsel bulunmuyor.';
      confirmBtn.disabled = true;
    } else {
      deleteMode = 'bulk';
      confirmText.textContent = `${checked.length} adet görsel silinecek. Bu işlem geri alınamaz.`;
      confirmBtn.disabled = false;
    }
    confirmModal.show();
  });

  // Modal içindeki onay
  confirmForm.addEventListener('submit', (e) => {
    e.preventDefault();
    <?php $BASE = rtrim(BASE_URL, '/'); ?>
    if (deleteMode === 'single') {
      medyaForm.action = '<?= $BASE ?>/admin/medya/sil';
    } else if (deleteMode === 'bulk') {
      medyaForm.action = '<?= $BASE ?>/admin/medya/toplu-sil';
    } else {
      return confirmModal.hide();
    }
    confirmModal.hide();
    medyaForm.submit();
  });
});
</script>
