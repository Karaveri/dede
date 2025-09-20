// === Yardımcılar ===
const BASE  = (document.querySelector('meta[name="base"]')?.content || '').replace(/\/$/, '');
const CSRF  = document.querySelector('meta[name="csrf-token"]')?.content
           || document.querySelector('meta[name="csrf"]')?.content || '';

function showToast(message, type = 'success', delay = 2500) {
  const c = document.getElementById('toastContainer');
  if (!c) { alert(message); return; } // emniyet supabı
  const el = document.createElement('div');
  const cls = type === 'error' ? 'danger' : (type === 'warning' ? 'warning' : 'success');
  el.className = `toast align-items-center text-bg-${cls} border-0`;
  el.role = 'alert'; el.ariaLive = 'assertive'; el.ariaAtomic = 'true';
  el.innerHTML = `
    <div class="d-flex">
      <div class="toast-body">${message}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>`;
  c.appendChild(el);
  const t = new bootstrap.Toast(el, { delay });
  t.show();
  el.addEventListener('hidden.bs.toast', () => el.remove());
}

function confirmModal(message) {
  // sablon.php'deki modalı kullan
  const modalEl = document.getElementById('confirmModal');
  if (!modalEl) return Promise.resolve(confirm(message));
  document.getElementById('confirmModalMsg').textContent = message || 'Emin misiniz?';
  const okBtn = document.getElementById('confirmModalOk');
  const m = bootstrap.Modal.getOrCreateInstance(modalEl);
  return new Promise(resolve => {
    const onOk = () => { cleanup(); resolve(true); };
    const onHide = () => { cleanup(); resolve(false); };
    function cleanup(){
      okBtn.removeEventListener('click', onOk);
      modalEl.removeEventListener('hidden.bs.modal', onHide);
    }
    okBtn.addEventListener('click', onOk, { once: true });
    modalEl.addEventListener('hidden.bs.modal', onHide, { once: true });
    m.show();
  });
}

function url(path){ return `${BASE}${path}`; }

// === Diller: Form submit ===
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('dilForm');
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      try {
        const res = await fetch(url('/admin/diller/kaydet'), {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': CSRF,
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: fd,
          credentials: 'same-origin'
        });
        const j = await res.json();
        if (j.ok) {
          showToast('Dil kaydedildi.', 'success');
          const y = (j.veri && j.veri.yonlendir) ? j.veri.yonlendir : url('/admin/diller');
          setTimeout(() => { location.href = y; }, 800);
        } else {
          showToast(j.mesaj || 'Kayıt hatası', 'error');
          console.warn(j);
        }
      } catch (err) {
        console.error(err);
        showToast('İstek hatası', 'error');
      }
    });
  }

  // === Diller: Silme ===
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.js-dil-sil');
    if (!btn) return;
    const kod = btn.dataset.kod;
    const ok = await confirmModal(`${kod.toUpperCase()} dilini silmek istiyor musun?`);
    if (!ok) return;

    try {
      const res = await fetch(url('/admin/diller/sil'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-TOKEN': CSRF,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({ kod }),
        credentials: 'same-origin'
      });
      const j = await res.json();
      if (j.ok) {
        showToast('Dil silindi.', 'success');
        setTimeout(() => location.reload(), 700);
      } else {
        showToast(j.mesaj || 'Silme hatası', 'error');
      }
    } catch (err) {
      console.error(err);
      showToast('İstek hatası', 'error');
    }
  });
});
