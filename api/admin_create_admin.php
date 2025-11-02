<?php
/**
 * /api/admin_create_admin.php
 * API para Criação de Novos Administradores
 * - Requer que o utilizador seja um admin autenticado (e preferencialmente 'superadmin').
 * - Cria um novo registro na tabela `accounts` com role_id=5 (Admin).
 * - Cria um registro correspondente na tabela `admins` com o role_level.
 * - Envia um e-mail com as credenciais de login para o novo admin.
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
    echo json_encode(['success' => false, 'message' => getTranslation('not_authorized', $_SESSION['language'] ?? LANGUAGE_CONFIG['default'], 'admin_users')]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

// Coleta e validação dos inputs
$languageCode = $_SESSION['language'] ?? LANGUAGE_CONFIG['default'] ?? 'pt-br';

$full_name = filter_var($input['full_name'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
$password = $input['password'] ?? ''; // Senha bruta, não sanitizada para preservar caracteres especiais necessários
$role_level = filter_var($input['role_level'] ?? '', FILTER_SANITIZE_STRING); // 'superadmin' ou outro

// Validação de campos obrigatórios
if (empty($full_name) || empty($email) || empty($password) || empty($role_level)) {
    echo json_encode(['success' => false, 'message' => getTranslation('error_validation', $languageCode, 'admin_users')]);
    exit();
}

// Validação de formato de e-mail
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => getTranslation('invalid_email_format', $languageCode, 'admin_users')]);
    exit();
}

// Validação de força da senha (exemplo: mínimo 8 caracteres)
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => getTranslation('password_too_short', $languageCode, 'admin_users')]);
    exit();
}
// Considerar adicionar mais regras de complexidade de senha aqui (letras, números, símbolos)

$db = getDBConnection();

try {
    $db->beginTransaction();

    // 1. Verificar se o email já existe na tabela accounts
    $stmt_check_email = $db->prepare("SELECT id FROM accounts WHERE email = :email");
    $stmt_check_email->execute([':email' => $email]);
    if ($stmt_check_email->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception(getTranslation('error_email_exists', $languageCode, 'admin_users'));
    }

    // Opcional: Verificação de `role_level` do admin logado (apenas superadmin pode criar novos)
    // Se quiser implementar isso, descomente o bloco abaixo e a tradução 'permission_denied'.
    /*
    $currentAdminAccountId = $_SESSION['admin_id'];
    $stmt_check_superadmin = $db->prepare("SELECT role_level FROM admins WHERE account_id = :account_id");
    $stmt_check_superadmin->execute([':account_id' => $currentAdminAccountId]);
    $admin_details = $stmt_check_superadmin->fetch(PDO::FETCH_ASSOC);

    if (!$admin_details || $admin_details['role_level'] !== 'superadmin') {
        http_response_code(403);
        throw new Exception(getTranslation('permission_denied', $languageCode, 'admin_users'));
    }
    */

    // 2. Hash da senha
    $password_hash = password_hash($password, PASSWORD_BCRYPT); // Custo padrão 10 (como visto nos seus dados)

    // 3. Obter o ID da role 'admin' da tabela access_roles
    $stmt_get_admin_role_id = $db->prepare("SELECT id FROM access_roles WHERE slug = 'admin' AND is_active = 1");
    $stmt_get_admin_role_id->execute();
    $admin_role = $stmt_get_admin_role_id->fetch(PDO::FETCH_ASSOC);

    if (!$admin_role) {
        throw new Exception(getTranslation('admin_role_not_found', $languageCode, 'admin_users'));
    }
    $admin_role_id = $admin_role['id'];

    // 4. Inserir na tabela `accounts`
    $stmt_insert_account = $db->prepare("
        INSERT INTO accounts (email, full_name, role_id, password_hash, role, status, created_at, updated_at)
        VALUES (:email, :full_name, :role_id, :password_hash, :role_slug, :status, NOW(), NOW())
    ");

    $stmt_insert_account->execute([
        ':email' => $email,
        ':full_name' => $full_name,
        ':role_id' => $admin_role_id,
        ':password_hash' => $password_hash,
        ':role_slug' => 'admin', // Hardcoded 'admin' como visto na sua tabela accounts
        ':status' => 'active' // Admins criados pelo dashboard já começam ativos
    ]);

    $new_account_id = $db->lastInsertId();
    if (!$new_account_id) {
        throw new Exception("Falha ao obter o ID da nova conta criada.");
    }

    // 5. Inserir na tabela `admins` (para vincular o role_level)
    $stmt_insert_admin = $db->prepare("
        INSERT INTO admins (account_id, name, role_level, created_at, updated_at)
        VALUES (:account_id, :name, :role_level, NOW(), NOW())
    ");

    $stmt_insert_admin->execute([
        ':account_id' => $new_account_id,
        ':name' => $full_name, // Nome do admin (pode ser o mesmo do full_name da conta)
        ':role_level' => $role_level
    ]);

    // Commit da transação após todas as inserções bem-sucedidas
    $db->commit();

    // 6. Enviar e-mail com as credenciais para o novo administrador
    $emailSubject = getTranslation('email_subject_new_admin', $languageCode, 'admin_users');
    $emailTemplatePath = 'templates/emails/new_admin_credentials.html'; // Novo template

    $emailTemplateData = [
        'email_title_fallback' => $emailSubject,
        'greeting' => getTranslation('greeting', $languageCode, 'email_templates'),
        'user_name' => htmlspecialchars($full_name),
        'welcome_message_admin' => getTranslation('welcome_message_admin', $languageCode, 'admin_users'),
        'login_details_message' => getTranslation('login_details_message', $languageCode, 'admin_users'),
        'admin_email' => htmlspecialchars($email), // Email sem sanitização se for usado no mailto:
        'admin_password' => htmlspecialchars($password), // Atenção: A senha em texto puro é enviada AQUI!
        'admin_dashboard_link' => SITE_URL . '/admin/dashboard.php',
        'button_text_login_dashboard' => getTranslation('button_text_login_dashboard', $languageCode, 'admin_users'),
        'current_year' => date('Y'),
        'site_name' => SITE_NAME,
        'all_rights_reserved' => getTranslation('all_rights_reserved', $languageCode, 'email_templates'),
        'spam_notice' => getTranslation('spam_notice', $languageCode, 'email_templates'),
        'email_sent_to_label' => getTranslation('email_sent_to_label', $languageCode, 'email_templates'),
        'logo_alt_text' => getTranslation('logo_alt', $languageCode, 'header'), // Reutiliza a tradução do logo
        'mail_css_url' => SITE_URL . '/assets/css/mail.css' // Caminho para o CSS do e-mail
    ];

    $email_sent = send_email(
        $email,
        $emailSubject,
        $emailTemplatePath,
        $emailTemplateData,
        'new_admin_credentials',
        $new_account_id, // account_id do novo admin
        null, // visitor_id (pode ser null para emails de sistema)
        null  // event_id (pode ser null)
    );

    if (!$email_sent) {
        log_system_error("Falha ao enviar e-mail de credenciais para o novo admin: {$email}", 'ERROR', 'admin_creation_email_failure');
        // Não lançamos uma exceção fatal aqui para não reverter a criação do usuário, mas avisamos que o e-mail falhou.
        echo json_encode(['success' => true, 'message' => getTranslation('success_message_create_admin_no_email', $languageCode, 'admin_users')]);
    } else {
        echo json_encode(['success' => true, 'message' => getTranslation('success_message_create_admin', $languageCode, 'admin_users')]);
    }

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    log_system_error("Admin Creation Error: " . $e->getMessage() . " - Input: " . json_encode($input), 'ERROR', 'admin_create_admin_exception');
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>