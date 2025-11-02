<?php
/**
 * Test Search - Diagnóstico Simplificado
 * Acesse: https://bacosearch.com/test_search_simple.php?term=morena
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "=== TEST SEARCH DEBUG ===\n\n";

$term = $_GET['term'] ?? 'morena';
echo "Termo de busca: $term\n\n";

// Teste 1: Config
echo "1. Testando config.php...\n";
try {
    define('IN_BACOSEARCH', true);
    require_once __DIR__ . '/core/config.php';
    echo "   ✓ Config carregado\n";
} catch (Exception $e) {
    echo "   ✗ ERRO: " . $e->getMessage() . "\n";
    exit;
}

// Teste 2: Functions
echo "\n2. Testando functions.php...\n";
try {
    require_once __DIR__ . '/core/functions.php';
    echo "   ✓ Functions carregado\n";
} catch (Exception $e) {
    echo "   ✗ ERRO: " . $e->getMessage() . "\n";
    exit;
}

// Teste 3: DB Connection
echo "\n3. Testando conexão DB...\n";
try {
    $pdo = getDBConnection();
    echo "   ✓ Conexão estabelecida\n";
} catch (Exception $e) {
    echo "   ✗ ERRO: " . $e->getMessage() . "\n";
    exit;
}

// Teste 4: Search Expand Helpers
echo "\n4. Testando search_expand_helpers.php...\n";
try {
    if (file_exists(__DIR__ . '/core/search_expand_helpers.php')) {
        require_once __DIR__ . '/core/search_expand_helpers.php';
        echo "   ✓ Arquivo existe e foi carregado\n";
    } else {
        echo "   ✗ Arquivo NÃO encontrado\n";
        exit;
    }
} catch (Exception $e) {
    echo "   ✗ ERRO ao carregar: " . $e->getMessage() . "\n";
    exit;
}

// Teste 5: expandSearchTerm
echo "\n5. Testando expandSearchTerm()...\n";
try {
    if (function_exists('expandSearchTerm')) {
        $expanded = expandSearchTerm($term, $pdo);
        echo "   ✓ Função existe\n";
        echo "   Termos expandidos: " . json_encode($expanded, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "   ✗ Função NÃO existe\n";
        exit;
    }
} catch (Exception $e) {
    echo "   ✗ ERRO: " . $e->getMessage() . "\n";
    echo "   Stack: " . $e->getTraceAsString() . "\n";
    exit;
}

// Teste 6: buildExpandedSearchWhere
echo "\n6. Testando buildExpandedSearchWhere()...\n";
try {
    if (function_exists('buildExpandedSearchWhere')) {
        list($whereClause, $whereParams) = buildExpandedSearchWhere('p.display_name', $expanded, 'pterm');
        echo "   ✓ Função existe\n";
        echo "   WHERE: $whereClause\n";
        echo "   PARAMS: " . json_encode($whereParams, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "   ✗ Função NÃO existe\n";
        exit;
    }
} catch (Exception $e) {
    echo "   ✗ ERRO: " . $e->getMessage() . "\n";
    exit;
}

// Teste 7: Query Real
echo "\n7. Testando query real em providers...\n";
try {
    $sql = "SELECT COUNT(DISTINCT p.id) FROM providers p WHERE $whereClause";
    echo "   SQL: $sql\n";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($whereParams);
    $count = $stmt->fetchColumn();
    
    echo "   ✓ Query executada com sucesso\n";
    echo "   Resultados encontrados: $count\n";
} catch (Exception $e) {
    echo "   ✗ ERRO: " . $e->getMessage() . "\n";
    echo "   Stack: " . $e->getTraceAsString() . "\n";
    exit;
}

// Teste 8: Variável $like_term
echo "\n8. Testando variável \$like_term...\n";
$like_term = '%' . $term . '%';
echo "   \$like_term = '$like_term'\n";

echo "\n=== TODOS OS TESTES PASSARAM ===\n";
echo "\nO problema deve estar em search.php. Vou criar uma versão simplificada...\n";
