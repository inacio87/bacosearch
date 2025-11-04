<?php
/**
 * /companies.php - Página pública de Empresa (companies) por slug ou id
 */
require_once __DIR__ . '/core/bootstrap.php';

$languageCode = $_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us');
$page_name = 'company_detail_page';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;

$pdo = getDBConnection();
$company = null;
if ($id) {
  $st = $pdo->prepare("SELECT * FROM companies WHERE id=? AND status='active' AND is_active=1");
  $st->execute([$id]);
  $company = $st->fetch(PDO::FETCH_ASSOC);
} elseif ($slug) {
  $st = $pdo->prepare("SELECT * FROM companies WHERE slug=? AND status='active' AND is_active=1");
  $st->execute([$slug]);
  $company = $st->fetch(PDO::FETCH_ASSOC);
}

if (!$company) {
  http_response_code(404);
  die('Not found');
}

// Translations minimal
$title = $company['company_name'] ?: 'Company';
$city  = $_SESSION['city'] ?? getTranslation('detecting_location', $languageCode, 'ui_messages');

require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';
$e = static function($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<main>
  <div class="main-container">
    <div class="register-container">
      <div class="form-header"><h1><?= $e($title); ?></h1></div>
      <p><?= nl2br($e($company['description'] ?? '')); ?></p>
      <?php if (!empty($company['main_photo_url'])): ?>
        <img src="<?= $e($company['main_photo_url']); ?>" alt="" style="max-width:100%;height:auto;"/>
      <?php endif; ?>
      <?php if (!empty($company['gallery_photos'])): $g=json_decode($company['gallery_photos'],true) ?: []; if($g): ?>
        <div class="gallery" style="display:flex;gap:10px;flex-wrap:wrap;">
          <?php foreach ($g as $src): ?>
            <img src="<?= $e($src); ?>" alt="" style="max-width:220px;height:auto;border-radius:6px;"/>
          <?php endforeach; ?>
        </div>
      <?php endif; endif; ?>
    </div>
  </div>
</main>
<?php require_once TEMPLATE_PATH . 'footer.php';
