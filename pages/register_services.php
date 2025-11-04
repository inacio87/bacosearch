<?php
/**
 * /pages/register_services.php - Cadastro de Serviços (base)
 */

$page_name = 'services_registration_flow';
require_once dirname(__DIR__) . '/core/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$languageCode = $_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us');
$account_id = filter_input(INPUT_GET, 'account_id', FILTER_VALIDATE_INT);
$errors = $_SESSION['errors_services_form'] ?? [];
unset($_SESSION['errors_services_form']);

$show_form = false;
try {
  if (empty($account_id)) throw new Exception(getTranslation('error_access_denied_services_flow', $languageCode, 'ui_messages'));
  $acc = db_fetch_one("SELECT id, role_id, status FROM accounts WHERE id = ?", [$account_id]);
  if (!$acc || $acc['status'] !== 'active') throw new Exception(getTranslation('error_access_denied_services_flow', $languageCode, 'ui_messages'));
  $role = db_fetch_one("SELECT slug FROM access_roles WHERE id = ?", [$acc['role_id']]);
  if (($role['slug'] ?? '') !== 'services') throw new Exception(getTranslation('error_access_denied_services_flow', $languageCode, 'ui_messages'));
  $show_form = true;
} catch (Throwable $e) {
  $_SESSION['errors_login'] = ['general' => $e->getMessage()];
  header('Location: ' . SITE_URL . '/auth/login.php');
  exit;
}

$tmap = [
  'services_form_title'     => 'services_form',
  'services_form_subtitle'  => 'services_form',
  'label_service_title'     => 'services_form',
  'label_description'       => 'services_form',
  'label_category'          => 'services_form',
  'label_price_min'         => 'services_form',
  'label_price_max'         => 'services_form',
  'label_currency'          => 'services_form',
  'label_phone'             => 'services_form',
  'label_email'             => 'services_form',
  'label_website'           => 'services_form',
  'label_country'           => 'services_form',
  'label_state'             => 'services_form',
  'label_city'              => 'services_form',
  'label_street'            => 'services_form',
  'label_postal_code'       => 'services_form',
  'label_main_photo'        => 'services_form',
  'label_gallery_photos'    => 'services_form',
  'button_save_continue'    => 'common_buttons',
  'feedback_required_field' => 'validation_errors',
  'detecting_location'      => 'ui_messages',
  'logo_alt'                => 'header',
  'header_ads'              => 'header',
  'header_login'            => 'header',
  'header_menu'             => 'header',
  'about_us'                => 'header',
  'terms_of_service'        => 'header',
  'privacy_policy'          => 'header',
  'cookie_policy'           => 'header',
  'contact_us'              => 'header',
  'header_licenses'         => 'header',
  'footer_providers'        => 'footer',
  'footer_companies'        => 'footer',
  'footer_services'         => 'footer',
  'footer_streets'          => 'footer',
  'footer_clubs'            => 'footer',
];
$tr = [];
foreach ($tmap as $k=>$ctx) { $tr[$k] = getTranslation($k, $languageCode, $ctx); }

$city = $_SESSION['city'] ?? ($tr['detecting_location'] ?? 'detecting_location');
$tr['languageOptionsForDisplay'] = LANGUAGE_CONFIG['name_map'] ?? [];
$tr['current_language_display_name'] = $tr['languageOptionsForDisplay'][$languageCode] ?? getTranslation('language_label', $languageCode, 'default');
$page_title = $tr['services_form_title'] ?? 'services_form_title';

