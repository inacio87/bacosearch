<?php
/**
 * /search.php - Ponto de Entrada para Busca (VERSÃO REESTRUTURADA E FINAL)
 *
 * RESPONSABILIDADES:
 * 1. Segue o padrão arquitetural das outras páginas (about_us, register, etc.).
 * 2. Carrega bootstrap, prepara traduções e dados para o template.
 * 3. Executa uma busca híbrida e inteligente (FULLTEXT + LIKE + Intents).
 * 4. Renderiza a página de resultados chamando os templates head, header e footer.
 */

// PASSO 1: INICIALIZAÇÃO E DEFINIÇÃO DA PÁGINA
define('IN_BACOSEARCH', true);
require_once __DIR__ . '/core/bootstrap.php';

$page_name = 'search_page'; // Define o nome da página para o head.php

// PASSO 2: PREPARAÇÃO DE DADOS E TRADUÇÕES
$language_code = $_SESSION['language'] ?? (defined('LANGUAGE_CONFIG') ? LANGUAGE_CONFIG['default'] : 'pt-br');

// CSS específico desta página (não é tradução)
$page_specific_styles = [
    SITE_URL . '/assets/css/pages.css'
];

// Mapa centralizado de traduções (padrão index)
// Padronizamos 'detecting_location' em 'ui_messages'
$translations_map = [
    // Página de busca
    'search_title'           => 'search_page',
    'search_meta_description'=> 'search_page',
    'results_for'            => 'search_page',
    'no_results'             => 'search_page',
    'explore_suggestion'     => 'search_page',

    // Busca/header/footer
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

// Dados para header/template
$city = $_SESSION['city'] ?? ($translations['detecting_location'] ?? 'detecting_location');
$translations['languageOptionsForDisplay']     = LANGUAGE_CONFIG['name_map'] ?? [];
$translations['current_language_display_name'] = $translations['languageOptionsForDisplay'][$language_code] ?? $language_code;

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

// Meta tags do <head> — sem literais (fallback para o nome da chave)
$page_title       = $translations['search_title'] ?? 'search_title';
$meta_description = $translations['search_meta_description'] ?? 'search_meta_description';

// PASSO 3: LÓGICA PRINCIPAL DA BUSCA
$pdo = getDBConnection();
$results = [];
$totalResults = 0;
$totalPages = 0;
$itemsPerPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $itemsPerPage;

try {
    if (!empty($term)) {
        // Prepara o termo para FULLTEXT (ex.: +morena*)
        $search_terms = '+' . str_replace(' ', '* +', $term) . '*';

        // Contagem
        $countSql = "
            SELECT COUNT(DISTINCT p.id)
            FROM providers p
            WHERE
                (MATCH(p.display_name, p.ad_title, p.description, p.keywords) AGAINST (:search_terms IN BOOLEAN MODE)
                 OR p.keywords LIKE :like_term
                 OR p.display_name LIKE :like_term)
            AND p.is_active = 1 AND p.status = 'active'
        ";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute([
            ':search_terms' => $search_terms,
            ':like_term'    => '%' . $term . '%'
        ]);
        $totalResults = (int)$stmtCount->fetchColumn();
        $totalPages   = (int)ceil($totalResults / $itemsPerPage);

        if ($totalResults > 0) {
            // Principal com ranking
            $sql = "
                SELECT
                    p.id, p.display_name, p.ad_title, p.description,
                    pl.ad_city AS city, c.name AS country,
                    MATCH(p.display_name, p.ad_title, p.description, p.keywords) AGAINST (:search_terms IN BOOLEAN MODE) AS relevance
                FROM providers p
                LEFT JOIN provider_logistics pl ON p.id = pl.provider_id
                LEFT JOIN countries c ON p.nationality_id = c.id
                WHERE
                    (MATCH(p.display_name, p.ad_title, p.description, p.keywords) AGAINST (:search_terms IN BOOLEAN MODE)
                     OR p.keywords LIKE :like_term
                     OR p.display_name LIKE :like_term)
                AND p.is_active = 1 AND p.status = 'active'
                GROUP BY p.id
                ORDER BY relevance DESC
                LIMIT :offset, :itemsPerPage
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':search_terms', $search_terms, PDO::PARAM_STR);
            $stmt->bindValue(':like_term', '%' . $term . '%', PDO::PARAM_STR);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':itemsPerPage', $itemsPerPage, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        logGlobalSearch($pdo, $term);
    }
} catch (Exception $e) {
    // Logs não são exibidos ao utilizador; mantidos fora do sistema de traduções
    log_system_error("search.php error: " . $e->getMessage(), 'ERROR', 'search_failure');
    $results = [];
    $totalResults = 0;
    $totalPages = 0;
}

// PASSO 4: RENDERIZAÇÃO DA PÁGINA
$currentPage = $page;
require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';
require_once TEMPLATE_PATH . 'search-results.php';
require_once TEMPLATE_PATH . 'footer.php';
