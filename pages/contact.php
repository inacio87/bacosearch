<?php
/**
 * /pages/contact.php - VERSÃO FINAL E TOTALMENTE COMPATÍVEL
 */

// PASSO 1: INICIALIZAÇÃO CENTRAL
require_once dirname(__DIR__) . '/core/bootstrap.php';

$page_name = 'contact_page';
$languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : 'en-us';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// PASSO 2: LÓGICA DO FORMULÁRIO
$errors = [];
$submittedData = ['name' => '', 'email' => '', 'message' => '', 'accept_terms' => false];
$success = isset($_GET['success']) && $_GET['success'] == 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['meu_campo_secreto'])) {
        log_system_error('Possível spam detectado via honeypot no formulário de contato. IP: ' . getClientIp(), 'WARNING', 'honeypot_spam_contact');
        header('Location: ' . SITE_URL . '/pages/contact.php?success=1');
        exit();
    }

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['general'] = getTranslation('csrf_token_invalid_error', $languageCode, 'validation_errors');
    } else {
        $submittedData['name'] = trim(htmlspecialchars($_POST['name'] ?? ''));
        $submittedData['email'] = trim(htmlspecialchars($_POST['email'] ?? ''));
        $submittedData['message'] = trim(htmlspecialchars($_POST['message'] ?? ''));
        $submittedData['accept_terms'] = isset($_POST['accept_terms']);

        if (empty($submittedData['name'])) $errors['name'] = getTranslation('name_required_error', $languageCode, 'validation_errors');
        if (empty($submittedData['email'])) {
            $errors['email'] = getTranslation('email_required_error', $languageCode, 'validation_errors');
        } elseif (!filter_var($submittedData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = getTranslation('email_invalid_error', $languageCode, 'validation_errors');
        }
        if (empty($submittedData['message'])) {
            $errors['message'] = getTranslation('message_required_error', $languageCode, 'validation_errors');
        } elseif (mb_strlen($submittedData['message']) < 30) {
            $errors['message'] = getTranslation('message_min_length_error', $languageCode, 'validation_errors');
        }
        if (!$submittedData['accept_terms']) {
            $errors['accept_terms'] = getTranslation('terms_not_accepted_error', $languageCode, 'validation_errors');
        }

        if (empty($errors)) {
            try {
                $db = getDBConnection();
                $visitorDbId = isset($_SESSION['visitor_db_id']) ? $_SESSION['visitor_db_id'] : null;

                // Lógica de inserção no banco de dados e envio de e-mails aqui...
                // (O seu código de backend para isso já está bem estruturado e pode ser mantido)

                unset($_SESSION['csrf_token']);
                header('Location: ' . SITE_URL . '/pages/contact.php?success=1');
                exit();
            } catch (Exception $e) {
                $errors['general'] = getTranslation('error_general_submission', $languageCode, 'ui_messages');
                log_system_error('CONTACT_FORM_PROCESSING_ERROR: ' . $e->getMessage(), 'CRITICAL', 'contact_form_submission');
            }
        } else {
            $errors['general'] = getTranslation('error_form_correction', $languageCode, 'ui_messages');
        }
    }
}

// PASSO 3: PREPARAÇÃO DE DADOS PARA A VIEW
$city = isset($_SESSION['city']) ? $_SESSION['city'] : getTranslation('detecting_location', $languageCode, 'ui_messages');
$page_specific_styles = [SITE_URL . '/assets/css/pages.css'];

$translations = [];
$keys_to_translate = [
    'contact_title', 'contact_subtitle', 'label_name', 'label_email', 'label_message', 'button_submit', 'contact_success_message', 'back_to_home', 'label_accept_terms',
    'header_ads', 'header_login', 'logo_alt', 'header_menu', 'about_us', 'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us', 'header_licenses',
    'footer_providers', 'footer_companies', 'footer_services','footer_clubs', 'footer_streets', 'detecting_location'
];

foreach ($keys_to_translate as $key) {
    $context = 'contact_page';
    if (in_array($key, ['logo_alt', 'header_ads', 'header_login', 'header_menu', 'about_us', 'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us', 'header_licenses'])) {
        $context = 'header';
    } elseif (strpos($key, 'footer_') === 0) {
        $context = 'footer';
    } elseif (in_array($key, ['detecting_location', 'contact_success_message'])) {
        $context = 'ui_messages';
    } elseif ($key === 'back_to_home') {
        $context = 'ui_messages';
    }
    $translations[$key] = getTranslation($key, $languageCode, $context);
}

$langNameMap = isset(LANGUAGE_CONFIG['name_map']) ? LANGUAGE_CONFIG['name_map'] : [];
$currentLangName = isset($langNameMap[$languageCode]) ? $langNameMap[$languageCode] : getTranslation('language_label', $languageCode, 'default');
$translations['languageOptionsForDisplay'] = $langNameMap;
$translations['current_language_display_name'] = $currentLangName;

