<?php
/**
 * /api/api_submit_street.php - Submete referência de rua/bar (street_posts)
 * Requer usuário autenticado; status pendente até aprovação.
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['status'=>'error','message'=>'Method not allowed']); exit; }

try {
  $pdo = getDBConnection();
  $pdo->beginTransaction();

  // Autenticação básica via sessão
  $account_id = $_SESSION['account_id'] ?? ($_SESSION['temp_user_id'] ?? null);
  if (!$account_id) { http_response_code(401); throw new Exception('Login necessário'); }

  // CSRF básico
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(400); throw new Exception('CSRF token inválido'); }

  $place_type = ($_POST['place_type'] ?? 'street') === 'bar' ? 'bar' : 'street';
  $street_name = trim($_POST['street_name'] ?? '');
  $place_name  = trim($_POST['place_name'] ?? '');
  $ad_country  = trim($_POST['ad_country'] ?? '');
  $ad_state    = trim($_POST['ad_state'] ?? '');
  $ad_city     = trim($_POST['ad_city'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $tags        = trim($_POST['tags'] ?? '');

  if ($place_type === 'street' && $street_name==='') throw new Exception('Nome da rua é obrigatório');
  if ($place_type === 'bar' && $place_name==='') throw new Exception('Nome do bar é obrigatório');
  if ($ad_city==='') throw new Exception('Cidade é obrigatória');

  $stmt = $pdo->prepare("INSERT INTO street_posts (
    account_id, place_type, street_name, place_name, description, tags,
    ad_country, ad_state, ad_city, ad_latitude, ad_longitude,
    status, is_active, created_at
  ) VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending',0,NOW())");
  $stmt->execute([
    $account_id, $place_type, $street_name ?: null, $place_name ?: null, $description ?: null, $tags ?: null,
    $ad_country ?: null, $ad_state ?: null, $ad_city ?: null, null, null
  ]);
  $id = (int)$pdo->lastInsertId();

  $pdo->commit();
  echo json_encode(['status'=>'success','message'=>'Submissão enviada para análise.','data'=>['id'=>$id]]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  log_system_error('API_SUBMIT_STREET_ERROR: '.$e->getMessage(),'ERROR','api_submit_street');
  http_response_code(500); echo json_encode(['status'=>'error','message'=>'Falha ao enviar submissão.']);
}
