<?php
/**
 * /core/search_helpers.php
 *
 * Auxiliares para o sistema de busca do BacoSearch.
 * Incluído pelo core/bootstrap.php (que já carrega config + functions).
 * Última atualização: 14/08/2025 (compat. PHP 7.0–7.3)
 */

if (!defined('IN_BACOSEARCH')) {
    die('Acesso direto não permitido');
}

// ======================================================================
// CACHE (APCu -> ficheiros) + TTL
// ======================================================================
class BacoSearchCache {
    /** @var string */
    private $cache_dir;
    /** @var int */
    private $default_ttl;

    public function __construct(string $cache_dir = '/tmp/bacosearch_cache', int $default_ttl = 1800) {
        $this->cache_dir   = rtrim($cache_dir, '/');
        $this->default_ttl = $default_ttl;

        if (!is_dir($this->cache_dir)) {
            @mkdir($this->cache_dir, 0755, true);
        }
    }

    public function keyFrom(string $term, array $params = []): string {
        $keyData = ['term' => (string)$term] + (array)$params;
        ksort($keyData);
        return md5(json_encode($keyData));
    }

    public function get(string $key, $default = null) {
        // 1) APCu
        if (function_exists('apcu_fetch')) {
            $ok = false;
            $value = apcu_fetch('bs_'.$key, $ok);
            if ($ok) return $value;
        }

        // 2) Ficheiro
        $file = "{$this->cache_dir}/{$key}.cache";
        if (is_file($file)) {
            $data = @file_get_contents($file);
            if ($data !== false) {
                $payload = @unserialize($data);
                if (is_array($payload) && isset($payload['exp'], $payload['val'])) {
                    if ($payload['exp'] === 0 || time() < (int)$payload['exp']) {
                        return $payload['val'];
                    }
                }
            }
            @unlink($file);
        }
        return $default;
    }

    public function set(string $key, $data, int $ttl = null): void {
        $ttl = $ttl ?? $this->default_ttl;

        // 1) APCu
        if (function_exists('apcu_store')) {
            @apcu_store('bs_'.$key, $data, max(0, (int)$ttl));
        }

        // 2) Ficheiro
        $file = "{$this->cache_dir}/{$key}.cache";
        $payload = [
            'exp' => $ttl > 0 ? (time() + (int)$ttl) : 0,
            'val' => $data
        ];
        @file_put_contents($file, serialize($payload), LOCK_EX);
    }

    public function delete(string $key): void {
        if (function_exists('apcu_delete')) { @apcu_delete('bs_'.$key); }
        $file = "{$this->cache_dir}/{$key}.cache";
        if (is_file($file)) { @unlink($file); }
    }

    public function clear(): void {
        // limpa só os ficheiros deste diretório
        foreach (glob($this->cache_dir.'/*.cache') as $f) { @unlink($f); }
        // não faz apcu_clear_cache() para não afetar outros itens da app
    }
}

// ======================================================================
// ANALYTICS
// ======================================================================
class BacoSearchAnalytics {
    /** @var PDO */
    private $db;

    public function __construct(PDO $db) { $this->db = $db; }

    /**
     * Regista uma busca em `global_searches`.
     * Mantemos compatível com variações de schema (colunas opcionais).
     */
    public function recordSearch(string $term, int $results_count, array $metadata = []): void {
        try {
            $visitor_id = $_SESSION['visitor_db_id'] ?? null;
            $ip         = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $ua         = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
            $sid        = session_id();

            // Monta dinamicamente os campos que sabemos usar.
            $cols = ['term'];
            $vals = [':term' => $term];

            // Descobre colunas disponíveis
            static $columns = null;
            if ($columns === null) {
                $columns = [];
                try {
                    $stmt = $this->db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'global_searches'");
                    $columns = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
                } catch (Throwable $e) {
                    $columns = [];
                }
            }
            $addIfExists = function(string $col, $param, $val) use (&$columns, &$cols, &$vals) {
                if (in_array(strtolower($col), $columns, true)) {
                    $cols[] = $col;
                    $vals[$param] = $val;
                }
            };

            $addIfExists('results_count', ':results_count', $results_count);
            $addIfExists('user_ip',       ':user_ip',       $ip);
            $addIfExists('user_agent',    ':user_agent',    $ua);
            $addIfExists('session_id',    ':session_id',    $sid);
            $addIfExists('latitude',      ':latitude',      $metadata['lat'] ?? null);
            $addIfExists('longitude',     ':longitude',     $metadata['lon'] ?? null);
            $addIfExists('visitor_id',    ':visitor_id',    $visitor_id);
            $addIfExists('metadata',      ':metadata',      json_encode($metadata));

            // timestamps (se existirem)
            $addIfExists('created_at',    ':created_at',    date('Y-m-d H:i:s'));
            $addIfExists('updated_at',    ':updated_at',    date('Y-m-d H:i:s'));

            $sql = "INSERT INTO global_searches (".implode(',', $cols).") VALUES (".implode(',', array_keys($vals)).")";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($vals);
        } catch (Throwable $e) {
            log_system_error('BacoSearchAnalytics: '.$e->getMessage(), 'ERROR', 'analytics_record');
        }
    }
}

