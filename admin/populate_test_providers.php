<?php
/**
 * Popular Providers com Dados de Teste
 * NOTA: Rodar apenas UMA VEZ para inserir dados de exemplo
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

header('Content-Type: text/plain; charset=utf-8');

$pdo = getDBConnection();

echo "=== POPULANDO PROVIDERS COM DADOS DE TESTE ===\n\n";

// Dados de teste realistas
$testProviders = [
    // Morenas/Brunettes
    ['name' => 'Camila Morena', 'gender' => 'female', 'nationality' => 30], // Brasil
    ['name' => 'Isabella Brunette', 'gender' => 'female', 'nationality' => 30],
    ['name' => 'Julia Morena Gostosa', 'gender' => 'female', 'nationality' => 30],
    ['name' => 'Fernanda Latina', 'gender' => 'female', 'nationality' => 30],
    ['name' => 'Mariana Tropical', 'gender' => 'female', 'nationality' => 30],
    
    // Loiras/Blondes
    ['name' => 'Amanda Loira', 'gender' => 'female', 'nationality' => 30],
    ['name' => 'Carolina Blonde', 'gender' => 'female', 'nationality' => 225], // USA
    ['name' => 'Bianca Ruiva', 'gender' => 'female', 'nationality' => 30],
    
    // Diversos
    ['name' => 'Sofia Sensual', 'gender' => 'female', 'nationality' => 197], // Espanha
    ['name' => 'Gabriela Delícia', 'gender' => 'female', 'nationality' => 30],
    ['name' => 'Tatiana Safada', 'gender' => 'female', 'nationality' => 30],
    ['name' => 'Leticia Gostosa', 'gender' => 'female', 'nationality' => 30],
    ['name' => 'Vanessa Hot', 'gender' => 'female', 'nationality' => 225],
    ['name' => 'Priscila Naughty', 'gender' => 'female', 'nationality' => 30],
    ['name' => 'Adriana Sexy', 'gender' => 'female', 'nationality' => 30],
    
    // Trans
    ['name' => 'Patricia Trans', 'gender' => 'trans', 'nationality' => 30],
    ['name' => 'Nicole TS', 'gender' => 'trans', 'nationality' => 225],
    
    // Male
    ['name' => 'Diego Boy', 'gender' => 'male', 'nationality' => 30],
    ['name' => 'Carlos Massagista', 'gender' => 'male', 'nationality' => 30],
    ['name' => 'Rafael Man', 'gender' => 'male', 'nationality' => 30],
    
    // Outros países
    ['name' => 'Maria Colombiana', 'gender' => 'female', 'nationality' => 48], // Colômbia
    ['name' => 'Ana Argentina', 'gender' => 'female', 'nationality' => 10], // Argentina
    ['name' => 'Lucia Mexicana', 'gender' => 'female', 'nationality' => 142], // México
    ['name' => 'Emma European', 'gender' => 'female', 'nationality' => 74], // França
    ['name' => 'Yuki Asian', 'gender' => 'female', 'nationality' => 109], // Japão
];

try {
    $pdo->beginTransaction();
    
    // Primeiro cria uma account fake para cada provider
    $account_ids = [];
    
    foreach ($testProviders as $i => $provider) {
        $email = 'test' . ($i + 1) . '@bacosearch.test';
        $username = strtolower(str_replace(' ', '_', $provider['name']));
        
        // Insere account
        $stmt = $pdo->prepare("
            INSERT INTO accounts (username, email, role, is_verified, created_at, updated_at)
            VALUES (?, ?, 'advertiser', 1, NOW(), NOW())
        ");
        $stmt->execute([$username, $email]);
        $account_ids[] = $pdo->lastInsertId();
    }
    
    echo "✓ Criadas " . count($account_ids) . " accounts\n\n";
    
    // Agora insere os providers
    $inserted = 0;
    foreach ($testProviders as $i => $provider) {
        $stmt = $pdo->prepare("
            INSERT INTO providers 
            (account_id, display_name, gender, nationality_id, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
        ");
        
        $stmt->execute([
            $account_ids[$i],
            $provider['name'],
            $provider['gender'],
            $provider['nationality']
        ]);
        
        $inserted++;
        echo "  + {$provider['name']} ({$provider['gender']})\n";
    }
    
    $pdo->commit();
    
    echo "\n";
    echo str_repeat('=', 60) . "\n";
    echo "✓ SUCESSO: {$inserted} providers inseridos!\n";
    echo str_repeat('=', 60) . "\n\n";
    
    // Verifica total
    $total = $pdo->query("SELECT COUNT(*) FROM providers")->fetchColumn();
    echo "Total de providers no banco: {$total}\n\n";
    
    // Mostra alguns exemplos
    echo "Exemplos de busca que agora funcionarão:\n";
    echo "  - 'morena' → encontrará: Camila Morena, Isabella Brunette, Julia Morena Gostosa, etc.\n";
    echo "  - 'loira' → encontrará: Amanda Loira, Carolina Blonde\n";
    echo "  - 'trans' → encontrará: Patricia Trans, Nicole TS\n";
    echo "  - 'colombiana' → encontrará: Maria Colombiana\n\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
}

echo "\nFeito! Agora teste a busca em: https://bacosearch.com/search.php?term=morena\n";
