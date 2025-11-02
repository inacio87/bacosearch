<?php
/**
 * /api/track-visit.php - VERSÃO FINAL OTIMIZADA PARA PHP 8.1.33
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

$visitor_id_log = $_SESSION['visitor_db_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_system_error("Método não permitido: {$_SERVER['REQUEST_METHOD']}", 'ERROR', 'track_visit_invalid_method', $visitor_id_log);
    send_json_response(false, [], 405, 'Método não permitido.');
}

$data_raw = file_get_contents('php://input');
if (!$data_raw) {
    log_system_error("Nenhum payload recebido", 'WARNING', 'no_payload', $visitor_id_log);
    send_json_response(false, [], 400, 'Nenhum dado recebido.');
}

$data = json_decode($data_raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    log_system_error("JSON inválido recebido: $data_raw", 'ERROR', 'json_parse_error', $visitor_id_log);
    send_json_response(false, [], 400, 'JSON inválido.');
}

if (empty($data) || !isset($data['event_name']) || !isset($data['event_data']) || !is_array($data['event_data'])) {
    log_system_error("Payload inválido: $data_raw", 'WARNING', 'track_visit_invalid_input', $visitor_id_log);
    send_json_response(false, [], 400, 'Payload inválido.');
}

// Recupera visitor_db_id da sessão
$visitor_db_id = isset($_SESSION['visitor_db_id']) ? (int)$_SESSION['visitor_db_id'] : null;

if (!$visitor_db_id) {
    log_system_error("visitor_db_id não presente na sessão", 'ERROR', 'missing_visitor_id', null);
    send_json_response(false, [], 403, 'Visitante não identificado.');
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, is_bot, session_stage FROM visitors WHERE id = ?");
    $stmt->execute([$visitor_db_id]);
    $visitor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visitor) {
        log_system_error("visitor_id $visitor_db_id não encontrado", 'ERROR', 'visitor_not_found', $visitor_db_id);
        send_json_response(false, [], 403, 'Visitante não encontrado.');
    }

    if ($visitor['is_bot']) {
        log_system_error("Bot detectado, evento ignorado para visitor_id: $visitor_db_id", 'NOTICE', 'bot_event_ignored', $visitor_db_id);
        send_json_response(false, [], 403, 'Requisição de bot não permitida.');
    }

    $event_name = (string)$data['event_name'];
    $event_data_from_client = (array)$data['event_data'];
    // A linha abaixo foi modificada para remover a condição 'exit_link_click'
    $event_type = 'interaction'; // Agora, todos os eventos são tratados como 'interaction'

    $event_data_for_db = array_merge($event_data_from_client, [
        'ip' => getClientIp(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'page_url' => $_SERVER['REQUEST_URI'] ?? 'N/A',
        'referrer' => $_SERVER['HTTP_REFERER'] ?? 'direct',
        'session_id_php' => session_id(),
        'session_stage' => $visitor['session_stage'] ?? 'session'
    ]);

    $pdo->beginTransaction();

    // O bloco 'if ($event_name === 'exit_link_click' && !empty($event_data_from_client['exit_url']))' foi removido
    // pois não há mais a coluna 'exit_url' para ser atualizada.

    $stmt = $pdo->prepare("INSERT INTO events (visitor_id, event_type, event_name, event_data, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$visitor_db_id, $event_type, $event_name, json_encode($event_data_for_db)]);
    $pdo->commit();

    log_system_error("Evento '$event_name' registrado para visitor_id: $visitor_db_id", 'INFO', 'track_visit_success', $visitor_db_id);
    send_json_response(true, [], 204, 'Evento registrado com sucesso.');
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_system_error("Erro ao processar evento: {$e->getMessage()}, Payload: $data_raw", 'ERROR', 'track_visit_error', $visitor_db_id ?? null);
    send_json_response(false, [], 500, 'Erro interno do servidor.');
}
