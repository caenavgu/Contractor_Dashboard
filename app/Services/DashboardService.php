<?php
// app/Services/DashboardService.php
// -------------------------------------------------------------
// Lógica de negocio para el dashboard de usuario (listado y búsquedas).
// -------------------------------------------------------------
declare(strict_types=1);

class DashboardService
{
    public function __construct(
        private WarrantyRepository $warranty_repo,
        private int $per_page = 10
    ) {}

    public function getDashboardData(
        string $user_id,
        ?string $search_query,
        int $page
    ): array {
        if ($page < 1) {
            $page = 1;
        }

        $search_query = $search_query !== null ? trim($search_query) : null;
        $warranty_id_filter = $this->parseWarrantyIdFilter($search_query);

        $offset = ($page - 1) * $this->per_page;

        $warranties = $this->warranty_repo->findByUserPaginated(
            $user_id,
            $search_query,
            $warranty_id_filter,
            $this->per_page,
            $offset
        );

        // Agregar warranty_number formateado: E-00000042
        foreach ($warranties as &$w) {
            $w['warranty_number'] = $this->formatWarrantyNumber((int)$w['warranty_id']);
        }
        unset($w);

        $total = $this->warranty_repo->countByUser(
            $user_id,
            $search_query,
            $warranty_id_filter
        );

        $total_pages = max(1, (int)ceil($total / $this->per_page));

        return [
            'warranties' => $warranties,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $this->per_page,
                'total'        => $total,
                'total_pages'  => $total_pages,
            ],
            'filters' => [
                'search_query' => $search_query,
            ],
        ];
    }

    public function searchExternalWarranty(
        string $current_user_id,
        ?string $serial_number,
        ?string $owner_last_name
    ): ?array {
        $serial_number   = $serial_number !== null ? trim($serial_number) : '';
        $owner_last_name = $owner_last_name !== null ? trim($owner_last_name) : '';

        if ($serial_number === '' || $owner_last_name === '') {
            return null;
        }

        $row = $this->warranty_repo->findExternalWarrantyBySerialAndLastName(
            $serial_number,
            $owner_last_name,
            $current_user_id
        );

        if ($row === null) {
            return null;
        }

        $row['warranty_number'] = $this->formatWarrantyNumber((int)$row['warranty_id']);

        return $row;
    }

    /**
     * Si el usuario busca por "E-00000042" o "42", devolvemos 42
     * para filtrar por warranty_id.
     */
    private function parseWarrantyIdFilter(?string $search_query): ?int
    {
        if ($search_query === null) {
            return null;
        }

        $q = trim($search_query);
        if ($q === '') {
            return null;
        }

        // Formato E-00000042
        if (preg_match('/^E-(\d{1,8})$/i', $q, $m)) {
            $num = (int)$m[1];
            return $num > 0 ? $num : null;
        }

        // Solo números -> también lo interpretamos como ID
        if (preg_match('/^\d{1,9}$/', $q, $m)) {
            $num = (int)$m[0];
            return $num > 0 ? $num : null;
        }

        return null;
    }

    private function formatWarrantyNumber(int $warranty_id): string
    {
        return sprintf('E-%08d', $warranty_id);
    }
}
