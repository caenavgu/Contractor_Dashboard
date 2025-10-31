<?php
// app/Repositories/UserDetailsRepository.php
// -------------------------------------------------------------
// Acceso a la tabla user_details.
// Inserta los detalles del usuario moviendo los campos EPA aquí
// (según tu cambio reciente en la BD).
// -------------------------------------------------------------
declare(strict_types=1);

class UserDetailsRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Crea el registro en user_details.
     * Espera keys:
     *  - user_id (string, requerido)
     *  - first_name, last_name (string)
     *  - phone_number (string)
     *  - certifying_organization (string)
     *  - epa_certification_number (string)
     *  - epa_photo_url (string|null)
     *  - epa_photo_filename (string|null)
     *  - epa_photo_mime (string|null)
     *  - epa_photo_size (int|null)
     *  - epa_photo_checksum (string|null)
     */
    public function create(array $data): void
    {
        $sql = "INSERT INTO user_details
                   (user_id, first_name, last_name, phone_number,
                    certifying_organization, epa_certification_number,
                    epa_photo_url, epa_photo_filename, epa_photo_mime,
                    epa_photo_size, epa_photo_checksum,
                    created_at, updated_at)
                VALUES
                   (:user_id, :first_name, :last_name, :phone_number,
                    :certifying_organization, :epa_certification_number,
                    :epa_photo_url, :epa_photo_filename, :epa_photo_mime,
                    :epa_photo_size, :epa_photo_checksum,
                    NOW(), NOW())";

        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':user_id'                 => $data['user_id'],
            ':first_name'              => $data['first_name'] ?? null,
            ':last_name'               => $data['last_name'] ?? null,
            ':phone_number'            => $data['phone_number'] ?? null,
            ':certifying_organization' => $data['certifying_organization'] ?? null,
            ':epa_certification_number'      => $data['epa_certification_number'] ?? null,
            ':epa_photo_url'           => $data['epa_photo_url'] ?? null,
            ':epa_photo_filename'      => $data['epa_photo_filename'] ?? null,
            ':epa_photo_mime'          => $data['epa_photo_mime'] ?? null,
            ':epa_photo_size'          => isset($data['epa_photo_size']) ? (int)$data['epa_photo_size'] : null,
            ':epa_photo_checksum'      => $data['epa_photo_checksum'] ?? null,
        ]);
    }
}
