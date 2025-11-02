<?php
/**
 * /templates/search-results.php
 * Template 100% traduzido e padronizado para exibir os resultados da busca.
 *
 * ÚLTIMA ATUALIZAÇÃO: 15/08/2025
 */

if (!defined('TEMPLATE_PATH')) { exit; }

// Inclui o cabeçalho padrão do site
include TEMPLATE_PATH . 'head.php';
include TEMPLATE_PATH . 'header.php';

/* Helpers e fallbacks */
$e = static function (?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

$language_code  = $language_code ?? ($_SESSION['language'] ?? 'pt-br');
$term           = isset($term) ? (string)$term : '';
$totalResults   = isset($totalResults) ? (int)$totalResults : 0;
$currentPage    = isset($currentPage) ? max(1, (int)$currentPage) : 1;
$totalPages     = isset($totalPages) ? max(1, (int)$totalPages) : 1;
$results        = (isset($results) && is_array($results)) ? $results : [];

/* Traduções padronizadas */
$t_placeholder     = $translations['search_placeholder']
                  ?? (getTranslation('search_placeholder', $language_code, 'default') ?? 'Pesquisar...');
$t_results_for     = $translations['results_for']
                  ?? (getTranslation('results_for', $language_code, 'search_results') ?? 'Resultados para');
$t_profiles_found  = getTranslation('profiles_found', $language_code, 'search_results') ?? '%d perfis encontrados';
$t_no_results      = $translations['no_results']
                  ?? (getTranslation('no_results_found', $language_code, 'search_results') ?? 'Nenhum resultado encontrado.');
$t_suggestion      = $translations['explore_suggestion']
                  ?? (getTranslation('refine_search_suggestion', $language_code, 'search_results') ?? 'Tente refinar sua busca.');
$t_unit_km         = getTranslation('unit_km', $language_code, 'units') ?? 'km';

/* URLs */
$searchAction = rtrim(SITE_URL ?? '', '/') . '/search.php';
$providerUrl = static function ($id): string {
  $id = is_numeric($id) ? (string)(int)$id : rawurlencode((string)$id);
  return rtrim(SITE_URL ?? '', '/') . '/provider_profile.php?id=' . $id;
};
$paginationUrl = static function (string $term, int $page) use ($searchAction): string {
  return $searchAction . '?' . http_build_query(['term' => $term, 'page' => $page]);
};

/* Snippet seguro (multibyte) */
$snippet = static function (?string $text, int $max = 150): string {
  $text = (string)($text ?? '');
  if ($text === '') return '';
  if (function_exists('mb_strlen') && function_exists('mb_substr')) {
    return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '…' : $text;
  }
  return (strlen($text) > $max) ? substr($text, 0, $max) . '…' : $text;
};

/* Janela de paginação */
$window = 2;
$start  = max(1, $currentPage - $window);
$end    = min($totalPages, $currentPage + $window);
if ($end - $start < $window * 2) {
  if ($start === 1) { $end   = min($totalPages, $start + $window*2); }
  if ($end === $totalPages) { $start = max(1, $end - $window*2); }
}

/* CSP nonce (se disponível, para JSON-LD/tracking) */
$csp_nonce = $_SESSION['csp_nonce'] ?? null;
?>
<main class="main-content search-results-page">
  <div class="search-bar">
    <form method="GET" action="<?php echo $e($searchAction); ?>" class="google-style-search" role="search" aria-label="<?php echo $e($t_placeholder); ?>">
      <label class="visually-hidden" for="searchInput"><?php echo $e($t_placeholder); ?></label>
      <input
        type="text"
        name="term"
        id="searchInput"
        value="<?php echo $e($term); ?>"
        placeholder="<?php echo $e($t_placeholder); ?>"
        aria-label="<?php echo $e($t_placeholder); ?>"
        autocomplete="on"
      >
      <button type="submit" class="search-button" aria-label="<?php echo $e($t_placeholder); ?>">
        <i class="fas fa-search" aria-hidden="true"></i>
      </button>
    </form>
  </div>

  <div class="search-results" aria-live="polite">
    <h1 class="results-title"><?php echo $e($t_results_for); ?> “<?php echo $e($term); ?>”</h1>

    <p class="results-count"><?php echo $e(sprintf($t_profiles_found, $totalResults)); ?></p>

    <?php if (!empty($results)): ?>
      <div class="results-list" role="list">
        <?php foreach ($results as $provider):
          $id     = $provider['id'] ?? '';
          $name   = $provider['display_name'] ?? ($provider['artistic_name'] ?? '');
          $desc   = (string)($provider['description'] ?? '');
          $ad     = (string)($provider['ad_title'] ?? '');
          $text   = $desc !== '' ? $desc : $ad;
          $snip   = $snippet($text, 150);
          $dist   = (isset($provider['distance']) && is_numeric($provider['distance'])) ? (float)$provider['distance'] : null;
          $city   = trim((string)($provider['city'] ?? ''));
          $country= trim((string)($provider['country'] ?? ''));
          $loc    = trim($city . ($city && $country ? ', ' : '') . $country);
          $url    = $providerUrl($id);
        ?>
          <article class="result-item" role="listitem">
            <a href="<?php echo $e($url); ?>" class="result-title"><?php echo $e($name); ?></a>
            <?php if ($snip !== ''): ?>
              <p class="result-snippet"><?php echo $e($snip); ?></p>
            <?php endif; ?>
            <div class="result-meta">
              <?php if ($dist !== null): ?>
                <span class="distance"><?php echo (int)round($dist); ?> <?php echo $e($t_unit_km); ?></span>
              <?php endif; ?>
              <?php if ($loc !== ''): ?>
                <span class="location"><?php echo $e($loc); ?></span>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Paginação de resultados">
          <?php if ($currentPage > 1): ?>
            <a class="first" href="<?php echo $e($paginationUrl($term, 1)); ?>" aria-label="Primeira página">«</a>
            <a class="prev"  href="<?php echo $e($paginationUrl($term, $currentPage - 1)); ?>" aria-label="Página anterior">‹</a>
          <?php else: ?>
            <span class="first disabled" aria-hidden="true">«</span>
            <span class="prev disabled" aria-hidden="true">‹</span>
          <?php endif; ?>

          <?php if ($start > 1): ?>
            <a href="<?php echo $e($paginationUrl($term, 1)); ?>">1</a>
            <?php if ($start > 2): ?><span class="ellipsis" aria-hidden="true">…</span><?php endif; ?>
          <?php endif; ?>

          <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i === $currentPage): ?>
              <span class="active" aria-current="page"><?php echo $i; ?></span>
            <?php else: ?>
              <a href="<?php echo $e($paginationUrl($term, $i)); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
          <?php endfor; ?>

          <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?><span class="ellipsis" aria-hidden="true">…</span><?php endif; ?>
            <a href="<?php echo $e($paginationUrl($term, $totalPages)); ?>"><?php echo $totalPages; ?></a>
          <?php endif; ?>

          <?php if ($currentPage < $totalPages): ?>
            <a class="next" href="<?php echo $e($paginationUrl($term, $currentPage + 1)); ?>" aria-label="Próxima página">›</a>
            <a class="last" href="<?php echo $e($paginationUrl($term, $totalPages)); ?>" aria-label="Última página">»</a>
          <?php else: ?>
            <span class="next disabled" aria-hidden="true">›</span>
            <span class="last disabled" aria-hidden="true">»</span>
          <?php endif; ?>
        </nav>
      <?php endif; ?>

      <?php
        /* JSON-LD opcional para ItemList */
        $items = [];
        foreach ($results as $idx => $provider) {
          $pid   = $provider['id'] ?? '';
          $pname = (string)($provider['display_name'] ?? ($provider['artistic_name'] ?? ''));
          $pdesc = (string)(($provider['description'] ?? '') ?: ($provider['ad_title'] ?? ''));
          $items[] = [
            '@type'        => 'ListItem',
            'position'     => $idx + 1 + (($currentPage - 1) * count($results)),
            'url'          => $providerUrl($pid),
            'name'         => $pname,
            'description'  => $snippet($pdesc, 200),
          ];
        }
        if (!empty($items)) {
          $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
          $jsonLD = [
            '@context' => 'https://schema.org',
            '@type'    => 'ItemList',
            'itemListElement' => $items,
            'name'     => $t_results_for . ' ' . $term,
          ];
          echo '<script type="application/ld+json"'.($csp_nonce ? ' nonce="'.$e($csp_nonce).'"' : '').'>'.json_encode($jsonLD, $jsonFlags).'</script>';
        }
      ?>

    <?php else: ?>
      <div class="no-results" role="status" aria-live="polite">
        <p><?php echo $e($t_no_results); ?></p>
        <p><?php echo $e($t_suggestion); ?></p>
      </div>
    <?php endif; ?>
  </div>
</main>

<script <?php echo $csp_nonce ? 'nonce="'.$e($csp_nonce).'"' : ''; ?>>
/* Tracking (sem texto visível) — mantém comportamento seguro se sendTrackingData existir */
document.addEventListener('DOMContentLoaded', function() {
  try {
    if (typeof window.sendTrackingData === 'function') {
      const res = window.sendTrackingData({
        event_type: 'search',
        event_name: 'search_performed',
        term: '<?php echo $e($term); ?>',
        result_count: <?php echo (int)$totalResults; ?>,
        page: <?php echo (int)$currentPage; ?>
      });
      if (res && typeof res.then === 'function') {
        res.then(function(r){ console.log('Tracking OK'); })
           .catch(function(err){ console.error('Tracking error:', err); });
      }
    }
  } catch (err) { console.error(err); }
});
</script>

<?php
// Inclui o rodapé padrão do site
include TEMPLATE_PATH . 'footer.php';
