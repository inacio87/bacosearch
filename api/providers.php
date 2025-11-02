<?php
/**
 * /api/providers.php (compatível com schema modular)
 * - POST: get_countries / get_regions / get_cities (conta a partir de providers_logistics)
 * - GET: listagem com fallback CITY→REGION→COUNTRY→GLOBAL
 * - SQL dinâmico: só seleciona/filtra por colunas que existem nas tabelas
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');

$pdo = getDBConnection();

/* ---------------- Utils ---------------- */
function api_respond(bool $ok, $data = null, ?string $msg = null, int $code = 200): void {
  http_response_code($code);
  echo json_encode(['success'=>$ok,'data'=>$data,'message'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}
function s($v): string { return is_string($v) ? trim($v) : ''; }

/** cache de colunas por tabela */
function table_cols(PDO $pdo, string $table): array {
  static $cache = [];
  if (isset($cache[$table])) return $cache[$table];
  $stmt = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
  ");
  $stmt->execute([$table]);
  $cache[$table] = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
  return $cache[$table];
}
function has_col(PDO $pdo, string $table, string $col): bool {
  $cols = table_cols($pdo, $table);
  return isset($cols[$col]);
}
/** COALESCE dinâmico: só usa colunas existentes, senão NULL */
function coalesce_cols(PDO $pdo, string $table, string $alias, array $cands): string {
  $present = [];
  foreach ($cands as $c) if (has_col($pdo, $table, $c)) $present[] = "$alias.$c";
  return $present ? ('COALESCE('.implode(',', $present).')') : 'NULL';
}

/** WHERE base dinâmico: só aplica se a coluna existir */
function build_where_base(PDO $pdo): string {
  $w = [];
  if (has_col($pdo, 'providers', 'is_active'))   $w[] = "p.is_active = 1";
  if (has_col($pdo, 'providers', 'status'))      $w[] = "p.status = 'active'";
  if (has_col($pdo, 'providers', 'deleted_at'))  $w[] = "p.deleted_at IS NULL";
  return $w ? implode(' AND ', $w) : '1=1';
}

/** ORDER BY dinâmico */
function build_order(PDO $pdo): string {
  $order = [];
  if (has_col($pdo, 'providers', 'spotlight_level')) $order[] = "p.spotlight_level DESC";
  if (has_col($pdo, 'providers', 'updated_at'))      $order[] = "p.updated_at DESC";
  $order[] = "p.id DESC";
  return ' ORDER BY '.implode(', ', $order).' LIMIT 60';
}

/** Resolve nomes/colunas para region/country/city em logistics */
function logistics_cols(PDO $pdo): array {
  $has = fn($c) => has_col($pdo, 'providers_logistics', $c);
  return [
    'country' => $has('ad_country') ? 'ad_country' : ($has('country_code') ? 'country_code' : null),
    'region'  => $has('ad_region')  ? 'ad_region'  : ($has('region_name') ? 'region_name' : null),
    'city'    => $has('ad_city')    ? 'ad_city'    : ($has('city_name')   ? 'city_name'   : null),
    'lat'     => $has('ad_latitude')  ? 'ad_latitude'  : ( $has('latitude')  ? 'latitude'  : null),
    'lng'     => $has('ad_longitude') ? 'ad_longitude' : ( $has('longitude') ? 'longitude' : null),
  ];
}

/* ---------------- POST: listas (modais) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?: [];
    $action = $body['action'] ?? '';

    $lcols = logistics_cols($pdo);
    if (!$lcols['country']) api_respond(true, [], 'Sem coluna de país na logística (providers_logistics).');

    // JOIN countries se tiver iso_code
    $hasCountries = true; // tabela existe
    $countriesJoinOn = has_col($pdo, 'countries', 'iso_code') ? 'c.iso_code = l.'.$lcols['country'] : null;

    if ($action === 'get_countries') {
      $selectCountryName = has_col($pdo,'countries','name') ? 'c.name' : 'NULL';
      $selectCurrIcon    = has_col($pdo,'countries','currencies_icon') ? 'c.currencies_icon' : "''";
      $selectFlag        = has_col($pdo,'countries','flag_url') ? 'c.flag_url' : "''";

      $sql = "
        SELECT 
          l.{$lcols['country']} AS iso_code,
          COALESCE($selectCountryName, l.{$lcols['country']}) AS name,
          COALESCE($selectCurrIcon, '') AS currencies_icon,
          COALESCE($selectFlag, '')     AS flag_url,
          COUNT(*) AS provider_count
        FROM providers p
        JOIN providers_logistics l ON l.provider_id = p.id
        ".($countriesJoinOn ? "LEFT JOIN countries c ON $countriesJoinOn" : "")."
        WHERE ".build_where_base($pdo)."
        GROUP BY l.{$lcols['country']}, name, currencies_icon, flag_url
        HAVING provider_count > 0
        ORDER BY provider_count DESC, name ASC
      ";
      $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
      api_respond(true, $rows);
    }

    if ($action === 'get_regions') {
      if (!$lcols['region']) api_respond(true, []); // não há coluna de região
      $country = s($body['country_code'] ?? '');
      if ($country === '') api_respond(true, []);

      $stmt = $pdo->prepare("
        SELECT 
          l.{$lcols['region']} AS name,
          COUNT(DISTINCT l.{$lcols['city']}) AS city_count,
          COUNT(*) AS provider_count
        FROM providers p
        JOIN providers_logistics l ON l.provider_id = p.id
        WHERE ".build_where_base($pdo)." AND l.{$lcols['country']} = :c
        GROUP BY l.{$lcols['region']}
        HAVING provider_count > 0
        ORDER BY provider_count DESC, name ASC
      ");
      $stmt->execute([':c'=>$country]);
      api_respond(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'get_cities') {
      $country = s($body['country_code'] ?? '');
      $region  = s($body['region_name'] ?? '');
      if ($country === '') api_respond(true, []);

      $conds = ["l.{$lcols['country']} = :c"];
      $binds = [':c'=>$country];
      if ($region !== '' && $lcols['region']) { $conds[] = "l.{$lcols['region']} = :r"; $binds[':r'] = $region; }

      $stmt = $pdo->prepare("
        SELECT 
          l.{$lcols['city']} AS name,
          COUNT(*) AS provider_count
        FROM providers p
        JOIN providers_logistics l ON l.provider_id = p.id
        WHERE ".build_where_base($pdo)." AND ".implode(' AND ', $conds)."
        GROUP BY l.{$lcols['city']}
        HAVING provider_count > 0
        ORDER BY provider_count DESC, name ASC
      ");
      $stmt->execute($binds);
      api_respond(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    api_respond(false, null, 'Invalid action', 400);
  } catch (Throwable $e) {
    log_system_error('API_PROVIDERS_POST: '.$e->getMessage(), 'ERROR', 'api_providers');
    api_respond(false, null, 'Server error', 500);
  }
}

/* ---------------- GET: listagem com fallback ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  try {
    $planet  = strtolower(s($_GET['planet']  ?? 'earth'));
    $country = s($_GET['country'] ?? '');
    $region  = s($_GET['region']  ?? '');
    $city    = s($_GET['city']    ?? '');

    // Marte -> mensagem divertida
    if ($planet === 'mars') {
      $lang = $_SESSION['language'] ?? 'pt-br';
      $msg1 = getTranslation('planet_mars_message_1', $lang, 'results_providers') ?: 'Estamos trabalhando nisso.';
      $msg2 = getTranslation('planet_mars_message_2', $lang, 'results_providers') ?: '';
      $msg3 = getTranslation('planet_mars_message_3', $lang, 'results_providers') ?: '';
      api_respond(true, ['providers'=>[], 'level'=>'planet', 'location'=>null], trim("$msg1 $msg2 $msg3"));
    }

    // Descobrir colunas existentes
    $lcols = logistics_cols($pdo);
    if (!$lcols['city'] || !$lcols['country']) {
      api_respond(true, ['providers'=>[], 'level'=>'global', 'location'=>null], 'Logística sem colunas mínimas.');
    }

    // peças dinâmicas do SELECT
    $selects = [];
    $selects[] = "p.id";

    // Nome (tenta display_name, ad_title, senão NULL)
    $nameExpr = coalesce_cols($pdo, 'providers', 'p', ['display_name','ad_title']);
    $selects[] = "$nameExpr AS name";

    // Título e descrição (se existirem)
    $selects[] = has_col($pdo,'providers','ad_title') ? "p.ad_title" : "NULL AS ad_title";
    $selects[] = has_col($pdo,'providers','description') ? "p.description" : "NULL AS description";

    // localização
    $selects[] = "l.{$lcols['country']} AS country_code";
    $selects[] = $lcols['region'] ? "l.{$lcols['region']} AS region" : "NULL AS region";
    $selects[] = "l.{$lcols['city']}   AS city";

    // countries join (se possível)
    $joinCountriesOn = has_col($pdo,'countries','iso_code') ? "c.iso_code = l.{$lcols['country']}" : null;
    $selects[] = $joinCountriesOn && has_col($pdo,'countries','name') ? "c.name AS country_name" : "NULL AS country_name";
    $selects[] = $joinCountriesOn && has_col($pdo,'countries','currencies_icon') ? "COALESCE(c.currencies_icon,'') AS currencies_icon" : "'' AS currencies_icon";
    $selects[] = $joinCountriesOn && has_col($pdo,'countries','flag_url') ? "COALESCE(c.flag_url,'') AS country_flag" : "'' AS country_flag";

    // demografia/tipo
    $selects[] = has_col($pdo,'providers','age')           ? "p.age"           : "NULL AS age";
    $selects[] = has_col($pdo,'providers','gender')        ? "p.gender"        : "NULL AS gender";
    $selects[] = has_col($pdo,'providers','provider_type') ? "p.provider_type" : "NULL AS provider_type";

    // slug
    $selects[] = has_col($pdo,'providers','slug') ? "p.slug" : "NULL AS slug";

    // preço e moeda (se estiverem em providers)
    $selects[] = has_col($pdo,'providers','base_hourly_rate') ? "p.base_hourly_rate AS price" : "NULL AS price";
    $selects[] = has_col($pdo,'providers','currency')         ? "p.currency"                : "NULL AS currency";

    // imagem (main_photo_url → profile_photo → NULL)
    $imgExpr = coalesce_cols($pdo, 'providers', 'p', ['main_photo_url','profile_photo']);
    $selects[] = "$imgExpr AS image_url";

    // status/flags
    $selects[] = has_col($pdo,'providers','is_verified')   ? "p.is_verified"   : "NULL AS is_verified";
    $selects[] = has_col($pdo,'providers','online_status') ? "p.online_status" : "NULL AS online_status";
    $selects[] = has_col($pdo,'providers','onlyfans_url')  ? "p.onlyfans_url"  : "NULL AS onlyfans_url";
    $selects[] = has_col($pdo,'providers','spotlight_level') ? "p.spotlight_level" : "0 AS spotlight_level";

    $SELECT = "SELECT ".implode(",\n        ", $selects)."
      FROM providers p
      JOIN providers_logistics l ON l.provider_id = p.id
      ".($joinCountriesOn ? "LEFT JOIN countries c ON $joinCountriesOn" : "")."
    ";

    $ORDER = build_order($pdo);

    // Helper executor
    $queryWith = function(array $conds, array $binds) use ($pdo, $SELECT, $ORDER) {
      $w = build_where_base($pdo);
      if (!empty($conds)) $w .= ' AND '.implode(' AND ', $conds);
      $sql = $SELECT . ' WHERE ' . $w . $ORDER;
      $stmt = $pdo->prepare($sql);
      $stmt->execute($binds);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    $providers = [];
    $level = 'global';
    $resolved = ['country_code'=>null,'country_name'=>null,'region'=>null,'city'=>null];

    // 1) CITY
    if ($city !== '') {
      $providers = $queryWith(["l.{$lcols['city']} = :city"], [':city'=>$city]);
      if ($providers) {
        $level = 'city';
        $resolved['city']          = $city;
        $resolved['region']        = $providers[0]['region']        ?? null;
        $resolved['country_code']  = $providers[0]['country_code']  ?? null;
        $resolved['country_name']  = $providers[0]['country_name']  ?? null;
      }
    }

    // 2) REGION (só se existir coluna de região)
    if (!$providers && $region !== '' && $lcols['region']) {
      $providers = $queryWith(["l.{$lcols['region']} = :region"], [':region'=>$region]);
      if ($providers) {
        $level = 'region';
        $resolved['region']        = $region;
        $resolved['country_code']  = $providers[0]['country_code']  ?? null;
        $resolved['country_name']  = $providers[0]['country_name']  ?? null;
      }
    }

    // 3) COUNTRY
    if (!$providers && $country !== '') {
      $providers = $queryWith(["l.{$lcols['country']} = :country"], [':country'=>$country]);
      if ($providers) {
        $level = 'country';
        $resolved['country_code']  = $country;
        $resolved['country_name']  = $providers[0]['country_name']  ?? null;
      }
    }

    // 4) GLOBAL
    if (!$providers) {
      $providers = $queryWith([], []);
      $level = 'global';
    }

    // Derivar localização do primeiro, se nada resolvido
    if (empty(array_filter($resolved)) && $providers) {
      $resolved['country_code'] = $providers[0]['country_code'] ?? null;
      $resolved['country_name'] = $providers[0]['country_name'] ?? null;
      $resolved['region']       = $providers[0]['region'] ?? null;
      $resolved['city']         = $providers[0]['city'] ?? null;
    }

    api_respond(true, [
      'providers' => $providers,
      'level'     => $level,
      'location'  => $resolved,
    ]);
  } catch (Throwable $e) {
    log_system_error('API_PROVIDERS_GET: '.$e->getMessage(), 'ERROR', 'api_providers');
    api_respond(false, null, 'Server error', 500);
  }
}

api_respond(false, null, 'Method not allowed', 405);
