<?php
/**
 * /auth/resend_verification.php - PONTO DE ENTRADA PARA REENVIO DE E-MAIL (VERSÃO FINAL E COMPATÍVEL)
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';

// AJUSTADO: Removido o operador ?? para compatibilidade
$languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : (isset(LANGUAGE_CONFIG['default']) ? LANGUAGE_CONFIG['default'] : 'pt-br');
$redirect_url_on_success = SITE_URL . '/register.php?status=resend_success';
$redirect_url_on_error = SITE_URL . '/register.php?status=resend_error';

$cooldown_period = 300;
if (isset($_SESSION['last_resend_timestamp']) && (time() - $_SESSION['last_resend_timestamp']) < $cooldown_period) {
    $_SESSION['errors_register']['general'] = getTranslation('error_resend_too_soon', $languageCode, 'ui_messages');
    header('Location: ' . $redirect_url_on_error);
    exit();
}

$email_to_resend = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['email'])) {
    $email_to_resend = filter_var($_GET['email'], FILTER_VALIDATE_EMAIL);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email_to_resend = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
}

if (!$email_to_resend) {
    $_SESSION['errors_register']['general'] = getTranslation('email_invalid_error', $languageCode, 'validation_errors');
    header('Location: ' . $redirect_url_on_error);
    exit();
}

$db = getDBConnection();

try {
    $stmt_account = $db->prepare("SELECT id, full_name, email, status FROM accounts WHERE email = :email LIMIT 1");
    $stmt_account->execute([':email' => $email_to_resend]);
    $account = $stmt_account->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        log_system_error("Tentativa de reenviar email para conta inexistente: {$email_to_resend}", 'NOTICE', 'resend_verification_nonexistent_account');
        $_SESSION['registration_success_message'] = getTranslation('resend_email_sent_confirmation', $languageCode, 'ui_messages');
        header('Location: ' . $redirect_url_on_success);
        exit();
    }

    $account_id = $account['id'];
    $current_status = $account['status'];

    if ($current_status !== 'pending_email_verification') {
        if ($current_status === 'active' || $current_status === 'pending_admin_approval') {
            $_SESSION['registration_info_message'] = getTranslation('account_already_verified_or_approved', $languageCode, 'ui_messages');
            header('Location: ' . SITE_URL . '/auth/login.php');
            exit();
        } else {
            $_SESSION['registration_info_message'] = getTranslation('account_status_prevents_resend', $languageCode, 'ui_messages');
            header('Location: ' . SITE_URL . '/register.php?status=info');
            exit();
        }
    }

    $new_verification_token = bin2hex(random_bytes(32));
    $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $stmt_update_token = $db->prepare("UPDATE accounts SET verification_token = :token, verification_expires_at = :expiry, updated_at = NOW() WHERE id = :id AND status = 'pending_email_verification'");
    $rows_affected = $stmt_update_token->execute([':token' => $new_verification_token, ':expiry' => $token_expiry, ':id' => $account_id]);

    if ($rows_affected === 0) {
        throw new Exception(getTranslation('error_updating_verification_token', $languageCode, 'ui_messages'));
    }

    // Assumindo que a função send_email está configurada corretamente
    $verification_link = SITE_URL . '/auth/verify_email.php?token=' . $new_verification_token;
    $templateData = [
        'user_name' => $account['full_name'],
        'verification_link' => $verification_link
        // ... outras variáveis necessárias para o template
    ];
    $email_sent = send_email(
        $email_to_resend,
        getTranslation('email_subject_verify_account', $languageCode, 'email_templates'),
        'templates/emails/email_verification.html',
        $templateData
    );

    if (!$email_sent) {
        $_SESSION['errors_register']['general'] = getTranslation('error_resend_email_failed', $languageCode, 'ui_messages');
        header('Location: ' . $redirect_url_on_error);
        exit();
    }
    
    $_SESSION['last_resend_timestamp'] = time();

    $_SESSION['registration_success_message'] = getTranslation('resend_email_sent_confirmation', $languageCode, 'ui_messages');
    header('Location: ' . $redirect_url_on_success);
    exit();

} catch (Exception $e) {
    log_system_error("Erro no reenvio de e-mail de verificação para {$email_to_resend}: " . $e->getMessage(), 'CRITICAL', 'resend_verification_process_error');
    $_SESSION['errors_register']['general'] = getTranslation('error_general_resend', $languageCode, 'ui_messages');
    header('Location: ' . $redirect_url_on_error);
    exit();
}