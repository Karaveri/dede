/*! kategoriler.js – kategoriler/liste + kategoriler/cop */
(() => {
  "use strict";

    // ===== TR slug =====
  function slugifyTR(s) {
    if (!s) return '';
    const map = {'ç':'c','ğ':'g','ı':'i','ö':'o','ş':'s','ü':'u','Ç':'c','Ğ':'g','İ':'i','Ö':'o','Ş':'s','Ü':'u'};
    s = String(s).replace(/[ÇĞİÖŞÜçğıöşü]/g, m => map[m] || m);
    s = s.normalize('NFKD').replace(/[\u0300-\u036f]/g,'');
    s = s.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').replace(/-{2,}/g,'-');
    return s.substring(0, 220);
  }

  // ===== Helpers =====
  const BASE =
    document.querySelector('meta[name="base"]')?.content ||
    (typeof window.BASE_URL === 'string' ? window.BASE_URL : '') ||
    '';

  function getCsrf() {
    return (document.querySelector('meta[name="csrf-token"]')?.content
      || document.querySelector('meta[name="csrf"]')?.content
      || document.getElementById('csrf')?.value
      || document.querySelector('input[name="csrf"]')?.value
      || document.querySelector('input[name="csrf_token"]')?.value
      || document.querySelector('input[name="_token"]')?.value
      || '').trim();
  }

  function toastOrAlert(msg) {
    try { if (window.showToast) return window.showToast(msg); } catch {}
    alert(msg);
  }

	// --- Durum butonu yardımcıları ---
	function readBtnState(btn) {
	  const d = btn.dataset || {};
	  const raw = d.durum ?? d.state ?? d.aktif ?? d.status ?? '';
	  if (raw !== '') {
	    if (String(raw).match(/^(1|true|aktif|yayinda|yayında)$/i)) return 1;
	    if (String(raw).match(/^(0|false|pasif|taslak)$/i)) return 0;
	  }
	  const txt = (btn.textContent || '').trim();
	  if (/aktif|yayinda|yayında/i.test(txt)) return 1;
	  if (/pasif|taslak/i.test(txt)) return 0;
	  if (btn.classList.contains('btn-success') || btn.classList.contains('bg-success')) return 1;
	  if (btn.classList.contains('btn-secondary') || btn.classList.contains('bg-secondary')) return 0;
	  return 0;
	}

	function applyBtnState(btn, aktif) {
	  const onTxt  = btn.dataset.textOn  || 'Aktif';
	  const offTxt = btn.dataset.textOff || 'Pasif';

	  btn.dataset.durum = aktif ? '1' : '0';
	  btn.dataset.aktif = aktif ? '1' : '0';
	  btn.setAttribute('data-state', aktif ? 'aktif' : 'pasif');

	  // ana buton/rozet
	  btn.classList.toggle('btn-success', !!aktif);
	  btn.classList.toggle('btn-secondary', !aktif);
	  btn.classList.toggle('bg-success', !!aktif);
	  btn.classList.toggle('bg-secondary', !aktif);

	  // iç label/badge/spans
	  const labelEl =
	      btn.querySelector('[data-role="durum-label"]')
	   || btn.querySelector('.durum-label')
	   || btn.querySelector('.state-text')
	   || btn.querySelector('.btn-label')
	   || btn.querySelector('.badge')
	   || btn.querySelector('span')
	   || btn.querySelector('strong');

	  if (labelEl) {
	    labelEl.textContent = aktif ? onTxt : offTxt;
	    labelEl.classList.toggle('text-bg-success', !!aktif);
	    labelEl.classList.toggle('text-bg-secondary', !aktif);
	    labelEl.classList.toggle('bg-success', !!aktif);
	    labelEl.classList.toggle('bg-secondary', !aktif);
	    return;
	  }

	  const tn = Array.from(btn.childNodes).reverse().find(n => n.nodeType === 3);
	  if (tn) { tn.nodeValue = ' ' + (aktif ? onTxt : offTxt); return; }

	  btn.textContent = aktif ? onTxt : offTxt;
	}

  // Bootstrap onay modalini kullan; yoksa confirm
  function askConfirm(message) {
    const el = document.getElementById('confirmModal');
    const ok = document.getElementById('confirmModalOk');
    const msg = document.getElementById('confirmModalMsg');
    if (!el || !ok || !msg || !window.bootstrap) {
      return Promise.resolve(window.confirm(message));
    }
    msg.textContent = message || 'Onaylıyor musunuz?';
    const bs = bootstrap.Modal.getOrCreateInstance(el, { backdrop: 'static', keyboard: false });
    return new Promise((resolve) => {
      const onOk = () => { cleanup(); bs.hide(); resolve(true); };
      const onHide = () => { cleanup(); resolve(false); };
      const cleanup = () => {
        ok.removeEventListener('click', onOk);
        el.removeEventListener('hidden.bs.modal', onHide);
      };
      ok.addEventListener('click', onOk, { once: true });
      el.addEventListener('hidden.bs.modal', onHide, { once: true });
      bs.show();
    });
  }

  // URL-encoded POST (+ CSRF hem header hem body)
  async function postForm(url, data) {
    const csrf = getCsrf();
    const body = new URLSearchParams();
    Object.entries(data || {}).forEach(([k, v]) => {
      if (Array.isArray(v)) v.forEach(x => body.append(k, x));
      else body.append(k, v);
    });
    if (csrf && !body.has('csrf')) body.append('csrf', csrf);

    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'fetch',
        ...(csrf ? { 'X-CSRF-Token': csrf } : {}),
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      credentials: 'same-origin',
      body
    });
    let json = null; try { json = await res.json(); } catch {}
    if (!res.ok || (json && json.ok === false)) {
      const msg = (json && (json.mesaj || json.hata)) || `HTTP ${res.status}`;
      throw new Error(msg);
    }
    return json || { ok: true };
  }

  // Satırları kaldır + gerekiyorsa yenile
  function removeRows(ids) {
    (ids || []).forEach(id => document.querySelector(`tr[data-id="${id}"]`)?.remove());
    if (!document.querySelector('tbody tr')) { location.reload(); return true; }
    return false;
  }

	// ===== Çöp sayısı rozetini güncelle (cop sayfasında DOM'dan say, diğerlerinde JSON varsa kullan) =====
	function countCopRows() {
	  // Öncelik: veri satırları data-id taşır
	  let n = document.querySelectorAll('tbody tr[data-id]').length;
	  if (n > 0) return n;

	  // Yedek: içinde seçim kutusu olan satırlar
	  n = document.querySelectorAll('tbody tr input[type="checkbox"].sec-kayit, tbody tr input[name="ids[]"]').length;
	  if (n > 0) return n;

	  // Son çare: "kayıt yok" satırını sayma → 0
	  return 0;
	}

	function findCopBadgeTargets() {
	  const seen = new Set();
	  const targets = [];

	  [document.getElementById('copBadge'), document.querySelector('[data-cop-badge]')].forEach(el => {
	    if (el && !seen.has(el)) { seen.add(el); targets.push(el); }
	  });

	  document.querySelectorAll('a[href*="/admin/kategoriler/cop"], button[data-url*="/admin/kategoriler/cop"]').forEach(el => {
	    const b = el.querySelector('.badge');
	    if (b && !seen.has(b)) { seen.add(b); targets.push(b); }
	  });

	  if (!targets.length) {
	    const link = document.querySelector('a[href*="/admin/kategoriler/cop"]');
	    if (link) {
	      const span = document.createElement('span');
	      span.className = 'badge bg-danger ms-1';
	      link.appendChild(span);
	      targets.push(span);
	    }
	  }
	  return targets;
	}

	// ===== Çöp sayısı rozetini güncelle =====
	async function refreshTrashBadge() {
	  let n = 0;

	  if (/\/kategoriler\/cop(\/|$)/.test(location.pathname)) {
	    // Çöp sayfasında: sayıyı yerel DOM'dan al
	    n = countCopRows();
	  } else {
	    // 1) Varsa JSON'dan dene
	    try {
	      const url = (BASE || '') + '/admin/kategoriler/cop/say-json';
	      const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
	      let j = null; try { j = await res.json(); } catch {}
	      n = parseInt(j?.n ?? '0', 10) || 0;
	    } catch {}
		// 2) JSON başarısız/yanlışsa HTML'i çekip GERÇEK veri satırlarını say
		if (!(n > 0)) {
		  try {
		    const htmlUrl = (BASE || '') + '/admin/kategoriler/cop';
		    const res2 = await fetch(htmlUrl, { credentials: 'same-origin' });
		    const txt = await res2.text();
		    const dom = new DOMParser().parseFromString(txt, 'text/html');

		    // Öncelik: data-id'li satırlar (gerçek kayıtlar)
		    let m = dom.querySelectorAll('tbody tr[data-id]').length;

		    // Yedek: seçim kutusu taşıyan satırlar (ids[] / .sec-kayit)
		    if (!(m > 0)) {
		      m = dom.querySelectorAll('tbody tr input[type="checkbox"].sec-kayit, tbody tr input[name="ids[]"]').length;
		    }

		    n = m || 0;
		  } catch {}
		}
	  }

	  findCopBadgeTargets().forEach(el => { el.textContent = String(n); });
	}

	// ilk yüklemede ve her işlemden sonra çağırılacak
	document.addEventListener('DOMContentLoaded', () => {
	  refreshTrashBadge();

	  // /cop sayfasında başka bir script rozeti sıfırlarsa anında geri al
	  if (/\/kategoriler\/cop(\/|$)/.test(location.pathname)) {
	    const targets = findCopBadgeTargets();
	    const mo = new MutationObserver(() => {
	      const n = countCopRows();
	      targets.forEach(el => {
	        if ((el.textContent || '').trim() !== String(n)) el.textContent = String(n);
	      });
	    });
	    targets.forEach(el => mo.observe(el, { childList: true, characterData: true, subtree: true }));
	    // ilk saniyelerde olası gecikmeli “0” yazmalar için kısa bir sync döngüsü
	    let t = 0; const tick = () => { refreshTrashBadge(); if ((t+=1) < 10) setTimeout(tick, 150); };
	    setTimeout(tick, 150);
	  }
	});

	// DOM zaten hazırsa ilk değerleri hemen güncelle
	if (document.readyState !== 'loading') {
	  try { refreshTrashBadge(); } catch {}
	  try { updateSecimSayaci(); } catch {}
	}

	 // ===== Seç-Tüm + Sayaç =====
	 function updateSecimSayaci() {
	   const n = document.querySelectorAll('.sec-kayit:checked, .secKutusu:checked, input[name="ids[]"]:checked').length;
	   const el = document.getElementById('secimSayaci');
	   if (el) el.textContent = `${n} seçili`;
	 }
 
	 document.addEventListener('change', (e) => {
	   const master = e.target.closest('#secTum');
	   if (master) {
	     const val = !!master.checked;
	     document.querySelectorAll('.sec-kayit, .secKutusu, input[name="ids[]"]').forEach(ch => { if ('checked' in ch) ch.checked = val; });
	     updateSecimSayaci();
	     return;
	   }
	   if (e.target.matches('.sec-kayit, .secKutusu, input[name="ids[]"]')) {
	     // satır bazlı seçimlerde de sayaç güncellensin
	     updateSecimSayaci();
	   }
	 });

	// ilk yüklemede sayaç
	document.addEventListener('DOMContentLoaded', updateSecimSayaci);

		// ===== Kategoriler: Tekil DURUM toggle (tek yetkili) =====
		document.addEventListener('click', async (e) => {
		  // Sadece /admin/kategoriler sayfalarında çalış
		  if (!/\/admin\/kategoriler(\/|$)/.test(location.pathname)) return;

		  const btn = e.target.closest('.js-durum-btn');
		  if (!btn) return;
		  e.preventDefault();

		  // Aynı anda bir kez
		  if (btn.dataset.busy === '1') return;
		  btn.dataset.busy = '1';

		  const id  = parseInt(btn.getAttribute('data-id') || btn.closest('tr[data-id]')?.dataset.id || '0', 10);
		  const url = btn.getAttribute('data-url') || (BASE + '/admin/kategoriler/durum-tekil');
		  if (!id || !url) { btn.dataset.busy = '0'; return; }

		  // Mevcut durum → hedef
		  const cur = readBtnState(btn);                 // 1=aktif, 0=pasif
		  const targetDurum = cur === 1 ? 'pasif' : 'aktif';

		  try {
		    btn.disabled = true;
		    const j = await postForm(url, { id, durum: targetDurum }); // urlencoded + CSRF

		    // Sunucudan nihai durum; yoksa hedef
		    let yeni = cur === 1 ? 0 : 1;
		    if (j && typeof j.durum !== 'undefined') {
		      yeni = String(j.durum).match(/^(1|true|aktif|yayinda)$/i) ? 1 : 0;
		    } else if (j && j.data && typeof j.data.durum !== 'undefined') {
		      yeni = String(j.data.durum).match(/^(1|true|aktif|yayinda)$/i) ? 1 : 0;
		    }

		    // Sınıfları önce temizle, sonra tekini ekle (çifte sınıf kalmasın)
		    btn.classList.remove('btn-success', 'btn-secondary', 'bg-success', 'bg-secondary');
		    applyBtnState(btn, yeni);                     // iç label/badge/metni düzenler
		    toastOrAlert('Durum güncellendi.');
		  } catch (err) {
		    toastOrAlert(err.message || 'Durum güncellenemedi.');
		  } finally {
		    btn.disabled = false;
		    btn.dataset.busy = '0';
		  }
		});

		// ===== Normal liste: TOPLU (aktif/pasif/sil) =====
		document.addEventListener('click', async (e) => {
		  const btn = e.target.closest('.js-bulk, [data-bulk], [data-aksiyon]');
		  if (!btn) return;

		  // Çöp modu butonları bu bloğa gelmesin
		  if (/\/kategoriler\/cop(\/|$)/.test(location.pathname)) return;

		  e.preventDefault();

		  const ids = Array.from(document.querySelectorAll('.sec-kayit:checked, .secKutusu:checked, input[name="ids[]"]:checked'))
		                   .map(i => parseInt(i.value, 10)).filter(Boolean);
		  if (!ids.length) return toastOrAlert('Seçim yok.');

		  const action = btn.getAttribute('data-bulk') || btn.getAttribute('data-aksiyon') || '';
		  let url = btn.getAttribute('data-url') || '';
		  if (!url) {
		    if (action === 'sil') url = (BASE || '') + '/admin/kategoriler/sil-toplu';
		    else url = (BASE || '') + '/admin/kategoriler/durum-toplu';
		  }

		  // İletilecek durum değeri (aktif/pasif)
		  let durum = null;
		  if (/aktif/i.test(action)) durum = 'aktif';
		  if (/pasif|taslak/i.test(action)) durum = 'pasif';

		  // Onay metni
		  const msg = (action === 'sil')
		    ? 'Seçilenler çöp kutusuna taşınacak. Onaylıyor musunuz?'
		    : (durum === 'aktif' ? 'Seçilenler AKTİF yapılacak. Onaylıyor musunuz?'
		                         : 'Seçilenler PASİF yapılacak. Onaylıyor musunuz?');

		  const ok = await askConfirm(msg);
		  if (!ok) return;

		  try {
		    btn.disabled = true;
		    const payload = { 'ids[]': ids };
		    if (durum) payload.durum = durum;
		    await postForm(url, payload);

		    // UI: satırlarda durum rozetlerini güncelle veya sil işlemi ise satırları kaldır
		    if (action === 'sil') {
		      ids.forEach(id => document.querySelector(`tr[data-id="${id}"]`)?.remove());
		      if (!document.querySelector('tbody tr')) { location.reload(); return; }
		      toastOrAlert('Çöpe taşındı.');
		    } else {
		      const aktifMi = (durum === 'aktif');
		      ids.forEach(id => {
		        const b = document.querySelector(`tr[data-id="${id}"] .js-durum-btn`);
		        if (b) {
		          b.textContent = aktifMi ? 'Aktif' : 'Pasif';
		          b.classList.toggle('btn-success', aktifMi);
		          b.classList.toggle('btn-secondary', !aktifMi);
		        }
		      });
		      toastOrAlert('Durum güncellendi.');
		    }
		  } catch (err) {
		    toastOrAlert(err.message || 'Toplu işlem başarısız.');
		  } finally {
		    btn.disabled = false;
		  }
		});

		// ===== ÇÖP – TEKİL (Geri Al / Kalıcı Sil) =====
		document.addEventListener('click', async (e) => {
		  const btn = e.target.closest(
		    // Sınıf/atribüt + href desenleri (çoğu temaya uysun)
		    '.js-trash-tekli, [data-action="geri-al-tek"], [data-action="yok-et-tek"], .btn-geri-al-tek, .btn-kalici-sil-tek, a[href*="/geri-al"], a[href*="/yok-et"]'
		  );
		  if (!btn) return;

		  // Link default davranışını engelle
		  e.preventDefault();

		  // URL: data-url öncelik, yoksa href
		  let url = btn.getAttribute('data-url') || btn.getAttribute('href') || '';
		  if (!url || url === '#') return toastOrAlert('İşlem URL’i bulunamadı.');

		  // ID: 6 farklı kaynaktan dene (querystring dahil)
		  let id = parseInt(btn.getAttribute('data-id') || '0', 10);

		  // 1) TR data-id
		  const tr = btn.closest('tr');
		  if (!id && tr?.hasAttribute('data-id')) id = parseInt(tr.getAttribute('data-id') || '0', 10);

		  // 2) TR içindeki gizli inputlar
		  if (!id && tr) {
		    const hid = tr.querySelector('input[name="id"], input[name="kategori_id"], input[name="sil_id"]');
		    if (hid) id = parseInt(hid.value || '0', 10);
		  }

		  // 3) Hücredeki "#12" metninden
		  if (!id && tr) {
		    const firstCellText = (tr.querySelector('td, th')?.textContent || '').trim();
		    const m = firstCellText.match(/#\s*(\d+)/);
		    if (m) id = parseInt(m[1], 10);
		  }

		  // 4) URL querystring'inden (?id= / ?kategori_id= / ?sil_id=)
		  if (!id) {
		    try {
		      const u = new URL(url, location.origin);
		      id = parseInt(u.searchParams.get('id') || u.searchParams.get('kategori_id') || u.searchParams.get('sil_id') || '0', 10);
		    } catch {}
		  }

		  if (!id) return toastOrAlert('ID bulunamadı.');

		  const isPurge = /kalici|kalıcı|yok[-_ ]?et|kalici-sil/i.test(url);
		  const ok = await askConfirm(isPurge ? 'Kayıt KALICI silinecek. Onaylıyor musunuz?' : 'Kayıt geri alınacak. Onaylıyor musunuz?');
		  if (!ok) return;

		  try {
		    // Denetleyici hangi adı beklerse — üç isim + dizi formları
		    await postForm(url, {
		      id, kategori_id: id, sil_id: id,
		      'ids[]': [id], 'kategori_ids[]': [id]
		    });

		    // Satırı kaldır; tablo boşsa yenile
			if (!removeRows([id])) {
			  refreshTrashBadge();
			  toastOrAlert(isPurge ? 'Kalıcı silindi.' : 'Geri alındı.');
			}
		  } catch (err) {
		    toastOrAlert(err.message || 'İşlem başarısız.');
		  }
		});

		// ===== ÇÖP – TOPLU (Seçilenleri Geri Al / Kalıcı Sil) =====
		document.addEventListener('click', async (e) => {
		  const btn = e.target.closest('.js-trash');
		  if (!btn) return;
		  e.preventDefault();

		  const url = btn.getAttribute('data-url');
		  if (!url) { try { toastOrAlert('İşlem URL’i bulunamadı.'); } catch {} return; }

		  const ids = Array.from(document.querySelectorAll('.sec-kayit:checked, .secKutusu:checked, input[name="ids[]"]:checked'))
		                   .map(i => parseInt(i.value, 10)).filter(Boolean);
		  if (!ids.length) { try { toastOrAlert('Seçim yok.'); } catch {} return; }

		  const isPurge = /kalici|kalıcı|yok[-_ ]?et|kalici-sil/i.test(url);
		  const ok = await (typeof askConfirm === 'function'
		    ? askConfirm(isPurge ? 'Seçilenler KALICI silinecek. Onaylıyor musunuz?' : 'Seçilenler geri alınacak. Onaylıyor musunuz?')
		    : Promise.resolve(confirm(isPurge ? 'Seçilenler KALICI silinecek. Onaylıyor musunuz?' : 'Seçilenler geri alınacak. Onaylıyor musunuz?'))
		  );
		  if (!ok) return;

		  try {
		    // Her iki isimle de gönder → backend hangisini bekliyorsa onu alır
		    await postForm(url, { 'ids[]': ids, 'kategori_ids[]': ids });

		    // Satırları kaldırıp tablo boşsa yenile
			ids.forEach(id => document.querySelector(`tr[data-id="${id}"]`)?.remove());
			if (!document.querySelector('tbody tr')) { location.reload(); return; }
			refreshTrashBadge();
			toastOrAlert(isPurge ? 'Kalıcı silindi.' : 'Geri alındı.');

		  } catch (err) {
		    try { toastOrAlert(err.message || 'İşlem başarısız.'); } catch {}
		  }
		});

	  // ===== FORM – slug canlı + benzersizlik =====
	  (function bindKategoriForm() {
	    const form = document.querySelector('form[action*="/admin/kategoriler/kaydet"], form[action*="/admin/kategoriler/guncelle"]');
	    if (!form) return;

	    const ad   = form.querySelector('input[name="ad"]');
	    const slug = form.querySelector('input[name="slug"]#kategoriSlug');
	    if (!slug) return;

	    const checkUrl = slug.getAttribute('data-check-url') || (BASE + '/admin/kategoriler/slug-kontrol');
	    const id       = parseInt(slug.getAttribute('data-id') || '0', 10) || 0;

	    let slugDirty = !!slug.value.trim();
	    slug.addEventListener('input', () => { slugDirty = !!slug.value.trim(); });

	    if (ad) {
	      ad.addEventListener('input', debounce(async () => {
	        if (slugDirty) return;
	        const val = slugifyTR(ad.value);
	        await setUniqueSlug(val);
	      }, 250));
	    }

	    async function setUniqueSlug(val) {
	      if (!val) { slug.value = ''; setInvalid(true, 'Boş bırakılamaz.'); return; }
	      try {
	        const csrf = getCsrf();
	        const body = new URLSearchParams();
	        body.append('slug', val);
	        body.append('id', String(id));
	        if (csrf) body.append('csrf', csrf);

	        const res  = await fetch(checkUrl, {
	          method: 'POST',
	          headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch', ...(csrf ? {'X-CSRF-Token': csrf} : {}), 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
	          credentials: 'same-origin',
	          body
	        });
	        let json=null; try{ json = await res.json(); }catch(_){}
	        const s = json?.veri?.slug || val;
	        slug.value = s;
	        setInvalid(false);
	      } catch {
	        slug.value = val;
	        setInvalid(false);
	      }
	    }

	    function setInvalid(invalid, msg) {
	      slug.classList.toggle('is-invalid', !!invalid);
	      const fb = slug.parentElement.querySelector('.invalid-feedback');
	      if (fb && msg) fb.textContent = msg;
	      const submit = form.querySelector('button[type="submit"],input[type="submit"]');
	      if (submit) submit.disabled = !!invalid;
	    }

	    function debounce(fn, t=300){ let id; return (...a)=>{ clearTimeout(id); id=setTimeout(()=>fn(...a), t); }; }
	  })();

})();
