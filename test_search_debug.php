<?php
/**
 * Test Search with Error Display
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('IN_BACOSEARCH', true);
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/search_expand_helpers.php';

echo "<pre>";
echo "=== TESTE DE BUSCA COM DEBUG ===\n\n";

$term = 'morena';
echo "Termo de busca: {$term}\n\n";

try {
    $pdo = getDBConnection();
    echo "✓ Conexão com banco OK\n\n";
    
    echo "Testando expandSearchTerm()...\n";
    $expanded = expandSearchTerm($term, $pdo);
    echo "Termos expandidos: " . implode(', ', $expanded) . "\n\n";
    
    echo "Testando buildExpandedSearchWhere()...\n";
    list($where, $params) = buildExpandedSearchWhere('display_name', $expanded);
    echo "WHERE: {$where}\n";
    echo "PARAMS: " . print_r($params, true) . "\n\n";
    
    echo "Testando busca real...\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM providers WHERE {$where}");
    $stmt->execute($params);
    $count = $stmt->fetchColumn();
    echo "Providers encontrados: {$count}\n\n";
    
    if ($count > 0) {
        $stmt = $pdo->prepare("SELECT id, display_name, gender FROM providers WHERE {$where} LIMIT 5");
        $stmt->execute($params);
        echo "Primeiros resultados:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - {$row['display_name']} ({$row['gender']})\n";
        }
    }
    
    echo "\n✓ SUCESSO!\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "\nStack Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
