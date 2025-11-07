<?php
/**
 * /index.php - Ponto de Entrada Principal
 * (limpo: sem textos literais na UI)
 */

$page_name = 'home';

// PASSO 1: INICIALIZAÇÃO CENTRAL
require_once __DIR__ . '/core/bootstrap.php';

// Headers de cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// PASSO 2: PREPARAÇÃO DE DADOS PARA A VIEW
$language_code = $_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us');
$city          = $_SESSION['city'] ?? null;
$canAppendCity = (!empty($city) && !empty($_SESSION['location_accepted']) && (int)$_SESSION['location_accepted'] === 1);

// Mapa de traduções (somente as usadas pela HOME; header/footer já carregam as suas)
$translations_map = [
    // home_page
    'home_page_title'       => 'home_page',
    'home_page_description' => 'home_page',

    // header (somente o alt da logo usado nesta página)
    'logo_alt'              => 'header',

    // busca e filtros
    'search_button'                 => 'search',
    'search_placeholder'            => 'search',
    'loading_suggestions_text'      => 'search_filters',
    'result_type_popular'           => 'search_filters',
    'result_type_provider'          => 'search_filters',
    'result_type_category'          => 'search_filters',
    'result_type_location'          => 'search_filters',
    'result_type_business'          => 'search_filters',
    'result_type_event'             => 'search_filters',
    'result_type_creative'          => 'search_filters',
    'result_type_service'           => 'search_filters',

    // sugestões
    'suggestion_verified_providers' => 'search',
    'suggestion_sensual_massage'    => 'search',
    'suggestion_explore_motels'     => 'search',
    'suggestion_video_call'         => 'search',
    'suggestion_clubs_parties'      => 'search',
    'suggestion_couples_experiences'=> 'search',
    'suggestion_photographers'      => 'search',
    'suggestion_trans_companions'   => 'search',
    'suggestion_luxury_experiences' => 'search',
    'suggestion_discreet_places'    => 'search',
    'in_city'                       => 'search',

    // api errors
    'internal_server_error'         => 'api_errors',
];

$translations_data = [];
foreach ($translations_map as $key => $context) {
    $translations_data[$key] = getTranslation($key, $language_code, $context);
}
$translations = $translations_data;

// Sufixo “em <Cidade>” (só anexa quando temos cidade válida/aceita)
$in_city_suffix = ($canAppendCity ? (($translations['in_city'] ?? '') . $city) : '');

// Sugestões do placeholder
$placeholder_suggestions = [
    [
        'text' => ($translations['suggestion_verified_providers'] ?? 'suggestion_verified_providers') . $in_city_suffix,
        'type' => 'provider',
        'icon' => 'fas fa-user'
    ],
    [
        'text' => ($translations['suggestion_sensual_massage'] ?? 'suggestion_sensual_massage') . $in_city_suffix,
        'type' => 'service',
        'icon' => 'fas fa-spa'
    ],
    [
        'text' => ($translations['suggestion_explore_motels'] ?? 'suggestion_explore_motels') . $in_city_suffix,
        'type' => 'location',
        'icon' => 'fas fa-map-marker-alt'
    ],
    [
        'text' => $translations['suggestion_video_call'] ?? 'suggestion_video_call',
        'type' => 'digital',
        'icon' => 'fas fa-video'
    ],
    [
        'text' => ($translations['suggestion_clubs_parties'] ?? 'suggestion_clubs_parties') . $in_city_suffix,
        'type' => 'event',
        'icon' => 'fas fa-glass-cheers'
    ],
    [
        'text' => $translations['suggestion_couples_experiences'] ?? 'suggestion_couples_experiences',
        'type' => 'service',
        'icon' => 'fas fa-heart'
    ],
    [
        'text' => ($translations['suggestion_photographers'] ?? 'suggestion_photographers') . $in_city_suffix,
        'type' => 'service',
        'icon' => 'fas fa-camera'
    ],
    [
        'text' => $translations['suggestion_trans_companions'] ?? 'suggestion_trans_companions',
        'type' => 'provider',
        'icon' => 'fas fa-user'
    ],
    [
        'text' => $translations['suggestion_luxury_experiences'] ?? 'suggestion_luxury_experiences',
        'type' => 'service',
        'icon' => 'fas fa-gem'
    ],
    [
        'text' => ($translations['suggestion_discreet_places'] ?? 'suggestion_discreet_places') . $in_city_suffix,
        'type' => 'location',
        'icon' => 'fas fa-user-secret'
    ],
];
$placeholder_suggestions = array_filter(array_unique($placeholder_suggestions, SORT_REGULAR));
$json_suggestions = htmlspecialchars(json_encode($placeholder_suggestions), ENT_QUOTES, 'UTF-8');

