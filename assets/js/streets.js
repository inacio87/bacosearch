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

  const API_BASE = '/api/streets.php';

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

  async function loadStreets() {
    toggleLoading(true);
    try {
      const res = await apiPost('get_streets', {
        location_data: locationData,
        filters
      });
      const data = res?.data || {};
      renderBreadcrumb(data.location, data.level);
      renderResults(Array.isArray(data.streets)?data.streets:[]);
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
    parts.push(`<button type="button" class="bc-level">Terra</button>`);
    if (loc?.country_code){ parts.push(`<span class="bc-level">${safe(loc.country_name, loc.country_code)}</span>`); }
    if (loc?.region){ parts.push(`<span class="bc-level">${safe(loc.region)}</span>`); }
    if (loc?.city){ parts.push(`<span class="bc-level">${safe(loc.city)}</span>`); }
    bc.innerHTML = parts.join(' \u203A ');
  }

  function card(it){
    const name = safe(it.name, '—');
    const city = safe(it.city);
    const region = safe(it.region);
    const country = safe(it.country_name || it.country_code);
    const loc = [city,region,country].filter(Boolean).join(' · ');
    const placeType = safe(it.place_type, 'street');
    const icon = placeType === 'bar' ? '🍸' : '🛣️';
    return html`<article class="card">
      <div class="thumb" style="display: flex; align-items: center; justify-content: center; background: var(--color-bg-secondary, #eee); min-height: 150px; font-size: 4rem;">
        ${icon}
      </div>
      <div class="meta">
        <h3 class="name">${name}</h3>
        <div class="loc">${loc}</div>
        <div class="type muted" style="font-size: 0.85rem;">${placeType === 'bar' ? 'Bar' : 'Rua/Lugar'}</div>
      </div>
    </article>`;
  }

  function renderResults(items){
    const grid = $('#results-grid'); if(!grid) return;
    if (!items || items.length===0){
      grid.innerHTML = `<p class="no-results-message">${(window.translations && (translations.no_profiles_found||translations['no_profiles_found'])) || 'Nada por aqui ainda'}</p>`;
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
        loadStreets();
      });
    });
    const price = $('#price-range');
    if (price){ price.addEventListener('input', ()=>{ filters.price_max = Number(price.value)||null; }); price.addEventListener('change', ()=> loadStreets()); }
    const dist = $('#distance-range');
    if (dist){ dist.addEventListener('input', ()=>{ filters.distance = Number(dist.value)||null; }); dist.addEventListener('change', ()=> loadStreets()); }
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    try { if (window.initialStreetData && window.initialStreetData.streets && window.initialStreetData.streets.length) { renderResults(window.initialStreetData.streets); } } catch(e) {}
    wireFilters();
    loadStreets();
  });
})();
