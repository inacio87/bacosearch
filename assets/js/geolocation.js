/**
 * /assets/js/geolocation.js
 * Gerencia geolocalização do usuário no frontend (sem textos literais de UI).
 *
 * Mudanças (31/10/2025):
 * - NÃO pergunta geolocalização precisa no load.
 * - Pergunta só ao receber 'agegate:accepted' ou no clique do #locate-me-btn.
 * - Idempotente com cookies precise_loc_status / precise_loc_prompted.
 * - Usa Permissions API para evitar prompts desnecessários.
 *
 * Responsabilidades:
 * 1. Obtém localização precisa via navegador (somente quando solicitado).
 * 2. Fallback por IP no load para preencher cidade sem prompt.
 * 3. Envia coords para /api/geolocation.php e atualiza header (cidade).
 * 4. Integra com /api/check_session.php para atualizar sessão/idioma.
 * 5. Dispara evento 'locationUpdated' para sincronizar com outros scripts.
 * 6. Suporta botão manual (#locate-me-btn).
 */

(function () {
  // -------- helpers ambiente / cfg --------
  const appCfg = (typeof window !== "undefined" && window.appConfig) ? window.appConfig : {};
  const SITE_URL = (appCfg && typeof appCfg.site_url === "string") ? appCfg.site_url.replace(/\/+$/,'') : "";

  function normalizeLang(code) {
    if (!code) return null;
    return String(code).toLowerCase().replace('_','-');
  }

  function t(key, fallbackKey) {
    const dict = (appCfg && appCfg.translations) ? appCfg.translations : {};
    return (dict && dict[key]) || key || fallbackKey || key;
  }

  function currentLangGuess() {
    return (
      normalizeLang(appCfg.language) ||
      normalizeLang(document.documentElement.getAttribute('lang')) ||
      normalizeLang(navigator.language) ||
      'en-us'
    );
  }

  function updateCityDisplay(text) {
    const el = document.getElementById('city-display');
    if (!el) return;
    el.textContent = text;
  }

  // -------- helpers de cookie --------
  function getCookie(name) {
    return document.cookie.split('; ').reduce((acc, part) => {
      const [k, ...v] = part.split('=');
      return k === name ? decodeURIComponent(v.join('=')) : acc;
    }, undefined);
  }
  function setCookie(name, value, days) {
    const maxAge = days ? ';max-age=' + (days * 24 * 60 * 60) : '';
    const path   = ';path=/';
    const sameSite = ';SameSite=Lax';
    const secure = (location.protocol === 'https:') ? ';Secure' : '';
    document.cookie = name + '=' + encodeURIComponent(value) + maxAge + path + sameSite + secure;
  }

  // -------- ciclo principal --------
  document.addEventListener('DOMContentLoaded', () => {
    // 1) No load, usamos SOMENTE o fallback por IP (nunca pede permissão aqui).
    fallbackToIpLocation();

    // 2) Clique manual no botão: chama função idempotente (pode perguntar 1x).
    const locateButton = document.getElementById('locate-me-btn');
    if (locateButton) {
      locateButton.addEventListener('click', askForPreciseLocationOnce);
    }

    // 3) Ao aceitar o age gate (evento disparado pelo modal), tentamos pedir 1x.
    window.addEventListener('agegate:accepted', () => {
      askForPreciseLocationOnce();
    });
  });

  /**
   * Pergunta geolocalização do navegador de forma IDEMPOTENTE.
   * Regras:
   *  - Se já estiver 'granted' ou 'denied': não prompta.
   *  - Se já perguntamos nesta máquina (precise_loc_prompted=1): não prompta.
   *  - Caso contrário: prompta 1x e persiste resultado em cookies.
   */
  async function askForPreciseLocationOnce() {
    // Já decidida antes nesta máquina?
    const status = getCookie('precise_loc_status'); // 'granted'|'denied'
    if (status === 'granted' || status === 'denied') return false;

    // Já perguntamos uma vez (evita repetir durante recargas/fluxo)?
    if (getCookie('precise_loc_prompted') === '1') return false;

    // Tenta ler o estado via Permissions API para evitar prompt desnecessário
    try {
      if (navigator.permissions && navigator.permissions.query) {
        const perm = await navigator.permissions.query({ name: 'geolocation' });
        if (perm.state === 'granted') {
          setCookie('precise_loc_status', 'granted', 365);
          // Com permissão já concedida, basta ler 1x e enviar:
          return getPreciseNow();
        }
        if (perm.state === 'denied') {
          setCookie('precise_loc_status', 'denied', 365);
          return false;
        }
        // se 'prompt', seguimos para solicitar
      }
    } catch (e) { /* ignore */ }

    // Marca que já perguntamos (mesmo se o usuário fechar a caixa, não repetimos).
    setCookie('precise_loc_prompted', '1', 365);

    // Solicita geolocalização (se suportada)
    if ('geolocation' in navigator) {
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          setCookie('precise_loc_status', 'granted', 365);
          geolocationSuccess(pos);
        },
        () => {
          setCookie('precise_loc_status', 'denied', 365);
          // não força fallback aqui; já fizemos no load
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
      );
    } else {
      setCookie('precise_loc_status', 'denied', 365);
    }
    return true;
  }

  // Usa a permissão já concedida para ler coordenadas sem prompt (caso Permissions API diga 'granted')
  function getPreciseNow() {
    if (!('geolocation' in navigator)) return false;
    navigator.geolocation.getCurrentPosition(
      geolocationSuccess,
      /* onError */ () => { /* sem fallback aqui */ },
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
    return true;
  }

  function geolocationSuccess(position) {
    const coords = position.coords || {};
    fetchGeolocationAPI(coords);
  }

  /**
   * Envia coordenadas para /api/geolocation.php via POST JSON.
   */
  function fetchGeolocationAPI(coords) {
    const payload = {
      latitude: coords.latitude,
      longitude: coords.longitude,
      accuracy: coords.accuracy
    };

    fetch(SITE_URL + '/api/geolocation.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(r => (r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status))))
      .then(data => {
        if (data && data.success && data.data) {
          const dd = data.data;
          const cityText = dd.city || t('detecting_location', 'detecting_location');
          updateCityDisplay(cityText);

          const lang =
            normalizeLang(dd.language_code) ||
            currentLangGuess();

          const sessionData = Object.assign({}, dd, { language: lang });
          updateSession(sessionData);

          // evento para outros scripts
          window.dispatchEvent(new CustomEvent('locationUpdated', { detail: dd }));
        }
      })
      .catch(() => { /* silencioso */ });
  }

  /**
   * Fallback para localização baseada em IP via GET /api/geolocation.php?fallback=ip.
   * (Chamado no load para preencher cidade sem prompt.)
   */
  function fallbackToIpLocation() {
    fetch(SITE_URL + '/api/geolocation.php?fallback=ip', {
      method: 'GET',
      headers: { 'Accept': 'application/json' }
    })
      .then(r => (r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status))))
      .then(data => {
        if (data && data.success && data.data) {
          const dd = data.data;
          const cityText = dd.city || t('detecting_location', 'detecting_location');
          updateCityDisplay(cityText);

          const lang =
            normalizeLang(dd.language_code) ||
            currentLangGuess();

          const sessionData = Object.assign({}, dd, { language: lang });
          updateSession(sessionData);

          window.dispatchEvent(new CustomEvent('locationUpdated', { detail: dd }));
        } else {
          updateCityDisplay(t('detecting_location', 'detecting_location'));
        }
      })
      .catch(() => {
        updateCityDisplay(t('detecting_location', 'detecting_location'));
      });
  }

  /**
   * Atualiza a sessão via POST para /api/check_session.php.
   */
  function updateSession(locationData) {
    fetch(SITE_URL + '/api/check_session.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(locationData)
    })
      .then(r => (r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status))))
      .then(data => {
        if (data && data.success && data.data) {
          if (typeof window !== 'undefined') {
            window.appConfig = window.appConfig || {};
            window.appConfig.location = data.data;
            if (data.data.language) {
              window.appConfig.language = data.data.language;
            }
          }
        }
      })
      .catch(() => { /* silencioso */ });
  }

  // export para outros scripts; agora é a versão idempotente
  window.askForPreciseLocation = askForPreciseLocationOnce;
})();
