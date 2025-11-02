<?php
/**
 * /pages/register_providers.php - PÁGINA DE REGISTRO (VERSÃO FINAL E COMPATÍVEL + i18n por contexto)
 */

$page_name = 'provider_registration_flow';
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/core/i18n_layout.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$languageCode = isset($_SESSION['language']) ? strtolower(str_replace('_', '-', $_SESSION['language'])) : 'en-us';
$account_id   = filter_input(INPUT_GET, 'account_id', FILTER_VALIDATE_INT);
$form_data    = $_SESSION['form_data_provider_form']  ?? [];
$errors       = $_SESSION['errors_provider_form']     ?? [];
unset($_SESSION['errors_provider_form']);
$show_profile_form = false;

try {
    if (empty($account_id)) {
        throw new Exception(getTranslation('error_access_denied_provider_flow', $languageCode, 'ui_messages'));
    }

    $account_details = db_fetch_one("SELECT id, role_id, status, password_hash FROM accounts WHERE id = ?", [$account_id]);
    if (!$account_details) {
        throw new Exception(getTranslation('error_access_denied_provider_flow', $languageCode, 'ui_messages'));
    }

    $role_slug_db     = db_fetch_one("SELECT slug FROM access_roles WHERE id = ?", [$account_details['role_id']]);
    $account_role_slug = $role_slug_db['slug'] ?? null;

    if ($account_role_slug !== 'providers' && $account_role_slug !== 'services') {
        throw new Exception(getTranslation('error_access_denied_provider_flow', $languageCode, 'ui_messages'));
    }

    if (!empty($account_details['password_hash']) && $account_details['status'] === 'active') {
        $show_profile_form = true;
    } else {
        throw new Exception(getTranslation('error_access_denied_provider_flow', $languageCode, 'ui_messages'));
    }

} catch (Exception $e) {
    $_SESSION['errors_login'] = ['general' => $e->getMessage()];
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

/**
 * i18n — carregamos TODOS os contextos usados pela página e módulos.
 * A função loadTranslationsFor consulta: /lang/<base>/<context>.php, mapa geral e tabela texts.
 */
$contexts = [
    // cabeçalhos gerais desta página
    'provider_form', 'provider_profile_form', 'provider_body_form', 'provider_services_form',
    'provider_values_form', 'provider_media_form', 'provider_contact_form', 'provider_logistics_form',
    'provider_security_form',
    // comuns
    'common_options', 'common_buttons', 'common_form', 'validation_errors', 'ui_messages',
    // header/footer (para itens que a página usa direta/indiretamente)
    'header', 'footer',
];
$tx = loadTranslationsFor($languageCode, $contexts);

// Fallback MÍNIMO: chaves específicas que a página já pedia via getTranslation()
$needed_keys = [
    'module_title_profile' => 'provider_profile_form',
    'module_title_body' => 'provider_body_form',
    'module_title_services' => 'provider_services_form',
    'module_title_values' => 'provider_values_form',
    'module_title_media' => 'provider_media_form',
    'module_title_contact' => 'provider_contact_form',
    'module_title_logistics' => 'provider_logistics_form',
    'module_title_security' => 'provider_security_form',
    'provider_form_title' => 'provider_form',
    'provider_form_subtitle' => 'provider_form',
    'button_save_continue' => 'common_buttons',
    'profile_module_description' => 'provider_profile_form',

    // exemplos comuns utilizados na UI:
    'label_artistic_name' => 'provider_profile_form',
    'placeholder_artistic_name' => 'provider_profile_form',
    'label_ad_title' => 'provider_profile_form',
    'placeholder_ad_title' => 'provider_profile_form',
    'label_description' => 'provider_profile_form',
    'placeholder_description' => 'provider_profile_form',
    'label_gender' => 'provider_profile_form',
    'label_provider_type' => 'provider_profile_form',
    'label_nationality' => 'provider_profile_form',
    'label_age' => 'provider_profile_form',
    'label_languages' => 'provider_profile_form',
    'label_keywords' => 'provider_profile_form',
    'keywords_description' => 'provider_profile_form',
    'placeholder_keyword' => 'provider_profile_form',
    'keyword_limit_text' => 'provider_profile_form',
    'button_add_keyword' => 'provider_profile_form',

    'option_female' => 'common_options',
    'option_male' => 'common_options',
    'option_trans' => 'common_options',
    'option_couple' => 'common_options',
    'option_independent' => 'common_options',
    'option_agency' => 'common_options',
    'select_option_default' => 'common_options',

    'feedback_required_field' => 'validation_errors',
    'min_chars_feedback' => 'validation_errors',
    'max_chars_feedback' => 'validation_errors',
    'char_count_format' => 'common_form',

    // header/footer que esta página usa
    'logo_alt' => 'header',
    'header_ads' => 'header',
    'header_login' => 'header',
    'header_menu' => 'header',
    'detecting_location' => 'ui_messages',
    'about_us' => 'header',
    'terms_of_service' => 'header',
    'privacy_policy' => 'header',
    'cookie_policy' => 'header',
    'contact_us' => 'header',
    'header_licenses' => 'header',

    'footer_providers' => 'footer',
    'footer_explore' => 'footer',
    'footer_clubs' => 'footer',
    'footer_streets' => 'footer',
    'footer_companies' => 'footer',
    'footer_services' => 'footer',
];

// Monta $translations priorizando $tx (contextos) e caindo no getTranslation quando faltar
$translations = $tx;
foreach ($needed_keys as $key => $ctx) {
    if (!isset($translations[$key]) || $translations[$key] === '') {
        $translations[$key] = getTranslation($key, $languageCode, $ctx);
    }
}

// Dados de idioma e cidade para header
$city = $_SESSION['city'] ?? ($translations['detecting_location'] ?? 'detecting_location');
$translations['languageOptionsForDisplay'] = LANGUAGE_CONFIG['name_map'] ?? [];
$translations['current_language_display_name'] =
    $translations['languageOptionsForDisplay'][$languageCode] ?? (getTranslation('language_label', $languageCode, 'default') ?? strtoupper($languageCode));

$page_title = $translations['provider_form_title'] ?? 'provider_form_title';

// INJEÇÃO NO FRONT: disponibiliza site_url, language e TODAS as traduções carregadas
echo '<script>';
echo 'window.appConfig=window.appConfig||{};';
echo 'window.appConfig.site_url=' . json_encode(SITE_URL) . ';';
echo 'window.appConfig.language=' . json_encode($languageCode) . ';';
echo 'window.appConfig.translations=' . json_encode($translations, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . ';';
echo '</script>';

require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';

$e = static function($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<main>
  <div class="main-container">
    <?php if ($show_profile_form): ?>
      <form method="post"
            action="<?= $e(SITE_URL . '/api/api_register_providers.php'); ?>"
            id="provider-registration-form"
            enctype="multipart/form-data"
            novalidate>
        <input type="hidden" name="account_id" value="<?= $e($account_id); ?>">
        <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token'] ?? ''); ?>">

        <div class="register-container">
          <div class="form-header">
            <h1><?= $e($page_title); ?></h1>
            <p><?= $e($translations['provider_form_subtitle'] ?? ''); ?></p>
          </div>
        </div>

        <?php if (!empty($errors)): ?>
          <div class="register-container" style="background:#fff3cd;border-color:#ffeeba;margin-bottom:1rem;">
            <div class="error-message show" style="color:#856404;">
              <strong><?= $e($translations['feedback_required_field'] ?? ''); ?></strong>
              <ul>
                <?php foreach ($errors as $error): ?>
                  <li><?= $e($error); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        <?php endif; ?>

        <?php
          $module_path = dirname(__DIR__) . '/modules/providers/';
          $modules_to_include = ['profile.php','body.php','services.php','values.php','media.php','contact.php','logistics.php','security.php'];
          foreach ($modules_to_include as $module_file) {
              echo '<div class="register-container">';
              if (file_exists($module_path . $module_file)) {
                  try {
                      require $module_path . $module_file;
                  } catch (Exception $ex) {
                      echo '<p style="color:red;">' .
                        sprintf($e(getTranslation('error_loading_module', $languageCode, 'ui_messages')), $e($module_file)) .
                      '</p>';
                  }
              } else {
                  echo '<p style="color:red;">' .
                    sprintf($e(getTranslation('error_module_not_found', $languageCode, 'ui_messages')), $e($module_file)) .
                  '</p>';
              }
              echo '</div>';
          }
        ?>

        <div class="register-container form-actions-container">
          <div class="form-actions">
            <button type="submit" class="btn-primary">
              <?= $e($translations['button_save_continue'] ?? ''); ?>
            </button>
          </div>
        </div>
      </form>
    <?php else: ?>
      <div class="register-container">
        <div class="error-message show">
          <strong><?= $e(getTranslation('error_access_denied_provider_flow', $languageCode, 'ui_messages')); ?></strong>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<script>
// Submissão com feedback
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('provider-registration-form');
  if (!form) return;

  const btnTxtSaving   = <?= json_encode(getTranslation('form_saving_progress', $languageCode, 'ui_messages')); ?>;
  const btnTxtErrorComm= <?= json_encode(getTranslation('form_communication_error', $languageCode, 'ui_messages')); ?>;

  form.addEventListener('submit', function(event) {
    event.preventDefault();
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = btnTxtSaving;

    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        window.location.href = <?= json_encode(SITE_URL . '/success.php'); ?> + '?status=analysis_pending&provider_id=' + data.data.provider_id;
      } else {
        alert('Erro: ' + data.message);
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonText;
      }
    })
    .catch(err => {
      console.error('Erro de comunicação:', err);
      alert(btnTxtErrorComm);
      submitButton.disabled = false;
      submitButton.innerHTML = originalButtonText;
    });
  });
});
</script>

<?php require_once TEMPLATE_PATH . 'footer.php'; ?>
