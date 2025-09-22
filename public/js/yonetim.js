// Güvenli BASE kestirici (yoksa oluştur)
const BASE = document.querySelector('meta[name="base"]')?.content || '';
const api  = (p) => `${BASE}${p}`;
if (typeof window.__guessBase !== 'function') {
  window.__guessBase = function () {
    // Örn: /fevzi/public/admin/medya  =>  /fevzi/public
    const p = window.location.pathname || '';
    const i = p.indexOf('/admin/');
    return i > 0 ? p.slice(0, i) : '';
  };
}

function __normUploadUrl(u) {
  if (!u || typeof u !== 'string') return '';
  try {
    const b = (typeof __guessBase === 'function') ? __guessBase() : '';
    // /uploads/... gibi kökten başlayan yolları BASE ile birleştir
    return (u[0] === '/') ? (b + u) : u;
  } catch (_) {
    return u; // her ihtimale karşı sessiz düş
  }
}

function __medyaAddCard(urlRaw, thumbRaw) {
  const url   = __normUploadUrl(urlRaw || '');
  const thumb = __normUploadUrl(thumbRaw || '');
  const imgUrl = thumb || url;
  if (!url) return;

  const hedef = document.getElementById('medyaSonuc') || document.querySelector('.container') || document.body;

  const card = document.createElement('div');
  card.className = 'card mb-3';
  card.innerHTML = `
    <div class="card-body d-flex align-items-center gap-3">
      <img src="${imgUrl}" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:.5rem;">
      <div>
        <div><strong>${url.split('/').pop().split('?')[0]}</strong></div>
        <div class="text-muted small">Yüklendi</div>
        <div class="d-flex gap-2">
          <a class="link-primary" href="${url}" target="_blank" rel="noopener">Görüntüle</a>
          <button type="button" class="btn btn-sm btn-outline-secondary js-copy-url" data-url="${url}">Kopyala</button>
        </div>
      </div>
    </div>`;
  hedef.prepend(card);
}

// ==== XHR yardımcı: gerçek byte ilerleme için ====
function __xhrUpload(endpoint, fd, token, onProgress) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', endpoint, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    if (token) xhr.setRequestHeader('X-CSRF-Token', token);
    xhr.responseType = 'text';
    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable && typeof onProgress === 'function') onProgress(e.loaded, e.total);
    };
    xhr.onerror = () => reject(new Error('network'));
    xhr.onload = () => resolve(xhr);
    xhr.send(fd);
  });
}

// Basit ilerleme çubuğu üretici
function __makeProgressBar() {
  const wrap = document.createElement('div');
  wrap.className = 'progress mb-3';
  wrap.innerHTML = `<div class="progress-bar" role="progressbar" style="width:0%">0%</div>`;
  const bar = wrap.firstElementChild;
  return {
    el: wrap,
    set(pct, label) {
      const p = Math.max(0, Math.min(100, Math.round(pct)));
      bar.style.width = p + '%';
      bar.textContent = (label ? `${p}% – ${label}` : p + '%');
    },
    removeLater() { setTimeout(() => wrap.remove(), 800); }
  };
}

const csrfMeta = document.querySelector('meta[name="csrf-token"]');
// --- CSRF yardımcı (tek kaynak) ---
function getCsrfToken(scope){
  return (scope?.querySelector?.('input[name="csrf"]')?.value)
      || document.querySelector('meta[name="csrf-token"]')?.content
      || document.querySelector('meta[name="csrf"]')?.content
      || document.getElementById('csrf')?.value
      || '';
}
const CSRF_TOKEN = csrfMeta ? csrfMeta.getAttribute('content') : null;

async function apiFetch(url, opts = {}) {
  const headers = new Headers(opts.headers || {});
  headers.set('Accept', 'application/json');
  if (!(opts.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json; charset=utf-8');
  }
  if (CSRF_TOKEN) headers.set('X-CSRF-Token', CSRF_TOKEN);
  return fetch(url, { method: 'POST', ...opts, headers });
}
// export { apiFetch } -- modül kullanıyorsan


document.addEventListener('DOMContentLoaded', () => {
  const yol = window.location.pathname.replace(/\/+$/, '');
  document.querySelectorAll('.sol-menu .menu-baglantisi').forEach(a => {
    const href = a.getAttribute('href').replace(/\/+$/, '');
    if (href && yol === href) {
      a.classList.add('fw-semibold', 'text-primary');
    }
  });
});

async function jsonSafe(resp) {
  const raw = await resp.text();
  try {
    return JSON.parse(raw);
  } catch (e) {
    const plain = raw.replace(/<[^>]*>/g, ' ').slice(0, 300).trim();
    throw new Error(plain || 'Geçersiz JSON yanıtı');
  }
}

function getFormKategori() {
  return document.getElementById('kategoriForm') || document.querySelector('form[action*="/admin/kategoriler"]');
}
function getFormSayfa() {
  return document.getElementById('sayfaForm') || document.getElementById('sayfalarForm') || document.querySelector('form[action*="/admin/sayfalar"]');
}

function seciliIds(scope) {
  const q = scope ? (sel) => scope.querySelectorAll(sel) : (sel) => document.querySelectorAll(sel);
  return Array.from(q('.sec-kayit:checked, .chk:checked, .secim:checked'))
    .map(ch => parseInt(ch.value, 10))
    .filter(Number.isFinite);
}

function isAktifVal(v) {
  return v === true || v === 1 || v === '1' || v === 'aktif' || v === 'yayinda' || v === 'yayında';
}

function guncelleSatirDurum(ids, aktif, yaziAktif, yaziPasif) {
  ids.forEach(id => {
    // 1) Kategorilerde kullandığımız buton (data-id ile)
    let btn = document.querySelector(`.js-durum-btn[data-id="${id}"]`);

    // 2) Sayfalar listesinde satır içi form düğmesi
    if (!btn) btn = document.querySelector(`button[form="durum-form-${id}"]`);

    // 3) Çöp modda rozet (badge); data-id eklediysen bunu da günceller
    if (!btn) btn = document.querySelector(`span.badge[data-id="${id}"]`);

    if (!btn) return; // Eleman bulunamazsa sessiz geç

    btn.textContent = aktif ? yaziAktif : yaziPasif;

    // Butonsa Bootstrap sınıflarını güncelle
    btn.classList.toggle('btn-success', aktif);
    btn.classList.toggle('btn-secondary', !aktif);

    // Rozetse (badge) renk sınıflarını güncelle
    btn.classList.toggle('bg-success', aktif);
    btn.classList.toggle('bg-secondary', !aktif);
  });
}

async function topluDurumKategori(btn, durumInt) {
  const form = getFormKategori();
  if (!form) return alert('Form bulunamadı.');
  const ids = seciliIds(form);
  if (!ids.length) return alert('Önce satır seç.');

  const token = getCsrfToken(form);
  btn.disabled = true;

  try {
    const params = new URLSearchParams();
    params.append('csrf', token);
    params.append('durum', String(durumInt));
    ids.forEach(id => params.append('ids[]', id));

    const resp = await fetch(btn.dataset.url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-CSRF-Token': token,
        'X-Requested-With': 'XMLHttpRequest',  // <<< eklendi
        'Accept': 'application/json'            // <<< eklendi
      },
      body: params
    });

    const data = await jsonSafe(resp); // << resp.json() YERİNE
    if (!data.ok) throw new Error(data.mesaj || 'Güncelleme başarısız');

    guncelleSatirDurum(data.ids || ids, isAktifVal(data.durum), 'Aktif', 'Pasif');

    // seçimleri temizle
    const master = form.querySelector('#secTum');
    if (master) { master.checked = false; master.indeterminate = false; }
    form.querySelectorAll('.sec-kayit:checked, .chk:checked, .secim:checked').forEach(ch => ch.checked = false);
  } catch (err) {
    console.error(err);
    alert('Toplu durum değiştirilemedi: ' + err.message);
  } finally {
    btn.disabled = false;
  }
}

