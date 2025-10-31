<?php
// app/Repositories/session_repository.php
// -------------------------------------------------------------
// Gestión de sesiones persistentes en tabla sessions
// -------------------------------------------------------------
declare(strict_types=1);

class SessionRepository
{
    public function __construct(private PDO $pdo) {}

    public function create(string $user_id, string $session_id, string $session_token, string $ip, string $ua, int $ttl_seconds): void
    {
        $sql = "INSERT INTO sessions (session_id, user_id, session_token, ip_address, user_agent, expires_at, created_at)
                VALUES (:sid, :uid, :token, :ip, :ua, :exp, NOW())";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':sid' => $session_id,
            ':uid' => $user_id,
            ':token' => $session_token,
            ':ip' => $ip,
            ':ua' => $ua,
            ':exp' => date('Y-m-d H:i:s', time() + $ttl_seconds),
        ]);
    }

}
