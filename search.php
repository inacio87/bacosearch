<?php
/**
 * /search.php - Busca Unificada (Providers, Companies, Clubs, Services)
 * Limpo e determinístico, com paginação por aba e contadores.
 */

// Debug opcional (desativar em produção)
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

define('IN_BACOSEARCH', true);

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/search_expand_helpers.php';

$page_name = 'search_page';

// Traduções e estilos básicos (meta ficará no head.php)
$language_code = $_SESSION['language'] ?? (defined('LANGUAGE_CONFIG') ? LANGUAGE_CONFIG['default'] : 'pt-br');
$page_specific_styles = [SITE_URL . '/assets/css/pages.css'];

$translations_map = [
    'search_title'            => 'search_page',
    'search_meta_description' => 'search_page',
    'results_for'             => 'search_page',
    'no_results'              => 'search_page',
    'explore_suggestion'      => 'search_page',
    'search_placeholder'      => 'search',
    'header_ads'              => 'header',
    'header_login'            => 'header',
    'logo_alt'                => 'header',
    'header_menu'             => 'header',
    'detecting_location'      => 'ui_messages',
    'footer_providers'        => 'footer',
    'footer_companies'        => 'footer',
    'footer_streets'          => 'footer',
    'footer_clubs'            => 'footer',
    'footer_services'         => 'footer',
];

$translations = [];
foreach ($translations_map as $key => $context) {
    $translations[$key] = getTranslation($key, $language_code, $context);
}
$translations['languageOptionsForDisplay']     = LANGUAGE_CONFIG['name_map'] ?? [];
$translations['current_language_display_name'] = $translations['languageOptionsForDisplay'][$language_code] ?? $language_code;

$term = isset($_GET['term']) ? trim((string)$_GET['term']) : '';
$page_title       = $translations['search_title'] ?? 'search_title';
$meta_description = $translations['search_meta_description'] ?? 'search_meta_description';

// Estado inicial
$pdo = getDBConnection();
$providersResults = $companiesResults = $clubsResults = $servicesResults = [];
$totalProviders = $totalCompanies = $totalClubs = $totalServices = $totalResults = 0;

$itemsPerPage = 10;
$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }
$offset = ($page - 1) * $itemsPerPage;
$activeTab = isset($_GET['tab']) ? trim((string)$_GET['tab']) : 'all';

