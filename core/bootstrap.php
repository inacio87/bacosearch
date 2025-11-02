<?php
/**
 * /core/bootstrap.php - VERSÃO HARDENED + AUTOBAN + COMPAT. SCHEMA (AJUSTADA)
 * Última atualização: 14/08/2025
 *
 * Responsabilidades:
 * 1) Sessão segura + carregamento de config/functions/helpers.
 * 2) Gatekeeper anti-bot (early), rate-limit leve e bloqueios de rotas maliciosas.
 * 3) Mudança manual de idioma (GET ?lang=xx ou POST language) com persistência em cookie.
 * 4) Gestão de erros/exceções, logs e resposta API/HTML.
 * 5) Conexão BD; tracking de visitors e page_views; consent; geolocations.
 * 6) Autoban em bot_ips com ban_until.
 */

if (defined('CORE_BOOTSTRAP_LOADED')) { return; }
if (!defined('IN_BACOSEARCH')) {
    define('IN_BACOSEARCH', true);
}
define('CORE_BOOTSTRAP_LOADED', true);

ob_start();
date_default_timezone_set('Europe/Lisbon');

// ======================================================================
// Carregamentos centrais
// ======================================================================
require_once __DIR__ . '/config.php';
// Composer autoload (for external libs like PHPMailer, Stripe, etc.)
// Loaded early to make classes available to included files.
if (defined('ROOT_PATH')) {
    $composerAutoload = rtrim(ROOT_PATH, '/\\') . '/vendor/autoload.php';
    if (is_file($composerAutoload)) {
        require_once $composerAutoload;
    }
}
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/search_helpers.php';

// ======================================================================
// Sessão segura (simples e estável)
// ======================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SECURITY_CONFIG['session_lifetime'] ?? 7200,
        'path'     => '/',
        'domain'   => parse_url(SITE_URL, PHP_URL_HOST) ?: '',
        'secure'   => (strpos(SITE_URL, 'https://') === 0),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
// ======================================================================
// Age Gate (guardião) – roda bem cedo
// ======================================================================
require_once __DIR__ . '/age_gate_guard.php';
// ======================================================================
// Funções auxiliares locais
// ======================================================================
function isApiRequest() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return (strpos($uri, '/api/') === 0 || strpos($uri, '/admin/api/') === 0
        || strpos($accept, 'application/json') !== false);
}
function uaIsKnownSearchEngine($ua) {
    $ua = strtolower($ua ?? '');
    $patterns = [
        'googlebot','bingbot','yandexbot','ahrefsbot','mj12bot','semrushbot',
        'slurp','duckduckbot','applebot','twitterbot','facebookexternalhit',
        'linkedinbot','petalbot','baiduspider','seznambot'
    ];
    foreach ($patterns as $p) {
        if ($ua !== '' && strpos($ua, $p) !== false) return true;
    }
    return false;
}

// ======================================================================
// GATEKEEPER ANTIBOT (EARLY) — bloqueia lixo antes de consumir recursos
// ======================================================================
$REQ_URI     = $_SERVER['REQUEST_URI'] ?? '/';
$UA          = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ACCEPT      = $_SERVER['HTTP_ACCEPT'] ?? '';
$ACCEPT_LANG = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
$METHOD      = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$current_ip  = getClientIp();
$ua_lc       = strtolower($UA);

// nega rotas que nunca existiram no projeto
$denyPaths = [
  '/wp-admin', '/wp-login', '/wp-content', '/wp-includes',
  '/xmlrpc.php', '/.env', '/.git', '/vendor/', '/phpinfo',
  '/.aws/', '/.ssh/', '/alfacgiapi/', '/ALFA_DATA/'
];
foreach ($denyPaths as $p) {
    if (stripos($REQ_URI, $p) !== false) {
        http_response_code(403);
        exit;
    }
}

// headers "mínimos humanos": permitir bots de busca passarem
if (
    (empty($ACCEPT) || empty($ACCEPT_LANG)) &&
    strpos($REQ_URI, '/api/') !== 0 &&
    !uaIsKnownSearchEngine($UA)
) {
    http_response_code(403);
    exit;
}

