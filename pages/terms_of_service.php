<?php
/**
 * /pages/terms_of_service.php - VERSÃO FINAL E TOTALMENTE COMPATÍVEL
 */

// PASSO 1: INICIALIZAÇÃO CENTRAL
require_once dirname(__DIR__) . '/core/bootstrap.php';

$page_name = 'terms_of_service_page';

// PASSO 2: PREPARAÇÃO DE DADOS E TRADUÇÕES
$languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : (isset(LANGUAGE_CONFIG['default']) ? LANGUAGE_CONFIG['default'] : 'en-us');
$translationContext = 'legal_pages';

$page_specific_styles = [
    SITE_URL . '/assets/css/pages.css'
];

$translations = [];
$keys_to_translate = [
    'terms_of_service_title', 'terms_of_service_meta_description', 'terms_of_service_meta_keywords',
    'privacy_policy_last_updated', // Reutilizando a chave da data (pode criar uma nova se preferir)
    'terms_intro', 'terms_section1_title', 'terms_section1_text', 'terms_section2_title',
    'terms_section2_text', 'terms_section3_title', 'terms_section3_text', 'terms_section4_title',
    'terms_section4_text',
    // Reutilizados
    'header_ads', 'header_login', 'logo_alt', 'header_menu', 'about_us',
    'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us', 'header_licenses',
    'footer_providers', 'footer_companies', 'footer_services', 'footer_clubs', 'footer_streets',
    'detecting_location'
];

foreach ($keys_to_translate as $key) {
    $context = $translationContext;
    if (strpos($key, 'header_') === 0 || in_array($key, ['logo_alt', 'about_us', 'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us'])) {
        $context = 'header';
    } elseif (strpos($key, 'footer_') === 0) {
        $context = 'footer';
    } elseif ($key === 'detecting_location') {
        $context = 'ui_messages';
    } elseif ($key === 'privacy_policy_last_updated') {
        // A chave da data está no contexto 'legal_pages' (já que é genérica para estas páginas)
        $context = 'legal_pages';
    }
    $translations[$key] = getTranslation($key, $languageCode, $context);
}

// Prepara dados para os templates
$city_from_translations = isset($translations['detecting_location']) ? $translations['detecting_location'] : '';
$city = isset($_SESSION['city']) ? $_SESSION['city'] : $city_from_translations;
$langNameMap = isset(LANGUAGE_CONFIG['name_map']) ? LANGUAGE_CONFIG['name_map'] : [];
$currentLangName = isset($langNameMap[$languageCode]) ? $langNameMap[$languageCode] : getTranslation('language_label', $languageCode, 'default');
$translations['languageOptionsForDisplay'] = $langNameMap;
$translations['current_language_display_name'] = $currentLangName;

// Prepara as meta tags para o <head>
$page_title = !empty($translations['terms_of_service_title']) ? $translations['terms_of_service_title'] : 'terms_of_service_title';
$meta_description_fallback = isset(SEO_CONFIG['meta_description']) ? SEO_CONFIG['meta_description'] : '';
$meta_description = !empty($translations['terms_of_service_meta_description']) ? $translations['terms_of_service_meta_description'] : $meta_description_fallback;
$meta_keywords_fallback = isset(SEO_CONFIG['meta_keywords']) ? SEO_CONFIG['meta_keywords'] : '';
$meta_keywords = !empty($translations['terms_of_service_meta_keywords']) ? $translations['terms_of_service_meta_keywords'] : $meta_keywords_fallback;
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
            <p class="last-updated"><?= $e(isset($translations['privacy_policy_last_updated']) ? $translations['privacy_policy_last_updated'] : ''); ?></p>
        </div>
        <div class="content-body policy-content">
            <p><?= $e(isset($translations['terms_intro']) ? $translations['terms_intro'] : ''); ?></p>

            <div class="policy-section">
                <h2><?= $e(isset($translations['terms_section1_title']) ? $translations['terms_section1_title'] : ''); ?></h2>
                <p><?= $e(isset($translations['terms_section1_text']) ? $translations['terms_section1_text'] : ''); ?></p>
            </div>

            <div class="policy-section">
                <h2><?= $e(isset($translations['terms_section2_title']) ? $translations['terms_section2_title'] : ''); ?></h2>
                <p><?= $e(isset($translations['terms_section2_text']) ? $translations['terms_section2_text'] : ''); ?></p>
            </div>

            <div class="policy-section">
                <h2><?= $e(isset($translations['terms_section3_title']) ? $translations['terms_section3_title'] : ''); ?></h2>
                <p><?= $e(isset($translations['terms_section3_text']) ? $translations['terms_section3_text'] : ''); ?></p>
            </div>

            <div class="policy-section">
                <h2><?= $e(isset($translations['terms_section4_title']) ? $translations['terms_section4_title'] : ''); ?></h2>
                <p><?= $e(isset($translations['terms_section4_text']) ? $translations['terms_section4_text'] : ''); ?> <a href="<?= htmlspecialchars(SITE_URL . '/pages/contact.php'); ?>"><?= $e(isset($translations['contact_us']) ? $translations['contact_us'] : ''); ?></a>.</p>
            </div>
        </div>
    </div>
</main>

<?php
require_once TEMPLATE_PATH . 'footer.php';
?>