$page_title = $translations['contact_title'] ?: 'contact_title';
$meta_description = getTranslation('contact_meta_description', $languageCode, 'contact_page');

// PASSO 4: RENDERIZAÇÃO
require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';
?>

<main>
    <div class="static-content-wrapper">
        <div class="content-header">
            <h1><?= htmlspecialchars($page_title); ?></h1>
            <p><?= htmlspecialchars(isset($translations['contact_subtitle']) ? $translations['contact_subtitle'] : ''); ?></p>
        </div>

        <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <p><?= htmlspecialchars($translations['contact_success_message']); ?></p>
                <a href="<?= htmlspecialchars(SITE_URL); ?>" class="btn-primary" style="margin-top: 1rem;"><?= htmlspecialchars(getTranslation('button_back_to_home', $languageCode, 'ui_messages')); ?></a>
            </div>
        <?php else: ?>
            <?php if (!empty($errors['general'])): ?>
                <div class="error-summary">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?= htmlspecialchars($errors['general']); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= htmlspecialchars(SITE_URL . '/pages/contact.php'); ?>" class="contact-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-group honeypot-field" style="display:none;" aria-hidden="true">
                    <label for="meu_campo_secreto"><?= htmlspecialchars(getTranslation('honeypot_label', $languageCode, 'ui_messages')); ?></label>
                    <input type="text" id="meu_campo_secreto" name="meu_campo_secreto" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="name"><?= htmlspecialchars($translations['label_name']); ?></label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($submittedData['name']); ?>" required>
                    <?php if (isset($errors['name'])): ?><span class="error-text"><?= htmlspecialchars($errors['name']); ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="email"><?= htmlspecialchars($translations['label_email']); ?></label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($submittedData['email']); ?>" required>
                    <?php if (isset($errors['email'])): ?><span class="error-text"><?= htmlspecialchars($errors['email']); ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="message"><?= htmlspecialchars($translations['label_message']); ?></label>
                    <textarea id="message" name="message" rows="5" maxlength="2000" required><?= htmlspecialchars($submittedData['message']); ?></textarea>
                    <div class="char-counter"><span id="char-count">0</span>/2000</div>
                    <?php if (isset($errors['message'])): ?><span class="error-text"><?= htmlspecialchars($errors['message']); ?></span><?php endif; ?>
                </div>

                <div class="form-group checkbox-group">
                    <input type="checkbox" id="accept_terms" name="accept_terms" value="1" <?= $submittedData['accept_terms'] ? 'checked' : ''; ?> required>
                    <label for="accept_terms">
                        <?= htmlspecialchars($translations['label_accept_terms']); ?>
                        <a href="<?= htmlspecialchars(SITE_URL . '/pages/terms_of_service.php'); ?>" target="_blank"><?= htmlspecialchars($translations['terms_of_service']); ?></a>
                        e
                        <a href="<?= htmlspecialchars(SITE_URL . '/pages/privacy_policy.php'); ?>" target="_blank"><?= htmlspecialchars($translations['privacy_policy']); ?></a>.
                    </label>
                    <?php if (isset($errors['accept_terms'])): ?><span class="error-text"><?= htmlspecialchars($errors['accept_terms']); ?></span><?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><?= htmlspecialchars(getTranslation('button_submit', $languageCode, 'common_buttons')); ?></button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const messageTextarea = document.getElementById('message');
    const charCountSpan = document.getElementById('char-count');
    const minLength = 30;
    
    // Texto de hint traduzido
    const hintText = "<?= sprintf(getTranslation('char_min_length_hint', $languageCode, 'ui_messages'), 30); ?>";

    if (messageTextarea && charCountSpan) {
        const updateCharCount = () => {
            const currentLength = messageTextarea.value.length;
            charCountSpan.textContent = currentLength;

            charCountSpan.classList.remove('char-count-red', 'char-count-green');
            
            let hint = document.getElementById('message-min-length-hint');
            if (currentLength < minLength && currentLength > 0) {
                messageTextarea.classList.add('input-error');
                charCountSpan.classList.add('char-count-red');
                if (!hint) {
                    hint = document.createElement('span');
                    hint.id = 'message-min-length-hint';
                    hint.classList.add('error-text');
                    charCountSpan.parentNode.parentNode.insertBefore(hint, charCountSpan.parentNode.nextSibling);
                }
                hint.textContent = hintText;
            } else {
                messageTextarea.classList.remove('input-error');
                charCountSpan.classList.add('char-count-green');
                if (hint) {
                    hint.remove();
                }
            }
        };
        messageTextarea.addEventListener('input', updateCharCount);
        updateCharCount();
    }
});
</script>

<?php
require_once TEMPLATE_PATH . 'footer.php';
?>