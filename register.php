<?php
/**
 * /register.php - PONTO DE ENTRADA PARA CADASTRO DE ANUNCIANTES (FASE 1)
 * Produção: i18n, segurança, CSP nonce, A11y e correções de fluxo (flash + GET status)
 * ÚLTIMA ATUALIZAÇÃO: 15/08/2025
 */

/* PASSO 1: INICIALIZAÇÃO CENTRAL */
require_once __DIR__ . '/core/bootstrap.php';

/* Headers de cache */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

/* Helpers */
$e = static function (?string $v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};
$csp_nonce = $_SESSION['csp_nonce'] ?? null;

/* PASSO 2: PREPARAÇÃO DE DADOS ESPECÍFICOS DA PÁGINA */
$languageCode = $_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us');

$page_specific_styles = [
  SITE_URL . '/assets/css/provider-form.css'
];

/* Carrega a lista completa de códigos de telefone para o dropdown */
$phoneCodes = [];
try {
  $db_conn_phone = getDBConnection();
  $stmt_phone = $db_conn_phone->query("SELECT c.name, c.calling_code, c.iso_code FROM countries c WHERE c.calling_code IS NOT NULL AND c.calling_code != '' ORDER BY c.name ASC");
  $raw_phone_codes = $stmt_phone->fetchAll(PDO::FETCH_ASSOC);
  foreach ($raw_phone_codes as $code_data) {
    $iso = strtolower((string)$code_data['iso_code']);
    $phoneCodes[] = [
      'name'         => (string)$code_data['name'],
      'calling_code' => (string)$code_data['calling_code'],
      'iso_code'     => $iso,
      'flag_url'     => SITE_URL . '/assets/images/flags/' . $iso . '.png'
    ];
  }
} catch (Throwable $e_db) {
  log_system_error("Register Page: Erro ao carregar códigos de telefone: " . $e_db->getMessage(), 'error', 'register_load_phone_codes');
}

/* Carrega a lista de nacionalidades para o dropdown */
$nationalities = [];
try {
  $db_conn_nat = getDBConnection();
  $stmt_nat = $db_conn_nat->query("SELECT id, name AS country, iso_code FROM countries ORDER BY name ASC");
  $countries_for_select = $stmt_nat->fetchAll(PDO::FETCH_ASSOC);
  foreach ($countries_for_select as $country_data) {
    $iso = strtolower((string)$country_data['iso_code']);
    $nationalities[] = [
      'id'       => (int)$country_data['id'],
      'country'  => (string)$country_data['country'],
      'iso_code' => $iso,
      'flag_url' => SITE_URL . '/assets/images/flags/' . $iso . '.png'
    ];
  }
} catch (Throwable $e_db) {
  log_system_error("Register Page: Erro ao carregar nacionalidades: " . $e_db->getMessage(), 'error', 'register_load_nationalities');
}

/* Gera o token de segurança para o formulário */
if (empty($_SESSION['csrf_token'])) {
  try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
  catch (Throwable $e_csrf) { $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32)); }
}

/* Recupera erros/dados de submissão anterior (flash) */
$errors     = $_SESSION['errors_register'] ?? [];
$form_data  = $_SESSION['form_data_register'] ?? [];
$flash_general_error = $errors['general'] ?? null; // usado em alguns status

/* Mensagens (sucesso/erro/info) via modal */
$show_message    = false;
$message_content = '';
$message_type    = 'success';
$success_email   = '';
$status          = null;

