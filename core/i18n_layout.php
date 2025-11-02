<?php
/**
 * /core/i18n_layout.php
 * Sistema i18n com integração ao banco de dados (tabela translations)
 * Compatível com templates header.php, footer.php, age_gate_modal.php etc.
 * Última atualização: 31/10/2025
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php'; // garante LANGUAGE_CONFIG e SITE_URL

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
if (!defined('LANG_PATH')) {
    define('LANG_PATH', BASE_PATH . '/lang');
}

/* ==========================================================
 * Configuração básica e utilidades
 * ========================================================== */
function i18n_config(): array {
    return defined('LANGUAGE_CONFIG') ? LANGUAGE_CONFIG : [
        'default'   => 'en-us',
        'fallback'  => 'en-us',
        'available' => ['en-us'],
        'iso_map'   => ['en-us' => 'us'],
        'name_map'  => ['en-us' => 'English'],
    ];
}

function i18n_base(string $code): string {
    $code = strtolower(trim($code));
    if ($code === '') return 'en';
    $base = explode('-', $code, 2)[0];
    $base = preg_replace('/[^a-z]/', '', $base) ?: 'en';
    return substr($base, 0, 2);
}

function i18n_all_codes(): array {
    $cfg = i18n_config();
    return array_values(array_unique(array_map('strtolower', (array)$cfg['available'])));
}

function i18n_code_map_by_base(): array {
    static $cache = null;
    if (is_array($cache)) return $cache;
    $map = [];
    foreach (i18n_all_codes() as $code) {
        $b = i18n_base($code);
        if (!isset($map[$b])) $map[$b] = [];
        if (!in_array($code, $map[$b], true)) $map[$b][] = $code;
        if (!in_array($b, $map[$b], true)) $map[$b][] = $b;
    }
    ksort($map);
    return $cache = $map;
}

function i18n_all_bases(): array {
    return array_keys(i18n_code_map_by_base());
}

/* ==========================================================
 * Detecção e persistência do locale
 * ========================================================== */
function i18n_guess_from_accept_language(): ?string {
    $hdr = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if (!$hdr) return null;
    $avail = i18n_all_codes();
    $bases = i18n_all_bases();

    foreach (explode(',', $hdr) as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $candidate = strtolower(explode(';', $p, 2)[0]);
        $norm = i18n_normalize($candidate);
        if (in_array($norm, $avail, true)) return $norm;
        $b = i18n_base($norm);
        if (in_array($b, $bases, true)) return $b;
    }
    return null;
}

function i18n_normalize(string $locale): string {
    $locale = strtolower(trim($locale));
    $cfg    = i18n_config();
    $avail  = i18n_all_codes();
    $bases  = i18n_all_bases();
    if ($locale === '') return strtolower($cfg['default']);
    if (in_array($locale, $avail, true)) return $locale;
    $b = i18n_base($locale);
    if (in_array($b, $bases, true)) return $b;
    return strtolower($cfg['default']);
}

function i18n_get_locale(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $cfg = i18n_config();
    $bases = i18n_all_bases();

    if (!empty($_GET['lang'])) {
        $norm = i18n_normalize($_GET['lang']);
        $base = i18n_base($norm);
        if (in_array($base, $bases, true)) {
            $_SESSION['lang'] = $base;
            return $base;
        }
    }
    if (!empty($_SESSION['lang'])) return $_SESSION['lang'];
    return i18n_base($cfg['default']);
}

/* ==========================================================
 * Carregamento de traduções direto do banco de dados
 * ========================================================== */
if (!function_exists('loadContextTranslations')) {
    function loadContextTranslations(string $languageCode, string $context): array {
        try {
            global $pdo;
            if (!isset($pdo)) {
                require_once __DIR__ . '/db.php';
            }
            $stmt = $pdo->prepare("
                SELECT translation_key, translation_value
                FROM translations
                WHERE language_code = :lang AND context = :context
            ");
            $stmt->execute([
                ':lang' => strtolower($languageCode),
                ':context' => strtolower($context),
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            error_log('[i18n] DB Error: ' . $e->getMessage());
            return [];
        }
    }
}

/* ==========================================================
 * Templates específicos (header, footer, age_gate)
 * ========================================================== */
if (!function_exists('loadHeaderTranslations')) {
    function loadHeaderTranslations(string $languageCode = null): array {
        $languageCode = strtolower($languageCode ?: i18n_get_locale());
        return loadContextTranslations($languageCode, 'header');
    }
}

if (!function_exists('loadFooterTranslations')) {
    function loadFooterTranslations(string $languageCode = null): array {
        $languageCode = strtolower($languageCode ?: i18n_get_locale());
        return loadContextTranslations($languageCode, 'footer');
    }
}

if (!function_exists('loadAgeGateTranslations')) {
    function loadAgeGateTranslations(string $languageCode = null): array {
        $languageCode = strtolower($languageCode ?: i18n_get_locale());
        return loadContextTranslations($languageCode, 'age_gate');
    }
}

/* ==========================================================
 * Utilitários gerais
 * ========================================================== */
if (!function_exists('getTranslation')) {
    function getTranslation(string $key, string $languageCode = null, string $context = 'header') {
        $languageCode = strtolower($languageCode ?: i18n_get_locale());
        $data = loadContextTranslations($languageCode, $context);
        return $data[$key] ?? null;
    }
}
?>
