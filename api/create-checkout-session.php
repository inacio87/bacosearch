<?php
/**
 * /api/create-checkout-session.php - VERSÃO FINAL E 100% TRADUZIDA
 *
 * ÚLTIMA ATUALIZAÇÃO: 15/08/2025
 */

require_once __DIR__ . '/../core/bootstrap.php';
require_once dirname(__DIR__) . '/vendor/stripe-php/init.php';

$languageCode = $_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us');

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

/** Helpers */
$e = static function (?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

/** Força POST e valida CSRF */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Location: ' . rtrim(SITE_URL, '/') . '/checkout.php', true, 303);
  exit;
}
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', (string)$_POST['csrf_token'])) {
  http_response_code(400);
  die($e(getTranslation('checkout_error_csrf_failure', $languageCode, 'checkout_errors') ?: 'Invalid request.'));
}

/** Campos do formulário */
$price_id    = $_POST['price_id']    ?? null;
$provider_id = $_POST['provider_id'] ?? null;
$account_id  = $_POST['account_id']  ?? null;

/** Validações de formato e presença */
if (!$price_id || !preg_match('/^price_[A-Za-z0-9]+$/', (string)$price_id)) {
  log_system_error("Stripe Checkout: Tentativa sem price_id válido. Recebido: {$price_id}", 'warning', 'stripe_checkout_missing_or_bad_price_id');
  http_response_code(400);
  die($e(getTranslation('checkout_error_no_price_id', $languageCode, 'checkout_errors')));
}

if (!is_numeric($provider_id) || !is_numeric($account_id)) {
  log_system_error("Stripe Checkout: IDs inválidos. ProviderID: {$provider_id}, AccountID: {$account_id}", 'warning', 'stripe_checkout_invalid_ids');
  http_response_code(400);
  die($e(getTranslation('checkout_error_invalid_provider_info', $languageCode, 'checkout_errors')));
}

$provider_id = (int)$provider_id;
$account_id  = (int)$account_id;

/** (Opcional) Lista de Price IDs permitidos por configuração */
if (defined('STRIPE_ALLOWED_PRICE_IDS') && is_array(STRIPE_ALLOWED_PRICE_IDS) && !in_array($price_id, STRIPE_ALLOWED_PRICE_IDS, true)) {
  log_system_error("Stripe Checkout: price_id não permitido: {$price_id}", 'warning', 'stripe_checkout_disallowed_price');
  http_response_code(400);
  die($e(getTranslation('checkout_error_invalid_price_id', $languageCode, 'checkout_errors') ?: 'Invalid price.'));
}

/** Mapeia locale do Checkout a partir do idioma da sessão */
$mapLocale = static function (string $lang): string {
  $lang = strtolower(str_replace('_', '-', $lang));
  // principais locais suportados pelo Checkout; fallback = 'auto'
  $map = [
    'pt' => 'pt', 'pt-br' => 'pt', 'pt-pt' => 'pt',
    'en' => 'en', 'en-us' => 'en', 'en-gb' => 'en',
    'es' => 'es', 'es-es' => 'es', 'es-mx' => 'es',
    'fr' => 'fr', 'de' => 'de', 'it' => 'it', 'nl' => 'nl',
  ];
  if (isset($map[$lang])) return $map[$lang];
  $short = substr($lang, 0, 2);
  return $map[$short] ?? 'auto';
};
$locale = $mapLocale($languageCode);

/** URLs de retorno absolutas */
$success_url = rtrim(SITE_URL, '/') . '/success.php?session_id={CHECKOUT_SESSION_ID}&provider_id=' . rawurlencode((string)$provider_id) . '&account_id=' . rawurlencode((string)$account_id);
$cancel_url  = rtrim(SITE_URL, '/') . '/checkout.php?provider_id=' . rawurlencode((string)$provider_id) . '&account_id=' . rawurlencode((string)$account_id);

/** Idempotência para evitar sessões duplicadas em duplo clique */
$idempotency_key = 'chk_' . hash('sha256',
  (session_id() ?: uniqid('', true)) . '|' . $price_id . '|' . $provider_id . '|' . $account_id
);

try {
  /**
   * IMPORTANTE:
   * Para `mode => 'subscription'`, não listamos `multibanco` aqui.
   * Multibanco em subscrições funciona pelo Stripe Billing com `collection_method=send_invoice`,
   * não via Checkout imediato. Cartão permanece como método padrão de subscrição.
   */
  $checkout_session = \Stripe\Checkout\Session::create(
    [
      'mode'        => 'subscription',
      'locale'      => $locale, // deixa auto se mapeamento não cobriu
      'line_items'  => [[
        'price'    => $price_id,
        'quantity' => 1,
      ]],
      // cole ids úteis para reconciliação
      'client_reference_id' => (string)$account_id . ':' . (string)$provider_id,
      'metadata' => [
        'account_id'   => (string)$account_id,
        'provider_id'  => (string)$provider_id,
        'plan_price_id'=> (string)$price_id,
      ],
      'success_url' => $success_url,
      'cancel_url'  => $cancel_url,
      // Exija endereço de faturação se o seu negócio precisar
      // 'billing_address_collection' => 'required',
      // Permite cupões, se usar descontos
      // 'allow_promotion_codes' => true,
      // Em trials longos, pode usar:
      // 'payment_method_collection' => 'if_required',
      // Para coleta de IVA automática (se configurado na conta):
      // 'automatic_tax' => ['enabled' => true],
    ],
    ['idempotency_key' => $idempotency_key]
  );

  header('HTTP/1.1 303 See Other');
  header('Location: ' . $checkout_session->url);
  exit;

} catch (\Stripe\Exception\ApiErrorException $e) {
  log_system_error(
    "STRIPE_API_ERROR: " . $e->getMessage() . " | Price ID: {$price_id} | Account ID: {$account_id}",
    'critical',
    'stripe_api_error'
  );
  http_response_code(502);
  $msg = getTranslation('checkout_error_api_communication', $languageCode, 'checkout_errors')
      ?: 'Erro ao comunicar com o prestador de pagamentos.';
  die($e($msg));

} catch (Throwable $e) {
  log_system_error(
    "STRIPE_GENERAL_ERROR: " . $e->getMessage() . " | Price ID: {$price_id} | Account ID: {$account_id}",
    'critical',
    'stripe_general_error'
  );
  http_response_code(500);
  $msg = getTranslation('checkout_error_unexpected', $languageCode, 'checkout_errors')
      ?: 'Ocorreu um erro inesperado.';
  die($e($msg));
}
