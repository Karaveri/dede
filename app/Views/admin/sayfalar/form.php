<?php
// app/Views/admin/sayfalar/form.php
use App\Core\Csrf;

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Güvenli normalize
$sayfa    = (isset($sayfa) && is_array($sayfa)) ? $sayfa : [];
$BASE     = rtrim(BASE_URL, '/');
$__csrf   = $_SESSION['csrf'] ?? $_SESSION['csrf_token'] ?? Csrf::token();
$diller    = $diller    ?? [];
$ceviriler = $ceviriler ?? [];

// Varsayılan dil kodu (yoksa ilk dili kullan)
$__vars_dil = '';
foreach ($diller as $d) { if (!empty($d['varsayilan'])) { $__vars_dil = $d['kod']; break; } }
if ($__vars_dil === '' && $diller) { $__vars_dil = $diller[0]['kod']; }

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
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
  <?php
    // Controller’dan gelenler:
    // $diller (kod, ad, varsayilan), $ceviriler[dil_kod] => row
    $varsDil = 'tr';
    foreach ($diller ?? [] as $d) if (!empty($d['varsayilan'])) { $varsDil = $d['kod']; break; }
  ?>

<!-- İki sütun düzeni: solda içerik, sağda ayarlar -->
<div class="form-two-col">
  <div id="contentCol" class="content-col">
  <ul class="nav nav-tabs mb-3" id="langTabs" role="tablist">
    <?php foreach (($diller ?? []) as $i => $d): $active = $i===0 ? 'active' : ''; ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $active ?>" id="tab-<?= $d['kod'] ?>" data-bs-toggle="tab"
          data-bs-target="#pane-<?= $d['kod'] ?>" type="button" role="tab"
          aria-controls="pane-<?= $d['kod'] ?>" aria-selected="<?= $i===0?'true':'false' ?>">
          <?= strtoupper($d['kod']) ?> — <?= htmlspecialchars($d['ad']) ?>
          <?= !empty($d['varsayilan']) ? '<span class="badge text-bg-secondary ms-2">varsayılan</span>' : '' ?>
        </button>
      </li>
    <?php endforeach; ?>
  </ul>

  <!-- Sekme içerikleri -->
  <div class="tab-content" id="langTabContent">
    <?php foreach (($diller ?? []) as $i => $d):
      $kod = $d['kod']; $row = $ceviriler[$kod] ?? [];
    ?>
    <div class="tab-pane fade <?= $i===0?'show active':'' ?>" id="pane-<?= $kod ?>" role="tabpanel" aria-labelledby="tab-<?= $kod ?>">
      <div class="mb-3">
        <label class="form-label">Başlık (<?= strtoupper($kod) ?>)</label>
        <input type="text" class="form-control js-title" data-dil="<?= $kod ?>"
               name="ceviri[<?= $kod ?>][baslik]" value="<?= htmlspecialchars($row['baslik'] ?? '') ?>">
      </div>

      <div class="mb-3">
        <label class="form-label">Slug (<?= strtoupper($kod) ?>)</label>
        <input type="text" class="form-control js-slug" data-dil="<?= $kod ?>"
               name="ceviri[<?= $kod ?>][slug]" value="<?= htmlspecialchars($row['slug'] ?? '') ?>">
        <div class="form-text">Boş bırakılırsa başlıktan türetilir.</div>
        <div class="invalid-feedback">Bu slug kullanılıyor.</div>
      </div>

      <div class="mb-3">
        <label class="form-label">Özet (<?= strtoupper($kod) ?>)</label>
        <textarea class="form-control" rows="2" name="ceviri[<?= $kod ?>][ozet]"><?= htmlspecialchars($row['ozet'] ?? '') ?></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">İçerik (<?= strtoupper($kod) ?>)</label>
        <!-- Her dil için TinyMCE kurulacak -->
        <textarea class="form-control tinymce" rows="12" name="ceviri[<?= $kod ?>][icerik]"><?= htmlspecialchars($row['icerik'] ?? '') ?></textarea>
      </div>

      <!-- Meta alanlarını panel içinden gizle; yan panelle senkron devam edecek -->
      <input type="hidden" name="ceviri[<?= $kod ?>][meta_baslik]"   value="<?= htmlspecialchars($row['meta_baslik'] ?? '') ?>">
      <input type="hidden" name="ceviri[<?= $kod ?>][meta_aciklama]" value="<?= htmlspecialchars($row['meta_aciklama'] ?? '') ?>">
    </div>
    <?php endforeach; ?>
  </div>


