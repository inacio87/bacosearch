<?php
/**
 * /checkout.php - VERSÃO FINAL E CORRIGIDA SEM TEXTO DIRETO + MOEDA DINÂMICA
 * Última atualização: 15/08/2025
 */

require_once __DIR__ . '/core/bootstrap.php';

/* ===== PASSO 1: Helpers / segurança ===== */
$e = static function (?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
if (empty($_SESSION['csrf_token'])) {
  try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
  catch (Throwable $t) { $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32)); }
}

/* ===== PASSO 2: CONTROLE DE ACESSO ===== */
$account_id  = filter_input(INPUT_GET, 'account_id', FILTER_VALIDATE_INT) ?: ($_SESSION['account_id']  ?? null);
$provider_id = filter_input(INPUT_GET, 'provider_id', FILTER_VALIDATE_INT) ?: ($_SESSION['provider_id'] ?? null);

if (!$account_id || !$provider_id) {
  log_system_error("CHECKOUT_ACCESS_DENIED: Faltam IDs de conta ou de prestador.", 'WARNING', 'checkout_access');
  header("Location: " . rtrim(SITE_URL, '/') . "/auth/register.php", true, 302);
  exit;
}
$_SESSION['account_id']  = (int)$account_id;
$_SESSION['provider_id'] = (int)$provider_id;

/* ===== PASSO 3: OBTÉM MOEDA DO USUÁRIO (countries) ===== */
$currency_symbol = '';
$currency_code   = '';
try {
  $db = getDBConnection();
  if (!empty($_SESSION['country_id'])) {
    $stmt_currency = $db->prepare("SELECT currencies_icon, currencies FROM countries WHERE id = :id LIMIT 1");
    $stmt_currency->execute([':id' => $_SESSION['country_id']]);
    if ($row_currency = $stmt_currency->fetch(PDO::FETCH_ASSOC)) {
      $currency_symbol = (string)($row_currency['currencies_icon'] ?? '');
      $currency_code   = (string)($row_currency['currencies'] ?? '');
    }
  }
} catch (Throwable $ex) {
  log_system_error("CHECKOUT_CURRENCY_ERROR: " . $ex->getMessage(), 'NOTICE', 'checkout_currency_load');
}

/* ===== PASSO 4: CARREGAR PLANOS DE ASSINATURA ===== */
$plans = [];
try {
  // Reusa $db se já aberto; senão abre
  if (!isset($db)) { $db = getDBConnection(); }
  $stmt_plans = $db->query("SELECT * FROM plans WHERE is_active = TRUE ORDER BY price_monthly ASC");
  $plans = $stmt_plans->fetchAll(PDO::FETCH_ASSOC);

  foreach ($plans as &$plan) {
    $plan['features'] = (isset($plan['features']) && is_string($plan['features']))
      ? (json_decode($plan['features'], true) ?? [])
      : [];
    // saneia tipo para classe CSS
    $plan['type'] = preg_match('~^(free|premium)$~', (string)($plan['type'] ?? ''), $m) ? $m[1] : 'free';
  }
  unset($plan);
} catch (Throwable $ex) {
  log_system_error("CHECKOUT_ERROR: Falha ao carregar planos do DB: " . $ex->getMessage(), 'CRITICAL', 'checkout_load_plans');
  $plans = [];
}

/* ===== PASSO 5: TRADUÇÕES ===== */
$languageCode = $_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us');
$city = $_SESSION['city'] ?? getTranslation('detecting_location', $languageCode, 'header');

$page_specific_styles  = [ rtrim(SITE_URL, '/') . '/assets/css/checkout.css' ];
$page_specific_scripts = [[ 'src' => 'https://js.stripe.com/v3/', 'attrs' => ['defer'] ]];

$translations = [];
$keys_to_translate = [
  'checkout_title','checkout_heading','checkout_subheading',
  'plan_free_name','plan_premium_name','plan_free_price',
  'feature_ad_visibility','feature_photo_limit','feature_direct_contact',
  'feature_premium_highlight','feature_premium_photos',
  'button_confirm_publish','button_upgrade_premium',
  'coming_soon_premium','no_plans_found','price_per_month',
  'logo_alt','header_ads','header_login','header_menu','about_us','header_licenses',
  'terms_of_service','privacy_policy','cookie_policy','contact_us','detecting_location',
  'footer_providers','footer_companies','footer_clubs','footer_streets','footer_services'
];
foreach ($keys_to_translate as $key) {
  $context = 'checkout';
  if (strpos($key, 'header_') === 0 || in_array($key, ['logo_alt','detecting_location','header_licenses'], true)) {
    $context = 'header';
  } elseif (strpos($key, 'footer_') === 0 || in_array($key, ['about_us','terms_of_service','privacy_policy','cookie_policy','contact_us'], true)) {
    $context = 'footer';
  }
  $translations[$key] = getTranslation($key, $languageCode, $context);
}

