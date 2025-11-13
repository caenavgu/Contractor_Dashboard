<?php
// public/tools/audit-probe.php
declare(strict_types=1);

require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../app/Repositories/AuditLogRepository.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $repo = new AuditLogRepository($pdo);

    // Usa el user actual de sesión, o permite ?uid=A000000001
    $uid = $_GET['uid'] ?? ($_SESSION['user']['user_id'] ?? null);
    if (!$uid) {
        echo "No hay user_id en sesión. Inicia sesión primero o pasa ?uid=A000000001\n";
        exit;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $sid = session_id() ?: 'NO-PHP-SESSION';

    $repo->logSignIn((string)$uid, (string)$sid);

    echo "OK audit sign_in inserted for user_id={$uid} session_id={$sid} at ".date('c')."\n";
} catch (Throwable $e) {
    echo "FAIL: ".$e->getMessage()."\n";
}