</div><!-- /#contentCol -->

    <aside id="settingsSidebar">
      <div class="card shadow-sm sticky-lg-top">
        <div class="card-body">
          <!-- Durum -->
          <div class="mb-3">
            <label class="form-label d-block">Durum</label>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="durum" id="drmY" value="yayinda" <?= ($val('durum')==='yayinda'?'checked':'') ?>>
              <label class="form-check-label" for="drmY">Yayında</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="durum" id="drmT" value="taslak" <?= ($val('durum')!=='yayinda'?'checked':'') ?>>
              <label class="form-check-label" for="drmT">Taslak</label>
            </div>
          </div>

          <!-- Meta Başlık/Açıklama (TR ile senkron) -->
          <div class="mb-3">
            <label class="form-label">Meta Başlık (<span id="metaLang">TR</span>)</label>
            <input type="text" class="form-control" id="metaBaslikTR_side" placeholder="Meta başlık">
          </div>
          <div class="mb-3">
            <label class="form-label">Meta Açıklama (<span class="metaLang">TR</span>)</label>
            <textarea class="form-control" id="metaAciklamaTR_side" rows="3" placeholder="Meta açıklama"></textarea>
            <div class="form-text"><span id="metaSideCount">0</span> karakter</div>
          </div>

          <!-- Öne çıkan görsel -->
          <?php $kapak = trim($val('kapak_gorsel')); ?>
          <div class="mb-3">
            <label class="form-label d-block">Öne çıkan görsel</label>
            <div class="border rounded p-2 d-flex align-items-center">
              <span id="featuredBadge" class="badge text-bg-info me-2" style="display:none;">Seçili</span>
              <img id="featuredPreview"
                   src="<?= $kapak ? htmlspecialchars($kapak,ENT_QUOTES,'UTF-8') : '' ?>"
                   alt=""
                   class="me-2"
                   style="width:88px;height:88px;object-fit:cover;<?= $kapak?'':'display:none;' ?>">
              <div class="flex-grow-1">
                <input type="hidden" name="kapak_gorsel" id="featuredInput" value="<?= htmlspecialchars($kapak,ENT_QUOTES,'UTF-8') ?>">
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-outline-primary" id="btnFeaturedPick">Medya Seç</button>
                  <button type="button" class="btn btn-outline-secondary" id="btnFeaturedUrl">URL</button>
                  <button type="button" class="btn btn-outline-danger" id="btnFeaturedClear">Kaldır</button>
                </div>
                <div class="form-text mt-1">Kare önerilir (en az 300×300). Listelemelerde kullanılır.</div>
              </div>
            </div>
          </div>

      <div class="d-grid">
        <button class="btn btn-primary" type="submit">Kaydet</button>
      </div>
    </div>
  </div>
