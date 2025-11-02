<?php
/**
 * /api/check_session.php - Endpoint para atualizar dados de geolocalização e idioma
 *
 * Última atualização: 15/08/2025 - CORRIGIDO para respeitar a escolha manual de idioma.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

require_once dirname(__DIR__) . '/core/bootstrap.php';

/* ========================== HELPERS LOCAIS ========================== */

function pdo(): PDO {
    return getDBConnection();
}

function normalizeLang(?string $code): ?string {
    if (!$code) return null;
    return str_replace('_', '-', strtolower(trim($code)));
}

/** idiomas ativos a partir de languages.code */
function getActiveLanguageCodes(PDO $pdo): array {
    $stmt = $pdo->query("SELECT code FROM languages WHERE is_active = 1");
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    // VERSÃO CORRIGIDA: Usa uma função anónima tradicional
    return array_map(function($c) {
        return strtolower(trim($c));
    }, $rows ?: []);
}

function getCountryLanguageCode(PDO $pdo, ?string $iso): ?string {
    if (!$iso) return null;
    $stmt = $pdo->prepare("SELECT language_code FROM countries WHERE iso_code = ? LIMIT 1");
    $stmt->execute([$iso]);
    $code = $stmt->fetchColumn();
    return $code ? normalizeLang($code) : null;
}

function decideFinalLanguage(?string $inputLang, ?string $countryLang, array $available): string {
    $inputLang   = normalizeLang($inputLang);
    $countryLang = normalizeLang($countryLang);
    $default     = normalizeLang(LANGUAGE_CONFIG['default'] ?? ($available[0] ?? 'en-us'));
    if ($inputLang && in_array($inputLang, $available, true)) return $inputLang;
    if ($countryLang && in_array($countryLang, $available, true)) return $countryLang;
    return $default ?: 'en-us';
}

/* ============================ PRÉ-VALIDAÇÕES ============================ */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(false, [], 405, 'Method not allowed');
}

$visitor_db_id = $_SESSION['visitor_db_id'] ?? null;
if ($visitor_db_id === null) {
    log_system_error("CRITICAL: visitor_db_id não definido.", 'CRITICAL', 'check_session_no_visitor_id');
    send_json_response(false, [], 500, 'Erro de inicialização do visitante.');
}

