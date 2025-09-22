/* public/js/medya.js */
(function medyaModule(){
  const C = window.adminCore; if (!C || !C.once('medya')) return;

  const grid = document.getElementById('medya-grid');
  const medyaForm = document.getElementById('medyaForm');
  if (!grid && !medyaForm) return; // medya sayfasında değiliz

  const ctx  = window.PAGE_MEDYA || {};
  const BASE = ctx.BASE || (location.origin);
  const csrf = ctx.CSRF || C.getCsrf();

  // Tümünü seç
  document.getElementById('secTum')?.addEventListener('change', function(){
    document.querySelectorAll('.chk').forEach(ch => ch.checked = !!this.checked);
  });

  // Önizleme modalini doldur + etiket/meta çek
  document.addEventListener('click', async (e)=>{
    const a = e.target.closest('.media-thumb'); if(!a) return;
    e.preventDefault();
    const src = a.dataset.src, mid = a.dataset.mid || '';

    document.getElementById('mediaModalImg').src = src;
    document.getElementById('mediaUrl').value  = src;
    document.getElementById('openNewTab').href = src;
    document.getElementById('mediaMid').value  = mid;

    const nn = document.getElementById('mediaNewName');
    const sp = document.getElementById('slugPreview');
    if (nn) {
      try { const base = (src.split('/').pop() || '').replace(/\.[^.]+$/, ''); nn.value=''; nn.placeholder = base || ''; } catch{ nn.value=''; }
      const updatePreview = ()=>{ const seed = nn.value.trim() || nn.placeholder || ''; if (sp) sp.textContent = C.slugifyTR(seed); };
      updatePreview(); nn.oninput = updatePreview;
    }
    await loadTags(mid); await loadMeta(mid);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('mediaModal')).show();
  });

  function renderBadges(list){
    const wrap = document.getElementById('mediaTagsBadges'); if(!wrap) return;
    wrap.innerHTML = ''; (list||[]).forEach(e=>{ const s=document.createElement('span'); s.className='badge text-bg-light border me-1 mb-1'; s.textContent = e.ad || e.slug; wrap.appendChild(s); });
  }

  async function loadTags(mid){
    const r = await C.fetchJSON(`${BASE}/admin/api/medya/etiketler?mid=${encodeURIComponent(mid)}`);
    if (r.ok && r.json?.ok) {
      const tags = r.json.etiketler || [];
      document.getElementById('mediaTags').value = tags.map(t => t.ad || t.slug).join(', ');
      renderBadges(tags);
    } else { document.getElementById('mediaTags').value = ''; renderBadges([]); }
  }

  document.getElementById('saveTagsBtn')?.addEventListener('click', async ()=>{
    const mid  = parseInt(document.getElementById('mediaMid').value || '0', 10);
    const tags = document.getElementById('mediaTags').value || '';
    const mode = document.querySelector('input[name="tagMode"]:checked')?.value || 'replace';
    if (!mid) return;
    const r = await C.fetchJSON(`${BASE}/admin/api/medya/etiketle`, {
      method:'POST',
      headers:{ 'Content-Type':'application/json', ...(csrf?{ 'X-CSRF-Token':csrf, 'X-CSRF':csrf }:{}) },
      body: JSON.stringify({ medya_id: mid, etiketler: tags, mod: mode })
    });
    if (r.ok && r.json?.ok) { renderBadges(r.json.etiketler||[]); C.toast(`Etiketler ${mode==='append'?'eklendi':'eşitlendi'}.`); }
    else C.toast('Kaydedilemedi: ' + (r.json?.hata || `HTTP ${r.status}`), 'danger');
  });

  async function loadMeta(mid){
    const r = await C.fetchJSON(`${BASE}/admin/api/medya/meta?mid=${encodeURIComponent(mid)}`);
    if (r.ok && r.json?.ok) {
      const m = r.json.medya || {};
      document.getElementById('mediaAlt').value   = m.alt_text ?? '';
      document.getElementById('mediaTitle').value = m.title    ?? '';
    } else { document.getElementById('mediaAlt').value=''; document.getElementById('mediaTitle').value=''; }
  }

  document.getElementById('saveMetaBtn')?.addEventListener('click', async ()=>{
    const mid = parseInt(document.getElementById('mediaMid').value || '0', 10);
    const alt = document.getElementById('mediaAlt').value || '';
    const tit = document.getElementById('mediaTitle').value || '';
    const yeni = (document.getElementById('mediaNewName')?.value || '').trim();
    if (!mid) return;
    const payload = { medya_id: mid, alt_text: alt, title: tit }; if (yeni) payload.yeni_ad = yeni;
    const r = await C.fetchJSON(`${BASE}/admin/api/medya/meta`, {
      method:'POST',
      headers:{ 'Content-Type':'application/json', ...(csrf?{ 'X-CSRF-Token':csrf, 'X-CSRF':csrf }:{}) },
      body: JSON.stringify(payload)
    });
    if (r.ok && r.json?.ok) {
      if (r.json?.yol) { const urlInp=document.getElementById('mediaUrl'); const openA=document.getElementById('openNewTab'); urlInp && (urlInp.value=r.json.yol); openA && (openA.href=r.json.yol); }
      C.toast('Meta bilgileri kaydedildi.');
    } else C.toast('Meta kaydedilemedi: ' + (r.json?.hata || `HTTP ${r.status}`), 'danger');
  });

  // Toplu etiket modalı
  (function bulkTags(){
    const bulkTagBtn = document.getElementById('bulkTagBtn');
    const bulkTagModalEl = document.getElementById('bulkTagModal');
    const bulkTagModal = bulkTagModalEl ? new bootstrap.Modal(bulkTagModalEl) : null;
    bulkTagBtn?.addEventListener('click', ()=>{
      const ids = Array.from(document.querySelectorAll('.chk:checked')).map(ch => +ch.value);
      if (!ids.length) return C.toast('Seçili görsel yok.', 'warning');
      document.getElementById('bulkTagsInput').value = '';
      bulkTagModal?.show(); setTimeout(()=> document.getElementById('bulkTagsInput')?.focus(), 200);
    });
    document.getElementById('bulkTagForm')?.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const ids = Array.from(document.querySelectorAll('.chk:checked')).map(ch => +ch.value);
      const tags = document.getElementById('bulkTagsInput').value || '';
      if (!ids.length) return C.toast('Seçili görsel yok.', 'warning');
      const saveBtn = document.getElementById('bulkTagSaveBtn'); saveBtn.disabled = true;
      let ok=0, fail=0;
      for (const id of ids) {
        const r = await C.fetchJSON(`${BASE}/admin/api/medya/etiketle`, {
          method:'POST',
          headers:{ 'Content-Type':'application/json', ...(csrf?{ 'X-CSRF-Token':csrf, 'X-CSRF':csrf }:{}) },
          body: JSON.stringify({ medya_id: id, etiketler: tags, mod: 'append' })
        });
        if (r.ok && r.json?.ok) ok++; else fail++;
      }
      saveBtn.disabled = false; bulkTagModal?.hide();
      C.toast(`Etiket atama • Tamam: ${ok} • Hata: ${fail}`, fail ? 'warning' : 'success');
    });
  })();

  // Silme onay modali (tekil + toplu)
  (function deletion(){
    const hiddenSingleId = document.getElementById('hiddenSingleId');
    const confirmModalEl = document.getElementById('confirmDeleteModal');
    const confirmText    = document.getElementById('confirmText');
    const confirmForm    = document.getElementById('confirmForm');
    const confirmBtn     = document.getElementById('confirmBtn');
    if (!confirmModalEl || !confirmForm || !medyaForm) return;
    let deleteMode = null;
    const confirmModal = new bootstrap.Modal(confirmModalEl);

    document.querySelectorAll('.btn-sil-tek').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        deleteMode = 'single';
        const id = btn.dataset.id, name = btn.dataset.name || ('#'+id);
        hiddenSingleId.value = id;
        confirmText.textContent = `"${name}" adlı görsel silinecek. Bu işlem geri alınamaz.`;
        confirmBtn.disabled = false; confirmModal.show();
      });
    });

    document.getElementById('bulkDeleteBtn')?.addEventListener('click', ()=>{
      const checked = Array.from(document.querySelectorAll('.chk')).filter(ch => ch.checked);
      if (!checked.length) { deleteMode=null; confirmText.textContent='Seçili görsel bulunmuyor.'; confirmBtn.disabled = true; }
      else { deleteMode='bulk'; confirmText.textContent = `${checked.length} adet görsel silinecek. Bu işlem geri alınamaz.`; confirmBtn.disabled=false; }
      confirmModal.show();
    });

    confirmForm.addEventListener('submit', (e)=>{
      e.preventDefault();
      if (deleteMode==='single')      medyaForm.action = `${BASE}/admin/medya/sil`;
      else if (deleteMode==='bulk')   medyaForm.action = `${BASE}/admin/medya/toplu-sil`;
      else return confirmModal.hide();
      confirmModal.hide(); medyaForm.submit();
    });
  })();
})();
