<?php
// app/Services/approval_service.php
// -------------------------------------------------------------
// Servicio de aprobaciones (admin):
// - Aprobar usuario
// - Rechazar usuario (con motivo)
// - Merge de contractor staging
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

    /**
     * Aprueba al usuario (is_active = 1), registra auditoría y envía email.
     */
    public function approve_user(string $admin_user_id, string $user_id): void
    {
        // Activar
        $this->user_repo->activate_user($user_id, $admin_user_id);

        // Auditoría
        $this->audit_repo->add($admin_user_id, 'user', $user_id, 'user_approved', []);

        // Email al usuario
        $st = $this->pdo->prepare("SELECT email, (SELECT first_name FROM user_details ud WHERE ud.user_id = u.user_id LIMIT 1) AS first_name FROM users u WHERE user_id = :id LIMIT 1");
        $st->execute([':id' => $user_id]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $to = $row['email'];
            $name = $row['first_name'] ?? '';
            $subject = 'Your account has been approved';
            $body = "<p>Dear " . htmlspecialchars($name) . ",</p>
                     <p>Your account has been approved. You can now sign in and use the app.</p>";
            send_mail($to, $subject, $body);
            $this->audit_repo->add($admin_user_id, 'user', $user_id, 'email_user', ['type'=>'approved_notice']);
        }
    }

    /**
     * Rechaza usuario (is_active queda 0, set rejected_at + reason), auditoría y email.
     */
    public function reject_user(string $admin_user_id, string $user_id, string $reason): void
    {
        $sql = "UPDATE users SET rejected_at = NOW(), rejection_reason = :reason WHERE user_id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute([':reason' => $reason, ':id' => $user_id]);

        $this->audit_repo->add($admin_user_id, 'user', $user_id, 'user_rejected', ['reason' => $reason]);

        $st = $this->pdo->prepare("SELECT email, (SELECT first_name FROM user_details ud WHERE ud.user_id = u.user_id LIMIT 1) AS first_name FROM users u WHERE user_id = :id LIMIT 1");
        $st->execute([':id' => $user_id]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $to = $row['email'];
            $name = $row['first_name'] ?? '';
            $subject = 'Your account could not be approved';
            $body = "<p>Dear " . htmlspecialchars($name) . ",</p>
                     <p>We were unable to approve your profile at this time. Reason:</p>
                     <blockquote>" . nl2br(htmlspecialchars($reason)) . "</blockquote>
                     <p>Please contact Technical Support if you need assistance.</p>";
            send_mail($to, $subject, $body);
            $this->audit_repo->add($admin_user_id, 'user', $user_id, 'email_user', ['type'=>'rejected_notice']);
        }
    }

    /**
     * Merge de contractor staging contra contractor existente aplicando campos seleccionados.
     * $apply_fields son nombres de columnas "input_*" que mapearemos.
     */
    public function merge_staging(string $admin_user_id, int $staging_id, array $apply_fields): void
    {
        $staging = $this->staging_repo->find_by_id($staging_id);
        if (!$staging) {
            throw new RuntimeException('Staging not found');
        }
        if (empty($staging['existing_contractor_id'])) {
            throw new RuntimeException('No existing contractor linked to this staging');
        }

        $map = [
            'input_company_name'   => 'company_name',
            'input_address'        => 'address',
            'input_address_2'      => 'address_2',
            'input_city'           => 'city',
            'input_state_code'     => 'state_code',
            'input_zip_code'       => 'zip_code',
            'input_company_phone'  => 'company_phone',
            'input_company_email'  => 'company_email',
            'input_company_website'=> 'company_website',
        ];
        $to_apply = [];
        foreach ($apply_fields as $f) {
            if (isset($map[$f])) {
                $to_apply[$map[$f]] = $staging[$f] ?? null;
            }
        }

        $contractor_id = (int)$staging['existing_contractor_id'];
        $this->contractor_repo->update_partial($contractor_id, $to_apply);
        $this->staging_repo->mark_merged($staging_id, (int)$admin_user_id); // si admin_user_id no es numérico, usa 0 o null
        $this->audit_repo->add($admin_user_id, 'contractor', (string)$contractor_id, 'contractor_merge_performed', [
            'staging_id' => $staging_id,
            'applied_fields' => array_keys($to_apply)
        ]);
    }
}