// Nomes de idioma para exibição
$translations['languageOptionsForDisplay'] = LANGUAGE_CONFIG['name_map'] ?? [];
$translations['current_language_display_name'] =
    $translations['languageOptionsForDisplay'][$language_code] ?? strtoupper($language_code);

$page_title       = $translations['home_page_title']       ?? 'home_page_title';
$meta_description = $translations['home_page_description'] ?? (SEO_CONFIG['meta_description'] ?? 'home_page_description');
$meta_keywords    = SEO_CONFIG['meta_keywords']            ?? '';
$meta_author      = SEO_CONFIG['meta_author']              ?? 'meta_author';
$page_specific_styles = [];

// =======================================================
// INÍCIO: LÓGICA DE ANÚNCIOS
// =======================================================
require_once __DIR__ . '/api/dashboard_ads.php';

$user_country_code = $_SESSION['country_code'] ?? null;
$user_region       = $_SESSION['region'] ?? null;
$user_city         = $_SESSION['city'] ?? null;

$ads = getAllHomePageAds($pdo, $user_country_code, $user_region, $user_city, true);

$ads['global']     = $ads['global']     ?? [];
$ads['national']   = $ads['national']   ?? [];
$ads['regional_1'] = $ads['regional_1'] ?? [];
$ads['regional_2'] = $ads['regional_2'] ?? [];
// =======================================================
// FIM: LÓGICA DE ANÚNCIOS
// =======================================================

// PASSO 4: RENDERIZAÇÃO DA PÁGINA
require_once TEMPLATE_PATH . 'head.php';
?>

<?php
require_once TEMPLATE_PATH . 'header.php';

// --- FUNÇÃO DE RENDERIZAÇÃO DE BANNER ---
function renderAdBanner(array $ad_data, string $slot_class, string $id_prefix = ''): void {
    $desktop_img = !empty($ad_data['image_path'])        ? SITE_URL . $ad_data['image_path']        : null;
    $mobile_img  = !empty($ad_data['image_path_mobile']) ? SITE_URL . $ad_data['image_path_mobile'] : null;

    if (!$desktop_img && !$mobile_img) return;

    $wrapper_classes = [$slot_class];
    if (!$desktop_img && $mobile_img) { $wrapper_classes[] = 'mobile-only'; }
    if ($desktop_img && !$mobile_img) { $wrapper_classes[] = 'desktop-only'; }

    $main_img_src = $desktop_img ?: $mobile_img; ?>
    <div class="<?= htmlspecialchars(implode(' ', $wrapper_classes)) ?>">
        <a href="<?= SITE_URL ?>/redirect_ad.php?id=<?= $ad_data['id'] ?>" target="_blank" rel="noopener sponsored" title="<?= htmlspecialchars($ad_data['title']) ?>">
            <picture>
                <?php if ($mobile_img): ?>
                    <source media="(max-width: 767px)" srcset="<?= htmlspecialchars($mobile_img) ?>">
                <?php endif; ?>
                <img src="<?= htmlspecialchars($main_img_src) ?>" alt="<?= htmlspecialchars($ad_data['title']) ?>">
            </picture>
        </a>
    </div>
<?php } ?>