try {
    if ($term !== '') {
        // Providers usam termos expandidos (display_name, ad_title, description)
        $expandedTerms = expandSearchTerm($term, $pdo);
        list($w1, $p1) = buildExpandedSearchWhere('p.display_name', $expandedTerms, 'pterm');
        list($w2, $p2) = buildExpandedSearchWhere('p.ad_title', $expandedTerms, 'pt2');
        list($w3, $p3) = buildExpandedSearchWhere('p.description', $expandedTerms, 'pt3');
        $providersWhereClause = '(' . $w1 . ' OR ' . $w2 . ' OR ' . $w3 . ')';
        $providersWhereParams = $p1 + $p2 + $p3;

        // LIKE básico para demais verticais
        $like_term = '%' . $term . '%';

        // Providers
        try {
            $st = $pdo->prepare("SELECT COUNT(DISTINCT p.id) FROM providers p WHERE {$providersWhereClause}");
            $st->execute($providersWhereParams);
            $totalProviders = (int)$st->fetchColumn();

            if ($totalProviders > 0 && ($activeTab === 'all' || $activeTab === 'providers')) {
                $limit = ($activeTab === 'providers') ? $itemsPerPage : 5;
                $offsetVal = ($activeTab === 'providers') ? $offset : 0;
                $q = $pdo->prepare(
                    "SELECT p.id, p.display_name, p.gender, 'provider' AS result_type
                     FROM providers p
                     WHERE {$providersWhereClause}
                     ORDER BY p.updated_at DESC
                     LIMIT :limit OFFSET :offset"
                );
                foreach ($providersWhereParams as $k => $v) { $q->bindValue($k, $v, PDO::PARAM_STR); }
                $q->bindValue(':limit', $limit, PDO::PARAM_INT);
                $q->bindValue(':offset', $offsetVal, PDO::PARAM_INT);
                $q->execute();
                $providersResults = $q->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) { log_system_error('Search providers: '.$e->getMessage(), 'ERROR', 'search'); }

        // Companies
        try {
            $st = $pdo->prepare(
                "SELECT COUNT(DISTINCT id) FROM companies
                 WHERE (company_name LIKE :t OR description LIKE :t OR keywords LIKE :t)"
            );
            $st->execute([':t' => $like_term]);
            $totalCompanies = (int)$st->fetchColumn();

            if ($totalCompanies > 0 && ($activeTab === 'all' || $activeTab === 'companies')) {
                $limit = ($activeTab === 'companies') ? $itemsPerPage : 5;
                $offsetVal = ($activeTab === 'companies') ? $offset : 0;
                $q = $pdo->prepare(
                    "SELECT id, company_name AS display_name, description,
                            ad_city AS city, ad_country AS country, 'company' AS result_type
                     FROM companies
                     WHERE (company_name LIKE :t OR description LIKE :t OR keywords LIKE :t)
                     ORDER BY updated_at DESC
                     LIMIT :limit OFFSET :offset"
                );
                $q->bindValue(':t', $like_term, PDO::PARAM_STR);
                $q->bindValue(':limit', $limit, PDO::PARAM_INT);
                $q->bindValue(':offset', $offsetVal, PDO::PARAM_INT);
                $q->execute();
                $companiesResults = $q->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) { log_system_error('Search companies: '.$e->getMessage(), 'ERROR', 'search'); }

        // Clubs
        try {
            $st = $pdo->prepare(
                "SELECT COUNT(DISTINCT id) FROM clubs
                 WHERE (club_name LIKE :t OR description LIKE :t OR keywords LIKE :t)"
            );
            $st->execute([':t' => $like_term]);
            $totalClubs = (int)$st->fetchColumn();

            if ($totalClubs > 0 && ($activeTab === 'all' || $activeTab === 'clubs')) {
                $limit = ($activeTab === 'clubs') ? $itemsPerPage : 5;
                $offsetVal = ($activeTab === 'clubs') ? $offset : 0;
                $q = $pdo->prepare(
                    "SELECT id, club_name AS display_name, description,
                            ad_city AS city, ad_country AS country, 'club' AS result_type
                     FROM clubs
                     WHERE (club_name LIKE :t OR description LIKE :t OR keywords LIKE :t)
                     ORDER BY updated_at DESC
                     LIMIT :limit OFFSET :offset"
                );
                $q->bindValue(':t', $like_term, PDO::PARAM_STR);
                $q->bindValue(':limit', $limit, PDO::PARAM_INT);
                $q->bindValue(':offset', $offsetVal, PDO::PARAM_INT);
                $q->execute();
                $clubsResults = $q->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) { log_system_error('Search clubs: '.$e->getMessage(), 'ERROR', 'search'); }

        // Services (services_listings)
        try {
            $st = $pdo->prepare(
                "SELECT COUNT(DISTINCT id) FROM services_listings
                 WHERE (service_title LIKE :t OR description LIKE :t)"
            );
            $st->execute([':t' => $like_term]);
            $totalServices = (int)$st->fetchColumn();

            if ($totalServices > 0 && ($activeTab === 'all' || $activeTab === 'services')) {
                $limit = ($activeTab === 'services') ? $itemsPerPage : 5;
                $offsetVal = ($activeTab === 'services') ? $offset : 0;
                $q = $pdo->prepare(
                    "SELECT id, service_title AS display_name, description,
                            ad_city AS city, ad_country AS country, 'service' AS result_type
                     FROM services_listings
                     WHERE (service_title LIKE :t OR description LIKE :t)
                     ORDER BY updated_at DESC
                     LIMIT :limit OFFSET :offset"
                );
                $q->bindValue(':t', $like_term, PDO::PARAM_STR);
                $q->bindValue(':limit', $limit, PDO::PARAM_INT);
                $q->bindValue(':offset', $offsetVal, PDO::PARAM_INT);
                $q->execute();
                $servicesResults = $q->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) { log_system_error('Search services: '.$e->getMessage(), 'ERROR', 'search'); }

        // Totais, logging
        $totalResults = $totalProviders + $totalCompanies + $totalClubs + $totalServices;
        try { logGlobalSearch($pdo, (string)$term); } catch (Throwable $e) { /* noop */ }
        $visitor_id = $_SESSION['visitor_db_id'] ?? null;
        if (function_exists('logSearchLog')) {
            try { logSearchLog($term, $totalResults, $pdo, $visitor_id); } catch (Throwable $e) { /* noop */ }
        }
    }
} catch (Throwable $e) {
    log_system_error('search.php error: '.$e->getMessage(), 'ERROR', 'search_failure');
}

// Paginação (apenas para a aba ativa, quando não for "all")
$totalPages = 0;
$currentPage = $page;
if ($activeTab === 'providers' && $totalProviders > 0) {
    $totalPages = (int)ceil($totalProviders / $itemsPerPage);
} elseif ($activeTab === 'companies' && $totalCompanies > 0) {
    $totalPages = (int)ceil($totalCompanies / $itemsPerPage);
} elseif ($activeTab === 'clubs' && $totalClubs > 0) {
    $totalPages = (int)ceil($totalClubs / $itemsPerPage);
} elseif ($activeTab === 'services' && $totalServices > 0) {
    $totalPages = (int)ceil($totalServices / $itemsPerPage);
}

// Renderização
require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';
require_once TEMPLATE_PATH . 'search-results-unified.php';
require_once TEMPLATE_PATH . 'footer.php';

?>

