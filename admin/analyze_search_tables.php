<?php
/**
 * Análise Profunda das Tabelas de Search
 */

// Suprimir warnings de log temporariamente
error_reporting(E_ERROR | E_PARSE);

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

header('Content-Type: text/plain; charset=utf-8');

echo "============================================================\n";
echo "   ANÁLISE PROFUNDA DAS TABELAS DE SEARCH - BACOSEARCH\n";
echo "============================================================\n\n";

$pdo = getDBConnection();

$tables = ['search_intents', 'search_logs', 'global_searches'];

foreach ($tables as $table) {
    echo str_repeat('=', 60) . "\n";
    echo "TABELA: {$table}\n";
    echo str_repeat('=', 60) . "\n\n";
    
    try {
        // Estrutura da tabela
        $stmt = $pdo->query("DESCRIBE {$table}");
        echo "ESTRUTURA DA TABELA:\n";
        echo str_repeat('-', 60) . "\n";
        printf("%-20s | %-15s | %-5s | %-5s | %-15s\n", 'CAMPO', 'TIPO', 'NULL', 'KEY', 'EXTRA');
        echo str_repeat('-', 60) . "\n";
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "  - '{$row['search_term']}': {$row['total']} buscas\n";
                }
            }
            
            if ($table === 'global_searches') {
                // Total por tipo
                $stmt = $pdo->query("
                    SELECT search_type, COUNT(*) as total 
                    FROM {$table} 
                    GROUP BY search_type 
                    ORDER BY total DESC
                ");
                echo "Total por Tipo de Busca:\n";
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "  - {$row['search_type']}: {$row['total']}\n";
                }
                
                // Top termos
                $stmt = $pdo->query("
                    SELECT search_term, COUNT(*) as total 
                    FROM {$table} 
                    WHERE search_term IS NOT NULL AND search_term != ''
                    GROUP BY search_term 
                    ORDER BY total DESC 
                    LIMIT 10
                ");
                echo "\nTop 10 Termos (Global):\n";
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "  - '{$row['search_term']}': {$row['total']}\n";
                }
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
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
