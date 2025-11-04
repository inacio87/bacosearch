<?php
// MIGRATION SCRIPT AUTÔNOMO - NÃO USA INCLUDES DE LAYOUT
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
ob_end_flush();
flush();
header('Content-Type: text/html; charset=utf-8');
echo "<pre style='background:#222;color:#fff;padding:10px;'>[DEBUG] Script iniciado em: ".date('Y-m-d H:i:s')."</pre>\n";

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
    die('Arquivo .env não encontrado.');
}
$dsn = 'mysql:host=' . $envVars['DB_HOST'] . ';dbname=' . $envVars['DB_DATABASE'] . ';charset=' . ($envVars['DB_CHARSET'] ?? 'utf8mb4');
$user = $envVars['DB_USERNAME'];
$pass = $envVars['DB_PASSWORD'];
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "<b>Conexão DB OK</b><br>\n";
} catch (Throwable $e) {
    die('<b>Erro ao conectar DB:</b> '.htmlspecialchars($e->getMessage()));
}

$sqlFile = __DIR__ . '/admin/migrations/2025-11-03_add_verticals.sql';
if (!is_file($sqlFile)) {
    http_response_code(404);
    echo "Migration file not found: {$sqlFile}\n";
    exit;
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    http_response_code(500);
    echo "Failed to read migration file.\n";
    exit;
}

// Split statements by semicolon not inside quotes (simple heuristic adequate for this file)
$statements = [];
$buffer = '';
$inSingle = false; $inDouble = false; $len = strlen($sql);
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

$executed = 0; $errors = 0;
try { $pdo->exec('SET SESSION sql_mode = REPLACE(@@sql_mode, "ONLY_FULL_GROUP_BY", "")'); } catch (Throwable $e) {}

foreach ($clean as $idx => $stmt) {
    try {
        $pdo->exec($stmt);
        $executed++;
        echo "<span class='ok'>[OK]</span> Statement ".($idx+1)." executed.<br>\n";
    } catch (Throwable $e) {
        $errors++;
        echo "<span class='err'>[ERR]</span> Statement ".($idx+1).": ".htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')."<br>\n";
    }
}

echo "<br><hr/><strong>Done. Executed: {$executed}, Errors: {$errors}.</strong></pre>";
exit;
