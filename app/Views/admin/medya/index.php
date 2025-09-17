<?php
// app/Views/admin/medya/index.php
use App\Core\Csrf;

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$mesaj = $_SESSION['mesaj'] ?? null; unset($_SESSION['mesaj']);
$hata  = $_SESSION['hata']  ?? null; unset($_SESSION['hata']);

$q           = $_GET['q'] ?? '';
$sayfa       = max(1, (int)($_GET['s'] ?? 1));
$sayfaSayisi = $sayfaSayisi ?? 1;

$csrfVal = $_SESSION['csrf'] ?? $_SESSION['csrf_token'] ?? (\App\Core\Csrf::token());
$BASE = rtrim(BASE_URL, '/');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h5 m-0">Medya Kütüphanesi</h1>
  <form method="post" action="<?= $BASE ?>/admin/medya/thumb-fix" class="ms-2">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfVal, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" class="btn btn-sm btn-outline-secondary">Eksik küçük görselleri üret</button>
  </form>
</div>

<?php if ($mesaj): ?><div class="alert alert-success py-2"><?= htmlspecialchars($mesaj) ?></div><?php endif; ?>
<?php if ($hata):  ?><div class="alert alert-danger  py-2"><?= htmlspecialchars($hata)  ?></div><?php endif; ?>

<!-- Var olan grid -->
<div id="medya-grid" class="d-none"></div>

<form method="get" action="<?= BASE_URL ?>/admin/medya" class="row g-2 mb-3">
  <div class="col-auto">
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control form-control-sm" placeholder="Ara (yol, mime)">
  </div>
  <div class="col-auto">
    <button class="btn btn-sm btn-secondary">Ara</button>
  </div>
</form>
<div class="row g-2">
  <div id="dropZone" class="border rounded p-4 text-center">
    <div class="fw-semibold mb-1">Dosyaları buraya sürükleyip bırak</div>
    <div class="small text-muted" style="color: white !important;">veya tıklayıp seç</div>
  </div>

  <!-- Gizli dosya input'u: JS tıklamada bunu açacak -->
  <input type="file" id="file" name="file"
         accept="image/jpeg,image/png,image/webp"
         multiple hidden>

  <div class="form-text mt-2">İzinli türler: JPEG, PNG, WEBP • Maksimum 8 MB</div>
</div>

<div id="medyaSonuc"></div>
<!-- TEK FORM: toplu + tekil silme bu formdan yönetilir -->
<form id="medyaForm" method="post" action="<?= BASE_URL ?>/admin/medya/toplu-sil">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfVal, ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfVal, ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="id" id="hiddenSingleId" value="">

  <div class="d-flex align-items-center gap-2 mb-2">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" id="secTum">
      <label class="form-check-label" for="secTum">Tümünü seç</label>
    </div>
    <!-- TOPLU SİL (geri geldi) -->
    <button type="button" class="btn btn-sm btn-danger" id="bulkDeleteBtn">Seçili olanları sil</button>
    <!-- TOPLU ETİKET -->
    <button type="button" class="btn btn-sm btn-outline-secondary" id="bulkTagBtn">Seçililere etiket ata</button>
    <span class="text-muted small">Silmek görünümü bozar; görsel sayfalarda kullanılıyorsa orada da kırık olur.</span>
  </div>

  <?php if (empty($medyalar)): ?>
    <div class="alert alert-info">Kayıt yok.</div>
  <?php else: ?>
    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-6 g-3">
      <?php foreach ($medyalar as $m): ?>
        <div class="col">
          <div class="card h-100">
            <?php
            // DİKKAT: satır ; ile bitiyor ve PHP bloğu kapanıyor
            $thumb = !empty($m['yol_thumb'] ?? null) ? $m['yol_thumb'] : $m['yol'];
            ?>
            <a href="#" class="ratio ratio-1x1 media-thumb"
               data-src="<?= $BASE . $m['yol'] ?>"
               data-mid="<?= (int)$m['id'] ?>">
              <img loading="lazy" decoding="async"
                   src="<?= $BASE . $thumb ?>" alt=""
                   class="card-img-top" style="object-fit:cover;">
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
        <img id="mediaModalImg" src="" alt="" class="imgCenter mb-3">

        <!-- Etiket rozetleri -->
        <div id="mediaTagsBadges" class="d-flex flex-wrap gap-1 mb-2"></div>

        <!-- Etiket düzenleme -->
        <div class="input-group input-group-sm mb-2">
          <input type="text" id="mediaTags" class="form-control" placeholder="Etiketleri virgülle yaz (örn: kapak, ürün)">
          <button type="button" class="btn btn-outline-secondary" id="saveTagsBtn">Kaydet</button>
        </div>
        <div class="mt-1">
          <div class="btn-group btn-group-sm" role="group" aria-label="Etiket modu">
            <input type="radio" class="btn-check" name="tagMode" id="tagModeReplace" value="replace" checked>
            <label class="btn btn-outline-secondary" for="tagModeReplace">Eşitle</label>
            <input type="radio" class="btn-check" name="tagMode" id="tagModeAppend" value="append">
            <label class="btn btn-outline-secondary" for="tagModeAppend">Ekle</label>
          </div>
        </div>

        <hr class="my-3">

        <!-- Meta alanları -->
        <div class="row g-2 align-items-center mb-2">
          <div class="col-12 col-sm-6">
            <label for="mediaAlt" class="form-label form-label-sm mb-1">Alt metin</label>
            <input type="text" id="mediaAlt" class="form-control form-control-sm" maxlength="255" placeholder="Örn: Gün batımı manzarası">
          </div>
          <div class="col-12 col-sm-6">
            <label for="mediaTitle" class="form-label form-label-sm mb-1">Başlık (title)</label>
            <input type="text" id="mediaTitle" class="form-control form-control-sm" maxlength="150" placeholder="Örn: Kapak görseli">
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="saveMetaBtn">Meta Kaydet</button>

        <!-- aktif medya id’si -->
        <input type="hidden" id="mediaMid" value="">
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

