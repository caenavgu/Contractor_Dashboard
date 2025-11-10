<?php
// app/Repositories/ContractorRepository.php
// -------------------------------------------------------------
// Acceso a la tabla 'contractors'.
// Clave primaria: contractor_id (INT AUTO_INCREMENT)
// Estados esperados: 'PENDING' | 'ACTIVE' | 'INACTIVE' (u otros que uses)
// -------------------------------------------------------------
declare(strict_types=1);

class ContractorRepository
{
    public function __construct(private PDO $pdo) {}

    /** Devuelve el contractor por ID o null si no existe. */
    public function find_by_id(int $contractor_id): ?array
    {
        $sql = "SELECT * FROM contractors WHERE contractor_id = :id LIMIT 1";
        $st  = $this->pdo->prepare($sql);
        $st->execute([':id' => $contractor_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
    
    /** Devuelve el contractor por CAC (normalizado a mayúsculas) o null si no existe. */
    public function find_by_cac(string $cac_license_number): ?array
    {
        $cac = strtoupper(trim($cac_license_number));
        $sql = "SELECT * FROM contractors WHERE cac_license_number = :cac LIMIT 1";
        $st  = $this->pdo->prepare($sql);
        $st->execute([':cac' => $cac]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Crea un contractor en estado PENDING (o el que pases en $data['status']).
     * Espera claves (las que tengas en tu tabla):
     *  - cac_license_number (STRING, requerido)
     *  - company_name (STRING)
     *  - company_phone (STRING)
     *  - company_email (STRING)
     *  - company_website (STRING)
     *  - address, address_2, city, state_code, zip_code
     *  - status (por defecto 'PENDING')
     * Devuelve el ID insertado (int).
     */
    public function create_pending(array $data): int
    {
        $sql = "INSERT INTO contractors (
                    cac_license_number,
                    company_name, company_phone, company_email, company_website,
                    address, address_2, city, state_code, zip_code,
                    status, created_at, updated_at
                ) VALUES (
                    :cac_license_number,
                    :company_name, :company_phone, :company_email, :company_website,
                    :address, :address_2, :city, :state_code, :zip_code,
                    :status, NOW(), NOW()
                )";

        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':cac_license_number' => strtoupper(trim((string)($data['cac_license_number'] ?? ''))),
            ':company_name'       => strtoupper(trim((string)($data['company_name'] ?? ''))),
            ':company_phone'      => (string)($data['company_phone'] ?? ''),
            ':company_email'      => (string)($data['company_email'] ?? ''),
            ':company_website'    => (string)($data['company_website'] ?? ''),
            ':address'            => strtoupper(trim((string)($data['address'] ?? ''))),
            ':address_2'          => strtoupper(trim((string)($data['address_2'] ?? ''))),
            ':city'               => strtoupper(trim((string)($data['city'] ?? ''))),
            ':state_code'         => strtoupper(trim((string)($data['state_code'] ?? ''))),
            ':zip_code'           => (string)($data['zip_code'] ?? ''),
            ':status'             => (string)($data['status'] ?? 'PENDING'),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /** Lista todos los contractors en estado PENDING (para la pantalla de aprobaciones). */
    public function list_pending(): array
    {
        $sql = "SELECT contractor_id, cac_license_number, company_name, company_phone, company_email,
                       company_website, address, address_2, city, state_code, zip_code,
                       status, approved_by, approved_at, rejected_by, rejected_at, rejection_reason,
                       merge_by, merge_at, created_at, updated_at
                FROM contractors
                WHERE status = 'PENDING'
                ORDER BY created_at DESC, contractor_id DESC";
        $st = $this->pdo->query($sql);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Cambia status genérico. */
    public function set_status(int $contractor_id, string $status): void
    {
        // $status ∈ {'PENDING','ACTIVE','INACTIVE','REJECTED'}
        $sql = "UPDATE contractors SET status = :s, updated_at = NOW() WHERE contractor_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([':s' => $status, ':id' => $contractor_id]);
    }

    /** Aprueba contractor, marca quién aprueba y cuándo. */
    public function approve(int $contractor_id, string $admin_user_id): void
    {
        $sql = "UPDATE contractors
                   SET status = 'ACTIVE',
                       approved_by = :by,
                       approved_at = NOW(),
                       updated_at = NOW()
                 WHERE contractor_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([':by' => $admin_user_id, ':id' => $contractor_id]);
    }

    /** Rechaza contractor con razón. */
    public function reject(int $contractor_id, string $admin_user_id, string $reason): void
    {
        $sql = "UPDATE contractors
                   SET status = 'REJECTED',
                       rejected_by = :by,
                       rejected_at = NOW(),
                       rejection_reason = :reason,
                       updated_at = NOW()
                 WHERE contractor_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([':by' => $admin_user_id, ':reason' => $reason, ':id' => $contractor_id]);
    }

    /** (Opcional) Marca merge si en tu flujo haces consolidación desde staging. */
    public function mark_merged(int $contractor_id, ?string $admin_user_id = null): void
    {
        if ($admin_user_id === null) {
            $admin_user_id = (string)($_SESSION['user']['user_id'] ?? '');
        }

        $sql = "UPDATE contractors
                   SET merge_by = :admin,
                       merge_at = NOW(),
                       updated_at = NOW()
                 WHERE contractor_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':admin' => $admin_user_id !== '' ? $admin_user_id : null,
            ':id'    => $contractor_id,
        ]);
    }

    /**
     * Actualiza campos permitidos del contractor.
     * Solo hace update de las claves presentes en $fields y whitelisteadas.
     * Devuelve true si se actualizó (o no había cambios) y false si no encontró el ID.
     */
    public function update_fields(int $contractor_id, array $fields): bool
    {
        // Whitelist de columnas actualizables
        $allowed = [
            'cac_license_number',
            'company_name', 'company_phone', 'company_email', 'company_website',
            'address', 'address_2', 'city', 'state_code', 'zip_code',
            'status',
        ];

        $set = [];
        $params = [':id' => $contractor_id];

        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) continue;

            $val = $fields[$col];

            // Normalización a mayúsculas para campos textuales (según tu criterio)
            if (in_array($col, ['cac_license_number','company_name','address','address_2','city','state_code'], true)) {
                $val = strtoupper(trim((string)$val));
            }

            $set[] = "{$col} = :{$col}";
            $params[":{$col}"] = $val;
        }

        if (empty($set)) {
            // Nada que actualizar; lo tratamos como éxito “no-op”
            return true;
        }

        $sql = "UPDATE contractors
                   SET " . implode(', ', $set) . ",
                       updated_at = NOW()
                 WHERE contractor_id = :id";

        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return ($st->rowCount() >= 0);
    }

    /** Alias usado por el flujo de Aprobaciones: aprueba → ACTIVE. */
    /**
     * Aprueba contractor:
     * - status = ACTIVE
     * - approved_by/approved_at
     * - limpia rejected_by/rejected_at/rejection_reason
     */
    public function activate_contractor(int $contractor_id, ?string $admin_user_id = null): void
    {
        if ($admin_user_id === null) {
            $admin_user_id = (string)($_SESSION['user']['user_id'] ?? '');
        }

        $sql = "UPDATE contractors
                   SET status = 'ACTIVE',
                       approved_by = :admin,
                       approved_at = NOW(),
                       rejected_by = NULL,
                       rejected_at = NULL,
                       rejection_reason = NULL,
                       updated_at = NOW()
                 WHERE contractor_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':admin' => $admin_user_id !== '' ? $admin_user_id : null,
            ':id'    => $contractor_id,
        ]);
    }

    /**
     * Rechaza contractor:
     * - status = REJECTED
     * - rejected_by/rejected_at/rejection_reason
     * - limpia approved_by/approved_at
     */
    public function reject_contractor(int $contractor_id, ?string $reason = null, ?string $admin_user_id = null): void
    {
        if ($admin_user_id === null) {
            $admin_user_id = (string)($_SESSION['user']['user_id'] ?? '');
        }

        $sql = "UPDATE contractors
                   SET status = 'REJECTED',
                       rejected_by = :admin,
                       rejected_at = NOW(),
                       rejection_reason = :reason,
                       approved_by = NULL,
                       approved_at = NULL,
                       updated_at = NOW()
                 WHERE contractor_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':admin'  => $admin_user_id !== '' ? $admin_user_id : null,
            ':reason' => $reason !== null ? (string)$reason : null,
            ':id'     => $contractor_id,
        ]);
    }

}