async function topluDurumSayfa(btn, durumStr) {
  const form = getFormSayfa();
  if (!form) return alert('Form bulunamadı.');
  const ids = seciliIds(form);
  if (!ids.length) return alert('Önce satır seç.');

  const token = getCsrfToken(form);
  btn.disabled = true;

  try {
    const params = new URLSearchParams();
    params.append('csrf', token);
    params.append('durum', durumStr);
    ids.forEach(id => params.append('ids[]', id));

    const resp = await fetch(btn.dataset.url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-CSRF-Token': token,
        'X-Requested-With': 'XMLHttpRequest',  // <<< eklendi
        'Accept': 'application/json'            // <<< eklendi
      },
      body: params
    });

    const data = await jsonSafe(resp); // << resp.json() YERİNE
    if (!data.ok) throw new Error(data.mesaj || 'Güncelleme başarısız');

    guncelleSatirDurum(data.ids || ids, isAktifVal(data.durum), 'Aktif', 'Taslak');

    // seçimleri temizle
    form.querySelector('#secTum')?.checked && (form.querySelector('#secTum').checked = false);
    form.querySelectorAll('.chk:checked').forEach(ch => ch.checked = false);
  } catch (err) {
    console.error(err);
    alert('Toplu durum değiştirilemedi: ' + err.message);
  } finally {
    btn.disabled = false;
  }
}

document.addEventListener('click', (e) => {
  // Kategoriler
  if (e.target.id === 'bulkAktifBtn')  { topluDurumKategori(e.target, 1); }
  if (e.target.id === 'bulkPasifBtn')  { topluDurumKategori(e.target, 0); }

  // Sayfalar
  if (e.target.id === 'bulkAktifBtnSayfa')  { topluDurumSayfa(e.target, 'aktif'); }
  if (e.target.id === 'bulkTaslakBtnSayfa') { topluDurumSayfa(e.target, 'taslak'); }
});

function simpleSlugify(s) {
  return (s || '')
    .toString()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/ı/g, 'i').replace(/ğ/g,'g').replace(/ü/g,'u').replace(/ş/g,'s').replace(/ö/g,'o').replace(/ç/g,'c')
    .replace(/[^a-z0-9\- ]/g, ' ')
    .trim()
    .replace(/\s+/g, '-')
    .replace(/\-+/g, '-');
}

async function checkSlug(inputEl, csrfToken) {
  const url  = inputEl.dataset.checkUrl;
  const id   = inputEl.dataset.id || 0;
  const slug = inputEl.value.trim();
  if (!url || !slug) { markInvalid(inputEl, true); return; }

  const resp = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-CSRF-Token': csrfToken },
    body: new URLSearchParams({ slug, id, csrf: csrfToken })
  });

  const raw = await resp.text();
  let data; try { data = JSON.parse(raw); } catch(e) { data = { ok:false, mesaj:'Geçersiz yanıt' }; }

  if (!data.ok) { markInvalid(inputEl, true, data.mesaj || 'Hata'); return; }
  markInvalid(inputEl, !data.uygun);
}

function markInvalid(el, invalid, msg) {
  const fb = el.parentElement.querySelector('.invalid-feedback');
  el.classList.toggle('is-invalid', invalid);
  if (fb && msg) fb.textContent = msg;
  const form = el.closest('form');
  const submit = form?.querySelector('button[type="submit"],input[type="submit"]');
  if (submit) submit.disabled = invalid;
}

(function initKategoriFormSlug() {
  const form = document.querySelector('form[action*="/admin/kategoriler"]');
  if (!form) return;
  const token = form.querySelector('input[name="csrf"]')?.value
             || form.querySelector('input[name="csrf_token"]')?.value
             || '';
  const ad   = form.querySelector('#kategoriAd');
  const slug = form.querySelector('#kategoriSlug');
  if (!slug) return;

  // Kullanıcı slug alanına dokunursa "dirty" olsun; boşaltırsa tekrar auto.
  let slugDirty = false;
  slug.addEventListener('input', () => {
    slugDirty = slug.value.trim() !== '';
  });

  // Başlık yazılırken, kullanıcı slug'a dokunmadıysa otomatik güncelle
  if (ad) {
    ad.addEventListener('input', () => {
      if (!slugDirty) {
        slug.value = simpleSlugify(ad.value);
        if (slug.value.trim()) checkSlug(slug, token);
      }
    });
  }

  // Slug'ta canlı benzersizlik kontrolü
  let t;
  ['input','blur','change'].forEach(ev => slug.addEventListener(ev, () => {
    clearTimeout(t);
    t = setTimeout(() => checkSlug(slug, token), ev === 'blur' ? 0 : 350);
  }));
})();

