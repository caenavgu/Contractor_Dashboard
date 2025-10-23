<?php
// app/Repositories/audit_log_repository.php
// -------------------------------------------------------------
// Registro de auditoría (robusto ante fallos de json_encode)
// -------------------------------------------------------------
declare(strict_types=1);

class AuditLogRepository
{
    public function __construct(private PDO $pdo) {}

    public function add(?string $actor_user_id, string $entity_type, ?int $entity_id, string $action, array $data): void
    {
        // Normalizar y acotar datos para evitar errores de codificación/tamaño
        $safe = $this->sanitize_data($data);

        // Intentar JSON; si falla, usar "{}"
        $json = json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '{}';
        }

        // INSERT sin CAST; MySQL castea string->JSON (si la columna es JSON)
        $sql = "INSERT INTO audit_logs (actor_user_id, entity_type, entity_id, action, data_json, created_at)
                VALUES (:actor, :etype, :eid, :action, :json, NOW())";

        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':actor'  => $actor_user_id,
            ':etype'  => substr($entity_type, 0, 50),
            ':eid'    => $entity_id ?? 0,
            ':action' => substr($action, 0, 50),
            ':json'   => $json,
        ]);
    }

    private function sanitize_data(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = is_string($k) ? $k : (string)$k;
            if (is_string($v)) {
                // Fuerza UTF-8 y recorta a 1000 chars para no explotar el tamaño
                $v = mb_convert_encoding($v, 'UTF-8', 'UTF-8');
                $v = mb_substr($v, 0, 1000);
                $out[$key] = $v;
            } elseif (is_array($v)) {
                $out[$key] = $this->sanitize_data($v);
            } else {
                $out[$key] = $v;
            }
        }
        return $out;
    }
}
