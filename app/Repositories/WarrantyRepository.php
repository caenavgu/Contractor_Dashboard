<?php
// app/Repositories/WarrantyRepository.php
// -------------------------------------------------------------
// Acceso a la tabla 'warranty_records' para el dashboard de usuario.
// -------------------------------------------------------------
declare(strict_types=1);

class WarrantyRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Lista paginada de garantías de un usuario, con filtro de búsqueda opcional.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findByUserPaginated(
        string $user_id,
        ?string $search_query,
        ?int $warranty_id_filter,
        int $limit,
        int $offset
    ): array {
        $sql = "
            SELECT
                warranty_id,
                owner_first_name,
                owner_last_name,
                owner_email,
                outdoor_serial_number,
                indoor_serial_number,
                installation_city,
                installation_state_code,
                purchased_date,
                status,
                created_at
            FROM warranty_records
            WHERE user_id = :user_id
        ";

        $params = [
            ':user_id' => $user_id,
        ];

        if ($search_query !== null && $search_query !== '') {
            $sql .= "
                AND (
                    outdoor_serial_number LIKE :q
                    OR indoor_serial_number  LIKE :q
                    OR owner_email          LIKE :q
                    OR owner_first_name     LIKE :q
                    OR owner_last_name      LIKE :q
                )
            ";
            $params[':q'] = '%' . $search_query . '%';
        }

        if ($warranty_id_filter !== null) {
            $sql .= " AND warranty_id = :wid";
            $params[':wid'] = $warranty_id_filter;
        }

        $sql .= " ORDER BY created_at DESC, warranty_id DESC
                  LIMIT :limit OFFSET :offset";

        $st = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                continue;
            }
            if ($key === ':wid') {
                $st->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $st->bindValue($key, $value);
            }
        }

        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);

        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countByUser(
        string $user_id,
        ?string $search_query,
        ?int $warranty_id_filter
    ): int {
        $sql = "
            SELECT COUNT(*) AS total
            FROM warranty_records
            WHERE user_id = :user_id
        ";

        $params = [
            ':user_id' => $user_id,
        ];

        if ($search_query !== null && $search_query !== '') {
            $sql .= "
                AND (
                    outdoor_serial_number LIKE :q
                    OR indoor_serial_number  LIKE :q
                    OR owner_email          LIKE :q
                    OR owner_first_name     LIKE :q
                    OR owner_last_name      LIKE :q
                )
            ";
            $params[':q'] = '%' . $search_query . '%';
        }

        if ($warranty_id_filter !== null) {
            $sql .= " AND warranty_id = :wid";
            $params[':wid'] = $warranty_id_filter;
        }

        $st = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            if ($key === ':wid') {
                $st->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $st->bindValue($key, $value);
            }
        }

        $st->execute();
        $total = $st->fetchColumn();

        return $total !== false ? (int)$total : 0;
    }

    /**
     * Busca una garantía que NO pertenezca al usuario actual,
     * por serial + apellido del dueño.
     */
    public function findExternalWarrantyBySerialAndLastName(
        string $serial_number,
        string $owner_last_name,
        string $current_user_id
    ): ?array {
        $sql = "
            SELECT
                warranty_id,
                owner_first_name,
                owner_last_name,
                owner_email,
                outdoor_serial_number,
                indoor_serial_number,
                installation_city,
                installation_state_code,
                purchased_date,
                status,
                user_id,
                created_at
            FROM warranty_records
            WHERE (outdoor_serial_number = :serial OR indoor_serial_number = :serial)
              AND UPPER(owner_last_name) = UPPER(:last_name)
              AND user_id <> :current_user_id
            ORDER BY created_at DESC, warranty_id DESC
            LIMIT 1
        ";

        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':serial'          => $serial_number,
            ':last_name'       => $owner_last_name,
            ':current_user_id' => $current_user_id,
        ]);

        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
