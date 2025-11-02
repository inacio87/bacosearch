<?php
/**
 * /success.php - PÁGINA DE SUCESSO (VERSÃO FINAL, SEM LITERAIS)
 *
 * RESPONSABILIDADES:
 * 1. Ponto de entrada após um processo bem-sucedido (registo, etc.).
 * 2. Carrega traduções de forma centralizada, seguindo o padrão do projeto.
 * 3. Mostra a mensagem correta com base no status ('analysis_pending').
 * 4. Renderiza a página completa com cabeçalho e rodapé.
 *
 * ÚLTIMA ATUALIZAÇÃO: 15/08/2025
 */

/* Identificador da página (para CSS/body class no head.php) */
$page_name = 'success_page';

/* PASSO 1: INICIALIZAÇÃO CENTRAL */
require_once __DIR__ . '/core/bootstrap.php';

/* PASSO 2: LÓGICA DA PÁGINA */
/* Sanitiza e valida "status" (whitelist) */
$allowed_status = ['analysis_pending', 'default'];
$rawStatus = isset($_GET['status']) ? strtolower((string)$_GET['status']) : 'default';
$status = in_array($rawStatus, $allowed_status, true) ? $rawStatus : 'default';

/* Limpeza de sessões sensíveis */
unset($_SESSION['form_data_provider_form'], $_SESSION['errors_provider_form']);

/* PASSO 3: PREPARAÇÃO DE TRADUÇÕES E DADOS PARA A VIEW */
$languageCode = $_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us');
$translations = [];

/**
 * Mapa de traduções:
 * - Padronizamos 'detecting_location' para o contexto 'ui_messages'
 * - Incluímos 'success_message_default' para a mensagem padrão
 */
$translation_map = [
  // Específicas da página
  'success_title'                   => 'ui_messages',
  'success_message_analysis_pending'=> 'ui_messages',
  'success_message_default'         => 'ui_messages',
  'button_home'                     => 'common_buttons',

  // Header
  'logo_alt'         => 'header',
  'header_ads'       => 'header',
  'header_login'     => 'header',
  'header_menu'      => 'header',
  'about_us'         => 'header',
  'terms_of_service' => 'header',
  'privacy_policy'   => 'header',
  'cookie_policy'    => 'header',
  'contact_us'       => 'header',
  'header_licenses'  => 'header',

  // Footer
  'footer_providers' => 'footer',
  'footer_companies' => 'footer',
  'footer_services'  => 'footer',
  'footer_streets'   => 'footer',
  'footer_clubs'     => 'footer',

  // Geolocalização padronizada
  'detecting_location' => 'ui_messages',
];

/* Carrega traduções */
foreach ($translation_map as $key => $context) {
  $translations[$key] = getTranslation($key, $languageCode, $context);
}

/* Variáveis para templates — SEM literais: fallback é a própria chave */
$page_title = $translations['success_title'] ?? 'success_title';
$city = $_SESSION['city'] ?? getTranslation('detecting_location', $languageCode, 'ui_messages');
$translations['languageOptionsForDisplay'] = LANGUAGE_CONFIG['name_map'] ?? [];
// AJUSTADO: Reutiliza a chave 'language_label' para o fallback
$translations['current_language_display_name'] =
  $translations['languageOptionsForDisplay'][$languageCode]
  ?? getTranslation('language_label', $languageCode, 'default');

/* PASSO 4: RENDERIZAÇÃO */
require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';

/* Helper de escape */
$e = static function (?string $v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};
?>
<main>
  <div class="main-container">
    <div class="register-container" style="margin-top: 3rem; margin-bottom: 3rem;">
      <div class="form-header" style="text-align: center;">

        <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--bs-success); margin-bottom: 1rem;" aria-hidden="true"></i>

        <h2><?= $e($page_title); ?></h2>

        <div class="success-message" role="status" aria-live="polite">
          <?php
            // Mensagem conforme status — SEM literais
            switch ($status) {
              case 'analysis_pending':
                $message_key = 'success_message_analysis_pending';
                break;
              case 'default':
              default:
                $message_key = 'success_message_default';
                break;
            }
            $message_to_display = getTranslation($message_key, $languageCode, 'ui_messages') ?? $message_key;

            echo '<p style="font-size: 1.1rem; max-width: 600px; margin: 0 auto;">'
                 . $e($message_to_display)
                 . '</p>';
          ?>
        </div>

        <a href="<?= $e(SITE_URL); ?>" class="btn-primary" style="margin-top: 2rem;">
          <i class="fas fa-home" aria-hidden="true"></i>
          <?= $e($translations['button_home'] ?? 'button_home'); ?>
        </a>

      </div>
    </div>
  </div>
</main>

<?php
require_once TEMPLATE_PATH . 'footer.php';
if (function_exists('ob_end_flush') && ob_get_level() > 0) {
  ob_end_flush();
}
