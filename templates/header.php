<?php
/**
 * /templates/header.php
 * Cabeçalho visual (dinâmico, sem textos literais)
 * Última atualização: 15/08/2025
 */

// Helpers i18n do layout
if (!function_exists('loadHeaderTranslations')) {
    require_once dirname(__DIR__) . '/core/i18n_layout.php';
}

$languageCode = strtolower(str_replace('_','-', $_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us')));

// Traduções base do header
$headerTx = loadHeaderTranslations($languageCode);

// Mescla com $translations da página (se existir) para permitir override local
if (!isset($translations) || !is_array($translations)) {
    $translations = [];
}
$translations = array_merge($headerTx, $translations);

// Chaves extras usadas aqui
$translations['header_dashboard'] = $translations['header_dashboard'] ?? getTranslation('header_dashboard', $languageCode, 'header') ?? 'header_dashboard';
$translations['header_menu']      = $translations['header_menu']      ?? getTranslation('header_menu',      $languageCode, 'header') ?? 'header_menu';
$translations['header_login']     = $translations['header_login']     ?? getTranslation('header_login',     $languageCode, 'header') ?? 'header_login';
$translations['header_logout']    = $translations['header_logout']    ?? getTranslation('header_logout',    $languageCode, 'header') ?? 'header_logout';
$translations['header_ads']       = $translations['header_ads']       ?? getTranslation('header_ads',       $languageCode, 'header') ?? 'header_ads';
$translations['logo_alt']         = $translations['logo_alt']         ?? getTranslation('logo_alt',         $languageCode, 'header') ?? 'logo_alt';

// Nome dos idiomas para o dropdown
$languageNameMap = $translations['languageOptionsForDisplay'] ?? (LANGUAGE_CONFIG['name_map'] ?? []);
$currentLanguageDisplayName = $translations['current_language_display_name'] ?? ($languageNameMap[$languageCode] ?? strtoupper($languageCode));

// Cidade exibida (fallback: detecting_location)
$city = $city
    ?? $_SESSION['city']
    ?? (getTranslation('detecting_location', $languageCode, 'ui_messages') ?? 'detecting_location');

// Estado de sessão/logado
$is_logged_in  = isset($_SESSION['account_id']) || isset($_SESSION['admin_id']);
$dashboard_url = isset($_SESSION['admin_id'])
    ? SITE_URL . '/admin/dashboard.php'
    : (isset($_SESSION['account_id']) ? SITE_URL . '/dashboard.php' : '');

// Tooltip do botão de geolocalização
$use_precise_location_title = getTranslation('use_precise_location', $languageCode, 'ui_messages') ?? 'use_precise_location';
?>
<header class="site-header">
    <div class="header-left">
        <?php if ($is_logged_in): ?>
            <a href="<?= htmlspecialchars($dashboard_url, ENT_QUOTES, 'UTF-8'); ?>" class="header-button">
                <?= htmlspecialchars($translations['header_dashboard'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php else: ?>
            <a href="<?= htmlspecialchars(SITE_URL . '/register.php', ENT_QUOTES, 'UTF-8'); ?>" class="header-button">
                <?= htmlspecialchars($translations['header_ads'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="header-center">
        <a href="<?= htmlspecialchars(SITE_URL, ENT_QUOTES, 'UTF-8'); ?>">
            <img
                src="<?= htmlspecialchars(SITE_URL . '/assets/images/logo.png', ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?= htmlspecialchars($translations['logo_alt'], ENT_QUOTES, 'UTF-8'); ?>"
            >
        </a>
    </div>

    <div class="header-right">
        <?php if ($is_logged_in): ?>
            <a href="<?= htmlspecialchars(SITE_URL . '/auth/logout.php', ENT_QUOTES, 'UTF-8'); ?>" class="header-button">
                <?= htmlspecialchars($translations['header_logout'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php else: ?>
            <a href="<?= htmlspecialchars(SITE_URL . '/auth/login.php', ENT_QUOTES, 'UTF-8'); ?>" class="header-button">
                <?= htmlspecialchars($translations['header_login'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endif; ?>
    </div>
</header>

<div class="city-bar">
    <div class="language-selector">
        <div class="custom-select">
            <div class="selected-option">
                <span class="selected-language">
                    <?= htmlspecialchars($currentLanguageDisplayName, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <span class="dropdown-arrow"></span>
            </div>
            <div class="options-list" id="language-options">
                <?php foreach ($languageNameMap as $code => $name): ?>
                    <div class="option" data-value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
                        <span><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <span class="city">
        <span id="city-display"><?= htmlspecialchars($city, ENT_QUOTES, 'UTF-8'); ?></span>
        <i
            class="fas fa-crosshairs"
            id="locate-me-btn"
            title="<?= htmlspecialchars($use_precise_location_title, ENT_QUOTES, 'UTF-8'); ?>"
        ></i>
    </span>

    <div class="menu-dropdown">
        <button
            class="menu-button"
            aria-label="<?= htmlspecialchars($translations['header_menu'], ENT_QUOTES, 'UTF-8'); ?>"
            title="<?= htmlspecialchars($translations['header_menu'], ENT_QUOTES, 'UTF-8'); ?>"
        >
            <img
                src="<?= htmlspecialchars(SITE_URL . '/assets/icons/icon-menu.svg', ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?= htmlspecialchars($translations['header_menu'], ENT_QUOTES, 'UTF-8'); ?>"
                class="menu-icon"
            >
        </button>
        <div id="dropdown-menu" class="dropdown-content">
            <a href="<?= htmlspecialchars(SITE_URL . '/pages/about_us.php', ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-info-circle"></i> <?= htmlspecialchars($translations['about_us'] ?? 'about_us', ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <a href="<?= htmlspecialchars(SITE_URL . '/pages/terms_of_service.php', ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-file-contract"></i> <?= htmlspecialchars($translations['terms_of_service'] ?? 'terms_of_service', ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <a href="<?= htmlspecialchars(SITE_URL . '/pages/privacy_policy.php', ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-shield-alt"></i> <?= htmlspecialchars($translations['privacy_policy'] ?? 'privacy_policy', ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <a href="<?= htmlspecialchars(SITE_URL . '/pages/cookie_policy.php', ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-cookie-bite"></i> <?= htmlspecialchars($translations['cookie_policy'] ?? 'cookie_policy', ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <a href<?= '="' . htmlspecialchars(SITE_URL . '/pages/license.php', ENT_QUOTES, 'UTF-8') . '"'; ?>>
                <i class="fas fa-id-card"></i> <?= htmlspecialchars($translations['header_licenses'] ?? 'header_licenses', ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <a href="<?= htmlspecialchars(SITE_URL . '/pages/contact.php', ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-envelope"></i> <?= htmlspecialchars($translations['contact_us'] ?? 'contact_us', ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </div>
    </div>
</div>

<!-- Form escondido para troca de idioma (bootstrap trata POST 'language' e redireciona limpo) -->
<form id="language-form" method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/', ENT_QUOTES, 'UTF-8'); ?>" style="display:none;">
    <input type="hidden" id="language-input" name="language" value="">
</form>
