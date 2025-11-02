<?php
/**
 * /auth/login.php - Página de Login do BacoSearch (Versão Final Corrigida)
 *
 * RESPONSABILIDADES:
 * 1. Exibir formulário de login com seleção de nível de acesso.
 * 2. Carregar o sistema de bootstrap central.
 * 3. Redirecionar corretamente utilizadores e admins já logados.
 * 4. Ligar ao sistema de recuperação de senha.
 */

// PASSO 1: INICIALIZAÇÃO CENTRAL
require_once dirname(__DIR__, 1) . '/core/bootstrap.php'; 

// PASSO 2: VERIFICAÇÃO DE SESSÃO E REDIRECIONAMENTO
// Redireciona utilizadores ou administradores que já tenham uma sessão ativa.
if (isset($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . '/admin/dashboard.php');
    exit();
}
if (isset($_SESSION['account_id'])) {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit();
}

// ===================================================================================
// PASSO 3: PREPARAÇÃO DE DADOS PARA A VIEW (LÓGICA CORRIGIDA)
// ===================================================================================

// **CORREÇÃO: Definir variáveis de sessão e de idioma PRIMEIRO.**
$languageCode = $_SESSION['language'] ?? LANGUAGE_CONFIG['default'] ?? 'pt-br';
$city = $_SESSION['city'] ?? null; // A tradução será pega abaixo
$errors = $_SESSION['errors_login'] ?? [];
$form_data = $_SESSION['form_data_login'] ?? [];
$success_message = $_SESSION['success_message_login'] ?? null;
unset($_SESSION['errors_login'], $_SESSION['form_data_login'], $_SESSION['success_message_login']);

// Geração de Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Buscar os Níveis de Acesso já com o $languageCode definido
try {
    $db = getDBConnection();
    $stmt = $db->prepare(
        "SELECT 
            ar.id, 
            COALESCE(t.translation_value, ar.name) AS name 
         FROM access_roles ar
         LEFT JOIN translations t ON ar.slug = t.translation_key AND t.context = 'access_roles' AND t.language_code = :lang
         WHERE ar.is_active = 1 
         ORDER BY ar.id ASC"
    );
    $stmt->execute([':lang' => $languageCode]);
    $access_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    log_system_error("Falha ao buscar access_roles para a página de login: " . $e->getMessage(), 'critical', 'login_page_db_error');
    $access_roles = []; 
}

// Preparar todas as traduções necessárias
$translations = [];
$keys_to_translate = [
    'login_title', 'login_subtitle', 'label_email', 'label_password', 'button_login',
    'label_access_level', 'forgot_password_link', 'no_account_text', 'register_now_link', 'logo_alt',
    'header_ads', 'header_login', 'header_menu', 'about_us', 'terms_of_service',
    'privacy_policy', 'cookie_policy', 'contact_us', 'footer_providers',
    'footer_companies', 'footer_services', 'detecting_location', 'header_licenses','footer_clubs', 'footer_streets',
    'login_meta_description'
];
foreach ($keys_to_translate as $key) {
    $context = 'login_page';
    if (in_array($key, ['logo_alt', 'header_ads', 'header_login', 'header_menu', 'about_us', 'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us', 'detecting_location', 'header_licenses'])) {
        $context = 'header';
    } elseif (str_starts_with($key, 'footer_')) {
        $context = 'footer';
    }
    $translations[$key] = getTranslation($key, $languageCode, $context);
}

if (!$city) {
    $city = $translations['detecting_location'] ?? 'Detectando...';
}

// Preparar dados para o seletor de idioma e meta tags
$translations['languageOptionsForDisplay'] = LANGUAGE_CONFIG['name_map'] ?? [];
$translations['current_language_display_name'] = $translations['languageOptionsForDisplay'][$languageCode] ?? 'Idioma';
$page_title = $translations['login_title'] ?: 'Faça Login';
$meta_description = $translations['login_meta_description'] ?? 'Faça login na sua conta.';

// Definir o CSS da página
$page_specific_styles = [
    SITE_URL . '/assets/css/pages.css'
];


// ===================================================================================
// PASSO 4: RENDERIZAÇÃO DA PÁGINA
// ===================================================================================
require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';
?>

<main>
    <div class="login-container">
        <div class="login-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p><?php echo htmlspecialchars($translations['login_subtitle'] ?? ''); ?></p>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="error-summary"><p><?php echo htmlspecialchars($errors['general']); ?></p></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="success-summary"><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo htmlspecialchars(SITE_URL . '/auth/process_login.php'); ?>" class="login-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            
            <div class="form-group">
                <label for="email"><?php echo htmlspecialchars($translations['label_email']); ?></label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                <?php if (isset($errors['email'])): ?><span class="error-text"><?php echo htmlspecialchars($errors['email']); ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="role_id"><?php echo htmlspecialchars($translations['label_access_level'] ?? 'Acesso'); ?></label>
                <select id="role_id" name="role_id" required>
                    <?php foreach ($access_roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>" <?php echo (isset($form_data['role_id']) && $form_data['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['role_id'])): ?><span class="error-text"><?php echo htmlspecialchars($errors['role_id']); ?></span><?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="password"><?php echo htmlspecialchars($translations['label_password']); ?></label>
                <input type="password" id="password" name="password" required>
                <?php if (isset($errors['password'])): ?><span class="error-text"><?php echo htmlspecialchars($errors['password']); ?></span><?php endif; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary"><?php echo htmlspecialchars($translations['button_login']); ?></button>
                <a href="<?php echo htmlspecialchars(SITE_URL . '/auth/forgot_password.php'); ?>" class="forgot-password-link"><?php echo htmlspecialchars($translations['forgot_password_link'] ?? 'Esqueceu a senha?'); ?></a>
            </div>

            <p class="register-cta">
                <?php echo htmlspecialchars($translations['no_account_text'] ?? 'Não tem uma conta?'); ?>
                <a href="<?php echo htmlspecialchars(SITE_URL . '/register.php'); ?>"><?php echo htmlspecialchars($translations['register_now_link'] ?? 'Cadastre-se agora!'); ?></a>
            </p>
        </form>
    </div>
</main>

<?php
require_once TEMPLATE_PATH . 'footer.php';
ob_end_flush();
?>