/* Sanitiza e valida status vindo por GET */
if (isset($_GET['status'])) {
  $allowed = [
    'success_verification_sent','password_set_success','info',
    'resend_success','resend_error','verification_error','registration_success'
  ];
  $status = in_array($_GET['status'], $allowed, true) ? $_GET['status'] : null;

  if ($status === 'success_verification_sent' && isset($_SESSION['registration_success_message'])) {
    $show_message    = true;
    $message_type    = 'success';
    $message_content = getTranslation('registration_email_verification_sent', $languageCode, 'ui_messages');
    $success_email   = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
    unset($_SESSION['registration_success_message']);
  } elseif ($status === 'password_set_success' && isset($_SESSION['registration_success_message'])) {
    $show_message    = true;
    $message_type    = 'success';
    $message_content = getTranslation('password_set_pending_admin_approval', $languageCode, 'ui_messages');
    unset($_SESSION['registration_success_message']);
  } elseif ($status === 'info' && isset($_SESSION['registration_info_message'])) {
    $show_message    = true;
    $message_type    = 'info';
    $message_content = (string)$_SESSION['registration_info_message'];
    unset($_SESSION['registration_info_message']);
  } elseif ($status === 'resend_success' && isset($_SESSION['registration_success_message'])) {
    $show_message    = true;
    $message_type    = 'success';
    $message_content = (string)$_SESSION['registration_success_message'];
    unset($_SESSION['registration_success_message']);
  } elseif ($status === 'resend_error' && $flash_general_error) {
    $show_message    = true;
    $message_type    = 'error';
    $message_content = (string)$flash_general_error;
  } elseif ($status === 'verification_error') {
    $show_message    = true;
    $message_type    = 'error';
    $message_content = getTranslation('invalid_link_or_token', $languageCode, 'ui_messages');
  } elseif ($status === 'registration_success' && (int)($_GET['email_sent_bot'] ?? 0) === 1) {
    $show_message    = true;
    $message_type    = 'success';
    $message_content = getTranslation('registration_email_verification_sent', $languageCode, 'ui_messages');
  }
}

/* Agora limpamos os flashes de formulário */
unset($_SESSION['errors_register'], $_SESSION['form_data_register']);

/* Traduções necessárias */
$translations = [];
$keys_to_translate = [
  'register_title','register_meta_description','select_option','label_full_name','label_birth_date','label_email','label_phone',
  'label_nationality','label_account_type','account_type_provider','account_type_services','account_type_companies','button_proceed',
  'label_privacy_consent_full','label_password','label_repeat_password','form_errors_title','honeypot_label','password_rules_text',
  'registration_email_verification_sent','password_set_pending_admin_approval','email_already_registered_login_prompt',
  'email_already_registered_pending_email_verification','email_already_registered_general_info','check_inbox_spam_notice',
  'resend_success','resend_error','resend_email_sent_confirmation','account_already_verified_or_approved',
  'account_status_prevents_resend','error_updating_verification_token','error_resend_email_failed','error_general_resend',
  'invalid_link_or_token','registration_almost_there_title','button_back_to_home','email_sent_to_label','error_form_correction',
  'error_registration_failed','full_name_error','birth_date_error','email_error','phone_error','account_type_error',
  'nationality_required_error','privacy_consent_required_error','password_error','repeat_password_error','password_complexity_error',
  'password_too_short','passwords_do_not_match','email_already_registered_error',
];

/* Mapeia contextos (compatível PHP 7) */
foreach ($keys_to_translate as $key) {
  $context = 'register_page';
  if (substr($key, -6) === '_error' || in_array($key, [
    'full_name_error','birth_date_error','email_error','phone_error',
    'account_type_error','nationality_required_error','privacy_consent_required_error',
    'password_error','repeat_password_error','password_complexity_error',
    'password_too_short','passwords_do_not_match','email_already_registered_error'
  ], true)) {
    $context = 'validation_errors';
  } elseif (in_array($key, [
    'form_errors_title','honeypot_label','password_rules_text',
    'registration_email_verification_sent','password_set_pending_admin_approval',
    'email_already_registered_login_prompt','email_already_registered_pending_email_verification',
    'email_already_registered_general_info','check_inbox_spam_notice',
    'resend_success','resend_error','resend_email_sent_confirmation',
    'account_already_verified_or_approved','account_status_prevents_resend',
    'error_updating_verification_token','error_resend_email_failed','error_general_resend',
    'invalid_link_or_token','registration_almost_there_title',
    'button_back_to_home','email_sent_to_label','error_form_correction','error_registration_failed'
  ], true)) {
    $context = 'ui_messages';
  }
  $translations[$key] = getTranslation($key, $languageCode, $context);
}

