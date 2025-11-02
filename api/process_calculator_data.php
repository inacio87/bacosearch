<?php
/**
 * process_calculator_data.php - VERSÃO ATUALIZADA E REFATORADA
 *
 * Processa o formulário de feedback da calculadora, agora integrado à tabela 'leads'.
 *
 * ÚLTIMA ATUALIZAÇÃO: 09/07/2025 - Refatorado para usar a tabela 'leads' com
 * persistência robusta dos detalhes JSON, tratamento de transações, e
 * ajustes na lógica de e-mail e redirecionamento. Adicionado HONEYPOT ANTI-SPAM.
 */

require_once dirname(__DIR__) . '/core/bootstrap.php'; // Carrega o bootstrap

// Definir headers para evitar cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Limpa e prepara os feedbacks de sessão para o formulário
$_SESSION['form_feedback_errors'] = [];
$_SESSION['form_feedback_success'] = false;

// Preserva os dados do POST para repopular o formulário em caso de erro
$form_data_to_preserve = $_POST;
unset($form_data_to_preserve['csrf_token'], $form_data_to_preserve['form_token_hidden']);
$_SESSION['form_data_calculator'] = $form_data_to_preserve;

// Adiciona um log para depuração inicial
log_system_error('Process Calculator Data: Requisição recebida.', 'DEBUG', 'process_calculator_data_request');

// =========================================================
// INÍCIO: VERIFICAÇÃO HONEYPOT (ANTI-SPAM)
// =========================================================
if (!empty($_POST['meu_campo_secreto_calculator'])) {
    log_system_error('Possível spam detectado via honeypot no formulário da calculadora. IP: ' . getClientIp(), 'WARNING', 'honeypot_spam_calculator');
    
    $redirect_to_calculator_with_token = SITE_URL . '/pages/license.php'; // Redireciona para license.php para o modal
    if (!empty($_POST['form_token_hidden'])) {
        $redirect_to_calculator_with_token .= "?token=" . urlencode($_POST['form_token_hidden']) . "&status=feedback_completed";
    } else {
        $redirect_to_calculator_with_token .= "?status=feedback_completed";
    }
    header("Location: " . $redirect_to_calculator_with_token);
    exit();
}
// =========================================================
// FIM: VERIFICAÇÃO HONEYPOT
// =========================================================


// =======================================================================
// VALIDAÇÃO DE SEGURANÇA (Tokens CSRF e de Acesso à Calculadora)
// =======================================================================
$current_lead_token = $_POST['form_token_hidden'] ?? null;
$session_token = $_SESSION['current_calculator_token'] ?? null;
$csrf_token_post = $_POST['csrf_token'] ?? '';
$csrf_token_session = $_SESSION['csrf_token'] ?? '';
$languageCode = $_SESSION['language'] ?? 'en-us'; // Define languageCode para traduções de erro

if (!$current_lead_token || !$session_token || !hash_equals($current_lead_token, $session_token) || !hash_equals($csrf_token_post, $csrf_token_session)) {
    log_system_error('Erro de segurança (CSRF/Token de acesso) no formulário da calculadora. Lead Token: ' . ($current_lead_token ?? 'N/A') . ', Session Token: ' . ($session_token ?? 'N/A'), 'CRITICAL', 'calculator_security_validation_failure');
    $_SESSION['form_feedback_errors']['general'] = getTranslation('error_security_validation', $languageCode, 'ui_messages');
    // Redireciona para a página de licença, pois o acesso via token falhou
    header("Location: " . SITE_URL . '/pages/license.php?status=calculator_access_denied');
    exit();
}

// O token CSRF deve ser consumido após o uso bem-sucedido da validação de segurança
unset($_SESSION['csrf_token']);


