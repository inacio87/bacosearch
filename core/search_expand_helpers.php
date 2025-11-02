<?php
/**
 * Search Helper Functions
 * Funções para expandir termos de busca e logging
 */

if (!defined('IN_BACOSEARCH')) { exit; }

/**
 * Expande um termo de busca usando sinônimos do search_intents
 */
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

/**
 * Registra uma busca no global_searches
 */
function logGlobalSearch($term, $results_count, $pdo, $visitor_id = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $country_code = $_SESSION['country_code'] ?? 'unknown';
        $language_code = $_SESSION['language'] ?? 'pt-br';
        $lat = $_SESSION['latitude'] ?? null;
        $lon = $_SESSION['longitude'] ?? null;
        
        $metadata = json_encode([
            'user_lat' => $lat,
            'user_lon' => $lon,
            'country_code' => $country_code,
            'language_code' => $language_code
        ]);
        
        // Pega o próximo ID
        $nextId = $pdo->query("SELECT IFNULL(MAX(id), 0) + 1 FROM global_searches")->fetchColumn();
        
        $stmt = $pdo->prepare("
            INSERT INTO global_searches 
            (id, term, visitor_id, ip_address, results_count, created_at, metadata)
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $stmt->execute([
            $nextId,
            $term,
            $visitor_id,
            $ip,
            $results_count,
            $metadata
        ]);
        
    } catch (Exception $e) {
        // Silencioso - não deve quebrar a busca
        error_log("Error logging search: " . $e->getMessage());
    }
}

/**
 * Registra uma busca no search_logs (com normalização)
 */
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

/**
 * Constrói cláusula WHERE para busca expandida
 */
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