</aside>
</div><!-- /.form-two-col -->

  <!-- Backend geriye dönük alanları beklerse TR değerlerini taşıyacağımız gizli inputlar -->
  <input type="hidden" name="baslik"        id="legacy_baslik">
  <input type="hidden" name="slug"          id="legacy_slug">
  <textarea        name="icerik"        id="legacy_icerik"        class="d-none"></textarea>
  <textarea        name="ozet"          id="legacy_ozet"          class="d-none"></textarea>
  <input type="hidden" name="meta_baslik"    id="legacy_meta_baslik">
  <input type="hidden" name="meta_aciklama"  id="legacy_meta_aciklama">

  <input type="hidden" name="csrf" value="<?= htmlspecialchars($__csrf, ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($__csrf, ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="<?= $sayfaId ?>">
  <?php endif; ?>
  <!-- alt tekrar eden durum/buton bloğu kaldırıldı; sağ paneldeki buton kullanılacak -->
  <div class="d-none">
    <input type="text" name="baslik" id="sayfaBaslik" class="form-control"
           value="<?= htmlspecialchars($baslikVal, ENT_QUOTES, 'UTF-8') ?>">
    <textarea id="ozet" name="ozet" class="form-control" rows="3"><?= htmlspecialchars($ozetVal, ENT_QUOTES, 'UTF-8') ?></textarea>
    <textarea id="icerik_legacy" name="icerik" class="form-control" rows="14"><?= $val('icerik') ?></textarea>
  </div>
</form>

