<?php
// app/Repositories/UserDetailsRepository.php
// -------------------------------------------------------------
// Acceso a la tabla user_details (esquema actual):
// user_id (PK), first_name, last_name, phone_number,
// epa_certification_number, certifying_organization, epa_photo_url,
// created_at, updated_at
// -------------------------------------------------------------
declare(strict_types=1);

class UserDetailsRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Inserta detalles del usuario.
     * Espera keys:
     *  - user_id (string, requerido)
     *  - first_name (string|null)
     *  - last_name (string|null)
     *  - phone_number (string|null)
     *  - epa_certification_number (string|null)
     *  - certifying_organization (string|null)
     *  - epa_photo_url (string|null)  // URL relativa al archivo subido
     */
    public function create(array $data): void
    {
        $sql = "INSERT INTO user_details
                    (user_id,
                     first_name,
                     last_name,
                     phone_number,
                     epa_certification_number,
                     certifying_organization,
                     epa_photo_url,
                     created_at,
                     updated_at)
                VALUES
                    (:user_id,
                     :first_name,
                     :last_name,
                     :phone_number,
                     :epa_certification_number,
                     :certifying_organization,
                     :epa_photo_url,
                     NOW(),
                     NOW())";

        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':user_id'                 => $data['user_id'],
            ':first_name'              => $data['first_name'] ?? null,
            ':last_name'               => $data['last_name'] ?? null,
            ':phone_number'            => $data['phone_number'] ?? null,
            ':epa_certification_number'=> $data['epa_certification_number'] ?? null,
            ':certifying_organization' => $data['certifying_organization'] ?? null,
            ':epa_photo_url'           => $data['epa_photo_url'] ?? null,
        ]);
    }

    /** (Opcional) Obtener detalles por user_id. */
    public function find_by_user_id(string $user_id): ?array
    {
        $sql = "SELECT user_id, first_name, last_name, phone_number,
                       epa_certification_number, certifying_organization,
                       epa_photo_url, created_at, updated_at
                  FROM user_details
                 WHERE user_id = :user_id
                 LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':user_id' => $user_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