(function initSayfaFormSlug() {
  const form = document.querySelector('form[action*="/admin/sayfalar"]');
  if (!form) return;

  const token  = form.querySelector('input[name="csrf"]')?.value
             || form.querySelector('input[name="csrf_token"]')?.value
             || '';

  const baslik = form.querySelector('#sayfaBaslik');
  const slug   = form.querySelector('#sayfaSlug');
  if (!slug) return;

  // Slug alanı boşsa YA DA mevcut slug şimdiki başlığın slug'ına eşitse auto-mod açık başlasın
  let autoSlug = (slug.value.trim() === '' || slug.value.trim() === simpleSlugify((baslik?.value || '')));

  function syncFromTitle() {
    if (!autoSlug || !baslik) return;
    const v = simpleSlugify(baslik.value);
    if (slug.value !== v) {
      slug.value = v;
      if (v) checkSlug(slug, token);
    }
  }

  // Başlık yazıldıkça auto-mod açıksa slug'ı güncelle
  if (baslik) baslik.addEventListener('input', syncFromTitle);

  // Kullanıcı slug alanına gerçekten yazarsa (veya yapıştırırsa) auto-mod kapanır
  ['keydown','paste','drop','compositionstart'].forEach(ev => {
    slug.addEventListener(ev, () => { autoSlug = false; });
  });

  // Slug tekrar tamamen boşaltılırsa auto-mod yeniden açılsın
  slug.addEventListener('input', () => {
    if (slug.value.trim() === '') {
      autoSlug = true;
      syncFromTitle();
    }
  });

  // Canlı benzersizlik kontrolü (mevcut davranışı koru)
  let t;
  ['input','blur','change'].forEach(ev => slug.addEventListener(ev, () => {
    clearTimeout(t);
    t = setTimeout(() => checkSlug(slug, token), ev === 'blur' ? 0 : 350);
  }));

  // Yeni sayfa formu ilk açıldığında da senkronla
  syncFromTitle();
})();

(function initOzetCounter() {
  function bind() {
    const t = document.getElementById('ozet');
    if (!t) return;

    const max = parseInt(t.dataset.maxlen || '200', 10);
    const counter = document.getElementById('ozetCount');

    function update() {
      const len = (t.value || '').trim().length;
      if (counter) counter.textContent = String(len);
      const invalid = len > max;
      t.classList.toggle('is-invalid', invalid);
      const form = t.closest('form');
      const submit = form?.querySelector('button[type="submit"],input[type="submit"]');
      if (submit) submit.disabled = invalid;
    }

    ['input','change','blur'].forEach(ev => t.addEventListener(ev, update));
    update();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind, { once: true });
  } else {
    bind();
  }
})();

// Toplu işlemler: .js-bulk
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.js-bulk');
  if (!btn) return;

  const aksiyon = btn.dataset.aksiyon; // 'aktif' | 'taslak' | 'sil'
  const url     = btn.dataset.url;

  const ids = Array.from(document.querySelectorAll('.sec-kayit:checked, .chk:checked, .secim:checked')).map(i => i.value);
  if (ids.length === 0) { alert('Önce satır seçmelisin.'); return; }

  if (aksiyon === 'sil') {
    const ok = await askConfirm('Seçilen kayıtlar çöp kutusuna taşınacak. Onaylıyor musunuz?');
    if (!ok) return;
  }

  try {
    btn.disabled = true;
    const payload = (aksiyon === 'sil') ? { ids } : { ids, durum: aksiyon };
    const data = await postFormData(url, payload);  // <<< tek kanal
    if (!data || data.ok !== true) throw new Error(data?.mesaj || 'İşlem başarısız');

    if (aksiyon === 'sil') {
      ids.forEach(id => document.querySelector(`tr[data-id="${id}"]`)?.remove());
    } else {
      const aktifMiGlobal = (data && 'durum' in data) ? isAktifVal(data.durum) : (aksiyon === 'aktif');
      const offText = (url && url.includes('/sayfalar/')) ? 'Taslak' : 'Pasif';
      ids.forEach(id => {
        const b = document.querySelector(`tr[data-id="${id}"] .js-durum-btn`);
        if (!b) return;
        b.classList.toggle('btn-success',  aktifMiGlobal);
        b.classList.toggle('btn-secondary', !aktifMiGlobal);
        b.textContent = aktifMiGlobal ? 'Aktif' : offText;
      });
    }
  } catch (err) {
    console.error(err);
    alert(err.message);
  } finally {
    btn.disabled = false;
  }
});

// "Bu kayıt çöp kutusuna taşınacak" onayı ile form submit
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.js-confirm-submit');
  if (!btn) return;

  const form = btn.closest('form');
  if (!form) return;

  const ok = await askConfirm(btn.dataset.msg || 'Bu kayıt çöp kutusuna taşınacak. Onaylıyor musunuz?');
  if (ok) form.submit();
});

/* ==== Bootstrap confirm (var olan #confirmModal kullanılır) ==== */
function askConfirm(msg, title){
  const modalEl = document.getElementById('confirmModal');
  if (!modalEl) return Promise.resolve(confirm(msg || 'Onaylıyor musunuz?'));
  const bs = new bootstrap.Modal(modalEl);
  const t = modalEl.querySelector('.modal-title');
  const b = modalEl.querySelector('#confirmModalMsg');
  const okBtn = modalEl.querySelector('#confirmModalOk');
  if (t) t.textContent = title || 'Onay';
  if (b) b.textContent = msg || 'Bu işlemi onaylıyor musunuz?';

  return new Promise(resolve => {
    let decided = false;
    const done = (val) => { if (decided) return; decided = true; cleanup(); resolve(val); };
    const onOk  = () => { done(true); bs.hide(); };
    const onHide= () => done(false);
    const cleanup = () => {
      okBtn.removeEventListener('click', onOk);
      modalEl.removeEventListener('hidden.bs.modal', onHide);
    };
    okBtn.addEventListener('click', onOk);
    modalEl.addEventListener('hidden.bs.modal', onHide, {once:true});
    bs.show();
  });
}