<!-- TOPLU ETİKET MODALI -->
<div class="modal fade" id="bulkTagModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="bulkTagForm">
      <div class="modal-header">
        <h5 class="modal-title">Seçililere etiket ata</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="small text-muted mb-2">
          Seçili görsellerin mevcut etiketlerine <b>ekler</b> (silmez).
          (Örn: <code>kapak, ürün</code>)
        </div>
        <input type="text" id="bulkTagsInput" class="form-control" placeholder="Etiketleri virgülle yaz">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
        <button type="submit" class="btn btn-primary" id="bulkTagSaveBtn">Kaydet</button>
      </div>
    </form>
  </div>
</div>

<!-- TOASTS -->
<div id="toastWrap" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1080;"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof __bindMedyaUpload === 'function') __bindMedyaUpload();
  // Tümünü seç
  const secTum = document.getElementById('secTum');
  if (secTum) {
    secTum.addEventListener('change', function(){
      document.querySelectorAll('.chk').forEach(ch => ch.checked = !!this.checked);
    });
  }

  // ÖNİZLEME MODALI (etiket yüklemeli)
  document.querySelectorAll('.media-thumb').forEach(a => {
    a.addEventListener('click', async (e) => {
      e.preventDefault();
      const src = a.dataset.src;
      const mid = a.dataset.mid || '';

      document.getElementById('mediaModalImg').src = src;
      document.getElementById('mediaUrl').value  = src;
      document.getElementById('openNewTab').href = src;
      document.getElementById('mediaMid').value  = mid;

      if (mid) {
        await loadTags(mid); // aşağıda tanımlıyoruz
      } else {
        renderBadges([]);
        document.getElementById('mediaTags').value = '';
      }
      // meta bilgileri de getir
      await loadMeta(mid);
      const m = bootstrap.Modal.getOrCreateInstance(document.getElementById('mediaModal'));
      m.show();
    });
  });

  // BASE ve CSRF (formdan ya da meta'dan)
  const BASE = '<?= $BASE ?>';
  const csrf = document.querySelector('#medyaForm input[name="csrf"]')?.value
            || document.querySelector('meta[name="csrf"]')?.content
            || '';

  // Etiket rozetlerini çizen küçük yardımcı
  function renderBadges(list) {
    const wrap = document.getElementById('mediaTagsBadges');
    if (!wrap) return;
    wrap.innerHTML = '';
    (list || []).forEach(e => {
      const s = document.createElement('span');
      s.className = 'badge text-bg-light border me-1 mb-1';
      s.textContent = e.ad || e.slug;
      wrap.appendChild(s);
    });
  }

  // Basit toast bildirimi
  function showToast(msg, variant = 'success') {
    const wrap = document.getElementById('toastWrap');
    if (!wrap) return alert(msg);

    const el = document.createElement('div');
    el.className = 'toast align-items-center text-bg-' +
      (variant === 'danger' ? 'danger' :
       variant === 'warning' ? 'warning' :
       variant === 'info' ? 'info' : 'success');
    el.role = 'alert';
    el.ariaLive = 'assertive';
    el.ariaAtomic = 'true';
    el.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${msg}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>`;
    wrap.appendChild(el);
    const t = new bootstrap.Toast(el, { delay: 2500 });
    t.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
  }

  // Güvenli JSON fetch (HTML dönerse de yakalar)
  async function fetchJSON(url, opt) {
    const r = await fetch(url, opt);
    const txt = await r.text();
    try { return { ok:r.ok, status:r.status, json: JSON.parse(txt) }; }
    catch { return { ok:r.ok, status:r.status, text: txt }; }
  }

  // Belirli bir medya için etiketleri yükle
  async function loadTags(mid) {
    const resp = await fetchJSON(`${BASE}/admin/api/medya/etiketler?mid=${encodeURIComponent(mid)}`);
    if (resp.ok && resp.json?.ok) {
      const tags = resp.json.etiketler || [];
      document.getElementById('mediaTags').value = tags.map(t => t.ad || t.slug).join(', ');
      renderBadges(tags);
    } else {
      document.getElementById('mediaTags').value = '';
      renderBadges([]);
    }
  }

  // Modal içindeki "Kaydet" butonu: etiketleri eşitle
  document.getElementById('saveTagsBtn')?.addEventListener('click', async () => {
    const mid  = parseInt(document.getElementById('mediaMid').value || '0', 10);
    const tags = document.getElementById('mediaTags').value || '';
    const mode = document.querySelector('input[name="tagMode"]:checked')?.value || 'replace';
    if (!mid) return;

    const resp = await fetchJSON(`${BASE}/admin/api/medya/etiketle`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(csrf ? { 'X-CSRF-Token': csrf, 'X-CSRF': csrf } : {})
      },
      body: JSON.stringify({ medya_id: mid, etiketler: tags, mod: mode })
    });

    if (resp.ok && resp.json?.ok) {
      renderBadges(resp.json.etiketler || []);
      showToast(`Etiketler ${mode === 'append' ? 'eklendi' : 'eşitlendi'}.`, 'success');
    } else {
      showToast('Kaydedilemedi: ' + (resp.json?.hata || `HTTP ${resp.status}`), 'danger');
    }
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

  // TOPLU ETİKET: butona basınca modal aç
  const bulkTagBtn = document.getElementById('bulkTagBtn');
  const bulkTagModalEl = document.getElementById('bulkTagModal');
  const bulkTagModal = bulkTagModalEl ? new bootstrap.Modal(bulkTagModalEl) : null;

  bulkTagBtn?.addEventListener('click', () => {
    const ids = Array.from(document.querySelectorAll('.chk:checked')).map(ch => +ch.value);
    if (!ids.length) {
      showToast('Seçili görsel yok.', 'warning');
      return;
    }
    document.getElementById('bulkTagsInput').value = '';
    bulkTagModal?.show();
    setTimeout(() => document.getElementById('bulkTagsInput')?.focus(), 200);
  });

  // TOPLU ETİKET: modal form submit
  document.getElementById('bulkTagForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const ids = Array.from(document.querySelectorAll('.chk:checked')).map(ch => +ch.value);
    const tags = document.getElementById('bulkTagsInput').value || '';
    if (!ids.length) {
      showToast('Seçili görsel yok.', 'warning');
      return;
    }

    // UI kilitle
    const saveBtn = document.getElementById('bulkTagSaveBtn');
    saveBtn.disabled = true;

    let ok = 0, fail = 0;
    for (const id of ids) {
      const resp = await fetchJSON(`${BASE}/admin/api/medya/etiketle`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(csrf ? { 'X-CSRF-Token': csrf, 'X-CSRF': csrf } : {})
        },
        body: JSON.stringify({ medya_id: id, etiketler: tags, mod: 'append' })
      });
      if (resp.ok && resp.json?.ok) ok++; else fail++;
    }

    saveBtn.disabled = false;
    bulkTagModal?.hide();
    showToast(`Etiket atama • Tamam: ${ok} • Hata: ${fail}`, fail ? 'warning' : 'success');
  });



  async function loadMeta(mid) {
    const resp = await fetchJSON(`${BASE}/admin/api/medya/meta?mid=${encodeURIComponent(mid)}`);
    if (resp.ok && resp.json?.ok) {
      const m = resp.json.medya || {};
      document.getElementById('mediaAlt').value   = m.alt_text ?? '';
      document.getElementById('mediaTitle').value = m.title    ?? '';
    } else {
      document.getElementById('mediaAlt').value   = '';
      document.getElementById('mediaTitle').value = '';
    }
  }

  document.getElementById('saveMetaBtn')?.addEventListener('click', async () => {
    const mid = parseInt(document.getElementById('mediaMid').value || '0', 10);
    const alt = document.getElementById('mediaAlt').value || '';
    const tit = document.getElementById('mediaTitle').value || '';
    if (!mid) return;

    const resp = await fetchJSON(`${BASE}/admin/api/medya/meta`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(csrf ? { 'X-CSRF-Token': csrf, 'X-CSRF': csrf } : {})
      },
      body: JSON.stringify({ medya_id: mid, alt_text: alt, title: tit })
    });

    if (resp.ok && resp.json?.ok) {
      showToast('Meta bilgileri kaydedildi.', 'success');
    } else {
      showToast('Meta kaydedilemedi: ' + (resp.json?.hata || `HTTP ${resp.status}`), 'danger');
    }
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
