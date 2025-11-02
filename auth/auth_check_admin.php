<?php
/**
 * /auth/auth_check_admin.php
 *
 * RESPONSABILIDADES:
 * 1. Carrega o sistema de bootstrap.
 * 2. Verifica se um administrador está logado (verificando 'admin_id').
 * 3. Se não estiver, redireciona para a página de login.
 *
 * Incluir no topo de qualquer página que exija autenticação de administrador.
 */

// PASSO 1: INICIALIZAÇÃO CENTRAL
require_once dirname(__DIR__) . '/core/bootstrap.php';

// PASSO 2: VERIFICAÇÃO DE AUTENTICAÇÃO DE ADMIN
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    // Redireciona para a página de login.
    header("Location: " . SITE_URL . "/auth/login.php");
    exit;
}
?>