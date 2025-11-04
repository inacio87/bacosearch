<?php
/**
 * /pages/results_providers.php - VERSÃO FINAL E TOTALMENTE COMPATÍVEL
 */

// AJUSTADO: Removido declare(strict_types=1); para compatibilidade
require_once dirname(__DIR__) . '/core/bootstrap.php';

$page_name = 'results_providers_page';

/* ===================== Helpers locais ===================== */
function bs_get_available_services($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'providers_services_list'");
        if (!$stmt || !$stmt->fetchColumn()) {
            return [];
        }
        $colsStmt = $pdo->query("SHOW COLUMNS FROM providers_services_list");
        $cols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
        if (empty($cols)) return [];

        $keyCandidates = ['service_key','key','slug','code','id'];
        $nameCandidates = ['term','name','label','title','display_name'];

        $keyCol = null;
        foreach ($keyCandidates as $c) { if (in_array($c, $cols, true)) { $keyCol = $c; break; } }
        if ($keyCol === null) { $keyCol = $cols[0]; }

        $nameCol = null;
        foreach ($nameCandidates as $c) { if (in_array($c, $cols, true)) { $nameCol = $c; break; } }
        if ($nameCol === null) { $nameCol = isset($cols[1]) ? $cols[1] : $cols[0]; }

        $sql = "SELECT {$keyCol} AS sk, {$nameCol} AS nm FROM providers_services_list";
        $list = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $list = $list ?: [];

        $out = [];
        foreach ($list as $row) {
            $sk = isset($row['sk']) ? (string)$row['sk'] : '';
            $nm = isset($row['nm']) ? (string)$row['nm'] : '';
            if ($sk === '' && $nm === '') continue;
            $out[] = [
                'service_key' => $sk !== '' ? $sk : $nm,
                'term'        => $nm !== '' ? $nm : $sk,
            ];
        }
        return $out;
    } catch (Exception $e) { // AJUSTADO: Throwable para Exception
        return [];
    }
}

/* ===================== CSS/JS específicos ===================== */
$page_specific_styles = [SITE_URL . '/assets/css/search-providers.css'];
$page_specific_scripts = [['src' => SITE_URL . '/assets/js/providers.js', 'attrs' => ['defer' => true]]];

/* ===================== Idioma + traduções ===================== */
$language_code = isset($_SESSION['language']) ? $_SESSION['language'] : (isset(LANGUAGE_CONFIG['default']) ? LANGUAGE_CONFIG['default'] : 'pt-br');
$translationContext = 'results_providers';

$translations = [];
$keys_to_translate = [
    'results_providers_title','results_providers_meta_description', 'no_profiles_found','km_from_you','price_not_informed','years_old',
    'modal_select_planet','planet_earth','planet_earth_desc','planet_mars','planet_mars_desc', 'planet_mars_message_1','planet_mars_message_2','planet_mars_message_3','mars_no_providers',
    'modal_select_country','modal_select_region','modal_select_city', 'filter_advanced_title','filter_services_title','filter_apply',
    'header_ads','header_login','logo_alt','header_menu','about_us', 'terms_of_service','privacy_policy','cookie_policy','contact_us',
    'footer_providers','footer_companies','footer_services','footer_clubs','footer_streets', 'detecting_location','header_licenses',
    'priceNotInformed','hour','km', 'breadcrumb.earth','breadcrumb.more',
];

foreach ($keys_to_translate as $key) {
    $ctx = $translationContext;
    if (strpos($key, 'header_') === 0 || in_array($key, ['logo_alt','about_us','terms_of_service','privacy_policy','cookie_policy','contact_us','detecting_location'], true)) {
        $ctx = 'header';
    } elseif (strpos($key, 'footer_') === 0) {
        $ctx = 'footer';
    } elseif (in_array($key, ['priceNotInformed','hour','km'], true)) {
        $ctx = 'providers';
    } elseif (strpos($key, 'breadcrumb.') === 0) {
        $ctx = 'breadcrumb';
    }
    $translations[$key] = getTranslation($key, $language_code, $ctx);
}

