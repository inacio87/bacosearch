<?php
/**
 * /admin/tools/check_missing_translations.php
 * 
 * Ferramenta de diagnóstico para identificar traduções faltantes
 * Compara chaves usadas no código vs registros no banco de dados
 * 
 * USO: Acesse via browser em /admin/tools/check_missing_translations.php
 */

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

// Requer autenticação admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die('Access denied. Admin authentication required.');
}

/**
 * Extrai todas as chaves de tradução usadas nos arquivos PHP
 */
function extractTranslationKeysFromCode(): array {
    $rootPath = dirname(__DIR__, 2);
    $keysFound = [];
    
    // Diretórios para escanear
    $directories = [
        $rootPath . '/pages',
        $rootPath . '/templates',
        $rootPath . '/admin',
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) continue;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            
            $content = file_get_contents($file->getPathname());
            $relativePath = str_replace($rootPath, '', $file->getPathname());
            
            // Padrão: getTranslation('key', $lang, 'context')
            if (preg_match_all("/getTranslation\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*[^,]+\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $key = $match[1];
                    $context = $match[2];
                    
                    if (!isset($keysFound[$context])) {
                        $keysFound[$context] = [];
                    }
                    if (!isset($keysFound[$context][$key])) {
                        $keysFound[$context][$key] = [];
                    }
                    $keysFound[$context][$key][] = $relativePath;
                }
            }
            
            // Padrão: $translations['key'] em arrays pré-carregados
            if (preg_match_all("/['\"]([a-z_\.]+)['\"]\s*,/", $content, $matches)) {
                // Detecta contexto do arquivo
                $fileContext = 'unknown';
                if (strpos($relativePath, 'results_clubs') !== false) $fileContext = 'results_clubs';
                elseif (strpos($relativePath, 'results_business') !== false) $fileContext = 'results_business';
                elseif (strpos($relativePath, 'results_services') !== false) $fileContext = 'results_services';
                elseif (strpos($relativePath, 'results_streets') !== false) $fileContext = 'results_streets';
                elseif (strpos($relativePath, 'header') !== false) $fileContext = 'header';
                elseif (strpos($relativePath, 'footer') !== false) $fileContext = 'footer';
                
                foreach ($matches[1] as $possibleKey) {
                    // Filtra apenas chaves válidas (sem variáveis)
                    if (preg_match('/^[a-z_\.]+$/', $possibleKey) && strlen($possibleKey) > 3) {
                        if (!isset($keysFound[$fileContext])) {
                            $keysFound[$fileContext] = [];
                        }
                        if (!isset($keysFound[$fileContext][$possibleKey])) {
                            $keysFound[$fileContext][$possibleKey] = [];
                        }
                        if (!in_array($relativePath, $keysFound[$fileContext][$possibleKey])) {
                            $keysFound[$fileContext][$possibleKey][] = $relativePath;
                        }
                    }
                }
            }
        }
    }
    
    return $keysFound;
}

/**
 * Busca todas as traduções existentes no banco
 */
