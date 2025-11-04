<?php
/**
 * /pages/streets_submit.php - Submissão de Ruas/Locais (forum-like)
 * Somente usuários autenticados podem submeter. Conteúdo fica pendente até aprovação de admin.
 */

$page_name = 'streets_submit_page';
require_once dirname(__DIR__) . '/core/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$languageCode = $_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us');

// Requer autenticação básica
$accountId = $_SESSION['account_id'] ?? ($_SESSION['temp_user_id'] ?? null);
if (!$accountId) {
  $_SESSION['errors_login'] = ['general' => getTranslation('login_required', $languageCode, 'ui_messages')];
  header('Location: ' . SITE_URL . '/auth/login.php');
  exit;
}

$tmap = [
  'streets_submit_title'     => 'streets_form',
  'streets_submit_subtitle'  => 'streets_form',
  'label_place_type'         => 'streets_form',
  'option_street'            => 'streets_form',
  'option_bar'               => 'streets_form',
  'label_street_name'        => 'streets_form',
  'label_place_name'         => 'streets_form',
  'label_city'               => 'streets_form',
  'label_state'              => 'streets_form',
  'label_country'            => 'streets_form',
  'label_description'        => 'streets_form',
  'label_tags'               => 'streets_form',
  'button_submit'            => 'common_buttons',
  'feedback_required_field'  => 'validation_errors',
  'detecting_location'       => 'ui_messages',
  'logo_alt'                 => 'header',
  'header_ads'               => 'header',
  'header_login'             => 'header',
  'header_menu'              => 'header',
  'about_us'                 => 'header',
  'terms_of_service'         => 'header',
  'privacy_policy'           => 'header',
  'cookie_policy'            => 'header',
  'contact_us'               => 'header',
  'header_licenses'          => 'header',
  'footer_providers'         => 'footer',
  'footer_companies'         => 'footer',
  'footer_services'          => 'footer',
  'footer_streets'           => 'footer',
  'footer_clubs'             => 'footer',
];
$tr = [];
foreach ($tmap as $k=>$ctx) { $tr[$k] = getTranslation($k, $languageCode, $ctx); }

$city = $_SESSION['city'] ?? ($tr['detecting_location'] ?? 'detecting_location');
$tr['languageOptionsForDisplay'] = LANGUAGE_CONFIG['name_map'] ?? [];
$tr['current_language_display_name'] = $tr['languageOptionsForDisplay'][$languageCode] ?? getTranslation('language_label', $languageCode, 'default');
$page_title = $tr['streets_submit_title'] ?? 'streets_submit_title';

require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';
$e = static function($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<main>
  <div class="main-container">
    <form method="post" action="<?= $e(SITE_URL . '/api/api_submit_street.php'); ?>" id="streets-form" novalidate>
      <input type="hidden" name="account_id" value="<?= $e((string)$accountId); ?>">
      <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token'] ?? ''); ?>">

      <div class="register-container">
        <div class="form-header">
          <h1><?= $e($page_title); ?></h1>
          <p><?= $e($tr['streets_submit_subtitle'] ?? ''); ?></p>
        </div>
      </div>

      <div class="register-container">
        <div class="form-group">
          <label><?= $e($tr['label_place_type'] ?? 'label_place_type'); ?></label>
          <select name="place_type" class="form-control">
            <option value="street"><?= $e($tr['option_street'] ?? 'option_street'); ?></option>
            <option value="bar"><?= $e($tr['option_bar'] ?? 'option_bar'); ?></option>
          </select>
        </div>
        <div class="form-group">
          <label><?= $e($tr['label_street_name'] ?? 'label_street_name'); ?></label>
          <input type="text" name="street_name" class="form-control" placeholder="Rua ...">
        </div>
        <div class="form-group">
          <label><?= $e($tr['label_place_name'] ?? 'label_place_name'); ?></label>
          <input type="text" name="place_name" class="form-control" placeholder="Nome do bar (se aplicável)">
        </div>
        <div class="form-row" style="display:flex; gap:12px; flex-wrap:wrap;">
          <div class="form-group" style="flex:1 1 180px;">
            <label><?= $e($tr['label_country'] ?? 'label_country'); ?></label>
            <input type="text" name="ad_country" maxlength="2" class="form-control" placeholder="PT">
          </div>
          <div class="form-group" style="flex:1 1 180px;">
            <label><?= $e($tr['label_state'] ?? 'label_state'); ?></label>
            <input type="text" name="ad_state" class="form-control">
          </div>
          <div class="form-group" style="flex:1 1 180px;">
            <label><?= $e($tr['label_city'] ?? 'label_city'); ?></label>
            <input type="text" name="ad_city" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label><?= $e($tr['label_description'] ?? 'label_description'); ?></label>
          <textarea name="description" rows="5" class="form-control" placeholder="Descreva por que esta rua/este bar é uma referência..."></textarea>
        </div>
        <div class="form-group">
          <label><?= $e($tr['label_tags'] ?? 'label_tags'); ?></label>
          <input type="text" name="tags" class="form-control" placeholder="ex: acompanhantes, ponto, movimentado">
        </div>
      </div>

      <div class="register-container form-actions-container">
        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= $e($tr['button_submit'] ?? 'button_submit'); ?></button>
        </div>
      </div>
    </form>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const form = document.getElementById('streets-form');
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
          window.location.href = <?= json_encode(SITE_URL . '/success.php'); ?> + '?status=analysis_pending&street_post_id=' + data.data.id;
        } else { alert('Erro: ' + data.message); btn.disabled=false; btn.innerHTML=original; }
      }).catch(()=>{ alert(commErr); btn.disabled=false; btn.innerHTML=original; });
  });
});
</script>

<?php require_once TEMPLATE_PATH . 'footer.php';
