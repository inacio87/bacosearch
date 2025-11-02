<?php
// /age-gate/verify.php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$secret = $_ENV['AGE_GATE_SECRET'] ?? 'secret';

// 1) Método permitido
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'err' => 'method']);
  exit;
}

// 2) Lê JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'err' => 'payload']);
  exit;
}

// 3) Checa nonce (CSRF de sessão)
$nonce = (string)($data['nonce'] ?? '');
if (!isset($_SESSION['ag_nonce']) || !hash_equals((string)$_SESSION['ag_nonce'], $nonce)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'err' => 'nonce']);
  exit;
}

// 4) Checa micro “proof-of-work” (calculado no cliente)
// Esperado foi calculado no PHP e salvo em $_SESSION['ag_expect']
$pow = (string)($data['pow'] ?? '');
$expect = (string)($_SESSION['ag_expect'] ?? '');
if ($expect === '' || !hash_equals($expect, $pow)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'err' => 'pow']);
  exit;
}

// 5) Sinais mínimos (tempo/UA)
$ts  = (int)($data['ts'] ?? 0);
$ua  = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$nav = (string)($data['nav'] ?? '');

if ($ts < time() - 60) { // timestamp muito antigo
  http_response_code(403);
  echo json_encode(['ok' => false, 'err' => 'ts']);
  exit;
}

// Headless/automação óbvios
if (preg_match('/(HeadlessChrome|PhantomJS|Puppeteer|node\.js)/i', $ua . ' ' . $nav)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'err' => 'ua']);
  exit;
}

// (Opcional) rate-limit extremamente simples por IP (arquivo temporário)
/*
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$k = sys_get_temp_dir().'/ag_rl_'.preg_replace('/[^0-9a-f\.:]/i','_',$ip);
$hits = 0; $win = 60; // 60s
if (file_exists($k)) {
  [$hits,$t] = array_map('intval', explode(':', file_get_contents($k)) + [0,0]);
  if (time() - $t > $win) { $hits = 0; }
}
$hits++;
file_put_contents($k, $hits.':'.time(), LOCK_EX);
if ($hits > 10) {
  http_response_code(429);
  echo json_encode(['ok'=>false,'err'=>'ratelimit']);
  exit;
}
*/

// 6) Emite cookie HttpOnly para liberar o acesso
$cookieVal = hash_hmac('sha256', 'ok', $secret);
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

setcookie('age_verified_h2', $cookieVal, [
  'expires'  => time() + 60*60*24*365, // 1 ano
  'path'     => '/',
  'secure'   => $secure,
  'httponly' => true,
  'samesite' => 'Lax',
]);

echo json_encode(['ok' => true]);