function getExistingTranslationsFromDB(): array {
    try {
        global $pdo;
        if (!isset($pdo)) {
            require_once dirname(__DIR__, 2) . '/core/db.php';
        }
        
        $stmt = $pdo->query("
            SELECT DISTINCT language_code, context, translation_key
            FROM translations
            ORDER BY context, translation_key
        ");
        
        $dbTranslations = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lang = $row['language_code'];
            $ctx = $row['context'];
            $key = $row['translation_key'];
            
            if (!isset($dbTranslations[$ctx])) {
                $dbTranslations[$ctx] = [];
            }
            if (!isset($dbTranslations[$ctx][$key])) {
                $dbTranslations[$ctx][$key] = [];
            }
            $dbTranslations[$ctx][$key][] = $lang;
        }
        
        return $dbTranslations;
        
    } catch (PDOException $e) {
        error_log("DB Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Compara código vs banco e identifica faltantes
 */
function findMissingTranslations(array $codeKeys, array $dbKeys): array {
    $missing = [];
    $supportedLangs = ['pt-br', 'en-us', 'es'];
    
    foreach ($codeKeys as $context => $keys) {
        foreach ($keys as $key => $files) {
            // Verifica se existe no banco
            if (!isset($dbKeys[$context]) || !isset($dbKeys[$context][$key])) {
                // Totalmente ausente
                $missing[] = [
                    'context' => $context,
                    'key' => $key,
                    'missing_langs' => $supportedLangs,
                    'files' => $files,
                    'severity' => 'critical',
                ];
            } else {
                // Existe mas falta em alguns idiomas
                $presentLangs = $dbKeys[$context][$key];
                $missingLangs = array_diff($supportedLangs, $presentLangs);
                
                if (!empty($missingLangs)) {
                    $missing[] = [
                        'context' => $context,
                        'key' => $key,
                        'missing_langs' => array_values($missingLangs),
                        'files' => $files,
                        'severity' => 'warning',
                    ];
                }
            }
        }
    }
    
    return $missing;
}

// ============================================================
// EXECUÇÃO
// ============================================================

$startTime = microtime(true);

echo "<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <title>Translation Diagnostic Tool - BacoSearch</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #e74c3c; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .stats { display: flex; gap: 20px; margin: 20px 0; }
        .stat-box { flex: 1; padding: 15px; border-radius: 5px; text-align: center; }
        .stat-box.info { background: #3498db; color: white; }
        .stat-box.warning { background: #f39c12; color: white; }
        .stat-box.critical { background: #e74c3c; color: white; }
        .stat-box.success { background: #2ecc71; color: white; }
        .stat-number { font-size: 2em; font-weight: bold; }
        .stat-label { font-size: 0.9em; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #34495e; color: white; font-weight: 600; }
        tr:hover { background: #f9f9f9; }
        .critical-row { background: #ffe6e6; }
        .warning-row { background: #fff8e6; }
        .badge { padding: 3px 8px; border-radius: 3px; font-size: 0.85em; font-weight: bold; }
        .badge-critical { background: #e74c3c; color: white; }
        .badge-warning { background: #f39c12; color: white; }
        .code { font-family: 'Courier New', monospace; background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }
        .file-list { font-size: 0.85em; color: #666; margin-top: 5px; }
        .sql-output { background: #2c3e50; color: #ecf0f1; padding: 20px; border-radius: 5px; overflow-x: auto; margin: 20px 0; }
        .sql-output code { font-family: 'Courier New', monospace; }
        .copy-btn { background: #3498db; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; margin-top: 10px; }
        .copy-btn:hover { background: #2980b9; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Translation Diagnostic Report</h1>";
echo "<p><strong>Scan Date:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Extração
echo "<p>⏳ Scanning codebase for translation keys...</p>";
$codeKeys = extractTranslationKeysFromCode();

echo "<p>⏳ Querying database for existing translations...</p>";
$dbKeys = getExistingTranslationsFromDB();

echo "<p>⏳ Comparing and identifying missing translations...</p>";
$missing = findMissingTranslations($codeKeys, $dbKeys);

// Estatísticas
$totalCodeKeys = 0;
foreach ($codeKeys as $ctx => $keys) {
    $totalCodeKeys += count($keys);
}

$totalDbKeys = 0;
foreach ($dbKeys as $ctx => $keys) {
    $totalDbKeys += count($keys);
}

$criticalMissing = array_filter($missing, fn($m) => $m['severity'] === 'critical');
$warningMissing = array_filter($missing, fn($m) => $m['severity'] === 'warning');

echo "<div class='stats'>
    <div class='stat-box info'>
        <div class='stat-number'>{$totalCodeKeys}</div>
        <div class='stat-label'>Keys in Code</div>
    </div>
    <div class='stat-box success'>
        <div class='stat-number'>{$totalDbKeys}</div>
        <div class='stat-label'>Keys in Database</div>
    </div>
    <div class='stat-box critical'>
        <div class='stat-number'>" . count($criticalMissing) . "</div>
        <div class='stat-label'>Completely Missing</div>
    </div>
    <div class='stat-box warning'>
        <div class='stat-number'>" . count($warningMissing) . "</div>
        <div class='stat-label'>Partial Translation</div>
    </div>
</div>";

// Tabela de resultados
if (empty($missing)) {
    echo "<h2 style='color: #2ecc71;'>✅ All translations are complete!</h2>";
} else {
    echo "<h2>⚠️ Missing Translations (" . count($missing) . " issues found)</h2>";
    echo "<table>
        <thead>
            <tr>
                <th>Severity</th>
                <th>Context</th>
                <th>Translation Key</th>
                <th>Missing Languages</th>
                <th>Used in Files</th>
            </tr>
        </thead>
        <tbody>";
    
    foreach ($missing as $item) {
        $rowClass = $item['severity'] === 'critical' ? 'critical-row' : 'warning-row';
        $badgeClass = $item['severity'] === 'critical' ? 'badge-critical' : 'badge-warning';
        $severityText = strtoupper($item['severity']);
        
        $langsList = implode(', ', $item['missing_langs']);
        $filesList = implode('<br>', array_map(fn($f) => "<span class='code'>$f</span>", array_slice($item['files'], 0, 3)));
        if (count($item['files']) > 3) {
            $filesList .= '<br><em>+' . (count($item['files']) - 3) . ' more...</em>';
        }
        
        echo "<tr class='{$rowClass}'>
            <td><span class='badge {$badgeClass}'>{$severityText}</span></td>
            <td><code>{$item['context']}</code></td>
            <td><code>{$item['key']}</code></td>
            <td>{$langsList}</td>
            <td class='file-list'>{$filesList}</td>
        </tr>";
    }
    
    echo "</tbody></table>";
    
    // Gerar SQL para popular as faltantes
    echo "<h2>📝 SQL Script to Populate Missing Translations</h2>";
    echo "<p>Copy and execute this SQL in your database to add missing translations:</p>";
    
    $sqlStatements = [];
    foreach ($missing as $item) {
        foreach ($item['missing_langs'] as $lang) {
            $ctx = $item['context'];
            $key = $item['key'];
            
            // Valor padrão baseado na chave (para facilitar identificação)
            $defaultValue = ucwords(str_replace('_', ' ', $key));
            
            $sqlStatements[] = "  ('$lang', '$ctx', '$key', '$defaultValue', NOW(), NOW())";
        }
    }
    
    if (!empty($sqlStatements)) {
        $sqlScript = "INSERT IGNORE INTO translations (language_code, context, translation_key, translation_value, created_at, updated_at)\nVALUES\n";
        $sqlScript .= implode(",\n", $sqlStatements) . ";";
        
        echo "<div class='sql-output'><pre><code>" . htmlspecialchars($sqlScript) . "</code></pre></div>";
        echo "<button class='copy-btn' onclick='copySQL()'>📋 Copy SQL to Clipboard</button>";
        
        echo "<script>
        function copySQL() {
            const sql = `" . addslashes($sqlScript) . "`;
            navigator.clipboard.writeText(sql).then(() => {
                alert('SQL copied to clipboard!');
            });
        }
        </script>";
    }
}

$endTime = microtime(true);
$elapsed = number_format($endTime - $startTime, 2);

echo "<p style='margin-top: 40px; color: #666; font-size: 0.9em;'>Scan completed in {$elapsed} seconds</p>";
echo "</div></body></html>";
?>
