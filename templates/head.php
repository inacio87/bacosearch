<?php
/**
 * templates/head.php — pronto para produção (seguro e performático)
 * Última atualização: 15/08/2025
 */

if (!defined('SITE_URL')) { exit; }
if (ob_get_level() === 0) { ob_start(); }

/* Idioma normalizado */
$language_code = strtolower(str_replace('_','-', $language_code ?? ($_SESSION['language'] ?? 'en-us')));

/* Versão de assets (cache busting) — defina ASSET_VERSION no deploy */
$asset_version = defined('ASSET_VERSION') ? ASSET_VERSION : (getenv('ASSET_VERSION') ?: date('Ymd'));

/* Helper para versionar URLs de assets locais */
$addVersion = static function(string $url) use ($asset_version): string {
  if ($asset_version === '' || $asset_version === null) return $url;
  $sep = (strpos($url, '?') !== false) ? '&' : '?';
  return $url . $sep . 'v=' . rawurlencode((string)$asset_version);
};

/* Robots dinâmico para páginas que não devem indexar */
$noindex_pages = [
  'search_results','results_providers_page','results_business_page','results_clubs_page',
  'results_services_page','results_streets_page','login_page'
];
$robots = (!empty($page_name) && in_array($page_name, $noindex_pages, true)) ? 'noindex, nofollow' : 'index, follow';

/* Nonce para CSP (caso use header CSP com script-src 'nonce-...') */
if (empty($_SESSION['csp_nonce'])) {
  try { $_SESSION['csp_nonce'] = bin2hex(random_bytes(16)); }
  catch (Throwable $e) { $_SESSION['csp_nonce'] = bin2hex(openssl_random_pseudo_bytes(16)); }
}
$csp_nonce = $_SESSION['csp_nonce'] ?? null;

/* Caminho atual e canônica */
$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$canonical_url = htmlspecialchars(SITE_URL . ($current_path ?: '/'), ENT_QUOTES, 'UTF-8');

/* Fallbacks e metadados */
$site_name        = $page_title ?? getTranslation('site_name', $language_code, 'default');
$meta_description = $meta_description ?? '';
$meta_keywords    = $meta_keywords ?? '';
$meta_author      = $meta_author ?? '';
$theme_color      = $theme_color ?? '#0ea5e9';
$twitter_handle   = $twitter_handle ?? null;

$og_locale = str_replace('-', '_', strtolower($language_code));
$og_image  = SITE_URL . '/assets/images/social-share.png';

$available_languages = $available_languages ?? []; // ex.: ['en-us'=>'English','pt-pt'=>'Português']