// UA inválido ou típico de headless/scraper (healthcheck passa)
$badUa = [
  'curl','wget','python','go-http-client','libhttp','java',
  'node-fetch','httpclient','scrapy','spider','crawler',
  'headlesschrome','phantomjs','puppeteer'
];
$isHealth = isset($_GET['health']) || strpos($REQ_URI, '/health') === 0;
foreach ($badUa as $frag) {
    if ($UA === '' || strpos($ua_lc, $frag) !== false) {
        if (!$isHealth) { http_response_code(403); exit; }
    }
}

// rate-limit leve via sessão (10s janela; 40 req)
$k = 'rate_'.$current_ip;
$now = time();
if (!isset($_SESSION[$k])) $_SESSION[$k] = ['t'=>$now, 'c'=>0];
$bucket =& $_SESSION[$k];
if ($now - $bucket['t'] <= 10) {
    $bucket['c']++;
    if ($bucket['c'] > 40) {
        header('Retry-After: 20');
        http_response_code(429);
        exit('Too Many Requests');
    }
} else {
    $bucket = ['t'=>$now,'c'=>1];
}

// ======================================================================
// TROCA DE IDIOMA (GET ?lang=xx OU POST language) + persistência em cookie
// ======================================================================
$requestedLang = null;
if (isset($_GET['lang'])) {
    $requestedLang = strtolower(trim($_GET['lang']));
} elseif ($METHOD === 'POST' && isset($_POST['language'])) {
    $requestedLang = strtolower(trim($_POST['language']));
}

if ($requestedLang) {
    try {
        $pdoTmp = getDBConnection();
        $stmt = $pdoTmp->query("SELECT code FROM languages WHERE is_active = 1");
        $available = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'code');
        if (in_array($requestedLang, $available, true)) {
            $_SESSION['language'] = $requestedLang;
            $_SESSION['language_manually_set'] = true;

            setcookie(
                'lang', $requestedLang,
                [
                    'expires'  => time() + 31536000, // 1 ano
                    'path'     => '/',
                    'domain'   => parse_url(SITE_URL, PHP_URL_HOST) ?: '',
                    'secure'   => (strpos(SITE_URL, 'https://') === 0),
                    'httponly' => false,
                    'samesite' => 'Lax',
                ]
            );
        }
    } catch (Throwable $e) {
        // silencioso — não deve bloquear navegação
    }
    // redireciona para limpar a query (?lang=...) ou o POST
    $clean = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    header('Location: ' . $clean, true, 302);
    exit;
}

// Se não houver em sessão, herda do cookie 'lang'
if (empty($_SESSION['language']) && !empty($_COOKIE['lang'])) {
    $_SESSION['language'] = $_COOKIE['lang'];
    $_SESSION['language_manually_set'] = true;
}

// ======================================================================
// Gestão de erros/exceções
// ======================================================================
if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
    ini_set('display_errors', 'Off');
    ini_set('display_startup_errors', 'Off');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
} else {
    ini_set('display_errors', 'On');
    ini_set('display_startup_errors', 'On');
    error_reporting(E_ALL);
}
ini_set('log_errors', 'On');
ini_set('error_log', LOG_PATH);

