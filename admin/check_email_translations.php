<?php
/**
 * Script para verificar e adicionar traduções de email faltantes
 */

require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <title>Verificação de Traduções de Email</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        .success { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .warning { color: #ff9800; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f5f5f5; }
        .missing { background-color: #ffebee; }
        .found { background-color: #e8f5e9; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #45a049; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Verificação de Traduções de Email</h1>
";

try {
    $db = getDBConnection();
    
    // Chaves necessárias para o email de registro
    $requiredKeys = [
        'registration_verification_email_subject' => [
            'context' => 'email_templates',
            'pt' => 'Verificação de Registo - BacoSearch',
            'en' => 'Registration Verification - BacoSearch',
            'es' => 'Verificación de Registro - BacoSearch'
        ],
        'email_title_fallback' => [
            'context' => 'email_templates',
            'pt' => 'Verificação de Registo',
            'en' => 'Registration Verification',
            'es' => 'Verificación de Registro'
        ],
        'greeting' => [
            'context' => 'email_templates',
            'pt' => 'Olá',
            'en' => 'Hello',
            'es' => 'Hola'
        ],
        'registration_email_main_message' => [
            'context' => 'email_templates',
            'pt' => 'Obrigado por se registar! Por favor, clique no botão abaixo para verificar o seu endereço de email.',
            'en' => 'Thank you for registering! Please click the button below to verify your email address.',
            'es' => 'Gracias por registrarte! Por favor, haz clic en el botón de abajo para verificar tu dirección de correo electrónico.'
        ],
        'registration_email_follow_up_message' => [
            'context' => 'email_templates',
            'pt' => 'Após a verificação, a sua conta será analisada e ativada em breve.',
            'en' => 'After verification, your account will be reviewed and activated soon.',
            'es' => 'Después de la verificación, tu cuenta será revisada y activada pronto.'
        ],
        'verify_email_button_text' => [
            'context' => 'email_templates',
            'pt' => 'Verificar Email',
            'en' => 'Verify Email',
            'es' => 'Verificar Email'
        ],
        'all_rights_reserved' => [
            'context' => 'email_templates',
            'pt' => 'Todos os direitos reservados.',
            'en' => 'All rights reserved.',
            'es' => 'Todos los derechos reservados.'
        ],
        'spam_notice' => [
            'context' => 'email_templates',
            'pt' => 'Se você não solicitou este e-mail, ignore-o.',
            'en' => 'If you did not request this email, please ignore it.',
            'es' => 'Si no solicitaste este correo, ignóralo.'
        ],
        'check_inbox_spam_notice' => [
            'context' => 'ui_messages',
            'pt' => 'Verifique sua caixa de entrada e a pasta de spam.',
            'en' => 'Please check your inbox and spam folder.',
            'es' => 'Por favor, revisa tu bandeja de entrada y la carpeta de spam.'
        ],
        'logo_alt_text' => [
            'context' => 'email_templates',
            'pt' => 'BacoSearch Logo',
            'en' => 'BacoSearch Logo',
            'es' => 'BacoSearch Logo'
        ]
    ];
    
    echo "<h2>📊 Status das Traduções</h2>";
    echo "<table>";
    echo "<tr><th>Chave</th><th>Contexto</th><th>PT</th><th>EN</th><th>ES</th><th>Status</th></tr>";
    
    $missingTranslations = [];
    
    foreach ($requiredKeys as $key => $config) {
        $context = $config['context'];
        $ptExists = false;
        $enExists = false;
        $esExists = false;
        
        // Verificar PT
        $stmt = $db->prepare("SELECT translation_value FROM translations WHERE translation_key = ? AND language_code = 'pt' AND context = ?");
        $stmt->execute([$key, $context]);
        $ptValue = $stmt->fetchColumn();
        $ptExists = !empty($ptValue);
        
        // Verificar EN
        $stmt = $db->prepare("SELECT translation_value FROM translations WHERE translation_key = ? AND language_code = 'en' AND context = ?");
        $stmt->execute([$key, $context]);
        $enValue = $stmt->fetchColumn();
        $enExists = !empty($enValue);
        
        // Verificar ES
        $stmt = $db->prepare("SELECT translation_value FROM translations WHERE translation_key = ? AND language_code = 'es' AND context = ?");
        $stmt->execute([$key, $context]);
        $esValue = $stmt->fetchColumn();
        $esExists = !empty($esValue);
        
        $allExist = $ptExists && $enExists && $esExists;
        $rowClass = $allExist ? 'found' : 'missing';
        
        echo "<tr class='$rowClass'>";
        echo "<td><code>$key</code></td>";
        echo "<td>$context</td>";
        echo "<td>" . ($ptExists ? "✅ " . htmlspecialchars(substr($ptValue, 0, 30)) . "..." : "❌ Faltando") . "</td>";
        echo "<td>" . ($enExists ? "✅ " . htmlspecialchars(substr($enValue, 0, 30)) . "..." : "❌ Faltando") . "</td>";
        echo "<td>" . ($esExists ? "✅ " . htmlspecialchars(substr($esValue, 0, 30)) . "..." : "❌ Faltando") . "</td>";
        echo "<td>" . ($allExist ? "<span class='success'>Completo</span>" : "<span class='error'>Incompleto</span>") . "</td>";
        echo "</tr>";
        
        if (!$allExist) {
            $missingTranslations[$key] = $config;
        }
    }
    
    echo "</table>";
    
    if (empty($missingTranslations)) {
        echo "<div class='success' style='padding: 20px; background: #e8f5e9; border-radius: 4px; margin: 20px 0;'>";
        echo "🎉 <strong>Perfeito!</strong> Todas as traduções necessárias estão presentes no banco de dados.";
        echo "</div>";
    } else {
        echo "<div class='warning' style='padding: 20px; background: #fff3e0; border-radius: 4px; margin: 20px 0;'>";
        echo "⚠️ <strong>Encontradas " . count($missingTranslations) . " tradução(ões) faltando!</strong>";
        echo "</div>";
        
        echo "<h2>🔧 SQL para Adicionar Traduções Faltantes</h2>";
        echo "<p>Copie e cole este SQL no phpMyAdmin:</p>";
        echo "<pre>";
        
        echo "-- Adicionar traduções faltantes para emails de registro\n\n";
        
        foreach ($missingTranslations as $key => $config) {
            foreach (['pt', 'en', 'es'] as $lang) {
                if (isset($config[$lang])) {
                    $value = addslashes($config[$lang]);
                    $context = $config['context'];
                    
                    echo "INSERT INTO translations (translation_key, language_code, context, translation_value, created_at, updated_at)\n";
                    echo "VALUES ('$key', '$lang', '$context', '$value', NOW(), NOW())\n";
                    echo "ON DUPLICATE KEY UPDATE translation_value = '$value', updated_at = NOW();\n\n";
                }
            }
        }
        
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<div class='error' style='padding: 20px; background: #ffebee; border-radius: 4px;'>";
    echo "<h2>❌ Erro</h2>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "
    </div>
</body>
</html>";
