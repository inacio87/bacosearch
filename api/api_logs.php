<?php
/**
 * /admin/api_logs.php - API para buscar dados de logs do sistema
 *
 * RESPONSABILIDADES:
 * 1. Recebe parâmetros de `log_type`, `period`, `page` e `limit`.
 * 2. Consulta o banco de dados para os logs correspondentes.
 * 3. Aplica filtros de tempo e paginação.
 * 4. Retorna uma resposta JSON com os logs e informações de paginação.
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';

date_default_timezone_set('Europe/Lisbon');
header('Content-Type: application/json');

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- Config de timezones ---
    $db_timezone  = 'America/Sao_Paulo'; 
    $app_timezone = 'Europe/Lisbon';    

    // --- Parâmetros ---
    $allowed_log_types = ['system_logs', 'search_logs', 'api_accuracy_log', 'secret_access_logs'];
    $log_type = $_GET['log_type'] ?? 'system_logs';
    if (!in_array($log_type, $allowed_log_types)) {
        $log_type = 'system_logs';
    }

    $allowed_periods = ['5min', 'today', '7d', '30d', '360d'];
    $period = $_GET['period'] ?? '7d';
    if (!in_array($period, $allowed_periods)) {
        $period = '7d';
    }

    $page  = max(1, (int) ($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int) ($_GET['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;

    // --- Filtro de tempo ---
    $convert_col = "created_at"; // default
    if ($log_type === 'secret_access_logs') {
        $convert_col = "accessed_at";
    }

    $convert_tz_sql = "CONVERT_TZ($convert_col, '$db_timezone', '$app_timezone')";
    switch ($period) {
        case '5min':
            $sql_where_time = "$convert_tz_sql >= CONVERT_TZ(NOW(), '$db_timezone', '$app_timezone') - INTERVAL 5 MINUTE";
            break;
        case 'today':
            $sql_where_time = "DATE($convert_tz_sql) = DATE(CONVERT_TZ(NOW(), '$db_timezone', '$app_timezone'))";
            break;
        case '7d':
            $sql_where_time = "$convert_tz_sql >= CONVERT_TZ(NOW(), '$db_timezone', '$app_timezone') - INTERVAL 7 DAY";
            break;
        case '30d':
            $sql_where_time = "$convert_tz_sql >= CONVERT_TZ(NOW(), '$db_timezone', '$app_timezone') - INTERVAL 30 DAY";
            break;
        case '360d':
            $sql_where_time = "$convert_tz_sql >= CONVERT_TZ(NOW(), '$db_timezone', '$app_timezone') - INTERVAL 360 DAY";
            break;
        default:
            $sql_where_time = "1=1"; // fallback sem filtro
    }

    // --- Seleção de colunas ---
    switch ($log_type) {
        case 'system_logs':
            $columns_to_select = "
                id, level, message, context, visitor_id, ip_address, user_agent, request_uri,
                CONVERT_TZ(created_at, '$db_timezone', '$app_timezone') AS created_at
            ";
            $order_by_column = "created_at";
            break;

        case 'search_logs':
            $columns_to_select = "
                id, term, normalized_term, intent_category, results_count, visitor_id,
                CONVERT_TZ(created_at, '$db_timezone', '$app_timezone') AS created_at
            ";
            $order_by_column = "created_at";
            break;

        case 'api_accuracy_log':
            $columns_to_select = "
                id, api_name, distance_error_km, country_code, region,
                CONVERT_TZ(created_at, '$db_timezone', '$app_timezone') AS created_at,
                CONVERT_TZ(updated_at, '$db_timezone', '$app_timezone') AS updated_at
            ";
            $order_by_column = "created_at";
            break;

        case 'secret_access_logs':
            $columns_to_select = "
                id, account_id, provider_id,
                CONVERT_TZ(accessed_at, '$db_timezone', '$app_timezone') AS accessed_at,
                ip_address, user_agent
            ";
            $order_by_column = "accessed_at";
            break;

        default:
            $columns_to_select = "*";
            $order_by_column = "id";
    }

    // --- Total de registros ---
    $count_query = "SELECT COUNT(*) FROM `$log_type` WHERE $sql_where_time";
    $stmt_count = $pdo->prepare($count_query);
    $stmt_count->execute();
    $total_records = (int) $stmt_count->fetchColumn();
    $total_pages = max(1, ceil($total_records / $limit));

    // --- Buscar registros ---
    $query = "SELECT $columns_to_select FROM `$log_type` 
              WHERE $sql_where_time 
              ORDER BY `$order_by_column` DESC 
              LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'        => true,
        'logs'           => $logs,
        'total_records'  => $total_records,
        'total_pages'    => $total_pages,
        'current_page'   => $page,
        'items_per_page' => $limit
    ]);

} catch (PDOException $e) {
    error_log("API Logs Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar logs (PDO).']);
} catch (Exception $e) {
    error_log("API Logs General Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro inesperado ao processar logs.']);
}
