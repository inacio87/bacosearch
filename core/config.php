<?php
/**
 * /config.php - Arquivo Central de Configuração da Aplicação
 *
 * RESPONSABILIDADES:
 * 1. Carregar as variáveis de ambiente do arquivo .env.
 * 2. Definir funções auxiliares para acessar as variáveis de ambiente.
 * 3. Definir todas as constantes globais da aplicação (caminhos, DB, segurança, APIs, etc.).
 * 4. Realizar verificações críticas do ambiente de execução (extensões, permissões).
 *
 * ÚLTIMA ATUALIZAÇÃO: 07/07/2025 - Adicionada constante ADMIN_CONTACT_EMAIL.
 */

// Impede o acesso direto ao arquivo de configuração para segurança.
if (basename($_SERVER['SCRIPT_FILENAME']) === 'config.php') {
    http_response_code(403);
    exit('Acesso direto não permitido.');
}

// Carrega variáveis de ambiente do arquivo .env.
$envFile = dirname(__DIR__, 2) . '/.env';
$envVars = [];

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || trim($line) === '') {
            continue;
        }
        @list($key, $value) = explode('=', $line, 2);
        if ($key !== null) {
            $envVars[trim($key)] = trim($value ?? '');
        }
    }
} else {
    // Log para um arquivo temporário se LOG_PATH ainda não estiver definido
    error_log("CRÍTICO: Arquivo de ambiente não encontrado no caminho: $envFile.", 3, '/tmp/error.log');
    http_response_code(500);
    exit('Ocorreu um erro crítico na inicialização do aplicativo.');
}

// Define o LOG_PATH antecipadamente para registro de erros.
// Depende de $envVars, então esta linha deve vir após o carregamento do .env
define('LOG_PATH', $envVars['LOG_PATH'] ?? '/tmp/error.log');

/**
 * Obtém o valor de uma variável de ambiente.
 */
function env($key, $default = null) {
    global $envVars;
    $value = $envVars[$key] ?? $default;
    // Remove as aspas simples ou duplas se a string começar e terminar com elas.
    if (is_string($value) && (str_starts_with($value, "'") && str_ends_with($value, "'") || str_starts_with($value, '"') && str_ends_with($value, '"'))) {
        return substr($value, 1, -1);
    }
    return $value;
}

/**
 * Obtém o valor de uma variável de ambiente obrigatória.
 */
function env_required($key) {
    $value = env($key);
    if ($value === null || $value === '') {
        error_log("CRÍTICO: Variável de ambiente obrigatória '$key' não definida ou vazia.", 3, LOG_PATH);
        http_response_code(500);
        exit("Erro crítico de configuração do aplicativo.");
    }
    return $value;
}


// --- Configurações do Ambiente da Aplicação ---
define('ENVIRONMENT', env('APP_ENV', 'production'));
define('DEBUG_MODE', env('APP_DEBUG') === 'true');

// MODIFICAÇÃO AQUI: Garante que SITE_URL seja uma string válida.
$appUrl = env('APP_URL');
if (empty($appUrl)) {
    $appUrl = 'https://bacosearch.com'; // Fallback padrão se APP_URL não estiver no .env ou estiver vazio
}
define('SITE_URL', $appUrl); // AGORA SITE_URL SEMPRE TERÁ UM VALOR VÁLIDO E NÃO NULL

define('SITE_NAME', env('APP_NAME', 'BacoSearch'));

// --- Caminhos do Sistema ---
// ROOT_PATH deve ser definido no .env como o caminho absoluto para a raiz do seu projeto
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', env_required('ROOT_PATH')); 
}
define('CORE_PATH', ROOT_PATH . 'core/');
define('TEMPLATE_PATH', ROOT_PATH . 'templates/');
define('UPLOAD_PATH', ROOT_PATH . 'uploads/');
define('CACHE_PATH', ROOT_PATH . 'cache/');
define('MODULES_PATH', ROOT_PATH . 'modules/');

// --- Configuração do Banco de Dados ---
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env_required('DB_DATABASE'));
define('DB_USER', env_required('DB_USERNAME'));
define('DB_PASS', env_required('DB_PASSWORD'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));
define('DB_COLLATE', env('DB_COLLATION', 'utf8mb4_unicode_ci'));

