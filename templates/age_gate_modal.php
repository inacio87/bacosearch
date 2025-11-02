<?php
/**
 * /templates/age_gate_modal.php
 * Versão final pronta para produção (A11y + CSP + i18n + cookies)
 * Última atualização: 15/08/2025
 */

if (!defined('SITE_URL')) { exit; }

/** Traduções */
require_once dirname(__DIR__) . '/core/i18n_layout.php';
$language_code          = $_SESSION['language'] ?? 'pt-br';
$header_translations    = loadHeaderTranslations($language_code) ?? [];
$age_gate_translations  = loadAgeGateTranslations($language_code) ?? [];

/** Helpers seguros */
$e = static function (?string $v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};

/** Versionamento opcional de assets (defina ASSET_VERSION no deploy) */
$asset_version = defined('ASSET_VERSION') ? ASSET_VERSION : (getenv('ASSET_VERSION') ?: '');
$addVersion = static function (string $url) use ($asset_version): string {
  if ($asset_version === '') return $url;
  $sep = (strpos($url, '?') !== false) ? '&' : '?';
  return $url . $sep . 'v=' . rawurlencode((string)$asset_version);
};

/** CSP nonce (compatível com o head.php sugerido) */
$csp_nonce = $_SESSION['csp_nonce'] ?? null;

/** Fallbacks de chaves para evitar notices */
$logo_alt                = $header_translations['logo_alt']                     ?? 'Logotipo';
$txt_terms               = $header_translations['terms_of_service']             ?? 'Termos de serviço';
$txt_privacy             = $header_translations['privacy_policy']               ?? 'Política de privacidade';
$txt_cookie              = $header_translations['cookie_policy']                ?? 'Política de cookies';
$txt_leave               = $header_translations['header_logout']                ?? 'Sair';

$ag_title                = $age_gate_translations['age_gate_title']             ?? 'Confirmação de idade';
$ag_p1                   = $age_gate_translations['age_gate_p1']                ?? 'Você deve confirmar sua idade para continuar.';
$ag_p2                   = $age_gate_translations['age_gate_p2']                ?? 'Ao continuar, você declara que possui a idade legal no seu país.';
$ag_enter                = $age_gate_translations['age_gate_enter_button']      ?? 'Entrar';
?>

<link rel="stylesheet" href="<?php echo $e($addVersion(SITE_URL . '/assets/css/age-gate-modal.css')); ?>">

<!--
  Importante:
  - Iniciamos oculto (display:none) para evitar FOUC; o JS mostra se o cookie não existir.
  - Usamos ARIA + foco preso para acessibilidade.
-->
<div class="age-gate-overlay" id="age-gate-overlay" style="display:none" role="dialog" aria-modal="true" aria-labelledby="age-gate-title" aria-describedby="age-gate-desc">
  <div class="age-gate-modal" id="age-gate-modal" tabindex="-1">
    <img src="<?php echo $e(SITE_URL . '/assets/images/logo.png'); ?>" alt="<?php echo $e($logo_alt); ?>" class="logo">

    <h2 id="age-gate-title"><?php echo $e($ag_title); ?></h2>

    <p id="age-gate-desc"><?php echo $e($ag_p1); ?></p>
    <p><?php echo $e($ag_p2); ?></p>

    <p class="terms">
      <a href="<?php echo $e(SITE_URL . '/pages/terms_of_service.php'); ?>" target="_blank" rel="noopener noreferrer"><?php echo $e($txt_terms); ?></a> |
      <a href="<?php echo $e(SITE_URL . '/pages/privacy_policy.php'); ?>" target="_blank" rel="noopener noreferrer"><?php echo $e($txt_privacy); ?></a> |
      <a href="<?php echo $e(SITE_URL . '/pages/cookie_policy.php'); ?>" target="_blank" rel="noopener noreferrer"><?php echo $e($txt_cookie); ?></a>
    </p>

    <div class="button-container">
      <button class="leave-button" id="age-gate-leave" type="button"><?php echo $e($txt_leave); ?></button>
      <button class="enter-button" id="age-gate-enter" type="button"><?php echo $e($ag_enter); ?></button>
    </div>
  </div>
