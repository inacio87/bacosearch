<?php
/**
 * /sitemap.php - Gerador de Sitemap XML (VERSÃO AJUSTADA PARA NOVO DB)
 *
 * RESPONSABILIDADES:
 * 1. Gerar um sitemap XML completo para SEO.
 * 2. Incluir páginas estáticas e dinâmicas (provedores) com base na nova estrutura.
 * 3. Adicionar tags hreflang para internacionalização.
 *
 * ÚLTIMA ATUALIZAÇÃO: 05/07/2025 - Adaptado para o novo DB e URLs.
 */

header('Content-Type: application/xml; charset=utf-8');

// PASSO 1: INICIALIZAÇÃO CENTRAL
// AJUSTE: Usar bootstrap.php para carregar tudo (config, db, sessão, etc.)
require_once dirname(__FILE__) . '/core/bootstrap.php';


$db = getDBConnection(); // Obtém a conexão PDO via bootstrap/functions.php

// A função normalizeString é necessária para slugs (assumida como global via functions.php)
if (!function_exists('normalizeString')) {
    function normalizeString(string $string): string {
        $string = strtolower($string);
        $string = strtr($string, 'áàãâäéèêëíìîïóòõôöúùûüç', 'aaaaaeeeeiiiiooooouuuuc');
        $string = str_replace(['Á','À','Ã','Â','Ä','É','È','Ê','Ë','Í','Ì','Î','Ï','Ó','Ò','Õ','Ô','Ö','Ú','Ù','Û','Ü','Ç'],
                              ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c'], $string);
        return $string;
    }
}

// Função para formatar a data no formato W3C
function formatDate($date) {
    if (empty($date)) return date('Y-m-d'); // Fallback para data atual se a data for nula
    try {
        return (new DateTime($date))->format('Y-m-d');
    } catch (Exception $e) {
        log_system_error("Sitemap: Erro ao formatar data '{$date}': " . $e->getMessage(), 'warning', 'sitemap_date_format');
        return date('Y-m-d'); // Retorna a data atual em caso de erro de formatação
    }
}

// Iniciar o XML
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

// =======================================================================
// AJUSTE: Páginas Estáticas (URLs e Prioridades)
// =======================================================================
$staticPages = [
    [
        'loc' => SITE_URL . '/', // Página inicial
        'lastmod' => '2025-07-05',
        'changefreq' => 'daily',
        'priority' => '1.0'
    ],
    [
        'loc' => SITE_URL . '/pages/about_us.php',
        'lastmod' => '2025-07-05',
        'changefreq' => 'monthly',
        'priority' => '0.6'
    ],
    [
        'loc' => SITE_URL . '/pages/contact.php',
        'lastmod' => '2025-07-05',
        'changefreq' => 'monthly',
        'priority' => '0.7'
    ],
    [
        'loc' => SITE_URL . '/pages/terms_of_service.php',
        'lastmod' => '2025-07-05',
        'changefreq' => 'monthly',
        'priority' => '0.5'
    ],
    [
        'loc' => SITE_URL . '/pages/privacy_policy.php',
        'lastmod' => '2025-07-05',
        'changefreq' => 'monthly',
        'priority' => '0.5'
    ],
    [
        'loc' => SITE_URL . '/pages/cookie_policy.php',
        'lastmod' => '2025-07-05',
        'changefreq' => 'monthly',
        'priority' => '0.4'
    ],
    [
        'loc' => SITE_URL . '/pages/license.php', // Página de licenciamento
        'lastmod' => '2025-07-05',
        'changefreq' => 'weekly',
        'priority' => '0.7'
    ],
    [
        'loc' => SITE_URL . '/pages/register.php', // Página de registro geral
        'lastmod' => '2025-07-05',
        'changefreq' => 'weekly',
        'priority' => '0.8'
    ],
    [
        'loc' => SITE_URL . '/search.php', // Página de busca geral
        'lastmod' => '2025-07-05',
        'changefreq' => 'daily',
        'priority' => '0.9'
    ],
    // Adicionar links para tipos de busca específicos
    [
        'loc' => SITE_URL . '/search.php?type=providers', // Busca apenas por provedores
        'lastmod' => '2025-07-05',
        'changefreq' => 'daily',
        'priority' => '0.8'
    ],
    [
        'loc' => SITE_URL . '/search.php?type=businesses', // Busca apenas por negócios
        'lastmod' => '2025-07-05',
        'changefreq' => 'daily',
        'priority' => '0.8'
    ],
    [
        'loc' => SITE_URL . '/search.php?type=events', // Busca apenas por eventos/listagens de eventos
        'lastmod' => '2025-07-05',
        'changefreq' => 'daily',
        'priority' => '0.8'
    ],
    // Note: 'register_providers_public.php' e 'register_providers_private.php' foram consolidados.
    // 'register_providers.php' é um formulário de continuação, não uma página pública de SEO.
];

// Adicionar páginas estáticas com suporte a hreflang
foreach ($staticPages as $page) {
    $xml .= "  <url>\n";
    $xml .= "    <loc>" . htmlspecialchars($page['loc']) . "</loc>\n";
    $xml .= "    <lastmod>" . $page['lastmod'] . "</lastmod>\n";
    $xml .= "    <changefreq>" . $page['changefreq'] . "</changefreq>\n";
    $xml .= "    <priority>" . $page['priority'] . "</priority>\n";

    // Adicionar hreflang para cada idioma
    foreach (LANGUAGE_CONFIG['available'] as $lang) {
        $hreflang = str_replace('us', 'US', str_replace('br', 'BR', $lang)); // Ajuste de código para hreflang (ex: en-US, pt-BR)
        $lang_param_separator = (strpos($page['loc'], '?') !== false ? '&' : '?'); // Determina se usa '?' ou '&'
        $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"" . htmlspecialchars($hreflang) . "\" href=\"" . htmlspecialchars($page['loc'] . $lang_param_separator . "lang=" . $lang) . "\" />\n";
    }

    $xml .= "  </url>\n";
}

// =======================================================================
// AJUSTE: Páginas Dinâmicas (Provedores)
// (Inclui apenas provedores ativos e aprovados)
// =======================================================================
try {
    // AJUSTE: Fazer JOIN com accounts para verificar status da conta
    $stmt = $db->query("
        SELECT 
            p.id, 
            p.display_name, 
            p.created_at, 
            p.updated_at 
        FROM providers p
        JOIN accounts a ON p.account_id = a.id
        WHERE p.deleted_at IS NULL 
          AND p.is_active = TRUE 
          AND a.status = 'active' 
          AND p.profile_status = 'approved'
        ORDER BY p.id DESC -- Ordenar para consistência no sitemap
    ");
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($providers as $provider) {
        if (!isset($provider['id']) || !is_numeric($provider['id']) || empty($provider['display_name'])) continue;
        
        $lastmod = $provider['updated_at'] ? formatDate($provider['updated_at']) : formatDate($provider['created_at']);
        
        // AJUSTE: URL amigável do perfil do provedor (SITE_URL/profile/{id}-{slug_do_nome})
        $url_slug = urlencode(normalizeString($provider['display_name']));
        $url = SITE_URL . "/profile/" . urlencode($provider['id']) . "-" . $url_slug;

        $xml .= "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($url) . "</loc>\n";
        $xml .= "    <lastmod>" . $lastmod . "</lastmod>\n";
        $xml .= "    <changefreq>weekly</changefreq>\n";
        $xml .= "    <priority>0.8</priority>\n";

        // Adicionar hreflang para cada idioma
        foreach (LANGUAGE_CONFIG['available'] as $lang) {
            $hreflang = str_replace('us', 'US', str_replace('br', 'BR', $lang));
            $lang_param_separator = (strpos($url, '?') !== false ? '&' : '?');
            $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"" . htmlspecialchars($hreflang) . "\" href=\"" . htmlspecialchars($url . $lang_param_separator . "lang=" . $lang) . "\" />\n";
        }

        $xml .= "  </url>\n";
    }
} catch (Exception $e) {
    log_system_error("Sitemap: Erro ao buscar provedores: " . $e->getMessage(), 'ERROR', 'sitemap_providers_query');
}

// =======================================================================
// Opcional: Adicionar páginas dinâmicas para Businesses (Se aplicável)
// =======================================================================
/*
try {
    $stmt_biz = $db->query("
        SELECT 
            b.id, 
            b.business_name, 
            b.created_at, 
            b.updated_at 
        FROM businesses b
        JOIN accounts a ON b.account_id = a.id
        WHERE b.deleted_at IS NULL 
          AND a.status = 'active'
        ORDER BY b.id DESC
    ");
    $businesses = $stmt_biz->fetchAll(PDO::FETCH_ASSOC);

    foreach ($businesses as $business) {
        if (!isset($business['id']) || !is_numeric($business['id']) || empty($business['business_name'])) continue;
        
        $lastmod = $business['updated_at'] ? formatDate($business['updated_at']) : formatDate($business['created_at']);
        $url_slug = urlencode(normalizeString($business['business_name']));
        $url = SITE_URL . "/business/" . urlencode($business['id']) . "-" . $url_slug; // Exemplo de URL para negócios

        $xml .= "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($url) . "</loc>\n";
        $xml .= "    <lastmod>" . $lastmod . "</lastmod>\n";
        $xml .= "    <changefreq>weekly</changefreq>\n";
        $xml .= "    <priority>0.7</priority>\n";
        foreach (LANGUAGE_CONFIG['available'] as $lang) {
            $hreflang = str_replace('us', 'US', str_replace('br', 'BR', $lang));
            $lang_param_separator = (strpos($url, '?') !== false ? '&' : '?');
            $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"" . htmlspecialchars($hreflang) . "\" href=\"" . htmlspecialchars($url . $lang_param_separator . "lang=" . $lang) . "\" />\n";
        }
        $xml .= "  </url>\n";
    }
} catch (Exception $e) {
    log_system_error("Sitemap: Erro ao buscar negócios: " . $e->getMessage(), 'ERROR', 'sitemap_businesses_query');
}
*/

$xml .= '</urlset>';

// Output do XML
echo $xml;
?>