/* CDN externo opcional com SRI (preencha $fontawesome_sri se quiser usar SRI) */
$fontawesome_cdn = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css';
$fontawesome_sri = $fontawesome_sri ?? null;
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($language_code, ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="<?php echo htmlspecialchars($robots, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="referrer" content="strict-origin-when-cross-origin">
  <meta name="color-scheme" content="light dark">
  <meta name="theme-color" content="<?php echo htmlspecialchars($theme_color, ENT_QUOTES, 'UTF-8'); ?>">

  <title><?php echo htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="author" content="<?php echo htmlspecialchars($meta_author, ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="canonical" href="<?php echo $canonical_url; ?>">

  <!-- Open Graph / Twitter -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?php echo $canonical_url; ?>">
  <meta property="og:site_name" content="<?php echo htmlspecialchars(getTranslation('site_name', $language_code, 'default'), ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:locale" content="<?php echo htmlspecialchars($og_locale, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:title" content="<?php echo htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:description" content="<?php echo htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:image" content="<?php echo htmlspecialchars($og_image, ENT_QUOTES, 'UTF-8'); ?>">

  <meta name="twitter:card" content="summary_large_image">
  <?php if (!empty($twitter_handle)) : ?>
  <meta name="twitter:site" content="@<?php echo htmlspecialchars(ltrim($twitter_handle, '@'), ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>

  <!-- Hreflang (se disponível) -->
  <?php if (!empty($available_languages) && is_array($available_languages)) :
    foreach ($available_languages as $lang_code => $label) :
      $lang_code = strtolower(str_replace('_','-',(string)$lang_code));
      $href = rtrim(SITE_URL,'/') . '/' . $lang_code . ($current_path ?: '/');
  ?>
    <link rel="alternate" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" hreflang="<?php echo htmlspecialchars($lang_code, ENT_QUOTES, 'UTF-8'); ?>">
  <?php endforeach; endif; ?>

  <!-- Ícones -->
  <link rel="icon" href="<?php echo htmlspecialchars($addVersion(SITE_URL . '/assets/images/icon.png'), ENT_QUOTES, 'UTF-8'); ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($addVersion(SITE_URL . '/assets/images/icon.png'), ENT_QUOTES, 'UTF-8'); ?>">

  <!-- Preconnect/DNS Prefetch do CDN -->
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">

  <!-- CSS base -->
  <link rel="stylesheet" href="<?php echo htmlspecialchars($addVersion(SITE_URL . '/assets/css/variables.css'), ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="<?php echo htmlspecialchars($addVersion(SITE_URL . '/assets/css/style.css'), ENT_QUOTES, 'UTF-8'); ?>">

  <!-- Font Awesome (CDN) -->
  <link rel="stylesheet"
        href="<?php echo htmlspecialchars($fontawesome_cdn, ENT_QUOTES, 'UTF-8'); ?>"
        <?php if ($fontawesome_sri) : ?>
        integrity="<?php echo htmlspecialchars($fontawesome_sri, ENT_QUOTES, 'UTF-8'); ?>"
        <?php endif; ?>
        crossorigin="anonymous" referrerpolicy="no-referrer">

<?php
  /* CSS específicos por página (mantidos e versionados) */
  $page_specific_styles = $page_specific_styles ?? [];
  if (!empty($page_name)) {
    switch ($page_name) {
      case 'home':
        $page_specific_styles[] = SITE_URL . '/assets/css/index.css'; break;
      case 'search_results':
      case 'results_providers_page':
        $page_specific_styles[] = SITE_URL . '/assets/css/search-providers.css'; break;
      case 'results_business_page':
      case 'results_clubs_page':
      case 'results_services_page':
      case 'results_streets_page':
        $page_specific_styles[] = SITE_URL . '/assets/css/search-generic.css'; break;
      case 'provider_profile':
        $page_specific_styles[] = SITE_URL . '/assets/css/providers.css'; break;
      case 'provider_registration_flow':
        $page_specific_styles[] = SITE_URL . '/assets/css/register.css';
        $page_specific_styles[] = SITE_URL . '/assets/css/provider-form.css'; break;
      case 'license_landing':
        $page_specific_styles[] = SITE_URL . '/assets/css/license.css'; break;
      case 'about_us_page':
      case 'login_page':
      case 'terms_of_service_page':
      case 'privacy_policy_page':
      case 'cookie_policy_page':
      case 'contact_page':
        $page_specific_styles[] = SITE_URL . '/assets/css/pages.css'; break;
    }
  }
  foreach (array_unique($page_specific_styles) as $style) {
    $href = $addVersion($style);
    echo '<link rel="stylesheet" href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">'.PHP_EOL;
  }

  /* Prepara objeto seguro para o front-end */
  $appConfig = [
    'site_url'     => SITE_URL,
    'visitor_id'   => $_SESSION['visitor_db_id'] ?? null,
    /* Evite expor o ID real da sessão em produção.
       Habilite esta linha apenas se você souber o que está fazendo. */
    'session_id'   => (defined('EXPOSE_SESSION_ID') && EXPOSE_SESSION_ID === true) ? session_id() : null,
    'latitude'     => $_SESSION['latitude'] ?? null,
    'longitude'    => $_SESSION['longitude'] ?? null,
    'city'         => $_SESSION['city'] ?? getTranslation('unknown_city_text', $language_code, 'ui_messages'),
    'region'       => $_SESSION['region'] ?? null,
    'country_code' => $_SESSION['country_code'] ?? null,
    'language'     => $language_code,
    'translations' => $translations ?? []
  ];
  $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
             | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

  <!-- Config JS (com nonce para CSP) -->
  <script <?php echo $csp_nonce ? 'nonce="'.htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8').'"' : ''; ?>>
    window.appConfig = <?php echo json_encode($appConfig, $jsonFlags); ?>;
  </script>

  <!-- Scripts base -->
  <script src="<?php echo htmlspecialchars($addVersion(SITE_URL . '/assets/js/utils.js'), ENT_QUOTES, 'UTF-8'); ?>" defer <?php echo $csp_nonce ? 'nonce="'.htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8').'"' : ''; ?>></script>
  <script src="<?php echo htmlspecialchars($addVersion(SITE_URL . '/assets/js/geolocation.js'), ENT_QUOTES, 'UTF-8'); ?>" defer <?php echo $csp_nonce ? 'nonce="'.htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8').'"' : ''; ?>></script>
  <script src="<?php echo htmlspecialchars($addVersion(SITE_URL . '/assets/js/menu-dropdown.js'), ENT_QUOTES, 'UTF-8'); ?>" defer <?php echo $csp_nonce ? 'nonce="'.htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8').'"' : ''; ?>></script>
  <script src="<?php echo htmlspecialchars($addVersion(SITE_URL . '/assets/js/language-selector.js'), ENT_QUOTES, 'UTF-8'); ?>" defer <?php echo $csp_nonce ? 'nonce="'.htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8').'"' : ''; ?>></script>

<?php
  /* Scripts específicos por página (mantidos, versionados e sem duplicar) */
  $page_specific_scripts = $page_specific_scripts ?? [];
  if (!empty($page_name)) {
    switch ($page_name) {
      case 'home':
        $page_specific_scripts[] = ['src' => SITE_URL . '/assets/js/search-enhanced.js', 'attrs' => ['defer']]; break;
      case 'results_providers_page':
        $page_specific_scripts[] = ['src' => SITE_URL . '/assets/js/providers.js', 'attrs' => ['defer']]; break;
      case 'results_business_page':
        $page_specific_scripts[] = ['src' => SITE_URL . '/assets/js/businesses.js', 'attrs' => ['defer']]; break;
      case 'results_clubs_page':
        $page_specific_scripts[] = ['src' => SITE_URL . '/assets/js/clubs.js', 'attrs' => ['defer']]; break;
      case 'results_services_page':
        $page_specific_scripts[] = ['src' => SITE_URL . '/assets/js/services.js', 'attrs' => ['defer']]; break;
      case 'results_streets_page':
        $page_specific_scripts[] = ['src' => SITE_URL . '/assets/js/streets.js', 'attrs' => ['defer']]; break;
      case 'provider_registration_flow':
        $page_specific_scripts[] = ['src' => SITE_URL . '/assets/js/provider-form-validation.js', 'attrs' => ['defer']]; break;
      case 'license_calculator':
        $page_specific_scripts[] = ['src' => SITE_URL . '/assets/js/license-calculator.js', 'attrs' => ['defer']]; break;
    }
  }
  $loaded = [];
  foreach ($page_specific_scripts as $js) {
    $src = $js['src']; if (in_array($src, $loaded, true)) continue;
    $attrs = empty($js['attrs']) ? '' : ' '.implode(' ', $js['attrs']);
    $src_v = $addVersion($src);
    $nonce_attr = $csp_nonce ? ' nonce="'.htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8').'"' : '';
    echo '<script src="'.htmlspecialchars($src_v, ENT_QUOTES, 'UTF-8').'"'.$attrs.$nonce_attr.'></script>'.PHP_EOL;
    $loaded[] = $src;
  }

  /* JSON-LD básico do WebSite (ajuste SEARCH_URL se necessário) */
  $search_url = defined('SEARCH_URL') ? SEARCH_URL : (rtrim(SITE_URL,'/').'/search?q={search_term_string}');
  $schemaWebsite = [
    '@context' => 'https://schema.org',
    '@type'    => 'WebSite',
    'name'     => $site_name,
    'url'      => SITE_URL,
    'inLanguage' => $language_code,
    'potentialAction' => [
      '@type' => 'SearchAction',
      'target' => $search_url,
      'query-input' => 'required name=search_term_string'
    ],
  ];
?>
  <script type="application/ld+json" <?php echo $csp_nonce ? 'nonce="'.htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8').'"' : ''; ?>>
    <?php echo json_encode($schemaWebsite, $jsonFlags); ?>
  </script>
</head>
<body class="<?php echo htmlspecialchars($page_name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