// ======================================================================
// FILTROS
// ======================================================================
class BacoSearchFilters {

    /**
     * Aplica filtros (WHERE) de forma segura. Aceita valores simples ou arrays.
     * Suporta: category, gender, ethnicity, body_type, verified_only, available_now,
     * preços (price_min/price_max), idades (age_min/age_max), localização (country, region, city).
     *
     * @param string $query  SQL base (SELECT ... FROM providers p ...)
     * @param array  $filters Filtros vindos do request
     * @param array  $params  Referência ao array de params (named) da query
     * @param string $alias   Alias da tabela principal (default 'p')
     */
    public static function applyFilters(string $query, array $filters, array &$params, string $alias = 'p'): string {
        $where = [];

        // Helper para IN seguro (strings)
        $applyInList = function(string $column, string $paramBase, $values) use (&$params, &$where, $alias) {
            $values = is_array($values) ? $values : [$values];
            $values = array_filter(array_map('strval', $values), function ($v) { return $v !== ''; });
            if (!$values) return;
            $placeholders = [];
            foreach ($values as $i => $val) {
                $ph = ":{$paramBase}_{$i}";
                $placeholders[] = $ph;
                $params[$ph] = strtolower($val);
            }
            $where[] = "LOWER({$alias}.{$column}) IN (".implode(',', $placeholders).")";
        };

        // Texto (category/ethnicity/body_type) — aceita único ou array
        if (!empty($filters['category']))   $applyInList('category',   'cat',  $filters['category']);
        if (!empty($filters['gender']))     $applyInList('gender',     'gen',  $filters['gender']);
        if (!empty($filters['ethnicity']))  $applyInList('ethnicity',  'eth',  $filters['ethnicity']);
        if (!empty($filters['body_type']))  $applyInList('body_type',  'btype',$filters['body_type']);

        // Localização (se existirem essas colunas em providers)
        if (!empty($filters['country'])) $applyInList('country_code', 'country', $filters['country']);
        if (!empty($filters['region']))  $applyInList('region',       'region',  $filters['region']);
        if (!empty($filters['city']))    $applyInList('city',         'city',    $filters['city']);

        // Flags
        if (!empty($filters['verified_only'])) { $where[] = "{$alias}.is_verified = 1"; }
        if (!empty($filters['available_now'])) { $where[] = "{$alias}.availability = 'now'"; }

        // Preço
        $min = isset($filters['price_min']) ? (float)$filters['price_min'] : null;
        $max = isset($filters['price_max']) ? (float)$filters['price_max'] : null;
        if ($min !== null && $max !== null && $max >= $min) {
            $where[] = "{$alias}.base_hourly_rate BETWEEN :price_min AND :price_max";
            $params[':price_min'] = $min;
            $params[':price_max'] = $max;
        } elseif ($min !== null) {
            $where[] = "{$alias}.base_hourly_rate >= :price_min";
            $params[':price_min'] = $min;
        } elseif ($max !== null) {
            $where[] = "{$alias}.base_hourly_rate <= :price_max";
            $params[':price_max'] = $max;
        }

        // Idade
        $ageMin = isset($filters['age_min']) ? (int)$filters['age_min'] : null;
        $ageMax = isset($filters['age_max']) ? (int)$filters['age_max'] : null;
        if ($ageMin !== null && $ageMax !== null && $ageMax >= $ageMin) {
            $where[] = "{$alias}.age BETWEEN :age_min AND :age_max";
            $params[':age_min'] = $ageMin;
            $params[':age_max'] = $ageMax;
        } elseif ($ageMin !== null) {
            $where[] = "{$alias}.age >= :age_min";
            $params[':age_min'] = $ageMin;
        } elseif ($ageMax !== null) {
            $where[] = "{$alias}.age <= :age_max";
            $params[':age_max'] = $ageMax;
        }

        // Status padrão (se existir a coluna na tua tabela)
        $where[] = "{$alias}.deleted_at IS NULL";
        $where[] = "{$alias}.status = 'approved'";

        if ($where) {
            $query .= (stripos($query, 'WHERE') === false ? " WHERE " : " AND ") . implode(' AND ', $where);
        }
        return $query;
    }

