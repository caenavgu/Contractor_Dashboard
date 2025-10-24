<?php
// app/Repositories/contractor_staging_repository.php
// -------------------------------------------------------------
// Repositorio para contractor_staging
// -------------------------------------------------------------
declare(strict_types=1);

class ContractorStagingRepository
{
    public function __construct(private PDO $pdo) {}

    public function create(array $input, ?int $existing_contractor_id, ?int $created_by_user_id): int
    {
        $sql = "INSERT INTO contractor_staging
            (existing_contractor_id, input_cac_license_number, input_company_name, input_address, input_address_2, input_city, input_state_code, input_zip_code, input_company_phone, input_company_email, input_company_website, input_raw_json, created_by_user_id, status, created_at)
            VALUES (:existing_id, :cac, :company, :address, :address_2, :city, :state_code, :zip, :phone, :email, :website, :raw, :created_by, 'pending', NOW())";
        $st = $this->pdo->prepare($sql);
        $raw = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $st->execute([
            ':existing_id' => $existing_contractor_id,
            ':cac' => $input['cac_license_number'],
            ':company' => $input['company_name'] ?? null,
            ':address' => $input['address'] ?? null,
            ':address_2' => $input['address_2'] ?? null,
            ':city' => $input['city'] ?? null,
            ':state_code' => $input['state_code'] ?? null,
            ':zip' => $input['zip_code'] ?? null,
            ':phone' => $input['company_phone'] ?? null,
            ':email' => $input['company_email'] ?? null,
            ':website' => $input['company_website'] ?? null,
            ':raw' => $raw,
            ':created_by' => $created_by_user_id,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function find_pending(): array
    {
        $sql = "SELECT * FROM contractor_staging WHERE status = 'pending' ORDER BY created_at DESC";
        $st = $this->pdo->query($sql);
        return $st->fetchAll();
    }

    public function find_by_id(int $id): ?array
    {
        $sql = "SELECT * FROM contractor_staging WHERE staging_id = :id LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function mark_merged(int $id, int $admin_id): void
    {
        $sql = "UPDATE contractor_staging SET status='merged', merged_at = NOW(), merged_by_admin_id = :admin WHERE staging_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([':admin'=>$admin_id, ':id'=>$id]);
    }

    public function mark_discarded(int $id, int $admin_id): void
    {
        $sql = "UPDATE contractor_staging SET status='discarded', updated_at = NOW(), merged_by_admin_id = :admin WHERE staging_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([':admin'=>$admin_id, ':id'=>$id]);
    }
}
