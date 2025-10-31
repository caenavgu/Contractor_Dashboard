<?php
// app/Services/ApprovalService.php
// -------------------------------------------------------------
// Reglas de negocio para aprobaciones y merges.
// - Aprobar/Rechazar usuarios
// - Aprobar/Rechazar contractors
// - Merge de contractor (staging -> existente, sin tocar CAC)
// - Si contractor queda ACTIVE, el usuario asociado pasa a user_type='CON'
// -------------------------------------------------------------
declare(strict_types=1);

class ApprovalService
{
    public function __construct(
        private PDO $pdo,
        private UserRepository $user_repo,
        private ContractorRepository $contractor_repo,
        private ContractorStagingRepository $staging_repo,
        private AuditLogRepository $audit_repo
    ) {}

    /* ==================== USERS ==================== */

    public function approve_user(string $user_id, string $admin_user_id): void
    {
        $this->pdo->beginTransaction();
        try {
            $user = $this->user_repo->find_by_cac($user_id);
            if (!$user) {
                throw new RuntimeException('User not found');
            }

            $this->user_repo->approve_user($user_id, $admin_user_id);

            // Si su contractor ya está ACTIVE => forzar user_type='CON'
            $contractor_id = $user['contractor_id'] ?? null;
            if ($contractor_id) {
                $contractor = $this->contractor_repo->find_by_cac((int)$contractor_id);
                if ($contractor && ($contractor['status'] ?? '') === 'ACTIVE') {
                    $this->user_repo->update_user_type($user_id, 'CON');
                }
            }

            // Audit
            $this->audit_repo->log('user_approved', $user_id, [
                'approved_by' => $admin_user_id,
                'email'       => (string)$user['email'],
            ]);

            // Email al usuario (silencioso si falla en local)
            $to = (string)$user['email'];
            $subject = 'Your account has been approved';
            $body = "Hello,\n\nYour account has been approved. You can now sign in.\n\nRegards,\nSupport";
            try { send_mail($to, $subject, $body); } catch (Throwable $e) { /* noop */ }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function reject_user(string $user_id, string $reason, string $admin_user_id): void
    {
        $this->pdo->beginTransaction();
        try {
            $user = $this->user_repo->find_by_id($user_id);
            if (!$user) {
                throw new RuntimeException('User not found');
            }

            $this->user_repo->reject_user($user_id, $reason, $admin_user_id);

            // Audit
            $this->audit_repo->log('user_rejected', $user_id, [
                'rejected_by' => $admin_user_id,
                'reason'      => $reason,
            ]);

            // Email al usuario
            $to = (string)$user['email'];
            $subject = 'Your account was rejected';
            $body = "Hello,\n\nWe could not verify your information. Please contact Technical Support.\nReason: {$reason}\n\nRegards,\nSupport";
            try { send_mail($to, $subject, $body); } catch (Throwable $e) { /* noop */ }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /* ==================== CONTRACTORS ==================== */

    public function approve_contractor(int $contractor_id, string $admin_user_id): void
    {
        $this->pdo->beginTransaction();
        try {
            $contractor = $this->contractor_repo->find_by_id($contractor_id);
            if (!$contractor) {
                throw new RuntimeException('Contractor not found');
            }

            $this->contractor_repo->activate_contractor($contractor_id, $admin_user_id);

            // Todos los usuarios asociados a este contractor cuya cuenta esté ACTIVE o PENDING → user_type='CON'
            $this->user_repo->promote_users_to_contractor_by_contractor_id($contractor_id);

            // Audit
            $this->audit_repo->log('contractor_approved', (string)$contractor_id, [
                'approved_by' => $admin_user_id,
                'cac'         => (string)$contractor['cac_license_number'],
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function reject_contractor(int $contractor_id, string $reason, string $admin_user_id): void
    {
        $this->pdo->beginTransaction();
        try {
            $contractor = $this->contractor_repo->find_by_id($contractor_id);
            if (!$contractor) {
                throw new RuntimeException('Contractor not found');
            }

            $this->contractor_repo->reject_contractor($contractor_id, $reason, $admin_user_id);

            // Audit
            $this->audit_repo->log('contractor_rejected', (string)$contractor_id, [
                'rejected_by' => $admin_user_id,
                'reason'      => $reason,
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Merge: aplica datos del staging al contractor existente (no cambia CAC).
     * - staging_id referencia a fila en tabla temporal (contractor_staging).
     * - contractor_id destino.
     */
    public function merge_contractor(int $staging_id, int $contractor_id, string $admin_user_id): void
    {
        $this->pdo->beginTransaction();
        try {
            $staging = $this->staging_repo->find_by_id($staging_id);
            if (!$staging) {
                throw new RuntimeException('Staging not found');
            }

            $contractor = $this->contractor_repo->find_by_id($contractor_id);
            if (!$contractor) {
                throw new RuntimeException('Contractor not found');
            }

            // Campos mergeables (NO se toca CAC)
            $fields = [
                'company_name','company_phone','company_email','company_website',
                'address','address_2','city','state_code','zip_code',
            ];
            $changes = [];
            foreach ($fields as $f) {
                $new_val = $staging[$f] ?? null;
                if ($new_val !== null && $new_val !== $contractor[$f]) {
                    $changes[$f] = $new_val;
                }
            }

            if ($changes) {
                $this->contractor_repo->update_fields($contractor_id, $changes, $admin_user_id);
            }

            // Marcar staging como resuelto (eliminamos o marcamos)
            $this->staging_repo->mark_resolved($staging_id, 'MERGED', $admin_user_id);

            // Si contractor queda ACTIVE => promover usuarios asociados a user_type='CON'
            $contractor_after = $this->contractor_repo->find_by_id($contractor_id);
            if ($contractor_after && ($contractor_after['status'] ?? '') === 'ACTIVE') {
                $this->user_repo->promote_users_to_contractor_by_contractor_id($contractor_id);
            }

            // Audit
            $this->audit_repo->log('contractor_merged', (string)$contractor_id, [
                'staging_id'  => $staging_id,
                'approved_by' => $admin_user_id,
                'changed'     => array_keys($changes),
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Keep: se descarta el staging y se mantiene el contractor actual tal cual.
     */
    public function keep_contractor(int $staging_id, int $contractor_id, string $admin_user_id): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->staging_repo->mark_resolved($staging_id, 'KEPT', $admin_user_id);

            // Audit
            $this->audit_repo->log('contractor_merged', (string)$contractor_id, [
                'staging_id'  => $staging_id,
                'approved_by' => $admin_user_id,
                'changed'     => [],
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
