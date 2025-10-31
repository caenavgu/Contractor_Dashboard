<?php
// app/Repositories/AuditLogRepository.php
// -------------------------------------------------------------
// Repositorio de auditoría con inserción tolerante al esquema.
// Implementa: log(), list_recent()
// - Detecta columnas presentes en audit_logs y arma INSERT dinámico.
// - meta: se guarda en meta_json (si existe) o metadata/details como texto JSON.
// -------------------------------------------------------------
declare(strict_types=1);

class AuditLogRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Registra un evento en audit_logs.
     *
     * @param string|null $user_id   ID del usuario (puede ser null, ej. login_failed).
     * @param string      $action    Acción estándar (login_success, login_failed, user_created, contractor_approved, user_approved, warranty_created, warranty_updated, pdf_generated, serial_number_added, email_user, email_client, email_admin, etc.)
     * @param string|null $ip        IP origen (opcional).
     * @param array       $meta      Datos extra (se serializan a JSON si hay columna adecuada).
     */
    public function log(?string $user_id, string $action, ?string $ip = null, array $meta = []): void
    {
        $action = trim($action);
        if ($action === '') {
            // Nada que registrar
            return;
        }

        // Detectar columnas disponibles en audit_logs
        $dbName = $this->pdo->query("SELECT DATABASE()")->fetchColumn();
        if (!$dbName) {
            throw new RuntimeException('Cannot determine current database for audit logging.');
        }

        $colsStmt = $this->pdo->prepare("
            SELECT COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'audit_logs'
        ");
        $colsStmt->execute([':db' => $dbName]);
        $cols = array_map(static fn($r) => $r['COLUMN_NAME'], $colsStmt->fetchAll(PDO::FETCH_ASSOC));

        // Helper rápido
        $has = static fn(string $c) => in_array($c, $cols, true);

        $insertCols = [];
        $params     = [];

        // action (obligatorio)
        $insertCols[]    = 'action';
        $params[':action'] = $action;

        // user_id si la tabla lo tiene
        if ($has('user_id') && $user_id !== null) {
            $insertCols[]   = 'user_id';
            $params[':user_id'] = $user_id;
        }

        // ip_address si existe columna
        if ($has('ip_address') && $ip !== null) {
            $insertCols[]   = 'ip_address';
            $params[':ip_address'] = $ip;
        }

        // user_agent si existe columna y lo podemos obtener del entorno
        if ($has('user_agent') && isset($_SERVER['HTTP_USER_AGENT'])) {
            $insertCols[]   = 'user_agent';
            $params[':user_agent'] = (string)$_SERVER['HTTP_USER_AGENT'];
        }

        // meta / detalles: priorizamos JSON si la columna existe
        $metaJson = !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        if ($metaJson === false) {
            $metaJson = null; // fallback si falla la codificación
        }

        if ($metaJson !== null) {
            if ($has('meta_json')) {
                $insertCols[]         = 'meta_json';
                $params[':meta_json'] = $metaJson;
            } elseif ($has('metadata')) {
                $insertCols[]          = 'metadata';
                $params[':metadata']   = $metaJson;
            } elseif ($has('details')) {
                $insertCols[]         = 'details';
                $params[':details']   = $metaJson;
            }
        }

        // created_at si la columna existe y NO tiene default; en muchos esquemas no hace falta
        if ($has('created_at')) {
            // Intentamos no forzar si la columna tiene default CURRENT_TIMESTAMP, pero
            // no es trivial detectarlo aquí. Poner NOW() es seguro en la mayoría de casos.
            $insertCols[]        = 'created_at';
            $params[':created_at'] = date('Y-m-d H:i:s');
        }

        // Si por alguna razón la tabla solo tiene 'action' (muy mínima), igual insertamos.
        $colsSql   = implode(', ', $insertCols);
        $valuesSql = implode(', ', array_keys($params));

        $sql = "INSERT INTO audit_logs ($colsSql) VALUES ($valuesSql)";
        $st  = $this->pdo->prepare($sql);
        $st->execute($params);
    }

    /**
     * Lista los últimos N registros (útil para depurar).
     *
     * @param int $limit
     * @return array<int, array<string,mixed>>
     */
    public function list_recent(int $limit = 50): array
    {
        $limit = max(1, min($limit, 500));
        // Seleccionamos columnas comunes si existen; si no, usamos SELECT *
        $dbName = $this->pdo->query("SELECT DATABASE()")->fetchColumn();
        $colsStmt = $this->pdo->prepare("
            SELECT COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'audit_logs'
        ");
        $colsStmt->execute([':db' => $dbName]);
        $cols = array_map(static fn($r) => $r['COLUMN_NAME'], $colsStmt->fetchAll(PDO::FETCH_ASSOC));
        $has = static fn(string $c) => in_array($c, $cols, true);

        if ($has('created_at')) {
            $sql = "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT {$limit}";
        } elseif ($has('log_id')) {
            $sql = "SELECT * FROM audit_logs ORDER BY log_id DESC LIMIT {$limit}";
        } else {
            $sql = "SELECT * FROM audit_logs LIMIT {$limit}";
        }

        $st = $this->pdo->query($sql);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // Alias opcionales por compatibilidad si en algún punto alguien llamó a create()/add()
    public function create(?string $user_id, string $action, ?string $ip = null, array $meta = []): void
    {
        $this->log($user_id, $action, $ip, $meta);
    }
    public function add(?string $user_id, string $action, ?string $ip = null, array $meta = []): void
    {
        $this->log($user_id, $action, $ip, $meta);
    }
}
