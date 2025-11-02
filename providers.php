<?php
/**
 * /providers.php - PÁGINA DE PERFIL COMPLETA DO ANUNCIANTE (VERSÃO FINAL E CORRIGIDA)
 * Última atualização: 15/08/2025
 */

require_once __DIR__ . '/core/bootstrap.php';

$page_name = 'provider_profile';

$languageCode = $_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us');

/* Helper de escape */
$e = static function (?string $v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};

/* CSP nonce (para JSON-LD/inline script, se seu head.php usar) */
$csp_nonce = $_SESSION['csp_nonce'] ?? null;

/* ID do provider via querystring (id=) ou via slug ...-123 */
$provider_id = 0;
if (isset($_GET['id'])) {
  $provider_id = (int)$_GET['id'];
} else {
  $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
  if (preg_match('~-(\d+)(?:/)?$~', $uri, $m)) {
    $provider_id = (int)$m[1];
  }
}

if ($provider_id <= 0) {
  http_response_code(404);
  exit('<h1>' . $e(getTranslation('error_404_title', $languageCode, 'ui_messages')) . '</h1><p>' . $e(getTranslation('error_404_message', $languageCode, 'ui_messages')) . '</p>');
}

/* Busca dados do provider (+ joins auxiliares) */
try {
  $pdo = getDBConnection();
  $sql = "
    SELECT
      p.*,
      l.ad_city        AS ad_city,
      l.ad_country     AS ad_country,
      l.ad_latitude    AS prov_latitude,
      l.ad_longitude   AS prov_longitude,
      cat.name         AS category_name,
      cn.name          AS nationality_name,
      cn.nationality_female,
      pb.height_cm     AS body_height_cm,
      pb.weight_kg     AS body_weight_kg,
      pb.hair_color    AS body_hair_color,
      pb.eye_color     AS body_eye_color,
      pb.body_type     AS body_type,
      pb.bust_cm       AS body_bust_cm,
      pb.tattoos       AS body_tattoos,
      pb.piercings     AS body_piercings
    FROM providers p
    LEFT JOIN providers_logistics l ON l.provider_id = p.id
    LEFT JOIN providers_body pb      ON pb.provider_id = p.id
    LEFT JOIN providers_contact pc    ON pc.provider_id = p.id
    LEFT JOIN categories cat          ON cat.id = p.category_id
    LEFT JOIN countries cn            ON cn.id = p.nationality_id
    WHERE p.id = :id AND p.status = 'active' AND p.is_active = 1
    LIMIT 1
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id' => $provider_id]);
  $provider = $stmt->fetch(PDO::FETCH_ASSOC);

  $services_offered = [];
  if ($provider) {
    $stmt_services = $pdo->prepare("SELECT service_key, status, price, notes FROM providers_service_offerings WHERE provider_id = ? ORDER BY service_key");
    $stmt_services->execute([$provider_id]);
    $services_offered = $stmt_services->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (Throwable $e_db) {
  error_log("Erro ao buscar perfil do provider: " . $e_db->getMessage());
  http_response_code(500);
  exit('<h1>' . $e(getTranslation('error_500_title', $languageCode, 'ui_messages')) . '</h1><p>' . $e(getTranslation('error_500_message', $languageCode, 'ui_messages')) . '</p>');
}

if (!$provider) {
  http_response_code(404);
  exit('<h1>' . $e(getTranslation('error_404_title', $languageCode, 'ui_messages')) . '</h1><p>' . $e(getTranslation('error_404_provider_unavailable', $languageCode, 'ui_messages')) . '</p>');
}

/* Slug canônico (SEO) */
$in_connector = getTranslation('in_connector', $languageCode, 'provider_page');
$slug = create_slug(
  (string)($provider['category_name'] ?? '') . '-' .
  (string)($provider['nationality_name'] ?? '') . '-' .
  (string)$in_connector . '-' .
  (string)($provider['ad_city'] ?? '') . '-' .
  (string)($provider['display_name'] ?? '') . '-' .
  $provider['id']
);

/* Redireciona para o slug canônico também quando veio com ?id= */
$current_path = ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');
if ($current_path !== $slug) {
  header('Location: ' . rtrim(SITE_URL, '/') . '/' . $slug, true, 301);
  exit;
}

/* Dados de sessão de localização */
$session_lat  = (float)($_SESSION['latitude']  ?? 0);
$session_lon  = (float)($_SESSION['longitude'] ?? 0);
$session_city = $_SESSION['city'] ?? getTranslation('unknown_city', $languageCode, 'provider_page');

/* Traduções necessárias (mapeando contextos de forma compatível com PHP 7) */
$keys_to_translate = [
  'online_status','verified_profile','years_old','distance_from_you','details_title','height_label','weight_label',
  'hair_color_label','eye_color_label','ethnicity_label','body_type_label','bust_size_label','sexual_orientation_label',
  'pubic_hair_label','foot_size_label','tattoos_yes','tattoos_no','piercings_yes','piercings_no','services_offered_title',
  'prices_title','hour_price','15_min_price','30_min_price','2_hour_price','overnight_price','additional_info_title',
  'languages_label','service_locations_label','amenities_label','social_media_title','videos_title','whatsapp_button',
  'telegram_button','call_button','sms_button','gender_female','gender_male','gender_trans','gender_not_informed',
  'service_status_included','service_status_negotiable','service_status_extra_fee','no_photos_available','unknown_city',
  'in_connector','photo_of','instagram_label','twitter_label','onlyfans_label','video_not_supported','very_close','lt_one_km',
  'distance_not_calculated','km_unit','of_preposition','logo_alt','header_login','header_menu','footer_providers',
  'footer_companies','footer_services','footer_clubs','footer_streets','detecting_location','header_licenses','error_404_title',
  'error_404_message','error_404_provider_unavailable','error_500_title','error_500_message','you_are_in'
];
$translations = [];
foreach ($keys_to_translate as $key) {
  $ctx = 'provider_page';
  if (strpos($key, 'header_') === 0 || in_array($key, ['logo_alt','detecting_location','header_licenses'], true)) {
    $ctx = 'header';
  } elseif (strpos($key, 'footer_') === 0) {
    $ctx = 'footer';
  } elseif (strpos($key, 'error_') === 0) {
    $ctx = 'ui_messages';
  }
  $translations[$key] = getTranslation($key, $languageCode, $ctx);
}

/* Idiomas para seletor (usado pelo header/footer) */
$translations['languageOptionsForDisplay'] = LANGUAGE_CONFIG['name_map'] ?? [];
$translations['current_language_display_name'] = $translations['languageOptionsForDisplay'][$languageCode]
  ?? getTranslation('language_label', $languageCode, 'default');

/* Título/descrição para o <head> (head.php usa essas variáveis) */
$display_name = trim((string)($provider['display_name'] ?? ''));
$city_name    = trim((string)($provider['ad_city'] ?? ''));
$page_title   = $e(trim($display_name . ' ' . ($translations['in_connector'] ?? '') . ' ' . $city_name));

$desc_src = (string)($provider['description'] ?? '');
if (function_exists('mb_substr') && function_exists('mb_strlen')) {
  $meta_description = $e(mb_substr($desc_src, 0, 160)) . (mb_strlen($desc_src) > 160 ? '…' : '');
} else {
  $meta_description = $e(substr($desc_src, 0, 160)) . (strlen($desc_src) > 160 ? '…' : '');
}

/* Galeria de fotos */
$photo_gallery = [];
if (!empty($provider['main_photo_url'])) {
  $photo_gallery[] = rtrim(SITE_URL, '/') . $provider['main_photo_url'];
}
$additional_photos = json_decode($provider['gallery_photos'] ?? '[]', true);
if (json_last_error() === JSON_ERROR_NONE && is_array($additional_photos)) {
  foreach ($additional_photos as $rel) {
    $full = rtrim(SITE_URL, '/') . (string)$rel;
    if (!in_array($full, $photo_gallery, true)) $photo_gallery[] = $full;
  }
}

/* Vídeos (array de URLs ou objetos {url|src}) */
$videos = json_decode($provider['videos'] ?? '[]', true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($videos)) $videos = [];

/* Idiomas falados */
$languages_spoken = json_decode($provider['languages_spoken'] ?? '[]', true) ?: [];

/* Gênero exibido */
$gender_display = $translations['gender_not_informed'] ?? '';
if (($provider['gender'] ?? '') === 'female') $gender_display = $translations['gender_female'] ?? '';
if (($provider['gender'] ?? '') === 'male')   $gender_display = $translations['gender_male'] ?? '';
if (($provider['gender'] ?? '') === 'trans')  $gender_display = $translations['gender_trans'] ?? '';

/* Atributos físicos */
$height_cm = $provider['body_height_cm'] ?? null;
$weight_kg = $provider['body_weight_kg'] ?? null;
$hair_color= $provider['body_hair_color'] ?? null;
$eye_color = $provider['body_eye_color']  ?? null;
$body_type = $provider['body_type'] ?? null;
$bust_cm   = $provider['body_bust_cm']  ?? null;
/* Alguns esquemas salvam em pb.foot_size; outros em p.foot_size. */
$foot_size = $provider['body_foot_size'] ?? $provider['foot_size'] ?? null;
$tattoos   = isset($provider['body_tattoos']) ? (int)$provider['body_tattoos'] : null;
$piercings = isset($provider['body_piercings']) ? (int)$provider['body_piercings'] : null;

/* Contatos */
$phone_code     = (string)($provider['contact_phone_code'] ?? ($provider['phone_code'] ?? ''));
$phone_number   = (string)($provider['contact_phone_number'] ?? ($provider['phone_number'] ?? ''));
$instagram      = trim((string)($provider['contact_instagram'] ?? ''));
$twitter        = trim((string)($provider['contact_twitter'] ?? ''));
$telegram       = trim((string)($provider['contact_telegram'] ?? ''));
$accept_whatsapp= (int)($provider['contact_accepts_whatsapp'] ?? 0);
$accept_calls   = (int)($provider['contact_accepts_calls']   ?? 0);
$accept_sms     = (int)($provider['contact_accepts_sms']     ?? 0);
/* Corrige bug: usar Telegram se houver username/handle, não dependemos de flag inexistente */
$has_telegram   = $telegram !== '';

/* Moeda */
$currency = (string)($provider['currency'] ?? '');

/* Normaliza telefone para E.164 (+351XXXXXXXXX) */
$toE164 = static function (string $code, string $number): ?string {
  $digits = preg_replace('/\D+/', '', $code . $number);
  if ($digits === '' || $digits === null) return null;
  $digits = ltrim($digits, '0'); // evita 00 prefix
  return '+' . $digits;
};
$tel_e164 = $toE164($phone_code, $phone_number);

/* Renderização do cabeçalho */
require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';
?>
<main class="provider-profile-main">
  <div class="profile-header-block">
    <h1>
      <?= $e($display_name) ?>
      <?php if (!empty($provider['is_verified'])): ?>
        <i class="fas fa-check-circle verified-check" title="<?= $e($translations['verified_profile'] ?? '') ?>" aria-hidden="true"></i>
      <?php endif; ?>
    </h1>

    <p class="profile-meta-line">
      <?= $e((string)($provider['age'] ?? '')) ?> <?= $e($translations['years_old'] ?? '') ?>
      <span class="meta-separator" aria-hidden="true">|</span>
      <?= $e($provider['nationality_female'] ?? $provider['nationality_name'] ?? '') ?>
      <span class="meta-separator" aria-hidden="true">|</span>
      <span id="distance-info"
            data-lat="<?= $e((string)($provider['prov_latitude']  ?? '')) ?>"
            data-lon="<?= $e((string)($provider['prov_longitude'] ?? '')) ?>"
            data-city="<?= $e($session_city) ?>"
            data-user-lat="<?= $e((string)$session_lat) ?>"
            data-user-lon="<?= $e((string)$session_lon) ?>"
            data-km-unit="<?= $e($translations['km_unit'] ?? 'km') ?>"
            aria-live="polite">
        <?= $e($translations['distance_not_calculated'] ?? '') ?>
      </span>
    </p>

    <p class="profile-meta-line secondary">
      <?= $e($provider['category_name'] ?? '') ?>
      <span class="meta-separator" aria-hidden="true">|</span>
      <?= $e($gender_display) ?>
      <span class="meta-separator" aria-hidden="true">|</span>
      <?= $e(($translations['you_are_in'] ?? '') . ' ' . $session_city) ?>
    </p>
  </div>

  <hr class="profile-divider">

  <?php if (!empty($photo_gallery)): ?>
    <div class="media-gallery" role="list">
      <?php foreach ($photo_gallery as $photo_url): ?>
        <a href="<?= $e($photo_url) ?>" data-lightbox="profile-gallery" role="listitem">
          <img src="<?= $e($photo_url) ?>" alt="<?= $e(($translations['photo_of'] ?? 'Photo of') . ' ' . $display_name) ?>">
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="no-photos-placeholder" role="status" aria-live="polite">
      <p><?= $e($translations['no_photos_available'] ?? '') ?></p>
    </div>
  <?php endif; ?>

  <div class="profile-content-blocks">
    <section class="profile-section">
      <h2><?= $e($provider['ad_title'] ?? '') ?></h2>
      <p><?= nl2br($e($provider['description'] ?? '')) ?></p>
    </section>

    <section class="profile-section">
      <h2><?= $e($translations['details_title'] ?? '') ?></h2>
      <div class="details-grid">
        <?php if ($height_cm): ?><div class="detail-item"><strong><?= $e($translations['height_label'] ?? '') ?>:</strong> <?= $e((string)$height_cm) ?> cm</div><?php endif; ?>
        <?php if ($weight_kg): ?><div class="detail-item"><strong><?= $e($translations['weight_label'] ?? '') ?>:</strong> <?= $e((string)$weight_kg) ?> kg</div><?php endif; ?>
        <?php if ($hair_color): ?><div class="detail-item"><strong><?= $e($translations['hair_color_label'] ?? '') ?>:</strong> <?= $e((string)$hair_color) ?></div><?php endif; ?>
        <?php if ($eye_color): ?><div class="detail-item"><strong><?= $e($translations['eye_color_label'] ?? '') ?>:</strong> <?= $e((string)$eye_color) ?></div><?php endif; ?>
        <?php if (!empty($provider['ethnicity'])): ?><div class="detail-item"><strong><?= $e($translations['ethnicity_label'] ?? '') ?>:</strong> <?= $e((string)$provider['ethnicity']) ?></div><?php endif; ?>
        <?php if ($body_type): ?><div class="detail-item"><strong><?= $e($translations['body_type_label'] ?? '') ?>:</strong> <?= $e((string)$body_type) ?></div><?php endif; ?>
        <?php if ($bust_cm): ?><div class="detail-item"><strong><?= $e($translations['bust_size_label'] ?? '') ?>:</strong> <?= $e((string)$bust_cm) ?> cm</div><?php endif; ?>
        <?php if (!empty($provider['sexual_orientation'])): ?><div class="detail-item"><strong><?= $e($translations['sexual_orientation_label'] ?? '') ?>:</strong> <?= $e((string)$provider['sexual_orientation']) ?></div><?php endif; ?>
        <?php if (!empty($provider['pubic_hair'])): ?><div class="detail-item"><strong><?= $e($translations['pubic_hair_label'] ?? '') ?>:</strong> <?= $e((string)$provider['pubic_hair']) ?></div><?php endif; ?>
        <?php if ($foot_size): ?><div class="detail-item"><strong><?= $e($translations['foot_size_label'] ?? '') ?>:</strong> <?= $e((string)$foot_size) ?></div><?php endif; ?>
        <?php if ($tattoos !== null): ?><div class="detail-item"><strong><?= $e($tattoos ? ($translations['tattoos_yes'] ?? 'Tattoos: yes') : ($translations['tattoos_no'] ?? 'Tattoos: no')) ?></strong></div><?php endif; ?>
        <?php if ($piercings !== null): ?><div class="detail-item"><strong><?= $e($piercings ? ($translations['piercings_yes'] ?? 'Piercings: yes') : ($translations['piercings_no'] ?? 'Piercings: no')) ?></strong></div><?php endif; ?>
      </div>
    </section>

    <?php if (!empty($services_offered)): ?>
      <section class="profile-section">
        <h2><?= $e($translations['services_offered_title'] ?? '') ?></h2>
        <div class="service-list">
          <?php foreach ($services_offered as $service): ?>
            <?php
              $svc_name = getTranslation($service['service_key'], $languageCode, 'services') ?: $service['service_key'];
              $svc_status = $service['status'] ?? '';
              $svc_price  = $service['price'] ?? null;
              $badge = '';
              if ($svc_status === 'included')   $badge = $translations['service_status_included']  ?? '';
              if ($svc_status === 'negotiable') $badge = $translations['service_status_negotiable'] ?? '';
            ?>
            <div class="service-item">
              <span class="service-name"><?= $e((string)$svc_name); ?></span>
              <?php if ($svc_status === 'extra' && $svc_price !== null && $currency): ?>
                <span class="service-status extra-fee">+ <?= $e(sprintf('%s %s', $svc_price, $currency)); ?></span>
              <?php elseif ($badge !== ''): ?>
                <span class="service-status <?= $e($svc_status); ?>"><?= $e($badge); ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <section class="profile-section">
      <h2><?= $e(($translations['prices_title'] ?? '') . ($currency ? " ({$currency})" : '')) ?></h2>
      <div class="details-grid">
        <?php if (!empty($provider['base_hourly_rate'])): ?><div class="detail-item"><strong><?= $e($translations['hour_price'] ?? '') ?>:</strong> <?= $e((string)$provider['base_hourly_rate']) ?></div><?php endif; ?>
        <?php if (!empty($provider['price_15_min'])):   ?><div class="detail-item"><strong><?= $e($translations['15_min_price'] ?? '') ?>:</strong> <?= $e((string)$provider['price_15_min']) ?></div><?php endif; ?>
        <?php if (!empty($provider['price_30_min'])):   ?><div class="detail-item"><strong><?= $e($translations['30_min_price'] ?? '') ?>:</strong> <?= $e((string)$provider['price_30_min']) ?></div><?php endif; ?>
        <?php if (!empty($provider['price_2_hr'])):     ?><div class="detail-item"><strong><?= $e($translations['2_hour_price'] ?? '') ?>:</strong> <?= $e((string)$provider['price_2_hr']) ?></div><?php endif; ?>
        <?php if (!empty($provider['price_overnight'])):?><div class="detail-item"><strong><?= $e($translations['overnight_price'] ?? '') ?>:</strong> <?= $e((string)$provider['price_overnight']) ?></div><?php endif; ?>
      </div>
    </section>

    <?php if ($instagram || $twitter || $has_telegram): ?>
      <section class="profile-section">
        <h2><?= $e($translations['social_media_title'] ?? '') ?></h2>
        <ul class="social-links">
          <?php if ($instagram): ?>
            <?php $insta = preg_replace('~^@~', '', $instagram); ?>
            <li>
              <a href="https://instagram.com/<?= $e($insta); ?>" target="_blank" rel="noopener noreferrer">
                <i class="fab fa-instagram" aria-hidden="true"></i> <?= $e($translations['instagram_label'] ?? 'Instagram'); ?>
              </a>
            </li>
          <?php endif; ?>
          <?php if ($twitter): ?>
            <?php $tw = preg_replace('~^@~', '', $twitter); ?>
            <li>
              <a href="https://twitter.com/<?= $e($tw); ?>" target="_blank" rel="noopener noreferrer">
                <i class="fab fa-twitter" aria-hidden="true"></i> <?= $e($translations['twitter_label'] ?? 'Twitter'); ?>
              </a>
            </li>
          <?php endif; ?>
          <?php if ($has_telegram): ?>
            <?php $tg = preg_replace('~^@~', '', $telegram); ?>
            <li>
              <a href="https://t.me/<?= $e($tg); ?>" target="_blank" rel="noopener noreferrer">
                <i class="fab fa-telegram-plane" aria-hidden="true"></i> <?= $e($translations['telegram_button'] ?? 'Telegram'); ?>
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if (!empty($videos)): ?>
      <section class="profile-section">
        <h2><?= $e($translations['videos_title'] ?? '') ?></h2>
        <div class="video-grid">
          <?php foreach ($videos as $vid): ?>
            <?php
              $vsrc = '';
              if (is_string($vid)) $vsrc = $vid;
              elseif (is_array($vid)) { $vsrc = (string)($vid['url'] ?? $vid['src'] ?? ''); }
              if ($vsrc === '') continue;
            ?>
            <video controls preload="metadata">
              <source src="<?= $e($vsrc); ?>">
              <?= $e($translations['video_not_supported'] ?? 'Seu navegador não suporta vídeo.'); ?>
            </video>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

  </div>
</main>

<nav class="sticky-contact-bar" aria-label="Contact options">
  <?php if ($accept_whatsapp && $tel_e164): ?>
    <a href="https://wa.me/<?= $e(ltrim($tel_e164, '+')); ?>" target="_blank" rel="noopener noreferrer" class="btn-contact whatsapp-btn">
      <i class="fab fa-whatsapp" aria-hidden="true"></i> <span><?= $e($translations['whatsapp_button'] ?? 'WhatsApp'); ?></span>
    </a>
  <?php endif; ?>

  <?php if ($has_telegram): ?>
    <?php $tg = preg_replace('~^@~', '', $telegram); ?>
    <a href="https://t.me/<?= $e($tg); ?>" target="_blank" rel="noopener noreferrer" class="btn-contact telegram-btn">
      <i class="fab fa-telegram-plane" aria-hidden="true"></i> <span><?= $e($translations['telegram_button'] ?? 'Telegram'); ?></span>
    </a>
  <?php endif; ?>

  <?php if ($accept_calls && $tel_e164): ?>
    <a href="tel:<?= $e($tel_e164); ?>" class="btn-contact call-btn">
      <i class="fas fa-phone" aria-hidden="true"></i> <span><?= $e($translations['call_button'] ?? 'Ligar'); ?></span>
    </a>
  <?php endif; ?>

  <?php if ($accept_sms && $tel_e164): ?>
    <a href="sms:<?= $e($tel_e164); ?>" class="btn-contact sms-btn">
      <i class="fas fa-comment-dots" aria-hidden="true"></i> <span><?= $e($translations['sms_button'] ?? 'SMS'); ?></span>
    </a>
  <?php endif; ?>
</nav>

<?php
/* JSON-LD opcional (Person + localização/URL) */
try {
  $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
  $sameAs = [];
  if ($instagram) $sameAs[] = 'https://instagram.com/' . preg_replace('~^@~', '', $instagram);
  if ($twitter)   $sameAs[] = 'https://twitter.com/'   . preg_replace('~^@~', '', $twitter);
  if ($has_telegram) $sameAs[] = 'https://t.me/'       . preg_replace('~^@~', '', $telegram);

  $schema = [
    '@context' => 'https://schema.org',
    '@type'    => 'Person',
    'name'     => $display_name,
    'url'      => rtrim(SITE_URL, '/') . '/' . $slug,
  ];
  if (!empty($photo_gallery)) { $schema['image'] = $photo_gallery[0]; }
  if (!empty($provider['prov_latitude']) && !empty($provider['prov_longitude'])) {
    $schema['geo'] = [
      '@type' => 'GeoCoordinates',
      'latitude'  => (float)$provider['prov_latitude'],
      'longitude' => (float)$provider['prov_longitude'],
    ];
  }
  if (!empty($provider['ad_city']) || !empty($provider['ad_country'])) {
    $schema['address'] = [
      '@type' => 'PostalAddress',
      'addressLocality' => (string)($provider['ad_city'] ?? ''),
      'addressCountry'  => (string)($provider['ad_country'] ?? ''),
    ];
  }
  if (!empty($languages_spoken)) { $schema['knowsLanguage'] = array_values($languages_spoken); }
  if (!empty($sameAs)) { $schema['sameAs'] = $sameAs; }

  echo '<script type="application/ld+json' . ($csp_nonce ? '" nonce="'.$e($csp_nonce) : '"') . '">' . json_encode($schema, $jsonFlags) . '</script>';
} catch (Throwable $e_jsld) {
  // silencia erros de schema
}
?>

<script>
/* Script de distância e interações leves (visível mas sem texto) — mantido */
</script>

<?php
require_once TEMPLATE_PATH . 'footer.php';
