<?php
/**
 * /auth/process_login.php - Processador de Login (VERSÃO FINAL E TOTALMENTE COMPATÍVEL)
 */

// PASSO 1: INICIALIZAÇÃO E FUNÇÃO AUXILIAR
require_once dirname(__DIR__, 1) . '/core/bootstrap.php';

function redirectToLogin($errors = [], $formData = []) {
    $_SESSION['errors_login'] = $errors;
    $_SESSION['form_data_login'] = $formData;
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

$languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : 'pt-br';

// PASSO 2: VERIFICAÇÕES DE SEGURANÇA
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit(getTranslation('method_not_allowed', $languageCode, 'ui_messages'));
}

$csrf_session = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
if (!isset($_POST['csrf_token']) || !hash_equals($csrf_session, $_POST['csrf_token'])) {
    redirectToLogin(['general' => getTranslation('csrf_token_invalid_error', $languageCode, 'validation_errors')]);
}

// PASSO 3: VALIDAÇÃO DOS INPUTS DO FORMULÁRIO
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = isset($_POST['password']) ? $_POST['password'] : null;
$form_role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);

$errors = [];
if (!$email) {
    $errors['email'] = getTranslation('email_invalid_error', $languageCode, 'validation_errors');
}
if (empty($password)) {
    $errors['password'] = getTranslation('password_required_error', $languageCode, 'validation_errors');
}
if (!$form_role_id) {
    $errors['role_id'] = getTranslation('role_required_error', $languageCode, 'validation_errors');
}

if (!empty($errors)) {
    redirectToLogin($errors, ['email' => $email, 'role_id' => $form_role_id]);
}

// PASSO 4: LÓGICA DE AUTENTICAÇÃO
try {
    $db = getDBConnection();

    $stmt = $db->prepare("SELECT * FROM accounts WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash']) || $user['role_id'] != $form_role_id) {
        redirectToLogin(['general' => getTranslation('login_failed_error', $languageCode, 'validation_errors')]);
    }

    if ($user['status'] !== 'active') {
        $status_error_key = 'status_error_' . $user['status'];
        $error_message = getTranslation($status_error_key, $languageCode, 'validation_errors');
        $fallback_message = getTranslation('account_inactive_generic_error', $languageCode, 'validation_errors'); // Chave genérica
        redirectToLogin(['general' => !empty($error_message) ? $error_message : $fallback_message]);
    }

    // PASSO 5: SUCESSO! CRIAR A SESSÃO CORRETA
    unset($_SESSION['errors_login'], $_SESSION['form_data_login']);
    session_regenerate_id(true);

    if ($user['role_id'] == 5) { // ID de Admin
        $stmt_admin = $db->prepare("SELECT id, name FROM admins WHERE account_id = :account_id");
        $stmt_admin->execute([':account_id' => $user['id']]);
        $admin_record = $stmt_admin->fetch(PDO::FETCH_ASSOC);

        if (!$admin_record) {
            log_system_error("Erro de configuração: Utilizador {$user['id']} tem role admin mas não está na tabela 'admins'.", 'critical', 'login_auth_mismatch');
            redirectToLogin(['general' => getTranslation('admin_config_error', $languageCode, 'validation_errors')]);
        }

        $_SESSION['admin_id'] = $admin_record['id'];
        $_SESSION['admin_name'] = $admin_record['name'];
        header('Location: ' . SITE_URL . '/admin/dashboard.php');
        exit();

    } else { // Outros utilizadores
        $_SESSION['account_id'] = $user['id'];
        $_SESSION['user_full_name'] = $user['full_name'];
        $_SESSION['user_role_id'] = $user['role_id'];
        header('Location: ' . SITE_URL . '/dashboard.php');
        exit();
    }

} catch (Exception $e) {
    log_system_error("Erro de base de dados no login: " . $e->getMessage(), 'critical', 'login_db_exception');
    redirectToLogin(['general' => getTranslation('server_error_try_again', $languageCode, 'ui_messages')]);
}