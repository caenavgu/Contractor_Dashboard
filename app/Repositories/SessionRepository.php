<?php
// app/Repositories/SessionRepository.php
// -------------------------------------------------------------
// Gestión de sesiones persistentes en tabla `sessions`
// -------------------------------------------------------------
declare(strict_types=1);

class SessionRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Crea un registro de sesión.
     *
     * @param int         $userId
     * @param string      $token            token opaco para cookie (session_token)
     * @param string      $ip               IP del cliente
     * @param string      $ua               User-Agent
     * @param int         $ttlSeconds       segundos hasta expiración
     * @param string|null $phpSessionId     id de la sesión PHP (opcional)
     */
    public function create(
    string $userId,      // ← string, no int
    string $token,
    string $ip,
    string $ua,
    int $ttlSeconds,
    ?string $phpSessionId = null
    ): void {
        $sql = "INSERT INTO sessions (session_id, user_id, session_token, ip_address, user_agent, expires_at, created_at)
                VALUES (:sid, :uid, :tok, :ip, :ua, :exp, NOW())";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':sid' => $phpSessionId,
            ':uid' => $userId,  // ← pasa el string tal cual
            ':tok' => $token,
            ':ip'  => $ip,
            ':ua'  => mb_substr($ua, 0, 255),
            ':exp' => date('Y-m-d H:i:s', time() + $ttlSeconds),
        ]);
    }

    /** Obtiene una sesión por token (aunque esté expirada la retornará). */
    public function getByToken(string $token): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM sessions WHERE session_token = :t LIMIT 1");
        $st->execute([':t' => $token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Borra una sesión por token. */
    public function deleteByToken(string $token): void
    {
        $st = $this->pdo->prepare("DELETE FROM sessions WHERE session_token = :t");
        $st->execute([':t' => $token]);
    }

    /** Borra todas las sesiones de un usuario. */
    public function deleteAllForUser(string $userId): void
    {
        $st = $this->pdo->prepare("DELETE FROM sessions WHERE user_id = :u");
        $st->execute([':u' => $userId]);
    }

    public function markSeenByToken(string $token, int $idleTtlSeconds = 1800, int $absoluteTtlDays = 15): void
    {
        // Refresco “deslizable” de 30 min con tope absoluto de 15 días desde created_at
        $idle   = max(60, (int)$idleTtlSeconds);
        $absDay = max(1, (int)$absoluteTtlDays);

        // INTERVAL no acepta placeholders para la unidad; interpolamos enteros saneados
        $sql = "
            UPDATE sessions
            SET last_seen_at = NOW(),
                expires_at   = LEAST(
                    DATE_ADD(created_at, INTERVAL {$absDay} DAY),
                    DATE_ADD(NOW(),       INTERVAL {$idle} SECOND)
                )
            WHERE session_token = :t
            LIMIT 1
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([':t' => $token]);
    }

}
