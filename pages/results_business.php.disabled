<?php
/**
 * /pages/results_business.php - Lista de Empresas (padrão BacoSearch)
 * ÚLTIMA ATUALIZAÇÃO: 03/11/2025
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';

$page_name = 'results_business_page';

/* ===================== CSS/JS específicos ===================== */
$page_specific_styles = [SITE_URL . '/assets/css/search-providers.css'];
$page_specific_scripts = [['src' => SITE_URL . '/assets/js/businesses.js', 'attrs' => ['defer' => true]]];

/* ===================== Idioma + traduções ===================== */
$language_code = $_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us');
$translationContext = 'results_business';

$translations = [];
$keys_to_translate = [
    'results_business_title','results_business_meta_description','no_profiles_found','km_from_you','price_not_informed','years_old',
    'modal_select_planet','planet_earth','planet_earth_desc','planet_mars','planet_mars_desc',
    'planet_mars_message_1','planet_mars_message_2','planet_mars_message_3','mars_no_businesses',
    'modal_select_country','modal_select_region','modal_select_city',
    'filter_advanced_title','filter_apply',
    'filter_price','filter_distance','filter_advanced',
    'filter_category_liberal','filter_category_sensual_bar','filter_category_striptease','filter_category_erotic_discoteca','filter_category_sensual_spa','filter_category_events',
    'ad_level_city','ad_level_region','ad_level_country','ad_level_global',
    'header_ads','header_login','logo_alt','header_menu','about_us',
    'terms_of_service','privacy_policy','cookie_policy','contact_us',
    'footer_providers','footer_companies','footer_services','footer_businesses','footer_streets',
    'detecting_location','header_licenses',
    'breadcrumb.earth','breadcrumb.more',
];

foreach ($keys_to_translate as $key) {
    $ctx = $translationContext;
    if (strpos($key, 'header_') === 0 || in_array($key, ['logo_alt','about_us','terms_of_service','privacy_policy','cookie_policy','contact_us','detecting_location'], true)) {
        $ctx = 'header';
    } elseif (strpos($key, 'footer_') === 0) {
        $ctx = 'footer';
    } elseif (strpos($key, 'breadcrumb.') === 0) {
        $ctx = 'breadcrumb';
    }
    $translations[$key] = getTranslation($key, $language_code, $ctx);
}

$langNameMap = LANGUAGE_CONFIG['name_map'] ?? [];
$currentLangName = $langNameMap[$language_code] ?? getTranslation('language_label', $language_code, 'default');
$translations['languageOptionsForDisplay'] = $langNameMap;
$translations['current_language_display_name'] = $currentLangName;

/* ===================== META ===================== */
$page_title = $translations['results_business_title'] ?: 'results_business_title';
$meta_description = $translations['results_business_meta_description'] ?: (SEO_CONFIG['meta_description'] ?? '');
$meta_keywords = SEO_CONFIG['meta_keywords'] ?? '';
$meta_author = SEO_CONFIG['meta_author'] ?? 'BacoSearch';

/* ===================== Local inicial (sessão) ===================== */
$planet = 'earth';
$countryCode = $_SESSION['country_code'] ?? $_SESSION['country_iso'] ?? $_SESSION['location_country'] ?? '';
$regionName  = $_SESSION['region'] ?? $_SESSION['region_name'] ?? '';
$cityName    = $_SESSION['city'] ?? $_SESSION['city_name'] ?? '';

$countryName = null;
if (!empty($countryCode)) {
    try {
        $st = getDBConnection()->prepare("SELECT name FROM countries WHERE iso_code = ? LIMIT 1");
        $st->execute([$countryCode]);
        $countryNameFetched = $st->fetchColumn();
        $countryName = $countryNameFetched ?: null;
    } catch (Exception $e) { /* silencioso */ }
}

/* ===================== Filtros iniciais (GET) ===================== */
$initial_filters = [
    'category'  => $_GET['category'] ?? 'liberal',
    'price_max' => $_GET['price_max'] ?? null,
    'distance'  => $_GET['distance'] ?? null,
    'keywords'  => $_GET['keywords'] ?? '',
];

/* ===================== Dados iniciais p/ JS ===================== */
$initial_location = [
    'planet'       => $planet,
    'country_code' => $countryCode,
    'country_name' => $countryName,
    'region'       => $regionName,
    'city'         => $cityName,
];
$initial_business_data = [ 'businesses' => [], 'level' => 'global' ];
$adData = [ 'global' => [] ];

/* ===================== Render ===================== */
require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';

