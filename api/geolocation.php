<?php
/**
 * /api/geolocation.php - Endpoint para atualizar geolocalização via JSON POST
 *
 * Responsabilidades:
 * 1. Receber dados de geolocalização via JSON POST (e fallback GET ?fallback=ip).
 * 2. Validar e sanitizar inputs.
 * 3. Atualizar sessão ($_SESSION) com os dados recebidos.
 * 4. Persistir dados na tabela `visitors` e registrar eventos.
 * 5. Atualizar session_stage condicionalmente (session -> consented -> engaged).
 * 6. Retornar JSON com dados atualizados, incluindo language_code (countries).
 *
 * Última atualização: 27/08/2025 - compatibilidade total (sem arrow functions).
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

require_once dirname(__DIR__) . '/core/bootstrap.php';

/* ========================== HELPERS ========================== */

function pdo(): PDO {
    return getDBConnection();
}

/**
 * Retorna o código de idioma (ex.: pt-br, en-us) para um ISO de país (ex.: PT)
 */
function fetchCountryLanguageCode(PDO $pdo, ?string $iso): ?string {
    if (!$iso) return null;
    $stmt = $pdo->prepare("SELECT language_code FROM countries WHERE iso_code = ? LIMIT 1");
    $stmt->execute([$iso]);
    $lang = $stmt->fetchColumn();
    return $lang ? strtolower(trim($lang)) : null;
}

/**
 * Retorna lista de idiomas ativos (ou fallback no config).
 */
function getActiveLanguageCodesSafe(PDO $pdo): array {
    try {
        $rows = $pdo->query("SELECT code FROM languages WHERE is_active = 1")
                    ->fetchAll(PDO::FETCH_COLUMN);
        $rows = array_map(function($c) {
            return strtolower(trim($c));
        }, $rows ?: []);
        if (!empty($rows)) return $rows;
    } catch (Throwable $e) {
        // ignora erro e cai no config
    }
    return array_map('strtolower', LANGUAGE_CONFIG['available'] ?? []);
}

/**
 * Normaliza código de idioma (pt_PT -> pt-pt).
 */
function normalizeLang(?string $code): ?string {
    if (!$code) return null;
    $code = strtolower(trim($code));
    return str_replace('_', '-', $code);
}

/**
 * Escolhe idioma final (prioridade: input > countryLang > default).
 */
function decideFinalLanguage(?string $inputLang, ?string $countryLang, array $available): string {
    $inputLang   = normalizeLang($inputLang);
    $countryLang = normalizeLang($countryLang);
    $default     = normalizeLang(LANGUAGE_CONFIG['default'] ?? ($available[0] ?? 'en-us'));

    if ($inputLang && in_array($inputLang, $available, true)) {
        return $inputLang;
    }
    if ($countryLang && in_array($countryLang, $available, true)) {
        return $countryLang;
    }
    return $default ?: 'en-us';
}

/**
 * Lê fallback de IP a partir da sessão (não consulta APIs).
 */
function readIpFallbackFromSession(): array {
    return [
        'city'          => $_SESSION['city']          ?? null,
        'region'        => $_SESSION['region']        ?? null,
        'country_code'  => $_SESSION['country_code']  ?? null,
        'postal_code'   => $_SESSION['postal_code']   ?? null,
        'latitude'      => $_SESSION['latitude']      ?? null,
        'longitude'     => $_SESSION['longitude']     ?? null,
        'accuracy'      => $_SESSION['accuracy']      ?? null,
        'accuracy_level'=> $_SESSION['accuracy_level']?? 'ip',
        'source'        => 'ip',
        'location_accepted' => 0
    ];
}

/* ====================== RATE LIMIT E PRÉ-VALIDAÇÕES ====================== */

$visitor_db_id = $_SESSION['visitor_db_id'] ?? null;
if ($visitor_db_id === null) {
    $msg = "CRITICAL: visitor_db_id não definido.";
    error_log($msg);
    echo json_encode(['success' => false, 'message' => 'Erro de inicialização do visitante.']);
    exit;
}

$ip = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$rate_key  = 'geo_rate_' . $ip;
$rate_time = $_SESSION[$rate_key . '_time'] ?? 0;
$rate_count= $_SESSION[$rate_key] ?? 0;

if (time() - $rate_time < 300) {
    $rate_count++;
    if ($rate_count > 30) {
        error_log("Rate limit excedido para IP: $ip");
        echo json_encode(['success' => false, 'message' => 'Limite de requisições excedido.']);
        exit;
    }
} else {
    $rate_count = 1;
}
$_SESSION[$rate_key]       = $rate_count;
$_SESSION[$rate_key.'_time']= time();

