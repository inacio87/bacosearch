<?php
/**
 * /pages/cookie_policy.php - VERSÃO FINAL E TOTALMENTE COMPATÍVEL
 */

// PASSO 1: INICIALIZAÇÃO CENTRAL
require_once dirname(__DIR__) . '/core/bootstrap.php';

$page_name = 'cookie_policy_page';

// PASSO 2: PREPARAÇÃO DE DADOS E TRADUÇÕES
$languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : (isset(LANGUAGE_CONFIG['default']) ? LANGUAGE_CONFIG['default'] : 'en-us');
$translationContext = 'legal_pages';

$page_specific_styles = [
    SITE_URL . '/assets/css/pages.css'
];

$translations = [];
$keys_to_translate = [
    'cookie_policy_title', 'cookie_policy_meta_description', 'cookie_policy_meta_keywords',
    'cookie_policy_p1', 'cookie_policy_what_h2', 'cookie_policy_what_p1', 'cookie_policy_how_h2',
    'cookie_policy_how_p1', 'cookie_policy_types_h3', 'cookie_policy_types_essential_h4',
    'cookie_policy_types_essential_p1', 'cookie_policy_types_performance_h4',
    'cookie_policy_types_performance_p1', 'cookie_policy_types_functionality_h4',
    'cookie_policy_types_functionality_p1', 'cookie_policy_types_thirdparty_h4',
    'cookie_policy_types_thirdparty_p1', 'cookie_policy_consent_h2', 'cookie_policy_consent_p1',
    'cookie_policy_manage_h2', 'cookie_policy_manage_p1', 'cookie_policy_list_intro_p',
    'cookie_policy_changes_h2', 'cookie_policy_changes_p1', 'cookie_policy_contact_h2',
    'cookie_policy_contact_p1',
    // Chaves reutilizadas
    'logo_alt', 'header_ads', 'header_login', 'header_menu', 'about_us',
    'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us',
    'footer_providers', 'footer_explore','footer_clubs',
    'footer_streets',
    'detecting_location'
];

foreach ($keys_to_translate as $key) {
    $context = $translationContext;
    // AJUSTADO: Usa strpos() para compatibilidade com PHP 7
    if (strpos($key, 'header_') === 0 || in_array($key, ['logo_alt', 'about_us', 'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us'])) {
        $context = 'header';
    } elseif (strpos($key, 'footer_') === 0) {
        $context = 'footer';
    } elseif ($key === 'detecting_location') {
        $context = 'ui_messages';
    }
    $translations[$key] = getTranslation($key, $languageCode, $context);
}

// Prepara dados que o header.php precisa.
$city_from_translations = isset($translations['detecting_location']) ? $translations['detecting_location'] : '';
$city = isset($_SESSION['city']) ? $_SESSION['city'] : $city_from_translations;

$langNameMap = isset(LANGUAGE_CONFIG['name_map']) ? LANGUAGE_CONFIG['name_map'] : [];
$currentLangName = isset($langNameMap[$languageCode]) ? $langNameMap[$languageCode] : getTranslation('language_label', $languageCode, 'default');
$translations['languageOptionsForDisplay'] = $langNameMap;
$translations['current_language_display_name'] = $currentLangName;

// Prepara as meta tags para o <head>.
$page_title = !empty($translations['cookie_policy_title']) ? $translations['cookie_policy_title'] : 'cookie_policy_title';
$meta_description_fallback = isset(SEO_CONFIG['meta_description']) ? SEO_CONFIG['meta_description'] : '';
$meta_description = !empty($translations['cookie_policy_meta_description']) ? $translations['cookie_policy_meta_description'] : $meta_description_fallback;
$meta_keywords_fallback = isset(SEO_CONFIG['meta_keywords']) ? SEO_CONFIG['meta_keywords'] : '';
$meta_keywords = !empty($translations['cookie_policy_meta_keywords']) ? $translations['cookie_policy_meta_keywords'] : $meta_keywords_fallback;
$meta_author_fallback = 'BacoSearch';
$meta_author = isset(SEO_CONFIG['meta_author']) ? SEO_CONFIG['meta_author'] : $meta_author_fallback;


// PASSO 3: RENDERIZAÇÃO DA PÁGINA
require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';

