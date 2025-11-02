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
        
        // 4. Busca por 'morena' (só com colunas que existem)
        echo "4. Buscando por 'morena':\n";
        $stmt = $pdo->prepare("
            SELECT id, display_name, gender, status
            FROM providers 
            WHERE display_name LIKE :term
            LIMIT 5
        ");
        $stmt->execute([':term' => '%morena%']);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($results) > 0) {
            foreach ($results as $r) {
                echo "  ID: {$r['id']}\n";
                echo "  Nome: {$r['display_name']}\n";
                echo "  Status: {$r['status']}\n";
                echo "  ---\n";
            }
        } else {
            echo "  NENHUM RESULTADO com 'morena'\n";
        }
        echo "\n";
        
        // 5. Mostra primeiros 5 registros
        echo "5. Primeiros 5 registros (qualquer um):\n";
        $stmt = $pdo->query("SELECT id, display_name, gender, status FROM providers LIMIT 5");
        $any = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($any) > 0) {
            foreach ($any as $r) {
                echo "  ID: {$r['id']} | Nome: {$r['display_name']} | Status: {$r['status']}\n";
            }
        } else {
            echo "  TABELA VAZIA - sem nenhum registro\n";
        }
        
        // 6. INSERIR DADOS DE TESTE
        echo "\n6. Inserindo dados de teste:\n";
        
        // Primeiro, vamos criar uma conta (account)
        $pdo->exec("
            INSERT IGNORE INTO accounts (id, email, password_hash, role, is_verified, created_at, updated_at)
            VALUES (999, 'test@bacosearch.com', 'dummy_hash', 'provider', 1, NOW(), NOW())
        ");
        
        // Agora inserir providers de teste
        $testData = [
            ['Morena Linda', 'female', 1],
            ['Julia Morena', 'female', 1],
            ['Camila Morena Gostosa', 'female', 1],
            ['Loira Safada', 'female', 1],
            ['Ruiva Tesuda', 'female', 1],
        ];
        
        foreach ($testData as $data) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO providers (account_id, display_name, gender, nationality_id, status, created_at, updated_at)
                    VALUES (999, ?, ?, ?, 'active', NOW(), NOW())
                ");
                $stmt->execute($data);
                echo "  ✓ Inserido: {$data[0]}\n";
            } catch (Exception $e) {
                echo "  ✗ Erro ao inserir {$data[0]}: " . $e->getMessage() . "\n";
            }
        }
        
        // Verifica total após inserção
        $newCount = $pdo->query("SELECT COUNT(*) FROM providers")->fetchColumn();
        echo "\n  Total após inserção: $newCount registros\n";
    }
    
    echo "\n=== FIM DEBUG ===\n";
    echo "\nAgora teste: https://bacosearch.com/search.php?term=morena\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}