function handleFatalError() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
        $logMessage = sprintf(
            "[%s] FATAL ERROR: %s in %s on line %d | URI: %s | IP: %s\n",
            date("Y-m-d H:i:s"),
            $error['message'],
            $error['file'],
            $error['line'],
            $_SERVER['REQUEST_URI'] ?? 'N/A',
            $_SERVER['REMOTE_ADDR'] ?? 'N/A'
        );
        error_log($logMessage, 3, LOG_PATH);

        while (ob_get_level() > 0) { ob_end_clean(); }

        if (isApiRequest()) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
        } else {
            http_response_code(500);
            if (defined('TEMPLATE_PATH') && file_exists(TEMPLATE_PATH . '/errors/500.php')) {
                include TEMPLATE_PATH . '/errors/500.php';
            } else {
                echo "<h1>Erro Interno do Servidor</h1><p>Tente novamente mais tarde.</p>";
            }
        }
        exit;
    }
}
function handleException($exception) {
    $logMessage = sprintf(
        "[%s] UNCAUGHT EXCEPTION: %s in %s on line %d | URI: %s | IP: %s\nStack Trace:\n%s\n",
        date("Y-m-d H:i:s"),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $_SERVER['REQUEST_URI'] ?? 'N/A',
        $_SERVER['REMOTE_ADDR'] ?? 'N/A',
        $exception->getTraceAsString()
    );
    error_log($logMessage, 3, LOG_PATH);

    while (ob_get_level() > 0) { ob_end_clean(); }

    if (isApiRequest()) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
    } else {
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
            http_response_code(500);
            if (defined('TEMPLATE_PATH') && file_exists(TEMPLATE_PATH . '/errors/500.php')) {
                include TEMPLATE_PATH . '/errors/500.php';
            } else {
                echo "<h1>Erro Interno do Servidor</h1><p>Tente novamente mais tarde.</p>";
            }
        } else {
            http_response_code(500);
            echo "<pre><strong>UNCAUGHT EXCEPTION:</strong>\n" . htmlspecialchars($logMessage) . "</pre>";
        }
    }
    exit;
}
register_shutdown_function('handleFatalError');
set_exception_handler('handleException');

// ======================================================================
// Conexão BD
// ======================================================================
try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    log_system_error("Falha CRÍTICA na conexão com o banco: " . $e->getMessage(), 'CRITICAL', 'db_connection_failure', $_SESSION['visitor_db_id'] ?? null);
    handleException($e);
    exit;
}

// ======================================================================
// Compatibilidade de schema (descoberta de coluna país em visitors)
// ======================================================================
$VIS_COUNTRY_COL = 'location_country';
try {
    $chk = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='visitors' AND COLUMN_NAME IN ('location_country','country_code') LIMIT 1");
    $chk->execute();
    $col = $chk->fetchColumn();
    if (!empty($col)) { $VIS_COUNTRY_COL = $col; }
} catch (Throwable $e) {
    // segue com default
}

// ======================================================================
// Tracking visitante e page views
// ======================================================================
$user_agent       = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$current_page_url = $_SERVER['REQUEST_URI'] ?? 'N/A';
$referrer_url     = $_SERVER['HTTP_REFERER'] ?? 'direct';
$isBot            = 0;
$detection_reason = '';
$visitor_db_id    = null;

