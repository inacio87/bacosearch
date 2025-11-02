<?php
/**
 * /api/admin_user_actions.php
 * API para Ações Administrativas em Utilizadores (versão completa e ajustada)
 * - Gerencia ações como aprovar, rejeitar, suspender contas, etc.
 * - Requer que o utilizador seja um admin autenticado.
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';

// Verificações de segurança: Apenas requisições POST via AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit();
}

header('Content-Type: application/json');

// Verificação de autenticação do administrador
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado. Por favor, faça login novamente.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

// Filtrar e validar input
$action = $input['action'] ?? '';
$userId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);
$languageCode = $_SESSION['language'] ?? LANGUAGE_CONFIG['default'] ?? 'pt-br';

if (!$userId) {
    echo json_encode(['success' => false, 'message' => getTranslation('invalid_user_id', $languageCode, 'admin_users')]);
    exit();
}

$db = getDBConnection();

try {
    $db->beginTransaction();

    switch ($action) {
        case 'approve':
            // AJUSTE PRINCIPAL: Permite aprovação de utilizadores com status 'pending_email_verification' ou 'pending_admin_approval'.
            $stmt_update_account = $db->prepare(
                "UPDATE accounts SET status = 'active', updated_at = NOW() WHERE id = :id AND (status = 'pending_admin_approval' OR status = 'pending_email_verification')"
            );
            $stmt_update_account->execute([':id' => $userId]);

            if ($stmt_update_account->rowCount() > 0) {
                // Obter informações da conta para os próximos passos
                $stmt_get_info = $db->prepare("SELECT role, email, full_name FROM accounts WHERE id = ?");
                $stmt_get_info->execute([$userId]);
                $account_info = $stmt_get_info->fetch(PDO::FETCH_ASSOC);

                if ($account_info) {
                    // Atualizar tabela de perfil correspondente (se existir)
                    $profile_table = '';
                    if ($account_info['role'] === 'providers') $profile_table = 'providers';
                    elseif ($account_info['role'] === 'businesses') $profile_table = 'businesses';
                    
                    if ($profile_table) {
                        $stmt_update_profile = $db->prepare("UPDATE {$profile_table} SET profile_status = 'active', updated_at = NOW() WHERE account_id = :account_id AND profile_status = 'pending'");
                        $stmt_update_profile->execute([':account_id' => $userId]);
                    }

                    // Enviar e-mail de boas-vindas/aprovação
                    $approvalTemplateData = [
                        'email_title_fallback' => getTranslation('email_title_approved', $languageCode, 'email_templates'),
                        'user_name' => $account_info['full_name'],
                        'approval_message' => getTranslation('approval_message', $languageCode, 'email_templates'),
                        // Adicionar outras variáveis de template necessárias...
                    ];

                    send_email(
                        $account_info['email'],
                        $approvalTemplateData['email_title_fallback'],
                        'templates/emails/registration_approved.html',
                        $approvalTemplateData,
                        'registration_account_approved',
                        $userId
                    );

                    $db->commit();
                    echo json_encode(['success' => true, 'message' => getTranslation('user_approved_success', $languageCode, 'admin_users')]);

                } else {
                     throw new Exception("Falha ao obter informações da conta após a atualização.");
                }

            } else {
                throw new Exception(getTranslation('user_not_found_or_already_approved', $languageCode, 'admin_users'));
            }
            break;

        case 'reject':
            // Esta lógica já estava correta, pois considera ambos os status pendentes.
            $stmt_update_account = $db->prepare("UPDATE accounts SET status = 'rejected', updated_at = NOW() WHERE id = :id AND (status = 'pending_admin_approval' OR status = 'pending_email_verification')");
            $stmt_update_account->execute([':id' => $userId]);

            if ($stmt_update_account->rowCount() > 0) {
                // Opcional: Enviar e-mail de rejeição
                // ...
                $db->commit();
                echo json_encode(['success' => true, 'message' => getTranslation('user_rejected_success', $languageCode, 'admin_users')]);
            } else {
                throw new Exception(getTranslation('user_not_found_or_status_not_rejectable', $languageCode, 'admin_users'));
            }
            break;
            
        case 'suspend':
            // Lógica para suspender um utilizador ativo
            $stmt = $db->prepare("UPDATE accounts SET status = 'suspended', updated_at = NOW() WHERE id = :id AND status = 'active'");
            $stmt->execute([':id' => $userId]);
            if ($stmt->rowCount() > 0) {
                 $db->commit();
                 echo json_encode(['success' => true, 'message' => getTranslation('user_suspended_success', $languageCode, 'admin_users')]);
            } else {
                 throw new Exception(getTranslation('user_not_found_or_not_active', $languageCode, 'admin_users'));
            }
            break;

        case 'activate':
            // Lógica para reativar um utilizador suspenso
            $stmt = $db->prepare("UPDATE accounts SET status = 'active', updated_at = NOW() WHERE id = :id AND status = 'suspended'");
            $stmt->execute([':id' => $userId]);
            if ($stmt->rowCount() > 0) {
                 $db->commit();
                 echo json_encode(['success' => true, 'message' => getTranslation('user_activated_success', $languageCode, 'admin_users')]);
            } else {
                 throw new Exception(getTranslation('user_not_found_or_not_suspended', $languageCode, 'admin_users'));
            }
            break;

        default:
            throw new Exception(getTranslation('invalid_action', $languageCode, 'admin_users'));
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    log_system_error("Admin User Action Error (Action: {$action}, UserID: {$userId}): " . $e->getMessage(), 'ERROR', 'admin_user_action_exception');
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>