$langNameMap = isset(LANGUAGE_CONFIG['name_map']) ? LANGUAGE_CONFIG['name_map'] : [];
$currentLangName = isset($langNameMap[$language_code]) ? $langNameMap[$language_code] : getTranslation('language_label', $language_code, 'default');
$translations['languageOptionsForDisplay'] = $langNameMap;
$translations['current_language_display_name'] = $currentLangName;

/* ===================== META ===================== */
$page_title_fallback = 'results_providers_title';
$page_title = !empty($translations['results_providers_title']) ? $translations['results_providers_title'] : $page_title_fallback;
$meta_description_fallback = isset(SEO_CONFIG['meta_description']) ? SEO_CONFIG['meta_description'] : '';
$meta_description = !empty($translations['results_providers_meta_description']) ? $translations['results_providers_meta_description'] : $meta_description_fallback;
$meta_keywords = isset(SEO_CONFIG['meta_keywords']) ? SEO_CONFIG['meta_keywords'] : '';
$meta_author = isset(SEO_CONFIG['meta_author']) ? SEO_CONFIG['meta_author'] : 'BacoSearch';

/* ===================== Local inicial (sessão) ===================== */
$planet = 'earth';
$countryCode = isset($_SESSION['country_code']) ? $_SESSION['country_code'] : (isset($_SESSION['country_iso']) ? $_SESSION['country_iso'] : (isset($_SESSION['location_country']) ? $_SESSION['location_country'] : ''));
$regionName  = isset($_SESSION['region']) ? $_SESSION['region'] : (isset($_SESSION['region_name']) ? $_SESSION['region_name'] : '');
$cityName    = isset($_SESSION['city']) ? $_SESSION['city'] : (isset($_SESSION['city_name']) ? $_SESSION['city_name'] : '');

$countryName = null;
if (!empty($countryCode)) {
    try {
        $st = getDBConnection()->prepare("SELECT name FROM countries WHERE iso_code = ? LIMIT 1");
        $st->execute([$countryCode]);
        $countryNameFetched = $st->fetchColumn();
        $countryName = $countryNameFetched ? $countryNameFetched : null;
    } catch (Exception $e) { /* silencioso */ }
}

/* ===================== Filtros iniciais (GET) ===================== */
$initial_filters = [
    'category'  => isset($_GET['category']) ? $_GET['category'] : 'provider',
    'gender'    => isset($_GET['gender']) ? $_GET['gender'] : null,
    'price_max' => isset($_GET['price_max']) ? $_GET['price_max'] : null,
    'distance'  => isset($_GET['distance']) ? $_GET['distance'] : null,
    'services'  => isset($_GET['services']) ? explode(',', (string)$_GET['services']) : [],
    'keywords'  => isset($_GET['keywords']) ? $_GET['keywords'] : '',
];

/* ===================== Dados iniciais p/ JS ===================== */
$initial_location = [
    'planet'       => $planet,
    'country_code' => $countryCode,
    'country_name' => $countryName,
    'region'       => $regionName,
    'city'         => $cityName,
];
$initial_provider_data = [ 'providers' => [], 'level' => 'global' ];
$adData = [ 'global' => [] ];

$pdo = getDBConnection();
$available_services = bs_get_available_services($pdo);

/* ===================== Render ===================== */
require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';