</div>

<noscript>
  <style>
    /* Se JS estiver desabilitado, mostramos um aviso bloqueante minimalista */
    #age-gate-overlay{display:flex!important;align-items:center;justify-content:center;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:99999}
    #age-gate-modal{background:#fff;padding:1.25rem;max-width:520px;border-radius:12px}
  </style>
  <div id="age-gate-overlay" role="dialog" aria-modal="true">
    <div id="age-gate-modal">
      <p><?php echo $e($ag_p1 . ' ' . ($age_gate_translations['enable_js_message'] ?? 'Ative o JavaScript para continuar.')); ?></p>
    </div>
  </div>
</noscript>

<script <?php echo $csp_nonce ? 'nonce="' . $e($csp_nonce) . '"' : ''; ?>>
(function () {
  /** Utils de cookie robustos */
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

  var overlay     = document.getElementById('age-gate-overlay');
  var modal       = document.getElementById('age-gate-modal');
  var enterButton = document.getElementById('age-gate-enter');
  var leaveButton = document.getElementById('age-gate-leave');

  if (!overlay || !modal || !enterButton || !leaveButton) return;

  var previouslyFocused = null;

  function showGate() {
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    previouslyFocused = document.activeElement;
    modal.focus({preventScroll:true});
    trapFocus();
    overlay.setAttribute('aria-hidden', 'false');
  }
  function hideGate() {
    overlay.style.display = 'none';
    document.body.style.overflow = 'auto';
    overlay.setAttribute('aria-hidden', 'true');
    if (previouslyFocused && previouslyFocused.focus) {
      previouslyFocused.focus({preventScroll:true});
    }
  }

  /** Evita FOUC: só mostra se cookie não existir */
  if (!getCookie('age_verified_consent')) {
    showGate();
  }

  /** Clique em Entrar */
  var clicked = false;
  enterButton.addEventListener('click', function () {
    if (clicked) return;
    clicked = true;

    setCookie('age_verified_consent', 'true', 365);
    setCookie('consent_given', 'true', 365);

    hideGate();

    // Dispara evento para outras partes do app ouvirem
    try {
      var ev = new CustomEvent('agegate:accepted', { detail: { at: Date.now() } });
      window.dispatchEvent(ev);
    } catch (e) {}

    // Se existir função de localização precisa, chama
    if (typeof window.askForPreciseLocation === 'function') {
      try { window.askForPreciseLocation(); } catch (err) { /* noop */ }
    } else {
      // Fallback leve: recarrega apenas se necessário (evita loop)
      if (!sessionStorage.getItem('agegate_reloaded')) {
        sessionStorage.setItem('agegate_reloaded', '1');
        window.location.reload();
      }
    }
  });

  /** Clique em Sair */
  leaveButton.addEventListener('click', function () {
    // Você pode redirecionar para uma página interna de saída se preferir
    window.location.assign('https://www.google.com');
  });

  /** Fecha com ESC? — não. Mantemos bloqueio; ESC apenas move foco ao botão Sair */
  modal.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      leaveButton.focus();
    }
  });

  /** Impede fechar ao clicar fora */
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) {
      // Foco volta ao modal
      modal.focus();
    }
  });

  /** Trap de foco acessível (TAB/Shift+TAB) */
  function trapFocus() {
    var focusables = overlay.querySelectorAll('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])');
    focusables = Array.prototype.slice.call(focusables).filter(function (el) {
      return el.offsetParent !== null;
    });
    if (focusables.length === 0) return;
    var first = focusables[0], last = focusables[focusables.length - 1];

    overlay.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') return;
      if (e.shiftKey) {
        if (document.activeElement === first) {
          e.preventDefault();
          last.focus();
        }
      } else {
        if (document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    });
  }
})();
</script>
