<?php
// app/Repositories/UserRepository.php
// -------------------------------------------------------------
// Acceso a la tabla 'users' (credenciales, estados, verificación).
// Nota: el user_id es VARCHAR(16) y lo genera un trigger BEFORE INSERT.
// -------------------------------------------------------------
declare(strict_types=1);

class UserRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Crea un nuevo usuario en 'users'.
     *
     * $data requiere:
     *  - email           (string)
     *  - username        (string)
     *  - password_hash   (string)
     *  - user_type       (string: 'ADM','TEC','CON','SOP')
     *  - contractor_id   (int|null)
     *  - status          (string: 'PENDING','ACTIVE','INACTIVE','REJECTED')
     *
     * Devuelve el user_id generado por el trigger.
     */
    public function create(array $data): string
    {
        $sql = "INSERT INTO users (
                    email,
                    username,
                    password_hash,
                    user_type,
                    contractor_id,
                    status,
                    created_at,
                    updated_at
                ) VALUES (
                    :email,
                    :username,
                    :password_hash,
                    :user_type,
                    :contractor_id,
                    :status,
                    NOW(),
                    NOW()
                )";

        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':email'         => $data['email'],
            ':username'      => $data['username'],
            ':password_hash' => $data['password_hash'],
            ':user_type'     => $data['user_type'],
            ':contractor_id' => $data['contractor_id'] ?? null,
            ':status'        => $data['status'] ?? 'PENDING',
        ]);

        // Como el PK (user_id) lo genera un trigger y es VARCHAR,
        // lastInsertId() no sirve. Tomamos el user_id recién creado por email.
        $q = $this->pdo->prepare("SELECT user_id FROM users WHERE email = :e LIMIT 1");
        $q->execute([':e' => $data['email']]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['user_id'])) {
            throw new RuntimeException('DB error: user created but user_id not retrievable.');
        }

        return (string)$row['user_id'];
    }

    /**
     * Asigna/actualiza el contractor_id de un usuario.
     * No cambia user_type ni status; tus triggers se encargan de ello.
     */
    public function assign_contractor(string $user_id, int $contractor_id): void
    {
        $sql = "UPDATE users
                   SET contractor_id = :cid,
                       updated_at = NOW()
                 WHERE user_id = :uid";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':cid' => $contractor_id,
            ':uid' => $user_id,
        ]);
    }

    public function find_by_id(string $user_id): ?array
    {
        $sql = "SELECT * FROM users WHERE user_id = :id LIMIT 1";
        $st  = $this->pdo->prepare($sql);
        $st->execute([':id' => $user_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function find_by_email(string $email): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM users WHERE email = :e LIMIT 1");
        $st->execute([':e' => $email]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function list_pending_verified(): array
    {
        $sql = "SELECT u.*
                  FROM users u
                 WHERE u.status = 'PENDING'
                   AND u.email_verified_at IS NOT NULL
              ORDER BY u.created_at DESC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function approve_user(string $user_id, string $admin_user_id): void
    {
        $sql = "UPDATE users
                   SET status = 'ACTIVE',
                       approved_by = :by,
                       approved_at = NOW(),
                       updated_at  = NOW()
                 WHERE user_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([':by' => $admin_user_id, ':id' => $user_id]);
    }

    public function reject_user(string $user_id, string $reason, string $admin_user_id): void
    {
        $sql = "UPDATE users
                   SET status = 'REJECTED',
                       rejected_by = :by,
                       rejected_at = NOW(),
                       rejection_reason = :reason,
                       updated_at = NOW()
                 WHERE user_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([':by' => $admin_user_id, ':reason' => $reason, ':id' => $user_id]);
    }

    public function update_user_type(string $user_id, string $new_type): void
    {
        $sql = "UPDATE users
                   SET user_type = :t,
                       updated_at = NOW()
                 WHERE user_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([':t' => $new_type, ':id' => $user_id]);
    }

    public function promote_users_to_contractor_by_contractor_id(int $contractor_id): void
    {
        $sql = "UPDATE users
                   SET user_type = 'CON',
                       updated_at = NOW()
                 WHERE contractor_id = :cid
                   AND user_type <> 'ADM'";
        $st = $this->pdo->prepare($sql);
        $st->execute([':cid' => $contractor_id]);
    }

    /* ---------- Email verification helpers ---------- */

    public function set_email_verification(string $user_id, string $token, string $expires_at): void
    {
        $sql = "UPDATE users
                   SET email_verification_token = :t,
                       email_verification_expires_at = :e,
                       updated_at = NOW()
                 WHERE user_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([':t' => $token, ':e' => $expires_at, ':id' => $user_id]);
    }

    public function find_by_verification_token(string $token): ?array
    {
        $sql = "SELECT *
                  FROM users
                 WHERE email_verification_token = :t
                   AND email_verification_expires_at IS NOT NULL
                   AND email_verification_expires_at > NOW()
                 LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':t' => $token]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function mark_email_verified(string $user_id): void
    {
        $sql = "UPDATE users
                   SET email_verified_at = NOW(),
                       email_verification_token = NULL,
                       email_verification_expires_at = NULL,
                       updated_at = NOW()
                 WHERE user_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([':id' => $user_id]);
    }
}
