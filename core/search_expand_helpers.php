<?php
/**
 * Search Helper Functions
 * Funções para expandir termos de busca e logging
 */

if (!defined('IN_BACOSEARCH')) { exit; }

/**
 * Expande um termo de busca usando sinônimos do search_intents
 */
if (!function_exists('expandSearchTerm')) {
function expandSearchTerm($term, $pdo) {
    $expanded = [$term];
    
    try {
        // Busca o termo e seus sinônimos
        $stmt = $pdo->prepare("
            SELECT term, synonyms 
            FROM search_intents 
            WHERE term = :term 
               OR JSON_CONTAINS(synonyms, JSON_QUOTE(:term))
            LIMIT 1
        ");
        $stmt->execute([':term' => strtolower($term)]);
        $intent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($intent) {
            // Adiciona o termo principal
            $expanded[] = $intent['term'];
            
            // Adiciona os sinônimos
            if (!empty($intent['synonyms'])) {
                $synonyms = json_decode($intent['synonyms'], true);
                if (is_array($synonyms)) {
                    $expanded = array_merge($expanded, $synonyms);
                }
            }
        }
        
        // Remove duplicatas e vazios
        $expanded = array_unique(array_filter($expanded));
        
    } catch (Exception $e) {
        // Silencioso - retorna apenas o termo original em caso de erro
    }
    
    return $expanded;
}
}

// ATENÇÃO: logGlobalSearch já existe em core/functions.php com assinatura diferente.
// Para evitar conflitos, NÃO redefinimos aqui.

/**
 * Registra uma busca no search_logs (com normalização)
 */
if (!function_exists('logSearchLog')) {
function logSearchLog($term, $results_count, $pdo, $visitor_id = null, $intent_category = null) {
    try {
        $normalized = strtolower(trim($term));
        $normalized = preg_replace('/[^a-z0-9\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        $stmt = $pdo->prepare("
            INSERT INTO search_logs 
            (term, normalized_term, intent_category, results_count, visitor_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $term,
            $normalized,
            $intent_category,
            $results_count,
            $visitor_id
        ]);
        
    } catch (Exception $e) {
        // Silencioso
        error_log("Error logging search_log: " . $e->getMessage());
    }
}
}

/**
 * Constrói cláusula WHERE para busca expandida
 */
if (!function_exists('buildExpandedSearchWhere')) {
function buildExpandedSearchWhere($column, $terms, $param_prefix = 'term') {
    if (empty($terms)) {
        return ['1=0', []];
    }
    
    $conditions = [];
    $params = [];
    
    foreach ($terms as $i => $term) {
        $param_name = ":{$param_prefix}_{$i}";
        $conditions[] = "{$column} LIKE {$param_name}";
        $params[$param_name] = '%' . $term . '%';
    }
    
    $where = '(' . implode(' OR ', $conditions) . ')';
    
    return [$where, $params];
}
}
