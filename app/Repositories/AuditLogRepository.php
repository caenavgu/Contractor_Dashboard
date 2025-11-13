<?php
// app/Repositories/AuditLogRepository.php
// -------------------------------------------------------------
// Inserta eventos de auditoría en audit_logs
//  - actor_user_id: VARCHAR(16) NULL (puede ser el propio user o el admin)
//  - entity_type, entity_id: obligatorios en esquema actual (no NULL para entity_type)
//  - action: string corto (ej: 'sign_in', 'sign_out', 'user_created')
//  - data_json: JSON con ip, ua y cualquier metadato
// -------------------------------------------------------------
declare(strict_types=1);

class AuditLogRepository
{
    public function __construct(private PDO $pdo) {}

    /** Inserta un log genérico. $meta se serializa a JSON y se fusiona con ip/ua. */
    public function log(
        ?string $actor_user_id,
        string  $action,
        ?string $ip,
        array   $meta = [],
        ?string $entity_type = null,
        ?string $entity_id   = null
    ): void {
        try {
            $actor = $actor_user_id !== null ? trim($actor_user_id) : null;
            $ip    = $ip !== null ? trim($ip) : null;
            $ua    = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $payload = $meta;
            if ($ip !== null) $payload['ip'] = $ip;
            if ($ua)          $payload['ua'] = (string)$ua;

            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Nunca NULL: si no viene, usamos 'event' por defecto
            $etype = ($entity_type !== null && trim($entity_type) !== '') ? trim($entity_type) : 'event';

            $sql = "INSERT INTO audit_logs
                        (actor_user_id, entity_type, entity_id, action, data_json, created_at)
                    VALUES
                        (:actor_user_id, :entity_type, :entity_id, :action, :data_json, NOW())";

            $st = $this->pdo->prepare($sql);
            $st->execute([
                ':actor_user_id' => ($actor !== '') ? $actor : null,
                ':entity_type'   => $etype,
                ':entity_id'     => $entity_id,
                ':action'        => $action,
                ':data_json'     => $json,
            ]);
        } catch (\Throwable $e) {
            app_log('AUDIT repo log() EXCEPTION: '.$e->getMessage());
        }
    }

    // Helpers de dominio

    public function logSignIn(string $user_id, string $session_id): void
    {
        $this->log($user_id, 'sign_in', $this->ip(), ['session_id' => $session_id], 'user', $user_id);
    }

    public function logSignOut(?string $user_id, ?string $session_id): void
    {
        $this->log($user_id, 'sign_out', $this->ip(), ['session_id' => $session_id], 'user', $user_id ?? null);
    }

    public function logUserCreated(string $user_id, array $meta = []): void
    {
        $this->log($user_id, 'user_created', $this->ip(), $meta, 'user', $user_id);
    }

    public function logEmailVerified(string $user_id): void
    {
        $this->log($user_id, 'email_verified', $this->ip(), [], 'user', $user_id);
    }

    private function ip(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR','HTTP_CLIENT_IP','REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = (string)$_SERVER[$k];
                if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
                return $ip;
            }
        }
        return 'unknown';
    }
}
