<?php
/**
 * /pages/results_services.php - VERSÃO FINAL COM ARQUITETURA CORRIGIDA E NAVEGAÇÃO HIERÁRQUICA
 *
 * RESPONSABILIDADES:
 * 1. Ponto de entrada para a página "Resultados para Serviços".
 * 2. Carrega o sistema de bootstrap central.
 * 3. Define o CSS e JS específicos para esta página.
 * 4. Prepara dados de localização e traduções para o JavaScript.
 * 5. Renderiza a estrutura HTML com breadcrumb hierárquico e filtros dinâmicos.
 * 6. Implementa sistema de fallback de anúncios por localização.
 * 7. Adiciona funcionalidade de navegação planetária (Terra/Marte).
 * 8. Persiste o estado dos filtros na URL.
 *
 * ÚLTIMA ATUALIZAÇÃO: 06/08/2025 - Alinhamento com results_providers.php e correção da persistência do idioma.
 */

// PASSO 1: INICIALIZAÇÃO CENTRAL
require_once dirname(__DIR__) . '/core/bootstrap.php';

// Inclui as funções adicionais
require_once dirname(__DIR__) . '/api/additional_functions.php';

$page_name = 'results_services_page';

// Define os estilos e scripts específicos para esta página.
$page_specific_styles = [
    SITE_URL . '/assets/css/search-services.css' // Use search-providers.css se não houver específico
];

$page_specific_scripts = [
    ['src' => SITE_URL . '/assets/js/location-navigator.js', 'attrs' => ['defer']]
];

// PASSO 2: PREPARAÇÃO DE DADOS E TRADUÇÕES

// CORREÇÃO: Garante que o idioma da sessão é o primeiro a ser considerado.
// O valor LANGUAGE_CONFIG['default'] é o fallback final.
$languageCode = $_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'pt-pt');

$translationContext = 'results_services';

$translations = [];
$keys_to_translate = [
    'results_services_title', 'results_services_meta_description', 'results_services_last_updated',
    'results_intro_p1', 'results_section1_title', 'results_section1_text',
    'filter_category_massage', 'filter_category_webcam', 'filter_category_escort', 'filter_category_fotografo', 'filter_category_videomaker', 'filter_category_designer', 'filter_category_manager', 'filter_category_seguranca', 'filter_category_consultor', 'filter_category_terapeuta', 'filter_category_trainer', 'filter_category_tradutor',
    'filter_advanced_title', 'filter_price', 'filter_distance', 'filter_apply', 'filter_advanced',
    'no_profiles_found', 'label_accept_terms', 'link_terms_of_service', 'link_privacy_policy',
    'header_ads', 'header_login', 'logo_alt', 'header_menu', 'about_us',
    'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us',
    'footer_providers', 'footer_companies', 'footer_services', 'footer_clubs', 'footer_streets',
    'detecting_location', 'header_licenses',
    // Novas chaves para o breadcrumb e indicador de nível de anúncios
    'ad_level_city', 'ad_level_region', 'ad_level_country', 'ad_level_global',
    'breadcrumb_planet', 'modal_select_country', 'modal_select_region', 'modal_select_city',
    'km_from_you', 'price_not_informed', 'years_old',
    'modal_select_planet', 'planet_earth', 'planet_earth_desc', 'planet_mars', 'planet_mars_desc',
    'planet_mars_message_1', 'planet_mars_message_2', 'planet_mars_message_3',
    'advertisers_label', 'cities_label'
];

foreach ($keys_to_translate as $key) {
    $context = $translationContext;
    if (str_starts_with($key, 'header_') || in_array($key, ['logo_alt', 'about_us', 'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us', 'detecting_location'])) {
        $context = 'header';
    } elseif (str_starts_with($key, 'footer_')) {
        $context = 'footer';
    }
    // A função getTranslation já lida com o fallback, portanto não é preciso duplicar a lógica aqui
    $translations[$key] = getTranslation($key, $languageCode, $context);
}

// Dados do visitor da tabela 'visitors'
$visitor_id = $_SESSION['visitor_db_id'] ?? null;
$location_data = [
    'lat' => $_SESSION['latitude'] ?? null,
    'lon' => $_SESSION['longitude'] ?? null,
    'city' => $_SESSION['city'] ?? null,
    'region' => $_SESSION['region'] ?? null,
    'country_code' => $_SESSION['country_code'] ?? null,
    'country_name' => null
];

// Lógica para inicializar a localização a partir da URL
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

