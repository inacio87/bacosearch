<?php
/**
 * /pages/license.php - VERSÃO FINAL E TOTALMENTE COMPATÍVEL
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';

$page_name = 'license_landing';
$languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : (isset(LANGUAGE_CONFIG['default']) ? LANGUAGE_CONFIG['default'] : 'en-us');

$errors = isset($_SESSION['errors_license']) ? $_SESSION['errors_license'] : [];
$form_data = isset($_SESSION['form_data_license']) ? $_SESSION['form_data_license'] : [];
unset($_SESSION['errors_license'], $_SESSION['form_data_license']);

$show_calculator_feedback_success_modal = isset($_GET['status']) && $_GET['status'] === 'feedback_completed';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Lógica de Validação de Token de Acesso (mantida como está, pois usa funções compatíveis)
// ...

$page_specific_styles = [SITE_URL . '/assets/css/license.css'];

$translations = [];
$keys_to_translate = [
    'license_page_title', 'license_page_description', 'license_hero_title', 'license_hero_subtitle', 'license_hero_social_proof',
    'license_form_title', 'license_form_name_placeholder', 'license_form_email_placeholder',
    'license_form_cta_button', 'license_form_notice', 'form_errors_title',
    'impact_title', 'impact_1_number', 'impact_1_text', 'impact_2_number', 'impact_2_text',
    'impact_3_number', 'impact_3_text', 'impact_4_number', 'impact_4_text',
    'benefits_title', 'benefits_subtitle', 'benefit_1_title', 'benefit_1_text', 'benefit_2_title', 'benefit_2_text',
    'benefit_3_title', 'benefit_3_text', 'benefit_4_title', 'benefit_4_text', 'benefit_5_title', 'benefit_5_text',
    'benefit_6_title', 'benefit_6_text',
    'timeline_title', 'timeline_1_title', 'timeline_1_text', 'timeline_2_title', 'timeline_2_text',
    'timeline_3_title', 'timeline_3_text', 'timeline_4_title', 'timeline_4_text',
    'included_title', 'included_item_1', 'included_item_2', 'included_item_3',
    'included_item_4', 'included_item_5', 'included_item_6',
    'license_faq_title', 'license_faq1_q', 'license_faq1_a', 'license_faq2_q', 'license_faq2_a', 'license_faq3_q', 'license_faq3_a', 'license_faq4_q', 'license_faq4_a', 'license_faq5_q', 'license_faq5_a', 'license_faq6_q', 'license_faq6_a', 'license_faq7_q', 'license_faq7_a', 'license_faq8_q', 'license_faq8_a', 'license_faq9_q', 'license_faq9_a', 'license_faq10_q', 'license_faq10_a',
    'final_cta_title', 'final_cta_subtitle', 'final_cta_urgency', 'final_cta_button', 'license_final_quote',
    'license_modal_success_title', 'license_modal_success_body', 'license_modal_success_spam', 'license_modal_close_button',
    'license_feedback_completed_title', 'license_feedback_completed_body',
    'logo_alt', 'header_ads', 'header_login', 'header_menu', 'about_us',
    'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us', 'header_licenses',
    'footer_providers', 'footer_companies', 'footer_services','footer_clubs', 'footer_streets', 'detecting_location'
];

foreach ($keys_to_translate as $key) {
    $context = 'license_page';
    if (strpos($key, 'header_') === 0 || in_array($key, ['logo_alt', 'about_us', 'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us', 'header_licenses'])) {
        $context = 'header';
    } elseif (strpos($key, 'footer_') === 0) {
        $context = 'footer';
    } elseif (strpos($key, '_error') !== false || strpos($key, 'form_errors_') === 0) {
        $context = 'validation_errors';
    } elseif (strpos($key, 'license_modal_') === 0 || strpos($key, 'license_feedback_completed_') === 0) {
        $context = 'ui_messages';
    } elseif ($key === 'detecting_location') {
        $context = 'ui_messages';
    }
    $translations[$key] = getTranslation($key, $languageCode, $context);
}

$city = isset($_SESSION['city']) ? $_SESSION['city'] : (isset($translations['detecting_location']) ? $translations['detecting_location'] : '');
$langNameMap = isset(LANGUAGE_CONFIG['name_map']) ? LANGUAGE_CONFIG['name_map'] : [];
$currentLangName = isset($langNameMap[$languageCode]) ? $langNameMap[$languageCode] : getTranslation('language_label', $languageCode, 'default');
$translations['languageOptionsForDisplay'] = $langNameMap;
$translations['current_language_display_name'] = $currentLangName;

// AJUSTADO: Usa o nosso padrão de fallback para a chave
$page_title = !empty($translations['license_page_title']) ? $translations['license_page_title'] : 'license_page_title';
$meta_description = !empty($translations['license_page_description']) ? $translations['license_page_description'] : (isset(SEO_CONFIG['meta_description']) ? SEO_CONFIG['meta_description'] : '');
$meta_keywords = isset(SEO_CONFIG['meta_keywords']) ? SEO_CONFIG['meta_keywords'] : '';
$meta_author_fallback = 'BacoSearch';
$meta_author = isset(SEO_CONFIG['meta_author']) ? SEO_CONFIG['meta_author'] : $meta_author_fallback;

require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';

$e = function($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>

<main>
    <div class="license-page">
        <section class="hero-section" id="form-section">
            <div class="container">
                <h1><?= $e(isset($translations['license_hero_title']) ? $translations['license_hero_title'] : 'license_hero_title'); ?></h1>
                <p class="subtitle"><?= $e(isset($translations['license_hero_subtitle']) ? $translations['license_hero_subtitle'] : 'license_hero_subtitle'); ?></p>
                <p class="social-proof"><?= $e(isset($translations['license_hero_social_proof']) ? $translations['license_hero_social_proof'] : 'license_hero_social_proof'); ?></p>
                <div class="form-container">
                    <h2><?= $e(isset($translations['license_form_title']) ? $translations['license_form_title'] : 'license_form_title'); ?></h2>
                    <?php if (!empty($errors)): ?>
                        <div class="error-message show">
                            <strong><?= $e(isset($translations['form_errors_title']) ? $translations['form_errors_title'] : 'form_errors_title'); ?></strong>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $e($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <form id="license-form" action="<?= $e(SITE_URL . '/api/process_license_lead.php'); ?>" method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $e(isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''); ?>">
                        <div class="form-group honeypot-field" style="display:none;" aria-hidden="true">
                            <label for="meu_campo_secreto_license"><?= $e(getTranslation('honeypot_label', $languageCode, 'ui_messages')); ?></label>
                            <input type="text" id="meu_campo_secreto_license" name="meu_campo_secreto_license" tabindex="-1" autocomplete="off">
                        </div>
                        <input type="text" name="name" placeholder="<?= $e(isset($translations['license_form_name_placeholder']) ? $translations['license_form_name_placeholder'] : 'license_form_name_placeholder'); ?>" value="<?= $e(isset($form_data['name']) ? $form_data['name'] : ''); ?>" required class="form-control">
                        <input type="email" name="email" placeholder="<?= $e(isset($translations['license_form_email_placeholder']) ? $translations['license_form_email_placeholder'] : 'license_form_email_placeholder'); ?>" value="<?= $e(isset($form_data['email']) ? $form_data['email'] : ''); ?>" required class="form-control">
                        <button type="submit" class="btn-primary"><?= $e(isset($translations['license_form_cta_button']) ? $translations['license_form_cta_button'] : 'license_form_cta_button'); ?></button>
                    </form>
                    <p class="form-notice"><?= $e(isset($translations['license_form_notice']) ? $translations['license_form_notice'] : 'license_form_notice'); ?></p>
                </div>
            </div>
        </section>
        </div>
</main>
<?php
require_once TEMPLATE_PATH . 'footer.php';
?>