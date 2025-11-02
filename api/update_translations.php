<?php
/**
 * /api/update_translations.php
 * Processa as atualizações de traduções enviadas pelo módulo de traduções.
 * - Responde em JSON, para ser usado com AJAX.
 * - Utiliza INSERT ... ON DUPLICATE KEY UPDATE para eficiência.
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';

// Verificação se a requisição é do tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // 405 Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit();
}

header('Content-Type: application/json');

// Verificação de segurança: O endpoint só é acessível para administradores logados.
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(401); // 401 Unauthorized
    echo json_encode(['success' => false, 'message' => getTranslation('not_authorized', $_SESSION['language'] ?? 'pt-br', 'global') ?: 'Não autorizado.']);
    exit();
}


// --- Validação e Sanitização dos Dados ---
$input = $_POST;
$translation_key = filter_var($input['translation_key'] ?? '', FILTER_SANITIZE_STRING);
$context         = filter_var($input['context'] ?? '', FILTER_SANITIZE_STRING);
$translations    = $input['translations'] ?? [];
$languageCode    = $_SESSION['language'] ?? 'pt-br';

// Valida se os dados essenciais foram recebidos
if (empty($translation_key) || !is_array($translations)) {
    http_response_code(400); // 400 Bad Request
    echo json_encode(['success' => false, 'message' => 'Dados inválidos ou faltando para salvar as traduções.']);
    exit;
}

try {
    $db = getDBConnection();
    $db->beginTransaction();

    // Query para inserir ou atualizar traduções.
    $stmt = $db->prepare("
        INSERT INTO translations (language_code, translation_key, context, translation_value, created_at)
        VALUES (:language_code, :translation_key, :context, :translation_value, NOW())
        ON DUPLICATE KEY UPDATE
            translation_value = VALUES(translation_value),
            last_updated = NOW()
    ");

    foreach ($translations as $language_code => $translation_value) {
        $sanitized_value = filter_var($translation_value, FILTER_SANITIZE_STRING);
        
        $stmt->execute([
            ':language_code'    => $language_code,
            ':translation_key'  => $translation_key,
            ':context'          => $context,
            ':translation_value'=> $sanitized_value
        ]);
    }

    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => getTranslation('translations_saved_successfully', $languageCode, 'admin_translations') ?: 'Traduções salvas com sucesso!'
    ]);

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    log_system_error('Erro ao atualizar traduções: ' . $e->getMessage(), 'ERROR', 'update_translations_api');
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => getTranslation('database_error', $languageCode, 'global') ?: 'Ocorreu um erro no servidor.'
    ]);
}
?>