// =======================================================================
// VALIDAÇÃO DOS DADOS DO FORMULÁRIO
// =======================================================================
$name = trim($_POST['name'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$phone_code = trim($_POST['phone_code'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$country_of_interest = trim($_POST['country_of_interest'] ?? '');
$state_of_interest = trim($_POST['state_of_interest'] ?? '');
$digital_experience = trim($_POST['digital_experience'] ?? '');
$how_did_you_hear = trim($_POST['how_did_you_hear'] ?? '');
$message = trim($_POST['message'] ?? '');

if (empty($name)) {
    $_SESSION['form_feedback_errors']['name'] = getTranslation('error_name_required', $languageCode, 'validation_errors');
}
if (!$email) {
    $_SESSION['form_feedback_errors']['email'] = getTranslation('error_email_invalid', $languageCode, 'validation_errors');
}
if (empty($phone_number) || !preg_match('/^\d{7,15}$/', $phone_number)) {
    $_SESSION['form_feedback_errors']['phone_number'] = getTranslation('error_phone_invalid', $languageCode, 'validation_errors');
}

// Se houver erros de validação, redireciona de volta ao formulário
if (!empty($_SESSION['form_feedback_errors'])) {
    $_SESSION['form_feedback_errors']['general'] = getTranslation('error_form_correction', $languageCode, 'ui_messages');
    log_system_error('Erros de validação no formulário de feedback da calculadora para email: ' . ($email ?: 'N/A'), 'INFO', 'calculator_feedback_validation_errors');
    header("Location: " . SITE_URL . '/pages/calculator.php' . "?token=" . urlencode($current_lead_token));
    exit();
}

// Inicializa $db como null para garantir que o try/catch possa verificar se a conexão foi estabelecida.
$db = null;

// =======================================================================
// PERSISTÊNCIA DOS DADOS NO BANCO DE DADOS (NOVA LÓGICA COM TABELA 'leads')
// =======================================================================
try {
    $db = getDBConnection();
    $db->beginTransaction(); // Inicia uma transação para garantir atomicidade

    $visitor_db_id = $_SESSION['visitor_db_id'] ?? null;
    if (!$visitor_db_id) {
        log_system_error("process_calculator_data: visitor_db_id não encontrado na sessão. Possível problema de sessão ou rastreamento.", 'ERROR', 'calculator_feedback_no_visitor_id');
        $_SESSION['form_feedback_errors']['general'] = getTranslation('error_general_try_again', $languageCode, 'ui_messages');
        $db->rollBack();
        header("Location: " . SITE_URL . '/pages/calculator.php' . "?token=" . urlencode($current_lead_token));
        exit();
    }

    // Tentar encontrar o lead existente pelo token
    $stmt_find_lead = $db->prepare("SELECT id, token_status, details FROM leads WHERE lead_token = :lead_token LIMIT 1");
    $stmt_find_lead->execute([':lead_token' => $current_lead_token]);
    $existing_lead_data = $stmt_find_lead->fetch(PDO::FETCH_ASSOC);

    $lead_id = $existing_lead_data['id'] ?? null;
    $lead_status = $existing_lead_data['token_status'] ?? null;
    
    // Decodifica os detalhes existentes
    $current_details = json_decode($existing_lead_data['details'] ?? '{}', true);

    // Se o token já foi usado ou não encontrado, é um erro de segurança/token inválido
    if (!$lead_id || $lead_status !== 'active') {
        log_system_error("Process Calculator Data: Tentativa de submissão com token inválido, expirado ou já usado. Lead ID: {$lead_id}, Status: {$lead_status}, Token: {$current_lead_token}", 'WARNING', 'calculator_feedback_token_invalid_or_used');
        $_SESSION['form_feedback_errors']['general'] = getTranslation('error_token_not_found_or_used', $languageCode, 'ui_messages');
        $db->rollBack();
        header("Location: " . SITE_URL . '/pages/license.php?status=calculator_access_denied'); // Redireciona para página de licença
        exit();
    }

    // Preparar os dados específicos da calculadora para o campo JSON 'details'
    $new_calculator_details = [
        'name' => $name, // Pode ser atualizado se o usuário corrigiu
        'email' => $email, // Pode ser atualizado
        'phone_code' => $phone_code,
        'phone_number' => $phone_number,
        'country_of_interest' => $country_of_interest,
        'state_of_interest' => $state_of_interest,
        'digital_experience' => $digital_experience,
        'how_did_you_hear' => $how_did_you_hear,
        'message' => $message,
        'form_type' => 'license_calculator_feedback', // Identificador do tipo de formulário
        'feedback_submission_timestamp' => date('Y-m-d H:i:s') // Quando este feedback foi enviado
    ];
    
    // Adiciona o novo feedback a um array 'calculator_feedback_history' para manter histórico.
    // Isso garante que submissões múltiplas do mesmo formulário sejam registradas.
    if (!isset($current_details['calculator_feedback_history'])) {
        $current_details['calculator_feedback_history'] = [];
    }
    $current_details['calculator_feedback_history'][] = $new_calculator_details;

    $params = [
        ':lead_id' => $lead_id,
        ':visitor_id' => $visitor_db_id,
        ':name' => $name, // Atualiza o nome principal do lead
        ':email' => $email, // Atualiza o email principal do lead
        ':phone' => $phone_code . $phone_number, // Concatena e atualiza o campo phone
        ':form_source' => 'license_calculator', // A submissão mais recente é da calculadora
        ':details' => json_encode($current_details) // Salva os detalhes mesclados
    ];

    // Atualiza o lead.
    $stmt_update_lead = $db->prepare("UPDATE leads SET
        visitor_id = :visitor_id,
        name = :name,
        email = :email,
        phone = :phone,
        form_source = :form_source,
        details = :details,
        submitted_at = NOW(),
        token_status = 'used' -- Marca o token como usado se for de uso único após esta submissão
        WHERE id = :lead_id AND token_status = 'active'"); // Garante que atualiza apenas leads ativos

    $stmt_update_lead->execute($params);
    log_system_error("Lead de feedback da calculadora atualizado com sucesso. Lead ID: {$lead_id}", 'INFO', 'calculator_feedback_lead_updated');

    // Opcional: Atualizar o estágio do visitante para 'engaged'
    $stmt_update_visitor_stage = $db->prepare("UPDATE visitors SET session_stage = 'engaged', updated_at = NOW() WHERE id = ? AND session_stage IN ('session', 'consented')");
    $stmt_update_visitor_stage->execute([$visitor_db_id]);

    // Opcional: Registrar um evento de submissão do formulário na tabela 'events'
    $insert_event_stmt = $db->prepare("INSERT INTO events (visitor_id, event_type, event_name, event_data, created_at) VALUES (?, ?, ?, ?, NOW())");
    $event_data = [
        'lead_id' => $lead_id,
        'email' => $email,
        'form_source' => 'license_calculator_feedback',
        'details_submitted' => $new_calculator_details // Salva APENAS os detalhes do feedback atual no evento
    ];
    $insert_event_stmt->execute([$visitor_db_id, 'submit', 'calculator_feedback_submitted', json_encode($event_data)]);
    $new_event_id = $db->lastInsertId(); // Captura o ID do evento

    $db->commit(); // Confirma a transação se tudo correu bem
    log_system_error("Transação de feedback da calculadora confirmada para Lead ID: {$lead_id}", 'DEBUG', 'calculator_feedback_transaction_committed');


    // =========================================================
    // INÍCIO DA LÓGICA DE ENVIO DE E-MAILS (PARA O FEEDBACK DA CALCULADORA)
    // =========================================================
    // E-mail de Notificação para o Administrador sobre o Feedback da Calculadora
    if (defined('ADMIN_CONTACT_EMAIL') && !empty(ADMIN_CONTACT_EMAIL)) {
        $toAdminEmail = ADMIN_CONTACT_EMAIL;
        $adminSubject = getTranslation('calculator_feedback_email_subject_admin', $languageCode, 'email_templates');
        $adminTemplatePath = 'templates/emails/admin_calculator_feedback_notification.html'; // NOVO TEMPLATE PARA ADMIN

        $adminTemplateData = [
            'email_title_fallback' => $adminSubject, // Usar para o title no template
            'lead_name' => $name,
            'lead_email' => $email,
            'lead_phone' => $phone_code . $phone_number,
            'lead_country' => $country_of_interest,
            'lead_state' => $state_of_interest,
            'lead_digital_experience' => $digital_experience,
            'lead_how_did_you_hear' => $how_did_you_hear,
            'lead_message' => nl2br(htmlspecialchars($message)),
            'lead_ip' => getClientIp(),
            'lead_user_agent' => ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A'),
            'lead_id' => $lead_id,
            'lead_token' => $current_lead_token,
            'site_url' => SITE_URL,
            'current_year' => date('Y'),
            'site_name' => SITE_NAME,
            'all_rights_reserved' => getTranslation('all_rights_reserved', $languageCode, 'email_templates'),
            'spam_notice' => getTranslation('spam_notice', $languageCode, 'email_templates'),
            'email_sent_to_label' => getTranslation('email_sent_to_label', $languageCode, 'email_templates'),
            'mail_css_url' => SITE_URL . '/assets/css/mail.css',
            'logo_alt_text' => getTranslation('logo_alt_text', $languageCode, 'email_templates'),
        ];

        $email_sent_admin = send_email(
            $toAdminEmail,
            $adminSubject,
            $adminTemplatePath,
            $adminTemplateData,
            'calculator_feedback_admin_notification',
            $lead_id,
            $visitor_db_id,
            $new_event_id
        );

        if (!$email_sent_admin) {
            log_system_error("Falha ao enviar e-mail de feedback da calculadora para o administrador (email: {$toAdminEmail}).", 'ERROR', 'calculator_feedback_email_failure_admin');
        } else {
            log_system_error("E-mail de feedback da calculadora enviado para o administrador: {$toAdminEmail}.", 'INFO', 'calculator_feedback_email_sent_admin');
        }
    } else {
        log_system_error("ADMIN_CONTACT_EMAIL não definido ou vazio. E-mail de feedback da calculadora para o administrador não enviado.", 'CRITICAL', 'calculator_feedback_email_config_missing_admin');
    }

    // E-mail de Confirmação para o Usuário
    $toUserEmail = $email;
    $userSubject = getTranslation('calculator_feedback_email_subject_user_thank_you', $languageCode, 'email_templates');
    $userTemplatePath = 'templates/emails/calculator_feedback_thank_you.html'; // NOVO TEMPLATE PARA O USUÁRIO

    $userTemplateData = [
        'email_title_fallback' => $userSubject, // Usar para o title no template
        'user_name' => $name,
        'greeting' => getTranslation('greeting', $languageCode, 'email_templates'),
        'thank_you_message' => getTranslation('calculator_feedback_user_thank_you_message', $languageCode, 'email_templates'),
        'follow_up_message' => getTranslation('calculator_feedback_user_follow_up_message', $languageCode, 'email_templates'),
        'site_url' => SITE_URL,
        'current_year' => date('Y'),
        'site_name' => SITE_NAME,
        'all_rights_reserved' => getTranslation('all_rights_reserved', $languageCode, 'email_templates'),
        'spam_notice' => getTranslation('spam_notice', $languageCode, 'email_templates'),
        'email_sent_to_label' => getTranslation('email_sent_to_label', $languageCode, 'email_templates'),
        'user_email' => $email,
        'logo_alt_text' => getTranslation('logo_alt_text', $languageCode, 'email_templates'),
        'mail_css_url' => SITE_URL . '/assets/css/mail.css'
    ];

    $email_sent_user = send_email(
        $toUserEmail,
        $userSubject,
        $userTemplatePath,
        $userTemplateData,
        'calculator_feedback_user_confirmation',
        $lead_id,
        $visitor_db_id,
        null // Não é um evento primário, mas uma resposta (opcionalmente pode ser o mesmo $new_event_id se for considerado parte do mesmo processo)
    );

    if (!$email_sent_user) {
        log_system_error("Falha ao enviar e-mail de confirmação de feedback da calculadora para o usuário (email: {$toUserEmail}).", 'ERROR', 'calculator_feedback_email_failure_user');
    } else {
        log_system_error("E-mail de confirmação de feedback da calculadora enviado para o usuário: {$toUserEmail}.", 'INFO', 'calculator_feedback_email_sent_user');
    }
    // =========================================================
    // FIM DA LÓGICA DE ENVIO DE E-MAILS
    // =========================================================


    // Limpa os dados de sessão relacionados ao formulário após o sucesso completo e envio de emails
    unset($_SESSION['current_calculator_token']);
    unset($_SESSION['form_data_calculator']);
    $_SESSION['form_feedback_success'] = true; // Define o status de sucesso para a página de destino

    // Redireciona para license.php para exibir o modal de sucesso da calculadora
    header("Location: " . SITE_URL . '/pages/license.php' . "?status=feedback_completed");
    exit();

} catch (PDOException $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack(); // Reverte a transação em caso de erro no DB
    }
    log_system_error("Erro de banco de dados ao processar feedback da calculadora: " . $e->getMessage() . " | SQLSTATE: " . $e->getCode(), 'CRITICAL', 'calculator_feedback_db_error');
    $_SESSION['form_feedback_errors']['general'] = getTranslation('database_error', $languageCode, 'ui_messages');
    header("Location: " . SITE_URL . '/pages/calculator.php' . "?token=" . urlencode($current_lead_token));
    exit();
} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack(); // Reverte a transação em caso de erro inesperado
    }
    log_system_error("Erro inesperado ao processar feedback da calculadora: " . $e->getMessage(), 'CRITICAL', 'calculator_feedback_general_error');
    $_SESSION['form_feedback_errors']['general'] = getTranslation('generic_error', $languageCode, 'ui_messages');
    header("Location: " . SITE_URL . '/pages/calculator.php' . "?token=" . urlencode($current_lead_token));
    exit();
}