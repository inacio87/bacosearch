<?php
/**
 * Migration Runner - Executa via CLI e gera log
 * Uso: php migrate_verticals.php
 */

$logFile = __DIR__ . '/logs/migration_' . date('Y-m-d_H-i-s') . '.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}

function logMsg($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$msg}\n";
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

logMsg("========================================");
logMsg("Migration Script Started");
logMsg("========================================");

// Conexão direta ao banco (sem bootstrap.php)
$envFile = __DIR__ . '/.env';
if (!is_file($envFile)) $envFile = __DIR__ . '/../.env';
$envVars = [];
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || trim($line) === '') continue;
        @list($key, $value) = explode('=', $line, 2);
        if ($key !== null) $envVars[trim($key)] = trim($value ?? '');
    }
} else {
    logMsg("ERRO: Arquivo .env não encontrado.");
    exit(1);
}

$dsn = 'mysql:host=' . $envVars['DB_HOST'] . ';dbname=' . $envVars['DB_DATABASE'] . ';charset=' . ($envVars['DB_CHARSET'] ?? 'utf8mb4');
$user = $envVars['DB_USERNAME'];
$pass = $envVars['DB_PASSWORD'];

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    logMsg("✓ Conexão DB OK");
} catch (Throwable $e) {
    logMsg("✗ ERRO ao conectar DB: " . $e->getMessage());
    exit(1);
}

$sqlFile = __DIR__ . '/admin/migrations/2025-11-03_add_verticals.sql';
if (!is_file($sqlFile)) {
    logMsg("✗ ERRO: Migration file not found: {$sqlFile}");
    exit(1);
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    logMsg("✗ ERRO: Failed to read migration file.");
    exit(1);
}

logMsg("✓ Migration file loaded: " . basename($sqlFile));

// Split statements by semicolon not inside quotes
$statements = [];
$buffer = '';
$inSingle = false; 
$inDouble = false; 
$len = strlen($sql);
for ($i=0; $i<$len; $i++) {
    $ch = $sql[$i];
    if ($ch === "'" && !$inDouble) { $inSingle = !$inSingle; $buffer .= $ch; continue; }
    if ($ch === '"' && !$inSingle) { $inDouble = !$inDouble; $buffer .= $ch; continue; }
    if ($ch === ';' && !$inSingle && !$inDouble) {
        $statements[] = trim($buffer);
        $buffer = '';
        continue;
    }
    $buffer .= $ch;
}
$buffer = trim($buffer);
if ($buffer !== '') { $statements[] = $buffer; }

// Remove comments and empty statements
$clean = [];
foreach ($statements as $stmt) {
    $lines = preg_split('/\r?\n/', $stmt);
    $filtered = [];
    foreach ($lines as $line) {
        $t = trim($line);
        if ($t === '' || strpos($t, '--') === 0) continue;
        $filtered[] = $line;
    }
    $stmt2 = trim(implode("\n", $filtered));
    if ($stmt2 !== '') $clean[] = $stmt2;
}

logMsg("Total statements to execute: " . count($clean));
logMsg("========================================");

$executed = 0; 
$errors = 0;
try { 
    $pdo->exec('SET SESSION sql_mode = REPLACE(@@sql_mode, "ONLY_FULL_GROUP_BY", "")'); 
    logMsg("✓ SQL mode adjusted");
} catch (Throwable $e) {
    logMsg("⚠ Warning adjusting SQL mode: " . $e->getMessage());
}

foreach ($clean as $idx => $stmt) {
    $stmtPreview = substr(str_replace(["\n", "\r"], ' ', $stmt), 0, 80);
    try {
        $pdo->exec($stmt);
        $executed++;
        logMsg("✓ [" . ($idx+1) . "/" . count($clean) . "] OK: " . $stmtPreview);
    } catch (Throwable $e) {
        $errors++;
        logMsg("✗ [" . ($idx+1) . "/" . count($clean) . "] ERROR: " . $e->getMessage());
        logMsg("   Statement preview: " . $stmtPreview);
    }
}

logMsg("========================================");
logMsg("Migration Complete");
logMsg("Executed: {$executed} | Errors: {$errors}");
logMsg("========================================");
logMsg("Log saved to: {$logFile}");

if ($errors > 0) {
    exit(1);
} else {
    exit(0);
}