try {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input', 400);
    }

    $pdo = pdo();
    $availableLanguages = getActiveLanguageCodes($pdo);
    if (empty($availableLanguages)) {
        throw new Exception("Nenhum idioma configurado no sistema.", 500);
    }
    
    // --- LÓGICA DE IDIOMA CORRIGIDA ---
    if (empty($_SESSION['language_manually_set'])) {
        $languageInput = normalizeLang($input['language'] ?? null);
        $countryIso = isset($input['country_code']) ? strtoupper(trim((string)$input['country_code'])) : ($_SESSION['country_code'] ?? null);
        $countryLanguageCode = getCountryLanguageCode($pdo, $countryIso);
        $finalLanguage = decideFinalLanguage($languageInput, $countryLanguageCode, $availableLanguages);
        $_SESSION['language'] = $finalLanguage;
    } else {
        $finalLanguage = $_SESSION['language'];
        $countryIso = $_SESSION['country_code'] ?? null; // Pega o código de país da sessão para info
        $countryLanguageCode = getCountryLanguageCode($pdo, $countryIso);
    }
    // --- FIM DA CORREÇÃO ---

    // ===== Validação/Sanitização dos outros campos =====
    $field_validations = [ 'city' => ['validate' => fn($v) => is_string($v) && strlen($v) > 0, 'sanitize' => fn($v) => htmlspecialchars(trim(substr($v, 0, 100)), ENT_QUOTES, 'UTF-8')], 'country_code' => ['validate' => fn($v) => is_string($v) && strlen($v) === 2, 'sanitize' => fn($v) => strtoupper(htmlspecialchars(trim(substr($v, 0, 2)), ENT_QUOTES, 'UTF-8'))], 'region' => ['validate' => fn($v) => is_string($v), 'sanitize' => fn($v) => htmlspecialchars(trim(substr($v, 0, 100)), ENT_QUOTES, 'UTF-8')], 'postal_code' => ['validate' => fn($v) => is_string($v), 'sanitize' => fn($v) => htmlspecialchars(trim(substr($v, 0, 20)), ENT_QUOTES, 'UTF-8')], 'latitude' => ['validate' => fn($v) => is_numeric($v), 'sanitize' => fn($v) => floatval($v)], 'longitude' => ['validate' => fn($v) => is_numeric($v), 'sanitize' => fn($v) => floatval($v)], 'accuracy_level' => ['validate' => fn($v) => is_string($v), 'sanitize' => fn($v) => htmlspecialchars(trim(substr($v, 0, 20)), ENT_QUOTES, 'UTF-8')], 'source' => ['validate' => fn($v) => is_string($v), 'sanitize' => fn($v) => htmlspecialchars(trim(substr($v, 0, 50)), ENT_QUOTES, 'UTF-8')], 'accuracy' => ['validate' => fn($v) => is_numeric($v), 'sanitize' => fn($v) => floatval($v)] ];
    $data = [];
    foreach ($field_validations as $field => $rules) {
        if (isset($input[$field]) && $rules['validate']($input[$field])) {
            $data[$field] = $rules['sanitize']($input[$field]);
        }
    }

    // ==== Atualizar sessão ====
    foreach ($data as $key => $value) {
        $_SESSION[$key] = $value;
    }
    $_SESSION['geolocation_timestamp'] = time();

    if (isset($data['country_code'])) {
        $stmt = $pdo->prepare("SELECT name FROM countries WHERE iso_code = ? LIMIT 1");
        $stmt->execute([$data['country_code']]);
        $_SESSION['country'] = $stmt->fetchColumn() ?: null;
    }

    // ==== Atualizar tabela visitors ====
    $updateFields = [ 'location_country = ?', 'city = ?', 'region = ?', 'postal_code = ?', 'latitude = ?', 'longitude = ?', 'preferred_language = ?', "session_stage = CASE WHEN session_stage = 'session' THEN 'consented' WHEN session_stage = 'consented' AND ? IS NOT NULL THEN 'engaged' ELSE session_stage END", 'updated_at = NOW()' ];
    $params = [ $_SESSION['country_code'] ?? null, $_SESSION['city'] ?? null, $_SESSION['region'] ?? null, $_SESSION['postal_code'] ?? null, $_SESSION['latitude'] ?? null, $_SESSION['longitude'] ?? null, $finalLanguage, ($_SESSION['latitude'] ?? null), $visitor_db_id ];
    $sql = "UPDATE visitors SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // ==== Registrar evento ====
    $event_data = [ 'city' => $_SESSION['city'] ?? null, 'language' => $_SESSION['language'], 'country_code' => $_SESSION['country_code'] ?? null, 'country' => $_SESSION['country'] ?? null, 'region' => $_SESSION['region'] ?? null, 'postal_code' => $_SESSION['postal_code'] ?? null, 'latitude' => $_SESSION['latitude'] ?? null, 'longitude' => $_SESSION['longitude'] ?? null, 'source' => $_SESSION['source'] ?? 'api_update', 'accuracy_level' => $_SESSION['accuracy_level'] ?? 'none', 'accuracy' => $_SESSION['accuracy'] ?? null, 'country_language_code' => $countryLanguageCode ];
    $pdo->prepare("INSERT INTO events (visitor_id, event_type, event_name, event_data, created_at) VALUES (?, ?, ?, ?, NOW())")->execute([ $visitor_db_id, 'interaction', 'location_updated', json_encode($event_data, JSON_UNESCAPED_SLASHES) ]);

    // ==== Resposta JSON ====
    $session_data = [ 'city' => $_SESSION['city'] ?? 'Unknown Location', 'language' => $_SESSION['language'], 'country_code' => $_SESSION['country_code'] ?? null, 'country' => $_SESSION['country'] ?? null, 'region' => $_SESSION['region'] ?? null, 'postal_code' => $_SESSION['postal_code'] ?? null, 'latitude' => $_SESSION['latitude'] ?? null, 'longitude' => $_SESSION['longitude'] ?? null, 'accuracy_level' => $_SESSION['accuracy_level'] ?? 'none', 'source' => $_SESSION['source'] ?? 'none', 'accuracy' => $_SESSION['accuracy'] ?? null, 'country_language_code' => $countryLanguageCode ];
    send_json_response(true, $session_data, 200, 'Session updated successfully.');

} catch (Exception $e) {
    $http_code = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int)$e->getCode() : 500;
    $error_message = "Check Session Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    log_system_error($error_message, "CRITICAL", "api_check_session_exception", $visitor_db_id ?? null);
    send_json_response(false, [], $http_code, "Internal server error: " . $e->getMessage());
}

exit;