// --- Configurações de Localização e Internacionalização (I18n) ---
define('LANGUAGE_CONFIG', [
    'default' => 'en-us',
    'fallback' => 'en-us',
    'available' => [
        'pt-br', 'en-us', 'es-es', 'de-de', 'ru-ru', 'ja-jp', 'zh-cn',
        'fr-fr', 'it-it', 'ar-sa', 'nl-nl', 'pl-pl'
    ],
    'iso_map' => [
        'pt-br' => 'br', 'en-us' => 'us', 'es-es' => 'es', 'de-de' => 'de',
        'ru-ru' => 'ru', 'ja-jp' => 'jp', 'zh-cn' => 'cn', 'fr-fr' => 'fr',
        'it-it' => 'it', 'ar-sa' => 'sa', 'nl-nl' => 'nl', 'pl-pl' => 'pl',
    ],
    'name_map' => [
        'pt-br' => 'Português', 'en-us' => 'English', 'es-es' => 'Español',
        'de-de' => 'Deutsch', 'ru-ru' => 'Русский', 'ja-jp' => '日本語',
        'zh-cn' => '中文', 'fr-fr' => 'Français', 'it-it' => 'Italiano',
        'ar-sa' => 'العربية', 'nl-nl' => 'Nederlands', 'pl-pl' => 'Polski'
    ]
]);

// --- Configurações de Segurança ---
define('SECURITY_CONFIG', [
    'hash_algo' => PASSWORD_ARGON2ID,
    'hash_options' => [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 2
    ],
    'session_lifetime' => (int) env('SESSION_LIFETIME', 7200),
    'max_login_attempts' => (int) env('MAX_LOGIN_ATTEMPTS', 5),
    'lockout_time' => (int) env('LOCKOUT_TIME', 900)
]);

// --- Configuração do Administrador para Logs (ADICIONADO) ---
// Define as credenciais para acessar a página de logs.
define('ADMIN_CONFIG', [
    'user' => env('ADMIN_USER', 'admin'),
    'password_hash' => env('ADMIN_PASS_HASH')
]);

// --- Configurações de API ---
define('API_CONFIG', [
    'timeout' => 10,
    'Maps_API_KEY' => env_required('Maps_API_KEY'),
]);

// --- Configurações do Stripe (ADICIONADO) ---
define('STRIPE_PUBLISHABLE_KEY', env('STRIPE_PUBLISHABLE_KEY'));
define('STRIPE_SECRET_KEY', env_required('STRIPE_SECRET_KEY'));


// --- Configurações de Upload de Arquivos ---
define('UPLOAD_CONFIG', [
    'max_size' => 10485760, // 10MB em bytes
    'allowed_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],
    'max_dimensions' => ['width' => 2048, 'height' => 2048]
]);

// --- Configurações de Cache ---
define('CACHE_CONFIG', [
    'enabled' => env('CACHE_ENABLED') === 'true',
    'driver' => env('CACHE_DRIVER', 'files'),
    'lifetime' => (int) env('CACHE_LIFETIME', 3600),
    'prefix' => env('CACHE_PREFIX', 'baco_'),
]);

// --- Configurações de E-mail ---
define('MAIL_CONFIG', [
    'driver' => env('MAIL_DRIVER', 'smtp'),
    'host' => env('MAIL_HOST'),
    'port' => (int) env('MAIL_PORT', 587),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'username' => env_required('MAIL_USERNAME'),
    'password' => env_required('MAIL_PASSWORD'),
    'from' => [
        'address' => env_required('MAIL_FROM_ADDRESS'),
        'name' => env('MAIL_FROM_NAME', 'BacoSearch')
    ]
]);

// ADICIONADO: Define o email de contato do administrador, puxando do .env
define('ADMIN_CONTACT_EMAIL', env_required('ADMIN_CONTACT_EMAIL_ADDRESS')); 


// --- Configurações de SEO (Search Engine Optimization) ---
define('SEO_CONFIG', [
    'title_separator' => ' | ',
    'meta_description' => 'BacoSearch - Adult Services & Nightlife Directory',
    'meta_keywords' => 'escorts, adult services, nightlife, search',
    'robots' => 'index,follow',
    'google_analytics_id' => 'G-SG1L2SHV8V',
    'meta_author' => 'BacoSearch'
]);

// --- Verificações e Configurações de Tempo de Execução ---
$required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'curl'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $error_message = "CRÍTICO: Extensão PHP necessária não encontrada: '$ext'.";
        error_log($error_message, 3, LOG_PATH);
        if (DEBUG_MODE) {
            die("Erro Crítico: Extensão PHP necessária '$ext' não encontrada.");
        } else {
            http_response_code(500);
            exit("Erro de configuração do servidor.");
        }
    }
}