<style type="text/css">
/* Sayfa formu: iki sütun düzeni */
.form-two-col { display: block; }
@media (min-width: 992px) {
  .form-two-col {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 1.25rem;
    align-items: start;
  }
  #contentCol { grid-column: 1; }
  #settingsSidebar { grid-column: 2; }
}
</style>

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

  // === Meta yan paneli aktif sekmeye bağla (dil dinamik) ===
  const VARS_DIL = '<?= $varsDil ?>';
  const LANGS    = <?= json_encode(array_column($diller ?? [], 'kod')) ?>;

  const sideMB = document.getElementById('metaBaslikTR_side');
  const sideMA = document.getElementById('metaAciklamaTR_side');
  const metaLangEls = [document.getElementById('metaLang'), ...document.querySelectorAll('.metaLang')];

  let boundMB = null, boundMA = null; // önceki sekmenin input referansları

  function ensureHidden(name, lang) {
    let el = document.querySelector(`[name="ceviri[${lang}][${name}]"]`);
    if (!el) {
      el = document.createElement('input');
      el.type = 'hidden';
      el.name = `ceviri[${lang}][${name}]`;
      el.value = '';
      document.getElementById(`pane-${lang}`)?.appendChild(el);
    }
    return el;
  }

  function bindSideMeta(lang) {
    // önceki dinleyicileri kaldır
    if (boundMB) boundMB.oninput = null;
    if (boundMA) boundMA.oninput = null;

    // hedef dildeki gizli alanları hazırla
    boundMB = ensureHidden('meta_baslik',   lang);
    boundMA = ensureHidden('meta_aciklama', lang);

    // değerleri senkronize et
    if (sideMB) { sideMB.value = boundMB.value || ''; sideMB.oninput = () => { boundMB.value = sideMB.value; }; }
    if (sideMA) { sideMA.value = boundMA.value || ''; sideMA.oninput = () => { boundMA.value = sideMA.value; }; }

    // etiketleri güncelle
    metaLangEls.forEach(el => { if (el) el.textContent = lang.toUpperCase(); });

    // sayaç da güncellensin
    const cnt = document.getElementById('metaSideCount');
    if (cnt && sideMA) { cnt.textContent = sideMA.value.length; }
  }

  // ilk bağlama: aktif sekme ya da varsayılan dil
  let firstActive = document.querySelector('.nav-link.active')?.id?.replace('tab-','') || VARS_DIL;
  bindSideMeta(firstActive);

  // Meta açıklama sayaç + renk
  const cnt = document.getElementById('metaSideCount');
  function paintCount(n){
    if (!cnt) return;
    cnt.textContent = n;
    const v = Number(n);
    cnt.style.color = (v>=80 && v<=160) ? 'var(--bs-success)' : 'var(--bs-secondary)';
  }
  if (sideMA){
    paintCount(sideMA.value.length);
    sideMA.addEventListener('input', ()=> paintCount(sideMA.value.length));
  }

  const UPLOAD_URL = '<?= $BASE ?>/admin/medya/yukle';
  const CSRF_FIELD = document.querySelector('input[name="csrf"]') || document.querySelector('input[name="csrf_token"]');
  const CSRF_TOKEN = CSRF_FIELD ? CSRF_FIELD.value : '';

  // Tek kaynak: TR-dostu slugify (global kullanılsın)
  window.slugify = function(s){
    const map = {'ç':'c','ğ':'g','ı':'i','ö':'o','ş':'s','ü':'u','Ç':'c','Ğ':'g','İ':'i','Ö':'o','Ş':'s','Ü':'u'};
    s = (s || '').replace(/[ÇĞİÖŞÜçğıöşü]/g, m => map[m] || m);
    s = s.normalize('NFKD').replace(/[\u0300-\u036f]/g,'');
    s = s.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').replace(/-{2,}/g,'-');
    return s.substring(0, 220);
  };
  // Not: canlı senkron ve benzersizlik kontrolleri alttaki IIFE’de tek kanaldan yapılıyor.

  // Önceden kurulmuş editörleri temizle (geri gelişte çakışma olmasın)
  try { (tinymce.EditorManager?.editors || []).forEach(ed => ed.remove()); } catch(e){}

  // Ortak TinyMCE ayarı (eski çalışan sürümle aynı davranış)
  function makeTinyConfig(target) {
    return {
      target,
      height: 520,
      menubar: 'file edit view insert format tools table help',
      plugins: 'advlist autolink lists link image charmap preview code fullscreen insertdatetime media table wordcount autosave',
      toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline | alignleft aligncenter alignright | bullist numlist | removeformat | preview code fullscreen',
      branding: false,
      relative_urls: false, remove_script_host: false, convert_urls: true,
      automatic_uploads: true, images_upload_credentials: true, file_picker_types: 'image',

      images_upload_handler: function (blobInfo, progress) {
        return new Promise((resolve, reject) => {
          const xhr = new XMLHttpRequest();
          xhr.open('POST', UPLOAD_URL, true);
          xhr.setRequestHeader('X-CSRF-TOKEN', CSRF_TOKEN);

          xhr.upload.onprogress = (e) => { if (e.lengthComputable) progress(Math.round(e.loaded / e.total * 100)); };
          xhr.onload = function () {
            if (xhr.status < 200 || xhr.status >= 300) return reject('HTTP ' + xhr.status);
            let json; try { json = JSON.parse(xhr.responseText); } catch { return reject('Invalid JSON'); }
            if (!json || typeof json.location !== 'string') return reject('Invalid response');
            resolve(json.location);
          };
          xhr.onerror = () => reject('XHR error');

          const fd = new FormData();
          fd.append('file', blobInfo.blob(), blobInfo.filename());
          fd.append('csrf_token', CSRF_TOKEN);
          xhr.send(fd);
        });
      },

      file_picker_callback: (cb, value, meta) => {
        if (meta.filetype !== 'image') return;
        const input = document.createElement('input');
        input.type = 'file'; input.accept = 'image/*';
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
        const form = document.getElementById('sayfaForm') || document.querySelector('form');
        if (form) form.addEventListener('submit', () => tinymce.triggerSave());
      }
    };
  }


  // 1) İlk görünen dil sekmesindeki '.tinymce' alanları başlat (fallback'li)
  let started = 0;
  document.querySelectorAll('.tab-pane.show.active .tinymce').forEach(t => {
    tinymce.init(makeTinyConfig(t)); started++;
  });
  
  // 2) Fallback: ilk pane 'show active' değilse zorla aktif yapıp kur
  if (!started) {
    const firstPane = document.querySelector('.tab-pane');
    if (firstPane) {
      firstPane.classList.add('show','active');
      firstPane.querySelectorAll('.tinymce').forEach(t => { tinymce.init(makeTinyConfig(t)); started++; });
    }
  }

  // 3) Son çare: sayfadaki ilk .tinymce'yi kur
  if (!started) {
    const any = document.querySelector('.tinymce');
    if (any) tinymce.init(makeTinyConfig(any));
  }

  // 4) Sekme değişince lazy-init
  document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn => {
    btn.addEventListener('shown.bs.tab', (ev) => {
      const paneSel = ev.target.getAttribute('data-bs-target'); // #pane-tr
      document.querySelectorAll(`${paneSel} .tinymce`).forEach(t => {
        if (t.closest('.tox-tinymce')) return;
        tinymce.init(makeTinyConfig(t));
      });
      const lang = (paneSel || '').replace('#pane-','');
      bindSideMeta(lang);
    });
  });

  // Tiny hataları yakalamak için debug izi (geliştirme sırasında faydalı)
  window.addEventListener('error', e => {
    if ((e?.message || '').toLowerCase().includes('tinymce')) {
      console.warn('TinyMCE error:', e.message);
    }
  });

  // === Öne çıkan görsel ===
  const fInput = document.getElementById('featuredInput');
  const fPrev  = document.getElementById('featuredPreview');
  const btnPick  = document.getElementById('btnFeaturedPick');
  const btnUrl   = document.getElementById('btnFeaturedUrl');
  const btnClear = document.getElementById('btnFeaturedClear');

  function setFeatured(src){
    fInput.value = src || '';
    const badge = document.getElementById('featuredBadge');
    if (src) {
      fPrev.src = src; fPrev.style.display = '';
      if (badge) badge.style.display = '';
    } else {
      fPrev.removeAttribute('src'); fPrev.style.display = 'none';
      if (badge) badge.style.display = 'none';
    }
  }

  btnClear?.addEventListener('click', ()=> setFeatured(''));

  // Basit URL girişi
  btnUrl?.addEventListener('click', ()=>{
    const url = prompt('Görsel URL’si (uploads içinden bir yol da olabilir):', fInput.value || '');
    if (url !== null) setFeatured(url.trim());
  });

  // Medya seç: yeni pencerede /admin/medya aç, postMessage ile yol bekle
  btnPick?.addEventListener('click', ()=>{
    const w = window.open('<?= $BASE ?>/admin/medya?picker=1','medyaSec','width=1200,height=800');
    const onMsg = (e)=>{
      try {
        if (!e.data || e.data.type !== 'medya:secildi') return;
        // e.data.payload: { yol, yol_thumb }
        setFeatured(e.data.payload?.yol || '');
        window.removeEventListener('message', onMsg);
        w?.close();
      } catch(_){}
    };
    window.addEventListener('message', onMsg);
  });

  // --- Submit'te TR sekmesinden legacy alanlara kopyala (geri uyumluluk) ---
  const form = document.getElementById('sayfaForm') || document.querySelector('form');
  if (form) form.addEventListener('submit', () => {
    // Tüm editorleri textarea'ya yaz
    try { tinymce.triggerSave(); } catch(e) {}

    const pick = (n) => {
      // önce varsayılan dil
      let v = (document.querySelector(`[name="ceviri[${VARS_DIL}][${n}]"]`)||{}).value || '';
      if (v) return v;
      // boşsa diğer dillerde ilk dolu değeri al
      for (const L of LANGS) {
        if (L === VARS_DIL) continue;
        const el = document.querySelector(`[name="ceviri[${L}][${n}]"]`);
        if (el && el.value && el.value.trim() !== '') return el.value;
      }
      return '';
    };
    document.getElementById('legacy_baslik').value = pick('baslik');
    document.getElementById('legacy_slug').value = pick('slug') || (window.slugify ? window.slugify(pick('baslik')) : pick('baslik'));
    document.getElementById('legacy_ozet').value          = pick('ozet');
    document.getElementById('legacy_icerik').value        = pick('icerik');
    document.getElementById('legacy_meta_baslik').value   = pick('meta_baslik');
    document.getElementById('legacy_meta_aciklama').value = pick('meta_aciklama');
  });
});
</script>


