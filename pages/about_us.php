<?php
/**
 * /pages/about_us.php - VERSÃO FINAL, CORRIGIDA E TOTALMENTE COMPATÍVEL
 */

/* PASSO 1: INICIALIZAÇÃO CENTRAL */
require_once dirname(__DIR__) . '/core/bootstrap.php';

$page_name = 'about_us_page';

/* PASSO 2: PREPARAÇÃO DE DADOS E TRADUÇÕES */
$languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : (isset(LANGUAGE_CONFIG['default']) ? LANGUAGE_CONFIG['default'] : 'en-us');
$translationContext = 'about_us';

/* Estilos específicos da página (usados pelo head.php) */
$page_specific_styles = [
    rtrim(SITE_URL, '/') . '/assets/css/pages.css'
];

/* Carrega traduções necessárias (sem literais) */
$translations = [];
$keys_to_translate = [
    'about_us_title','about_us_meta_description','about_us_last_updated',
    'about_us_intro_p1','about_us_intro_p2','our_vision_h2','our_vision_p1','our_vision_p2',
    'global_hub_h2','global_hub_li1','global_hub_li2','global_hub_li3','global_hub_li4',
    'for_advertisers_h2','for_advertisers_p1','for_advertisers_li1','for_advertisers_li2',
    'for_advertisers_li3','for_advertisers_li4','for_advertisers_li5',
    'single_registration_h3','single_registration_p1','full_control_h3','full_control_li1',
    'full_control_li2','full_control_li3','what_we_offer_h2','what_we_offer_li1',
    'what_we_offer_li2','what_we_offer_li3','what_we_offer_li4',
    'freedom_responsibility_h2','freedom_responsibility_p1','freedom_responsibility_li1',
    'freedom_responsibility_li2','freedom_responsibility_li3','freedom_responsibility_li4',
    'baco_icon_h2','baco_icon_p1','join_us_h2','join_us_p1','join_us_p2',
    'header_ads','header_login','logo_alt','header_menu','about_us',
    'terms_of_service','privacy_policy','cookie_policy','contact_us','header_licenses',
    'footer_providers','footer_companies','footer_services','footer_clubs','footer_streets',
    'detecting_location',
    'site_name'
];

foreach ($keys_to_translate as $key) {
    $context = $translationContext;
    if (in_array($key, ['header_ads','header_login','logo_alt','header_menu','about_us','terms_of_service','privacy_policy','cookie_policy','contact_us','header_licenses'], true)) {
        $context = 'header';
    } elseif (strpos($key, 'footer_') === 0) {
        $context = 'footer';
    } elseif ($key === 'detecting_location') {
        $context = 'ui_messages';
    } elseif ($key === 'site_name') {
        $context = 'default';
    }
    $translations[$key] = getTranslation($key, $languageCode, $context);
}

$city_from_translations = isset($translations['detecting_location']) ? $translations['detecting_location'] : '';
$city = isset($_SESSION['city']) ? $_SESSION['city'] : $city_from_translations;

$langNameMap = isset(LANGUAGE_CONFIG['name_map']) ? LANGUAGE_CONFIG['name_map'] : [];
$currentLangName = isset($langNameMap[$languageCode]) ? $langNameMap[$languageCode] : getTranslation('language_label', $languageCode, 'default');
$translations['languageOptionsForDisplay'] = $langNameMap;
$translations['current_language_display_name'] = $currentLangName;

$page_title_fallback = 'about_us_title';
$page_title = !empty($translations['about_us_title']) ? $translations['about_us_title'] : $page_title_fallback;

$meta_description_fallback = isset(SEO_CONFIG['meta_description']) ? SEO_CONFIG['meta_description'] : 'about_us_meta_description';
$meta_description = !empty($translations['about_us_meta_description']) ? $translations['about_us_meta_description'] : $meta_description_fallback;

$csp_nonce = isset($_SESSION['csp_nonce']) ? $_SESSION['csp_nonce'] : null;

require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';