// Lógica para inicializar os filtros a partir da URL
$initialFilters = [
    'category' => htmlspecialchars($_GET['category'] ?? 'massage'),
    'gender' => htmlspecialchars($_GET['gender'] ?? 'female'),
    'price_max' => htmlspecialchars($_GET['price_max'] ?? 2000),
    'distance' => htmlspecialchars($_GET['distance'] ?? 100)
];

$page_title = $translations['results_services_title'] ?? 'Buscar Serviços';
$meta_description = $translations['results_services_meta_description'] ?? 'Encontre os melhores serviços na sua área.';

$translations['languageOptionsForDisplay'] = LANGUAGE_CONFIG['name_map'] ?? [];
$translations['current_language_display_name'] = $translations['languageOptionsForDisplay'][$languageCode] ?? 'Language';

// PASSO 3: LÓGICA DE FALLBACK DE ANÚNCIOS e SERVIÇOS
$service_data = findServicesWithFallback($location_data, $initialFilters);
$ad_data = getAdvertisements($location_data);

// PASSO 4: RENDERIZAÇÃO DA PÁGINA
require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';
?>
<main class="search-main global-content-wrapper">
    <div class="breadcrumb-trail" id="location-breadcrumb">
    </div>
    <div class="ad-level-indicator">
        <small>
            <?php
            switch($ad_data['level']) {
                case 'city':
                    echo str_replace('{city}', htmlspecialchars($location_data['city']), $translations['ad_level_city'] ?? 'Anúncios da cidade de {city}');
                    break;
                case 'region':
                    echo str_replace('{region}', htmlspecialchars($location_data['region']), $translations['ad_level_region'] ?? 'Anúncios da região de {region}');
                    break;
                case 'country':
                    echo str_replace('{country}', htmlspecialchars($location_data['country_name']), $translations['ad_level_country'] ?? 'Anúncios de {country}');
                    break;
                case 'global':
                    echo $translations['ad_level_global'] ?? 'Anúncios globais';
                    break;
            }
            ?>
        </small>
    </div>
    <div class="filter-group categories">
        <button data-filter="category" data-value="massage" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_massage'] ?? 'Massagem'); ?></button>
        <button data-filter="category" data-value="webcam" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_webcam'] ?? 'Webcam'); ?></button>
        <button data-filter="category" data-value="escort" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_escort'] ?? 'Acompanhante'); ?></button>
        <button data-filter="category" data-value="fotografo" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_fotografo'] ?? 'Fotógrafo'); ?></button>
        <button data-filter="category" data-value="videomaker" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_videomaker'] ?? 'Videomaker'); ?></button>
        <button data-filter="category" data-value="designer" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_designer'] ?? 'Designer'); ?></button>
        <button data-filter="category" data-value="manager" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_manager'] ?? 'Gestor de Redes Sociais'); ?></button>
        <button data-filter="category" data-value="seguranca" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_seguranca'] ?? 'Segurança'); ?></button>
        <button data-filter="category" data-value="consultor" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_consultor'] ?? 'Consultor de Imagem'); ?></button>
        <button data-filter="category" data-value="terapeuta" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_terapeuta'] ?? 'Terapeuta Tântrico'); ?></button>
        <button data-filter="category" data-value="trainer" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_trainer'] ?? 'Personal Trainer'); ?></button>
        <button data-filter="category" data-value="tradutor" class="filter-btn"><?php echo htmlspecialchars($translations['filter_category_tradutor'] ?? 'Tradutor'); ?></button>
    </div>
    <div class="advanced-filters">
        <div class="filter-item">
            <label for="price-range" class="filter-label"><?php echo htmlspecialchars($translations['filter_price'] ?? 'Preço'); ?>: <span id="price-value"></span></label>
            <input type="range" id="price-range" class="range-slider" min="0" max="2000" step="50" data-filter="price_max" value="2000">
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
            <?php if (empty($service_data['services'])): ?>
                <p class="no-results-message"><?php echo htmlspecialchars($translations['no_profiles_found'] ?? 'Nenhum serviço encontrado'); ?></p>
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
                        <p><?php echo htmlspecialchars($translations['planet_earth_desc'] ?? 'Explore serviços do nosso planeta'); ?></p>
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
                        <input type="range" id="price-range" class="range-slider" min="0" max="2000" step="50" data-filter="price_max" value="2000">
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
const initialFilters = <?php echo json_encode($initialFilters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const locationData = <?php echo json_encode($location_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const adData = <?php echo json_encode($ad_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const initialClubData = <?php echo json_encode($club_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const translations = <?php echo json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<?php
require_once TEMPLATE_PATH . 'footer.php';
ob_end_flush();
?>