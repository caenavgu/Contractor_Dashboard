<?php
// app/Presenters/approvals_presenter.php
// -------------------------------------------------------------
// Presenter para la pantalla de Aprobaciones.
// GET: lista pendings
// POST: procesa acciones (approve/reject/merge)
// -------------------------------------------------------------
declare(strict_types=1);

class ApprovalsPresenter
{
    public function __construct(
        private PDO $pdo,
        private ApprovalService $service,
        private ContractorStagingRepository $staging_repo
    ) {}

    public function handle_get(): array
    {
        // Usuarios con email verificado y no activos ni rechazados
        $sql = "SELECT user_id, email, email_verified_at,
                (SELECT first_name FROM user_details ud WHERE ud.user_id = u.user_id LIMIT 1) AS first_name,
                (SELECT last_name FROM user_details ud WHERE ud.user_id = u.user_id LIMIT 1) AS last_name
                FROM users u
                WHERE email_verified_at IS NOT NULL AND is_active = 0 AND rejected_at IS NULL
                ORDER BY created_at DESC";
        $st = $this->pdo->query($sql);
        $pending_users = $st->fetchAll(PDO::FETCH_ASSOC);

        $pending_stagings = $this->staging_repo->find_pending();

        return [
            'pending_users' => $pending_users,
            'pending_stagings' => $pending_stagings,
            'message' => $_GET['msg'] ?? '',
            'error' => $_GET['err'] ?? '',
        ];
    }

    public function handle_post(): array
    {
        $action = $_POST['action'] ?? '';
        $admin = current_user_from_session();
        $admin_id = (string)($admin['user_id'] ?? '');

        try {
            switch ($action) {
                case 'approve_user':
                    $user_id = (string)($_POST['user_id'] ?? '');
                    if ($user_id === '') throw new InvalidArgumentException('Missing user_id');
                    $this->service->approve_user($admin_id, $user_id);
                    $msg = 'User approved.';
                    break;

                case 'reject_user':
                    $user_id = (string)($_POST['user_id'] ?? '');
                    $reason = trim((string)($_POST['reason'] ?? ''));
                    if ($user_id === '') throw new InvalidArgumentException('Missing user_id');
                    if ($reason === '') $reason = 'Your data could not be verified. Please contact Technical Support.';
                    $this->service->reject_user($admin_id, $user_id, $reason);
                    $msg = 'User rejected.';
                    break;

                case 'merge_staging':
                    $staging_id = (int)($_POST['staging_id'] ?? 0);
                    $apply_fields = $_POST['apply_fields'] ?? [];
                    if ($staging_id <= 0) throw new InvalidArgumentException('Missing staging_id');
                    $this->service->merge_staging($admin_id, $staging_id, $apply_fields);
                    $msg = 'Staging merged.';
                    break;

                default:
                    throw new InvalidArgumentException('Unknown action');
            }

            // Redirige con mensaje
            header('Location: ' . route_url('/approvals') . '?msg=' . urlencode($msg));
            exit;
        } catch (Throwable $e) {
            $err = $e->getMessage();
            header('Location: ' . route_url('/approvals') . '?err=' . urlencode($err));
            exit;
        }
    }
}
