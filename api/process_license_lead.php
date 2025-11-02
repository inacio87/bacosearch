<?php
/**
 * process_license_lead.php - VERSÃO REFATORADA E ATUALIZADA
 *
 * Processa a submissão do formulário de licenciamento, agora integrado à tabela 'leads'.
 *
 * ÚLTIMA ATUALIZAÇÃO: 09/07/2025 - Refatorado para usar a tabela 'leads' com 'lead_token',
 * 'token_status', e 'token_expires_at', e para registar eventos. ADICIONADO HONEYPOT ANTI-SPAM.
 * Ajuste no caminho do bootstrap.php e correção de variáveis para send_email.
 */

// Ajuste no caminho do bootstrap.php
// Se process_license_lead.php está em /api/ e bootstrap.php está em /core/,
// então dirname(__DIR__) leva para o diretório "pai" de /api/, que seria a raiz do projeto.
require_once dirname(__DIR__) . '/core/bootstrap.php';

// Definir headers para evitar cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$redirect_url = SITE_URL . '/pages/license.php';

// Inicializa variáveis de sessão para erros e dados do formulário
$_SESSION['errors_license'] = [];
$_SESSION['form_data_license'] = $_POST;

// Adiciona um log para depuração inicial
log_system_error('Process License Lead: Requisição recebida.', 'DEBUG', 'process_license_lead_request');

// =========================================================
// INÍCIO: VERIFICAÇÃO HONEYPOT (ANTI-SPAM)
// =========================================================
if (!empty($_POST['meu_campo_secreto_license'])) {
    log_system_error('Possível spam detectado via honeypot no formulário de licenciamento. IP: ' . getClientIp(), 'WARNING', 'honeypot_spam_license');
    // Redireciona como se fosse sucesso para confundir o bot, e sai.
    header('Location: ' . $redirect_url . "?status=success");
    exit();
}
// =========================================================
// FIM: VERIFICAÇÃO HONEYPOT
// =========================================================


// =======================================================================
// VALIDAÇÃO DE SEGURANÇA (Token CSRF)
// =======================================================================
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    log_system_error('Erro CSRF no formulário de licenciamento. Token inválido ou ausente.', 'WARNING', 'csrf_validation_license');
    $_SESSION['errors_license'][] = getTranslation('csrf_token_invalid_error', $_SESSION['language'] ?? 'en-us', 'validation_errors');
    header("Location: " . $redirect_url);
    exit();
}
unset($_SESSION['csrf_token']); // O token CSRF deve ser consumido após o uso