$translations['languageOptionsForDisplay']      = LANGUAGE_CONFIG['name_map'] ?? [];
$translations['current_language_display_name']  = $translations['languageOptionsForDisplay'][$languageCode]
  ?? getTranslation('language_label', $languageCode, 'default');
$page_title = $translations['checkout_title'] ?? 'checkout_title';

/* ===== PASSO 6: Render ===== */
require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';

/* Util para mostrar moeda (símbolo -> código -> vazio) */
$displayCurrency = function () use ($currency_symbol, $currency_code): string {
  $sym = trim($currency_symbol);
  if ($sym !== '') return $sym;
  $code = trim($currency_code);
  return $code !== '' ? ' ' . $code : '';
};
?>
<main class="checkout-container">
  <div class="checkout-card">
    <div class="card-header">
      <i class="fas fa-check-circle" aria-hidden="true"></i>
      <h2><?= $e($translations['checkout_heading'] ?? ''); ?></h2>
      <p><?= $e($translations['checkout_subheading'] ?? ''); ?></p>
    </div>

    <div class="plans-wrapper" aria-live="polite">
      <?php if (empty($plans)): ?>
        <p><?= $e($translations['no_plans_found'] ?? ''); ?></p>
      <?php else: ?>
        <?php foreach ($plans as $plan): ?>
          <?php
            $isFree    = ($plan['type'] === 'free');
            $isPremium = ($plan['type'] === 'premium');
            $planNameKey = 'plan_' . $plan['type'] . '_name';
            $planName  = $translations[$planNameKey] ?? (string)($plan['name'] ?? ucfirst($plan['type']));
            $price     = (float)($plan['price_monthly'] ?? 0);
          ?>
          <div class="plan-details <?= $e($plan['type']); ?>">
            <div class="plan-box <?= $e($plan['type']); ?>-plan">
              <div class="plan-name">
                <?php if ($isFree): ?><i class="fas fa-star" aria-hidden="true"></i>
                <?php elseif ($isPremium): ?><i class="fas fa-gem" aria-hidden="true"></i><?php endif; ?>
                <span><?= $e($planName); ?></span>
              </div>

              <div class="plan-price">
                <?php if ($price == 0): ?>
                  <?= $e($translations['plan_free_price'] ?? ''); ?>
                <?php else: ?>
                  <?= $e(number_format($price, 2, ',', '.')); ?><?= $e($displayCurrency()); ?>
                  <span class="per-month"><?= $e($translations['price_per_month'] ?? ''); ?></span>
                <?php endif; ?>
              </div>
            </div>

            <ul class="plan-features">
              <?php foreach ($plan['features'] as $feature): ?>
                <li class="<?= (!empty($feature['highlight'])) ? 'highlight' : ''; ?>">
                  <i class="<?= $e($feature['icon'] ?? 'fas fa-check'); ?>" aria-hidden="true"></i>
                  <?php
                    $textKey = $feature['text_key'] ?? '';
                    $text    = $textKey !== '' ? ($translations[$textKey] ?? '') : '';
                    if ($text === '') { $text = (string)($feature['text'] ?? ''); }
                  ?>
                  <?= $e($text); ?>
                </li>
              <?php endforeach; ?>
            </ul>

            <div class="card-footer">
              <?php if ($isFree): ?>
                <a href="<?= $e(rtrim(SITE_URL, '/') . '/success.php'); ?>" class="submit-button free">
                  <?= $e($translations['button_confirm_publish'] ?? ''); ?> <i class="fas fa-rocket" aria-hidden="true"></i>
                </a>
              <?php elseif ($isPremium): ?>
                <?php if (!empty($plan['stripe_price_id'])): ?>
                  <form action="<?= $e(rtrim(SITE_URL, '/') . '/api/create-checkout-session.php'); ?>" method="POST">
                    <input type="hidden" name="csrf_token"  value="<?= $e($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="price_id"    value="<?= $e($plan['stripe_price_id']); ?>">
                    <input type="hidden" name="provider_id" value="<?= $e((string)$provider_id); ?>">
                    <input type="hidden" name="account_id"  value="<?= $e((string)$account_id); ?>">
                    <button type="submit" class="submit-button premium">
                      <?= $e($translations['button_upgrade_premium'] ?? ''); ?> <i class="fas fa-credit-card" aria-hidden="true"></i>
                    </button>
                  </form>
                <?php else: ?>
                  <button type="button" class="submit-button premium disabled" disabled>
                    <?= $e($translations['coming_soon_premium'] ?? ''); ?>
                  </button>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php
require_once TEMPLATE_PATH . 'footer.php';
