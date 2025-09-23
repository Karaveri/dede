/* public/js/medya.js — temiz, tek otorite sürüm
   - adminCore (core.js) yüklü olsun ya da olmasın çalışır
   - BASE/APP/CSRF sağlam tespit (layout’a bağımlı değil)
   - Bootstrap yoksa modal çağrıları guard’lanır (crash etmez)
   - Etiket Bulutu (sayfa üstü) yalnız burada yönetilir
   - Toplu etiket verme + silme onayı burada ele alınır
*/

(function medyaModule () {
  // -------------------------------------------------------------------
  // 0) Tek sefer kilidi
  // -------------------------------------------------------------------
  if (window.__MEDYA_ONCE) return;
  window.__MEDYA_ONCE = true;

  // -------------------------------------------------------------------
  // 1) Çekirdek / yardımcılar (shim)
  // -------------------------------------------------------------------
  const C = window.adminCore || {};

  // idempotent "once" shim (core.js yoksa)
  if (!C.once) {
    const __onceMap = {};
    C.once = (key) => {
      if (__onceMap[key]) return false;
      __onceMap[key] = true;
      return true;
    };
  }
  if (!C.once('medya')) return; // ikinci kez bağlanma

  // fetchJSON shim (core yoksa)
  C.fetchJSON = C.fetchJSON || (async (url, opt) => {
    const resp = await fetch(url, opt || {});
    let json = null; try { json = await resp.json(); } catch {}
    return { ok: resp.ok, status: resp.status, json };
  });

  // toast shim (dinamik): her çağrıda adminCore/showToast var mı bak
  const toast = (msg, variant) => {
    const ac = window.adminCore;
    const fn = ac?.toastOrAlert || ac?.toast;
    if (typeof fn === 'function') return fn(msg, variant);
    if (typeof window.showToast === 'function') return window.showToast(msg, variant);
    return alert(msg);
  };

  // slugify shim
  C.slugifyTR = C.slugifyTR || ((s) => {
    if (!s) return '';
    const map = {'ş':'s','Ş':'s','ı':'i','İ':'i','ç':'c','Ç':'c','ğ':'g','Ğ':'g','ü':'u','Ü':'u','ö':'o','Ö':'o'};
    return String(s).replace(/[şŞıİçÇğĞüÜöÖ]/g, ch => map[ch] || ch)
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
      .toLowerCase().replace(/[^a-z0-9]+/g,'-')
      .replace(/^-+|-+$/g,'');
  });

  // -------------------------------------------------------------------
  // 2) BASE / APP / CSRF tespiti (layout’suz da çalışır)
  // -------------------------------------------------------------------
  const ctx  = window.PAGE_MEDYA || {};
  const BASE =
      ctx.BASE
   || window.BASE_URL
   || (C.getBase ? C.getBase() : null)
   || document.querySelector('meta[name="base"]')?.content
   || location.origin;

  // Uygulama kökü (http://host/app). /admin öncesi path; /public varsa at.
  const prefixFromPath = location.pathname.includes('/admin/')
    ? location.pathname.split('/admin/')[0]
    : location.pathname.replace(/\/public\/?$/,'').replace(/\/+$/,'');
  let APP =
      (typeof window.APP_URL === 'string' && window.APP_URL)   ? window.APP_URL
    : (location.origin + prefixFromPath).replace(/\/+$/,'');

  if (/\/public\/?$/.test(APP)) APP = APP.replace(/\/public\/?$/,''); // güvence

  const csrf =
      ctx.CSRF
   || document.querySelector('meta[name="csrf"]')?.content
   || document.querySelector('meta[name="csrf-token"]')?.content
   || document.getElementById('csrf')?.value
   || document.querySelector('input[name="csrf"]')?.value
   || '';

  // Küçük yardımcılar
  const qs = (sel, root = document) => root.querySelector(sel);
  const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const hasBootstrap = !!window.bootstrap;

  // Sayfa gerçekten medya sayfası mı?
  const isMedyaPage = !!qs('#medya-grid, #medyaForm, #etiket-filter');
  if (!isMedyaPage) return;

  // -------------------------------------------------------------------
  // 3) Güvenli fetch yardımcıları
  // -------------------------------------------------------------------
  async function postJSON(url, payload) {
    const headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' };
    if (csrf) { headers['X-CSRF-Token'] = csrf; headers['X-CSRF'] = csrf; }
    return C.fetchJSON(url, { method: 'POST', headers, body: JSON.stringify(payload || {}) });
  }
  async function getJSON(url) {
    return C.fetchJSON(url, { headers: { 'X-Requested-With': 'fetch' } });
  }

  // -------------------------------------------------------------------
  // 4) Toplu Etiket Modalı (opsiyonel; bootstrap yoksa uyarı verir)
  // -------------------------------------------------------------------
  (function bulkTags(){
    const btn = qs('#bulkTagBtn');
    const modalEl = qs('#bulkTagModal');
    const modal = (hasBootstrap && modalEl) ? new window.bootstrap.Modal(modalEl) : null;
    const input = qs('#bulkTagsInput');
    const form  = qs('#bulkTagForm');

    if (btn) {
      btn.addEventListener('click', () => {
        const ids = qsa('.chk:checked').map(ch => +ch.value).filter(Boolean);
        if (!ids.length) { toast('Seçili görsel yok.', 'warning'); return; }
        if (input) input.value = '';
        if (modal) { modal.show(); setTimeout(() => input?.focus(), 150); }
        else toast('Etiketleme için Bootstrap modal bulunamadı.', 'info');
      });
    }

    if (form) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const ids = qsa('.chk:checked').map(ch => +ch.value).filter(Boolean);
        const tags = (input?.value || '').trim();
        if (!ids.length) return toast('Seçili görsel yok.', 'warning');

        let ok = 0, fail = 0;
        for (const id of ids) {
          const r = await postJSON(`${BASE}/admin/api/medya/etiketle`, {
            medya_id: id, etiketler: tags, mod: 'append'
          });
          (r.ok && r.json?.ok) ? ok++ : fail++;
        }
        modal?.hide();
        toast(`Etiket atama • Tamam: ${ok} • Hata: ${fail}`, fail ? 'warning' : 'success');
      });
    }
  })();

  // -------------------------------------------------------------------
  // 5) Silme Onayı (tekil + toplu) — form action’ı APP ile kur
  // -------------------------------------------------------------------
  (function deletion(){
    const medyaForm      = qs('#medyaForm');
    const hiddenSingleId = qs('#hiddenSingleId');
    const confirmModalEl = qs('#confirmDeleteModal');
    const confirmText    = qs('#confirmText');
    const confirmForm    = qs('#confirmForm');
    const confirmBtn     = qs('#confirmBtn');
    const confirmModal   = (hasBootstrap && confirmModalEl) ? new window.bootstrap.Modal(confirmModalEl) : null;

    if (!medyaForm || !confirmForm) return;

    let deleteMode = null;

    // Tekil sil (delegasyon değil; sayfadaki hazır butonları bağlarız)
    qsa('.btn-sil-tek').forEach((btn) => {
      btn.addEventListener('click', () => {
        deleteMode = 'single';
        const id   = btn.dataset.id;
        const name = btn.dataset.name || ('#' + id);
        if (hiddenSingleId) hiddenSingleId.value = id;
        if (confirmText) confirmText.textContent = `"${name}" adlı görsel silinecek. Bu işlem geri alınamaz.`;
        if (confirmBtn) confirmBtn.disabled = false;
        confirmModal?.show();
      });
    });

    // Toplu sil
    qs('#bulkDeleteBtn')?.addEventListener('click', () => {
      const checked = qsa('.chk').filter(ch => ch.checked);
      if (!checked.length) {
        deleteMode = null;
        if (confirmText) confirmText.textContent = 'Seçili görsel bulunmuyor.';
        if (confirmBtn) confirmBtn.disabled = true;
      } else {
        deleteMode = 'bulk';
        if (confirmText) confirmText.textContent = `${checked.length} adet görsel silinecek. Bu işlem geri alınamaz.`;
        if (confirmBtn) confirmBtn.disabled = false;
      }
      confirmModal?.show();
    });

    // Onay formu
    confirmForm.addEventListener('submit', (e) => {
      e.preventDefault();
      if (!deleteMode) return confirmModal?.hide();
      medyaForm.action = (deleteMode === 'single')
        ? `${BASE}/admin/medya/sil`
        : `${BASE}/admin/medya/toplu-sil`;
      confirmModal?.hide();
      medyaForm.submit();
    });
  })();

  // -------------------------------------------------------------------
  // 6) Etiket Bulutu & Filtre (sayfa üstü)
  //    - /admin/api/medya/etiketler → { etiketler: [{slug, ad, adet?}, ...] }
  //    - URL param: tags=slug1,slug2  mode=any|all  q=...
  // -------------------------------------------------------------------
  (function tagFilter(){
    const wrap     = qs('#etiket-filter');
    const modeBtn  = qs('#etiket-mode');
    const clearBtn = qs('#etiket-clear');
    const searchEl = qs('#medya-ara');
    const sonucEl  = qs('#sonuc-bilgi');

    if (!wrap) return;
    if (wrap.dataset.init === '1') return;
    wrap.dataset.init = '1';

    const params   = new URLSearchParams(location.search);
    let selected   = (params.get('tags') || '').split(',').filter(Boolean);
    let mode       = (params.get('mode') || 'any'); // any | all

    function applyModeBtn(){
      if (!modeBtn) return;
      modeBtn.classList.remove('btn-danger','btn-outline-secondary','btn-warning');
      const isAny = (mode !== 'all');
      modeBtn.classList.add(isAny ? 'btn-danger' : 'btn-outline-secondary');
      modeBtn.textContent = isAny ? 'Mod: En az biri' : 'Mod: Hepsi';
    }
    applyModeBtn();

    async function loadFilterTags(){
      try {
        const r  = await getJSON(`${BASE}/admin/api/medya/etiketler`);
        const js = r?.json || {};
        wrap.innerHTML = '';

        // Esnek şema desteği: etiketler birden fazla anahtarda gelebilir
        const list =
          (Array.isArray(js.etiketler)         && js.etiketler) ||
          (Array.isArray(js.data?.etiketler)   && js.data.etiketler) ||
          (Array.isArray(js.data)              && js.data) ||
          (Array.isArray(js.tags)              && js.tags) ||
          [];

        if (!list.length) {
          console.warn('Etiket listesi boş veya şema dışı:', { status: r?.status, json: js });
          return;
        }

        draw(list);
      } catch (e) {
        wrap.innerHTML = '';
        console.warn('Etiket bulutu yüklenemedi:', e);
      }
    }

    function draw(list){
      // "Tümü" kısayolu
      const bAll = document.createElement('button');
      bAll.type = 'button';
      bAll.className = 'btn btn-sm me-2 mb-2 ' + (selected.length ? 'btn-outline-secondary' : 'btn-secondary');
      bAll.textContent = 'Tümü';
      bAll.addEventListener('click', () => {
        selected = [];
        params.delete('tags');
        // sayfa/sayfa numarası sıfırlansın
        params.delete('s'); params.delete('sayfa');
        location.search = '?' + params.toString();
      });
      wrap.appendChild(bAll);

      // Etiket butonları
      list.forEach(t => {
        const slug = t.slug;
        const ad   = t.ad || slug;
        const adet = (typeof t.adet === 'number') ? ` (${t.adet})` : '';
        const b = document.createElement('button');
        b.type = 'button';
        const isSel = selected.includes(slug);
        b.className = 'btn btn-sm me-2 mb-2 ' + (isSel ? 'btn-primary' : 'btn-outline-secondary');
        b.dataset.slug = slug;
        b.textContent = ad + adet;
        b.addEventListener('click', () => {
          if (selected.includes(slug)) selected = selected.filter(s => s !== slug);
          else selected.push(slug);
          if (selected.length) params.set('tags', selected.join(',')); else params.delete('tags');
          params.set('mode', mode);
          params.delete('s'); params.delete('sayfa');
          location.search = '?' + params.toString();
        });
        wrap.appendChild(b);
      });

      if (sonucEl) sonucEl.textContent = ''; // istersen burada toplam/aktif etiket sayısı yaz
    }

    // Mod düğmesi
    modeBtn?.addEventListener('click', () => {
      mode = (mode === 'all') ? 'any' : 'all';
      params.set('mode', mode);
      applyModeBtn();
      params.delete('s'); params.delete('sayfa');
      location.search = '?' + params.toString();
    });

    // Arama input’u (varsa) — param güncelle
    let tmr;
    searchEl?.addEventListener('input', () => {
      clearTimeout(tmr);
      tmr = setTimeout(() => {
        const q = (searchEl.value || '').trim();
        if (q) params.set('q', q); else params.delete('q');
        params.delete('s'); params.delete('sayfa');
        location.search = '?' + params.toString();
      }, 300);
    });

    // İlk yük
    loadFilterTags();
  })();

  // -------------------------------------------------------------------
  // 7) Toplu işlem (aktif/pasif/sil/geri-al/yok-et) — genel amaçlı
  //    Not: sunucuda x-www-form-urlencoded ve boş-gövde başarı toleransı var.
  // -------------------------------------------------------------------
  (function bulkActions(){
    document.addEventListener('click', (ev) => {
      const btn = ev.target.closest('.js-bulk');
      if (!btn) return;

      ev.preventDefault();
      if (btn.dataset.busy === '1') return;
      btn.dataset.busy = '1';

      const aksiyon = btn.dataset.aksiyon || btn.dataset.bulk || '';
      const url = btn.dataset.url || `${BASE}/admin/medya/${aksiyon}`;
      const secimler = qsa('input[name="ids[]"]:checked,.sec-kayit:checked,.secKutusu:checked')
                        .map(el => el.value).filter(Boolean);

      if (!secimler.length) {
        btn.dataset.busy = '';
        return toast('Seçim yapılmadı.');
      }

      const go = (C.askConfirm)
        ? C.askConfirm(`${secimler.length} öğe için "${aksiyon}" uygulanacak. Onaylıyor musun?`)
        : Promise.resolve(confirm(`${secimler.length} öğe için "${aksiyon}" uygulanacak. Onaylıyor musun?`));

      go.then(async (ok) => {
        if (!ok) { btn.dataset.busy = ''; return; }

        const body = new URLSearchParams();
        if (csrf) body.set('csrf', csrf);
        body.set('aksiyon', aksiyon);
        secimler.forEach(id => body.append('ids[]', id));

        let resp;
        try {
          resp = await fetch(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
              'X-Requested-With': 'fetch'
            },
            body
          });
        } catch (_) {
          btn.dataset.busy = '';
          return toast('İstek hatası oluştu.');
        }

        btn.dataset.busy = '';
        if (resp && resp.ok) {
          // Optimistic UI: seçilen satırları DOM’dan kaldır
          secimler.forEach(id => { qsa(`[data-id="${id}"]`).forEach(row => row.remove()); });
          const kalan = qs('#medya-grid [data-id]');
          if (!kalan) setTimeout(() => location.reload(), 150);
          else toast('Toplu işlem uygulandı.');
        } else {
          toast('Sunucu yanıtı başarısız.');
        }
      });
    });
  })();

  // -------------------------------------------------------------------
  // 8) Yardımcı: “Tümünü seç” (sayfada varsa)
  // -------------------------------------------------------------------
  (function selectAll(){
    const secTum = qs('#secTum');
    if (!secTum) return;
    secTum.addEventListener('change', function(){
      qsa('.chk').forEach(ch => ch.checked = !!this.checked);
    });
  })();

  // -------------------------------------------------------------------
  // 9) Sürükle-bırak & tıklayıp seç: güvenli bağlayıcı
  // -------------------------------------------------------------------
  if (typeof window.__bindMedyaUpload !== 'function') {
    window.__bindMedyaUpload = function __bindMedyaUpload() {
      if (window.__UPLOAD_INITTED) return; // <<< ikinci kez kurma
      window.__UPLOAD_INITTED = true;
      // Toleranslı hedefler
      const form = document.getElementById('medyaForm') || document.querySelector('form#medya-form, form[action*="/medya"]');
      const dz   = document.querySelector('[data-dropzone], #uploadDrop, #dropZone, .upload-drop, .medya-dropzone');

      if (dz && dz.dataset.bound === '1') return;
      if (dz) dz.dataset.bound = '1';

      const inp  = (form && form.querySelector('input[type="file"]')) || document.querySelector('input[type="file"][name*="dosya"], input[type="file"][name*="gorsel"], input[type="file"]');

      if (!form || !inp) return; // dosya inputu yoksa sessiz çık

      // Tıklama → file input
      if (dz) {
        dz.addEventListener('click', () => inp.click());
        // Görsel geri bildirim
        ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('is-drag'); }));
        ;['dragleave','dragend','drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('is-drag'); }));
        // Drop → yükle
        dz.addEventListener('drop', e => {
          if (!e.dataTransfer?.files?.length) return;
          handleFiles(e.dataTransfer.files);
        });
      }

      // Seçim → yükle
      inp.addEventListener('change', () => handleFiles(inp.files));

      // Sunucu tek dosya bekliyor: her dosyayı ayrı POST et
      async function handleFiles(files) {
        if (!files || !files.length) return;

        const BASE = window.BASE_URL
                  || document.querySelector('meta[name="base"]')?.content
                  || location.origin;

        const csrf = document.querySelector('meta[name="csrf"]')?.content
                  || document.getElementById('csrf')?.value
                  || document.querySelector('input[name="csrf"]')?.value
                  || '';

        for (const f of Array.from(files)) {
          const fd = new FormData();
          fd.append('file', f);             // <<< ÖNEMLİ: 'file' anahtarı
          if (csrf) fd.append('csrf', csrf);

          const r = await fetch(`${BASE}/admin/medya/yukle`, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'fetch' }
          });

          // Sunucu bazen boş gövde döndürebiliyor; 2xx ise başarı say
          if (!r.ok) {
            let js = null;
            try { js = await r.json(); } catch {}
            const kod   = js?.kod || js?.code;
            const mesaj = js?.mesaj || js?.message;

            if (r.status === 409 || kod === 'ZATEN_VAR') {
              // Sunucu “zaten var” dedi — toast ile bildir
              toast(mesaj || 'Aynı dosya zaten kayıtlı.', 'warning');
            } else {
              toast('Yükleme başarısız: HTTP ' + r.status, 'danger');
            }
            return; // ilk hatada dur
          }
        }

        // Hepsi bitti → yenile
        location.reload();
      }
    };
  }

  // Sayfa yüklendiyse otomatik bağla (inline çağrı olmasa da)
  try { window.__bindMedyaUpload(); } catch {}

})(); // medyaModule sonu
