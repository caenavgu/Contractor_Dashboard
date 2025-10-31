<?php
// app/Repositories/ContractorRepository.php
// -------------------------------------------------------------
// Acceso a la tabla contractors (usa columna STATUS, no is_active)
// -------------------------------------------------------------
declare(strict_types=1);

class ContractorRepository
{
    public function __construct(private PDO $pdo) {}

    /** Busca contractor por CAC. Devuelve null si no existe. */
    public function find_by_cac(string $cac_license_number): ?array
    {
        $sql = "SELECT contractor_id, cac_license_number, company_name, company_phone, company_email,
                       company_website, address, address_2, city, state_code, zip_code,
                       status, approved_by, approved_at, rejected_by, rejected_at, rejection_reason,
                       merge_by, merge_at, created_at, updated_at
                FROM contractors
                WHERE cac_license_number = :cac
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':cac' => $cac_license_number]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Crea contractor en estado PENDING. Devuelve contractor_id (int). */
    public function create_pending(array $data): int
    {
        $sql = "INSERT INTO contractors
                   (cac_license_number, company_name, company_phone, company_email, company_website,
                    address, address_2, city, state_code, zip_code, status, created_at, updated_at)
                VALUES
                   (:cac, :name, :phone, :email, :web,
                    :addr, :addr2, :city, :state, :zip, 'PENDING', NOW(), NOW())";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':cac'   => trim((string)$data['cac_license_number']),
            ':name'  => trim((string)$data['company_name']),
            ':phone' => $data['company_phone'] ?? null,
            ':email' => $data['company_email'] ?? null,
            ':web'   => $data['company_website'] ?? null,
            ':addr'  => trim((string)$data['address']),
            ':addr2' => $data['address_2'] ?? null,
            ':city'  => trim((string)$data['city']),
            ':state' => trim((string)$data['state_code']),
            ':zip'   => trim((string)$data['zip_code']),
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

    /** Guarda datos de merge (solo metadata). */
    public function mark_merged(int $contractor_id, string $admin_user_id): void
    {
        $sql = "UPDATE contractors
                   SET merge_by = :by, merge_at = NOW(), updated_at = NOW()
                 WHERE contractor_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([':by' => $admin_user_id, ':id' => $contractor_id]);
    }
}
