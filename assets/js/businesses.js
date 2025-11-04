(function(){
  "use strict";

  const $ = (s, c=document) => c.querySelector(s);
  const $$ = (s, c=document) => Array.from(c.querySelectorAll(s));
  const safe = (v, fb="") => (v===undefined||v===null?fb:v);
  const html = (strings, ...vals) => strings.reduce((a,s,i)=>a+s+(vals[i]??""), "");

  const app = window.appConfig || {};
  const lang = app.language || 'pt-br';
  let locationData = window.locationData || {
    country_code: app.country_code || '',
    region: app.region || '',
    city: app.city || ''
  };
  let filters = window.initialFilters || { category: 'liberal', price_max: null, distance: null, keywords: '' };

  const API_BASE = '/api/businesses.php';

  async function apiPost(action, payload) {
    const r = await fetch(API_BASE, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, lang, ...payload })
    });
    if (!r.ok) throw new Error('HTTP '+r.status);
    return r.json();
  }

  async function loadBusinesses() {
    toggleLoading(true);
    try {
      const res = await apiPost('get_businesses', {
        location_data: locationData,
        filters
      });
      const data = res?.data || {};
      renderBreadcrumb(data.location, data.level);
      renderResults(Array.isArray(data.businesses)?data.businesses:[]);
    } catch (e) {
      console.error(e);
      renderBreadcrumb(locationData, 'global');
      renderResults([]);
    } finally {
      toggleLoading(false);
    }
  }

  function toggleLoading(on){ const sp=$('#loading-spinner'); if(sp) sp.style.display = on?'block':'none'; }

  function renderBreadcrumb(loc, level){
    const bc = $('#location-breadcrumb'); if(!bc) return;
    const parts = [];
    parts.push(`<button type=\"button\" class=\"bc-level\">Terra</button>`);
    if (loc?.country_code){ parts.push(`<span class=\"bc-level\">${safe(loc.country_name, loc.country_code)}</span>`); }
    if (loc?.region){ parts.push(`<span class=\"bc-level\">${safe(loc.region)}</span>`); }
    if (loc?.city){ parts.push(`<span class=\"bc-level\">${safe(loc.city)}</span>`); }
    bc.innerHTML = parts.join(' \u203A ');
  }

  function card(it){
    const name = safe(it.name, '—');
    const city = safe(it.city);
    const region = safe(it.region);
    const country = safe(it.country_name || it.country_code);
    const loc = [city,region,country].filter(Boolean).join(' · ');
    const img = safe(it.image_url);
    const href = it.slug ? ('/empresa/'+encodeURIComponent(it.slug)) : 'javascript:void(0)';
    return html`<article class=\"card\">
      <a class=\"thumb\" href=\"${href}\" aria-label=\"${name}\">
        <img loading=\"lazy\" src=\"${img}\" alt=\"${name}\">\n      </a>
      <div class=\"meta\">\n        <h3 class=\"name\">${name}</h3>\n        <div class=\"loc\">${loc}</div>\n      </div>
    </article>`;
  }

  function renderResults(items){
    const grid = $('#results-grid'); if(!grid) return;
    if (!items || items.length===0){
      grid.innerHTML = `<p class=\"no-results-message\">${(window.translations && (translations.no_profiles_found||translations['no_profiles_found'])) || 'Nada por aqui ainda'}</p>`;
      return;
    }
    grid.innerHTML = items.map(card).join('');
  }

  function wireFilters(){
    $$('.filter-btn').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const f = btn.getAttribute('data-filter');
        const v = btn.getAttribute('data-value');
        if (f) filters[f] = v;
        loadBusinesses();
      });
    });
    const price = $('#price-range');
    if (price){ price.addEventListener('input', ()=>{ filters.price_max = Number(price.value)||null; }); price.addEventListener('change', ()=> loadBusinesses()); }
    const dist = $('#distance-range');
    if (dist){ dist.addEventListener('input', ()=>{ filters.distance = Number(dist.value)||null; }); dist.addEventListener('change', ()=> loadBusinesses()); }
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    try { if (window.initialBusinessData && window.initialBusinessData.businesses && window.initialBusinessData.businesses.length) { renderResults(window.initialBusinessData.businesses); } } catch(e) {}
    wireFilters();
    loadBusinesses();
  });
})();
