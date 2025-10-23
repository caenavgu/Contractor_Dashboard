<?php
// includes/db.php
// -------------------------------------------------------------
// PDO para MySQL 5.7 (utf8mb4). Usa config del bootstrap.
// -------------------------------------------------------------
declare(strict_types=1);

function get_pdo(array $app_config): PDO
{
    $host = $app_config['db_host'] ?? 'localhost';
    $port = (int)($app_config['db_port'] ?? 3306);
    $name = $app_config['db_name'] ?? '';
    $user = $app_config['db_user'] ?? '';
    $pass = $app_config['db_pass'] ?? '';
    $charset = $app_config['db_charset'] ?? 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return new PDO($dsn, $user, $pass, $options);
}
