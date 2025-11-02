<?php
/**
 * Análise Direta das Tabelas - Sem Bootstrap
 */

// Conexão direta
$host = 'localhost';
$db   = 'chefej82_bacchus_1';
$user = 'chefej82_dev_1987';
$pass = '7,ax&1vRRz_0';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "============================================================\n";
    echo "   ANÁLISE PROFUNDA DAS TABELAS DE SEARCH - BACOSEARCH\n";
    echo "============================================================\n\n";
    
    $tables = ['search_intents', 'search_logs', 'global_searches'];
    
    foreach ($tables as $table) {
        echo str_repeat('=', 60) . "\n";
        echo "TABELA: {$table}\n";
        echo str_repeat('=', 60) . "\n\n";
        
        try {
            // Verifica se a tabela existe
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            if ($stmt->rowCount() == 0) {
                echo "TABELA NÃO EXISTE!\n\n";
                continue;
            }
            
            // Estrutura da tabela
            $stmt = $pdo->query("DESCRIBE {$table}");
            echo "ESTRUTURA DA TABELA:\n";
            echo str_repeat('-', 60) . "\n";
            printf("%-20s | %-15s | %-5s | %-5s | %-15s\n", 'CAMPO', 'TIPO', 'NULL', 'KEY', 'EXTRA');
            echo str_repeat('-', 60) . "\n";
            
            while ($row = $stmt->fetch()) {
                printf("%-20s | %-15s | %-5s | %-5s | %-15s\n", 
                    $row['Field'], 
                    $row['Type'], 
                    $row['Null'], 
                    $row['Key'], 
                    $row['Extra']
                );
            }
            
            // Contagem de registros
            $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            echo "\n";
            echo str_repeat('-', 60) . "\n";
            echo "TOTAL DE REGISTROS: {$count}\n";
            echo str_repeat('-', 60) . "\n\n";
            
            // Índices
            echo "ÍNDICES E CHAVES:\n";
            echo str_repeat('-', 60) . "\n";
            $stmt = $pdo->query("SHOW INDEX FROM {$table}");
            $indexes = [];
            while ($row = $stmt->fetch()) {
                $indexes[] = sprintf("  %s (%s) - Coluna: %s (Unique: %s)", 
                    $row['Key_name'],
                    $row['Index_type'],
                    $row['Column_name'],
                    $row['Non_unique'] == 0 ? 'Sim' : 'Não'
                );
            }
            
            if (empty($indexes)) {
                echo "  Nenhum índice definido.\n";
            } else {
                echo implode("\n", $indexes) . "\n";
            }
            
            echo "\n";
            
            // Primeiros registros (se existir)
            if ($count > 0) {
                echo "PRIMEIROS 5 REGISTROS (mais recentes):\n";
                echo str_repeat('-', 60) . "\n";
                
                $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY id DESC LIMIT 5");
                $i = 1;
                
                while ($row = $stmt->fetch()) {
                    echo "\n[Registro #{$i}]\n";
                    foreach ($row as $key => $val) {
                        if (is_string($val) && strlen($val) > 100) {
                            $val = substr($val, 0, 100) . '...';
                        }
                        echo "  {$key}: " . ($val ?? 'NULL') . "\n";
                    }
                    $i++;
                }
                
                echo "\n";
                
                // Estatísticas adicionais
                echo "ESTATÍSTICAS:\n";
                echo str_repeat('-', 60) . "\n";
                
                if ($table === 'search_logs') {
                    // Top 10 termos mais buscados
                    $stmt = $pdo->query("
                        SELECT search_term, COUNT(*) as total 
                        FROM {$table} 
                        WHERE search_term IS NOT NULL AND search_term != ''
                        GROUP BY search_term 
                        ORDER BY total DESC 
                        LIMIT 10
                    ");
                    echo "Top 10 Termos Mais Buscados:\n";
                    while ($row = $stmt->fetch()) {
                        echo "  - '{$row['search_term']}': {$row['total']} buscas\n";
                    }
                }
                
                if ($table === 'global_searches') {
                    // Top termos mais buscados
                    $stmt = $pdo->query("
                        SELECT term, COUNT(*) as total 
                        FROM {$table} 
                        WHERE term IS NOT NULL AND term != ''
                        GROUP BY term 
                        ORDER BY total DESC 
                        LIMIT 10
                    ");
                    echo "Top 10 Termos Mais Buscados:\n";
                    while ($row = $stmt->fetch()) {
                        echo "  - '{$row['term']}': {$row['total']}\n";
                    }
                    
                    // Média de resultados
                    $stmt = $pdo->query("SELECT AVG(results_count) as avg_results FROM {$table}");
                    $avg = $stmt->fetchColumn();
                    echo "\nMédia de Resultados por Busca: " . round($avg, 2) . "\n";
                }
                
                if ($table === 'search_intents') {
                    // Distribuição por categoria
                    $stmt = $pdo->query("
                        SELECT category, COUNT(*) as total 
                        FROM {$table} 
                        WHERE category IS NOT NULL
                        GROUP BY category 
                        ORDER BY total DESC
                    ");
                    echo "Distribuição por Categoria:\n";
                    while ($row = $stmt->fetch()) {
                        echo "  - {$row['category']}: {$row['total']}\n";
                    }
                }
            }
            
            echo "\n";
            
        } catch (Exception $e) {
            echo "ERRO: " . $e->getMessage() . "\n\n";
        }
        
        echo "\n\n";
    }
    
    echo str_repeat('=', 60) . "\n";
    echo "ANÁLISE COMPLETA\n";
    echo str_repeat('=', 60) . "\n";
    echo "\nData/Hora: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERRO DE CONEXÃO: " . $e->getMessage() . "\n";
}
