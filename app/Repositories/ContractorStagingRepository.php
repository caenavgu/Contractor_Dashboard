<?php
// app/Repositories/ContractorStagingRepository.php
// -------------------------------------------------------------
// Tabla temporal para conflictos de contractors (por CAC ya existente).
// Estructura mínima supuesta:
//  - staging_id (PK)
//  - cac_license_number
//  - company_name, company_phone, company_email, company_website
//  - address, address_2, city, state_code, zip_code
//  - status ('PENDING','RESOLVED')
//  - resolution ('MERGED','KEPT') NULL en pending
//  - created_at, resolved_at
//  - created_by, resolved_by (user_id)
// -------------------------------------------------------------
declare(strict_types=1);

class ContractorStagingRepository
{
    public function __construct(private PDO $pdo) {}

    /** Inserta un staging en 'PENDING' (cuando CAC ya existe). */
    public function create_pending(array $data, string $created_by): int
    {
        $sql = "INSERT INTO contractor_staging
                   (cac_license_number, company_name, company_phone, company_email, company_website,
                    address, address_2, city, state_code, zip_code, status, created_by, created_at)
                VALUES
                   (:cac, :name, :phone, :email, :web,
                    :addr, :addr2, :city, :state, :zip, 'PENDING', :by, NOW())";
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
            ':by'    => $created_by,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Lista pendientes para revisión. */
    public function list_pending(): array
    {
        $sql = "SELECT *
                FROM contractor_staging
                WHERE status = 'PENDING'
                ORDER BY created_at DESC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Marca un staging como resuelto (por merge o descarte). */
    public function resolve(int $staging_id, string $new_status = 'REJECTED'): void
    {
        // new_status típico: 'REJECTED' o 'INACTIVE' (si quieres guardar histórico)
        $sql = "UPDATE contractor_staging
                   SET status = :s, updated_at = NOW()
                 WHERE id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([':s' => $new_status, ':id' => $staging_id]);
    }
}