// visitor_cookie_id persistente (1 ano)
if (empty($_SESSION['visitor_cookie_id'])) {
    if (!empty($_COOKIE['visitor_cookie_id'])) {
        $_SESSION['visitor_cookie_id'] = $_COOKIE['visitor_cookie_id'];
    } else {
        $_SESSION['visitor_cookie_id'] = uniqid('bs_', true);
        setcookie(
            'visitor_cookie_id', $_SESSION['visitor_cookie_id'],
            [
                'expires'  => time() + 31536000,
                'path'     => '/',
                'domain'   => parse_url(SITE_URL, PHP_URL_HOST) ?: '',
                'secure'   => (strpos(SITE_URL, 'https://') === 0),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }
}
$visitor_cookie_id = $_SESSION['visitor_cookie_id'];

// detecção por UA
$botUserAgentPatterns = [
    '~^$|unknown~i',
    '~bot|spider|crawl|scraper|curl|wget|python|java|go-http-client|axios|http client|node-fetch|postman~i',
    '~headlesschrome|phantomjs|puppeteer~i',
    '~meta-externalagent|Expanse|ALittle Client|fasthttp~i',
    '~Googlebot|Bingbot|YandexBot|AhrefsBot|MJ12bot|SemrushBot|Slurp|DuckDuckBot~i',
    '~Amazonbot|Applebot|FacebookExternalHit|Twitterbot~i'
];
if (empty($user_agent) || $user_agent === 'unknown') {
    $isBot = 1; $detection_reason = "User-Agent vazio ou desconhecido";
} else {
    foreach ($botUserAgentPatterns as $pattern) {
        if (preg_match($pattern, $user_agent)) { $isBot=1; $detection_reason="Padrão de User-Agent: ".$pattern; break; }
    }
}

// URL suspeitas (apenas literals, mais rápido)
$suspiciousUrlFragments = [
    '/wp-admin','/wp-login','/wp-content','/wp-includes','/wp-plain','/wordpress','/xmlrpc.php',
    '/backup','/.env','/config.php','/.git','/.aws/','/.ssh/','id_ecdsa',
    '/alfacgiapi/','/ALFA_DATA/','/phpinfo','/vendor/','/_profiler/','/console','/phpmyadmin/','/myadmin/',
    '.sql','.zip','.tar.gz','.bak','.log','.old','.json','.txt'
];
if (!$isBot) {
    foreach ($suspiciousUrlFragments as $frag) {
        if (strpos($current_page_url, $frag) !== false) { $isBot=1; $detection_reason="URL Suspeita: ".$frag; break; }
    }
}
// headers mínimos (já filtrado antes, aqui é defesa em profundidade)
if (!$isBot && (empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) || empty($_SERVER['HTTP_ACCEPT']))) {
    $isBot = 1; $detection_reason = "Falta de Headers (Accept-Language ou Accept)";
}

$device_type = 'desktop';
if (strpos($ua_lc, 'mobile') !== false) $device_type = 'mobile';
elseif (strpos($ua_lc, 'tablet') !== false) $device_type = 'tablet';

$geoData = [];                // manter fora do try para uso adiante
$is_geolocation_re_run = false;
$current_session_stage = 'session';

try {
    $pdo->beginTransaction();

    $stmt_check_visitor = $pdo->prepare("SELECT id, session_stage, city, region, postal_code, latitude, longitude, location_source, location_accuracy_level, location_accepted, preferred_language, is_bot FROM visitors WHERE cookie_id = ?");
    $stmt_check_visitor->execute([$visitor_cookie_id]);
    $visitor_data_from_db = $stmt_check_visitor->fetch(PDO::FETCH_ASSOC);

    if ($visitor_data_from_db) {
        $_SESSION['visitor_db_id'] = (int)$visitor_data_from_db['id'];
        $visitor_db_id = (int)$_SESSION['visitor_db_id'];
        $current_session_stage = $visitor_data_from_db['session_stage'];
        $isBot = ($isBot == 1 || $visitor_data_from_db['is_bot'] == 1) ? 1 : 0;

        if (!isset($_SESSION['geolocation_timestamp'])
            || (time() - $_SESSION['geolocation_timestamp'] > 86400)
            || empty($_SESSION['country_code'])
            || empty($_SESSION['city'])) {

            $geoData = getGeolocation($_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us'));
            $_SESSION['geolocation_timestamp'] = time();

            if (!isset($_SESSION['language_manually_set'])) {
                $_SESSION['language'] = $geoData['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us');
            }
            $_SESSION['city'] = $geoData['city'] ?? 'Cidade Desconhecida';
            $_SESSION['country_code'] = $geoData['country_code'] ?? null;
            $_SESSION['region'] = $geoData['region'] ?? null;
            $_SESSION['latitude'] = $geoData['latitude'] ?? null;
            $_SESSION['longitude'] = $geoData['longitude'] ?? null;
            $_SESSION['postal_code'] = $geoData['postal_code'] ?? null;
            $_SESSION['location_source'] = $geoData['source'] ?? 'ip_based';
            $_SESSION['location_accuracy_level'] = $geoData['accuracy_level'] ?? 'medium';
            $_SESSION['location_accepted'] = $geoData['location_accepted'] ?? 0;
            $_SESSION['preferred_language'] = $_SESSION['language'];

            $is_geolocation_re_run = true;
        } else {
            $geoData = [
                'language'          => $_SESSION['language'],
                'city'              => $_SESSION['city'] ?: 'Cidade Desconhecida',
                'country_code'      => $_SESSION['country_code'],
                'region'            => $_SESSION['region'],
                'latitude'          => $_SESSION['latitude'],
                'longitude'         => $_SESSION['longitude'],
                'postal_code'       => $_SESSION['postal_code'],
                'source'            => $_SESSION['location_source'],
                'accuracy_level'    => $_SESSION['location_accuracy_level'],
                'location_accepted' => $_SESSION['location_accepted'],
                'preferred_language'=> $_SESSION['preferred_language'] ?? $_SESSION['language']
            ];
        }
    } else {
        $insert_visitor_query = "INSERT INTO visitors (
            cookie_id, ip_address, user_agent, {$VIS_COUNTRY_COL}, city, region, postal_code,
            latitude, longitude, location_source, location_accuracy_level, location_accepted,
            session_stage, referrer, device_type, is_bot, preferred_language, created_at, updated_at, last_seen_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'session', ?, ?, ?, ?, NOW(), NOW(), NOW())";

        $geoData = getGeolocation(LANGUAGE_CONFIG['default'] ?? 'en-us');
        $_SESSION['geolocation_timestamp'] = time();
        $_SESSION['language'] = $_SESSION['language'] ?? ($geoData['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us'));
        $_SESSION['city'] = $geoData['city'] ?? 'Cidade Desconhecida';
        $_SESSION['country_code'] = $geoData['country_code'] ?? null;
        $_SESSION['region'] = $geoData['region'] ?? null;
        $_SESSION['latitude'] = $geoData['latitude'] ?? null;
        $_SESSION['longitude'] = $geoData['longitude'] ?? null;
        $_SESSION['postal_code'] = $geoData['postal_code'] ?? null;
        $_SESSION['location_source'] = $geoData['source'] ?? 'ip_based';
        $_SESSION['location_accuracy_level'] = $geoData['accuracy_level'] ?? 'medium';
        $_SESSION['location_accepted'] = $geoData['location_accepted'] ?? 0;
        $_SESSION['preferred_language'] = $_SESSION['language'];

        $insert_visitor_params = [
            $visitor_cookie_id, $current_ip, $user_agent,
            $geoData['country_code'], $geoData['city'], $geoData['region'], $geoData['postal_code'],
            $geoData['latitude'], $geoData['longitude'], $geoData['source'],
            $geoData['accuracy_level'], $geoData['location_accepted'],
            $referrer_url, $device_type, $isBot, $_SESSION['language']
        ];
        $stmt_insert_visitor = $pdo->prepare($insert_visitor_query);
        $stmt_insert_visitor->execute($insert_visitor_params);

        $_SESSION['visitor_db_id'] = (int)$pdo->lastInsertId();
        $visitor_db_id = (int)$_SESSION['visitor_db_id'];
        $current_session_stage = 'session';
    }

    // UPDATE visitor
    $update_fields = [
        'ip_address = ?', 'user_agent = ?', 'is_bot = ?', 'last_seen_at = NOW()', 'updated_at = NOW()',
        'device_type = ?', 'referrer = ?', 'session_stage = ?', 'preferred_language = ?'
    ];
    $update_params = [
        $current_ip, $user_agent, $isBot, $device_type, $referrer_url, $current_session_stage ?? 'session', $_SESSION['language']
    ];
    if ($is_geolocation_re_run) {
        $update_fields[] = "{$VIS_COUNTRY_COL} = ?";
        $update_fields[] = "city = ?";
        $update_fields[] = "region = ?";
        $update_fields[] = "postal_code = ?";
        $update_fields[] = "latitude = ?";
        $update_fields[] = "longitude = ?";
        $update_fields[] = "location_source = ?";
        $update_fields[] = "location_accuracy_level = ?";
        $update_fields[] = "location_accepted = ?";
        array_push(
            $update_params,
            $geoData['country_code'], $geoData['city'], $geoData['region'], $geoData['postal_code'],
            $geoData['latitude'], $geoData['longitude'], $geoData['source'], $geoData['accuracy_level'], $geoData['location_accepted']
        );
    }
    $update_visitor_query = "UPDATE visitors SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $update_params[] = $visitor_db_id;
    $stmt_update_visitor = $pdo->prepare($update_visitor_query);
    $stmt_update_visitor->execute($update_params);

    // log básico de bot
    if ($isBot) {
        log_system_error("Bot detectado: {$detection_reason} | UA: {$user_agent} | IP: {$current_ip} | URL: {$current_page_url}", 'NOTICE', 'bot_detection', $visitor_db_id);
    }

    // classificar maliciosidade
    $isMaliciousBot = false;
    $maliciousReasons = [
        "User-Agent vazio ou desconhecido",
        "Padrão de User-Agent: ~headlesschrome|phantomjs|puppeteer~i",
        "Falta de Headers (Accept-Language ou Accept)"
    ];
    if ($isBot) {
        foreach ($maliciousReasons as $reason) {
            if (strpos($detection_reason, $reason) !== false) { $isMaliciousBot = true; break; }
        }
        if (!$isMaliciousBot) {
            foreach ($suspiciousUrlFragments as $frag) {
                if (strpos($current_page_url, $frag) !== false) {
                    $isMaliciousBot = true;
                    $detection_reason .= " (URL maliciosa)";
                    break;
                }
            }
        }
    }

    // Autoban 24h
    if ($isMaliciousBot) {
        try {
            $banStmt = $pdo->prepare("
                INSERT INTO bot_ips (ip, reason, hit_count, last_seen_at, ban_until, ua_sample)
                VALUES (?, ?, 1, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), ?)
                ON DUPLICATE KEY UPDATE
                  reason = VALUES(reason),
                  hit_count = hit_count + 1,
                  last_seen_at = NOW(),
                  ban_until = GREATEST(IFNULL(ban_until, NOW()), DATE_ADD(NOW(), INTERVAL 1 DAY)),
                  ua_sample = VALUES(ua_sample)
            ");
            $banStmt->execute([$current_ip, $detection_reason, substr($user_agent,0,250)]);
        } catch (PDOException $e) {
            log_system_error("Falha ao registrar ban de bot: ".$e->getMessage(), 'ERROR', 'bot_autoban', $visitor_db_id);
        }
        $pdo->commit();
        http_response_code(403);
        exit;
    }

    // page_view (ignora admin)
    $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    if (!$isAdmin && !$isBot) {
        try {
            $insert_page_view_stmt = $pdo->prepare("INSERT INTO page_views (
                visitor_id, page_url, referrer_url, ip_address, country_code, device_type, is_bot_view, visit_timestamp
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $insert_page_view_stmt->execute([
                $visitor_db_id, $current_page_url, $referrer_url, $current_ip,
                $geoData['country_code'] ?? null, $device_type, $isBot
            ]);
        } catch (PDOException $e) {
            log_system_error("Erro ao registrar page view: " . $e->getMessage(), 'ERROR', 'page_view_tracking', $visitor_db_id);
        }
    } else if ($isAdmin) {
        log_system_error("PageView de Admin ignorada (visitor_id: {$visitor_db_id}, URL: {$current_page_url})", 'INFO', 'admin_pageview_ignored', $visitor_db_id);
    }

    // consent cookie → evento + avanço de stage
    if ($visitor_db_id && isset($_COOKIE['consent_given']) && $_COOKIE['consent_given'] === 'true') {
        $stmt_check_stage = $pdo->prepare("SELECT session_stage FROM visitors WHERE id = ?");
        $stmt_check_stage->execute([$visitor_db_id]);
        $current_db_stage = $stmt_check_stage->fetchColumn();
        if ($current_db_stage === 'session') {
            try {
                $pdo->prepare("UPDATE visitors SET session_stage='consented', updated_at=NOW() WHERE id=?")->execute([$visitor_db_id]);
                $insert_consent_event_stmt = $pdo->prepare("INSERT INTO events (visitor_id, event_type, event_name, event_data, created_at) VALUES (?, ?, ?, ?, NOW())");
                $consent_event_data = [
                    'ip' => $current_ip, 'ua' => $user_agent, 'referrer' => $referrer_url,
                    'page_url' => $current_page_url, 'is_bot' => $isBot, 'consent_status' => 'accepted', 'session_id' => session_id()
                ];
                $insert_consent_event_stmt->execute([$visitor_db_id, 'consent', 'modal_accepted', json_encode($consent_event_data)]);
            } catch (PDOException $e) {
                log_system_error("Erro ao atualizar stage para 'consented' ou registrar evento de consentimento: " . $e->getMessage(), 'ERROR', 'consent_update', $visitor_db_id);
            }
        }
    }

    // login de accounts (atualiza last_login_at)
    if (isset($_SESSION['account_id']) && $_SESSION['account_id'] !== null) {
        $account_id = (int)$_SESSION['account_id'];
        try {
            $pdo->prepare("UPDATE accounts SET last_login_at = NOW() WHERE id = ?")->execute([$account_id]);
        } catch (PDOException $e) {
            log_system_error("Erro ao atualizar last_login_at account_id: {$account_id}: " . $e->getMessage(), 'ERROR', 'account_login_update', $visitor_db_id);
        }
    }

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    log_system_error("Erro geral no rastreamento: " . $e->getMessage() . " | URL: {$current_page_url}", 'ERROR', 'visitor_tracking_main', $visitor_db_id ?? null);
}

// ======================================================================
// user_geolocations — fora da transação principal
// ======================================================================
if (!empty($geoData) && ($geoData['location_accepted'] ?? 0) === 1 && !empty($geoData['country_code']) && !empty($geoData['city'])) {
    try {
        $stmt_check_geo = $pdo->prepare("SELECT id FROM user_geolocations WHERE country_iso_code = ? AND region_name = ? AND city_name = ? AND postal_code = ?");
        $stmt_check_geo->execute([$geoData['country_code'], $geoData['region'] ?? null, $geoData['city'], $geoData['postal_code'] ?? null]);
        $existing_geo = $stmt_check_geo->fetch(PDO::FETCH_ASSOC);

        if ($existing_geo) {
            $pdo->prepare("UPDATE user_geolocations SET visit_count = visit_count + 1, last_updated = NOW() WHERE id = ?")->execute([$existing_geo['id']]);
        } else {
            $pdo->prepare("INSERT INTO user_geolocations (
                country_iso_code, region_name, city_name, postal_code, latitude, longitude, accuracy_level,
                visit_count, unique_visitors_count, first_recorded, last_updated
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, NOW(), NOW())")->execute([
                $geoData['country_code'], $geoData['region'] ?? null, $geoData['city'], $geoData['postal_code'] ?? null,
                $geoData['latitude'], $geoData['longitude'], $geoData['accuracy_level']
            ]);
        }
    } catch (PDOException $e) {
        log_system_error("Erro ao registrar/atualizar user_geolocations: " . $e->getMessage(), 'ERROR', 'user_geolocation_tracking', $visitor_db_id ?? null);
    }
}

// ======================================================================
// Helpers locais (mapeamentos) — usados por páginas
// ======================================================================
function map_db_to_radio_value($db_value) {
    if ($db_value === 1 || $db_value === '1' || $db_value === true || strtolower((string)$db_value) === 'yes') return 'yes';
    if ($db_value === 0 || $db_value === '0' || strtolower((string)$db_value) === 'no') return 'no';
    return 'not_specified';
}
function map_service_value($db_value) {
    $db_value = strtolower(trim((string)$db_value));
    if (in_array($db_value, ['do', 'yes', '1'], true)) return 'do';
    if (in_array($db_value, ['dont', 'no', '0'], true)) return 'no';
    return 'negotiable';
}
