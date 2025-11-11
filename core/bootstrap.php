<?php
// Bootstrap básico do BacoSearch (stub)
// - Liga exibição de erros no ambiente de dev
// - Carrega autoload do Composer se existir
// - Inicia sessão
// - Define timezone padrão

// Habilitar errors em dev (ajuste conforme ambiente)
if (!defined('BACOSEARCH_ENV')) {
    define('BACOSEARCH_ENV', 'dev');
}
if (BACOSEARCH_ENV === 'dev') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Carregar variáveis de ambiente de .env
require_once __DIR__ . '/env.php';

// Timezone padrão
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('America/Sao_Paulo');
}

// Composer autoload (se existir)
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helpers simples
if (!function_exists('asset')) {
    function asset(string $path): string {
        $path = '/' . ltrim($path, '/');
        return $path;
    }
}

if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
