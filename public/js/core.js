window.adminCore = (function () {
  let __once = {};
  function once(key){ if(__once[key]) return false; __once[key]=true; return true; }

  function getCsrf() {
    return (window.PAGE_SAYFALAR?.CSRF) ||
           (window.PAGE_MEDYA?.CSRF)     ||
           document.getElementById('csrf')?.value ||
           document.querySelector('meta[name="csrf-token"]')?.content ||
           document.querySelector('meta[name="csrf"]')?.content || '';
  }
  function toast(msg, variant){
    try { if (window.showToast) return window.showToast(msg, variant); } catch(_){}
    alert(msg);
  }
  async function postForm(url, dataObj) {
    const token = getCsrf();
    const body = new URLSearchParams();
    Object.entries(dataObj||{}).forEach(([k,v])=>{
      if (Array.isArray(v)) v.forEach(x=>body.append(k,x)); else body.append(k,v);
    });
    if (token && !body.has('csrf')) body.append('csrf', token);
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Accept':'application/json',
        'X-Requested-With':'fetch',
        'X-CSRF-Token': token,
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      credentials: 'same-origin',
      body
    });
    let json=null; try{ json = await res.json(); }catch(_){}
    return { ok: res.ok, json };
  }
  function fetchJSON(url, opt) {
    return fetch(url, opt).then(async r=>{
      const t = await r.text();
      try { return { ok:r.ok, status:r.status, json: JSON.parse(t) }; }
      catch { return { ok:r.ok, status:r.status, text: t }; }
    });
  }
  function removeRowsByIds(ids){ ids.forEach(id=>document.querySelector(`tr[data-id="${id}"]`)?.remove()); }
  function slugifyTR(s){
    const map = {'ş':'s','Ş':'s','ı':'i','İ':'i','ç':'c','Ç':'c','ğ':'g','Ğ':'g','ü':'u','Ü':'u','ö':'o','Ö':'o'};
    s = (s||'').replace(/[şŞıİçÇğĞüÜöÖ]/g, ch => map[ch] || ch)
               .normalize('NFKD').replace(/[\u0300-\u036f]/g,'')
               .toLowerCase().replace(/[^a-z0-9]+/g,'-')
               .replace(/^-+|-+$/g,'').replace(/-{2,}/g,'-');
    return s.substring(0,220);
  }

  return { once, getCsrf, toast, postForm, fetchJSON, removeRowsByIds, slugifyTR };
})();
