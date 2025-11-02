<?php
/**
 * Script de debug temporário para verificar e popular dados
 */
define('IN_BACOSEARCH', true);
require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG DATABASE ===\n\n";

try {
    $pdo = getDBConnection();
    
    // 1. Verifica se tabela providers existe
    echo "1. Verificando tabela PROVIDERS:\n";
    $tables = $pdo->query("SHOW TABLES LIKE 'providers'")->fetchAll();
    echo "Tabela providers existe: " . (count($tables) > 0 ? "SIM" : "NÃO") . "\n\n";
    
    if (count($tables) > 0) {
        // 2. Verifica estrutura
        echo "2. Estrutura da tabela providers:\n";
        $columns = $pdo->query("DESCRIBE providers")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
        echo "\n";
        
        // 3. Conta registros
        $count = $pdo->query("SELECT COUNT(*) FROM providers")->fetchColumn();
        echo "3. Total de registros em providers: $count\n\n";
        
        // 4. Busca por 'morena'
        echo "4. Buscando por 'morena':\n";
        $stmt = $pdo->prepare("
            SELECT id, display_name, ad_title, description, keywords, status, is_active
            FROM providers 
            WHERE display_name LIKE :term 
               OR ad_title LIKE :term 
               OR description LIKE :term 
               OR keywords LIKE :term
            LIMIT 5
        ");
        $stmt->execute([':term' => '%morena%']);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($results) > 0) {
            foreach ($results as $r) {
                echo "  ID: {$r['id']}\n";
                echo "  Nome: {$r['display_name']}\n";
                echo "  Título: {$r['ad_title']}\n";
                echo "  Status: {$r['status']} | Ativo: {$r['is_active']}\n";
                echo "  ---\n";
            }
        } else {
            echo "  NENHUM RESULTADO com 'morena'\n";
        }
        echo "\n";
        
        // 5. Mostra primeiros 5 registros
        echo "5. Primeiros 5 registros (qualquer um):\n";
        $stmt = $pdo->query("SELECT id, display_name, ad_title, status, is_active FROM providers LIMIT 5");
        $any = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($any) > 0) {
            foreach ($any as $r) {
                echo "  ID: {$r['id']} | Nome: {$r['display_name']} | Status: {$r['status']} | Ativo: {$r['is_active']}\n";
            }
        } else {
            echo "  TABELA VAZIA - sem nenhum registro\n";
        }
    }
    
    echo "\n=== FIM DEBUG ===\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}
