<?php
/**
 * Debug Search Page
 */
define('IN_BACOSEARCH', true);
require_once __DIR__ . '/core/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG SEARCH PAGE ===\n\n";

echo "1. CSS FILES:\n";
$css_path = __DIR__ . '/assets/css/search.css';
echo "   - search.css existe: " . (file_exists($css_path) ? 'SIM' : 'NÃO') . "\n";
echo "   - search.css tamanho: " . (file_exists($css_path) ? filesize($css_path) . ' bytes' : 'N/A') . "\n";

echo "\n2. TEMPLATE:\n";
$template_path = __DIR__ . '/templates/search-results-unified.php';
echo "   - Template existe: " . (file_exists($template_path) ? 'SIM' : 'NÃO') . "\n";
echo "   - Template tamanho: " . (file_exists($template_path) ? filesize($template_path) . ' bytes' : 'N/A') . "\n";

echo "\n3. TESTE DE BUSCA:\n";
try {
    $pdo = getDBConnection();
    $term = 'test';
    $like_term = '%' . $term . '%';
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM providers WHERE display_name LIKE :term");
    $stmt->execute([':term' => $like_term]);
    $count = $stmt->fetchColumn();
    echo "   - Providers encontrados com 'test': {$count}\n";
    
    echo "\n4. ESTRUTURA PROVIDERS:\n";
    $stmt = $pdo->query("DESCRIBE providers");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   - {$row['Field']} ({$row['Type']})\n";
    }
    
} catch (Exception $e) {
    echo "   ERRO: " . $e->getMessage() . "\n";
}

echo "\n5. SESSION:\n";
echo "   - Language: " . ($_SESSION['language'] ?? 'N/A') . "\n";
echo "   - City: " . ($_SESSION['city'] ?? 'N/A') . "\n";

echo "\n=== FIM DEBUG ===\n";
