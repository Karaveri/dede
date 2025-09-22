/* public/js/sayfalar.js */
/*! sayfalar.js — liste/çöp + form tek dosya
   Gereksinimler:
   - <meta name="base" content="<?= BASE_URL ?>">
   - <meta name="csrf"|name="csrf-token"> (veya formda hidden csrf)
   - TinyMCE (form sayfasında) */

(function () {
  "use strict";

  // ====== küçük yardımcılar ======
  const BASE =
  document.querySelector('meta[name="base"]')?.content
  || (typeof window.BASE_URL === 'string' ? window.BASE_URL : '')
  || (typeof window.__guessBase === 'function' ? window.__guessBase() : '');
  function getCsrf() {
    return (document.querySelector('meta[name="csrf-token"]')?.content
      || document.querySelector('meta[name="csrf"]')?.content
      || document.getElementById('csrf')?.value
      || document.querySelector('input[name="csrf"]')?.value
      || document.querySelector('input[name="csrf_token"]')?.value
      || document.querySelector('input[name="_token"]')?.value
      || '').trim();
  }
  const CSRF = getCsrf();

  function toast(msg) {
    try { if (window.showToast) return window.showToast(msg); } catch {}
    alert(msg);
  }

  // Bootstrap onay modali: #confirmModal (fallback: window.confirm)
  function askConfirm(message) {
    return new Promise((resolve) => {
      const modalEl = document.getElementById('confirmModal');
      const msgEl   = document.getElementById('confirmModalMsg');
      const okBtn   = document.getElementById('confirmModalOk');

      // Modal yoksa klasik confirm ile devam
      if (!modalEl || !okBtn || !msgEl || !window.bootstrap) {
        resolve(window.confirm(message));
        return;
      }

      msgEl.textContent = message;
      const bs = window.bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: false });

      const cleanup = () => {
        okBtn.removeEventListener('click', onOk);
        modalEl.removeEventListener('hidden.bs.modal', onCancel);
      };
      const onOk = () => { cleanup(); bs.hide(); resolve(true); };
      const onCancel = () => { cleanup(); resolve(false); };

      okBtn.addEventListener('click', onOk, { once: true });
      modalEl.addEventListener('hidden.bs.modal', onCancel, { once: true });
      bs.show();
    });
  }

  // fetch -> JSON (boş gövdeyi de başarı say)
  async function postJSON(url, data = {}, asForm = false) {
    const headers = { 'Accept': 'application/json', 'X-Requested-With': 'fetch' };
    let body;

    if (asForm) {
      const fd = new FormData();
      Object.entries(data || {}).forEach(([k, v]) => {
        if (Array.isArray(v)) v.forEach(x => fd.append(k, x)); else fd.append(k, v);
      });
      if (CSRF && !fd.has('csrf')) fd.append('csrf', CSRF);
      body = fd; // FormData — Content-Type otomatik
      if (CSRF) headers['X-CSRF-Token'] = CSRF;
    } else {
      headers['Content-Type'] = 'application/json';
      if (CSRF) headers['X-CSRF-TOKEN'] = CSRF;
      body = JSON.stringify(data || {});
    }

    const res = await fetch(url, { method: 'POST', credentials: 'same-origin', headers, body });

    // JSON olmayabilir (inline betikteki gibi tolere et)
    let json = null;
    try { json = await res.json(); } catch {}

    if (!res.ok || (json && json.ok === false)) {
      const msg = (json && (json.mesaj || json.hata)) || `HTTP ${res.status}`;
      throw new Error(msg);
    }
    // JSON yoksa da başarı kabul et
    return json || { ok: true };
  }

  // Basit TR-uyumlu slug
  function slugifyTR(s) {
    if (!s) return '';
    const map = {'ç':'c','ğ':'g','ı':'i','ö':'o','ş':'s','ü':'u','Ç':'c','Ğ':'g','İ':'i','Ö':'o','Ş':'s','Ü':'u'};
    s = String(s).replace(/[ÇĞİÖŞÜçğıöşü]/g, m => map[m] || m);
    s = s.normalize('NFKD').replace(/[\u0300-\u036f]/g,'');
    s = s.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').replace(/-{2,}/g,'-');
    return s.substring(0, 220);
  }

  // Sayfa içinde selector’lara göre şartlı bağlan
  document.addEventListener('DOMContentLoaded', () => {
    bindListTrashModule();   // liste & çöp
    bindFormModule();        // oluştur/düzenle formu
  });

  // ====== LİSTE / ÇÖP ======
  function bindListTrashModule() {

    // =============== Seç-Üstündeki Hepsini Seç ===============
    document.addEventListener('change', (e) => {
      const master = e.target.closest('#secTum');
      if (!master) return;
      const v = !!master.checked;
      document.querySelectorAll('.sec-kayit, .secKutusu, input[name="ids[]"]').forEach(ch => ch.checked = v);
    });

    // =============== Tekil Durum (Liste modunda) ===============
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.js-durum-btn');
      if (!btn) return;
      e.preventDefault();

      const id  = parseInt(btn.dataset.id, 10) || parseInt(btn.closest('tr[data-id]')?.dataset.id || '0', 10);
      const url = btn.dataset.url || (BASE + '/admin/sayfalar/durum');
      if (!id || !url) return;

      try {
        btn.disabled = true;
        const j = await postJSON(url, { id }, true);
        const aktif = (String(j?.durum) === '1') || /^(aktif|yayinda)/i.test(String(j?.durum_str||''));
        btn.textContent = aktif ? 'Aktif' : 'Taslak';
        btn.classList.toggle('btn-success', aktif);
        btn.classList.toggle('btn-secondary', !aktif);
        btn.classList.toggle('bg-success', aktif);
        btn.classList.toggle('bg-secondary', !aktif);
      } catch (err) {
        toast(err.message || 'İşlem başarısız.');
      } finally {
        btn.disabled = false;
      }
    });

    // =============== Normal listede "Sil" butonu (çöpe gönder) ===============
    document.addEventListener('click', async (e) => {
      const b = e.target.closest('.js-confirm-submit');
      if (!b) return;
      e.preventDefault();
      const msg = b.getAttribute('data-msg') || 'Onaylıyor musunuz?';
      if (!(await askConfirm(msg))) return;
      const f = b.closest('form');
      if (f) f.submit();
    });

    // =============== ÇÖP ekranı — TEKİL: Geri Al / Kalıcı Sil ===============
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.js-trash-tekli, .js-restore');
      if (!btn) return;
      e.preventDefault();

      const url = btn.dataset.url || '';
      let id = parseInt(btn.dataset.id || '', 10);
      if (!id) {
        const tr = btn.closest('tr[data-id]');
        if (tr) id = parseInt(tr.getAttribute('data-id') || '0', 10);
      }

      if (!url || !id) return toast('Eksik veri.');

      const isPurge = /\/yok-et(\b|$)/.test(url);
      const msg = isPurge
        ? 'Kayıt KALICI olarak silinecek, onaylıyor musunuz?'
        : 'Kayıt geri alınacak, onaylıyor musunuz?';
      if (!(await askConfirm(msg))) return;

      try {
        btn.disabled = true;
        await postJSON(url, { id }, true);               // tekil id
        // Satırı kaldır
        const tr = btn.closest('tr[data-id]');
        if (tr) tr.remove();

        // Tabloda satır kalmadıysa ya da görünüm çöp ise güvenli yenile
        if (!document.querySelector('tbody tr') || /\/sayfalar\/cop(\b|$)/.test(location.pathname)) {
          setTimeout(() => location.reload(), 50);
          return;
        }

        toast(isPurge ? 'Kalıcı silindi.' : 'Geri alındı.');
      } catch (err) {
        toast(err.message || 'İşlem başarısız.');
      } finally {
        btn.disabled = false;
      }
    });

    // =============== ÇÖP ekranı — TOPLU: Seçilenleri Geri Al / Kalıcı Sil ===============
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.js-trash, .js-cop, [data-bulk="geri-al"], [data-bulk="yok-et"]');
      if (!btn) return;
      e.preventDefault();

      // URL: data-url varsa onu kullan; yoksa data-bulk'a göre kur.
      let url = btn.dataset.url || '';
      if (!url) {
        const bulk = btn.dataset.bulk;
        if (bulk === 'geri-al') url = BASE + '/admin/sayfalar/geri-al';
        else if (bulk === 'yok-et') url = BASE + '/admin/sayfalar/yok-et';
      }
      if (!url) return toast('İşlem URL’i bulunamadı.');

      const ids = Array.from(document.querySelectorAll('.sec-kayit:checked, .secKutusu:checked, input[name="ids[]"]:checked'))
                        .map(i => parseInt(i.value, 10)).filter(Boolean);
      if (!ids.length) return toast('Seçim yok.');

      const isPurge = /\/yok-et(\b|$)/.test(url);
      const msg = isPurge
        ? 'Seçilenler KALICI olarak silinecek. Onaylıyor musunuz?'
        : 'Seçilenler geri alınacak. Onaylıyor musunuz?';
      if (!(await askConfirm(msg))) return;

      try {
        btn.disabled = true;
        await postJSON(url, { 'ids[]': ids }, true);     // backend ids[] bekliyor
        // Seçilen satırları kaldır
        ids.forEach(id => document.querySelector(`tr[data-id="${id}"]`)?.remove());

        // Tabloda satır kalmadıysa ya da görünüm çöp ise güvenli yenile
        if (!document.querySelector('tbody tr') || /\/sayfalar\/cop(\b|$)/.test(location.pathname)) {
          setTimeout(() => location.reload(), 50);
          return;
        }

        toast(isPurge ? 'Kalıcı silindi.' : 'Geri alındı.');
      } catch (err) {
        toast(err.message || 'İşlem başarısız.');
      } finally {
        btn.disabled = false;
      }
    });
  }

  // ====== FORM ======
  function bindFormModule() {
    const form = document.querySelector('form[action*="/admin/sayfalar/kaydet"], form[action*="/admin/sayfalar/guncelle"]');
    if (!form) return;

    // --- Slug alanı: başlıktan otomatik doldur + benzersizlik (çeviri API’si)
    const baslik = form.querySelector('input[name="baslik"]');
    const slug   = form.querySelector('input[name="slug"]');
    const sayfaId = parseInt(form.querySelector('input[name="id"]')?.value || '0', 10) || 0;
    const slugHelp = form.querySelector('#slugHelp, [data-role="slugHelp"]');
    const SLUG_API = BASE + '/admin/sayfalar/slug-kontrol';

    let slugDirty = !!(slug && slug.value.trim());
    if (slug) slug.addEventListener('input', () => { slugDirty = slug.value.trim() !== ''; });

    if (baslik && slug) {
      baslik.addEventListener('input', debounce(async () => {
        if (slugDirty) return;
        const val = slugifyTR(baslik.value);
        await setUniqueSlug(val);
      }, 250));
    }

    async function setUniqueSlug(val, lang /*ops*/) {
      if (!slug) return;
      if (!val) { slug.value = ''; markInvalid(slug, true, 'Boş bırakılamaz.'); return; }
      // Controller’daki slugKontrol dil parametresi bekliyor; TR varsayılanını kullanıyoruz.
      try {
        const j = await postJSON(SLUG_API, { dil_kod: (lang||'tr'), slug: val, id: sayfaId }, true);
        const s = j?.veri?.slug || val;
        slug.value = s;
        markInvalid(slug, false);
      } catch {
        slug.value = val;          // en azından normalize edilmiş hali
        markInvalid(slug, false);
      }
    }

    // Yardımcı: invalid vurgusu
    function markInvalid(el, invalid, msg) {
      const fb = el.parentElement.querySelector('.invalid-feedback');
      el.classList.toggle('is-invalid', !!invalid);
      if (fb && msg) fb.textContent = msg;
      const submit = form.querySelector('button[type="submit"],input[type="submit"]');
      if (submit) submit.disabled = !!invalid;
    }

    // --- TinyMCE (sekme bazlı lazy-init; controller’daki çalışan sürümle uyumlu) ---
    if (window.tinymce) {
      // Önceden kurulmuş editörleri temizle (back/forward çakışması)
      try { (tinymce.EditorManager?.editors || []).forEach(ed => ed.remove()); } catch {}

      function makeTinyConfig(target) {
        const UPLOAD_URL = BASE + '/admin/medya/yukle';
        return {
          target,
          height: 520,
          menubar: 'file edit view insert format tools table help',
          plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table wordcount autosave',
          toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline | alignleft aligncenter alignright | bullist numlist | removeformat | preview code fullscreen',
          branding: false,
          relative_urls: false, remove_script_host: false, convert_urls: true,
          automatic_uploads: true, images_upload_credentials: true, file_picker_types: 'image',
          images_upload_handler: (blobInfo, progress) => new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', UPLOAD_URL, true);
            if (CSRF) xhr.setRequestHeader('X-CSRF-TOKEN', CSRF);
            xhr.upload.onprogress = (e) => { if (e.lengthComputable) progress(Math.round(e.loaded / e.total * 100)); };
            xhr.onload = function () {
              if (xhr.status < 200 || xhr.status >= 300) return reject('HTTP ' + xhr.status);
              let json; try { json = JSON.parse(xhr.responseText); } catch { return reject('Invalid JSON'); }
              // { location: 'https://.../public/uploads/editor/...' } bekleniyor
              const loc = json && (json.location || json.url);
              if (!loc || typeof loc !== 'string') return reject('Invalid response');
              resolve(loc);
            };
            xhr.onerror = () => reject('Upload failed');
            const fd = new FormData();
            fd.append('file', blobInfo.blob(), blobInfo.filename());
            if (CSRF) fd.append('csrf', CSRF);
            xhr.send(fd);
          }),
        };
      }

      // 1) İlk görünen .tinymce
      let started = false;
      document.querySelectorAll('.tinymce').forEach(t => {
        if (started) return;
        if (t.offsetParent !== null) { tinymce.init(makeTinyConfig(t)); started = true; }
      });
      // 2) Sekme değişince lazy-init
      document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', (ev) => {
          const paneSel = ev.target.getAttribute('data-bs-target'); // #pane-tr
          document.querySelectorAll(`${paneSel} .tinymce`).forEach(t => {
            if (t.closest('.tox-tinymce')) return;
            tinymce.init(makeTinyConfig(t));
          });
        });
      });
    }

    // --- Meta açıklama yan panel sayacı (opsiyonel) ---
    (function metaCounter() {
      const cnt = document.getElementById('metaSideCount');
      const sideMA = document.getElementById('metaAciklamaTR_side');
      function paint(n){
        if (!cnt) return;
        cnt.textContent = String(n);
        const v = Number(n);
        cnt.style.color = (v>=80 && v<=160) ? 'var(--bs-success)' : 'var(--bs-secondary)';
      }
      if (sideMA) {
        paint(sideMA.value.length);
        sideMA.addEventListener('input', () => paint(sideMA.value.length));
      }
    })();
  }

  // ====== küçük yardımcılar ======
  function debounce(fn, t=250) { let id; return (...a)=>{clearTimeout(id); id=setTimeout(()=>fn.apply(null,a),t);} }

})();
