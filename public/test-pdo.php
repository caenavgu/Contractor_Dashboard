<?php
// public/test-pdo.php  (temporal: bórralo luego)
// Comprueba conexión y muestra OK/ERROR sin tocar el resto del sistema.
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../includes/helpers.php';
$app = read_config_ini([__DIR__ . '/../config/app.local.ini']);
try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $app['db_host'] ?? '127.0.0.1',
        (int)($app['db_port'] ?? 3306),
        $app['db_name'] ?? '',
        $app['db_charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $app['db_user'] ?? '', $app['db_pass'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    echo "<h2>PDO OK</h2>";
} catch (Throwable $e) {
    echo "<h2>PDO ERROR</h2><pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES) . "</pre>";
}