$e = function($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<main class="search-main global-content-wrapper">
    <div class="breadcrumb-trail" id="location-breadcrumb">
    </div>
    <div class="ad-level-indicator">
        <small>
            <?php 
            $level_to_show = $adData['level'] ?? 'global';
            switch($level_to_show) {
                case 'city':
                    echo str_replace('{city}', htmlspecialchars($initial_location['city'] ?? ''), $translations['ad_level_city'] ?? 'Anúncios da cidade de {city}');
                    break;
                case 'region':
                    echo str_replace('{region}', htmlspecialchars($initial_location['region'] ?? ''), $translations['ad_level_region'] ?? 'Anúncios da região de {region}');
                    break;
                case 'country':
                    echo str_replace('{country}', htmlspecialchars($initial_location['country_name'] ?? ''), $translations['ad_level_country'] ?? 'Anúncios de {country}');
                    break;
                case 'global':
                    echo $translations['ad_level_global'] ?? 'Anúncios globais';
                    break;
            }
            ?>
        </small>
    </div>
    <div class="filter-group categories">
        <button data-filter="category" data-value="liberal" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_liberal'] ?? 'Empresas Liberais'); ?></button>
        <button data-filter="category" data-value="sensual_bar" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_sensual_bar'] ?? 'Bares Sensuais'); ?></button>
        <button data-filter="category" data-value="striptease" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_striptease'] ?? 'Striptease'); ?></button>
        <button data-filter="category" data-value="erotic_discoteca" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_erotic_discoteca'] ?? 'Discotecas Eróticas'); ?></button>
        <button data-filter="category" data-value="sensual_spa" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_sensual_spa'] ?? 'Spas Sensuais'); ?></button>
        <button data-filter="category" data-value="events" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_events'] ?? 'Eventos +18'); ?></button>
    </div>
    <div class="advanced-filters">
        <div class="filter-item">
            <label for="price-range" class="filter-label"><?php echo htmlspecialchars($translations['filter_price'] ?? 'Preço'); ?>: <span id="price-value"></span></label>
            <input type="range" id="price-range" class="range-slider" min="0" max="5000" step="100" data-filter="price_max" value="5000">
        </div>
        <button id="advanced-filter-btn" class="advanced-filter-toggle">
            <i class="fas fa-sliders-h"></i>
            <span><?php echo htmlspecialchars($translations['filter_advanced'] ?? 'Filtros'); ?></span>
        </button>
        <div class="filter-item">
            <label for="distance-range" class="filter-label"><?php echo htmlspecialchars($translations['filter_distance'] ?? 'Distância'); ?>: <span id="distance-value"></span></label>
            <input type="range" id="distance-range" class="range-slider" min="1" max="200" step="1" data-filter="distance" value="100">
        </div>
    </div>
    <div class="results-container">
        <div class="loading-spinner" id="loading-spinner"><i class="fas fa-spinner fa-spin"></i></div>
        <section id="results-grid">
            <?php if (empty($initial_business_data['businesses'])): ?>
                <p class="no-results-message"><?php echo htmlspecialchars($translations['no_profiles_found'] ?? 'Nenhum clube encontrado'); ?></p>
            <?php endif; ?>
        </section>
    </div>
    <div id="planet-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php echo htmlspecialchars($translations['modal_select_planet'] ?? 'Selecione o Planeta'); ?></h2>
                <button id="close-planet-modal" class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="planet-options">
                    <div class="planet-option" onclick="selectPlanet('earth')">
                        <i class="fas fa-globe"></i>
                        <h3><?php echo htmlspecialchars($translations['planet_earth'] ?? 'Terra'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['planet_earth_desc'] ?? 'Explore Empresas do nosso planeta'); ?></p>
                    </div>
                    <div class="planet-option" onclick="selectPlanet('mars')">
                        <i class="fas fa-rocket"></i>
                        <h3><?php echo htmlspecialchars($translations['planet_mars'] ?? 'Marte'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['planet_mars_desc'] ?? 'Em breve...'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="location-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="location-modal-title"></h2>
                <button id="close-location-modal" class="close-modal">&times;</button>
            </div>
            <div class="modal-body" id="location-modal-list"></div>
        </div>
    </div>
    <div id="advanced-filter-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php echo htmlspecialchars($translations['filter_advanced_title'] ?? 'Filtros Avançados'); ?></h2>
                <button id="close-advanced-modal-btn" class="close-modal">&times;</button>
            </div>
            <div class="modal-body" id="advanced-filters-body">
                <div class="filter-group advanced-filters">
                    <div class="filter-item">
                        <label for="price-range" class="filter-label"><?php echo htmlspecialchars($translations['filter_price'] ?? 'Preço'); ?>: <span id="price-value"></span></label>
                        <input type="range" id="price-range" class="range-slider" min="0" max="5000" step="100" data-filter="price_max" value="5000">
                    </div>
                    <div class="filter-item">
                        <label for="distance-range" class="filter-label"><?php echo htmlspecialchars($translations['filter_distance'] ?? 'Distância'); ?>: <span id="distance-value"></span></label>
                        <input type="range" id="distance-range" class="range-slider" min="1" max="200" step="1" data-filter="distance" value="100">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="apply-advanced-filters-btn" class="btn-primary"><?php echo htmlspecialchars($translations['filter_apply'] ?? 'Aplicar Filtros'); ?></button>
            </div>
        </div>
    </div>
</main>
<script>
const initialFilters = <?php echo json_encode($initial_filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const locationData = <?php echo json_encode($initial_location, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const adData = <?php echo json_encode($adData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const initialBusinessData = <?php echo json_encode($initial_business_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const translations = <?php echo json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<?php
require_once TEMPLATE_PATH . 'footer.php';
ob_end_flush();
?>
