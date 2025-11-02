<?php
/**
 * /api/track_email_click.php
 * Rastreia cliques em links de e-mails e redireciona para a URL original.
 *
 * Captura o ID do pixel e o link original da URL, registra o clique no DB,
 * e então redireciona o usuário para o link original.
 *
 * ATUALIZAÇÃO: 09/07/2025 - Ajuste no caminho do bootstrap.php e melhoria no tratamento de erros.
 */

// Caminho ajustado para o bootstrap.php
// Se track_email_click.php está em /api/ e bootstrap.php está em /core/,
// então dirname(__DIR__) leva para o diretório "pai" de /api/, que seria a raiz do projeto.
require_once dirname(__DIR__) . '/core/bootstrap.php';

// Definir headers para evitar cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$trackingId = $_GET['id'] ?? '';
$originalUrl = $_GET['link'] ?? '';
$emailBatchId = $_GET['batch'] ?? '';

// Adiciona um log para depuração inicial
log_system_error("Track Email Click: Requisição recebida. ID: {$trackingId}, URL: {$originalUrl}, Batch: {$emailBatchId}", 'DEBUG', 'email_click_tracking_request');


if (!empty($trackingId) && !empty($originalUrl)) {
    $decodedUrl = urldecode($originalUrl);

    // Adiciona um log para depuração da URL decodificada
    log_system_error("Track Email Click: URL decodificada: {$decodedUrl}", 'DEBUG', 'email_click_tracking_decoded_url');

    // Validação da URL para evitar redirecionamentos maliciosos ou URLs inválidas
    // Você pode precisar ajustar esta validação para permitir URLs internas
    // ou apenas domínios específicos. Por enquanto, uma checagem básica.
    if (!filter_var($decodedUrl, FILTER_VALIDATE_URL)) {
        log_system_error("Track Email Click: Tentativa de redirecionamento para URL inválida: {$decodedUrl}", 'WARNING', 'email_click_invalid_url');
        // Em vez de redirecionar para a home, redirecionar para uma página de erro ou a própria página de licença
        header("Location: " . SITE_URL . '/pages/license.php?status=invalid_link');
        exit();
    }
    
    // IMPORTANTE: Verifique se o placeholder {{access_link}} foi substituído.
    // Se a URL ainda contiver {{ ou }}, isso indica que o sistema de envio de e-mail falhou em preencher o placeholder.
    if (strpos($decodedUrl, '{{') !== false || strpos($decodedUrl, '}}') !== false) {
        log_system_error("Track Email Click: URL decodificada ainda contém placeholders: {$decodedUrl}. Verifique o sistema de envio de e-mails.", 'CRITICAL', 'email_click_placeholder_not_replaced');
        // Redireciona para uma página informativa ou para a página de licença sem o token
        header("Location: " . SITE_URL . '/pages/license.php?status=link_error');
        exit();
    }


    try {
        $db = getDBConnection(); // Tenta obter a conexão com o banco de dados

        // Coleta informações do cliente
        $clientIp = getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $timestamp = date('Y-m-d H:i:s');

        // Dados do clique para o JSON
        $clickedLinkData = [
            'url' => $decodedUrl,
            'timestamp' => $timestamp,
            'ip_address' => $clientIp,
            'user_agent' => $userAgent,
            'batch_id' => $emailBatchId
        ];

        // Consulta SQL para atualizar o registro do email com o clique
        // Verifica se 'details' é NULL e inicializa como objeto JSON vazio se for.
        // Adiciona um clique ao array 'clicked_links' e atualiza o último clique.
        $stmt = $db->prepare("
            UPDATE emails_sent
            SET
                details = JSON_ARRAY_APPEND(
                    COALESCE(details, '{}'),
                    '$.clicked_links',
                    :clicked_link
                ),
                details = JSON_SET(
                    COALESCE(details, '{}'),
                    '$.last_click_at', :last_click_at,
                    '$.last_click_ip', :last_click_ip,
                    '$.last_click_ua', :last_click_ua
                )
            WHERE tracking_pixel_id = :tracking_id
            LIMIT 1
        ");

        $stmt->execute([
            ':clicked_link' => json_encode($clickedLinkData),
            ':last_click_at' => $timestamp,
            ':last_click_ip' => $clientIp,
            ':last_click_ua' => $userAgent,
            ':tracking_id' => $trackingId
        ]);

        log_system_error("Track Email Click: Clique registrado com sucesso para ID: {$trackingId}, URL: {$decodedUrl}", 'INFO', 'email_click_tracking_success');

        // Redireciona o usuário para a URL original
        header("Location: " . $decodedUrl);
        exit();

    } catch (Exception $e) {
        // Loga o erro específico do banco de dados ou da operação.
        log_system_error("Track Email Click: Erro ao registrar clique em link (ID: {$trackingId}, URL: {$decodedUrl}): " . $e->getMessage(), 'ERROR', 'email_click_tracking_db_error');

        // Em caso de erro no rastreamento, ainda tenta redirecionar o usuário para não interromper a navegação.
        // Isso é crucial para a experiência do usuário, mesmo que o rastreamento falhe.
        header("Location: " . $decodedUrl);
        exit();
    }
} else {
    // Se parâmetros inválidos, loga e redireciona para uma página mais amigável ou de erro.
    log_system_error("Track Email Click: Parâmetros de rastreamento inválidos. ID: '{$trackingId}', URL: '{$originalUrl}'", 'WARNING', 'email_click_invalid_params');
    // Redireciona para a página de licença ou home, dependendo da sua preferência para links quebrados.
    header("Location: " . SITE_URL . '/pages/license.php?status=invalid_tracking');
    exit();
}
?>