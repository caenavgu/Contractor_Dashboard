<?php
// app/Repositories/contractor_repository.php
// -------------------------------------------------------------
// Repositorio para contractors
// -------------------------------------------------------------
declare(strict_types=1);

class ContractorRepository
{
    public function __construct(private PDO $pdo) {}

    public function find_by_cac(string $cac): ?array
    {
        $sql = "SELECT * FROM contractors WHERE cac_license_number = :cac LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':cac' => $cac]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO contractors (company_name, cac_license_number, address, address_2, city, state_code, zip_code, company_phone, company_email, company_website, is_active, created_at)
                VALUES (:company_name, :cac, :address, :address_2, :city, :state_code, :zip_code, :phone, :email, :website, 0, NOW())";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':company_name' => $data['company_name'] ?? null,
            ':cac' => $data['cac_license_number'],
            ':address' => $data['address'] ?? null,
            ':address_2' => $data['address_2'] ?? null,
            ':city' => $data['city'] ?? null,
            ':state_code' => $data['state_code'] ?? null,
            ':zip_code' => $data['zip_code'] ?? null,
            ':phone' => $data['company_phone'] ?? null,
            ':email' => $data['company_email'] ?? null,
            ':website' => $data['company_website'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update_partial(int $contractor_id, array $fields): void
    {
        $allowed = ['company_name','address','address_2','city','state_code','zip_code','company_phone','company_email','company_website'];
        $set = []; $params = [':id'=>$contractor_id];
        foreach ($fields as $k=>$v) {
            if (!in_array($k, $allowed, true)) continue;
            $set[] = "`$k` = :$k";
            $params[":$k"] = $v;
        }
        if (empty($set)) return;
        $sql = "UPDATE contractors SET " . implode(',', $set) . ", updated_at = NOW() WHERE contractor_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
    }
}
