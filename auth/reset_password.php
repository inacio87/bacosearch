<?php
/**
 * /auth/reset_password.php - PÁGINA FINAL E TOTALMENTE COMPATÍVEL
 */

require_once dirname(__DIR__, 1) . '/core/bootstrap.php';

$languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : (isset(LANGUAGE_CONFIG['default']) ? LANGUAGE_CONFIG['default'] : 'pt-br');
$token = isset($_GET['token']) ? $_GET['token'] : null;
$errors = [];
$valid_token = false;
$user_id = null;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_specific_styles = [SITE_URL . '/assets/css/pages.css'];

$translations = [];
$keys_to_translate = [
    'reset_password_title', 'reset_password_subtitle', 'label_new_password', 'label_confirm_password', 'button_reset_password', 'token_invalid_error',
    'passwords_do_not_match_error', 'password_reset_success', 'back_to_login_link',
    'logo_alt', 'header_login', 'footer_providers', 'footer_companies', 'footer_clubs', 'footer_streets', 'footer_services'
];
foreach ($keys_to_translate as $key) {
    $context = 'reset_password_page';
    if ($key === 'logo_alt' || $key === 'header_login') { $context = 'header'; }
    if (strpos($key, 'footer_') === 0) { $context = 'footer'; }
    if ($key === 'passwords_do_not_match_error') { $context = 'validation_errors'; }
    if ($key === 'password_reset_success') { $context = 'ui_messages'; }
    $translations[$key] = getTranslation($key, $languageCode, $context);
}

$page_title = !empty($translations['reset_password_title']) ? $translations['reset_password_title'] : 'reset_password_title';
$city = isset($_SESSION['city']) ? $_SESSION['city'] : getTranslation('detecting_location', $languageCode, 'ui_messages');
$langNameMap = isset(LANGUAGE_CONFIG['name_map']) ? LANGUAGE_CONFIG['name_map'] : [];
$currentLangName = isset($langNameMap[$languageCode]) ? $langNameMap[$languageCode] : getTranslation('language_label', $languageCode, 'default');
$translations['languageOptionsForDisplay'] = $langNameMap;
$translations['current_language_display_name'] = $currentLangName;

if (!$token) {
    $errors['general'] = $translations['token_invalid_error'];
} else {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT id FROM accounts WHERE password_reset_token = :token AND password_reset_expires_at > NOW()");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $valid_token = true;
            $user_id = $user['id'];
        } else {
            $errors['general'] = $translations['token_invalid_error'];
        }
    } catch (Exception $e) {
        $errors['general'] = getTranslation('server_error_try_again', $languageCode, 'ui_messages');
        log_system_error("Erro ao validar token de reset: " . $e->getMessage(), 'critical', 'reset_password_token_validation');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $csrf_session = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_session, $_POST['csrf_token'])) {
        $errors['general'] = getTranslation('csrf_token_invalid_error', $languageCode, 'validation_errors');
    } else {
        $new_password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        if (empty($new_password) || $new_password !== $confirm_password) {
            $errors['general'] = $translations['passwords_do_not_match_error'];
        } else {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $update_stmt = $db->prepare("UPDATE accounts SET password_hash = :hash, password_reset_token = NULL, password_reset_expires_at = NULL WHERE id = :id");
            $update_stmt->execute([':hash' => $new_password_hash, ':id' => $user_id]);
            
            $_SESSION['success_message_login'] = $translations['password_reset_success'];
            header('Location: ' . SITE_URL . '/auth/login.php');
            exit();
        }
    }
}

require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';
$e = function($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>

<main>
    <div class="login-container">
        <div class="login-header">
            <h1><?= $e($page_title); ?></h1>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="error-summary">
                <i class="fas fa-exclamation-circle"></i>
                <p><?= $e($errors['general']); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($valid_token): ?>
            <p><?= $e(isset($translations['reset_password_subtitle']) ? $translations['reset_password_subtitle'] : ''); ?></p>
            <form method="post" action="" class="login-form">
                <input type="hidden" name="token" value="<?= $e($token); ?>">
                <input type="hidden" name="csrf_token" value="<?= $e(isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''); ?>">
                
                <div class="form-group">
                    <label for="password"><?= $e(isset($translations['label_new_password']) ? $translations['label_new_password'] : ''); ?></label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password"><?= $e(isset($translations['label_confirm_password']) ? $translations['label_confirm_password'] : ''); ?></label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="form-actions" style="justify-content: center;">
                    <button type="submit" class="btn-primary"><?= $e(isset($translations['button_reset_password']) ? $translations['button_reset_password'] : ''); ?></button>
                </div>
            </form>
        <?php else: ?>
            <div style="text-align: center; margin-top: 20px;">
                <a href="<?= $e(SITE_URL . '/auth/login.php'); ?>" class="forgot-password-link"><?= $e(isset($translations['back_to_login_link']) ? $translations['back_to_login_link'] : ''); ?></a>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once TEMPLATE_PATH . 'footer.php'; ?>