// --- Tüm POST'lar buradan geçsin (CSRF header + body) ---
async function postFormData(url, obj){
  const fd = new FormData();
  Object.entries(obj || {}).forEach(([k,v])=>{
    if (Array.isArray(v)) v.forEach(val => fd.append(k+'[]', val));
    else fd.append(k, v);
  });

  // CSRF token'ı hem header'a hem body'ye koy
  const token =
    (typeof getCsrfToken === 'function' && getCsrfToken(document)) ||
    document.querySelector('meta[name="csrf-token"]')?.content ||
    document.querySelector('meta[name="csrf"]')?.content ||
    document.getElementById('csrf')?.value || '';

  if (token && !fd.has('csrf')) fd.append('csrf', token);

  const r = await fetch(url, {
    method: 'POST',
    headers: {
      'X-CSRF-Token': token,
      'X-Requested-With': 'fetch',
      'Accept': 'application/json'
    },
    body: fd
  });

  try { return await r.json(); } catch { return { ok:false }; }
}

// --- Çöp rozeti: her zaman görünür; değer 0 olsa bile ---
async function refreshCopBadge(){
  const link  = document.querySelector('#copLink[data-count-url]');
  const badge = document.getElementById('copBadge');
  if (!link || !badge) return;
  try {
    const r = await fetch(link.dataset.countUrl, { headers: { 'Accept': 'application/json' } });
    const j = await r.json();
    const n = Number(j?.n ?? j?.count ?? 0);
    badge.textContent = Number.isFinite(n) ? String(n) : '0';
    badge.classList.remove('d-none');           // << 0 olsa bile göster
  } catch {
    // Hata halinde de rozet 0 olarak görünsün
    badge.textContent = '0';
    badge.classList.remove('d-none');
  }
}
document.addEventListener('DOMContentLoaded', refreshCopBadge);

// ==== Medya Yükleme (byte progress + multiple + drag&drop) ====
function __bindMedyaUpload() {
  // Bu özellik yalnız medya sayfasında aktif olmalı
  const drop = document.getElementById('dropZone');
  if (!drop) return; // Medya sayfası değil → hiç bağlama

  const form = document.getElementById('medyaYukleForm')
           || document.querySelector('form[action*="/admin/medya/yukle"]')
           || document.getElementById('medyaForm'); // body fallback YOK
  if (!form) return; // Form da yoksa hiç bağlama

  if (form.dataset.uploadBound === '1') return; // ikinci kez bağlama
  form.dataset.uploadBound = '1';

  let fileInput = form.querySelector('input[type="file"]');
  if (!fileInput) {
    // Yedek: input formda yoksa dinamik oluştur
    fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.name = 'file';
    fileInput.accept = 'image/jpeg,image/png,image/webp';
    fileInput.multiple = true;
    fileInput.hidden = true;
    form.appendChild(fileInput);
  }

  const endpoint =
    document.querySelector('form[action*="/admin/medya/yukle"]')?.getAttribute('action')
    || (__guessBase() + '/admin/medya/yukle');
  const token = getCsrfToken(form);
  const hedef = document.getElementById('medyaSonuc') || document.body;
  const btn = form.querySelector('#btnUpload') || form.querySelector('button[type="submit"]');  
  let prog = null; // progress bar, dış scope

  // Butona basınca yükle (native submit yerine)
  btn?.addEventListener('click', (e) => {
    e.preventDefault();
    runUpload(fileInput.files);
  });

  // Dosya seçer seçmez otomatik yükle (tek ya da çoklu)
  fileInput.addEventListener('change', () => {
    if (fileInput.files && fileInput.files.length) runUpload(fileInput.files);
  });  

  // İstemci tarafı sınırlar (8MB ve tür filtresi)
  const MAX_BYTES = Number(form.dataset.maxBytes || 8*1024*1024);
  const ALLOW = new Set(['image/jpeg','image/png','image/webp']);
  if (!fileInput.getAttribute('accept')) {
    fileInput.setAttribute('accept','image/jpeg,image/png,image/webp');
  }

  // Tek dosya yükle
  async function uploadOne(file) {

    // İstemci kontrolü: tür + boyut
    if (!ALLOW.has(file.type)) {
      const ext = (file.name.split('.').pop() || '').toLowerCase();
      const msg = `Desteklenmeyen tür: ${ext || file.type || 'bilinmiyor'}. Sadece JPEG, PNG, WEBP.`;
      (typeof showToast === 'function') ? showToast(msg, 'info') : alert(msg);
      return;
    }
    if (file.size > MAX_BYTES) {
      const mb = (file.size/1048576).toFixed(2);
      const msg = `Dosya çok büyük (${mb} MB). İzin verilen en fazla ${(MAX_BYTES/1048576).toFixed(0)} MB.`;
      (typeof showToast === 'function') ? showToast(msg, 'info') : alert(msg);
      return;
    } 

    // FormData (form alanlarını kopyala + dosyayı ekle)
    const fd = new FormData();           // formları kopyalama yok
    fd.append('file', file);
    if (token) fd.append('csrf', token); // CSRF’i sayfadan okuduk

    // XHR ile yükle, byte bazlı ilerleme
    const xhr = await __xhrUpload(endpoint, fd, token, (loaded, total) => {
      const label = `${file.name} (${(loaded/1048576).toFixed(2)} / ${(total/1048576).toFixed(2)} MB)`;
      if (prog) prog.set((loaded / total) * 100, label);
    });

    // Yanıtı işle
    const raw = xhr.responseText || '';
    let data = {}; try { data = JSON.parse(raw); } catch {}
    const ok = xhr.status >= 200 && xhr.status < 300 && data && (data.ok !== false);
    const rawUrl   = (data && (data.url || data.location)) || '';
    const rawThumb = (data && data.thumb) || '';
    if (!ok || (!rawUrl && !rawThumb)) {
      const msg = (data && (data.mesaj || data.error)) || `Yükleme başarısız (HTTP ${xhr.status})`;
      (typeof showToast === 'function') ? showToast(msg, 'info') : alert(msg);
      return;
    }
    __medyaAddCard(rawUrl, rawThumb);
  }

  // Çoklu yükleme: sırayla
  async function runUpload(files) {
    const list = Array.from(files || []);
    if (!list.length) {
      (typeof showToast === 'function') ? showToast('Yüklenecek dosya yok.', 'info') : alert('Yüklenecek dosya yok.');
      return;
    }

    // --- burada kilidi set et ---
    if (form.dataset.uploadRunning === '1') return;
    form.dataset.uploadRunning = '1';

    // --- progress bar'ı burada oluştur ---
    prog = __makeProgressBar();
    (document.getElementById('medyaSonuc') || document.body).prepend(prog.el);

    // butonu kilitle
    (form.querySelector('#btnUpload') || form.querySelector('button[type="submit"],input[type="submit"]'))?.setAttribute('disabled','disabled');

    try {
      for (const f of list) {
        await uploadOne(f);
      }
      (typeof showToast === 'function') ? showToast('Yükleme tamamlandı.', 'success') : null;
      form.reset();
      prog.set(100, 'Tamamlandı');
    } catch (e) {
      console.error(e);
      (typeof showToast === 'function') ? showToast('Ağ hatası oluştu.', 'info') : alert('Ağ hatası oluştu.');
    } finally {
      (form.querySelector('#btnUpload') || form.querySelector('button[type="submit"],input[type="submit"]'))?.removeAttribute('disabled');
      delete form.dataset.uploadRunning;
      if (prog) { prog.removeLater(); prog = null; }
    }
  }

  // Sadece upload formunda native submit'i engelle
  if (form.id === 'medyaYukleForm' || /\/admin\/medya\/yukle/i.test(form.getAttribute('action') || '')) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      e.stopImmediatePropagation();
    }, { capture: true, passive: false });
  }

  // Dropzone erişilebilirlik ve tıklanabilirlik
  drop.setAttribute('role','button');
  drop.setAttribute('tabindex','0');
  drop.style.cursor = 'pointer';
  drop.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      drop.click();
    }
  });  

  ['dragenter','dragover'].forEach(evt => drop.addEventListener(evt, (e) => {
    e.preventDefault(); e.stopPropagation();
    drop.classList.add('is-dragover');
  }));

  ['dragleave','dragend','drop'].forEach(evt => drop.addEventListener(evt, (e) => {
    if (evt !== 'drop') { e.preventDefault(); e.stopPropagation(); }
    drop.classList.remove('is-dragover');
  }));

  drop.addEventListener('drop', (e) => {
    e.preventDefault(); e.stopPropagation();
    const files = Array.from(e.dataTransfer?.files || []);
    runUpload(files);
  });

  // Drop alanına tıklayınca dosya seçici açılsın
  drop.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();

    // Chromium destekliyorsa en temiz yol
    if (typeof fileInput.showPicker === 'function') {
      try { fileInput.showPicker(); return; } catch (_) { /* fallback'e geç */ }
    }

    // Fallback: input gizliyse anlık görünür/erişilebilir yap, tıkla, tekrar gizle
    const wasHiddenAttr = fileInput.hasAttribute('hidden');
    if (wasHiddenAttr) fileInput.removeAttribute('hidden');

    const prevStyle = fileInput.getAttribute('style') || '';
    // Ekran dışına taşı – display:none YAPMA
    fileInput.setAttribute('style', (prevStyle ? prevStyle + '; ' : '') +
      'position:fixed;left:0;top:0;opacity:0.01;width:1px;height:1px;');

    try { fileInput.click(); } finally {
      // kısa bir gecikmeyle eski duruma dön
      setTimeout(() => {
        fileInput.setAttribute('style', prevStyle);
        if (wasHiddenAttr) fileInput.setAttribute('hidden','');
      }, 0);
    }
  });
}