$e = function ($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};
?>
<main role="main" aria-labelledby="about-title">
    <div class="static-content-wrapper">
        <div class="content-header">
            <h1 id="about-title"><?= $e($page_title); ?></h1>
            <p class="last-updated"><?= $e(isset($translations['about_us_last_updated']) ? $translations['about_us_last_updated'] : 'about_us_last_updated'); ?></p>
        </div>

        <div class="content-body">
            <section>
                <p><?= $e(isset($translations['about_us_intro_p1']) ? $translations['about_us_intro_p1'] : 'about_us_intro_p1'); ?></p>
                <p><?= $e(isset($translations['about_us_intro_p2']) ? $translations['about_us_intro_p2'] : 'about_us_intro_p2'); ?></p>
            </section>
            
            <section>
                <h2><?= $e(isset($translations['our_vision_h2']) ? $translations['our_vision_h2'] : 'our_vision_h2'); ?></h2>
                <p><?= $e(isset($translations['our_vision_p1']) ? $translations['our_vision_p1'] : 'our_vision_p1'); ?></p>
                <p><?= $e(isset($translations['our_vision_p2']) ? $translations['our_vision_p2'] : 'our_vision_p2'); ?></p>
            </section>

            <section>
                <h2><?= $e(isset($translations['global_hub_h2']) ? $translations['global_hub_h2'] : 'global_hub_h2'); ?></h2>
                <ul>
                    <li><?= $e(isset($translations['global_hub_li1']) ? $translations['global_hub_li1'] : 'global_hub_li1'); ?></li>
                    <li><?= $e(isset($translations['global_hub_li2']) ? $translations['global_hub_li2'] : 'global_hub_li2'); ?></li>
                    <li><?= $e(isset($translations['global_hub_li3']) ? $translations['global_hub_li3'] : 'global_hub_li3'); ?></li>
                    <li><?= $e(isset($translations['global_hub_li4']) ? $translations['global_hub_li4'] : 'global_hub_li4'); ?></li>
                </ul>
            </section>

            <section>
                <h2><?= $e(isset($translations['for_advertisers_h2']) ? $translations['for_advertisers_h2'] : 'for_advertisers_h2'); ?></h2>
                <p><?= $e(isset($translations['for_advertisers_p1']) ? $translations['for_advertisers_p1'] : 'for_advertisers_p1'); ?></p>
                <ul>
                    <li><?= $e(isset($translations['for_advertisers_li1']) ? $translations['for_advertisers_li1'] : 'for_advertisers_li1'); ?></li>
                    <li><?= $e(isset($translations['for_advertisers_li2']) ? $translations['for_advertisers_li2'] : 'for_advertisers_li2'); ?></li>
                    <li><?= $e(isset($translations['for_advertisers_li3']) ? $translations['for_advertisers_li3'] : 'for_advertisers_li3'); ?></li>
                    <li><?= $e(isset($translations['for_advertisers_li4']) ? $translations['for_advertisers_li4'] : 'for_advertisers_li4'); ?></li>
                    <li><?= $e(isset($translations['for_advertisers_li5']) ? $translations['for_advertisers_li5'] : 'for_advertisers_li5'); ?></li>
                </ul>
            </section>

            <section>
                <h3><?= $e(isset($translations['single_registration_h3']) ? $translations['single_registration_h3'] : 'single_registration_h3'); ?></h3>
                <p><?= $e(isset($translations['single_registration_p1']) ? $translations['single_registration_p1'] : 'single_registration_p1'); ?></p>
                <h3><?= $e(isset($translations['full_control_h3']) ? $translations['full_control_h3'] : 'full_control_h3'); ?></h3>
                <ul>
                    <li><?= $e(isset($translations['full_control_li1']) ? $translations['full_control_li1'] : 'full_control_li1'); ?></li>
                    <li><?= $e(isset($translations['full_control_li2']) ? $translations['full_control_li2'] : 'full_control_li2'); ?></li>
                    <li><?= $e(isset($translations['full_control_li3']) ? $translations['full_control_li3'] : 'full_control_li3'); ?></li>
                </ul>
            </section>

            <section>
                <h2><?= $e(isset($translations['what_we_offer_h2']) ? $translations['what_we_offer_h2'] : 'what_we_offer_h2'); ?></h2>
                <ul>
                    <li><?= $e(isset($translations['what_we_offer_li1']) ? $translations['what_we_offer_li1'] : 'what_we_offer_li1'); ?></li>
                    <li><?= $e(isset($translations['what_we_offer_li2']) ? $translations['what_we_offer_li2'] : 'what_we_offer_li2'); ?></li>
                    <li><?= $e(isset($translations['what_we_offer_li3']) ? $translations['what_we_offer_li3'] : 'what_we_offer_li3'); ?></li>
                    <li><?= $e(isset($translations['what_we_offer_li4']) ? $translations['what_we_offer_li4'] : 'what_we_offer_li4'); ?></li>
                </ul>
            </section>

            <section>
                <h2><?= $e(isset($translations['freedom_responsibility_h2']) ? $translations['freedom_responsibility_h2'] : 'freedom_responsibility_h2'); ?></h2>
                <p><?= $e(isset($translations['freedom_responsibility_p1']) ? $translations['freedom_responsibility_p1'] : 'freedom_responsibility_p1'); ?></p>
                <ul>
                    <li><?= $e(isset($translations['freedom_responsibility_li1']) ? $translations['freedom_responsibility_li1'] : 'freedom_responsibility_li1'); ?></li>
                    <li><?= $e(isset($translations['freedom_responsibility_li2']) ? $translations['freedom_responsibility_li2'] : 'freedom_responsibility_li2'); ?></li>
                    <li><?= $e(isset($translations['freedom_responsibility_li3']) ? $translations['freedom_responsibility_li3'] : 'freedom_responsibility_li3'); ?></li>
                    <li><?= $e(isset($translations['freedom_responsibility_li4']) ? $translations['freedom_responsibility_li4'] : 'freedom_responsibility_li4'); ?></li>
                </ul>
            </section>

            <section>
                <h2><?= $e(isset($translations['baco_icon_h2']) ? $translations['baco_icon_h2'] : 'baco_icon_h2'); ?></h2>
                <p><?= $e(isset($translations['baco_icon_p1']) ? $translations['baco_icon_p1'] : 'baco_icon_p1'); ?></p>
            </section>

            <section>
                <h2><?= $e(isset($translations['join_us_h2']) ? $translations['join_us_h2'] : 'join_us_h2'); ?></h2>
                <p><?= $e(isset($translations['join_us_p1']) ? $translations['join_us_p1'] : 'join_us_p1'); ?></p>
                <p>
                    <?= $e(isset($translations['join_us_p2']) ? $translations['join_us_p2'] : 'join_us_p2') ?>
                    <a href="<?= $e(rtrim(SITE_URL, '/') . '/pages/contact.php'); ?>"><?= $e(isset($translations['contact_us']) ? $translations['contact_us'] : 'contact_us') ?></a>.
                </p>
            </section>
        </div>
    </div>
</main>

<?php
/* JSON-LD opcional (Organization) — AJUSTADO COM A SUA SUGESTÃO */
try {
    $org = [
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => (string)(isset($translations['site_name']) ? $translations['site_name'] : ''),
        'url'      => rtrim(SITE_URL, '/'),
        'logo'     => rtrim(SITE_URL, '/') . '/assets/images/logo.png'
    ];

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    // Monta o atributo nonce de forma segura, apenas se existir
    $nonceAttr = $csp_nonce ? ' nonce="' . htmlspecialchars((string)$csp_nonce, ENT_QUOTES, 'UTF-8') . '"' : '';

    echo '<script type="application/ld+json"' . $nonceAttr . '>'
       . json_encode($org, $jsonFlags)
       . '</script>';
} catch (Exception $ex) { // Variável renomeada para $ex para evitar conflito
    // silencioso
}

require_once TEMPLATE_PATH . 'footer.php';
?>