<script>
(function(){
  const BASE  = '<?= $BASE ?? BASE_URL ?>';
  const CSRF  = document.querySelector('meta[name="csrf-token"]')?.content
             || document.querySelector('meta[name="csrf"]')?.content || '';
  const SLUG_API = BASE + '/admin/sayfalar/slug-kontrol';
  const SAYFA_ID = <?= (int)($sayfa['id'] ?? 0) ?>;

  // ---- küçük transliterasyon & slugify (tek kaynak) ----
  const slugify = window.slugify || (function(){
    const map = {'ç':'c','ğ':'g','ı':'i','ö':'o','ş':'s','ü':'u','Ç':'c','Ğ':'g','İ':'i','Ö':'o','Ş':'s','Ü':'u'};
    return function(s){
      if(!s) return '';
      s = String(s).replace(/[ÇĞİÖŞÜçğıöşü]/g, m => map[m] || m);
      s = s.normalize('NFKD').replace(/[\u0300-\u036f]/g,'');
      s = s.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').replace(/-{2,}/g,'-');
      return s.substring(0, 220);
    };
  })();

  // ---- dil bazlı dirty kontrolü ----
  const dirty = {}; // { 'tr': true/false, 'en': ... }
 
  function langOf(el){
    // input name="ceviri[tr][baslik]" -> tr
    const m = el.name && el.name.match(/^ceviri\[([^\]]+)\]\[/);
    return m ? m[1] : (el.dataset.lang || '');
  }

  // Başlık değiştikçe slug’ı güncelle (kullanıcı slug’a hiç dokunmamışsa)
  document.addEventListener('input', (e)=>{
    const title = e.target.closest('input[name^="ceviri["][name$="[baslik]"]');
    if(!title) return;
    const lang = langOf(title);
    const slugInput = document.querySelector(`input[name="ceviri[${lang}][slug]"]`);
    if(!slugInput) return;

    if (dirty[lang]) return; // kullanıcı slug’ı elle değiştirmiş

    const sug = slugify(title.value);
    slugInput.value = sug;
  });

  // Kullanıcı slug alanına yazarsa o dil için dirty= true
  document.addEventListener('input', (e)=>{
    const slug = e.target.closest('input[name^="ceviri["][name$="[slug]"]');
    if(!slug) return;
    const lang = langOf(slug);
    dirty[lang] = true;
  });

  // Slug alanından çıkınca benzersizlik kontrolü (boşsa başlıktan üret)
  document.addEventListener('blur', async (e)=>{
    const slug = e.target.closest('input[name^="ceviri["][name$="[slug]"]');
    if(!slug) return;
    const lang = langOf(slug);
    const title = document.querySelector(`input[name="ceviri[${lang}][baslik]"]`);
    let val = slug.value.trim() || slugify(title?.value || '');

    // boşsa dokunma
    if(!val){ slug.value = ''; return; }

    try{
      const res = await fetch(SLUG_API, {
        method: 'POST',
        headers: { 'Content-Type':'application/x-www-form-urlencoded',
                   'X-CSRF-TOKEN': CSRF, 'X-Requested-With':'XMLHttpRequest' },
        body: new URLSearchParams({ id: SAYFA_ID, dil_kod: lang, slug: val }),
        credentials: 'same-origin'
      });
      const j = await res.json();
      // API { ok:true, veri:{ slug:'...' } } döner varsayımı
      if(j && j.ok && j.veri && j.veri.slug){
        slug.value = j.veri.slug;
      } else {
        // API yoksa veya hata dönerse en azından normalize edilmiş hali kullan
        slug.value = val;
      }
    }catch(_){ slug.value = val; }
  }, true);
})();
</script>
