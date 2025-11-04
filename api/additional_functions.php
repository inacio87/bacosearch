<?php
/**
 * additional_functions.php — Funções auxiliares para APIs de resultados (businesses, clubs, services, streets)
 *
 * Fornece busca com fallback (CITY → REGION → COUNTRY → GLOBAL) e montagem de itens padronizados.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

/** Retorna PDO compartilhado */
function af_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = getDBConnection();
    return $pdo;
}

/** Verifica se a coluna existe na tabela atual do DB */
function af_has_col(string $table, string $col): bool {
    static $cache = [];
    $key = $table;
    if (!isset($cache[$key])) {
        $stmt = af_pdo()->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $cache[$key] = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
    return isset($cache[$key][$col]);
}

/** Monta WHERE base com colunas comuns (status/is_active/deleted_at) caso existam */
function af_where_base(string $alias, string $table): string {
    $w = [];
    if (af_has_col($table, 'is_active'))  $w[] = "$alias.is_active = 1";
    if (af_has_col($table, 'status'))     $w[] = "$alias.status = 'active'";
    if (af_has_col($table, 'deleted_at')) $w[] = "$alias.deleted_at IS NULL";
    return $w ? implode(' AND ', $w) : '1=1';
}

/** ORDER BY comum (spotlight/updated/id) quando existir */
function af_order(string $alias, string $table): string {
    $o = [];
    if (af_has_col($table, 'spotlight_level')) $o[] = "$alias.spotlight_level DESC";
    if (af_has_col($table, 'updated_at'))      $o[] = "$alias.updated_at DESC";
    $o[] = "$alias.id DESC";
    return ' ORDER BY '.implode(', ', $o).' LIMIT 60';
}

/** Coalesce de colunas se existirem */
function af_coalesce(string $table, string $alias, array $cands): string {
    $present = [];
    foreach ($cands as $c) if (af_has_col($table, $c)) $present[] = "$alias.$c";
    return $present ? ('COALESCE('.implode(',', $present).')') : 'NULL';
}

/**
 * Busca com fallback para uma tabela vertical.
 * - $table: nome da tabela (companies, clubs, services_listings, street_posts)
 * - $nameCols: candidatos de nome/título
 * - $slugCol: coluna slug (ou null)
 * - $imageCols: candidatos de imagem
 * - $countryCol/$stateCol/$cityCol: colunas de localização
 * - $extraSelect: pares [aliasSQL => exprSQL]
 * - $filters: filtros opcionais (category, keywords, price_max etc.) — aplicado quando disponíveis
 */
function af_fetch_with_fallback(string $table, array $nameCols, ?string $slugCol, array $imageCols,
    string $countryCol='ad_country', string $stateCol='ad_state', string $cityCol='ad_city', array $extraSelect=[], array $filters=[]): array {

    $pdo = af_pdo();

    // SELECT dinâmico
    $sel = [];
    $sel[] = "t.id";
    $sel[] = af_coalesce($table, 't', $nameCols) . ' AS name';
    $sel[] = $slugCol && af_has_col($table, $slugCol) ? "t.$slugCol AS slug" : "NULL AS slug";
    $sel[] = af_coalesce($table, 't', $imageCols) . ' AS image_url';
    $sel[] = af_has_col($table, $countryCol) ? "t.$countryCol AS country_code" : "NULL AS country_code";
    $sel[] = af_has_col($table, $stateCol) ? "t.$stateCol AS region" : "NULL AS region";
    $sel[] = af_has_col($table, $cityCol) ? "t.$cityCol AS city" : "NULL AS city";

    // Join countries (se existir iso_code)
    $joinCountriesOn = af_has_col('countries', 'iso_code') && af_has_col($table, $countryCol)
        ? "LEFT JOIN countries c ON c.iso_code = t.$countryCol"
        : '';
    $sel[] = $joinCountriesOn && af_has_col('countries','name') ? 'c.name AS country_name' : 'NULL AS country_name';
    $sel[] = $joinCountriesOn && af_has_col('countries','currencies_icon') ? "COALESCE(c.currencies_icon,'') AS currencies_icon" : "'' AS currencies_icon";

    foreach ($extraSelect as $alias => $expr) {
        $sel[] = "$expr AS $alias";
    }

    $SELECT = 'SELECT '.implode(",\n        ", $sel)."\n        FROM $table t\n        $joinCountriesOn\n    ";
    $ORDER = af_order('t', $table);

    $baseWhere = af_where_base('t', $table);

    // filtros básicos
    $applyFilters = function(array &$conds, array &$binds) use ($table, $filters) {
        // category por slug/id (se houver category_id ou category_slug)
        if (!empty($filters['category'])) {
            $cat = trim((string)$filters['category']);
            if ($cat !== '') {
                if (af_has_col($table, 'category_slug')) { $conds[] = 't.category_slug = :cat_slug'; $binds[':cat_slug']=$cat; }
                elseif (af_has_col($table, 'category')) { $conds[] = 't.category = :cat'; $binds[':cat']=$cat; }
                elseif (af_has_col($table, 'category_id') && ctype_digit($cat)) { $conds[] = 't.category_id = :catid'; $binds[':catid']=(int)$cat; }
            }
        }
        // keywords (name/description)
        if (!empty($filters['keywords'])) {
            $kw = '%'.str_replace(['%','_'],['\%','\_'], (string)$filters['keywords']).'%';
            $likeConds = [];
            if (af_has_col($table, 'description')) $likeConds[] = 't.description LIKE :kw';
            foreach (['name','company_name','club_name','service_title','place_name','street_name'] as $c) {
                if (af_has_col($table, $c)) { $likeConds[] = "t.$c LIKE :kw"; }
            }
            if ($likeConds) { $conds[] = '('.implode(' OR ', $likeConds).')'; $binds[':kw']=$kw; }
        }
        // price_max simples (services_listings: price_min/price_max)
        if (isset($filters['price_max']) && af_has_col($table, 'price_min')) {
            $pm = (float)$filters['price_max'];
            if ($pm > 0) { $conds[] = '(t.price_min <= :pmax OR t.price_max <= :pmax)'; $binds[':pmax']=$pm; }
        }
    };

    $run = function(array $conds, array $binds) use ($pdo, $SELECT, $ORDER, $baseWhere, $applyFilters) {
        $applyFilters($conds, $binds);
        $where = $baseWhere;
        if ($conds) $where .= ' AND '.implode(' AND ', $conds);
        $sql = $SELECT.' WHERE '.$where.$ORDER;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($binds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    $loc_country = isset($_POST['country_code']) ? (string)$_POST['country_code'] : '';
    $loc_region  = isset($_POST['region']) ? (string)$_POST['region'] : '';
    $loc_city    = isset($_POST['city']) ? (string)$_POST['city'] : '';
    // prefer dados recebidos via JSON
    $reqBody = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
    $ld = $reqBody['location_data'] ?? [];
    $country = (string)($ld['country_code'] ?? $loc_country);
    $region  = (string)($ld['region'] ?? $loc_region);
    $city    = (string)($ld['city'] ?? $loc_city);

    $items = [];
    $level = 'global';
    $resolved = ['country_code'=>null,'country_name'=>null,'region'=>null,'city'=>null];

    // 1) CITY
    if ($city !== '' && af_has_col($table, $cityCol)) {
        $items = $run(["t.$cityCol = :city"], [':city'=>$city]);
        if ($items) { $level='city'; $resolved['city']=$city; $resolved['region']=$items[0]['region']??null; $resolved['country_code']=$items[0]['country_code']??null; $resolved['country_name']=$items[0]['country_name']??null; }
    }

    // 2) REGION
    if (!$items && $region !== '' && af_has_col($table, $stateCol)) {
        $items = $run(["t.$stateCol = :reg"], [':reg'=>$region]);
        if ($items) { $level='region'; $resolved['region']=$region; $resolved['country_code']=$items[0]['country_code']??null; $resolved['country_name']=$items[0]['country_name']??null; }
    }

    // 3) COUNTRY
    if (!$items && $country !== '' && af_has_col($table, $countryCol)) {
        $items = $run(["t.$countryCol = :ct"], [':ct'=>$country]);
        if ($items) { $level='country'; $resolved['country_code']=$country; $resolved['country_name']=$items[0]['country_name']??null; }
    }

    // 4) GLOBAL
    if (!$items) {
        $items = $run([], []);
        $level = 'global';
    }

    if (empty(array_filter($resolved)) && $items) {
        $resolved['country_code'] = $items[0]['country_code'] ?? null;
        $resolved['country_name'] = $items[0]['country_name'] ?? null;
        $resolved['region']       = $items[0]['region'] ?? null;
        $resolved['city']         = $items[0]['city'] ?? null;
    }

    return ['items'=>$items,'level'=>$level,'location'=>$resolved];
}

/* ---------------- Wrappers por vertical ---------------- */

function findBusinessesWithFallback(array $location_data=[], array $filters=[]): array {
    // Tabela criada na migration: companies
    $ret = af_fetch_with_fallback(
        'companies',
        ['company_name','name'],
        'slug',
        ['main_photo_url'],
        'ad_country','ad_state','ad_city',
        [],
        $filters
    );
    return ['businesses'=>$ret['items'],'level'=>$ret['level'],'location'=>$ret['location']];
}

function findClubsWithFallback(array $location_data=[], array $filters=[]): array {
    $ret = af_fetch_with_fallback(
        'clubs',
        ['club_name','name'],
        'slug',
        ['main_photo_url'],
        'ad_country','ad_state','ad_city',
        [],
        $filters
    );
    return ['clubs'=>$ret['items'],'level'=>$ret['level'],'location'=>$ret['location']];
}

function findServicesWithFallback(array $location_data=[], array $filters=[]): array {
    // Tabela criada na migration: services_listings
    $extra = [];
    if (af_has_col('services_listings','price_min')) $extra['price_min'] = 't.price_min';
    if (af_has_col('services_listings','price_max')) $extra['price_max'] = 't.price_max';
    if (af_has_col('services_listings','currency'))  $extra['currency']  = 't.currency';
    $ret = af_fetch_with_fallback(
        'services_listings',
        ['service_title','name'],
        'slug',
        ['main_photo_url'],
        'ad_country','ad_state','ad_city',
        $extra,
        $filters
    );
    return ['services'=>$ret['items'],'level'=>$ret['level'],'location'=>$ret['location']];
}

function findStreetsWithFallback(array $location_data=[], array $filters=[]): array {
    // Tabela criada na migration: street_posts
    $extra = [];
    if (af_has_col('street_posts','place_type')) $extra['place_type'] = 't.place_type';
    $ret = af_fetch_with_fallback(
        'street_posts',
        ['place_name','street_name'],
        null,
        [],
        'ad_country','ad_state','ad_city',
        $extra,
        $filters
    );
    return ['streets'=>$ret['items'],'level'=>$ret['level'],'location'=>$ret['location']];
}

?>
