<?php
/**
 * /api/api_top10.php — Top 10 do Dashboard (BacoSearch)
 *
 * Suporta:
 *   - GET : ?action=top10&metric=<countries|cities|pages|referrers|devices|searches|exits>&period=<today|7d|30d|360d>
 *   - POST:  action=top10, metric=..., range=<today|7d|30d|360d>
 *
 * Tabelas usadas:
 *   page_views(
 *     id, visitor_id, page_url, referrer_url, ip_address, country_code,
 *     device_type, is_bot_view, visit_timestamp
 *   )
 *   visitors(
 *     id, cookie_id, ip_address, user_agent, location_country, city, region, ...,
 *     device_type, is_bot, last_seen_at, ...
 *   )
 *   global_searches(
 *     id, term, visitor_id, ip_address, results_count, created_at, metadata
 *   )
 *   countries(
 *     id, name, iso_code, calling_code, nationality, flag_url, language_code,
 *     currencies, currencies_icon, created_at, updated_at
 *   )
 *
 * Última atualização: 13/08/2025
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');

function api_response(bool $ok, $data = null, ?string $msg = null, int $code = 200): void {
  http_response_code($code);
  echo json_encode(['success' => $ok, 'data' => $data, 'message' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

/** Filtra os binds para conter somente placeholders existentes no SQL (evita HY093). */
function filter_binds(string $sql, array $binds): array {
  preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $m);
  $needed = array_unique($m[1] ?? []);
  $out = [];
  foreach ($needed as $k) {
    $ph = ':' . $k;
    if (array_key_exists($ph, $binds)) $out[$ph] = $binds[$ph];
  }
  return $out;
}

