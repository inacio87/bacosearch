<?php
/**
 * /core/functions.php - Funções e Classes Auxiliares Globais (VERSÃO HARDENED)
 *
 * Incluído por core/bootstrap.php.
 * Última atualização: 10/08/2025
 */

if (!defined('IN_BACOSEARCH')) {
    exit('Acesso direto não permitido.');
}

// ---------------------------------------------------------------------
// PHPMailer (instalação manual)
// ---------------------------------------------------------------------
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once dirname(__DIR__) . '/vendor/phpmailer/src/Exception.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/src/PHPMailer.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/src/SMTP.php';

// Cache em memória do processo para traduções
$translationsCache = [];

// ---------------------------------------------------------------------
// 1) CONEXÃO, LOG E UTILITÁRIOS
// ---------------------------------------------------------------------
function getDBConnection(): PDO {
    static $dbConnection = null;
    if ($dbConnection === null) {
        $dsn = sprintf("mysql:host=%s;dbname=%s;charset=%s", DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => sprintf("SET NAMES '%s' COLLATE '%s'", DB_CHARSET, DB_COLLATE)
        ];
        try {
            $dbConnection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // tenta registrar no arquivo se DB falhar
            error_log("Falha na conexão com o banco de dados: " . $e->getMessage(), 3, LOG_PATH);
            exit('Erro interno do servidor: Falha na conexão com o banco de dados.');
        }
    }
    return $dbConnection;
}

