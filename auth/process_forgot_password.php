<?php
/**
 * /auth/process_forgot_password.php - VERSÃO FINAL E TOTALMENTE COMPATÍVEL
 */

require_once dirname(__DIR__, 1) . '/core/bootstrap.php';

// Define o idioma o mais cedo possível para as mensagens de erro
$languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : 'pt-br';

// Segurança: Apenas método POST e validação de CSRF
$csrf_session = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || !hash_equals($csrf_session, $_POST['csrf_token'])) {
    http_response_code(403);
    exit(getTranslation('access_denied', $languageCode, 'ui_messages'));
}

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$birth_date = isset($_POST['birth_date']) ? $_POST['birth_date'] : null;

// Mensagem de sucesso genérica para não dar pistas a atacantes
$redirect_url = SITE_URL . '/auth/forgot_password.php';
$_SESSION['success_forgot'] = getTranslation('reset_link_sent_confirmation', $languageCode, 'ui_messages');

if (!$email || !$birth_date) {
    header('Location: ' . $redirect_url);
    exit();
}

try {
    $db = getDBConnection();
    
    $stmt = $db->prepare("SELECT id, full_name, birth_date FROM accounts WHERE email = :email AND status = 'active'");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['birth_date'] === $birth_date) {
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $update_stmt = $db->prepare("UPDATE accounts SET password_reset_token = :token, password_reset_expires_at = :expires WHERE id = :id");
        $update_stmt->execute([':token' => $token, ':expires' => $expires_at, ':id' => $user['id']]);

        $reset_link = SITE_URL . '/auth/reset_password.php?token=' . $token;
        
        $templateData = [
            'email_title_fallback' => getTranslation('email_title_password_reset', $languageCode, 'email_templates'),
            'mail_css_url' => SITE_URL . '/assets/css/mail.css',
            'site_url' => SITE_URL,
            'logo_alt_text' => getTranslation('logo_alt', $languageCode, 'header'),
            'greeting' => getTranslation('greeting', $languageCode, 'email_templates'),
            'user_name' => $user['full_name'],
            'password_reset_message' => getTranslation('password_reset_message', $languageCode, 'email_templates'),
            'reset_link' => $reset_link,
            'button_text_reset_password' => getTranslation('button_text_reset_password', $languageCode, 'email_templates'),
            'security_notice' => getTranslation('security_notice_password_reset', $languageCode, 'email_templates'),
            'current_year' => date('Y'),
            'site_name' => SITE_NAME,
            'all_rights_reserved' => getTranslation('all_rights_reserved', $languageCode, 'email_templates'),
            'spam_notice' => getTranslation('spam_notice', $languageCode, 'email_templates'),
            'email_sent_to_label' => getTranslation('email_sent_to_label', $languageCode, 'email_templates'),
            'user_email' => $email,
            'tracking_pixel_url' => SITE_URL . '/api/track_email_open.php?type=password_reset&id=' . $user['id']
        ];
        
        send_email(
            $email, 
            $templateData['email_title_fallback'], 
            'templates/emails/password_reset.html',
            $templateData
        );
    }

} catch (Exception $e) {
    log_system_error("Erro no processo de esqueci a senha: " . $e->getMessage(), 'critical', 'forgot_password_process');
}

header('Location: ' . $redirect_url);
exit();