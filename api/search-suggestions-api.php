<?php
/**
 * /search-suggestions-api.php - API para sugestões de busca em tempo real (VERSÃO AJUSTADA)
 *
 * RESPONSABILIDADES:
 * 1. Receber termo de busca via AJAX.
 * 2. Buscar sugestões relevantes no banco de dados, utilizando a tabela `translations`.
 * 3. Complementar com buscas populares e providers.
 * 4. Formatar e retornar a resposta em JSON com fallback para sugestões estáticas.
 */

// Em produção, é recomendado desativar a exibição de erros para o utilizador.
// ini_set('display_errors', 0);
// error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// PASSO 1: INICIALIZAÇÃO CENTRAL
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/bootstrap.php';

// Função de normalização de strings
if (!function_exists('normalizeString')) {
    function normalizeString(string $string): string {
        $string = mb_strtolower($string, 'UTF-8');
        $string = strtr($string, 'áàãâäéèêëíìîïóòõôöúùûüç', 'aaaaaeeeeiiiiooooouuuuc');
        return $string;
    }
}

$response = ['suggestions' => []];
$languageCode = $_SESSION['language'] ?? (defined('LANGUAGE_CONFIG') ? LANGUAGE_CONFIG['default'] : 'en-us');

try {
    $user_query = isset($_GET['q']) ? trim($_GET['q']) : '';

    if (mb_strlen($user_query, 'UTF-8') >= 2) {
        $pdo = getDBConnection();
        $normalizedUserQuery = normalizeString($user_query);

        // --- Busca de sugestões na tabela translations com context = 'search' ---
        $serviceSuggestions = [];

        $stmtTranslatedSuggestions = $pdo->prepare("
            SELECT translation_key, translation_value
            FROM translations
            WHERE context = 'search'
              AND language_code = :language_code
              AND LOWER(translation_value) LIKE :term_like
            ORDER BY 
                CASE
                    WHEN LOWER(translation_value) = :exact_term THEN 0
                    WHEN LOWER(translation_value) LIKE :term_prefix THEN 1
                    WHEN LOWER(translation_value) LIKE :term_like THEN 2
                    ELSE 3
                END ASC,
                translation_value ASC
            LIMIT 10
        ");

        $stmtTranslatedSuggestions->execute([
            ':language_code' => $languageCode,
            ':term_like' => '%' . $normalizedUserQuery . '%',
            ':exact_term' => $normalizedUserQuery,
            ':term_prefix' => $normalizedUserQuery . '%'
        ]);

        $rawServiceSuggestions = $stmtTranslatedSuggestions->fetchAll(PDO::FETCH_ASSOC);

        // Processar sugestões
        $processedServiceSuggestions = [];
        $seenServiceKeys = [];

        foreach ($rawServiceSuggestions as $suggestion) {
            $serviceKey = $suggestion['translation_key'];
            if (isset($seenServiceKeys[$serviceKey])) {
                continue;
            }
            $seenServiceKeys[$serviceKey] = true;

            $processedServiceSuggestions[] = [
                'text' => $suggestion['translation_value'],
                'type' => 'service',
                'icon' => 'fas fa-hand-sparkles',
                'score' => 1 // Pontuação padrão
            ];
        }

        // Ordenar e limitar
        usort($processedServiceSuggestions, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        $serviceSuggestions = array_slice($processedServiceSuggestions, 0, 5);

        // --- Busca por buscas populares ---
        $stmt_popular = $pdo->prepare("
            SELECT term, COUNT(*) as frequency 
            FROM global_searches 
            WHERE LOWER(term) LIKE :termPrefix 
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
            GROUP BY term 
            ORDER BY frequency DESC, term ASC 
            LIMIT 5
        ");
        $stmt_popular->execute([
            ':termPrefix' => $normalizedUserQuery . '%'
        ]);
        $popularSearches = $stmt_popular->fetchAll(PDO::FETCH_ASSOC);

        // --- Busca por providers ---
        $stmt_providers = $pdo->prepare("
            SELECT DISTINCT p.display_name as suggestion 
            FROM providers p 
            JOIN accounts a ON p.account_id = a.id 
            WHERE LOWER(p.display_name) LIKE :termPrefix 
              AND p.deleted_at IS NULL 
              AND p.is_active = 1 
              AND a.status = 'active' 
            LIMIT 5
        ");
        $stmt_providers->execute([
            ':termPrefix' => $normalizedUserQuery . '%'
        ]);
        $providerSuggestions = $stmt_providers->fetchAll(PDO::FETCH_ASSOC);

        // --- Combina todas as sugestões ---
        $allSuggestions = [];

        foreach ($serviceSuggestions as $suggestion) {
            $allSuggestions[] = [
                'text' => $suggestion['text'],
                'type' => 'service',
                'icon' => 'fas fa-hand-sparkles'
            ];
        }

        foreach ($popularSearches as $search) {
            $allSuggestions[] = [
                'text' => $search['term'],
                'type' => 'popular',
                'icon' => 'fas fa-fire'
            ];
        }

        foreach ($providerSuggestions as $provider) {
            $allSuggestions[] = [
                'text' => $provider['suggestion'],
                'type' => 'provider',
                'icon' => 'fas fa-user'
            ];
        }

        // --- Filtra sugestões únicas ---
        $uniqueSuggestions = [];
        $seen = [];
        $maxDisplaySuggestions = 8;

        foreach ($allSuggestions as $suggestion) {
            $key = mb_strtolower($suggestion['text'], 'UTF-8');
            if (!isset($seen[$key]) && count($uniqueSuggestions) < $maxDisplaySuggestions) {
                $seen[$key] = true;
                $uniqueSuggestions[] = $suggestion;
            }
        }

        // --- Fallback para sugestões estáticas ---
        if (empty($uniqueSuggestions)) {
            $static_suggestions = [
                ['text' => 'Acompanhantes Verificadas', 'type' => 'provider', 'icon' => 'fas fa-user'],
                ['text' => 'Massagens Sensuais', 'type' => 'service', 'icon' => 'fas fa-spa'],
                ['text' => 'Explorar Motéis', 'type' => 'location', 'icon' => 'fas fa-map-marker-alt'],
                ['text' => 'Chamada de Vídeo', 'type' => 'digital', 'icon' => 'fas fa-video'],
                ['text' => 'Clubes e Festas', 'type' => 'event', 'icon' => 'fas fa-glass-cheers'],
                ['text' => 'Experiências para Casais', 'type' => 'service', 'icon' => 'fas fa-heart'],
                ['text' => 'Fotógrafos', 'type' => 'service', 'icon' => 'fas fa-camera'],
                ['text' => 'Acompanhantes Trans', 'type' => 'provider', 'icon' => 'fas fa-user'],
                ['text' => 'Experiências de Luxo', 'type' => 'service', 'icon' => 'fas fa-gem'],
                ['text' => 'Locais Discretos', 'type' => 'location', 'icon' => 'fas fa-user-secret']
            ];
            $uniqueSuggestions = array_slice($static_suggestions, 0, $maxDisplaySuggestions);
        }

        $response['suggestions'] = $uniqueSuggestions;
    }

} catch (Exception $e) {
    log_system_error("search-suggestions-api.php: Erro geral na busca por sugestões: " . $e->getMessage(), 'error', 'search_suggestions_api');
    // Retorna sugestões vazias em caso de erro
} finally {
    http_response_code(200);
    echo json_encode($response);
    exit;
}