$e = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>

<main>
    <div class="static-content-wrapper">
        <div class="content-header">
            <h1><?= $e($page_title); ?></h1>
            <p class="last-updated"><?= $e(isset($translations['cookie_policy_p1']) ? $translations['cookie_policy_p1'] : ''); ?></p>
        </div>
        <div class="content-body policy-content">
            <h2><?= $e(isset($translations['cookie_policy_what_h2']) ? $translations['cookie_policy_what_h2'] : ''); ?></h2>
            <p><?= $e(isset($translations['cookie_policy_what_p1']) ? $translations['cookie_policy_what_p1'] : ''); ?></p>

            <h2><?= $e(isset($translations['cookie_policy_how_h2']) ? $translations['cookie_policy_how_h2'] : ''); ?></h2>
            <p><?= $e(isset($translations['cookie_policy_how_p1']) ? $translations['cookie_policy_how_p1'] : ''); ?></p>

            <h3><?= $e(isset($translations['cookie_policy_types_h3']) ? $translations['cookie_policy_types_h3'] : ''); ?></h3>

            <h4><?= $e(isset($translations['cookie_policy_types_essential_h4']) ? $translations['cookie_policy_types_essential_h4'] : ''); ?></h4>
            <p><?= $e(isset($translations['cookie_policy_types_essential_p1']) ? $translations['cookie_policy_types_essential_p1'] : ''); ?></p>

            <h4><?= $e(isset($translations['cookie_policy_types_performance_h4']) ? $translations['cookie_policy_types_performance_h4'] : ''); ?></h4>
            <p><?= $e(isset($translations['cookie_policy_types_performance_p1']) ? $translations['cookie_policy_types_performance_p1'] : ''); ?></p>

            <h4><?= $e(isset($translations['cookie_policy_types_functionality_h4']) ? $translations['cookie_policy_types_functionality_h4'] : ''); ?></h4>
            <p><?= $e(isset($translations['cookie_policy_types_functionality_p1']) ? $translations['cookie_policy_types_functionality_p1'] : ''); ?></p>

            <h4><?= $e(isset($translations['cookie_policy_types_thirdparty_h4']) ? $translations['cookie_policy_types_thirdparty_h4'] : ''); ?></h4>
            <p><?= $e(isset($translations['cookie_policy_types_thirdparty_p1']) ? $translations['cookie_policy_types_thirdparty_p1'] : ''); ?></p>

            <h2><?= $e(isset($translations['cookie_policy_consent_h2']) ? $translations['cookie_policy_consent_h2'] : ''); ?></h2>
            <p><?= $e(isset($translations['cookie_policy_consent_p1']) ? $translations['cookie_policy_consent_p1'] : ''); ?></p>

            <h2><?= $e(isset($translations['cookie_policy_manage_h2']) ? $translations['cookie_policy_manage_h2'] : ''); ?></h2>
            <p><?= $e(isset($translations['cookie_policy_manage_p1']) ? $translations['cookie_policy_manage_p1'] : ''); ?></p>

            <p><?= $e(isset($translations['cookie_policy_list_intro_p']) ? $translations['cookie_policy_list_intro_p'] : ''); ?></p>

            <h2><?= $e(isset($translations['cookie_policy_changes_h2']) ? $translations['cookie_policy_changes_h2'] : ''); ?></h2>
            <p><?= $e(isset($translations['cookie_policy_changes_p1']) ? $translations['cookie_policy_changes_p1'] : ''); ?></p>

            <h2><?= $e(isset($translations['cookie_policy_contact_h2']) ? $translations['cookie_policy_contact_h2'] : ''); ?></h2>
            <p><?= $e(str_replace('[seu_email@exemplo.com]', ADMIN_CONTACT_EMAIL, isset($translations['cookie_policy_contact_p1']) ? $translations['cookie_policy_contact_p1'] : '')); ?></p>
            <p><a href="<?= htmlspecialchars(SITE_URL . '/pages/contact.php'); ?>"><?= $e(isset($translations['contact_us']) ? $translations['contact_us'] : ''); ?></a>.</p>
        </div>
    </div>
</main>

<?php
require_once TEMPLATE_PATH . 'footer.php';
?>