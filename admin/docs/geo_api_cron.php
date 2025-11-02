<?php
/**
 * /admin/docs/geo_api_cron.php
 * Versão DEBUG AJUSTADA para rodar no CRON (HostGator).
 * Última atualização: 28/08/2025
 */

declare(strict_types=1);
date_default_timezone_set('UTC');

// --- Caminho absoluto do projeto ---
define('ROOT_PATH', '/home4/chefej82/bacosearch.com');
chdir(ROOT_PATH);

// --- Arquivo de log ---
$logFile = ROOT_PATH . '/admin/docs/geo_api_cron.log';

// --- Função utilitária ---
function write_log(string $message, string $file): void {
    file_put_contents($file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

// --- Configuração de erros ---
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $logFile);

// --- Início do log ---
file_put_contents($logFile, "==================================================\n", FILE_APPEND);
write_log("CRON iniciado", $logFile);

try {
    // Verifica bootstrap
    $bootstrapPath = ROOT_PATH . '/core/bootstrap.php';
    write_log("Tentando incluir: " . $bootstrapPath, $logFile);

    if (!file_exists($bootstrapPath)) {
        write_log("ERRO: bootstrap.php não encontrado!", $logFile);
        exit(1);
    }
    if (!is_readable($bootstrapPath)) {
        write_log("ERRO: bootstrap.php não legível!", $logFile);
        exit(1);
    }

    require_once $bootstrapPath;
    write_log("Bootstrap incluído com sucesso.", $logFile);

    // Testa função
    if (!function_exists('getDBConnection')) {
        write_log("ERRO: função getDBConnection() não encontrada após bootstrap!", $logFile);
        exit(1);
    }

    // Testa conexão
    $pdo = getDBConnection();
    if ($pdo instanceof PDO) {
        write_log("Conexão com o banco de dados estabelecida com sucesso.", $logFile);
    } else {
        write_log("ERRO: getDBConnection() não retornou PDO válido.", $logFile);
        exit(1);
    }

    // Se chegou até aqui, cron está OK
    write_log("Script de teste finalizado sem erros.", $logFile);
} catch (Throwable $e) {
    $msg = "ERRO FATAL: " . $e->getMessage() .
           " no arquivo " . $e->getFile() .
           " na linha " . $e->getLine();
    write_log($msg, $logFile);
}

// --- Fim do log ---
write_log("CRON finalizado", $logFile);
