<?php
/**
 * /api/api_register.php - API para Registo de Anunciantes (Fase 1)
 *
 * RESPONSABILIDADES (ARQUITETURA FINAL):
 * 1. Receber e validar rigorosamente os dados do formulário de registo inicial.
 * 2. Aplicar proteção contra CSRF e spam (honeypot).
 * 3. Verificar se o email já está em uso por uma conta ativa.
 * 4. Inserir uma solicitação de registo na tabela `registration_requests` com status 'pendente'.
 * - O payload desta solicitação contém todos os dados para a criação da conta.
 * 5. Gerar um token único com validade de 24 horas.
 * 6. Enviar um e-mail com o link de verificação para o script que finalizará o registo.
 * 7. Redirecionar o utilizador de volta para a página de registo com mensagens de feedback claras.
 */

// PASSO 1: INICIALIZAÇÃO E CARREGAMENTO DE DEPENDÊNCIAS
require_once dirname(__DIR__) . '/core/bootstrap.php';

// Arrays para armazenar erros e dados do formulário em caso de falha
$errors = [];
$form_data = $_POST; // Captura todos os dados submetidos para repreencher o formulário se necessário

// PASSO 2: VALIDAÇÃO DE SEGURANÇA
// Apenas permitir requisições do tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método Não Permitido
    // Para APIs, é bom retornar JSON em caso de erro de método
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método de requisição não permitido.']);
    exit();
}

// Validação Honeypot (Anti-Spam)
if (!empty($_POST['website_url'])) {
    log_system_error('Possível spam detectado via honeypot no registo. IP: ' . getClientIp(), 'WARNING', 'honeypot_spam_register');
    // Para confundir o bot, redireciona para uma mensagem de sucesso "falsa"
    $_SESSION['registration_success_message'] = true;
    header('Location: ' . SITE_URL . '/register.php?status=success'); // Redirecionamento genérico
    exit();
}

// Validação do Token CSRF para proteger contra ataques cross-site
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $errors['general'] = getTranslation('csrf_token_invalid_error', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'validation_errors');
    log_system_error('CSRF token inválido no registo. IP: ' . getClientIp(), 'WARNING', 'csrf_register_mismatch');

    $_SESSION['errors_register'] = $errors;
    $_SESSION['form_data_register'] = $form_data;
    header('Location: ' . SITE_URL . '/register.php');
    exit();
}