// Dinleyici bağlama: DOM hazır olduktan sonra
if (document.readyState !== 'loading') {
  __bindMedyaUpload();
} else {
  if (document.readyState !== 'loading') {
    __bindMedyaUpload();
  } else {
    document.addEventListener('DOMContentLoaded', __bindMedyaUpload, { once: true });
  }
}

// === Tekil sil (dinamik kartlar için delegasyon) ===
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.btn-sil-tek');
  if (!btn) return;

  e.preventDefault();

  const id   = btn.dataset.id;
  const name = btn.dataset.name || ('#' + id);

  const hiddenSingleId = document.getElementById('hiddenSingleId');
  const confirmText    = document.getElementById('confirmText');
  const confirmBtn     = document.getElementById('confirmBtn');
  const confirmModalEl = document.getElementById('confirmDeleteModal');
  const medyaForm      = document.getElementById('medyaForm');

  if (!hiddenSingleId || !confirmText || !confirmBtn || !confirmModalEl || !medyaForm) return;

  hiddenSingleId.value = id;
  confirmText.textContent = `"${name}" adlı görsel silinecek. Bu işlem geri alınamaz.`;

  const modal = (bootstrap && bootstrap.Modal && bootstrap.Modal.getOrCreateInstance)
    ? bootstrap.Modal.getOrCreateInstance(confirmModalEl)
    : new bootstrap.Modal(confirmModalEl);

  const onConfirm = (ev) => {
    ev.preventDefault();
    medyaForm.action = (typeof BASE !== 'undefined') ? `${BASE}/admin/medya/sil` : '/admin/medya/sil';
    modal.hide();
    medyaForm.submit(); // native submit: olası submit handler'ı bypass eder
  };
  confirmBtn.addEventListener('click', onConfirm, { once: true });

  modal.show();
});

