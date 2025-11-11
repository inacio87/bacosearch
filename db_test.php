<?php
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "DB connection diagnostic\n";
echo "=======================\n";

$expected = ['DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS','DB_CHARSET'];
foreach ($expected as $k) {
    $val = getenv($k);
    $masked = ($k === 'DB_PASS' && $val !== false) ? str_repeat('*', max(4, strlen($val))) : ($val === false ? '[not set]' : $val);
    echo str_pad($k, 10) . ': ' . $masked . "\n";
}

echo "\nAttempting PDO...\n";
try {
    $pdo = db();
    $version = $pdo->query('SELECT VERSION() as v')->fetch()['v'] ?? 'unknown';
    echo "SUCCESS: Connected. MySQL version: {$version}\n";
} catch (Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}
