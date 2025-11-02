<?php
// /core/age_gate_guard.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Rotas isentas: assets, o próprio verify, robots, etc.
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$exempts = [
  '/age-gate/verify.php',
  '/pages/terms_of_service.php',
  '/pages/privacy_policy.php',
  '/pages/cookie_policy.php',
];
if (preg_match('#^/(assets/|.well-known/)#', $uri)) return;
if (in_array($uri, $exempts, true)) return;

// Já verificado?
$verified = isset($_COOKIE['age_verified_h2'])
         && hash_equals($_COOKIE['age_verified_h2'], hash_hmac('sha256', 'ok', $_ENV['AGE_GATE_SECRET'] ?? 'secret'));

if (!$verified) {
  // Sinaliza noindex até verificar
  header('X-Robots-Tag: noindex, nofollow', true);
  // Injeta um HTML mínimo com o modal (ou redireciona para home que contém o modal)
  // Aqui só garante que o modal será mostrado:
  // Opcional: redirecionar para a home se preferir centralizar:
  // header('Location: /?gate=1', true, 302); exit;
}