<main class="main-content">
    <div class="banners-homepage">
        <div class="group-banner-global">
            <?php if (!empty($ads['global'])): ?>
                <div class="banner-global">
                    <?php renderAdBanner($ads['global'], 'ad-banner-top'); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="group-logo-search">
            <div class="logo">
                <img src="<?= htmlspecialchars(SITE_URL . '/assets/images/logo.png', ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?= htmlspecialchars($translations['logo_alt'] ?? 'logo_alt', ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="search-container" data-suggestions="<?= $json_suggestions; ?>">
                <input type="text" id="searchInput"
                       placeholder="<?= htmlspecialchars($translations['search_placeholder'] ?? 'search_placeholder', ENT_QUOTES, 'UTF-8'); ?>">
                <button type="button" class="search-button"
                        aria-label="<?= htmlspecialchars($translations['search_button'] ?? 'search_button', ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>

        <div class="group-banner-national">
            <?php if (!empty($ads['national'])): ?>
                <div class="banner-national">
                    <?php renderAdBanner($ads['national'], 'ad-banner-national'); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="group-banners-regional">
            <?php if (!empty($ads['regional_1']) || !empty($ads['regional_2'])): ?>
                <div class="banner-regionals">
                    <?php if (!empty($ads['regional_1'])): ?>
                        <div class="banner-regional">
                            <?php renderAdBanner($ads['regional_1'], 'ad-banner-regional', 'regional_1'); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($ads['regional_2'])): ?>
                        <div class="banner-regional">
                            <?php renderAdBanner($ads['regional_2'], 'ad-banner-regional', 'regional_2'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
    // Traduções para JS da home
    const FRONTEND_TRANSLATIONS = {
        loading_suggestions_text: "<?= htmlspecialchars($translations['loading_suggestions_text'] ?? 'loading_suggestions_text', ENT_QUOTES, 'UTF-8'); ?>",
        search_placeholder:        "<?= htmlspecialchars($translations['search_placeholder']        ?? 'search_placeholder',        ENT_QUOTES, 'UTF-8'); ?>",
        result_type_popular:       "<?= htmlspecialchars($translations['result_type_popular']       ?? 'result_type_popular',       ENT_QUOTES, 'UTF-8'); ?>",
        result_type_provider:      "<?= htmlspecialchars($translations['result_type_provider']      ?? 'result_type_provider',      ENT_QUOTES, 'UTF-8'); ?>",
        result_type_category:      "<?= htmlspecialchars($translations['result_type_category']      ?? 'result_type_category',      ENT_QUOTES, 'UTF-8'); ?>",
        result_type_location:      "<?= htmlspecialchars($translations['result_type_location']      ?? 'result_type_location',      ENT_QUOTES, 'UTF-8'); ?>",
        result_type_business:      "<?= htmlspecialchars($translations['result_type_business']      ?? 'result_type_business',      ENT_QUOTES, 'UTF-8'); ?>",
        result_type_event:         "<?= htmlspecialchars($translations['result_type_event']         ?? 'result_type_event',         ENT_QUOTES, 'UTF-8'); ?>",
        result_type_creative:      "<?= htmlspecialchars($translations['result_type_creative']      ?? 'result_type_creative',      ENT_QUOTES, 'UTF-8'); ?>",
        result_type_service:       "<?= htmlspecialchars($translations['result_type_service']       ?? 'result_type_service',       ENT_QUOTES, 'UTF-8'); ?>",
        internal_server_error:     "<?= htmlspecialchars($translations['internal_server_error']     ?? 'internal_server_error',     ENT_QUOTES, 'UTF-8'); ?>",
        in_city:                   "<?= htmlspecialchars($translations['in_city']                   ?? 'in_city',                   ENT_QUOTES, 'UTF-8'); ?>"
    };

    if (typeof window.escapeHtml !== 'function') {
        window.escapeHtml = (text) => {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        };
    }

    // Efeito de máquina de escrever para o placeholder
    (function() {
        const searchInput   = document.getElementById('searchInput');
        const container     = document.querySelector('.search-container');
        const suggestions   = JSON.parse(container?.getAttribute('data-suggestions') || '[]');

        let currentIndex = 0, currentText = '', charIndex = 0, isDeleting = false, typewriterInterval = null;

        function typeWriter() {
            if (!searchInput || searchInput === document.activeElement) return;

            const currentSuggestion = suggestions[currentIndex]?.text || '';
            if (!currentSuggestion) { currentIndex = 0; return; }

            if (isDeleting) { currentText = currentSuggestion.substring(0, charIndex - 1); charIndex--; }
            else            { currentText = currentSuggestion.substring(0, charIndex + 1); charIndex++; }

            searchInput.placeholder = currentText;

            if (!isDeleting && charIndex === currentSuggestion.length) {
                isDeleting = true; setTimeout(() => {}, 1000);
            } else if (isDeleting && charIndex === 0) {
                isDeleting = false; currentIndex = (currentIndex + 1) % suggestions.length;
            }
            const speed = isDeleting ? 50 : 100;
            typewriterInterval = setTimeout(typeWriter, speed);
        }

        window.startTypewriter = function() {
            if (!typewriterInterval && searchInput && searchInput !== document.activeElement) {
                currentIndex = 0; charIndex = 0; isDeleting = false; typeWriter();
            }
        };

        window.stopTypewriter = function() {
            if (typewriterInterval) {
                clearTimeout(typewriterInterval);
                typewriterInterval = null;
                searchInput.placeholder = "<?= htmlspecialchars($translations['search_placeholder'] ?? 'search_placeholder', ENT_QUOTES, 'UTF-8'); ?>";
            }
        };

        document.addEventListener('DOMContentLoaded', () => {
            window.startTypewriter();
            searchInput?.addEventListener('focus', window.stopTypewriter);
            searchInput?.addEventListener('blur',  () => { if (!searchInput.value) window.startTypewriter(); });
        });
    })();
</script>

<?php
require_once TEMPLATE_PATH . 'age_gate_modal.php';
require_once TEMPLATE_PATH . 'footer.php';