// === TEK YETKİLİ durum toggle handler (çakışma/yarışı engelle) ===
document.addEventListener('click', async (e) => {
  // Sadece /admin/sayfalar sayfalarında çalış
  if (!/\/admin\/sayfalar(\/|$)/.test(location.pathname)) return;

  const btn = e.target.closest('.js-durum-btn');
  if (!btn) return;

  e.preventDefault();
  e.stopPropagation();
  if (btn.dataset.busy === '1') return;
  btn.dataset.busy = '1';

  const url = btn.dataset.url;
  const id  = btn.dataset.id || btn.closest('tr[data-id]')?.dataset.id;
  if (!url || !id) { btn.dataset.busy = '0'; return; }

  // İstek gönderici (FormData + JSON bekler)
  async function postFD(u, obj){
    const fd = new FormData();
    Object.entries(obj).forEach(([k,v])=>fd.append(k,v));
    const r = await fetch(u, {
      method:'POST',
      headers:{
        'X-CSRF-Token': getCsrfToken(btn.closest('form') || document),
        'X-Requested-With':'XMLHttpRequest',
        'Accept':'application/json'
      },
      body: fd
    });
    try { return await r.json(); } catch { return {ok:false}; }
  }

  btn.disabled = true;
  try {
    const res = await postFD(url, { id });
    if (!res?.ok) throw new Error(res?.mesaj || 'Güncellenemedi');

    // Sunucudan kesin durum
    const s = String(res.durum_str ?? '').toLowerCase();
    const aktif = Number(res.durum) === 1 || s === 'aktif' || s === 'yayinda' || s === 'yayında';

    // Sayfalar → 'Taslak', Kategoriler → 'Pasif'
    const offText = url.includes('/sayfalar/') ? 'Taslak' : 'Pasif';

    // Önce her iki sınıfı da kaldır, sonra tekini ekle (çifte sınıf kalmasın)
    btn.classList.remove('btn-success', 'btn-secondary');
    btn.classList.add(aktif ? 'btn-success' : 'btn-secondary');

    btn.textContent = aktif ? 'Aktif' : offText;
    btn.setAttribute('aria-pressed', aktif ? 'true' : 'false');
    btn.dataset.state = aktif ? '1' : '0';
  } catch (err) {
    console.error(err);
    alert(err.message);
  } finally {
    btn.disabled = false;
    btn.dataset.busy = '0';
  }
});


// --- Cursor'u garanti altına al (inline !important) ---
document.addEventListener('DOMContentLoaded', () => {
  const sel = 'a[href],button,.btn,[role="button"],.nav-link,.dropdown-item,.page-link';
  document.querySelectorAll(sel).forEach(el => {
    // inline !important yazar; harici stilleri ezer
    el.style.setProperty('cursor', 'pointer', 'important');
  });
function __secimSay() {
  const n = document.querySelectorAll('.sec-kayit:checked, .chk:checked').length;
  const el = document.getElementById('secimSayaci');
  if (el) el.textContent = `${n} seçili`;
}
document.addEventListener('change', (e) => {
  if (e.target.matches('.sec-kayit,.chk,#secTum')) __secimSay();
});
document.addEventListener('DOMContentLoaded', __secimSay);
});

// --- Seç-Tüm (#secTum) ve satır seçimleri (global) ---
(function () {
  const rowSel = '.sec-kayit, .chk, .secim';

  function updateCount(selected = null) {
    const el = document.getElementById('secimSayaci');
    if (!el) return;
    const n = selected ?? document.querySelectorAll(`${rowSel}:checked`).length;
    el.textContent = `${n} seçili`;
  }

  document.addEventListener('change', (e) => {
    const master = document.getElementById('secTum');

    // 1) Seç-Tüm kutusu değiştiyse → tüm satırları eşitle
    if (master && e.target === master) {
      document.querySelectorAll(rowSel).forEach(ch => { if ('checked' in ch) ch.checked = master.checked; });
      master.indeterminate = false;
      updateCount();
      return;
    }

    // 2) Herhangi bir satır checkbox'ı değiştiyse → Seç-Tüm'ü güncelle
    if (e.target && e.target.matches(rowSel)) {
      const boxes = document.querySelectorAll(rowSel);
      const selected = document.querySelectorAll(`${rowSel}:checked`).length;
      if (master) {
        master.indeterminate = selected > 0 && selected < boxes.length;
        master.checked = boxes.length > 0 && selected === boxes.length;
      }
      updateCount(selected);
    }
  });
})();

// --- Küçük toast araçları (Bootstrap 5) ---
function showToast(msg = 'İşlem tamam', type = 'success'){
  // container layout'ta var: <div class="toast-container ..." id="toastContainer"></div>
  const cont = document.getElementById('toastContainer');
  if (!cont) return alert(msg);

  const el = document.createElement('div');
  el.className = 'toast';
  el.role = 'alert';
  el.ariaLive = 'assertive';
  el.ariaAtomic = 'true';
  el.innerHTML = `
    <div class="toast-header">
      <strong class="me-auto">${type === 'success' ? 'Başarılı' : 'Bilgi'}</strong>
      <small class="text-muted">şimdi</small>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Kapat"></button>
    </div>
    <div class="toast-body">${msg}</div>
  `;
  cont.appendChild(el);
  try {
    const t = new bootstrap.Toast(el, { delay: 2000 });
    t.show();
    el.addEventListener('hidden.bs.toast', ()=> el.remove());
  } catch { /* bootstrap yoksa alert'e düşer */ }
}




