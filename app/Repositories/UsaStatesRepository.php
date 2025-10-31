<?php
// app/Repositories/UsaStatesRepository.php
// -------------------------------------------------------------
// Acceso a la tabla usa_states
// Estructura esperada:
//   - state_code CHAR(2) PK/UNIQUE
//   - state_name VARCHAR(...)
// -------------------------------------------------------------
declare(strict_types=1);

class UsaStatesRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Devuelve todos los estados ordenados por nombre.
     * Formato: [ ['state_code'=>'FL','state_name'=>'Florida'], ... ]
     */
    public function list_all(): array
    {
        $sql = "SELECT state_code, state_name
                  FROM usa_states
              ORDER BY state_name ASC";
        $st = $this->pdo->query($sql);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Alias por compatibilidad con posibles llamadas camelCase */
    public function listAll(): array
    {
        return $this->list_all();
    }

    /**
     * Obtiene un estado por su código (ej: 'FL'). Devuelve null si no existe.
     */
    public function get_by_code(string $state_code): ?array
    {
        $sql = "SELECT state_code, state_name
                  FROM usa_states
                 WHERE state_code = :code
                 LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':code' => strtoupper(trim($state_code))]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