try {
  /** @var PDO $pdo */
  $pdo = getDBConnection();

  // --- parâmetros (compatível GET e POST) ---
  $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
  $p = $method === 'POST' ? $_POST : $_GET;

  $action = $p['action'] ?? 'top10';
  if ($action !== 'top10') {
    api_response(false, null, 'Invalid action', 400);
  }

  $metric = $p['metric'] ?? ($p['m'] ?? '');
  $allowed = ['countries','cities','pages','referrers','devices','searches','exits'];
  if (!in_array($metric, $allowed, true)) {
    api_response(false, null, 'Invalid metric', 400);
  }

  // GET usa ?period ; POST usa range
  $period = $p['period'] ?? ($p['range'] ?? '7d');

  $now = new DateTimeImmutable('now');
  switch ($period) {
    case 'today':
      $start = $now->setTime(0,0,0);
      break;
    case '30d':
      $start = $now->sub(new DateInterval('P30D'));
      break;
    case '360d':
      $start = $now->sub(new DateInterval('P360D'));
      break;
    case '7d':
    default:
      $start = $now->sub(new DateInterval('P7D'));
      break;
  }
  $end = $now;

  // Host do site para classificar referrers internos
  $siteHost = 'bacosearch.com';
  if (defined('SITE_URL')) {
    $host = parse_url(SITE_URL, PHP_URL_HOST);
    if (is_string($host) && $host !== '') $siteHost = $host;
  }

  $baseBinds = [
    ':start_at' => $start->format('Y-m-d H:i:s'),
    ':end_at'   => $end->format('Y-m-d H:i:s'),
    ':siteHost' => '%' . $siteHost . '%',
  ];

  // Helper: executa e normaliza saída para {label, count}
  $run = function(string $sql, array $binds) use ($pdo) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute(filter_binds($sql, $binds));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
      $out[] = [
        'label' => (string)($r['label'] ?? '(desconhecido)'),
        'count' => (int)   ($r['count'] ?? $r['cnt'] ?? 0),
      ];
    }
    return $out;
  };

  // -----------------------------
  // Métricas (uma por request)
  // -----------------------------
  switch ($metric) {
    case 'pages': {
      // Top páginas (base URL) por visitantes únicos
      $sql = "
        SELECT
          COALESCE(NULLIF(SUBSTRING_INDEX(pv.page_url,'?',1),''),'(desconhecida)') AS label,
          COUNT(DISTINCT COALESCE(pv.visitor_id, pv.ip_address)) AS count
        FROM page_views pv
        WHERE pv.visit_timestamp BETWEEN :start_at AND :end_at
          AND pv.is_bot_view = 0
          AND pv.page_url IS NOT NULL AND pv.page_url <> ''
        GROUP BY label
        ORDER BY count DESC
        LIMIT 10
      ";
      $data = $run($sql, $baseBinds);
      api_response(true, $data);
    }

    case 'referrers': {
      // Classificação de referrers (direto, interno, host externo)
      $sql = "
        SELECT
          CASE
            WHEN pv.referrer_url IS NULL OR pv.referrer_url = '' OR LOWER(pv.referrer_url)='direct' THEN 'Tráfego Direto'
            WHEN pv.referrer_url LIKE :siteHost THEN 'Navegação Interna'
            WHEN pv.referrer_url LIKE 'http%' THEN
              SUBSTRING_INDEX(REPLACE(REPLACE(pv.referrer_url,'https://',''),'http://',''), '/', 1)
            ELSE 'Outro'
          END AS label,
          COUNT(DISTINCT COALESCE(pv.visitor_id, pv.ip_address)) AS count
        FROM page_views pv
        WHERE pv.visit_timestamp BETWEEN :start_at AND :end_at
          AND pv.is_bot_view = 0
        GROUP BY label
        ORDER BY count DESC
        LIMIT 10
      ";
      $data = $run($sql, $baseBinds);
      api_response(true, $data);
    }

    case 'devices': {
      // Dispositivos por visitantes únicos
      $sql = "
        SELECT
          COALESCE(NULLIF(pv.device_type,''),'unknown') AS label,
          COUNT(DISTINCT COALESCE(pv.visitor_id, pv.ip_address)) AS count
        FROM page_views pv
        WHERE pv.visit_timestamp BETWEEN :start_at AND :end_at
          AND pv.is_bot_view = 0
        GROUP BY label
        ORDER BY count DESC
        LIMIT 10
      ";
      $data = $run($sql, $baseBinds);
      api_response(true, $data);
    }

    case 'countries': {
      // Países por visitantes únicos — usa nome completo do país (JOIN countries)
      $sql = "
        SELECT
          COALESCE(NULLIF(c.name,''), COALESCE(NULLIF(pv.country_code,''),'(desconhecido)')) AS label,
          COUNT(DISTINCT COALESCE(pv.visitor_id, pv.ip_address)) AS count
        FROM page_views pv
        LEFT JOIN countries c
          ON UPPER(c.iso_code) = UPPER(pv.country_code)
        WHERE pv.visit_timestamp BETWEEN :start_at AND :end_at
          AND pv.is_bot_view = 0
        GROUP BY label
        ORDER BY count DESC
        LIMIT 10
      ";
      $data = $run($sql, $baseBinds);
      api_response(true, $data);
    }

    case 'cities': {
      // Cidades via visitors.city, vinculadas às views do período
      $sql = "
        SELECT
          COALESCE(NULLIF(v.city,''),'(desconhecida)') AS label,
          COUNT(DISTINCT COALESCE(pv.visitor_id, pv.ip_address)) AS count
        FROM page_views pv
        JOIN visitors v ON v.id = pv.visitor_id
        WHERE pv.visit_timestamp BETWEEN :start_at AND :end_at
          AND pv.is_bot_view = 0
        GROUP BY label
        ORDER BY count DESC
        LIMIT 10
      ";
      $data = $run($sql, $baseBinds);
      api_response(true, $data);
    }

    case 'searches': {
      // Termos mais buscados (volume). Se quiser únicos por visitante, troque COUNT(*) por COUNT(DISTINCT COALESCE(gs.visitor_id, gs.ip_address))
      $sql = "
        SELECT
          TRIM(gs.term) AS label,
          COUNT(*) AS count
        FROM global_searches gs
        WHERE gs.created_at BETWEEN :start_at AND :end_at
          AND gs.term IS NOT NULL AND gs.term <> ''
        GROUP BY label
        ORDER BY count DESC
        LIMIT 10
      ";
      $data = $run($sql, $baseBinds);
      api_response(true, $data);
    }

    case 'exits': {
      // Última página por visitante no período (exit).
      // Tenta MySQL 8 (window function); se falhar, cai no fallback 5.7.
      $sql8 = "
        WITH last_hits AS (
          SELECT
            pv.visitor_id,
            COALESCE(NULLIF(SUBSTRING_INDEX(pv.page_url,'?',1),''),'(desconhecida)') AS page_base,
            pv.visit_timestamp,
            ROW_NUMBER() OVER (PARTITION BY pv.visitor_id ORDER BY pv.visit_timestamp DESC) AS rn
          FROM page_views pv
          WHERE pv.visit_timestamp BETWEEN :start_at AND :end_at
            AND pv.is_bot_view = 0
            AND pv.page_url IS NOT NULL AND pv.page_url <> ''
        )
        SELECT page_base AS label, COUNT(*) AS count
        FROM last_hits
        WHERE rn = 1
        GROUP BY page_base
        ORDER BY count DESC
        LIMIT 10
      ";

      try {
        $data = $run($sql8, $baseBinds);
        api_response(true, $data);
      } catch (Throwable $e) {
        // Fallback compatível com MySQL 5.7 (sem window functions).
        // Observação: este fallback considera apenas visitantes com visitor_id não nulo.
        $sql57 = "
          SELECT
            COALESCE(NULLIF(SUBSTRING_INDEX(pv.page_url,'?',1),''),'(desconhecida)') AS label,
            COUNT(*) AS count
          FROM page_views pv
          JOIN (
            SELECT pv2.visitor_id, MAX(pv2.visit_timestamp) AS max_ts
            FROM page_views pv2
            WHERE pv2.visit_timestamp BETWEEN :start_at AND :end_at
              AND pv2.is_bot_view = 0
            GROUP BY pv2.visitor_id
          ) last ON last.visitor_id = pv.visitor_id AND last.max_ts = pv.visit_timestamp
          WHERE pv.page_url IS NOT NULL AND pv.page_url <> ''
          GROUP BY label
          ORDER BY count DESC
          LIMIT 10
        ";
        $data = $run($sql57, $baseBinds);
        api_response(true, $data);
      }
    }
  }

  // Se chegou aqui, algo deu errado no switch
  api_response(false, null, 'Unknown error', 500);

} catch (Throwable $e) {
  log_system_error("API Top10 error: " . $e->getMessage(), 'error', 'api_top10');
  api_response(false, null, 'Ocorreu um erro ao consultar os dados.', 500);
}
