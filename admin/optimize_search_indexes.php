<?php
/**
 * Adiciona Índices para Performance de Busca
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

header('Content-Type: text/plain; charset=utf-8');

$pdo = getDBConnection();

echo "=== OTIMIZANDO BANCO DE DADOS - ÍNDICES ===\n\n";

$indices = [
    // global_searches - CRÍTICO para analytics
    [
        'table' => 'global_searches',
        'index' => 'idx_term',
        'sql' => 'CREATE INDEX idx_term ON global_searches(term)',
        'desc' => 'Índice no termo de busca'
    ],
    [
        'table' => 'global_searches',
        'index' => 'idx_created_at',
        'sql' => 'CREATE INDEX idx_created_at ON global_searches(created_at)',
        'desc' => 'Índice na data (para trending/analytics)'
    ],
    [
        'table' => 'global_searches',
        'index' => 'idx_visitor_id',
        'sql' => 'CREATE INDEX idx_visitor_id ON global_searches(visitor_id)',
        'desc' => 'Índice no visitor_id'
    ],
    [
        'table' => 'global_searches',
        'index' => 'PRIMARY',
        'sql' => 'ALTER TABLE global_searches MODIFY id BIGINT AUTO_INCREMENT PRIMARY KEY',
        'desc' => 'Adiciona PRIMARY KEY e AUTO_INCREMENT no id'
    ],
    
    // providers - melhorias
    [
        'table' => 'providers',
        'index' => 'idx_display_name',
        'sql' => 'CREATE INDEX idx_display_name ON providers(display_name)',
        'desc' => 'Índice no nome (busca principal)'
    ],
    [
        'table' => 'providers',
        'index' => 'idx_status_updated',
        'sql' => 'CREATE INDEX idx_status_updated ON providers(status, updated_at)',
        'desc' => 'Índice composto para listagens ativas'
    ],
    
    // search_intents - já tem bons índices, mas pode melhorar
    [
        'table' => 'search_intents',
        'index' => 'idx_category_term',
        'sql' => 'CREATE INDEX idx_category_term ON search_intents(category, term)',
        'desc' => 'Índice composto para busca por categoria'
    ],
];

$success = 0;
$skipped = 0;
$errors = 0;

foreach ($indices as $index) {
    echo "Tabela: {$index['table']}\n";
    echo "  → {$index['desc']}\n";
    
    try {
        // Verifica se o índice já existe
        $stmt = $pdo->query("SHOW INDEX FROM {$index['table']} WHERE Key_name = '{$index['index']}'");
        
        if ($stmt->rowCount() > 0) {
            echo "  ✓ Índice '{$index['index']}' já existe (skip)\n\n";
            $skipped++;
            continue;
        }
        
        // Cria o índice
        $pdo->exec($index['sql']);
        echo "  ✓ Índice '{$index['index']}' criado com sucesso!\n\n";
        $success++;
        
    } catch (PDOException $e) {
        // Alguns erros são OK (índice já existe, etc)
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "  ✓ Índice '{$index['index']}' já existe\n\n";
            $skipped++;
        } elseif (strpos($e->getMessage(), 'Multiple primary key') !== false) {
            echo "  ✓ PRIMARY KEY já existe\n\n";
            $skipped++;
        } else {
            echo "  ✗ ERRO: " . $e->getMessage() . "\n\n";
            $errors++;
        }
    }
}

echo str_repeat('=', 60) . "\n";
echo "RESUMO:\n";
echo "  Criados: {$success}\n";
echo "  Já existiam: {$skipped}\n";
echo "  Erros: {$errors}\n";
echo str_repeat('=', 60) . "\n\n";

// Mostra estatísticas das tabelas principais
echo "ESTATÍSTICAS PÓS-OTIMIZAÇÃO:\n\n";

$tables = ['providers', 'global_searches', 'search_intents', 'search_logs'];
foreach ($tables as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        $stmt = $pdo->query("SHOW INDEX FROM {$table}");
        $numIndexes = $stmt->rowCount();
        
        echo sprintf("  %-20s: %6d registros | %2d índices\n", $table, $count, $numIndexes);
    } catch (Exception $e) {
        echo "  {$table}: ERRO\n";
    }
}

echo "\nFEITO! Performance de busca otimizada.\n";
