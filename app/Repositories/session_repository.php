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
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + $ttl_seconds);

        $sql = "INSERT INTO sessions (session_id, user_id, session_token, ip_address, user_agent, last_seen_at, created_at, expires_at)
                VALUES (:sid, :uid, :tok, :ip, :ua, :last_seen, :created, :expires)";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':sid' => $session_id,
            ':uid' => $user_id,
            ':tok' => $session_token,
            ':ip'  => $ip,
            ':ua'  => substr($ua, 0, 255),
            ':last_seen' => $now,
            ':created'   => $now,
            ':expires'   => $expires,
        ]);
    }
}
