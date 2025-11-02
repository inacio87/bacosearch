/* providers.js — grid 2 colunas, breadcrumb com “Mais”, traduções e Terra
   Compatível com /api/providers.php (GET: planet/country/region/city; POST actions)
*/

(function () {
  "use strict";

  // ---------- helpers ----------
  const $  = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const safe = (v, fb = "") => (v === undefined || v === null ? fb : v);
  const html = (strings, ...vals) =>
    strings.reduce((acc, s, i) => acc + s + (vals[i] ?? ""), "");

  const ensureEl = (id, tag, cls, parent) => {
    let el = document.getElementById(id);
    if (el) return el;
    el = document.createElement(tag);
    el.id = id;
    if (cls) el.className = cls;
    (parent || document.body).appendChild(el);
    return el;
  };

  // sessão (vinda do PHP em data-*)
  const SES = (() => {
    const b = document.body;
    const num = (k) => {
      const v = b.getAttribute(`data-${k}`);
      return v ? Number(v) : null;
    };
    return {
      lang:   b.getAttribute("data-lang")   || "pt-br",
      country:b.getAttribute("data-country")|| "",
      region: b.getAttribute("data-region") || "",
      city:   b.getAttribute("data-city")   || "",
      lat:    num("lat"),
      lon:    num("lon"),
      tEarth: b.getAttribute("data-t-earth")|| null,
      tMore:  b.getAttribute("data-t-more") || null,
    };
  })();

  // ---------- i18n ----------
  const FALLBACK = {
    "pt-br": { earth: "Terra", more: "Mais", priceNotInformed: "Preço não informado", hour: "/h", km: "km", error_loading: "Erro ao carregar. Tente novamente." },
    "pt-pt": { earth: "Terra", more: "Mais", priceNotInformed: "Preço não informado", hour: "/h", km: "km", error_loading: "Erro ao carregar. Tente novamente." },
    "en-us": { earth: "Earth", more: "More", priceNotInformed: "Price not informed", hour: "/h", km: "km", error_loading: "Failed to load. Try again." },
    "es-es": { earth: "Tierra", more: "Más", priceNotInformed: "Precio no informado", hour: "/h", km: "km", error_loading: "Error al cargar. Intenta de nuevo." },
    "de-de": { earth: "Erde", more: "Mehr", priceNotInformed: "Preis nicht angegeben", hour: "/h", km: "km", error_loading: "Fehler beim Laden. Bitte erneut versuchen." },
    "fr-fr": { earth: "Terre", more: "Plus", priceNotInformed: "Prix non informé", hour: "/h", km: "km", error_loading: "Erreur de chargement. Réessayez." },
    "it-it": { earth: "Terra", more: "Più", priceNotInformed: "Prezzo non indicato", hour: "/h", km: "km", error_loading: "Errore di caricamento. Riprova." },
    "nl-nl": { earth: "Aarde", more: "Meer", priceNotInformed: "Prijs niet vermeld", hour: "/h", km: "km", error_loading: "Laden mislukt. Probeer opnieuw." },
  };

  function tr(key, fallback = "") {
    if (typeof window.__t === "function") {
      const v = window.__t(key);
      if (v) return v;
    }
    if (window.__i18n && window.__i18n[key]) return window.__i18n[key];
    if (key === "breadcrumb.earth" && SES.tEarth) return SES.tEarth;
    if (key === "breadcrumb.more"  && SES.tMore)  return SES.tMore;
    const f = FALLBACK[SES.lang] || FALLBACK["pt-br"];
    if (key === "breadcrumb.earth") return f.earth;
    if (key === "breadcrumb.more")  return f.more;
    if (key === "priceNotInformed") return f.priceNotInformed;
    if (key === "hour") return f.hour;
    if (key === "km") return f.km;
    if (key === "error_loading") return f.error_loading;
    return fallback || key;
  }

  // ---------- API ----------
  const API = {
    base: "/api/providers.php",

    async _get(url) {
      const sep = url.includes("?") ? "&" : "?";
      const withLang = `${url}${sep}lang=${encodeURIComponent(SES.lang)}`;
      const r = await fetch(withLang, { credentials: "include" });
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    },

    async _post(action, payload = {}) {
      const url = `${this.base}?lang=${encodeURIComponent(SES.lang)}`;
      const r = await fetch(url, {
        method: "POST",
        credentials: "include",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action, ...payload }),
      });
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    },

    async getProviders(params = {}) {
      const qs = new URLSearchParams({
        planet: "earth",
        country: params.country || "",
        region:  params.region  || "",
        city:    params.city    || "",
      });
      return this._get(`${this.base}?${qs.toString()}`);
    },

    listCountries() {
      return this._post("get_countries");
    },

    listRegions(countryCode) {
      return this._post("get_regions", { country_code: safe(countryCode, "") });
    },

    listCities(countryCode, regionName) {
      return this._post("get_cities", {
        country_code: safe(countryCode, ""),
        region_name:  safe(regionName, ""),
      });
    },
  };

  // ---------- Assets ----------
  const IMG_FALLBACK =
    'data:image/svg+xml;charset=utf-8,' +
    encodeURIComponent(
      `<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400">
         <defs><style>
           .bg{fill:#f3f4f6}.ph{fill:#c7c9cf;font:700 20px sans-serif}
         </style></defs>
         <rect class="bg" width="100%" height="100%"/>
         <text class="ph" x="50%" y="50%" dominant-baseline="middle" text-anchor="middle">No Image</text>
       </svg>`
    );

  // ---------- Modal ----------
  const modal = {
    el: null,
    ensure() {
      if (this.el) return this.el;
      document.body.insertAdjacentHTML(
        "beforeend",
        html`<div class="modal-overlay" id="locModal">
          <div class="modal-content">
            <div class="modal-header">
              <strong id="locTitle"></strong>
              <button class="close-modal" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body" id="locBody"></div>
          </div>
        </div>`
      );
      this.el = $("#locModal");
      this.el.addEventListener("click", (e) => {
        if (e.target.classList.contains("modal-overlay") || e.target.classList.contains("close-modal")) {
          this.hide();
        }
      });
      return this.el;
    },
    show(title, bodyHTML) {
      this.ensure();
      $("#locTitle").textContent = title;
      $("#locBody").innerHTML = bodyHTML;
      this.el.classList.add("show");
    },
    hide() {
      this.ensure();
      this.el.classList.remove("show");
    },
  };

  // ---------- Página ----------
  class ResultsPage {
    constructor() {
      const root = document.getElementById("providers-root") || $("main") || document.body;

      this.breadcrumb = $("#breadcrumb") || ensureEl("breadcrumb", "nav", "location-breadcrumb", root);
      this.grid       = $("#resultsGrid") || ensureEl("resultsGrid", "section", "results-grid", root);
      this.marsMsg    = $("#marsMsg") || ensureEl("marsMsg", "div", "mars-message", root);
      if (this.marsMsg && !this.marsMsg.innerHTML.trim()) {
        this.marsMsg.innerHTML = "<span></span>";
        this.marsMsg.style.display = "none";
      }

      this.current = {
        scope: "global",       // planet | country | region | city | global
        country: SES.country,
        country_name: "",
        region: SES.region,
        city: SES.city,
      };

      this.init();
    }

    async init() {
      try {
        const res = await API.getProviders({
          country: this.current.country,
          region:  this.current.region,
          city:    this.current.city,
        });

        if (res && res.success && res.data) {
          const items = Array.isArray(res.data.providers) ? res.data.providers : [];
          const level = res.data.level || "global";
          const loc   = res.data.location || {};
          this.current.scope        = level;
          this.current.country      = safe(loc.country_code, this.current.country);
          this.current.country_name = safe(loc.country_name, "");
          this.current.region       = safe(loc.region, this.current.region);
          this.current.city         = safe(loc.city, this.current.city);
          this.renderBreadcrumb();
          this.renderResults(items);
        } else {
          this.renderBreadcrumb();
          this.renderResults([]);
        }

        this.wireBreadcrumb();
      } catch (e) {
        console.error(e);
        this.showError(tr("error_loading"));
        this.renderBreadcrumb();
        this.renderResults([]);
        this.wireBreadcrumb();
      }
    }

    wireBreadcrumb() {
      this.breadcrumb.addEventListener("click", async (e) => {
        const el = e.target.closest("[data-bc]");
        if (!el) return;
        const bc = el.getAttribute("data-bc");

        try {
          if (bc === "planet") {
            await this.goPlanet();
          } else if (bc === "country") {
            this.current.scope = "country";
            this.current.region = "";
            this.current.city = "";
            await this.fetchAndRender();
          } else if (bc === "region") {
            this.current.scope = "region";
            this.current.city = "";
            await this.fetchAndRender();
          } else if (bc === "more-countries") {
            await this.openCountryList();
          } else if (bc === "more-regions") {
            if (!this.current.country) return;
            await this.openRegionList(this.current.country);
          } else if (bc === "more-cities") {
            if (!this.current.country || !this.current.region) return;
            await this.openCityList(this.current.country, this.current.region);
          }
        } catch (err) {
          console.error(err);
          this.showError(err.message || tr("error_loading"));
        }
      });
    }

    async goPlanet() {
      this.current.scope = "planet";
      this.current.country = "";
      this.current.country_name = "";
      this.current.region = "";
      this.current.city = "";
      await this.fetchAndRender();
    }

    async fetchAndRender() {
      const res = await API.getProviders({
        country: this.current.country,
        region:  this.current.region,
        city:    this.current.city,
      });
      const items = res?.data?.providers || [];
      const level = res?.data?.level || "global";
      const loc   = res?.data?.location || {};
      this.current.scope        = level;
      this.current.country      = safe(loc.country_code, this.current.country);
      this.current.country_name = safe(loc.country_name, this.current.country_name);
      this.current.region       = safe(loc.region, this.current.region);
      this.current.city         = safe(loc.city, this.current.city);
      this.renderBreadcrumb();
      this.renderResults(items);
    }

    // ---------- Breadcrumb ----------
    renderBreadcrumb() {
      const parts = [];

      // Terra
      parts.push(html`<button type="button" data-bc="planet" class="bc-level bc-planet">${tr("breadcrumb.earth")}</button>`);

      if (this.current.country) {
        parts.push(html`<button type="button" data-bc="country" class="bc-level bc-country">
          ${this.current.country_name || this.current.country}
        </button>
        <button type="button" data-bc="more-countries" class="bc-more">${tr("breadcrumb.more")}</button>`);
      } else {
        parts.push(html`<button type="button" data-bc="more-countries" class="bc-more">${tr("breadcrumb.more")}</button>`);
      }

      if (this.current.region) {
        parts.push(html`<button type="button" data-bc="region" class="bc-level bc-region">${this.current.region}</button>
        <button type="button" data-bc="more-regions" class="bc-more">${tr("breadcrumb.more")}</button>`);
      }

      if (this.current.city) {
        parts.push(html`<span class="bc-level bc-city" data-bc="city">${this.current.city}</span>
        <button type="button" data-bc="more-cities" class="bc-more">${tr("breadcrumb.more")}</button>`);
      }

      this.breadcrumb.innerHTML = parts.join("");
    }

    // ---------- Modal lists ----------
    async openCountryList() {
      try {
        const res = await API.listCountries();
        const rows = res?.data || [];
        const items = rows.map((r) => {
          const iso = safe(r.iso_code);
          const name = safe(r.name, iso);
          const cnt = r.provider_count ?? 0;
          return html`<li>
            <button type="button" data-country="${iso}" class="pick-country">
              <span class="name">${name}</span>
              <span class="count">${cnt}</span>
            </button>
          </li>`;
        }).join("");
        modal.show(tr("breadcrumb.more"), `<ul class="list-pick">${items}</ul>`);
        $("#locBody").addEventListener("click", async (e) => {
          const btn = e.target.closest(".pick-country");
          if (!btn) return;
          this.current.country = btn.getAttribute("data-country");
          this.current.region = "";
          this.current.city = "";
          modal.hide();
          await this.fetchAndRender();
        }, { once: true });
      } catch (e) {
        this.showError(tr("error_loading"));
      }
    }

    async openRegionList(country) {
      try {
        const res = await API.listRegions(country);
        const rows = res?.data || [];
        const items = rows.map((r) => {
          const name = safe(r.name);
          const cnt  = r.provider_count ?? 0;
          return html`<li>
            <button type="button" data-region="${name}" class="pick-region">
              <span class="name">${name}</span>
              <span class="count">${cnt}</span>
            </button>
          </li>`;
        }).join("");
        modal.show(tr("breadcrumb.more"), `<ul class="list-pick">${items}</ul>`);
        $("#locBody").addEventListener("click", async (e) => {
          const btn = e.target.closest(".pick-region");
          if (!btn) return;
          this.current.region = btn.getAttribute("data-region");
          this.current.city = "";
          modal.hide();
          await this.fetchAndRender();
        }, { once: true });
      } catch (e) {
        this.showError(tr("error_loading"));
      }
    }

    async openCityList(country, region) {
      try {
        const res = await API.listCities(country, region);
        const rows = res?.data || [];
        const items = rows.map((r) => {
          const name = safe(r.name);
          const cnt  = r.provider_count ?? 0;
          return html`<li>
            <button type="button" data-city="${name}" class="pick-city">
              <span class="name">${name}</span>
              <span class="count">${cnt}</span>
            </button>
          </li>`;
        }).join("");
        modal.show(tr("breadcrumb.more"), `<ul class="list-pick">${items}</ul>`);
        $("#locBody").addEventListener("click", async (e) => {
          const btn = e.target.closest(".pick-city");
          if (!btn) return;
          this.current.city = btn.getAttribute("data-city");
          modal.hide();
          await this.fetchAndRender();
        }, { once: true });
      } catch (e) {
        this.showError(tr("error_loading"));
      }
    }

    // ---------- Grid ----------
    renderResults(items) {
      if (!Array.isArray(items)) items = [];
      if (items.length === 0) {
        this.grid.innerHTML = `<div class="empty">${tr("error_loading", "Sem resultados no momento.")}</div>`;
        return;
      }
      const cards = items.map((it) => this.card(it)).join("");
      this.grid.innerHTML = cards;
    }

    card(it) {
      const name = safe(it.name, "—");
      const city = safe(it.city, "");
      const region = safe(it.region, "");
      const country = safe(it.country_name || it.country_code || "", "");
      const price = it.price != null ? it.price : null;
      const currency = safe(it.currency, "");
      const curIcon = safe(it.currencies_icon, "");
      const img = safe(it.image_url, "");

      const priceHTML = price != null
        ? html`<div class="price">${curIcon || ""} ${price} <span>${tr("hour")}</span></div>`
        : html`<div class="price muted">${tr("priceNotInformed")}</div>`;

      const location = [city, region, country].filter(Boolean).join(" · ");

      return html`<article class="provider-card">
        <a class="thumb" href="${it.slug ? "/p/" + encodeURIComponent(it.slug) : "javascript:void(0)"}" aria-label="${name}">
          <img loading="lazy" src="${img || IMG_FALLBACK}" alt="${name}"
               onerror="this.onerror=null;this.src='${IMG_FALLBACK}'" />
          ${it.spotlight_level > 0 ? `<span class="badge badge-spot">${it.spotlight_level}</span>` : ""}
        </a>
        <div class="meta">
          <h3 class="name">${name}</h3>
          <div class="loc">${location}</div>
          ${priceHTML}
        </div>
      </article>`;
    }

    // ---------- Errors ----------
    showError(msg) {
      if (!this.marsMsg) return;
      this.marsMsg.querySelector("span").textContent = msg || tr("error_loading");
      this.marsMsg.style.display = "block";
      setTimeout(() => (this.marsMsg.style.display = "none"), 4000);
    }
  }

  // init
  document.addEventListener("DOMContentLoaded", () => new ResultsPage());
})();
