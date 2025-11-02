<?php
/**
 * /search.php - Busca Unificada Estilo Google
 * Busca em: Providers, Companies, Clubs, Services
 */

// Debug mode temporário
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('IN_BACOSEARCH', true);

try {
    require_once __DIR__ . '/core/bootstrap.php';
} catch (Exception $e) {
    die("Erro no bootstrap: " . $e->getMessage());
}

try {
    require_once __DIR__ . '/core/search_expand_helpers.php';
} catch (Exception $e) {
    die("Erro ao carregar search_expand_helpers.php: " . $e->getMessage());
}

$page_name = 'search_page';

// Preparação de dados
$language_code = $_SESSION['language'] ?? (defined('LANGUAGE_CONFIG') ? LANGUAGE_CONFIG['default'] : 'pt-br');
$page_specific_styles = [SITE_URL . '/assets/css/pages.css', SITE_URL . '/assets/css/search.css'];

$translations_map = [
    'search_title'           => 'search_page',
    'search_meta_description'=> 'search_page',
    'results_for'            => 'search_page',
    'no_results'             => 'search_page',
    'explore_suggestion'     => 'search_page',
    'search_placeholder'     => 'search',
    'header_ads'             => 'header',
    'header_login'           => 'header',
    'logo_alt'               => 'header',
    'header_menu'            => 'header',
    'detecting_location'     => 'ui_messages',
    'footer_providers'       => 'footer',
    'footer_companies'       => 'footer',
    'footer_streets'         => 'footer',
    'footer_clubs'           => 'footer',
    'footer_services'        => 'footer',
];

$translations = [];
foreach ($translations_map as $key => $context) {
    $translations[$key] = getTranslation($key, $language_code, $context);
}

$city = $_SESSION['city'] ?? ($translations['detecting_location'] ?? 'detecting_location');
$translations['languageOptionsForDisplay']     = LANGUAGE_CONFIG['name_map'] ?? [];
$translations['current_language_display_name'] = $translations['languageOptionsForDisplay'][$language_code] ?? $language_code;

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$page_title       = $translations['search_title'] ?? 'search_title';
$meta_description = $translations['search_meta_description'] ?? 'search_meta_description';

// BUSCA UNIFICADA
$pdo = getDBConnection();
$providersResults = [];
$companiesResults = [];
$clubsResults = [];
$servicesResults = [];

$totalProviders = 0;
$totalCompanies = 0;
$totalClubs = 0;
$totalServices = 0;
$totalResults = 0;

$itemsPerPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $itemsPerPage;
$activeTab = isset($_GET['tab']) ? trim($_GET['tab']) : 'all';

