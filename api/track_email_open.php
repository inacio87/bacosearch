<?php
/**
 * /api/track_email_open.php
 * Rastreia a abertura de e-mails usando um pixel de imagem.
 *
 * Captura o ID do pixel da URL, marca o e-mail como aberto no DB,
 * e retorna uma imagem 1x1 transparente.
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';

// Garante que o buffer de saída esteja limpo para evitar problemas com a imagem
if (ob_get_level() > 0) {
    ob_end_clean();
}

$trackingId = $_GET['id'] ?? '';

if (!empty($trackingId)) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE emails_sent SET is_opened = TRUE, opened_at = NOW(), details = JSON_SET(details, '$.ip_address', :ip, '$.user_agent', :ua) WHERE tracking_pixel_id = :tracking_id AND is_opened = FALSE LIMIT 1");
        $stmt->execute([
            ':tracking_id' => $trackingId,
            ':ip' => getClientIp(),
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        log_system_error("Erro ao registrar abertura de email (ID: {$trackingId}): " . $e->getMessage(), 'ERROR', 'email_open_tracking');
    }
}

// Retorna um GIF 1x1 transparente
header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo base64_decode('R0lGODlhAQABAJAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
exit();