/* Header/meta */
$city = $_SESSION['city'] ?? getTranslation('detecting_location', $languageCode, 'ui_messages');
$translations['languageOptionsForDisplay'] = LANGUAGE_CONFIG['name_map'] ?? [];
$translations['current_language_display_name'] = $translations['languageOptionsForDisplay'][$languageCode] ?? strtoupper($languageCode);

$page_title       = $translations['register_title'] ?? 'register_title';
$meta_description = $translations['register_meta_description'] ?? (SEO_CONFIG['meta_description'] ?? 'register_meta_description');

/* PASSO 3: RENDERIZAÇÃO DA PÁGINA */
require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';
?>
<main>
  <div class="main-container">
    <div class="register-container">
      <div class="register-header">
        <h1><?= $e($page_title); ?></h1>
      </div>

      <?php if (!empty($errors) && !$show_message): ?>
        <div class="error-message show" role="alert" aria-live="polite">
          <strong><?= $e($translations['form_errors_title'] ?? 'form_errors_title'); ?></strong>
          <ul>
            <?php foreach ($errors as $key => $error_msg): if ($key === 'general') continue; ?>
              <li><?= $e((string)$error_msg); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($show_message): ?>
        <div id="registrationMessageModal" class="modal-overlay show" role="dialog" aria-modal="true" aria-labelledby="reg-modal-title">
          <div class="modal-content">
            <?php if ($message_type === 'success' || $message_type === 'info'): ?>
              <i class="fas fa-check-circle icon-success" aria-hidden="true"></i>
              <h2 id="reg-modal-title"><?= $e($translations['registration_almost_there_title'] ?? 'registration_almost_there_title'); ?></h2>
              <p><?= $e($message_content); ?></p>
              <?php if ($message_type === 'success' && !empty($success_email)): ?>
                <p class="small-text"><?= $e($translations['email_sent_to_label'] ?? 'email_sent_to_label'); ?>: <?= $e($success_email); ?></p>
              <?php endif; ?>
              <?php if ($message_type === 'success' && ($status === 'success_verification_sent' || $status === 'resend_success')): ?>
                <p class="small-text"><?= $e($translations['check_inbox_spam_notice'] ?? 'check_inbox_spam_notice'); ?></p>
              <?php endif; ?>
            <?php else: ?>
              <i class="fas fa-exclamation-circle icon-error" aria-hidden="true"></i>
              <h2 id="reg-modal-title"><?= $e($translations['form_errors_title'] ?? 'form_errors_title'); ?></h2>
              <p><?= $e($message_content); ?></p>
            <?php endif; ?>
            <div class="modal-actions">
              <button type="button" class="btn-primary" id="modalCloseButton"><?= $e($translations['button_back_to_home'] ?? 'button_back_to_home'); ?></button>
            </div>
          </div>
        </div>
      <?php else: ?>
        <form method="post" action="<?= $e(SITE_URL . '/api/api_register.php'); ?>" class="register-form" id="register-form" novalidate>
          <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token'] ?? ''); ?>">

          <div class="form-group honeypot-field" style="display:none" aria-hidden="true">
            <label for="website_url"><?= $e($translations['honeypot_label'] ?? 'honeypot_label'); ?></label>
            <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
          </div>

          <div class="form-section">
            <div class="form-group">
              <label for="real_name"><?= $e($translations['label_full_name'] ?? 'label_full_name'); ?></label>
              <input type="text" id="real_name" name="real_name" value="<?= $e($form_data['real_name'] ?? ''); ?>"
                     maxlength="150" required class="form-control" autocomplete="name">
            </div>

            <div class="form-group">
              <label for="birth_date"><?= $e($translations['label_birth_date'] ?? 'label_birth_date'); ?></label>
              <input type="date" id="birth_date" name="birth_date" value="<?= $e($form_data['birth_date'] ?? ''); ?>"
                     max="<?= $e(date('Y-m-d', strtotime('-18 years'))); ?>" required class="form-control" autocomplete="bday">
            </div>

            <div class="form-group">
              <label for="email"><?= $e($translations['label_email'] ?? 'label_email'); ?></label>
              <input type="email" id="email" name="email" value="<?= $e($form_data['email'] ?? ''); ?>"
                     required class="form-control" autocomplete="email" inputmode="email">
            </div>

            <div class="form-group <?= (isset($errors['password']) || isset($errors['password_too_short']) || isset($errors['password_complexity_error'])) ? 'has-error' : ''; ?>">
              <label for="password">
                <?= $e($translations['label_password'] ?? 'label_password'); ?>
                <?php if (isset($errors['password']) || isset($errors['password_too_short']) || isset($errors['password_complexity_error'])): ?>
                  <i class="fas fa-exclamation-triangle text-warning ml-2"
                     title="<?= $e($errors['password'] ?? $errors['password_too_short'] ?? $errors['password_complexity_error']); ?>" aria-hidden="true"></i>
                <?php endif; ?>
              </label>
              <input type="password" id="password" name="password" maxlength="100" required class="form-control" autocomplete="new-password">
              <small class="form-text text-muted">
                <?= $e($translations['password_rules_text'] ?? 'password_rules_text'); ?>
              </small>
            </div>

            <div class="form-group <?= (isset($errors['repeat_password']) || isset($errors['passwords_do_not_match'])) ? 'has-error' : ''; ?>">
              <label for="repeat_password">
                <?= $e($translations['label_repeat_password'] ?? 'label_repeat_password'); ?>
                <?php if (isset($errors['repeat_password']) || isset($errors['passwords_do_not_match'])): ?>
                  <i class="fas fa-exclamation-triangle text-warning ml-2"
                     title="<?= $e($errors['repeat_password'] ?? $errors['passwords_do_not_match']); ?>" aria-hidden="true"></i>
                <?php endif; ?>
              </label>
              <input type="password" id="repeat_password" name="repeat_password" maxlength="100" required class="form-control" autocomplete="new-password">
            </div>

            <div class="form-group">
              <label for="nationality_id"><?= $e($translations['label_nationality'] ?? 'label_nationality'); ?></label>
              <select id="nationality_id" name="nationality_id" required class="form-control">
                <option value=""><?= $e($translations['select_option'] ?? 'select_option'); ?></option>
                <?php
                  $selectedNationalityId = null;
                  if (!empty($form_data['nationality_id'])) {
                    $selectedNationalityId = (int)$form_data['nationality_id'];
                  } elseif (!empty($_SESSION['country_code'])) {
                    try {
                      $db_conn_nat_selected = getDBConnection();
                      $stmt_nat_selected = $db_conn_nat_selected->prepare("SELECT id FROM countries WHERE iso_code = :iso_code LIMIT 1");
                      $stmt_nat_selected->execute([':iso_code' => strtoupper($_SESSION['country_code'])]);
                      $result_id = $stmt_nat_selected->fetchColumn();
                      if ($result_id) { $selectedNationalityId = (int)$result_id; }
                    } catch (Throwable $e_sel) {
                      log_system_error("Register Page: Erro ao buscar ID da nacionalidade da sessão: " . $e_sel->getMessage(), 'notice', 'register_session_nationality_id');
                    }
                  }
                  foreach ($nationalities as $nationality):
                    $isSelected = ($selectedNationalityId === (int)$nationality['id']);
                ?>
                  <option value="<?= $e((string)$nationality['id']); ?>"
                          data-flag="<?= $e($nationality['flag_url']); ?>"
                          <?= $isSelected ? 'selected' : ''; ?>>
                    <?= $e((string)$nationality['country']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="phone_number"><?= $e($translations['label_phone'] ?? 'label_phone'); ?></label>
              <div class="phone-input-group">
                <select id="phone_code" name="phone_code" required class="form-control phone-code">
                  <?php
                    $selectedPhoneCodeValue = $form_data['phone_code'] ?? '+351';
                    foreach ($phoneCodes as $code):
                      $isSelected = ($selectedPhoneCodeValue === $code['calling_code']);
                  ?>
                    <option value="<?= $e($code['calling_code']); ?>"
                            data-flag="<?= $e($code['flag_url']); ?>"
                            <?= $isSelected ? 'selected' : ''; ?>>
                      <?= $e($code['calling_code']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <input type="tel" id="phone_number" name="phone_number"
                       value="<?= $e($form_data['phone_number'] ?? ''); ?>"
                       required class="form-control phone-number" autocomplete="tel" inputmode="tel">
              </div>
            </div>

            <div class="form-group">
              <label for="account_type"><?= $e($translations['label_account_type'] ?? 'label_account_type'); ?></label>
              <select id="account_type" name="account_type" required class="form-control">
                <option value=""><?= $e($translations['select_option'] ?? 'select_option'); ?></option>
                <option value="provider"  <?= (($form_data['account_type'] ?? '') === 'provider')  ? 'selected' : ''; ?>><?= $e($translations['account_type_provider']  ?? 'account_type_provider'); ?></option>
                <option value="services"  <?= (($form_data['account_type'] ?? '') === 'services')  ? 'selected' : ''; ?>><?= $e($translations['account_type_services']  ?? 'account_type_services'); ?></option>
                <option value="companies" <?= (($form_data['account_type'] ?? '') === 'companies') ? 'selected' : ''; ?>><?= $e($translations['account_type_companies'] ?? 'account_type_companies'); ?></option>
              </select>
            </div>

            <div class="form-group checkbox-group">
              <label class="checkbox-label" for="privacy_consent">
                <input type="checkbox" id="privacy_consent" name="privacy_consent" value="1"
                       <?= (isset($form_data['privacy_consent']) && $form_data['privacy_consent'] == '1') ? 'checked' : ''; ?> required>
                <span class="control-indicator"></span>
                <?php
                  /* Template pode conter HTML com %s para a URL — aplicamos sprintf sem escapar o template */
                  echo sprintf(
                    $translations['label_privacy_consent_full'] ?? 'label_privacy_consent_full',
                    $e(SITE_URL . '/pages/privacy_policy.php')
                  );
                ?>
              </label>
            </div>
          </div>

          <div class="form-actions">
            <input type="submit" class="btn-primary" value="<?= $e($translations['button_proceed'] ?? 'button_proceed'); ?>">
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</main>

<script <?= $csp_nonce ? 'nonce="'.$e($csp_nonce).'"' : ''; ?>>
document.addEventListener('DOMContentLoaded', function() {
  const phoneCodeSelect   = document.getElementById('phone_code');
  const nationalitySelect = document.getElementById('nationality_id');
  const modal             = document.getElementById('registrationMessageModal');
  const modalCloseButton  = document.getElementById('modalCloseButton');

  function updateFlag(selectElement) {
    if (!selectElement) return;
    const opt = selectElement.options[selectElement.selectedIndex];
    const flagUrl = opt && opt.dataset ? opt.dataset.flag : '';
    if (flagUrl) {
      selectElement.style.backgroundImage    = 'url(' + flagUrl + ')';
      selectElement.style.backgroundRepeat   = 'no-repeat';
      selectElement.style.backgroundSize     = '24px auto';
      selectElement.style.backgroundPosition = '10px center';
      selectElement.style.paddingLeft        = '40px';
    } else {
      selectElement.style.backgroundImage = 'none';
      selectElement.style.paddingLeft     = '14px';
    }
  }

  if (phoneCodeSelect) {
    updateFlag(phoneCodeSelect);
    phoneCodeSelect.addEventListener('change', function(){ updateFlag(phoneCodeSelect); });
  }
  if (nationalitySelect) {
    updateFlag(nationalitySelect);
    nationalitySelect.addEventListener('change', function(){ updateFlag(nationalitySelect); });
  }

  if (modal && modalCloseButton) {
    function closeModalAndGoHome() {
      modal.classList.remove('show');
      window.location.href = '<?= $e(SITE_URL); ?>';
    }
    modalCloseButton.addEventListener('click', closeModalAndGoHome);
    modal.addEventListener('click', function(event) {
      if (event.target === modal) closeModalAndGoHome();
    });
  }
});
</script>

<?php
require_once TEMPLATE_PATH . 'footer.php';
