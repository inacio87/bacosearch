<?php
/**
 * /api/verify_age.php
 * API para verificação de idade (+18)
 */

session_start();

// Marca que o usuário verificou a idade
$_SESSION['age_verified'] = true;
$_SESSION['age_verified_at'] = time();

// Retorna sucesso
header('Content-Type: application/json');
echo json_encode(['success' => true]);
