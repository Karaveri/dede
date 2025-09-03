<?php
// app/Views/admin/sayfalar/form.php
use App\Core\Csrf;

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Güvenli normalize
$sayfa    = (isset($sayfa) && is_array($sayfa)) ? $sayfa : [];
$BASE     = rtrim(BASE_URL, '/');
$__csrf   = $_SESSION['csrf'] ?? $_SESSION['csrf_token'] ?? Csrf::token();

$sayfaId   = (int)($sayfa['id'] ?? 0);
$isEdit    = $sayfaId > 0;
$action    = $isEdit ? ($BASE . '/admin/sayfalar/guncelle') : ($BASE . '/admin/sayfalar/kaydet');

$baslikVal = (string)($sayfa['baslik'] ?? '');
$slugVal   = (string)($sayfa['slug'] ?? '');
$ozetVal  = (string)($sayfa['ozet'] ?? '');

// Durum: projede JS/uçlar 'aktif'/'taslak' bekliyor → map edelim
$durumHam  = $sayfa['durum'] ?? 'aktif';
$durum     = in_array($durumHam, [1,'1',true,'aktif','yayinda'], true) ? 'aktif' : 'taslak';

// Kısa helper
$val = fn($k, $d = '') => htmlspecialchars((string)($sayfa[$k] ?? $d), ENT_QUOTES, 'UTF-8');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h5 m-0"><?= $isEdit ? 'Sayfa Düzenle' : 'Yeni Sayfa' ?></h1>
  <a href="<?= $BASE ?>/admin/sayfalar" class="btn btn-sm btn-outline-secondary">Listeye Dön</a>
</div>