    /**
     * Opções para dropdowns de filtro (valores distintos).
     */
    public static function getFilterOptions(PDO $db): array {
        $opts = ['categories'=>[], 'genders'=>[], 'ethnicities'=>[], 'body_types'=>[], 'cities'=>[], 'regions'=>[], 'countries'=>[]];

        try {
            $opts['categories'] = self::fetchDistinct($db, "SELECT DISTINCT category FROM providers WHERE deleted_at IS NULL AND status='approved' AND category IS NOT NULL");
            $opts['genders']    = self::fetchDistinct($db, "SELECT DISTINCT gender FROM providers WHERE deleted_at IS NULL AND status='approved' AND gender IS NOT NULL");
            $opts['ethnicities']= self::fetchDistinct($db, "SELECT DISTINCT ethnicity FROM providers WHERE deleted_at IS NULL AND status='approved' AND ethnicity IS NOT NULL");
            $opts['body_types'] = self::fetchDistinct($db, "SELECT DISTINCT body_type FROM providers WHERE deleted_at IS NULL AND status='approved' AND body_type IS NOT NULL");

            // se tiveres colunas de localização na tabela
            $opts['cities']     = self::fetchDistinct($db, "SELECT DISTINCT city FROM providers WHERE deleted_at IS NULL AND status='approved' AND city IS NOT NULL");
            $opts['regions']    = self::fetchDistinct($db, "SELECT DISTINCT region FROM providers WHERE deleted_at IS NULL AND status='approved' AND region IS NOT NULL");
            $opts['countries']  = self::fetchDistinct($db, "SELECT DISTINCT country_code FROM providers WHERE deleted_at IS NULL AND status='approved' AND country_code IS NOT NULL");
        } catch (Throwable $e) {
            log_system_error('BacoSearchFilters: ' . $e->getMessage(), 'ERROR', 'filters_options');
        }

        return $opts;
    }

    private static function fetchDistinct(PDO $db, string $sql): array {
        $stmt = $db->query($sql);
        return array_values(array_filter(
            $stmt->fetchAll(PDO::FETCH_COLUMN),
            function ($v) { return $v !== null && $v !== ''; }
        ));
    }

    /**
     * Ordenação segura com whitelist.
     * @param array $allowed Mapa campo=>coluna_sql (ex.: ['price'=>'p.base_hourly_rate','age'=>'p.age','created'=>'p.created_at'])
     * @return string "ORDER BY ..." ou string vazia
     */
    public static function buildOrderBy(array $allowed, ?string $sort, ?string $dir = 'asc'): string {
        if (!$sort || !isset($allowed[$sort])) return '';
        $direction = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        return " ORDER BY {$allowed[$sort]} {$direction} ";
    }

    /**
     * Paginação segura.
     * @return string "LIMIT :limit OFFSET :offset"
     */
    public static function buildLimitOffset(int $page = 1, int $perPage = 24, int $maxPerPage = 100, array &$params = []): string {
        $page    = max(1, (int)$page);
        $perPage = max(1, min((int)$perPage, (int)$maxPerPage));
        $offset  = ($page - 1) * $perPage;

        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;

        return " LIMIT :limit OFFSET :offset ";
    }
}

// ======================================================================
// FACTORIES
// ======================================================================
function getBacoSearchCache(): BacoSearchCache {
    static $cache_instance = null;
    if ($cache_instance === null) {
        $cache_dir = defined('CACHE_PATH') ? CACHE_PATH : ($_SERVER['DOCUMENT_ROOT'] ?? sys_get_temp_dir()) . '/cache/search';
        $cache_instance = new BacoSearchCache($cache_dir, 1800);
    }
    return $cache_instance;
}

function getBacoSearchAnalytics(PDO $db): BacoSearchAnalytics {
    static $analytics_instance = null;
    if ($analytics_instance === null) {
        $analytics_instance = new BacoSearchAnalytics($db);
    }
    return $analytics_instance;
}

/**
 * Retorna a classe de Filtros (para uso estático).
 */
function getBacoSearchFilters(): string {
    return BacoSearchFilters::class;
}

// Não redefinimos getDBConnection() aqui; já existe em core/functions.php