$e = function($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<main id="providers-root" class="global-content-wrapper">
    <nav id="breadcrumb" class="location-breadcrumb" aria-label="breadcrumb"></nav>
    <section class="results-grid-wrapper">
        <div id="marsMsg" class="mars-message" style="display:none;"></div>
        <div id="resultsGrid" class="results-grid"></div>
        <div class="no-results" id="no-results" style="display:none;">
            <p class="no-results-message"><?= $e(isset($translations['no_profiles_found']) ? $translations['no_profiles_found'] : 'no_profiles_found'); ?></p>
        </div>
        <nav class="pagination" id="pagination-controls" aria-label="Pagination" style="display:none;">
            <button class="pagination-btn" id="prev-page" disabled>«</button>
            <span id="page-info"></span>
            <button class="pagination-btn" id="next-page">»</button>
        </nav>
    </section>

    <div id="planet-modal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?= $e(isset($translations['modal_select_planet']) ? $translations['modal_select_planet'] : 'modal_select_planet'); ?></h2>
                <button id="close-planet-modal" class="close-modal" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="planet-options">
                    <div class="planet-option">
                        <h3><?= $e(isset($translations['planet_earth']) ? $translations['planet_earth'] : (isset($translations['breadcrumb.earth']) ? $translations['breadcrumb.earth'] : 'Terra')); ?></h3>
                        <p><?= $e(isset($translations['planet_earth_desc']) ? $translations['planet_earth_desc'] : 'planet_earth_desc'); ?></p>
                    </div>
                    <div class="planet-option">
                        <h3><?= $e(isset($translations['planet_mars']) ? $translations['planet_mars'] : 'Marte'); ?></h3>
                        <p><?= $e(isset($translations['planet_mars_desc']) ? $translations['planet_mars_desc'] : 'Em breve'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="location-modal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="location-modal-title"></h2>
                <button id="close-location-modal" class="close-modal" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body" id="breadcrumb-list"></div>
        </div>
    </div>

    <div id="advanced-filter-modal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?= $e(isset($translations['filter_advanced_title']) ? $translations['filter_advanced_title'] : 'filter_advanced_title'); ?></h2>
                <button id="close-advanced-modal-btn" class="close-modal" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="filter-group advanced-services-filter">
                    <h4><?= $e(isset($translations['filter_services_title']) ? $translations['filter_services_title'] : 'filter_services_title'); ?></h4>
                    <div class="services-list-container">
                        <?php if (!empty($available_services)): ?>
                            <?php foreach ($available_services as $service): ?>
                                <label class="service-checkbox-label">
                                    <input type="checkbox" name="services[]" value="<?= $e($service['service_key']); ?>">
                                    <?= $e($service['term']); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="apply-advanced-filters-btn" class="btn-primary">
                    <?= $e(isset($translations['filter_apply']) ? $translations['filter_apply'] : 'filter_apply'); ?>
                </button>
            </div>
        </div>
    </div>
</main>

<script>
    (function () {
        try {
            var b = document.body;
            var earth_text = <?= json_encode(isset($translations['breadcrumb.earth']) ? $translations['breadcrumb.earth'] : 'Terra', JSON_UNESCAPED_UNICODE) ?>;
            var more_text = <?= json_encode(isset($translations['breadcrumb.more']) ? $translations['breadcrumb.more'] : 'Mais', JSON_UNESCAPED_UNICODE) ?>;
            b.setAttribute('data-lang', <?= json_encode($language_code, JSON_UNESCAPED_UNICODE) ?>);
            b.setAttribute('data-country', <?= json_encode($countryCode ?: '', JSON_UNESCAPED_UNICODE) ?>);
            b.setAttribute('data-region', <?= json_encode($regionName ?: '', JSON_UNESCAPED_UNICODE) ?>);
            b.setAttribute('data-city', <?= json_encode($cityName ?: '', JSON_UNESCAPED_UNICODE) ?>);
            b.setAttribute('data-t-earth', earth_text);
            b.setAttribute('data-t-more',  more_text);
        } catch(e) { /* silencioso */ }
    }());

    window.appConfig = {
        site_url: <?= json_encode(SITE_URL, JSON_UNESCAPED_UNICODE) ?>,
        translations: <?= json_encode($translations, JSON_UNESCAPED_UNICODE) ?>,
        locationData: <?= json_encode($initial_location, JSON_UNESCAPED_UNICODE) ?>,
        adData: <?= json_encode($adData, JSON_UNESCAPED_UNICODE) ?>,
        initialProviderData: <?= json_encode($initial_provider_data, JSON_UNESCAPED_UNICODE) ?>,
        initialFilters: <?= json_encode($initial_filters, JSON_UNESCAPED_UNICODE) ?>
    };
</script>

<?php
require_once TEMPLATE_PATH . 'footer.php';
?>