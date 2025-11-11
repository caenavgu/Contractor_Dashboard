<?php
// app/Presenters/ApprovalsPresenter.php
// -------------------------------------------------------------
// Presenter de Aprobaciones (solo admin)
// - GET: lista pendientes (usuarios / contractors / staging)
// - POST: approve_user, reject_user, approve_contractor, reject_contractor,
//         merge_contractor, keep_contractor
// -------------------------------------------------------------
declare(strict_types=1);

class ApprovalsPresenter
{
    public function __construct(
        private PDO $pdo,
        private ApprovalService $approval_service,
        private ContractorStagingRepository $staging_repo,
        private ContractorRepository $contractor_repo
    ) {}

    private function view_path(): string
    {
        return BASE_PATH . '/public/views/approvals.php';
    }

    public function handle_get(): array
    {
        // Usuarios PENDING (verificados)
        $pending_users = (new UserRepository($this->pdo))->list_pending_verified();

        // Contractors PENDING
        $pending_contractors = $this->contractor_repo->list_pending();

        // Staging PENDING (conflictos por CAC existente)
        $pending_staging = $this->staging_repo->list_pending();

        return [
            'pending_users'       => $pending_users,
            'pending_contractors' => $pending_contractors,
            'pending_staging'     => $pending_staging,
        ];
    }

    public function handle_post(string $action, array $post, string $admin_user_id): array
    {
        $out = ['ok' => false, 'message' => ''];

        try {
            switch ($action) {
                case 'approve_user':
                    $this->approval_service->approve_user((string)$post['user_id'], $admin_user_id);
                    $out = ['ok' => true, 'message' => 'User approved'];
                    break;

                case 'reject_user':
                    $reason = (string)($post['reason'] ?? '');
                    $this->approval_service->reject_user((string)$post['user_id'], $admin_user_id, $reason);
                    $out = ['ok' => true, 'message' => 'User rejected'];
                    break;

                case 'approve_contractor':
                    $this->approval_service->approve_contractor((int)$post['contractor_id'], $admin_user_id);
                    $out = ['ok' => true, 'message' => 'Contractor approved'];
                    break;

                case 'reject_contractor':
                    $reason = (string)($post['reason'] ?? '');
                    $this->approval_service->reject_contractor((int)$post['contractor_id'], $admin_user_id, $reason);
                    $out = ['ok' => true, 'message' => 'Contractor rejected'];
                    break;

                case 'merge_contractor':
                    $this->approval_service->merge_contractor((int)$post['staging_id'], (int)$post['contractor_id'], $admin_user_id);
                    $out = ['ok' => true, 'message' => 'Contractor merged'];
                    break;

                case 'keep_contractor':
                    $this->approval_service->keep_contractor((int)$post['staging_id'], (int)$post['contractor_id'], $admin_user_id);
                    $out = ['ok' => true, 'message' => 'Kept existing contractor info'];
                    break;

                default:
                    $out = ['ok' => false, 'message' => 'Unknown action'];
            }
        } catch (Throwable $e) {
            $out = ['ok' => false, 'message' => 'Unexpected error: ' . $e->getMessage()];
        }

        return $out;
    }
}
