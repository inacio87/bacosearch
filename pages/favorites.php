<?php
/**
 * /pages/favorites.php - Itens curtidos (Favoritos)
 * Mostra listas de favoritos por vertical a partir da sessão ou DB no futuro.
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';

$page_name = 'favorites_page';
$languageCode = $_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us');

$e = static function($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$pdo = getDBConnection();

$favs = $_SESSION['favorites'] ?? [
  'providers' => [],
  'companies' => [],
  'clubs' => [],
  'services' => [],
];

function fetchByIds(PDO $pdo, string $table, array $ids, array $columns, string $statusCol = 'status', string $activeCol = 'is_active'): array {
  if (empty($ids)) return [];
  $ids = array_values(array_unique(array_map('intval', $ids)));
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $cols = implode(',', $columns);
  $sql = "SELECT {$cols} FROM {$table} WHERE id IN ($in)";
  if (in_array($statusCol, ['status'], true)) { $sql .= " AND status='active'"; }
  if (in_array($activeCol, ['is_active'], true)) { $sql .= " AND is_active=1"; }
  $st = $pdo->prepare($sql);
  $st->execute($ids);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$providers = fetchByIds($pdo, 'providers', $favs['providers'] ?? [], [
  'id','slug','display_name as title','main_photo_url','short_description as description'
], 'status','is_active');
$companies = fetchByIds($pdo, 'companies', $favs['companies'] ?? [], [
  'id','slug','company_name as title','main_photo_url','description'
]);
$clubs = fetchByIds($pdo, 'clubs', $favs['clubs'] ?? [], [
  'id','slug','club_name as title','main_photo_url','description'
]);
$services = fetchByIds($pdo, 'services_listings', $favs['services'] ?? [], [
  'id','slug','service_title as title','main_photo_url','description'
]);

require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';
?>
<main>
  <div class="main-container">
    <div class="register-container">
      <div class="form-header"><h1><?= $e(getTranslation('header_favorites', $languageCode, 'header') ?: 'Favoritos'); ?></h1></div>

      <?php $sections = [
        ['title'=>'Acompanhantes','items'=>$providers,'url'=>'/providers.php'],
        ['title'=>'Empresas','items'=>$companies,'url'=>'/companies.php'],
        ['title'=>'Clubes','items'=>$clubs,'url'=>'/clubs.php'],
        ['title'=>'Serviços','items'=>$services,'url'=>'/services.php'],
      ];
      foreach ($sections as $sec): ?>
        <h2 style="margin-top:20px;"><?= $e($sec['title']); ?></h2>
        <?php if (empty($sec['items'])): ?>
          <p style="opacity:.7;">Nada por aqui ainda.</p>
        <?php else: ?>
          <div class="results-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;">
            <?php foreach ($sec['items'] as $it): $url = SITE_URL . $sec['url'] . '?id=' . (int)$it['id']; ?>
              <a href="<?= $e($url); ?>" class="card" style="display:block;border:1px solid #eee;border-radius:8px;overflow:hidden;text-decoration:none;color:inherit;">
                <?php if (!empty($it['main_photo_url'])): ?>
                  <img src="<?= $e($it['main_photo_url']); ?>" alt="" style="width:100%;height:140px;object-fit:cover;">
                <?php endif; ?>
                <div style="padding:10px;">
                  <strong><?= $e($it['title'] ?? ('#'.$it['id'])); ?></strong>
                  <?php if (!empty($it['description'])): ?>
                    <p style="margin:6px 0 0 0;font-size:.9em;opacity:.8;max-height:3.6em;overflow:hidden;"><?= $e($it['description']); ?></p>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</main>
<?php require_once TEMPLATE_PATH . 'footer.php';