try {
    if (!empty($term)) {
        // EXPANDE O TERMO COM SINÔNIMOS DO search_intents
        $expandedTerms = expandSearchTerm($term, $pdo);
        
        // Constrói padrões LIKE para todos os termos expandidos
        $likePatterns = array_map(function($t) { return '%' . $t . '%'; }, $expandedTerms);
        
        // Para queries simples (companies, clubs, services) - mantém compatibilidade
        $like_term = '%' . $term . '%';
        
        // 1. PROVIDERS - Busca expandida com sinônimos
        try {
            // Constrói WHERE dinâmico para busca expandida
            list($whereClause, $whereParams) = buildExpandedSearchWhere('p.display_name', $expandedTerms, 'pterm');
            
            $countProvidersStmt = $pdo->prepare("
                SELECT COUNT(DISTINCT p.id)
                FROM providers p
                WHERE {$whereClause}
            ");
            $countProvidersStmt->execute($whereParams);
            $totalProviders = (int)$countProvidersStmt->fetchColumn();

            if ($totalProviders > 0 && ($activeTab === 'all' || $activeTab === 'providers')) {
                $limit = ($activeTab === 'providers') ? $itemsPerPage : 5;
                $offsetVal = ($activeTab === 'providers') ? $offset : 0;
                
                $providersStmt = $pdo->prepare("
                    SELECT p.id, p.display_name, p.gender,
                           c.name AS country, 'provider' AS result_type
                    FROM providers p
                    LEFT JOIN countries c ON p.nationality_id = c.id
                    WHERE {$whereClause}
                    GROUP BY p.id
                    ORDER BY p.updated_at DESC
                    LIMIT :limit OFFSET :offset
                ");
                
                foreach ($whereParams as $key => $val) {
                    $providersStmt->bindValue($key, $val, PDO::PARAM_STR);
                }
                $providersStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $providersStmt->bindValue(':offset', $offsetVal, PDO::PARAM_INT);
                $providersStmt->execute();
                $providersResults = $providersStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            log_system_error("Providers search error: " . $e->getMessage(), 'ERROR', 'search_providers');
        }

        // 2. COMPANIES
        try {
            $countCompaniesStmt = $pdo->prepare("
                SELECT COUNT(DISTINCT id)
                FROM companies
                WHERE (company_name LIKE :term 
                   OR description LIKE :term 
                   OR keywords LIKE :term)
            ");
            $countCompaniesStmt->execute([':term' => $like_term]);
            $totalCompanies = (int)$countCompaniesStmt->fetchColumn();

            if ($totalCompanies > 0 && ($activeTab === 'all' || $activeTab === 'companies')) {
                $limit = ($activeTab === 'companies') ? $itemsPerPage : 5;
                $offsetVal = ($activeTab === 'companies') ? $offset : 0;
                
                $companiesStmt = $pdo->prepare("
                    SELECT id, company_name AS display_name, description, 
                           city, country, 'company' AS result_type
                    FROM companies
                    WHERE (company_name LIKE :term 
                       OR description LIKE :term 
                       OR keywords LIKE :term)
                    ORDER BY 
                        CASE WHEN company_name LIKE :term THEN 1 ELSE 2 END,
                        updated_at DESC
                    LIMIT :limit OFFSET :offset
                ");
                $companiesStmt->bindValue(':term', $like_term, PDO::PARAM_STR);
                $companiesStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $companiesStmt->bindValue(':offset', $offsetVal, PDO::PARAM_INT);
                $companiesStmt->execute();
                $companiesResults = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            log_system_error("Companies search error: " . $e->getMessage(), 'ERROR', 'search_companies');
        }

        // 3. CLUBS
        try {
            $countClubsStmt = $pdo->prepare("
                SELECT COUNT(DISTINCT id)
                FROM clubs
                WHERE (club_name LIKE :term 
                   OR description LIKE :term 
                   OR keywords LIKE :term)
            ");
            $countClubsStmt->execute([':term' => $like_term]);
            $totalClubs = (int)$countClubsStmt->fetchColumn();

            if ($totalClubs > 0 && ($activeTab === 'all' || $activeTab === 'clubs')) {
                $limit = ($activeTab === 'clubs') ? $itemsPerPage : 5;
                $offsetVal = ($activeTab === 'clubs') ? $offset : 0;
                
                $clubsStmt = $pdo->prepare("
                    SELECT id, club_name AS display_name, description, 
                           city, country, 'club' AS result_type
                    FROM clubs
                    WHERE (club_name LIKE :term 
                       OR description LIKE :term 
                       OR keywords LIKE :term)
                    ORDER BY 
                        CASE WHEN club_name LIKE :term THEN 1 ELSE 2 END,
                        updated_at DESC
                    LIMIT :limit OFFSET :offset
                ");
                $clubsStmt->bindValue(':term', $like_term, PDO::PARAM_STR);
                $clubsStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $clubsStmt->bindValue(':offset', $offsetVal, PDO::PARAM_INT);
                $clubsStmt->execute();
                $clubsResults = $clubsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            log_system_error("Clubs search error: " . $e->getMessage(), 'ERROR', 'search_clubs');
        }

        // 4. SERVICES
        try {
            $countServicesStmt = $pdo->prepare("
                SELECT COUNT(DISTINCT id)
                FROM providers_services_list
                WHERE term LIKE :term OR service_key LIKE :term
            ");
            $countServicesStmt->execute([':term' => $like_term]);
            $totalServices = (int)$countServicesStmt->fetchColumn();

            if ($totalServices > 0 && ($activeTab === 'all' || $activeTab === 'services')) {
                $limit = ($activeTab === 'services') ? $itemsPerPage : 5;
                $offsetVal = ($activeTab === 'services') ? $offset : 0;
                
                $servicesStmt = $pdo->prepare("
                    SELECT id, term AS display_name, service_key AS description,
                           'service' AS result_type
                    FROM providers_services_list
                    WHERE term LIKE :term OR service_key LIKE :term
                    ORDER BY 
                        CASE WHEN term LIKE :term THEN 1 ELSE 2 END,
                        term ASC
                    LIMIT :limit OFFSET :offset
                ");
                $servicesStmt->bindValue(':term', $like_term, PDO::PARAM_STR);
                $servicesStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $servicesStmt->bindValue(':offset', $offsetVal, PDO::PARAM_INT);
                $servicesStmt->execute();
                $servicesResults = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            log_system_error("Services search error: " . $e->getMessage(), 'ERROR', 'search_services');
        }

        $totalResults = $totalProviders + $totalCompanies + $totalClubs + $totalServices;
        
        // REGISTRA A BUSCA PARA ANALYTICS (global_searches + search_logs)
        $visitor_id = $_SESSION['visitor_db_id'] ?? null;
        logGlobalSearch($term, $totalResults, $pdo, $visitor_id);
        logSearchLog($term, $totalResults, $pdo, $visitor_id);
    }
} catch (Exception $e) {
    log_system_error("search.php error: " . $e->getMessage(), 'ERROR', 'search_failure');
}

// Paginação
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