<form id="sayfaForm" class="needs-validation" method="post" action="<?= $action ?>" novalidate>
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($__csrf, ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($__csrf, ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="<?= $sayfaId ?>">
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-8">
      <div class="mb-3">
        <label for="sayfaBaslik" class="form-label">Başlık <span class="text-danger">*</span></label>
        <input type="text"
               name="baslik"
               id="sayfaBaslik"
               class="form-control"
               value="<?= htmlspecialchars($baslikVal, ENT_QUOTES, 'UTF-8') ?>"
               required>
        <div class="invalid-feedback">Başlık zorunludur.</div>
      </div>

<div class="mb-3">
  <label for="ozet" class="form-label">Özet</label>
  <textarea id="ozet" name="ozet" class="form-control" rows="3"
            data-maxlen="200"><?= htmlspecialchars($ozetVal, ENT_QUOTES, 'UTF-8') ?></textarea>
  <div class="d-flex justify-content-between">
    <div class="form-text">Liste ve SEO snippet’i için kısa bir özet (150–200 karakter önerilir).</div>
    <div class="form-text"><span id="ozetCount">0</span>/200</div>
  </div>
  <div class="invalid-feedback">Özet en fazla 200 karakter olmalı.</div>
</div>

      <div class="mb-3">
        <label for="icerik" class="form-label">İçerik</label>
        <textarea id="icerik" name="icerik" class="form-control" rows="14"><?= $val('icerik') ?></textarea>
      </div>
    </div>

    <div class="col-md-4">
      <div class="mb-3">
        <label for="sayfaSlug" class="form-label">Slug</label>
        <input type="text"
               class="form-control"
               id="sayfaSlug"
               name="slug"
               value="<?= htmlspecialchars($slugVal, ENT_QUOTES, 'UTF-8') ?>"
               placeholder="Boş bırakırsan otomatik oluşur"
               data-check-url="<?= $BASE ?>/admin/sayfalar/slug-kontrol"
               data-id="<?= $sayfaId ?>">
        <div class="form-text">Küçük harf, harf-rakam ve tire kullan.</div>
        <div class="invalid-feedback">Bu slug kullanılıyor.</div>
      </div>

<div class="mb-3">
  <label class="form-label">Durum</label>
  <div class="form-check">
    <input class="form-check-input" type="radio" name="durum" id="durumY" value="yayinda"
           <?= (($sayfa['durum'] ?? '') === 'yayinda') ? 'checked' : '' ?>>
    <label class="form-check-label" for="durumY">Yayında</label>
  </div>
  <div class="form-check">
    <input class="form-check-input" type="radio" name="durum" id="durumT" value="taslak"
           <?= (($sayfa['durum'] ?? '') === 'taslak') ? 'checked' : '' ?>>
    <label class="form-check-label" for="durumT">Taslak</label>
  </div>
</div>

      <div class="mb-3">
        <label for="meta_baslik" class="form-label">Meta Başlık</label>
        <input type="text" id="meta_baslik" name="meta_baslik" class="form-control" value="<?= $val('meta_baslik') ?>">
      </div>

      <div class="mb-3">
        <label for="meta_aciklama" class="form-label">Meta Açıklama</label>
        <textarea id="meta_aciklama" name="meta_aciklama" class="form-control" rows="3"><?= $val('meta_aciklama') ?></textarea>
      </div>

      <div class="d-grid gap-2">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Güncelle' : 'Kaydet' ?></button>
      </div>
    </div>
  </div>
</form>

<!-- Bootstrap form validation -->
<script>
(() => {
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(f => f.addEventListener('submit', e => {
    if (!f.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
    f.classList.add('was-validated');
  }));
})();
</script>

<!-- TinyMCE -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>

<!-- TinyMCE init -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const UPLOAD_URL = '<?= $BASE ?>/admin/medya/yukle';

  // Token'ı daima formdaki gizli alandan al (upload ve ana form aynı değeri kullansın)
  const CSRF_FIELD = document.querySelector('input[name="csrf"]') ||
                     document.querySelector('input[name="csrf_token"]');
  const CSRF_TOKEN = CSRF_FIELD ? CSRF_FIELD.value : '';

  // Sayfada önceden init edilmiş editor varsa sök (geri gelme/yeniden render durumları için)
  try { (tinymce.EditorManager?.editors || []).forEach(ed => ed.remove()); } catch(e){}

  tinymce.init({
    selector: 'textarea#icerik',
    height: 520,
    menubar: 'file edit view insert format tools table help',
    plugins: 'advlist autolink lists link image charmap preview code fullscreen insertdatetime media table wordcount autosave',
    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline | alignleft aligncenter alignright | bullist numlist | removeformat | preview code fullscreen',
    branding: false,
    relative_urls: false,
    remove_script_host: false,
    convert_urls: true,

    automatic_uploads: true,
    images_upload_credentials: true,
    file_picker_types: 'image',

    // Upload sekmesi + sürükle-bırak
    images_upload_handler: function (blobInfo, progress) {
      return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', UPLOAD_URL, true);
        xhr.setRequestHeader('X-CSRF-TOKEN', CSRF_TOKEN);

        xhr.upload.onprogress = (e) => {
          if (e.lengthComputable) progress(Math.round(e.loaded / e.total * 100));
        };

        xhr.onload = function () {
          if (xhr.status < 200 || xhr.status >= 300) return reject('HTTP Error: ' + xhr.status);
          let json;
          try { json = JSON.parse(xhr.responseText); } catch { return reject('Invalid JSON'); }
          if (!json || typeof json.location !== 'string') return reject('Invalid response');
          resolve(json.location);
        };
        xhr.onerror = () => reject('XHR error');

        const formData = new FormData();
        formData.append('file', blobInfo.blob(), blobInfo.filename());
        formData.append('csrf_token', CSRF_TOKEN); // yedek
        xhr.send(formData);
      });
    },

    // "Gözat" ile dosya seçerek yükleme
    file_picker_callback: (cb, value, meta) => {
      if (meta.filetype !== 'image') return;
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/*';
      input.onchange = () => {
        const fd = new FormData();
        fd.append('file', input.files[0]);
        fd.append('csrf_token', CSRF_TOKEN);
        fetch(UPLOAD_URL, {
          method: 'POST',
          body: fd,
          credentials: 'include',
          headers: { 'X-CSRF-TOKEN': CSRF_TOKEN }
        })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(d => d?.location ? cb(d.location) : alert('Yükleme hatası'))
        .catch(err => alert(err.message));
      };
      input.click();
    },

    setup(editor) {
      // Form submit'te TinyMCE içeriğini textarea'ya yazar
      const form = editor.formElement || document.getElementById('sayfaForm') || document.querySelector('form');
      if (form) form.addEventListener('submit', () => editor.save());
    }
  });
});
</script>
