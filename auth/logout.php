<?php
/**
 * /auth/logout.php - VERSÃO FINAL COM ARQUITETURA CORRIGIDA
 *
 * RESPONSABILIDADES:
 * 1. Carrega o sistema de bootstrap para garantir que a sessão é gerida corretamente.
 * 2. Destrói a sessão atual do utilizador para efetuar o logout.
 * 3. Redireciona o utilizador para a página inicial.
 *
 * ÚLTIMA ATUALIZAÇÃO: 28/06/2025
 */

// PASSO 1: INICIALIZAÇÃO CENTRAL
// Carrega tudo, incluindo o gestor de sessão.
require_once dirname(__DIR__) . '/core/bootstrap.php';


// PASSO 2: PROCESSO DE LOGOUT
// Limpa todas as variáveis da sessão.
$_SESSION = [];

// Se os cookies de sessão estiverem a ser usados, apaga-os.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destrói a sessão no servidor.
session_destroy();


// PASSO 3: REDIRECIONAMENTO
// Redireciona o utilizador para a página inicial do site.
header("Location: " . SITE_URL);
exit;
?>