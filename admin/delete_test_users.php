<?php
/**
 * Script para deletar usuários de teste e todos os seus rastros
 * Uso: acesse via navegador admin/delete_test_users.php
 */

require_once __DIR__ . '/../core/bootstrap.php';

// Proteção básica
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    die('Acesso negado. Apenas administradores.');
}

// Emails de teste para deletar
$testEmails = [
    'miiller@live.com',
    'trafegoinaciofilmes@gmail.com',
    'contato@inaciofilmes.com',
    'filmesinacio@gmail.com',
    'contato@centralbrasilatacado.com.br'
];

$pdo = getDBConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$results = [];
$totalDeleted = 0;

try {
    $pdo->beginTransaction();
    
    foreach ($testEmails as $email) {
        echo "<h3>Processando: $email</h3>";
        
        // 1. Buscar user_id
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo "<p style='color: orange;'>❌ Usuário não encontrado</p>";
            continue;
        }
        
        $userId = $user['id'];
        echo "<p>✓ Encontrado user_id: $userId</p>";
        
        // 2. Deletar de providers (se existir)
        $stmt = $pdo->prepare("DELETE FROM providers WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $deletedProviders = $stmt->rowCount();
        echo "<p>Providers deletados: $deletedProviders</p>";
        
        // 3. Deletar de companies (se existir)
        $stmt = $pdo->prepare("DELETE FROM companies WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $deletedCompanies = $stmt->rowCount();
        echo "<p>Companies deletadas: $deletedCompanies</p>";
        
        // 4. Deletar de clubs (se existir)
        $stmt = $pdo->prepare("DELETE FROM clubs WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $deletedClubs = $stmt->rowCount();
        echo "<p>Clubs deletados: $deletedClubs</p>";
        
        // 5. Deletar de services (se existir)
        $stmt = $pdo->prepare("DELETE FROM services WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $deletedServices = $stmt->rowCount();
        echo "<p>Services deletados: $deletedServices</p>";
        
        // 6. Deletar de photos (se existir)
        $stmt = $pdo->prepare("DELETE FROM photos WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $deletedPhotos = $stmt->rowCount();
        echo "<p>Photos deletadas: $deletedPhotos</p>";
        
        // 7. Deletar de videos (se existir)
        $stmt = $pdo->prepare("DELETE FROM videos WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $deletedVideos = $stmt->rowCount();
        echo "<p>Videos deletados: $deletedVideos</p>";
        
        // 8. Deletar de reviews/ratings relacionados
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE provider_id IN (SELECT id FROM providers WHERE user_id = :user_id)");
        $stmt->execute([':user_id' => $userId]);
        $deletedReviews = $stmt->rowCount();
        echo "<p>Reviews deletados: $deletedReviews</p>";
        
        // 9. Deletar de bookings/appointments (se existir)
        $stmt = $pdo->prepare("DELETE FROM bookings WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $deletedBookings = $stmt->rowCount();
        echo "<p>Bookings deletados: $deletedBookings</p>";
        
        // 10. Deletar de messages/conversations (se existir)
        $stmt = $pdo->prepare("DELETE FROM messages WHERE sender_id = :user_id OR receiver_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $deletedMessages = $stmt->rowCount();
        echo "<p>Messages deletadas: $deletedMessages</p>";
        
        // 11. Deletar de notifications (se existir)
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $deletedNotifications = $stmt->rowCount();
        echo "<p>Notifications deletadas: $deletedNotifications</p>";
        
        // 12. Deletar de sessions (se existir)
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $deletedSessions = $stmt->rowCount();
        echo "<p>Sessions deletadas: $deletedSessions</p>";
        
        // 13. Deletar de activity_logs (se existir)
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $deletedLogs = $stmt->rowCount();
        echo "<p>Activity logs deletados: $deletedLogs</p>";
        
        // 14. FINALMENTE deletar o usuário
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $deletedUser = $stmt->rowCount();
        
        if ($deletedUser > 0) {
            echo "<p style='color: green; font-weight: bold;'>✅ USUÁRIO DELETADO COM SUCESSO!</p>";
            $totalDeleted++;
        } else {
            echo "<p style='color: red;'>❌ Erro ao deletar usuário</p>";
        }
        
        echo "<hr>";
        
        $results[$email] = [
            'user_id' => $userId,
            'providers' => $deletedProviders,
            'companies' => $deletedCompanies,
            'clubs' => $deletedClubs,
            'services' => $deletedServices,
            'photos' => $deletedPhotos,
            'videos' => $deletedVideos,
            'reviews' => $deletedReviews,
            'bookings' => $deletedBookings,
            'messages' => $deletedMessages,
            'notifications' => $deletedNotifications,
            'sessions' => $deletedSessions,
            'activity_logs' => $deletedLogs,
            'user_deleted' => $deletedUser > 0
        ];
    }
    
    // Commit da transação
    $pdo->commit();
    
    echo "<h2 style='color: green;'>✅ PROCESSO CONCLUÍDO!</h2>";
    echo "<p>Total de usuários deletados: <strong>$totalDeleted</strong></p>";
    
    echo "<h3>Resumo detalhado:</h3>";
    echo "<pre>";
    print_r($results);
    echo "</pre>";
    
    echo "<p><a href='/'>← Voltar para home</a></p>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2 style='color: red;'>❌ ERRO NO PROCESSO!</h2>";
    echo "<p>Erro: " . $e->getMessage() . "</p>";
    echo "<p>Linha: " . $e->getLine() . "</p>";
    echo "<p>Arquivo: " . $e->getFile() . "</p>";
    echo "<p>Transação revertida (rollback). Nada foi deletado.</p>";
}
