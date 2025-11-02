<?php
/**
 * /auth/forgot_password.php - PÁGINA FINAL E TOTALMENTE COMPATÍVEL
 */

// PASSO 1: INICIALIZAÇÃO E REDIRECIONAMENTO
require_once dirname(__DIR__, 1) . '/core/bootstrap.php';

if (isset($_SESSION['admin_id']) || isset($_SESSION['account_id'])) {
    header('Location: ' . SITE_URL . '/');
    exit();
}

// PASSO 2: GERAÇÃO DE TOKEN E TRATAMENTO DE MENSAGENS
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$errors = isset($_SESSION['errors_forgot']) ? $_SESSION['errors_forgot'] : [];
$success_message = isset($_SESSION['success_forgot']) ? $_SESSION['success_forgot'] : null;
unset($_SESSION['errors_forgot'], $_SESSION['success_forgot']);

// PASSO 3: PREPARAÇÃO DE DADOS PARA A VIEW
$languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : (isset(LANGUAGE_CONFIG['default']) ? LANGUAGE_CONFIG['default'] : 'pt-br');
$city_from_translations = getTranslation('detecting_location', $languageCode, 'ui_messages');
$city = isset($_SESSION['city']) ? $_SESSION['city'] : $city_from_translations;

$page_specific_styles = [SITE_URL . '/assets/css/pages.css'];

$translations = [];
$keys_to_translate = [
    'forgot_password_title', 'forgot_password_subtitle', 'label_email', 'label_birth_date', 'button_send_reset_link', 'forgot_password_meta_description',
    'logo_alt', 'header_ads', 'header_login', 'header_menu', 'about_us', 'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us', 'header_licenses',
    'footer_providers', 'footer_companies', 'footer_clubs', 'footer_streets','footer_services', 'footer_explore'
];

foreach ($keys_to_translate as $key) {
    $context = 'forgot_password_page';
    if (strpos($key, 'header_') === 0 || in_array($key, ['logo_alt', 'about_us', 'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us'])) {
        $context = 'header';
    } elseif (strpos($key, 'footer_') === 0) {
        $context = 'footer';
    }
    $translations[$key] = getTranslation($key, $languageCode, $context);
}

$langNameMap = isset(LANGUAGE_CONFIG['name_map']) ? LANGUAGE_CONFIG['name_map'] : [];
$currentLangName = isset($langNameMap[$languageCode]) ? $langNameMap[$languageCode] : getTranslation('language_label', $languageCode, 'default');
$translations['languageOptionsForDisplay'] = $langNameMap;
$translations['current_language_display_name'] = $currentLangName;

$page_title = !empty($translations['forgot_password_title']) ? $translations['forgot_password_title'] : 'forgot_password_title';
$meta_description = !empty($translations['forgot_password_meta_description']) ? $translations['forgot_password_meta_description'] : (isset(SEO_CONFIG['meta_description']) ? SEO_CONFIG['meta_description'] : '');
$meta_keywords = isset(SEO_CONFIG['meta_keywords']) ? SEO_CONFIG['meta_keywords'] : '';
$meta_author = isset(SEO_CONFIG['meta_author']) ? SEO_CONFIG['meta_author'] : 'BacoSearch';

// PASSO 4: RENDERIZAÇÃO DA PÁGINA
require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';

$e = function($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>

<main>
    <div class="login-container">
        <div class="login-header">
            <h1><?= $e($page_title); ?></h1>
            <p><?= $e(isset($translations['forgot_password_subtitle']) ? $translations['forgot_password_subtitle'] : ''); ?></p>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="error-summary">
                <i class="fas fa-exclamation-circle"></i>
                <p><?= $e($errors['general']); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="success-summary">
                <i class="fas fa-check-circle"></i>
                <p><?= $e($success_message); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= $e(SITE_URL . '/auth/process_forgot_password.php'); ?>" class="login-form">
            <input type="hidden" name="csrf_token" value="<?= $e(isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''); ?>">
            
            <div class="form-group">
                <label for="email"><?= $e(isset($translations['label_email']) ? $translations['label_email'] : ''); ?></label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="birth_date"><?= $e(isset($translations['label_birth_date']) ? $translations['label_birth_date'] : ''); ?></label>
                <input type="date" id="birth_date" name="birth_date" required>
            </div>
            
            <div class="form-actions" style="justify-content: center;">
                <button type="submit" class="btn-primary"><?= $e(isset($translations['button_send_reset_link']) ? $translations['button_send_reset_link'] : ''); ?></button>
            </div>
        </form>
    </div>
</main>

<?php require_once TEMPLATE_PATH . 'footer.php'; ?>