// =======================================================================
// VALIDAÇÃO DOS DADOS DO FORMULÁRIO
// =======================================================================
$name = trim($_POST['name'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

if (empty($name)) {
    $_SESSION['errors_license'][] = getTranslation('name_required_error', $_SESSION['language'] ?? 'en-us', 'validation_errors');
}
if (!$email) {
    $_SESSION['errors_license'][] = getTranslation('email_invalid_error', $_SESSION['language'] ?? 'en-us', 'validation_errors');
}

// Se houver erros de validação, redireciona de volta ao formulário
if (!empty($_SESSION['errors_license'])) {
    log_system_error('Erros de validação no formulário de licenciamento para email: ' . ($email ?: 'N/A'), 'INFO', 'license_form_validation_errors');
    header("Location: " . $redirect_url);
    exit();
}

// Inicializa $db como null para garantir que o try/catch possa verificar se a conexão foi estabelecida.
$db = null;

// =======================================================================
// PERSISTÊNCIA DOS DADOS NO BANCO DE DADOS (NOVA LÓGICA COM TABELA 'leads')
// =======================================================================
try {
    $db = getDBConnection(); // Tenta obter a conexão com o banco de dados
    $db->beginTransaction(); // Inicia uma transação

    $access_token = bin2hex(random_bytes(32)); // Gera um novo token de acesso
    $token_expires_at = (new DateTime())->modify('+24 hours')->format('Y-m-d H:i:s');

    $visitor_db_id = $_SESSION['visitor_db_id'] ?? null; // ID numérico do visitante da sessão
    if (!$visitor_db_id) {
        log_system_error("process_license_lead: visitor_db_id não encontrado na sessão. Possível problema de sessão ou rastreamento.", 'ERROR', 'license_lead_no_visitor_id');
        $_SESSION['errors_license'][] = getTranslation('error_general_try_again', $_SESSION['language'] ?? 'en-us', 'ui_messages');
        $db->rollBack();
        header("Location: " . $redirect_url);
        exit();
    }

    // Tenta encontrar um lead existente com este email
    $stmt_find_lead = $db->prepare("SELECT id FROM leads WHERE email = :email LIMIT 1");
    $stmt_find_lead->execute([':email' => $email]);
    $existing_lead_id = $stmt_find_lead->fetchColumn();

    $common_params = [
        ':name'             => $name,
        ':email'            => $email,
        ':lead_token'       => $access_token,
        ':token_expires_at' => $token_expires_at,
        ':visitor_id'       => $visitor_db_id // Adiciona visitor_id aos parâmetros comuns
    ];

    if ($existing_lead_id) {
        // Atualiza o lead existente
        $stmt_update = $db->prepare("UPDATE leads SET
            name = :name,
            lead_token = :lead_token,
            token_status = 'active',
            token_expires_at = :token_expires_at,
            submitted_at = NOW(), -- Atualiza a data de submissão
            visitor_id = :visitor_id, -- Garante que o visitor_id esteja ligado
            details = JSON_SET(COALESCE(details, '{}'), '$.update_count', COALESCE(JSON_EXTRACT(details, '$.update_count'), 0) + 1) -- Rastrea updates
            WHERE id = :id");
        $common_params[':id'] = $existing_lead_id;
        $stmt_update->execute($common_params); // Executa com os parâmetros comuns
        log_system_error("Lead de licenciamento atualizado e novo token gerado para: {$email} (Lead ID: {$existing_lead_id})", 'INFO', 'license_lead_update');

    } else {
        // Insere um novo lead
        $stmt_insert = $db->prepare("INSERT INTO leads (name, email, lead_token, token_status, token_expires_at, visitor_id, form_source, submitted_at, details)
                                     VALUES (:name, :email, :lead_token, 'active', :token_expires_at, :visitor_id, 'license_form', NOW(), :details_json)");

        // Adiciona detalhes JSON para o INSERT (para que o campo não seja NULL na primeira inserção)
        $initial_details = json_encode([
            'ip_address' => getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'submission_page' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'update_count' => 0 // Inicializa a contagem de updates
        ]);
        $common_params[':details_json'] = $initial_details; // Adiciona o JSON aos parâmetros

        $stmt_insert->execute($common_params); // Executa com os parâmetros comuns (incluindo :details_json)
        $existing_lead_id = $db->lastInsertId(); // Obtém o ID do novo lead inserido
        log_system_error("Novo lead de licenciamento inserido e token gerado para: {$email} (Lead ID: {$existing_lead_id})", 'INFO', 'license_lead_submission');
    }

    // Opcional: Atualizar o estágio do visitante para 'engaged' após a submissão do formulário
    $stmt_update_visitor_stage = $db->prepare("UPDATE visitors SET session_stage = 'engaged', updated_at = NOW() WHERE id = ? AND session_stage IN ('session', 'consented')");
    $stmt_update_visitor_stage->execute([$visitor_db_id]);

    // Opcional: Registrar um evento de submissão do formulário na tabela 'events'
    $insert_event_stmt = $db->prepare("INSERT INTO events (visitor_id, event_type, event_name, event_data, created_at) VALUES (?, ?, ?, ?, NOW())");
    $event_data = [
        'lead_id' => $existing_lead_id,
        'email' => $email,
        'form_source' => 'license_form_submission',
        'lead_token' => $access_token,
        'name' => $name
    ];
    $insert_event_stmt->execute([$visitor_db_id, 'submit', 'license_form_submitted', json_encode($event_data)]);
    $new_event_id = $db->lastInsertId(); // Captura o ID do evento para associar ao e-mail

    $db->commit(); // Confirma a transação

    // Prepara o link da calculadora para o email
    $calculator_link = SITE_URL . '/pages/calculator.php?token=' . urlencode($access_token);
    
    // Armazena o token na sessão para o uso da calculadora
    $_SESSION['current_calculator_token'] = $access_token;


    // =========================================================
    // INÍCIO DA LÓGICA DE ENVIO DE E-MAILS (PARA O LEAD DE LICENÇA)
    // =========================================================
    // E-mail de Notificação e Acesso à Calculadora para o Usuário
    $toUserEmail = $email; 
    $userSubject = getTranslation('license_email_subject', $_SESSION['language'] ?? 'en-us', 'email_templates'); 

    $userTemplatePath = 'templates/emails/license_access_email.html'; // NOVO TEMPLATE PARA O EMAIL DE LICENÇA

    // Dados para preencher o template de e-mail de acesso
    $userTemplateData = [
        'email_title_fallback' => $userSubject,
        'greeting' => getTranslation('greeting', $_SESSION['language'] ?? 'en-us', 'email_templates'), // Adicione esta chave
        'license_access_granted_message' => getTranslation('license_access_granted_message', $_SESSION['language'] ?? 'en-us', 'email_templates'), // Adicione esta chave
        'license_details_message' => getTranslation('license_details_message', $_SESSION['language'] ?? 'en-us', 'email_templates'), // Adicione esta chave
        'button_text_access_content' => getTranslation('button_text_access_content', $_SESSION['language'] ?? 'en-us', 'email_templates'), // Adicione esta chave
        'user_name' => $name,
        'access_link' => $calculator_link, // Use 'access_link' aqui para o template do email
        'site_url' => SITE_URL,
        'current_year' => date('Y'),
        'site_name' => SITE_NAME,
        'all_rights_reserved' => getTranslation('all_rights_reserved', $_SESSION['language'] ?? 'en-us', 'email_templates'),
        'spam_notice' => getTranslation('spam_notice', $_SESSION['language'] ?? 'en-us', 'email_templates'),
        'email_sent_to_label' => getTranslation('email_sent_to_label', $_SESSION['language'] ?? 'en-us', 'email_templates'),
        'user_email' => $email,
        'logo_alt_text' => getTranslation('logo_alt_text', $_SESSION['language'] ?? 'en-us', 'email_templates'),
        'mail_css_url' => SITE_URL . '/assets/css/mail.css',
        // Adicione aqui outras chaves de tradução específicas para o template license_access_email.html
        // Por exemplo: 'license_email_intro', 'license_email_cta', 'license_email_instructions'
    ];

    $email_sent_user_license = send_email(
        $toUserEmail,
        $userSubject,
        $userTemplatePath,
        $userTemplateData,
        'license_access_email', // Tipo para registro
        $existing_lead_id,
        $visitor_db_id, // Usar a variável correta $visitor_db_id
        $new_event_id
    );

    if (!$email_sent_user_license) {
        log_system_error("Falha ao enviar e-mail de acesso à licença para o usuário (email: {$toUserEmail}).", 'ERROR', 'license_email_sending_failure_user');
    }
    // =========================================================
    // FIM DA LÓGICA DE ENVIO DE E-MAILS
    // =========================================================

    // Limpa os dados de sessão do formulário após o sucesso completo
    unset($_SESSION['form_data_license']);
    $_SESSION['form_feedback_success'] = true;

    // Redireciona para a página de sucesso
    header("Location: " . $redirect_url . "?status=success");
    exit();

} catch (PDOException $e) {
    if ($db && $db->inTransaction()) { // Verifica se $db é válido antes de chamar inTransaction()
        $db->rollBack();
    }
    log_system_error("Erro de banco de dados no processamento de lead de licenciamento: " . $e->getMessage() . " | SQLSTATE: " . $e->getCode(), 'CRITICAL', 'license_lead_db_error');
    $_SESSION['errors_license'][] = getTranslation('database_error', $_SESSION['language'] ?? 'en-us', 'ui_messages');
    header("Location: " . $redirect_url);
    exit();
} catch (Exception $e) {
    if ($db && $db->inTransaction()) { // Verifica se $db é válido antes de chamar inTransaction()
        $db->rollBack();
    }
    log_system_error("Erro inesperado no processamento de lead de licenciamento: " . $e->getMessage(), 'CRITICAL', 'license_lead_general_error');
    $_SESSION['errors_license'][] = getTranslation('generic_error', $_SESSION['language'] ?? 'en-us', 'ui_messages');
    header("Location: " . $redirect_url);
    exit();
}