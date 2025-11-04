<?php
/**
 * Verifica se os visitantes de teste foram completamente deletados
 * IDs: 58, 59, 60, 61, 63
 */

require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

$test_visitor_ids = [58, 59, 60, 61, 63];
$ids_string = implode(', ', $test_visitor_ids);

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Verificação de Deleção - Visitantes de Teste</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #666; margin-top: 30px; }
        .success { color: #4CAF50; font-weight: bold; padding: 10px; background: #e8f5e9; border-radius: 4px; }
        .warning { color: #ff9800; font-weight: bold; padding: 10px; background: #fff3e0; border-radius: 4px; }
        .error { color: #f44336; font-weight: bold; padding: 10px; background: #ffebee; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .check-item { margin: 15px 0; padding: 15px; background: #fafafa; border-left: 4px solid #2196F3; }
        .count { font-size: 24px; font-weight: bold; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Verificação de Deleção - Visitantes de Teste</h1>
        <p><strong>IDs verificados:</strong> $ids_string</p>
        <hr>
";

try {
    $db = getDBConnection();
    
    // 1. Verificar visitantes
    echo "<div class='check-item'>";
    echo "<h2>1️⃣ Tabela: visitors</h2>";
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM visitors WHERE id IN ($ids_string)");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count === 0) {
        echo "<p class='success'>✅ SUCESSO: Nenhum visitante encontrado (0 registros)</p>";
    } else {
        echo "<p class='error'>❌ ERRO: Ainda existem $count visitante(s)</p>";
        
        // Mostrar quais ainda existem
        $stmt = $db->prepare("SELECT id, cookie_id, ip_address, created_at FROM visitors WHERE id IN ($ids_string)");
        $stmt->execute();
        $remaining = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table><tr><th>ID</th><th>Cookie ID</th><th>IP</th><th>Criado em</th></tr>";
        foreach ($remaining as $row) {
            echo "<tr><td>{$row['id']}</td><td>{$row['cookie_id']}</td><td>{$row['ip_address']}</td><td>{$row['created_at']}</td></tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // 2. Verificar eventos
    echo "<div class='check-item'>";
    echo "<h2>2️⃣ Tabela: events</h2>";
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM events WHERE visitor_id IN ($ids_string)");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count === 0) {
        echo "<p class='success'>✅ SUCESSO: Nenhum evento encontrado (0 registros)</p>";
    } else {
        echo "<p class='error'>❌ ERRO: Ainda existem $count evento(s)</p>";
    }
    echo "</div>";
    
    // 3. Verificar page_views
    echo "<div class='check-item'>";
    echo "<h2>3️⃣ Tabela: page_views</h2>";
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM page_views WHERE visitor_id IN ($ids_string)");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count === 0) {
        echo "<p class='success'>✅ SUCESSO: Nenhuma visualização encontrada (0 registros)</p>";
    } else {
        echo "<p class='error'>❌ ERRO: Ainda existem $count visualização(ões)</p>";
    }
    echo "</div>";
    
    // 4. Verificar ad_stats
    echo "<div class='check-item'>";
    echo "<h2>4️⃣ Tabela: ad_stats</h2>";
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM ad_stats WHERE visitor_id IN ($ids_string)");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count === 0) {
        echo "<p class='success'>✅ SUCESSO: Nenhuma estatística de anúncio encontrada (0 registros)</p>";
    } else {
        echo "<p class='error'>❌ ERRO: Ainda existem $count estatística(s)</p>";
    }
    echo "</div>";
    
    // 5. Verificar system_logs
    echo "<div class='check-item'>";
    echo "<h2>5️⃣ Tabela: system_logs</h2>";
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM system_logs WHERE visitor_id IN ($ids_string)");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count === 0) {
        echo "<p class='success'>✅ SUCESSO: Nenhum log de sistema encontrado (0 registros)</p>";
    } else {
        echo "<p class='error'>❌ ERRO: Ainda existem $count log(s)</p>";
    }
    echo "</div>";
    
    // 6. Verificar global_searches
    echo "<div class='check-item'>";
    echo "<h2>6️⃣ Tabela: global_searches</h2>";
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM global_searches WHERE visitor_id IN ($ids_string)");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count === 0) {
        echo "<p class='success'>✅ SUCESSO: Nenhuma busca global encontrada (0 registros)</p>";
    } else {
        echo "<p class='error'>❌ ERRO: Ainda existem $count busca(s)</p>";
    }
    echo "</div>";
    
    // 7. Verificar search_logs
    echo "<div class='check-item'>";
    echo "<h2>7️⃣ Tabela: search_logs</h2>";
    
    // Verificar se a coluna visitor_id existe na tabela search_logs
    $stmt = $db->query("SHOW COLUMNS FROM search_logs LIKE 'visitor_id'");
    $column_exists = $stmt->fetch();
    
    if ($column_exists) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM search_logs WHERE visitor_id IN ($ids_string)");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        
        if ($count === 0) {
            echo "<p class='success'>✅ SUCESSO: Nenhum log de busca encontrado (0 registros)</p>";
        } else {
            echo "<p class='error'>❌ ERRO: Ainda existem $count log(s) de busca</p>";
        }
    } else {
        echo "<p class='warning'>⚠️ AVISO: Tabela search_logs não possui coluna visitor_id (ignorado)</p>";
    }
    echo "</div>";
    
    // Resumo final
    echo "<hr>";
    echo "<h2>📊 Resumo Final</h2>";
    
    // Contar total de rastros restantes
    $total_remaining = 0;
    
    $tables_to_check = [
        'visitors',
        'events',
        'page_views',
        'ad_stats',
        'system_logs',
        'global_searches'
    ];
    
    foreach ($tables_to_check as $table) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM $table WHERE visitor_id IN ($ids_string) OR (id IN ($ids_string) AND '$table' = 'visitors')");
        $stmt->execute();
        $total_remaining += (int)$stmt->fetchColumn();
    }
    
    if ($total_remaining === 0) {
        echo "<div class='success' style='font-size: 18px; text-align: center;'>";
        echo "🎉 <strong>PERFEITO!</strong> Todos os visitantes de teste e seus rastros foram completamente deletados!";
        echo "</div>";
        echo "<p style='text-align: center; margin-top: 20px;'>Você pode reutilizar os emails para novos testes de registro.</p>";
    } else {
        echo "<div class='error' style='font-size: 18px; text-align: center;'>";
        echo "⚠️ <strong>ATENÇÃO!</strong> Ainda existem $total_remaining registro(s) relacionado(s) aos visitantes de teste.";
        echo "</div>";
        echo "<p style='text-align: center; margin-top: 20px;'>Execute novamente o SQL de deleção no phpMyAdmin.</p>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ Erro na Verificação</h2>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Arquivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "
    </div>
</body>
</html>";
