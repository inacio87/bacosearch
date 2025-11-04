<?php
/**
 * /pages/results_business.php - VERSÃO FINAL E TOTALMENTE COMPATÍVEL
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/api/additional_functions.php';

$page_name = 'results_business_page';
$page_specific_styles = [SITE_URL . '/assets/css/search-business.css'];
$page_specific_scripts = [['src' => SITE_URL . '/assets/js/location-navigator.js', 'attrs' => ['defer']]];

$languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : (isset(LANGUAGE_CONFIG['default']) ? LANGUAGE_CONFIG['default'] : 'pt-pt');
$translationContext = 'results_business';

$translations = [];
$keys_to_translate = [
    'results_business_title', 'results_business_meta_description', 'results_business_last_updated', 'results_intro_p1', 'results_section1_title', 'results_section1_text',
    'filter_category_business', 'filter_category_hotel', 'filter_category_club', 'filter_gender_male', 'filter_gender_female', 'filter_gender_trans', 'filter_gender_couple',
    'filter_advanced_title', 'filter_price', 'filter_distance', 'filter_apply', 'filter_advanced', 'no_profiles_found', 'label_accept_terms', 'link_terms_of_service', 'link_privacy_policy',
    'header_ads', 'header_login', 'logo_alt', 'header_menu', 'about_us', 'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us', 'footer_providers', 'footer_companies', 'footer_services', 'footer_clubs', 'footer_streets',
    'detecting_location', 'header_licenses', 'ad_level_city', 'ad_level_region', 'ad_level_country', 'ad_level_global', 'breadcrumb_planet', 'modal_select_country', 'modal_select_region', 'modal_select_city',
    'km_from_you', 'price_not_informed', 'years_old', 'modal_select_planet', 'planet_earth', 'planet_earth_desc', 'planet_mars', 'planet_mars_desc',
    'planet_mars_message_1', 'planet_mars_message_2', 'planet_mars_message_3', 'advertisers_label', 'cities_label'
];

foreach ($keys_to_translate as $key) {
    $context = $translationContext;
    if (strpos($key, 'header_') === 0 || in_array($key, ['logo_alt', 'about_us', 'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us'])) {
        $context = 'header';
    } elseif (strpos($key, 'footer_') === 0) {
        $context = 'footer';
    } elseif ($key === 'detecting_location') {
        $context = 'ui_messages';
    }
    $translations[$key] = getTranslation($key, $languageCode, $context);
}

$location_data = [
    'lat' => isset($_SESSION['latitude']) ? $_SESSION['latitude'] : null, 'lon' => isset($_SESSION['longitude']) ? $_SESSION['longitude'] : null, 'city' => isset($_SESSION['city']) ? $_SESSION['city'] : null,
    'region' => isset($_SESSION['region']) ? $_SESSION['region'] : null, 'country_code' => isset($_SESSION['country_code']) ? $_SESSION['country_code'] : null, 'country_name' => null
];

if (isset($_GET['country_code'])) {
    $location_data['country_code'] = htmlspecialchars($_GET['country_code']);
    $location_data['region'] = isset($_GET['region']) ? htmlspecialchars($_GET['region']) : null;
    $location_data['city'] = isset($_GET['city']) ? htmlspecialchars($_GET['city']) : null;
}
if (!empty($location_data['country_code'])) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT name FROM countries WHERE iso_code = ? LIMIT 1");
    $stmt->execute([$location_data['country_code']]);
    $country_name = $stmt->fetchColumn();
    if ($country_name) {
        $location_data['country_name'] = $country_name;
    }
}

$initialFilters = [
    'category' => isset($_GET['category']) ? htmlspecialchars($_GET['category']) : 'business',
    'gender' => isset($_GET['gender']) ? htmlspecialchars($_GET['gender']) : 'female',
    'price_max' => isset($_GET['price_max']) ? htmlspecialchars($_GET['price_max']) : 5000,
    'distance' => isset($_GET['distance']) ? htmlspecialchars($_GET['distance']) : 100
];

$page_title = !empty($translations['results_business_title']) ? $translations['results_business_title'] : 'results_business_title';
$meta_description = !empty($translations['results_business_meta_description']) ? $translations['results_business_meta_description'] : 'Encontre os melhores negócios na sua área.';

$langNameMap = isset(LANGUAGE_CONFIG['name_map']) ? LANGUAGE_CONFIG['name_map'] : [];
$currentLangName = isset($langNameMap[$languageCode]) ? $langNameMap[$languageCode] : getTranslation('language_label', $languageCode, 'default');
$translations['languageOptionsForDisplay'] = $langNameMap;
$translations['current_language_display_name'] = $currentLangName;

$business_data = findBusinessesWithFallback($location_data, $initialFilters);
$ad_data = getAdvertisements($location_data);

require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';

$e = function($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<main class="search-main global-content-wrapper">
    <div class="breadcrumb-trail" id="location-breadcrumb"></div>
    <div class="ad-level-indicator">
        <small>
            <?php
            $ad_level = isset($ad_data['level']) ? $ad_data['level'] : 'global';
            switch($ad_level) {
                case 'city':
                    echo str_replace('{city}', $e($location_data['city']), $e(isset($translations['ad_level_city']) ? $translations['ad_level_city'] : ''));
                    break;
                case 'region':
                    echo str_replace('{region}', $e($location_data['region']), $e(isset($translations['ad_level_region']) ? $translations['ad_level_region'] : ''));
                    break;
                case 'country':
                    echo str_replace('{country}', $e($location_data['country_name']), $e(isset($translations['ad_level_country']) ? $translations['ad_level_country'] : ''));
                    break;
                default:
                    echo $e(isset($translations['ad_level_global']) ? $translations['ad_level_global'] : '');
            }
            ?>
        </small>
    </div>
    </main>
<?php
require_once TEMPLATE_PATH . 'footer.php';
?>