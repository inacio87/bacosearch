<?php
/**
 * /api/verify_email_and_set_password.php - Ponto de Entrada para Verificação de E-mail e Definição de Senha
 *
 * RESPONSABILIDADES:
 * 1. Valida o token de verificação recebido via URL.
 * 2. Se o token for válido e não expirado, exibe um formulário para o utilizador definir a senha.
 * 3. Após a submissão da senha, faz o hash da senha e a armazena na tabela 'accounts'.
 * 4. Atualiza o status da conta para 'pending_admin_approval'.
 * 5. Remove o token de verificação da conta (já utilizado).
 * 6. Dispara um e-mail de notificação para o administrador sobre a nova conta pendente.
 * 7. Redireciona o utilizador para a página de continuação do registo (register_[role].php) e efetua o login automático.
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';

$languageCode = $_SESSION['language'] ?? LANGUAGE_CONFIG['default'] ?? 'pt-br';

// Variáveis de estado para a página
$token_valid = false;
$account_id = null;
$user_email = null;
$full_name = null;
$role_slug = null;
$errors = [];
$success_message_key = '';
$show_set_password_form = false;

$db = getDBConnection();

// Adicionei isso para garantir que $account esteja disponível mesmo em erros pós-POST
$account = null; 

// --- FASE 1: VALIDAÇÃO DO TOKEN (GET request) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        $stmt = $db->prepare("SELECT id, email, full_name, role, status, verification_expires_at FROM accounts WHERE verification_token = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC); // $account definida aqui

        if ($account) {
            $current_time = new DateTime();
            $expiry_time = new DateTime($account['verification_expires_at']);

            if ($account['status'] === 'pending_email_verification' && $current_time < $expiry_time) {
                $token_valid = true;
                $account_id = $account['id'];
                $user_email = $account['email'];
                $full_name = $account['full_name'];
                $role_slug = $account['role'];
                $show_set_password_form = true;
            } elseif ($account['status'] !== 'pending_email_verification') {
                $errors['general'] = getTranslation('verification_token_already_used', $languageCode, 'ui_messages');
                log_system_error("Token de verificação usado ou conta em status inválido. Account ID: {$account['id']}", 'NOTICE', 'verify_email_used_or_invalid_status');
            } else { // Token expirado
                $errors['general'] = getTranslation('verification_token_expired', $languageCode, 'ui_messages');
                log_system_error("Token de verificação expirado para Account ID: {$account['id']}", 'NOTICE', 'verify_email_expired_token');
            }
        } else {
            $errors['general'] = getTranslation('verification_token_invalid', $languageCode, 'ui_messages');
            log_system_error("Token de verificação inválido recebido: {$token}", 'WARNING', 'verify_email_invalid_token');
        }
    } catch (Exception $e) {
        $errors['general'] = getTranslation('error_general_try_again', $languageCode, 'ui_messages');
        log_system_error("Erro ao validar token de verificação: " . $e->getMessage(), 'CRITICAL', 'verify_email_db_error');
    }
}
// --- FASE 2: PROCESSAMENTO DO FORMULÁRIO DE DEFINIÇÃO DE SENHA (POST request) ---
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'], $_POST['password'], $_POST['confirm_password'])) {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    try {
        $stmt = $db->prepare("SELECT id, email, full_name, role, status, verification_expires_at FROM accounts WHERE verification_token = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC); // $account redefinida aqui para o contexto POST

        // Revalidação CRÍTICA do token no POST
        if (!$account || $account['status'] !== 'pending_email_verification' || (new DateTime()) > (new DateTime($account['verification_expires_at']))) {
            // Este é o cenário que leva à mensagem "Link de verificação inválido ou expirado."
            $errors['general'] = getTranslation('verification_token_invalid_or_expired', $languageCode, 'ui_messages');
            log_system_error("Tentativa de uso de token inválido/expirado/status incorreto durante POST. Account: " . ($account['id'] ?? 'N/A') . " Status: " . ($account['status'] ?? 'N/A') . " Token: {$token}", 'WARNING', 'verify_email_post_invalid_token');
            
            // Para reexibir o formulário com o erro (e talvez uma opção de reenviar email)
            $token_valid = false; // O token não é válido para exibir o form de sucesso
            $show_set_password_form = false; // Não exibe o formulário novamente
            // Set user_email if account data is available for resend link
            if ($account) {
                $user_email = $account['email'];
            }
            // Não redireciona aqui. Permite que a página se renderize e mostre o erro.
        } else {
            // Se o token é válido para o POST, preenche as variáveis de sessão para a view
            $token_valid = true; // Marca como válido para exibir o formulário caso haja outros erros
            $account_id = $account['id'];
            $user_email = $account['email'];
            $full_name = $account['full_name'];
            $role_slug = $account['role'];

            // Validação da senha
            if (empty($password) || empty($confirm_password)) {
                $errors['password'] = getTranslation('password_required', $languageCode, 'validation_errors');
            } elseif ($password !== $confirm_password) {
                $errors['confirm_password'] = getTranslation('passwords_do_not_match', $languageCode, 'validation_errors');
            } elseif (mb_strlen($password) < 8) {
                $errors['password'] = getTranslation('password_min_length', $languageCode, 'validation_errors');
            }
            // Validação de complexidade da senha
            if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
                $errors['password_complexity'] = getTranslation('password_complexity_error', $languageCode, 'validation_errors');
            }

            if (empty($errors)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt_update_account = $db->prepare("
                    UPDATE accounts
                    SET password_hash = :password_hash, status = 'pending_admin_approval',
                        verification_token = NULL, verification_expires_at = NULL, updated_at = NOW()
                    WHERE id = :id AND verification_token = :token AND status = 'pending_email_verification'
                ");
                $rows_affected = $stmt_update_account->execute([
                    ':password_hash' => $password_hash,
                    ':id' => $account_id,
                    ':token' => $token
                ]);

                if ($rows_affected > 0) {
                    if (isset($_SESSION['visitor_db_id'])) {
                        $event_data = [
                            'account_id' => $account_id, 'email' => $user_email, 'status_after_action' => 'pending_admin_approval'
                        ];
                        $insert_event_stmt = $db->prepare("INSERT INTO events (visitor_id, event_type, event_name, event_data, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $insert_event_stmt->execute([$_SESSION['visitor_db_id'], 'user_action', 'email_verified_password_set', json_encode($event_data)]);

                        $stmt_update_visitor_stage = $db->prepare("UPDATE visitors SET session_stage = 'email_verified_password_set', updated_at = NOW() WHERE id = ? AND session_stage IN ('registered_pending_email_verification')");
                        $stmt_update_visitor_stage->execute([$_SESSION['visitor_db_id']]);
                    }

                    // Disparar e-mail para o administrador
                    if (defined('ADMIN_CONTACT_EMAIL')) {
                        $admin_subject = getTranslation('admin_notification_new_approval_request_subject', $languageCode, 'email_templates');
                        $adminTemplateData = [
                            'admin_subject_fallback' => htmlspecialchars($admin_subject), // Escapar aqui
                            'user_name' => htmlspecialchars($full_name), // Escapar aqui
                            'user_email' => htmlspecialchars($user_email), // Escapar aqui
                            'account_type' => htmlspecialchars(getTranslation('account_type_' . $role_slug, $languageCode, 'admin_users')), // Escapar aqui
                            'account_id' => htmlspecialchars($account_id), // Escapar aqui
                            'admin_dashboard_link' => htmlspecialchars(SITE_URL . '/admin/dashboard.php?module=users&status=pending_admin_approval'), // Escapar aqui
                            'site_name' => htmlspecialchars(SITE_NAME), // Escapar aqui
                            'mail_css_url' => htmlspecialchars(SITE_URL . '/assets/css/mail.css'), // Escapar aqui
                            'current_year' => htmlspecialchars(date('Y')), // Escapar aqui
                            'all_rights_reserved' => htmlspecialchars(getTranslation('all_rights_reserved', $languageCode, 'email_templates') ?? 'Todos os direitos reservados.'), // Escapar aqui
                            'spam_notice' => htmlspecialchars(getTranslation('spam_notice', $languageCode, 'email_templates') ?? 'Se você não solicitou este contato, por favor, ignore este e-mail.'), // Escapar aqui
                        ];

                        $email_sent_admin = send_email(
                            ADMIN_CONTACT_EMAIL,
                            $admin_subject,
                            'templates/emails/admin_new_account_approval_notification.html',
                            $adminTemplateData,
                            'admin_new_account_approval_notification',
                            $account_id,
                            null,
                            null
                        );

                        if (!$email_sent_admin) {
                            log_system_error("Falha ao enviar e-mail de notificação de aprovação para o administrador (email: " . ADMIN_CONTACT_EMAIL . ", user_id: {$account_id}).", 'ERROR', 'admin_approval_notification_email_failure');
                        }
                    } else {
                        log_system_error("ADMIN_CONTACT_EMAIL não definido. E-mail de notificação de aprovação do administrador não enviado para account_id: {$account_id}.", 'CRITICAL', 'admin_email_config_missing');
                    }

                    // Definir sessão para login automático e redirecionar
                    session_regenerate_id(true);
                    $_SESSION['account_id'] = $account_id;
                    $_SESSION['user_data'] = [
                        'id' => $account_id, 'email' => $user_email, 'role' => $role_slug, 'is_logged_in' => true
                    ];
                    $continue_registration_token = bin2hex(random_bytes(16));
                    $_SESSION['registration_continue_token_' . $account_id] = $continue_registration_token;

                    db_execute("UPDATE accounts SET last_login_at = NOW() WHERE id = ?", [$account_id]);

                    $next_registration_page = SITE_URL . '/pages/register_' . $role_slug . '.php?account_id=' . $account_id . '&token=' . $continue_registration_token;
                    header('Location: ' . $next_registration_page);
                    exit();

                } else {
                    // Se rows_affected é 0, o token pode ter sido usado entre a validação e a atualização.
                    $errors['general'] = getTranslation('verification_token_invalid_or_expired', $languageCode, 'ui_messages');
                    log_system_error("Atualização da senha falhou (0 rows affected). Token ou status alterado? Account ID: {$account_id}", 'ERROR', 'password_update_no_rows_affected');
                }
            } else {
                // Se houver erros de validação de senha, reexibe o formulário
                $show_set_password_form = true;
            }
        }
    } catch (Exception $e) {
        $errors['general'] = $e->getMessage();
        log_system_error('Erro geral ao definir senha e verificar e-mail: ' . $e->getMessage(), 'CRITICAL', 'set_password_process_error_catch');
        $token_valid = true; // Para reexibir o formulário com erros de PHP/DB
        $show_set_password_form = true; // Mantém o formulário visível para correção
        // Tenta preencher para o link de reenviar email
        if (isset($account)) {
            $user_email = $account['email'];
            $full_name = $account['full_name'];
            $role_slug = $account['role'];
        }
    }
} else {
    // Se não é GET com token ou POST válido
    if (empty($errors)) {
        $errors['general'] = getTranslation('invalid_access_no_token', $languageCode, 'ui_messages');
    }
}

// Prepara as traduções necessárias para a view
$translations = [];
$keys_to_translate = [
    'email_verification_title', 'set_password_subtitle', 'label_password', 'label_confirm_password',
    'button_set_password', 'password_required', 'passwords_do_not_match', 'password_min_length',
    'password_complexity_error', // Chave de erro de complexidade
    'error_general_try_again', 'verification_token_invalid', 'verification_token_expired',
    'verification_token_already_used', 'invalid_access_no_token', 'password_set_pending_admin_approval',
    'invalid_link_or_token', 'resend_email_prompt', 'resend_email_link',
    'verify_email_meta_description', 'form_errors_title', // Adicionado form_errors_title aqui para o contexto correto
    // Header e Footer
    'logo_alt', 'header_ads', 'header_login', 'header_menu', 'about_us',
    'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us',
    'header_licenses', 'footer_providers', 'footer_companies', 'footer_services',
    'detecting_location', 'enter_button', 'greeting',
    // Para o e-mail do admin (e suas traduções)
    'admin_notification_new_approval_request_subject', 'account_type_providers', 'account_type_businesses',
    'all_rights_reserved', 'spam_notice', 'email_sent_to_label',
    'error_updating_password', 'email_already_registered_pending_admin_approval',
];

foreach ($keys_to_translate as $key) {
    $context = 'ui_messages';
    if (in_array($key, ['email_verification_title', 'set_password_subtitle', 'label_password', 'label_confirm_password', 'button_set_password', 'verify_email_meta_description'])) {
        $context = 'set_password_page';
    } elseif (in_array($key, ['password_required', 'passwords_do_not_match', 'password_min_length', 'password_complexity_error', 'verification_token_invalid', 'verification_token_expired', 'verification_token_already_used', 'invalid_access_no_token'])) {
        $context = 'validation_errors'; // Erros de validação
    } elseif (in_array($key, ['error_general_try_again', 'invalid_link_or_token', 'resend_email_prompt', 'resend_email_link', 'form_errors_title', 'password_set_pending_admin_approval', 'error_updating_password', 'email_already_registered_pending_admin_approval'])) {
        $context = 'ui_messages'; // Mensagens gerais de UI e erros
    } elseif (in_array($key, ['admin_notification_new_approval_request_subject', 'all_rights_reserved', 'spam_notice', 'email_sent_to_label', 'greeting'])) {
        $context = 'email_templates'; // Traduções para e-mails
    } elseif (in_array($key, ['logo_alt', 'header_ads', 'header_login', 'header_menu', 'about_us', 'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us', 'detecting_location', 'header_licenses', 'enter_button'])) {
        $context = 'header';
    } elseif (str_starts_with($key, 'footer_')) {
        $context = 'footer';
    } elseif (str_starts_with($key, 'account_type_')) {
        $context = 'admin_users';
    }
    $translations[$key] = getTranslation($key, $languageCode, $context);
}

// Prepara dados para o header
$translations['languageOptionsForDisplay'] = LANGUAGE_CONFIG['name_map'] ?? [];
$translations['current_language_display_name'] = $translations['languageOptionsForDisplay'][$languageCode] ?? 'Language';

$page_title = $translations['email_verification_title'] ?? 'Verificar E-mail';
$meta_description = $translations['verify_email_meta_description'] ?? 'Verifique seu e-mail e defina sua senha para ativar sua conta.';

// CSS específico para a página
$page_specific_styles = [
    SITE_URL . '/assets/css/register.css',
    SITE_URL . '/assets/css/auth.css',
    SITE_URL . '/assets/css/pages.css'
];

require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';
?>

<main>
    <div class="main-container">
        <div class="register-container">
            <div class="register-header">
                <h1><?= htmlspecialchars($page_title) ?></h1>
                <p class="subtitle"><?= htmlspecialchars($translations['set_password_subtitle'] ?? 'Defina a sua senha para continuar.') ?></p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-message show">
                    <strong><?= htmlspecialchars($translations['form_errors_title'] ?? 'Por favor, corrija os seguintes erros:'); ?></strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($show_set_password_form): ?>
                <form action="<?= htmlspecialchars(SITE_URL . '/api/verify_email_and_set_password.php') ?>" method="POST" class="register-form" id="set-password-form">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    
                    <div class="form-group">
                        <label for="password"><?= htmlspecialchars($translations['label_password'] ?? 'Senha') ?></label>
                        <input type="password" id="password" name="password" required minlength="8">
                        <?php if (isset($errors['password'])): ?><span class="error-text"><?= htmlspecialchars($errors['password']) ?></span><?php endif; ?>
                        <?php if (isset($errors['password_complexity'])): ?><span class="error-text"><?= htmlspecialchars($errors['password_complexity']) ?></span><?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password"><?= htmlspecialchars($translations['label_confirm_password'] ?? 'Confirmar Senha') ?></label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                        <?php if (isset($errors['confirm_password'])): ?><span class="error-text"><?= htmlspecialchars($errors['confirm_password']) ?></span><?php endif; ?>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary"><?= htmlspecialchars($translations['button_set_password'] ?? 'Definir Senha') ?></button>
                    </div>
                </form>
            <?php else: ?>
                <div class="info-message">
                    <p style="text-align: center; color: var(--bs-danger, #dc3545);">
                        <?= htmlspecialchars($errors['general'] ?? $translations['invalid_link_or_token'] ?? 'Link de verificação inválido ou expirado.') ?>
                    </p>
                    <?php if (isset($errors['general']) && $errors['general'] === $translations['verification_token_expired']): ?>
                        <p style="text-align: center; margin-top: 20px;">
                            <?= htmlspecialchars($translations['resend_email_prompt'] ?? 'Precisa de um novo link?') ?> 
                            <a href="<?= SITE_URL ?>/auth/resend_verification.php?email=<?= urlencode($user_email ?? '') ?>">
                                <?= htmlspecialchars($translations['resend_email_link'] ?? 'Clique aqui para reenviar o e-mail de verificação.') ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
require_once TEMPLATE_PATH . 'footer.php';
ob_end_flush();
?>