require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';
$e = static function($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<main>
  <div class="main-container">
    <?php if ($show_form): ?>
    <form method="post" action="<?= $e(SITE_URL . '/api/api_register_services.php'); ?>" enctype="multipart/form-data" id="services-form" novalidate>
      <input type="hidden" name="account_id" value="<?= $e($account_id); ?>">
      <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token'] ?? ''); ?>">

      <div class="register-container">
        <div class="form-header">
          <h1><?= $e($page_title); ?></h1>
          <p><?= $e($tr['services_form_subtitle'] ?? ''); ?></p>
        </div>
      </div>

      <?php if (!empty($errors)): ?>
      <div class="register-container" style="background:#fff3cd;border-color:#ffeeba;margin-bottom:1rem;">
        <div class="error-message show" style="color:#856404;">
          <strong><?= $e($tr['feedback_required_field'] ?? ''); ?></strong>
          <ul><?php foreach ($errors as $err): ?><li><?= $e($err); ?></li><?php endforeach; ?></ul>
        </div>
      </div>
      <?php endif; ?>

      <div class="register-container">
        <div class="form-group">
          <label><?= $e($tr['label_service_title'] ?? 'label_service_title'); ?></label>
          <input type="text" name="service_title" maxlength="200" required class="form-control">
        </div>
        <div class="form-group">
          <label><?= $e($tr['label_description'] ?? 'label_description'); ?></label>
          <textarea name="description" rows="5" class="form-control"></textarea>
        </div>
        <div class="form-row" style="display:flex; gap:12px;">
          <div class="form-group" style="flex:1;">
            <label><?= $e($tr['label_price_min'] ?? 'label_price_min'); ?></label>
            <input type="number" step="0.01" name="price_min" class="form-control">
          </div>
          <div class="form-group" style="flex:1;">
            <label><?= $e($tr['label_price_max'] ?? 'label_price_max'); ?></label>
            <input type="number" step="0.01" name="price_max" class="form-control">
          </div>
          <div class="form-group" style="flex:1;">
            <label><?= $e($tr['label_currency'] ?? 'label_currency'); ?></label>
            <input type="text" name="currency" maxlength="10" class="form-control" placeholder="EUR">
          </div>
        </div>
      </div>

      <div class="register-container">
        <div class="form-group">
          <label><?= $e($tr['label_phone'] ?? 'label_phone'); ?></label>
          <div class="phone-input-group">
            <input type="text" name="phone_code" class="form-control phone-code" placeholder="+351" style="max-width:120px;">
            <input type="text" name="phone_number" class="form-control phone-number" placeholder="900000000">
          </div>
        </div>
        <div class="form-group">
          <label><?= $e($tr['label_email'] ?? 'label_email'); ?></label>
          <input type="email" name="email" class="form-control">
        </div>
        <div class="form-group">
          <label><?= $e($tr['label_website'] ?? 'label_website'); ?></label>
          <input type="url" name="website_url" class="form-control" placeholder="https://">
        </div>
      </div>

      <div class="register-container">
        <div class="form-group">
          <label><?= $e($tr['label_country'] ?? 'label_country'); ?></label>
          <input type="text" name="ad_country" class="form-control" maxlength="2" placeholder="PT">
        </div>
        <div class="form-group">
          <label><?= $e($tr['label_state'] ?? 'label_state'); ?></label>
          <input type="text" name="ad_state" class="form-control">
        </div>
        <div class="form-group">
          <label><?= $e($tr['label_city'] ?? 'label_city'); ?></label>
          <input type="text" name="ad_city" class="form-control">
        </div>
        <div class="form-group">
          <label><?= $e($tr['label_street'] ?? 'label_street'); ?></label>
          <input type="text" name="ad_street" class="form-control">
        </div>
        <div class="form-group">
          <label><?= $e($tr['label_postal_code'] ?? 'label_postal_code'); ?></label>
          <input type="text" name="ad_postal_code" class="form-control">
        </div>
      </div>

      <div class="register-container">
        <div class="form-group">
          <label><?= $e($tr['label_main_photo'] ?? 'label_main_photo'); ?></label>
          <input type="file" name="main_photo" accept="image/*" class="form-control">
        </div>
        <div class="form-group">
          <label><?= $e($tr['label_gallery_photos'] ?? 'label_gallery_photos'); ?></label>
          <input type="file" name="gallery_photos[]" multiple accept="image/*" class="form-control">
        </div>
      </div>

      <div class="register-container form-actions-container">
        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= $e($tr['button_save_continue'] ?? 'button_save_continue'); ?></button>
        </div>
      </div>
    </form>
    <?php else: ?>
      <div class="register-container"><div class="error-message show"><strong><?= $e(getTranslation('error_access_denied_services_flow', $languageCode, 'ui_messages')); ?></strong></div></div>
    <?php endif; ?>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const form = document.getElementById('services-form');
  if (!form) return;
  const btn = form.querySelector('button[type="submit"]');
  const original = btn.innerHTML;
  const savingText = <?= json_encode(getTranslation('form_saving_progress', $languageCode, 'ui_messages')); ?>;
  const commErr   = <?= json_encode(getTranslation('form_communication_error', $languageCode, 'ui_messages')); ?>;
  form.addEventListener('submit', function(ev){
    ev.preventDefault(); btn.disabled = true; btn.innerHTML = savingText;
    fetch(form.action, { method:'POST', body:new FormData(form), headers:{'Accept':'application/json'} })
      .then(r=>r.json()).then(data=>{
        if (data.status==='success') {
          window.location.href = <?= json_encode(SITE_URL . '/success.php'); ?> + '?status=analysis_pending&service_id=' + data.data.id;
        } else { alert('Erro: ' + data.message); btn.disabled=false; btn.innerHTML=original; }
      }).catch(()=>{ alert(commErr); btn.disabled=false; btn.innerHTML=original; });
  });
});
</script>

<?php require_once TEMPLATE_PATH . 'footer.php';