// PASSO 3: LIMPEZA E VALIDAÇÃO DOS DADOS DO FORMULÁRIO
$realName = filter_input(INPUT_POST, 'real_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$birthDate = filter_input(INPUT_POST, 'birth_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL); // Usar FILTER_VALIDATE_EMAIL para validação e limpeza
$password = $_POST['password'] ?? '';
$repeatPassword = $_POST['repeat_password'] ?? '';
$phoneCode = filter_input(INPUT_POST, 'phone_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$phoneNumber = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_NUMBER_INT);
$nationalityId = filter_input(INPUT_POST, 'nationality_id', FILTER_VALIDATE_INT);
$accountTypeSubmitted = filter_input(INPUT_POST, 'account_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$privacyConsent = filter_input(INPUT_POST, 'privacy_consent', FILTER_VALIDATE_BOOLEAN);

// --- Validações de Campo ---
if (empty($realName) || mb_strlen($realName) > 150) $errors['real_name'] = getTranslation('full_name_error', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'validation_errors');
if (empty($birthDate)) {
    $errors['birth_date'] = getTranslation('birth_date_error', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'validation_errors');
} else {
    try {
        if ((new DateTime())->diff(new DateTime($birthDate))->y < 18) {
            $errors['birth_date'] = getTranslation('age_restriction_error', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'validation_errors');
        }
    } catch (Exception $e) {
        $errors['birth_date'] = getTranslation('birth_date_error', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'validation_errors');
    }
}
if (!$email) $errors['email'] = getTranslation('email_error', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'validation_errors');

// Validação de Senha
$passwordMinLength = 8;
if (empty($password)) {
    $errors['password'] = getTranslation('password_error', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'validation_errors');
} elseif (mb_strlen($password) < $passwordMinLength || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    $errors['password'] = getTranslation('password_complexity_error', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'validation_errors');
}
if ($password !== $repeatPassword) $errors['repeat_password'] = getTranslation('passwords_do_not_match', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'validation_errors');
if (empty($phoneCode) || empty($phoneNumber)) $errors['phone_number'] = getTranslation('phone_error', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'validation_errors');
if (empty($nationalityId)) $errors['nationality_id'] = getTranslation('nationality_required_error', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'validation_errors');
if (!$privacyConsent) $errors['privacy_consent'] = getTranslation('privacy_consent_required_error', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'validation_errors');

// Mapeamento e validação do tipo de conta (apenas os 3 tipos permitidos)
$roleId = null;
$roleSlug = null;
if (!empty($accountTypeSubmitted)) {
    $mapFormToDbSlug = [
        'provider'  => 'providers',
        'services'  => 'services',
        'companies' => 'businesses'
    ];
    $targetSlug = $mapFormToDbSlug[$accountTypeSubmitted] ?? null;
    if ($targetSlug) {
        $roleDetails = db_fetch_one("SELECT id, slug FROM access_roles WHERE slug = ? AND is_active = 1 LIMIT 1", [$targetSlug]);
        if ($roleDetails) {
            $roleId = $roleDetails['id'];
            $roleSlug = $roleDetails['slug'];
        }
    }
}
if (!$roleId) $errors['account_type'] = getTranslation('account_type_error', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'validation_errors');

// Se houver qualquer erro de validação, redireciona de volta para o formulário
if (!empty($errors)) {
    $_SESSION['errors_register'] = $errors;
    $_SESSION['form_data_register'] = $form_data;
    header('Location: ' . SITE_URL . '/register.php');
    exit();
}

// PASSO 4: LÓGICA DE NEGÓCIO E INTERAÇÃO COM A BASE DE DADOS
try {
    $db_conn = getDBConnection();

    // 4.1. Verificar se o e-mail JÁ EXISTE e está ATIVO na tabela principal `accounts`
    if (db_fetch_one("SELECT id FROM accounts WHERE email = ? AND status = 'active'", [$email])) {
        $_SESSION['errors_register'] = ['email' => getTranslation('email_already_registered_login_prompt', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'ui_messages')];
        header('Location: ' . SITE_URL . '/register.php');
        exit();
    }

    // 4.2. Verificar se já existe uma solicitação PENDENTE e NÃO EXPIRADA para este email
    if (db_fetch_one("SELECT id FROM registration_requests WHERE email = ? AND status = 'pending_email_verification' AND expires_at > NOW()", [$email])) {
        $_SESSION['registration_info_message'] = getTranslation('email_already_registered_pending_email_verification', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'ui_messages');
        header('Location: ' . SITE_URL . '/register.php?status=info&email=' . urlencode($email));
        exit();
    }

    // 4.3. Limpar solicitações antigas para o mesmo email para evitar lixo na base de dados
    db_execute("DELETE FROM registration_requests WHERE email = ?", [$email]);

    // Inicia a transação para garantir a integridade da operação
    $db_conn->beginTransaction();

    // 4.4. Preparar dados para a nova solicitação de registo
    $token = bin2hex(random_bytes(32));
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours')); // Token expira em 24 horas

    // O data_payload agora guarda TUDO o que é necessário para criar a conta mais tarde
    $dataPayloadJson = json_encode([
        'full_name' => $realName,
        'birth_date' => $birthDate,
        'nationality_id' => (int)$nationalityId,
        'phone_code' => $phoneCode,
        'phone_number' => $phoneNumber,
        'privacy_consent' => (int)$privacyConsent,
        'password_hash' => $hashedPassword,
        'role_id' => $roleId,
        'role_slug' => $roleSlug
    ]);

    // 4.5. Inserir a solicitação de registo
    $stmt_request = $db_conn->prepare("
        INSERT INTO registration_requests (
            visitor_id, token, email, phone_number, account_type_requested,
            data_payload, status, expires_at, ip_address, created_at
        ) VALUES (
            :visitor_id, :token, :email, :phone_number, :account_type_requested,
            :data_payload, 'pending_email_verification', :expires_at, :ip_address, NOW()
        )
    ");

    $stmt_request->execute([
        ':visitor_id' => $_SESSION['visitor_db_id'] ?? null,
        ':token' => $token,
        ':email' => $email,
        ':phone_number' => $phoneCode . $phoneNumber,
        ':account_type_requested' => $accountTypeSubmitted,
        ':data_payload' => $dataPayloadJson,
        ':expires_at' => $expiresAt,
        ':ip_address' => getClientIp()
    ]);
    $registrationRequestId = $db_conn->lastInsertId();

    $db_conn->commit();

    // PASSO 5: ENVIAR EMAIL DE VERIFICAÇÃO
    $verificationLink = SITE_URL . '/api/verify_registration.php?token=' . $token;

    // --- Prepara as traduções necessárias para o e-mail ---
    $languageCode = $_SESSION['language'] ?? LANGUAGE_CONFIG['default'];
    $emailTranslations = [];
    $emailKeysToTranslate = [
        'registration_verification_email_subject',
        'email_title_fallback',
        'greeting',
        'registration_email_main_message', // Nova chave para o corpo principal
        'registration_email_follow_up_message', // Nova chave para a mensagem de acompanhamento
        'verify_email_button_text', // Texto do botão de verificação
        'your_message_label', // Embora não haja mensagem do usuário aqui, é bom ter se o template for genérico
        'all_rights_reserved',
        'spam_notice',
        'email_sent_to_label',
        'logo_alt_text',
        'check_inbox_spam_notice' // Para a nota de verificar caixa de entrada/spam
    ];

    foreach ($emailKeysToTranslate as $key) {
        // A maioria dessas chaves deve estar no contexto 'email_templates' ou 'ui_messages'
        $context = 'email_templates';
        if ($key === 'check_inbox_spam_notice' || $key === 'email_sent_to_label') {
            $context = 'ui_messages';
        }
        $emailTranslations[$key] = getTranslation($key, $languageCode, $context);
    }
    // --- Fim da preparação das traduções ---

    // Prepara os dados para o template do e-mail
    $userTemplateData = [
        'user_name' => $realName,
        'verification_link' => $verificationLink,
        'email_title_fallback' => $emailTranslations['email_title_fallback'] ?? 'Verificação de Registo',
        'greeting' => $emailTranslations['greeting'] ?? 'Olá',
        'main_message' => $emailTranslations['registration_email_main_message'] ?? 'Obrigado por se registar! Por favor, clique no botão abaixo para verificar o seu endereço de email.',
        'follow_up_message' => $emailTranslations['registration_email_follow_up_message'] ?? 'Após a verificação, a sua conta será analisada e ativada em breve.',
        'button_text' => $emailTranslations['verify_email_button_text'] ?? 'Verificar Email',
        'your_message_label' => $emailTranslations['your_message_label'] ?? '', // Pode ser vazio se não for aplicável
        'current_year' => date('Y'),
        'site_name' => SITE_NAME,
        'all_rights_reserved' => $emailTranslations['all_rights_reserved'] ?? 'Todos os direitos reservados.',
        'spam_notice' => $emailTranslations['spam_notice'] ?? 'Se você não solicitou este e-mail, por favor, ignore-o.',
        'email_sent_to_label' => $emailTranslations['email_sent_to_label'] ?? 'Este e-mail foi enviado para',
        'user_email' => $email,
        'logo_alt_text' => $emailTranslations['logo_alt_text'] ?? SITE_NAME . ' Logo',
        'mail_css_url' => SITE_URL . '/assets/css/mail.css',
        'check_spam_notice' => $emailTranslations['check_inbox_spam_notice'] ?? 'Por favor, verifique a sua caixa de entrada e a pasta de spam.'
    ];

    $emailSent = send_email(
        $email,
        $emailTranslations['registration_verification_email_subject'] ?? 'Verifique o seu Registo',
        'templates/emails/register_verification.html', // Certifique-se de que este template existe e é o correto
        $userTemplateData,
        'registration_verification_email',
        $registrationRequestId,
        $_SESSION['visitor_db_id'] ?? null,
        null // Não há um event_id específico para o envio inicial de registro aqui
    );

    if (!$emailSent) {
        log_system_error("Falha ao enviar e-mail de verificação para {$email}. Token: {$token}", 'ERROR', 'registration_email_failure');
        $_SESSION['errors_register'] = ['general' => getTranslation('error_resend_email_failed', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'ui_messages')];
        header('Location: ' . SITE_URL . '/register.php?status=email_error');
        exit();
    }

    // PASSO 6: SUCESSO FINAL
    $_SESSION['registration_success_message'] = true;
    header('Location: ' . SITE_URL . '/register.php?status=success_verification_sent&email=' . urlencode($email));
    exit();

} catch (Exception $e) {
    if (isset($db_conn) && $db_conn->inTransaction()) {
        $db_conn->rollBack();
    }
    log_system_error('REGISTRATION_API_ERROR: ' . $e->getMessage(), 'CRITICAL', 'api_register_processing');

    $_SESSION['errors_register'] = ['general' => getTranslation('error_registration_failed', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'ui_messages')];
    $_SESSION['form_data_register'] = $form_data;
    header('Location: ' . SITE_URL . '/register.php');
    exit();
}
