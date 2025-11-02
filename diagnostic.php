<?php
/**
 * Diagnóstico de Headers e Detecção
 * Acesse: https://bacosearch.com/diagnostic.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO BACOSEARCH ===\n\n";

echo "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . "\n";
echo "User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'VAZIO') . "\n";
echo "Accept: " . ($_SERVER['HTTP_ACCEPT'] ?? 'VAZIO') . "\n";
echo "Accept-Language: " . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'VAZIO') . "\n";
echo "Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . "\n\n";

echo "=== TODOS OS HEADERS ===\n";
foreach (getallheaders() as $name => $value) {
    echo "$name: $value\n";
}

echo "\n=== DETECÇÃO DE BOT ===\n";
$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$isBot = 0;
$reasons = [];

if (empty($_SERVER['HTTP_USER_AGENT']) || $_SERVER['HTTP_USER_AGENT'] === 'unknown') {
    $isBot = 1;
    $reasons[] = "User-Agent vazio ou desconhecido";
}

$botPatterns = [
    '~bot|spider|crawl|scraper|curl|wget|python|java|go-http-client~i',
    '~headlesschrome|phantomjs|puppeteer~i',
];
foreach ($botPatterns as $pattern) {
    if (preg_match($pattern, $ua)) {
        $isBot = 1;
        $reasons[] = "Padrão UA: $pattern";
    }
}

if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) || empty($_SERVER['HTTP_ACCEPT'])) {
    $isBot = 1;
    $reasons[] = "Falta Accept-Language ou Accept";
}

echo "É BOT? " . ($isBot ? "SIM" : "NÃO") . "\n";
if ($reasons) {
    echo "Razões:\n";
    foreach ($reasons as $r) {
        echo "  - $r\n";
    }
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