(function () {
  const csrf = document.querySelector('meta[name="csrf"]')?.getAttribute('content') || '';
  const grid = document.getElementById('medya-grid');
  const ara  = document.getElementById('medya-ara');
  const etiketWrap  = document.getElementById('etiket-filter');
  const modeBtn     = document.getElementById('etiket-mode');
  const clearBtn    = document.getElementById('etiket-clear');
  const sonucBilgi  = document.getElementById('sonuc-bilgi');

  let tags = new Set();     // çoklu seçim
  let mode = 'any';         // 'any' | 'all'  (Mod: En az biri = any)
  let q = '';
  let sayfa = 1;
  let isLoading = false;
  // — Önizleme modalı için aktif media id
  let __metaCurrentMid = null;

  function htmlesc(s){return s.replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}

  async function fetchJSON(url, opt) {
    const o = Object.assign({ headers: {} }, opt || {});
    if (o.method && o.method.toUpperCase() !== 'GET') {
      o.headers['Content-Type'] = 'application/json';
      if (csrf) o.headers['X-CSRF-Token'] = csrf;
    }
    const r = await fetch(url, o);
    return r.json();
  }

function kartHTML(k) {
  const raw   = k.yol_thumb || k.yol;
  const thumb = (typeof __normUploadUrl === 'function') ? __normUploadUrl(raw) : raw;
  const full  = (typeof __normUploadUrl === 'function') ? __normUploadUrl(k.yol) : k.yol;
  const fname = (k.yol || '').split('/').pop() || ('#' + k.id);
  const etiketler = (k.etiketler||[]).map(e => `<span class="badge text-bg-light border">${htmlesc(e.ad)}</span>`).join(' ');

  return `
    <div class="col">
      <div class="card shadow-sm h-100">
        <a href="${htmlesc(full)}" class="media-thumb" data-src="${htmlesc(full)}" data-mid="${k.id}">
          <img src="${htmlesc(thumb)}" class="card-img-top" loading="lazy" alt="">
        </a>

        <div class="card-body p-2">
          <div class="small text-muted mb-1">
              ${htmlesc((k.yol||'').split('/').pop() || ('dosya-'+k.id))}
            &nbsp;·&nbsp;${(k.genislik||'?')}×${(k.yukseklik||'?')}
          </div>
          <div class="d-flex flex-wrap gap-1">${etiketler}</div>
        </div>

        <!-- view ile tam uyumlu footer: -->
        <div class="card-footer d-flex justify-content-between align-items-center p-2">
          <div class="form-check m-0">
            <input class="form-check-input chk" type="checkbox" name="ids[]" value="${k.id}">
          </div>
          <button type="button"
                  class="btn btn-sm btn-outline-danger btn-sil-tek"
                  data-id="${k.id}"
                  data-name="${htmlesc(fname)}">Sil</button>
        </div>
      </div>
    </div>`;
}


  async function listeYukle(reset = true) {
    if (isLoading) return;
    isLoading = true;
    if (reset) {
      sayfa = 1;
      grid.innerHTML = '';
    }
    const params = new URLSearchParams();
    if (q) params.set('q', q);
    if (tags.size) params.set('tags', Array.from(tags).join(','));
    if (mode && tags.size) params.set('mode', mode);
    params.set('sayfa', String(sayfa));
    params.set('limit', '36');

    const js = await fetchJSON(api(`/admin/api/medya?${params.toString()}`));
    const kayitlar = js.kayitlar || [];
    const frag = document.createDocumentFragment();
    const tmp = document.createElement('div');
    tmp.innerHTML = kayitlar.map(kartHTML).join('');
    while (tmp.firstChild) frag.appendChild(tmp.firstChild);
    grid.appendChild(frag);
    isLoading = false;
  }

  async function etiketBulutu() {
    const js = await fetchJSON(api('/admin/api/medya/etiketler'));
    const el = etiketWrap;
    el.innerHTML = '';

    // "Tümü" (filtre temizle)
    const hepsi = document.createElement('button');
    hepsi.className = 'btn btn-sm ' + (tags.size ? 'btn-outline-secondary' : 'btn-secondary');
    hepsi.textContent = 'Tümü';
    hepsi.onclick = () => { tags.clear(); listeYukle(true); etiketBulutu(); };
    el.appendChild(hepsi);

    // Etiket chip'leri (çoklu seçim)
    (js.etiketler || []).forEach(e => {
      const b = document.createElement('button');
      const selected = tags.has(e.slug);
      b.className = 'btn btn-sm ' + (selected ? 'btn-secondary' : 'btn-outline-secondary');
      b.innerHTML = `${e.ad || e.slug} <span class="opacity-75">(${e.adet || 0})</span>`;
      b.dataset.slug = e.slug;
      b.onclick = () => {
        if (tags.has(e.slug)) { tags.delete(e.slug); } else { tags.add(e.slug); }
        listeYukle(true);
        etiketBulutu();
      };
      el.appendChild(b);
    });

    // Mod düğmesi etiketini ve rengini güncelle
    if (modeBtn) {
      modeBtn.textContent = 'Mod: ' + (mode === 'all' ? 'Tümü' : 'En az biri');
      modeBtn.classList.toggle('btn-danger', mode === 'any');
      modeBtn.classList.toggle('btn-warning', mode === 'all');
    }
  }

  // Arama debounce
  let t;
  ara?.addEventListener('input', () => {
    q = ara.value.trim();
    clearTimeout(t);
    t = setTimeout(() => listeYukle(true), 250);
  });

const bulkTagBtn     = document.getElementById('bulkTagBtn');
// Eski listener’ları temizle (butonu klonla)
if (bulkTagBtn) {
  const clone = bulkTagBtn.cloneNode(true);
  bulkTagBtn.parentNode.replaceChild(clone, bulkTagBtn);
  // ve clone üstüne yukarıdaki click handler'ı bağla:
  clone.addEventListener('click', () => {
    const ids = Array.from(document.querySelectorAll('.chk:checked')).map(ch => +ch.value);
    if (!ids.length) { showToast('Seçili görsel yok.', 'warning'); return; }
    document.getElementById('bulkTagsInput').value = '';
    bulkTagModal?.show();
    setTimeout(() => document.getElementById('bulkTagsInput')?.focus(), 150);
  });
}
const bulkTagModalEl = document.getElementById('bulkTagModal');
const bulkTagModal   = bulkTagModalEl ? new bootstrap.Modal(bulkTagModalEl) : null;

document.getElementById('bulkTagForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const ids  = Array.from(document.querySelectorAll('.chk:checked')).map(ch => +ch.value);
  const tags = document.getElementById('bulkTagsInput').value || '';
  if (!ids.length) { showToast('Seçili görsel yok.', 'warning'); return; }

  const csrf = document.querySelector('#medyaForm input[name="csrf"]')?.value
            || document.querySelector('meta[name="csrf"]')?.content || '';

  // UI kilitle
  const saveBtn = document.getElementById('bulkTagSaveBtn');
  saveBtn.disabled = true;

  let ok = 0, fail = 0;
  for (const id of ids) {
    const resp = await fetch(`${BASE}/admin/api/medya/etiketle`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(csrf ? { 'X-CSRF-Token': csrf, 'X-CSRF': csrf } : {})
      },
      body: JSON.stringify({ medya_id: id, etiketler: tags })
    });
    const txt = await resp.text();
    let js; try { js = JSON.parse(txt); } catch {}
    if (resp.ok && js?.ok) ok++; else fail++;
  }

  saveBtn.disabled = false;
  bulkTagModal?.hide();
  showToast(`Etiket atama • Tamam: ${ok} • Hata: ${fail}`, fail ? 'warning' : 'success');
});


  // İlk yük
  etiketBulutu();
  listeYukle(true);

  // Basit sonsuz kaydırma (performans maddesine zemin)
  window.addEventListener('scroll', () => {
    if ((window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 400)) {
      if (!isLoading) { sayfa++; listeYukle(false); }
    }
  });

  // Mod: En az biri / Tümü (any/all)
  modeBtn?.addEventListener('click', () => {
    mode = (mode === 'all') ? 'any' : 'all';
    listeYukle(true);
    etiketBulutu();
  });

  // Filtre temizle
  clearBtn?.addEventListener('click', () => {
    tags.clear();
    listeYukle(true);
    etiketBulutu();
  });

  // Görsele tıklayınca önizleme modalı ya da yeni sekme
  grid.addEventListener('click', (ev) => {
    const a = ev.target.closest('.js-preview');
    if (!a) return;
    const id  = a.dataset.id;
    const src = a.dataset.src || a.href;
  });

})();