function getClientIp(): string {
    // ordem prioriza cabeçalhos de proxy/CDN confiáveis
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Log estruturado em DB (com fallback para arquivo).
 * Inclui visitor_id quando disponível.
 */
function log_system_error(string $message, string $level = 'ERROR', ?string $context = null, ?int $visitorDbId = null): void {
    $valid = ['INFO','WARNING','ERROR','CRITICAL'];
    $level = in_array(strtoupper($level), $valid, true) ? strtoupper($level) : 'ERROR';
    try {
        $db = getDBConnection();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
        $request_uri = $_SERVER['REQUEST_URI'] ?? 'N/A';

        if ($visitorDbId === null && isset($_SESSION['visitor_db_id'])) {
            $visitorDbId = (int)$_SESSION['visitor_db_id'];
        }

        $stmt = $db->prepare("
            INSERT INTO system_logs (level, message, context, visitor_id, ip_address, user_agent, request_uri, created_at)
            VALUES (:level, :message, :context, :visitor_id, :ip_address, :user_agent, :request_uri, NOW())
        ");
        $stmt->execute([
            ':level'       => $level,
            ':message'     => $message,
            ':context'     => $context,
            ':visitor_id'  => $visitorDbId,
            ':ip_address'  => $ip_address,
            ':user_agent'  => $user_agent,
            ':request_uri' => $request_uri
        ]);
    } catch (Throwable $e) {
        error_log("CRITICAL DB LOG FAILURE: " . $e->getMessage() . " | Original: " . $message, 3, LOG_PATH);
    }
}

// Helperzinho
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// ---------------------------------------------------------------------
// 2) E-MAIL (PHPMailer) + rastreio
// ---------------------------------------------------------------------
/**
 * Envia um email HTML a partir de um template, com tracking de aberturas e cliques.
 * Regista em emails_sent; pode associar a account/visitor/lead/event.
 */
function send_email(
    string $to,
    string $subject,
    string $templatePath,
    array $templateData = [],
    string $emailType = 'generic',
    ?int $accountId = null,
    ?int $visitorId = null,
    ?int $eventId = null,
    ?int $leadId = null
): bool {
    // valida e carrega template
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        log_system_error("Tentativa de enviar email inválido: {$to}", 'ERROR', 'invalid_email_address');
        return false;
    }
    $absoluteTemplatePath = rtrim(ROOT_PATH, '/') . '/' . ltrim($templatePath, '/');
    if (!is_file($absoluteTemplatePath)) {
        log_system_error("Template de e-mail não encontrado em {$absoluteTemplatePath}", 'CRITICAL', 'email_template_error');
        return false;
    }
    $body = @file_get_contents($absoluteTemplatePath);
    if ($body === false) {
        log_system_error("Falha ao ler template de e-mail: {$absoluteTemplatePath}", 'CRITICAL', 'email_template_read');
        return false;
    }

    // tracking
    try {
        $trackingPixelId = bin2hex(random_bytes(32));
    } catch (Throwable $t) {
        $trackingPixelId = uniqid('trk_', true);
    }
    $trackingPixelUrl = rtrim(SITE_URL, '/') . '/api/track_email_open.php?id=' . urlencode($trackingPixelId);
    $emailBatchId = uniqid('email_', true);

    // CSS inline
    $cssFilePath = ROOT_PATH . '/assets/css/mail.css';
    $cssContent = '';
    if (is_file($cssFilePath)) {
        $cssContent = @file_get_contents($cssFilePath) ?: '';
    } else {
        log_system_error("CSS do e-mail não encontrado: {$cssFilePath}", 'WARNING', 'email_css_missing');
    }
    $body = preg_replace('/<link[^>]+href=["\']\{\{mail_css_url\}\}["\'][^>]*>/i', '', $body);
    if ($cssContent !== '') {
        $body = str_ireplace('</head>', '<style type="text/css">'.$cssContent.'</style></head>', $body);
    }

    // placeholders
    $templateData['site_url'] = SITE_URL;
    $templateData['site_name'] = SITE_NAME;
    $templateData['tracking_pixel_url'] = $trackingPixelUrl;

    foreach ($templateData as $key => $value) {
        if (is_scalar($value)) {
            $body = str_replace('{{' . $key . '}}', (string)$value, $body);
        }
    }

    // rastreamento de cliques (ignora internos/mailto/tel/pixel)
    $body = preg_replace_callback('/<a\s+(?:[^>]*?\s+)?href=["\']([^"\']+)["\']/', function($m) use ($trackingPixelId, $emailBatchId) {
        $originalUrl = $m[1];
        if (strpos($originalUrl, SITE_URL) !== false
            || strpos($originalUrl, 'mailto:') === 0
            || strpos($originalUrl, 'tel:') === 0
            || strpos($originalUrl, '/api/track_email_open.php') !== false) {
            return $m[0];
        }
        $redirectUrl = rtrim(SITE_URL, '/') . "/api/track_email_click.php?id=" .
            urlencode($trackingPixelId) . "&link=" . urlencode($originalUrl) . "&batch=" . urlencode($emailBatchId);
        return str_replace($originalUrl, $redirectUrl, $m[0]);
    }, $body);

    // prepara envio
    $db = getDBConnection();

    // tenta obter visitor da sessão se não passado
    if ($visitorId === null && isset($_SESSION['visitor_db_id'])) {
        $visitorId = (int)$_SESSION['visitor_db_id'];
    }

    try {
        // log em emails_sent (sem transação aqui)
        $stmt_log_email = $db->prepare("
            INSERT INTO emails_sent
              (account_id, lead_id, event_id, visitor_id, email_type, recipient_email, recipient_name, subject, tracking_pixel_id, details)
            VALUES
              (:account_id, :lead_id, :event_id, :visitor_id, :email_type, :recipient_email, :recipient_name, :subject, :tracking_pixel_id, :details)
        ");
        $emailDetails = json_encode([
            'email_batch_id' => $emailBatchId,
            'template' => $templatePath,
            'template_data_preview' => array_slice($templateData, 0, 5),
        ]);
        $stmt_log_email->execute([
            ':account_id'        => $accountId,
            ':lead_id'           => $leadId,
            ':event_id'          => $eventId,
            ':visitor_id'        => $visitorId,
            ':email_type'        => $emailType,
            ':recipient_email'   => $to,
            ':recipient_name'    => $templateData['user_name'] ?? $to,
            ':subject'           => $subject,
            ':tracking_pixel_id' => $trackingPixelId,
            ':details'           => $emailDetails
        ]);
        $email_sent_id = $db->lastInsertId();

        // PHPMailer
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_CONFIG['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_CONFIG['username'];
        $mail->Password   = MAIL_CONFIG['password'];
        $mail->SMTPSecure = MAIL_CONFIG['encryption'];
        $mail->Port       = MAIL_CONFIG['port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_CONFIG['from']['address'], MAIL_CONFIG['from']['name']);
        $mail->addAddress($to, $templateData['user_name'] ?? '');

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        $mail->send();

        if ($eventId !== null) {
            $db->prepare("UPDATE events SET email_id = :email_id WHERE id = :event_id")
               ->execute([':email_id' => $email_sent_id, ':event_id' => $eventId]);
        }
        return true;
    } catch (Throwable $e) {
        log_system_error("Falha no envio de e-mail para {$to}. Tipo: {$emailType}. PHPMailer/Exceção: " . $e->getMessage(), 'CRITICAL', 'email_sending_failure');
        return false;
    }
}

// ---------------------------------------------------------------------
// 3) TRADUÇÃO (com cache opcional APCu)
// ---------------------------------------------------------------------
function getTranslation(string $key, string $languageCode = 'en-us', string $context = 'default'): string {
    global $translationsCache;
    $cacheKey = "t|$languageCode|$context|$key";

    // APCu (se disponível)
    if (function_exists('apcu_fetch')) {
        $hit = apcu_fetch($cacheKey, $ok);
        if ($ok) { return $hit; }
    }
    if (isset($translationsCache[$cacheKey])) {
        return $translationsCache[$cacheKey];
    }

    $value = $key; // fallback
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT translation_value FROM translations WHERE translation_key = :k AND language_code = :l AND context = :c LIMIT 1");
        $stmt->execute([':k' => $key, ':l' => $languageCode, ':c' => $context]);
        $res = $stmt->fetchColumn();
        if ($res !== false) {
            $value = $res;
        } else {
            $fallback = LANGUAGE_CONFIG['default'] ?? 'en-us';
            if ($languageCode !== $fallback) {
                $stmt->execute([':k' => $key, ':l' => $fallback, ':c' => $context]);
                $res2 = $stmt->fetchColumn();
                if ($res2 !== false) {
                    $value = $res2;
                }
            }
        }
    } catch (Throwable $e) {
        log_system_error("TRANSLATION_ERROR: Falha ao buscar '{$key}': " . $e->getMessage(), 'ERROR', 'translation_system');
    }

    $translationsCache[$cacheKey] = $value;
    if (function_exists('apcu_store')) {
        // TTL curto para permitir atualizações rápidas no painel (ex.: 5 minutos)
        @apcu_store($cacheKey, $value, 300);
    }
    return $value;
}

// ---------------------------------------------------------------------
// 4) HTTP/API helpers
// ---------------------------------------------------------------------
function send_json_response(bool $success, array $data, int $http_code = 200, string $message = null): void {
    http_response_code($http_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'data'    => $data,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE | (defined('DEBUG_MODE') && DEBUG_MODE ? JSON_PRETTY_PRINT : 0));
    exit;
}

function fetch_external_api(string $url, int $timeout = 5): array {
    if (!extension_loaded('curl')) {
        throw new Exception('Server configuration error: cURL extension not loaded.');
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => 'BacoSearch-Client/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('Error communicating with external API: ' . $curlError);
    }
    if ($httpCode !== 200) {
        throw new Exception('External API returned HTTP error: ' . $httpCode . ' Response: ' . substr($response, 0, 200));
    }
    $data = json_decode($response, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response from external API. JSON Error: ' . json_last_error_msg() . ' Response: ' . substr($response, 0, 200));
    }
    return $data;
}

function fetch_google_api(string $url): array {
    $data = fetch_external_api($url, 7);
    if (!isset($data['status']) || $data['status'] !== 'OK') {
        $errorMessage = $data['error_message'] ?? 'Unknown API error.';
        throw new Exception("Google API response error: " . $errorMessage);
    }
    return $data;
}

// ---------------------------------------------------------------------
// 5) GEOLOCALIZAÇÃO
// ---------------------------------------------------------------------
function getLanguageFromCountry(?string $countryCode): string {
    $defaultLanguage = LANGUAGE_CONFIG['default'] ?? 'en-us';
    if (empty($countryCode)) return $defaultLanguage;
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT language_code FROM countries WHERE iso_code = ? LIMIT 1");
        $stmt->execute([strtoupper($countryCode)]);
        $result = $stmt->fetchColumn();
        return ($result) ?: $defaultLanguage;
    } catch (Throwable $e) {
        log_system_error("GEOLOC_DB_LANG_ERROR: País {$countryCode}: " . $e->getMessage(), 'ERROR', 'geolocation_language_db');
    }
    return $defaultLanguage;
}

function getGeolocation(string $languageCode, ?array $browserCoords = null): array {
    $defaultLocation = [
        'city' => null, 'country' => 'Unknown', 'country_code' => 'US',
        'latitude' => null, 'longitude' => null, 'source' => 'fallback',
        'language' => $languageCode, 'region' => null, 'postal_code' => null,
        'accuracy_level' => 'none', 'location_accepted' => 0
    ];

    // Prioriza coords do navegador (se tiveres front a enviar)
    if ($browserCoords && !empty($browserCoords['latitude']) && (API_CONFIG['Maps_API_KEY'] ?? null)) {
        if ($locationData = getLocationFromGoogleGeocoding((float)$browserCoords['latitude'], (float)$browserCoords['longitude'])) {
            $locationData['language'] = getLanguageFromCountry($locationData['country_code']);
            $locationData['source'] = $locationData['source'] ?? 'Google Geocoding';
            $locationData['accuracy_level'] = $locationData['accuracy_level'] ?? 'high';
            $locationData['location_accepted'] = 1;
            return $locationData;
        }
    }

    $ip = getClientIp();
    $apiProviders = [
        ['name' => 'ip-api.com', 'function' => 'getLocationFromIpApi'],
        ['name' => 'ipwho.is',   'function' => 'getLocationFromIpwhois'],
    ];

    foreach ($apiProviders as $provider) {
        if ($locationData = call_user_func($provider['function'], $ip)) {
            if (!empty($locationData['country_code'])) {
                $locationData['language'] = getLanguageFromCountry($locationData['country_code']);
                $locationData['source'] = $provider['name'];
                $locationData['accuracy_level'] = $locationData['accuracy_level'] ?? 'ip_based';
                $locationData['location_accepted'] = 0;
                return $locationData;
            }
        }
    }

    log_system_error("GEOLOC_FALLBACK: APIs de geolocalização falharam. IP: " . $ip, 'WARNING', 'geolocation_fallback_api');
    return $defaultLocation;
}

function getLocationFromGoogleGeocoding(float $lat, float $lon): ?array {
    $apiKey = API_CONFIG['Maps_API_KEY'] ?? null;
    if (!$apiKey) {
        log_system_error("Google Geocoding: Chave de API não definida.", 'WARNING', 'google_api_key_missing');
        return null;
    }
    $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lon}&key={$apiKey}";
    try {
        $data = fetch_google_api($url);
        if (!empty($data['results'])) {
            $result = $data['results'][0];
            $location = [
                'city' => null, 'country' => null, 'country_code' => null,
                'region' => null, 'postal_code' => null, 'latitude' => $lat, 'longitude' => $lon
            ];
            foreach ($result['address_components'] as $component) {
                if (in_array('locality', $component['types'])) $location['city'] = $component['long_name'];
                if (in_array('country', $component['types'])) {
                    $location['country'] = $component['long_name'];
                    $location['country_code'] = $component['short_name'];
                }
                if (in_array('administrative_area_level_1', $component['types'])) $location['region'] = $component['short_name'];
                if (in_array('postal_code', $component['types'])) $location['postal_code'] = $component['long_name'];
            }
            return array_merge($location, ['source' => 'Google Geocoding', 'accuracy_level' => 'high']);
        }
    } catch (Throwable $e) {
        log_system_error("GEOLOC_GOOGLE_ERROR: " . $e->getMessage(), 'ERROR', 'geolocation_google');
    }
    return null;
}

function getLocationFromIpApi(string $ip): ?array {
    try {
        $data = fetch_external_api("http://ip-api.com/json/{$ip}?fields=status,country,countryCode,regionName,city,zip,lat,lon");
        if (($data['status'] ?? '') === 'success') {
            return [
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? null,
                'country_code' => $data['countryCode'] ?? null,
                'region' => $data['regionName'] ?? null,
                'postal_code' => $data['zip'] ?? null,
                'latitude' => (float)($data['lat'] ?? 0),
                'longitude' => (float)($data['lon'] ?? 0),
                'accuracy_level' => 'ip_based'
            ];
        }
    } catch (Throwable $e) {
        log_system_error("GEOLOC_IPAPI_ERROR: " . $e->getMessage(), 'ERROR', 'geolocation_ipapi');
    }
    return null;
}

function getLocationFromIpwhois(string $ip): ?array {
    try {
        $data = fetch_external_api("https://ipwho.is/{$ip}");
        if ($data['success'] ?? false) {
            return [
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? null,
                'country_code' => $data['country_code'] ?? null,
                'region' => $data['region'] ?? null,
                'postal_code' => $data['postal'] ?? null,
                'latitude' => (float)($data['latitude'] ?? 0),
                'longitude' => (float)($data['longitude'] ?? 0),
                'accuracy_level' => 'ip_based'
            ];
        }
    } catch (Throwable $e) {
        log_system_error("GEOLOC_IPWHOIS_ERROR: " . $e->getMessage(), 'ERROR', 'geolocation_ipwhois');
    }
    return null;
}

// ---------------------------------------------------------------------
// 6) WRAPPERS PDO
// ---------------------------------------------------------------------
function db_fetch_all(string $query, array $params = []): array {
    try {
        $stmt = getDBConnection()->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        log_system_error("DB_FETCH_ALL_ERROR: " . $e->getMessage() . " | Query: " . substr($query, 0, 500), 'ERROR', 'db_query_error');
        if (defined('DEBUG_MODE') && DEBUG_MODE) throw $e;
        return [];
    }
}

function db_fetch_one(string $query, array $params = []): ?array {
    try {
        $stmt = getDBConnection()->prepare($query);
        $stmt->execute($params);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r !== false ? $r : null;
    } catch (PDOException $e) {
        log_system_error("DB_FETCH_ONE_ERROR: " . $e->getMessage() . " | Query: " . substr($query, 0, 500), 'ERROR', 'db_query_error');
        if (defined('DEBUG_MODE') && DEBUG_MODE) throw $e;
        return null;
    }
}

function db_execute(string $query, array $params = []): int {
    try {
        $stmt = getDBConnection()->prepare($query);
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        log_system_error("DB_EXECUTE_ERROR: " . $e->getMessage() . " | Query: " . substr($query, 0, 500), 'ERROR', 'db_execute_error');
        if (defined('DEBUG_MODE') && DEBUG_MODE) throw $e;
        return 0;
    }
}

function db_last_insert_id() {
    try {
        return getDBConnection()->lastInsertId();
    } catch (PDOException $e) {
        log_system_error("DB_LAST_INSERT_ID_ERROR: " . $e->getMessage(), 'ERROR', 'db_insert_id_error');
        if (defined('DEBUG_MODE') && DEBUG_MODE) throw $e;
        return false;
    }
}

// 7) GEO helpers (postal codes)
// ---------------------------------------------------------------------
function getCoordsFromPostalCode(string $postalCode, string $countryCode): ?array {
    try {
        $db = getDBConnection();
        // MODIFICADO: Agora consulta a tabela 'visitors' para obter os dados mais recentes de um local.
        $stmt = $db->prepare("
            SELECT latitude, longitude 
            FROM visitors 
            WHERE postal_code = ? 
              AND location_country = ?
              AND latitude IS NOT NULL 
              AND longitude IS NOT NULL
            ORDER BY last_seen_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$postalCode, $countryCode]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            return ['latitude' => (float)$r['latitude'], 'longitude' => (float)$r['longitude']];
        }
        return null;
    } catch (Throwable $e) {
        log_system_error('Erro ao buscar coordenadas do código postal na tabela visitors: ' . $e->getMessage(), 'ERROR', 'get_coords_from_visitor_postal_code');
        return null;
    }
}
// ---------------------------------------------------------------------
// 8) Log de buscas globais
// ---------------------------------------------------------------------
function logGlobalSearch(PDO $pdo, string $term): void {
    $visitor_id = $_SESSION['visitor_db_id'] ?? null; // corrigido para a chave usada no projeto
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $metadata   = json_encode(['user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null]);

    $sql = "INSERT INTO global_searches (term, visitor_id, ip_address, metadata)
            VALUES (:term, :visitor_id, :ip_address, :metadata)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':term'       => $term,
            ':visitor_id' => $visitor_id,
            ':ip_address' => $ip_address,
            ':metadata'   => $metadata
        ]);
    } catch (PDOException $e) {
        // silencioso para não quebrar UX
        error_log("Falha ao registar a busca global: " . $e->getMessage(), 3, LOG_PATH);
    }
}

// ---------------------------------------------------------------------
// 9) Slugs e normalização
// ---------------------------------------------------------------------
function create_slug(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($converted !== false) { $text = $converted; }
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text) ?? '';
    $text = preg_replace('/[\s-]+/', '-', $text) ?? '';
    $text = trim($text, '-');

    if ($text === '') {
        return 'n-a-' . time();
    }
    return $text;
}

/**
 * Normaliza string para busca (minusculas, sem acentos, sem símbolos).
 */
function normalizeString(string $string): string {
    if ($string === '') return '';
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
    $string = $converted !== false ? $converted : $string;
    $string = preg_replace('/[^a-z0-9\s]/', '', $string) ?? '';
    return strtolower(trim($string));
}
