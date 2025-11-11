<?php
// app/Services/ApprovalService.php
// -------------------------------------------------------------
// Aprueba / Rechaza USUARIOS y CONTRACTORS.
// - Corrige llamadas a AuditLogRepository::log(ip como string, meta como array)
// - Envía emails usando includes/mailer.php
// -------------------------------------------------------------
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/mailer.php';

class ApprovalService
{
    public function __construct(
        private PDO $pdo,
        private UserRepository $userRepo,
        private ContractorRepository $contractorRepo,
        private ContractorStagingRepository $stagingRepo,
        private AuditLogRepository $auditRepo
    ) {}

    /* ------------------- Helpers ------------------- */

    private function ip(): string
    {
        // Fallback local si no tienes client_ip()
        foreach (['HTTP_X_FORWARDED_FOR','HTTP_CLIENT_IP','REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = (string)$_SERVER[$k];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        return 'unknown';
    }

    private function findUserWithDetails(string $user_id): ?array
    {
        $sql = "SELECT u.user_id, u.email,
                       COALESCE(TRIM(CONCAT(ud.first_name,' ',ud.last_name)), '') AS full_name
                  FROM users u
             LEFT JOIN user_details ud ON ud.user_id = u.user_id
                 WHERE u.user_id = :id
                 LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':id' => $user_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /* =========================================================
       USUARIOS: Aprobar / Rechazar
       ========================================================= */

    public function approve_user(string $user_id, string $admin_user_id): array
    {
        try {
            // Actualiza estado + auditoría de usuario
            $sql = "UPDATE users
                       SET status = 'ACTIVE',
                           approved_by = :admin,
                           approved_at = NOW(),
                           rejected_by = NULL,
                           rejected_at = NULL,
                           rejection_reason = NULL,
                           updated_at = NOW()
                     WHERE user_id = :id";
            $st = $this->pdo->prepare($sql);
            $st->execute([
                ':admin' => $admin_user_id,
                ':id'    => $user_id,
            ]);

            // Audit log (firma CORRECTA: actor, action, ip, meta)
            $this->auditRepo->log(
                (string)$admin_user_id,
                'user_approved',
                $this->ip(),
                ['user_id' => (string)$user_id]
            );

            // Email al usuario
            $u = $this->findUserWithDetails($user_id);
            if ($u) {
                $email = (string)$u['email'];
                $name  = trim((string)$u['full_name']) ?: $email;
                try {
                    send_approved_email($email, $name);
                } catch (\Throwable $e) {
                    app_log('approve_user: mail error -> ' . $e->getMessage());
                }
            }

            return ['ok'=>true, 'message'=>'User approved'];
        } catch (\Throwable $e) {
            app_log('approve_user error: ' . $e->getMessage());
            return ['ok'=>false, 'message'=>'Could not approve user'];
        }
    }

    public function reject_user(string $user_id, string $admin_user_id, ?string $reason = null): array
    {
        try {
            $sql = "UPDATE users
                       SET status = 'REJECTED',
                           rejected_by = :admin,
                           rejected_at = NOW(),
                           rejection_reason = :reason,
                           approved_by = NULL,
                           approved_at = NULL,
                           updated_at = NOW()
                     WHERE user_id = :id";
            $st = $this->pdo->prepare($sql);
            $st->execute([
                ':admin'  => $admin_user_id,
                ':reason' => $reason !== null ? (string)$reason : null,
                ':id'     => $user_id,
            ]);

            $this->auditRepo->log(
                (string)$admin_user_id,
                'user_rejected',
                $this->ip(),
                ['user_id' => (string)$user_id, 'reason' => (string)$reason]
            );

            // Email al usuario
            $u = $this->findUserWithDetails($user_id);
            if ($u) {
                $email = (string)$u['email'];
                $name  = trim((string)$u['full_name']) ?: $email;
                try {
                    send_rejected_email($email, $name, (string)$reason);
                } catch (\Throwable $e) {
                    app_log('reject_user: mail error -> ' . $e->getMessage());
                }
            }

            return ['ok'=>true, 'message'=>'User rejected'];
        } catch (\Throwable $e) {
            app_log('reject_user error: ' . $e->getMessage());
            return ['ok'=>false, 'message'=>'Could not reject user'];
        }
    }

    /* =========================================================
       CONTRACTORS (ya los tienes funcionando, dejo firmas típicas)
       ========================================================= */

    public function approve_contractor(int $contractor_id, string $admin_user_id): array
    {
        try {
            $this->contractorRepo->activate_contractor($contractor_id, $admin_user_id);

            $this->auditRepo->log(
                (string)$admin_user_id,
                'contractor_approved',
                $this->ip(),
                ['contractor_id' => (int)$contractor_id]
            );

            return ['ok'=>true, 'message'=>'Contractor approved'];
        } catch (\Throwable $e) {
            app_log('approve_contractor error: ' . $e->getMessage());
            return ['ok'=>false, 'message'=>'Could not approve contractor'];
        }
    }

    public function reject_contractor(int $contractor_id, string $admin_user_id, ?string $reason = null): array
    {
        try {
            $this->contractorRepo->reject_contractor($contractor_id, $reason, $admin_user_id);

            $this->auditRepo->log(
                (string)$admin_user_id,
                'contractor_rejected',
                $this->ip(),
                ['contractor_id' => (int)$contractor_id, 'reason' => (string)$reason]
            );

            return ['ok'=>true, 'message'=>'Contractor rejected'];
        } catch (\Throwable $e) {
            app_log('reject_contractor error: ' . $e->getMessage());
            return ['ok'=>false, 'message'=>'Could not reject contractor'];
        }
    }

    /* =========================================================
       Hook del Presenter: procesa POST de /approvals
       - $action: 'approve_user' | 'reject_user' | 'approve_contractor' | 'reject_contractor'
       - $post:   $_POST
       - $admin_user_id: id del admin actual
       ========================================================= */
    public function handle_post(string $action, array $post, string $admin_user_id): array
    {
        switch ($action) {
            case 'approve_user':
                return $this->approve_user((string)($post['user_id'] ?? ''), $admin_user_id);

            case 'reject_user':
                return $this->reject_user(
                    (string)($post['user_id'] ?? ''),
                    $admin_user_id,
                    (string)($post['reason'] ?? '')
                );

            case 'approve_contractor':
                return $this->approve_contractor((int)($post['contractor_id'] ?? 0), $admin_user_id);

            case 'reject_contractor':
                return $this->reject_contractor(
                    (int)($post['contractor_id'] ?? 0),
                    $admin_user_id,
                    (string)($post['reason'] ?? '')
                );
        }

        return ['ok'=>false, 'message'=>'Unknown action'];
    }

    /* (Opcional) Datos para GET de approvals */
    public function handle_get(): array
    {
        // Aquí podrías armar listas de pendientes para la vista.
        // Te dejo el esqueleto por si lo usas.
        return [
            'pending_users'       => [], // llénalo si tu vista lo requiere
            'pending_contractors' => [], // "
        ];
    }
}