// === ÇÖP İŞLEMLERİ: Geri Al / Kalıcı Sil (tekil + toplu) ===
(function () {
  // Tek merkezden CSRF al: form gizli input > global yardımcı > meta
  function getCSRF() {
    // global yardımcı zaten dosyanın başında tanımlı (getCsrfToken)
    return (typeof getCsrfToken === 'function' ? getCsrfToken(document) : '') ||
           document.getElementById('csrf')?.value ||
           document.querySelector('meta[name="csrf-token"]')?.content ||
           document.querySelector('meta[name="csrf"]')?.content || '';
  }

  function toastOrAlert(msg) {
    try {
      if (window.showToast) return window.showToast(msg);
      if (window.toast)     return window.toast(msg);
    } catch(_) {}
    alert(msg);
  }

  // Bootstrap varsa modal ile, yoksa window.confirm ile onay
  function openConfirm(message) {
    const hasBS = !!(window.bootstrap && typeof bootstrap.Modal === 'function');
    if (!hasBS) return Promise.resolve(window.confirm(message));
    return new Promise((resolve) => {
      let modalEl = document.getElementById('confirmModal');
      if (!modalEl) {
        modalEl = document.createElement('div');
        modalEl.id = 'confirmModal';
        modalEl.className = 'modal';
        modalEl.innerHTML = `
          <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Onay</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body"><p class="m-0"></p></div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
              <button type="button" class="btn btn-primary" id="confirmOk">Tamam</button>
            </div>
          </div></div>`;
        document.body.appendChild(modalEl);
      }
      modalEl.querySelector('.modal-body p').textContent = message;
      const okBtn = modalEl.querySelector('#confirmOk');
      const bs    = bootstrap.Modal.getOrCreateInstance(modalEl);
      const onOk  = () => { okBtn.removeEventListener('click', onOk); resolve(true);  bs.hide(); };
      const onHd  = () => { okBtn.removeEventListener('click', onOk); resolve(false); };
      okBtn.addEventListener('click', onOk, { once: true });
      modalEl.addEventListener('hidden.bs.modal', onHd, { once: true });
      bs.show();
    });
  }

  async function postForm(url, dataObj) {
    const token = getCSRF();
    const body  = new URLSearchParams();
    for (const [k,v] of Object.entries(dataObj||{})) {
      if (Array.isArray(v)) v.forEach(x => body.append(k, x));
      else body.append(k, v);
    }
    if (token && !body.has('csrf')) body.append('csrf', token);

    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'fetch',
        'X-CSRF-Token': token, // header + body birlikte
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      credentials: 'same-origin',
      body
    });

    let json = null;
    try { json = await res.json(); } catch {}
    return { ok: res.ok, json };
  }

  function removeRowsByIds(ids) {
    ids.forEach(id => {
      const tr = document.querySelector(`tr[data-id="${id}"]`);
      if (tr) tr.remove();
    });
  }

  // TOPLU işlemler: .js-trash
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.js-trash');
    if (!btn) return;
    e.preventDefault();

    const url = btn.getAttribute('data-url');
    if (!url) { toastOrAlert('İşlem URL’i bulunamadı.'); return; }

    const ids = Array.from(document.querySelectorAll('.sec-kayit:checked, input[name="ids[]"]:checked'))
      .map(i => parseInt(i.value, 10)).filter(Boolean);

    if (ids.length === 0) { toastOrAlert('Seçim yok.'); return; }

    const isPurge = /kalici|yok[-_ ]?et/i.test(url);
    const ok = await openConfirm(isPurge
      ? 'Seçilenler KALICI olarak silinecek. Onaylıyor musunuz?'
      : 'Seçilenler geri yüklenecek. Onaylıyor musunuz?');
    if (!ok) return;

    const { ok: okResp, json } = await postForm(url, { 'ids[]': ids });
    if (okResp && (!json || json.ok !== false)) {
      removeRowsByIds(ids);
      if (!document.querySelector('tbody tr')) location.reload();
    } else {
      toastOrAlert((json && (json.mesaj || json.hata)) || 'İşlem başarısız.');
    }
  });

  // TEKİL işlemler: .js-trash-tekli (data-id, data-url)
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.js-trash-tekli');
    if (!btn) return;
    e.preventDefault();

    const url = btn.getAttribute('data-url');
    const id  = parseInt(btn.getAttribute('data-id'), 10);
    if (!url || !id) { toastOrAlert('İşlem bilgisi eksik.'); return; }

    const isPurge = /kalici|yok[-_ ]?et/i.test(url);
    const ok = await openConfirm(isPurge
      ? 'Kayıt KALICI olarak silinecek. Onaylıyor musunuz?'
      : 'Kayıt geri yüklenecek. Onaylıyor musunuz?');
    if (!ok) return;

    const { ok: okResp, json } = await postForm(url, { id });
    if (okResp && (!json || json.ok !== false)) {
      removeRowsByIds([id]);
      if (!document.querySelector('tbody tr')) location.reload();
    } else {
      toastOrAlert((json && (json.mesaj || json.hata)) || 'İşlem başarısız.');
    }
  });

  // “Tümünü seç” (#secTum)
  document.addEventListener('change', (e) => {
    const all = e.target.closest('#secTum');
    if (!all) return;
    const val = !!all.checked;
    document.querySelectorAll('.sec-kayit, input[name="ids[]"]').forEach(ch => ch.checked = val);
  });
})();
