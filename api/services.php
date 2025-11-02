<?php
/**
 * /api/services.php - Endpoint para resultados de serviços
 *
 * RESPONSABILIDADES:
 * 1. Receber parâmetros de localização e filtros via POST/GET.
 * 2. Consultar a tabela `services` com fallback (cidade > região > país > global).
 * 3. Retornar dados em JSON para consumo por results_services.php.
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/api/additional_functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed', 'success' => false]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if (empty($action)) {
        http_response_code(400);
        echo json_encode(['error' => 'Action parameter is missing.', 'success' => false]);
        exit;
    }

    switch ($action) {
        case 'get_services':
            $location_data = $input['location_data'] ?? [];
            $filters = $input['filters'] ?? [];
            $results = findServicesWithFallback($location_data, $filters);
            echo json_encode(['success' => true, 'data' => $results]);
            break;

        case 'get_available_categories':
            $pdo = getDBConnection();
            $stmt = $pdo->query("SELECT DISTINCT slug, name FROM categories WHERE is_active = 1 ORDER BY name ASC");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $categories]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action', 'success' => false]);
    }
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    error_log("Erro na API services: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
    echo json_encode(['error' => 'Internal Server Error. Check logs for details.', 'success' => false]);
    exit;
}