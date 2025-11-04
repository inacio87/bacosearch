<?php
/**
 * /api/favorites.php - Gerencia favoritos na sessão
 * Métodos: POST action=add|remove {type,id}; GET action=list
 */
require_once __DIR__ . '/../core/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!isset($_SESSION['favorites'])) {
  $_SESSION['favorites'] = [
    'providers'=>[], 'companies'=>[], 'clubs'=>[], 'services'=>[]
  ];
}

function sanitizeType($t){
  $t = strtolower(trim((string)$t));
  return in_array($t, ['providers','companies','clubs','services'], true) ? $t : null;
}

try {
  if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    $type = sanitizeType($_POST['type'] ?? '');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$type || $id<=0) {
      send_json_response(false, [], 400, 'Parâmetros inválidos');
    }
    if ($action === 'add') {
      if (!in_array($id, $_SESSION['favorites'][$type], true)) {
        $_SESSION['favorites'][$type][] = $id;
      }
      send_json_response(true, ['favorites'=>$_SESSION['favorites']], 200, 'Adicionado');
    } elseif ($action === 'remove') {
      $_SESSION['favorites'][$type] = array_values(array_filter($_SESSION['favorites'][$type], fn($v)=> (int)$v !== $id));
      send_json_response(true, ['favorites'=>$_SESSION['favorites']], 200, 'Removido');
    } else {
      send_json_response(false, [], 400, 'Ação inválida');
    }
  } else {
    send_json_response(true, ['favorites'=>$_SESSION['favorites']], 200, null);
  }
} catch (Throwable $e) {
  log_system_error('FAVORITES_API_ERROR: '.$e->getMessage(), 'ERROR', 'favorites_api');
  send_json_response(false, [], 500, 'Erro interno');
}