/* ============================ SUPORTE A GET (fallback=ip) ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $fallback = isset($_GET['fallback']) ? strtolower((string)$_GET['fallback']) : null;
    if ($fallback === 'ip') {
        try {
            $pdo = pdo();
            $availableLangs = getActiveLanguageCodesSafe($pdo);
            $countryIso = $_SESSION['country_code'] ?? null;
            $countryLang = fetchCountryLanguageCode($pdo, $countryIso);

            $finalLang = !empty($_SESSION['language_manually_set'])
                ? $_SESSION['language']
                : decideFinalLanguage($_SESSION['language'] ?? null, $countryLang, $availableLangs);

            $data = readIpFallbackFromSession();
            $data['language_code'] = $countryLang;
            $data['language']      = $finalLang;

            echo json_encode([
                'success' => true,
                'message' => 'Geolocation (IP fallback) lida da sessão.',
                'data'    => $data
            ]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Client Error: ' . $e->getMessage()]);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

/* ============================ FLUXO POST ============================ */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }

    $pdo = pdo();
    $availableLangs = getActiveLanguageCodesSafe($pdo);

    $data = [];
    if (isset($input['latitude'])) $data['latitude'] = filter_var($input['latitude'], FILTER_VALIDATE_FLOAT);
    if (isset($input['longitude'])) $data['longitude'] = filter_var($input['longitude'], FILTER_VALIDATE_FLOAT);
    if (isset($input['city'])) $data['city'] = htmlspecialchars(trim(substr($input['city'], 0, 100)), ENT_QUOTES, 'UTF-8');
    if (isset($input['region'])) $data['region'] = htmlspecialchars(trim(substr($input['region'], 0, 100)), ENT_QUOTES, 'UTF-8');
    if (isset($input['country_code'])) $data['country_code'] = strtoupper(htmlspecialchars(trim(substr($input['country_code'], 0, 2)), ENT_QUOTES, 'UTF-8'));
    if (isset($input['postal_code'])) $data['postal_code'] = htmlspecialchars(trim(substr($input['postal_code'], 0, 20)), ENT_QUOTES, 'UTF-8');
    if (isset($input['accuracy'])) $data['accuracy'] = filter_var($input['accuracy'], FILTER_VALIDATE_FLOAT);
    if (isset($input['accuracy_level'])) $data['accuracy_level'] = htmlspecialchars(trim(substr($input['accuracy_level'], 0, 20)), ENT_QUOTES, 'UTF-8');
    if (isset($input['source'])) $data['source'] = htmlspecialchars(trim(substr($input['source'], 0, 50)), ENT_QUOTES, 'UTF-8');
    $data['location_accepted'] = (isset($data['latitude']) && isset($data['longitude'])) ? 1 : 0;

    $countryIso   = $data['country_code'] ?? ($_SESSION['country_code'] ?? null);
    $countryLang  = fetchCountryLanguageCode($pdo, $countryIso);

    if (empty($_SESSION['language_manually_set'])) {
        $langInput = isset($input['language']) ? normalizeLang((string)$input['language']) : null;
        $finalLanguage = decideFinalLanguage($langInput, $countryLang, $availableLangs);
        $_SESSION['language'] = $finalLanguage;
    } else {
        $finalLanguage = $_SESSION['language'];
    }

    foreach ($data as $key => $value) {
        $_SESSION[$key] = $value;
    }
    $_SESSION['last_geo_update'] = time();

    // Atualiza tabela visitors
    $updateFields = [
        'ip_address = ?', 'user_agent = ?', 'is_bot = ?', 'last_seen_at = NOW()',
        'location_country = ?', 'city = ?', 'region = ?', 'postal_code = ?',
        'latitude = ?', 'longitude = ?', 'location_source = ?', 'location_accuracy_level = ?',
        'location_accepted = ?', 'preferred_language = ?', 'updated_at = NOW()'
    ];
    $params = [
        $ip, $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', $_SESSION['is_bot'] ?? 0,
        $data['country_code'] ?? null, $data['city'] ?? null, $data['region'] ?? null,
        $data['postal_code'] ?? null, $data['latitude'] ?? null, $data['longitude'] ?? null,
        $data['source'] ?? 'unknown', $data['accuracy_level'] ?? 'unknown',
        $data['location_accepted'], $finalLanguage, $visitor_db_id
    ];
    $sql = "UPDATE visitors SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // session_stage progress
    $stmt = $pdo->prepare("SELECT session_stage FROM visitors WHERE id = ?");
    $stmt->execute([$visitor_db_id]);
    $current_stage = $stmt->fetchColumn() ?: '';
    $new_stage = $current_stage;
    if ($current_stage === 'session') { $new_stage = 'consented'; }
    elseif ($current_stage === 'consented' && isset($data['latitude'])) { $new_stage = 'engaged'; }
    if ($new_stage !== $current_stage) {
        $pdo->prepare("UPDATE visitors SET session_stage = ? WHERE id = ?")->execute([$new_stage, $visitor_db_id]);
    }

    // resposta final
    $response_data = [
        'latitude' => $_SESSION['latitude'] ?? null,
        'longitude' => $_SESSION['longitude'] ?? null,
        'city' => $_SESSION['city'] ?? null,
        'region' => $_SESSION['region'] ?? null,
        'country_code' => $_SESSION['country_code'] ?? null,
        'postal_code' => $_SESSION['postal_code'] ?? null,
        'accuracy' => $_SESSION['accuracy'] ?? null,
        'accuracy_level' => $_SESSION['accuracy_level'] ?? null,
        'source' => $_SESSION['source'] ?? null,
        'location_accepted'=> $_SESSION['location_accepted'] ?? 0,
        'language_code' => $countryLang,
        'language' => $finalLanguage
    ];
    echo json_encode(['success' => true, 'message' => 'Geolocation updated successfully.', 'data' => $response_data]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Client Error: ' . $e->getMessage()]);
}
exit;
