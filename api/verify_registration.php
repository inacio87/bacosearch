<?php
/**
 * /api/verify_registration.php - PONTO DE VALIDAÇÃO E CRIAÇÃO DE CONTA
 *
 * RESPONSABILIDADES:
 * 1. Receber e validar o token da URL.
 * 2. Procurar o token na tabela `registration_requests`.
 * 3. Verificar se o token é válido, não expirou e não foi usado.
 * 4. Se for o primeiro clique válido:
 * a. Criar a conta na tabela `accounts` com os dados guardados (incluindo password_hash).
 * b. Atualizar o status do pedido para 'completed'.
 * c. Iniciar uma sessão básica para o fluxo de cadastro.
 * d. Redirecionar para a página de perfil com account_id.
 * 5. Se for um clique subsequente (token já usado), redirecionar para a página de login.
 * 6. Em caso de erro (token inválido/expirado), mostrar uma mensagem de erro.
 *
 * ÚLTIMA ATUALIZAÇÃO: 12/07/2025 - Ajustado para status 'active' e perfil direto.
 */

// PASSO 1: INICIALIZAÇÃO E OBTENÇÃO DO TOKEN
require_once dirname(__DIR__) . '/core/bootstrap.php';

$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Se nenhum token for fornecido no URL, não há nada a fazer.
if (empty($token)) {
    $_SESSION['general_error_message'] = getTranslation('invalid_verification_link', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'ui_messages');
    header('Location: ' . SITE_URL . '/register.php?status=error_no_token');
    exit();
}

// PASSO 2: PROCESSAMENTO E VALIDAÇÃO DO TOKEN
try {
    $db_conn = getDBConnection();

    // Procura a solicitação de registo na base de dados usando o token
    $request = db_fetch_one("SELECT * FROM registration_requests WHERE token = ?", [$token]);

    // CASO 1: TOKEN INVÁLIDO (não encontrado na base de dados)
    if (!$request) {
        $_SESSION['general_error_message'] = getTranslation('invalid_verification_token', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'ui_messages');
        header('Location: ' . SITE_URL . '/register.php?status=error_invalid_token');
        exit();
    }

    // CASO 2: TOKEN JÁ UTILIZADO (lógica para o segundo clique em diante)
    if ($request['status'] === 'completed') {
        $_SESSION['general_info_message'] = getTranslation('email_already_verified_login_prompt', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'ui_messages');
        header('Location: ' . SITE_URL . '/auth/login.php?status=already_verified');
        exit();
    }
    
    // CASO 3: TOKEN EXPIRADO
    if (new DateTime() > new DateTime($request['expires_at'])) {
        $_SESSION['general_error_message'] = getTranslation('verification_token_expired', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'ui_messages');
        db_execute("DELETE FROM registration_requests WHERE id = ?", [$request['id']]);
        header('Location: ' . SITE_URL . '/register.php?status=expired');
        exit();
    }
    
    // --- O CAMINHO FELIZ: PRIMEIRO CLIQUE E TOKEN VÁLIDO ---

    // Descodificar os dados do utilizador guardados no pedido
    $payload = json_decode($request['data_payload'], true);
    if (!$payload || !isset($payload['role_id']) || !isset($payload['password_hash'])) {
        throw new Exception("Payload de dados inválido ou corrompido para o token: " . $token);
    }
    
    // Inicia a transação para garantir atomicidade
    $db_conn->beginTransaction();

    // Verificação final de segurança: o email foi registado por outro meio?
    if (db_fetch_one("SELECT id FROM accounts WHERE email = ?", [$request['email']])) {
        $db_conn->rollBack();
        $_SESSION['general_info_message'] = getTranslation('email_already_verified_login_prompt', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'ui_messages');
        header('Location: ' . SITE_URL . '/auth/login.php?status=already_exists');
        exit();
    }

    // PASSO A: Criar a conta definitiva na tabela `accounts` com password_hash
    $stmt_account = $db_conn->prepare("
        INSERT INTO accounts (
            email, full_name, birth_date, nationality_id, phone_code, phone_number,
            password_hash, role_id, role, status, created_at, ip_address,
            visitor_id -- <-- Adicione a coluna visitor_id aqui!
        ) VALUES (
            :email, :full_name, :birth_date, :nationality_id, :phone_code, :phone_number,
            :password_hash, :role_id, :role_slug, 'active', NOW(), :ip_address,
            :visitor_id -- <-- Adicione o placeholder para visitor_id aqui!
        )
    ");
    
    $stmt_account->execute([
        ':email' => $request['email'],
        ':full_name' => $payload['full_name'] ?? null,
        ':birth_date' => $payload['birth_date'] ?? null,
        ':nationality_id' => $payload['nationality_id'] ?? null,
        ':phone_code' => $payload['phone_code'] ?? null,
        ':phone_number' => $payload['phone_number'] ?? null,
        ':password_hash' => $payload['password_hash'],
        ':role_id' => $payload['role_id'],
        ':role_slug' => $payload['role_slug'] ?? null,
        ':ip_address' => $request['ip_address'],
        ':visitor_id' => $request['visitor_id'] // <-- Passe o valor do visitor_id aqui!
    ]);
    $newAccountId = $db_conn->lastInsertId();

    // PASSO B: Atualizar o status do pedido para 'completed'
    db_execute("UPDATE registration_requests SET status = 'completed' WHERE id = ?", [$request['id']]);

    // Confirma as operações
    $db_conn->commit();

    // PASSO C: Iniciar uma sessão básica para o fluxo de cadastro
    session_regenerate_id(true); // Prevenção de session fixation
    $_SESSION['temp_user_id'] = $newAccountId; // Usar 'temp_user_id' para evitar login completo
    $_SESSION['user_email'] = $request['email'];
    $_SESSION['user_role'] = $payload['role_slug'] ?? 'provider'; // Default para provider
    
    // PASSO D: Redirecionar para a página de perfil com account_id
    $destinationPage = '/pages/register_' . htmlspecialchars($payload['role_slug'] ?? 'providers') . '.php';
    header('Location: ' . SITE_URL . $destinationPage . '?account_id=' . $newAccountId);
    exit();

} catch (Exception $e) {
    // Em caso de erro, reverte a transação e regista o erro
    if (isset($db_conn) && $db_conn->inTransaction()) {
        $db_conn->rollBack();
    }
    log_system_error('VERIFICATION_API_ERROR: ' . $e->getMessage(), 'CRITICAL', 'verify_registration_api');
    
    $_SESSION['general_error_message'] = getTranslation('error_registration_failed', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'ui_messages');
    header('Location: ' . SITE_URL . '/register.php?status=error_unexpected');
    exit();
}