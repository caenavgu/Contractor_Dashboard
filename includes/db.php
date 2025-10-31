<?php
// includes/db.php
// -------------------------------------------------------------
// Conexión PDO a MySQL (5.7) con charset utf8mb4.
// Valida credenciales y lanza errores claros.
// -------------------------------------------------------------
declare(strict_types=1);

/**
 * @param array{host:string,port:int,name:string,user:string,pass:string,charset:string} $db
 */
function get_pdo(array $db): PDO
{
    $host    = trim((string)($db['host']    ?? ''));
    $port    = (int)($db['port']    ?? 3306);
    $name    = trim((string)($db['name']    ?? ''));
    $user    = trim((string)($db['user']    ?? ''));
    $pass    = (string)($db['pass']    ?? '');
    $charset = trim((string)($db['charset'] ?? 'utf8mb4'));

    if ($host === '' || $name === '' || $user === '') {
        throw new RuntimeException('DB config inválida: host/name/user no pueden estar vacíos.');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
}
