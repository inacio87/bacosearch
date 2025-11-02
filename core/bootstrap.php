<?php
/**
 * core/bootstrap.php
 * Minimal bootstrap to bring the site back online.
 * Defines environment loading, constants, helpers, and safe defaults.
 */

if (!defined('IN_BACOSEARCH')) {
    define('IN_BACOSEARCH', true);
}

// Error handling: be conservative in production
ini_set('display_errors', '0');
error_reporting(E_ALL);

$ROOT_PATH = dirname(__DIR__);

// Simple .env loader (no external deps)
function load_env(string $path): void {
    if (!file_exists($path)) return;
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        if (strpos(ltrim($line), '#') === 0) continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        $val = trim($val, "\"' ");
        if ($key !== '') {
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
            @putenv($key . '=' . $val);
        }
    }
}

// Try project .env then account-level ~/.env (HostGator typical location)
load_env($ROOT_PATH . DIRECTORY_SEPARATOR . '.env');
$homeEnv = rtrim(getenv('HOME') ?: getenv('USERPROFILE') ?: '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
if ($homeEnv && file_exists($homeEnv)) {
    load_env($homeEnv);
}

function env(string $key, $default = null) {
    if (array_key_exists($key, $_ENV)) return $_ENV[$key];
    if (array_key_exists($key, $_SERVER)) return $_SERVER[$key];
    $v = getenv($key);
    return $v !== false ? $v : $default;
}

// Basic constants and paths
define('ROOT_PATH', rtrim($ROOT_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
define('TEMPLATE_PATH', ROOT_PATH . 'templates' . DIRECTORY_SEPARATOR);
define('SITE_URL', rtrim((string)env('APP_URL', ''), '/'));
if (!defined('APP_NAME')) {
    define('APP_NAME', (string)env('APP_NAME', 'BacoSearch'));
}

// SEO and language configs used by pages
if (!defined('SEO_CONFIG')) {
    define('SEO_CONFIG', [
        'meta_description' => 'BacoSearch - Busca e gestão de conteúdos.',
        'meta_keywords'    => 'busca, catálogo, anúncios',
        'meta_author'      => 'BacoSearch',
    ]);
}

if (!defined('LANGUAGE_CONFIG')) {
    define('LANGUAGE_CONFIG', [
        'default' => 'en-us',
        'name_map' => [
            'en-us' => 'English',
            'pt-br' => 'Português (Brasil)'
        ],
    ]);
}

// Session
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// DB connection (optional; returns PDO or throws)
function getDBConnection(): PDO {
    $host = env('DB_HOST', 'localhost');
    $db   = env('DB_DATABASE', '');
    $user = env('DB_USERNAME', '');
    $pass = env('DB_PASSWORD', '');
    $charset = env('DB_CHARSET', 'utf8mb4');
    $collation = env('DB_COLLATION', 'utf8mb4_unicode_ci');

    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$collation}"
    ];
    return new PDO($dsn, $user, $pass, $options);
}

// Translations fallback
function getTranslation(string $key, string $lang = 'en-us', string $context = 'default'): string {
    // Minimal fallback: return key; pages remain functional without fatal errors.
    return $key;
}

// Simple slug helper used in providers page
function create_slug(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('~[\s_]+~', '-', $text);
    $text = preg_replace('~[^a-z0-9\-]+~', '', $text);
    $text = preg_replace('~-+~', '-', $text);
    return trim($text, '-');
}

// Ensure template path exists to avoid include errors (minimal)
if (!is_dir(TEMPLATE_PATH)) {
    @mkdir(TEMPLATE_PATH, 0755, true);
}

// You may include additional app bootstrap files here (routes, helpers, etc.)
// For now we keep it minimal to restore availability.

?>
