<?php
// app/Repositories/user_repository.php
// -------------------------------------------------------------
// Repositorio usuario: crea fila en `users` y en `user_details`
// - Inserta `user_type` (ej: 'TEC', 'CON', 'SOP', 'ADM')
// - Transaccional: si falla cualquier insert, hace rollback
// -------------------------------------------------------------
declare(strict_types=1);

class UserRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Buscar usuario por email (mínimos campos para autenticación)
     */
    public function find_by_email(string $email): ?array
    {
        $sql = "SELECT user_id, email, password_hash, is_active, email_verified_at FROM users WHERE email = :email LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':email' => $email]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Crear usuario + detalles (transactional)
     * $data espera:
     *  - email, password_hash, contractor_id (nullable),
     *  - email_verification_token, email_verification_expires_at
     *  - user_type => código CHAR(3) (ej. 'TEC')
     *  - details => array con keys: first_name, last_name, phone_number,
     *      epa_certification_number, certifying_organization,
     *      epa_photo_filename, epa_photo_mime, epa_photo_size, epa_photo_checksum
     */
public function create(array $data): string
{
    $this->pdo->beginTransaction();
    try {
        $user_type = strtoupper(trim((string)($data['user_type'] ?? 'TEC')));
        if ($user_type === '' || strlen($user_type) > 4) {
            throw new InvalidArgumentException('Invalid user_type provided');
        }

        $sql_u = "INSERT INTO users
            (email, password_hash, contractor_id, user_type, is_active, email_verification_token, email_verification_expires_at, created_at)
            VALUES (:email, :pwhash, :contractor_id, :user_type, 0, :token, :token_expires, NOW())";
        $st = $this->pdo->prepare($sql_u);
        $st->execute([
            ':email' => $data['email'],
            ':pwhash' => $data['password_hash'],
            ':contractor_id' => $data['contractor_id'] ?? null,
            ':user_type' => $user_type,
            ':token' => $data['email_verification_token'] ?? null,
            ':token_expires' => $data['email_verification_expires_at'] ?? null,
        ]);

        // Obtener el user_id real (puede ser 'U12345' generado por trigger)
        $sql_select_id = "SELECT user_id FROM users WHERE email = :email LIMIT 1 FOR UPDATE";
        $st_sel = $this->pdo->prepare($sql_select_id);
        $st_sel->execute([':email' => $data['email']]);
        $row = $st_sel->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['user_id'])) {
            throw new RuntimeException('Unable to determine created user_id after insert.');
        }
        $user_id = (string)$row['user_id']; // mantener como string

        // Insertar en user_details usando el user_id como string (sin cast a int)
        $details = $data['details'] ?? [];
        $sql_d = "INSERT INTO user_details
            (user_id, first_name, last_name, phone_number, epa_certification_number, certifying_organization, epa_photo_url, epa_photo_filename, epa_photo_mime, epa_photo_size, epa_photo_checksum, created_at)
            VALUES
            (:user_id, :first_name, :last_name, :phone_number, :epa_cert, :cert_org, :epa_photo_url, :epa_filename, :epa_mime, :epa_size, :epa_checksum, NOW())";
        $st2 = $this->pdo->prepare($sql_d);
        $st2->execute([
            ':user_id' => $user_id,
            ':first_name' => $details['first_name'] ?? null,
            ':last_name' => $details['last_name'] ?? null,
            ':phone_number' => $details['phone_number'] ?? null,
            ':epa_cert' => $details['epa_certification_number'] ?? null,
            ':cert_org' => $details['certifying_organization'] ?? null,
            ':epa_photo_url' => $details['epa_photo_url'] ?? null,
            ':epa_filename' => $details['epa_photo_filename'] ?? null,
            ':epa_mime' => $details['epa_photo_mime'] ?? null,
            ':epa_size' => $details['epa_photo_size'] ?? null,
            ':epa_checksum' => $details['epa_photo_checksum'] ?? null,
        ]);

        $this->pdo->commit();
        return $user_id;
    } catch (Throwable $e) {
        $this->pdo->rollBack();
        throw new RuntimeException('DB error creating user: ' . $e->getMessage(), 0, $e);
    }
}


    /**
     * Buscar usuario por token de verificación
     */
    public function find_by_verification_token(string $token): ?array
    {
        $sql = "SELECT * FROM users WHERE email_verification_token = :token AND email_verification_expires_at >= NOW() LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':token'=>$token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Marcar email verificado (users). No tocamos user_details aquí.
     */
    public function mark_email_verified(int $user_id): void
    {
        $sql = "UPDATE users SET email_verified_at = NOW(), email_verification_token = NULL, email_verification_expires_at = NULL WHERE user_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([':id'=>$user_id]);
    }

    /**
     * Activar usuario (aprobado por admin)
     */
    public function activate_user(int $user_id, ?int $admin_id = null): void
    {
        $sql = "UPDATE users SET is_active = 1 WHERE user_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([':id'=>$user_id]);
    }
}
