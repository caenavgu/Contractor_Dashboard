<?php
// app/Presenters/SignInPresenter.php
// -------------------------------------------------------------
// Presenter de Sign In
// - GET: muestra formulario (con CSRF)
// - POST: valida credenciales vía AuthService, setea sesión y redirige
// -------------------------------------------------------------
declare(strict_types=1);

class SignInPresenter
{
    public function __construct(
        private AuthService $auth_service,
        private UserDetailsRepository $UserDetailsRepository,
        private ContractorRepository $ContractorRepository
    ) {}

    private function view_path(): string
    {
        return BASE_PATH . '/public/views/sign-in.php';
    }

    public function handle_get(): void
    {
        ensure_csrf_token();

        // La vista espera estas variables
        $error_msg = null;
        $invalid_field = null;
        $old = ['email' => ''];

        $view_file = $this->view_path();
        if (!is_file($view_file)) {
            http_response_code(500);
            echo '<h1>500 Internal Server Error</h1><p>View not found: ' . htmlspecialchars($view_file) . '</p>';
            exit;
        }
        require $view_file;
    }

    public function handle_post(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        ensure_csrf_token();

        if (!validate_csrf_token((string)($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo '<h1>400 Bad Request</h1><p>Invalid CSRF token.</p>';
            return;
        }

        $email       = trim((string)($_POST['email'] ?? ''));
        $password    = (string)($_POST['password'] ?? '');
        $remember_me = !empty($_POST['remember_me']); // checkbox típico

        $result = $this->auth_service->attempt_login($email, $password);

        if (!($result['ok'] ?? false)) {
            // variables EXACTAS que tu vista ya usa
            $error_msg     = (string)($result['error'] ?? 'Invalid credentials.');
            $invalid_field = $result['field'] ?? null;
            $old           = ['email' => $email];

            $view_file = $this->view_path();
            require $view_file;
            return;
        }

        // éxito: usuario válido
        /** @var array<string,mixed> $u */
        $u = $result['user']; // ['user_id','email','user_type', 'contractor_id'...]

        // ----------------------------------------
        // Obtener detalles del usuario
        // ----------------------------------------
        $user_details = $this->UserDetailsRepository->find_by_user_id((string)$u['user_id']);
        $full_name = '';
        if ($user_details) {
            $full_name = trim(
                (string)($user_details['first_name'] ?? '') . ' ' .
                (string)($user_details['last_name'] ?? '')
            );
        }

        // ----------------------------------------
        // Obtener nombre de contratista (si aplica)
        // ----------------------------------------
        $company_name = '';
        if (!empty($u['contractor_id'])) {
            $contractor = $this->ContractorRepository->find_by_id((int)$u['contractor_id']);
            if ($contractor) {
                $company_name = (string)$contractor['company_name'];
            }
        }

        // ----------------------------------------
        // Mapear tipo de usuario a texto legible
        // ----------------------------------------
        $type_map = [
            'ADM' => 'Admin',
            'SOP' => 'Support',
            'CON' => 'Contractor',
            'TEC' => 'Technician',
        ];

        $user_type_code  = strtoupper(trim((string)$u['user_type']));
        $user_type_label = $type_map[$user_type_code] ?? 'User';

        // ----------------------------------------
        // Guardar todo en la sesión
        // ----------------------------------------
        $_SESSION['user'] = [
            'user_id'        => (string)$u['user_id'],
            'email'          => (string)$u['email'],
            'user_type'      => $user_type_code,
            'user_type_label'=> $user_type_label,
            'name'           => $full_name,
            'company_name'   => $company_name,
            'contractor_id'  => $u['contractor_id'] ?? null,
        ];

        session_regenerate_id(true);

        // emitir sesión persistente (cookie + audit log)
        try {
            $this->auth_service->issue_session((string)$u['user_id'], $remember_me);
            app_log('SIGNIN presenter: issue_session ok (uid='.(string)$u['user_id'].')');
        } catch (\Throwable $e) {
            app_log('SIGNIN presenter: issue_session EXCEPTION -> '.$e->getMessage());
            // No bloqueamos el login por fallo de sesión persistente/audit
        }

        // Redirección por rol
        if ($_SESSION['user']['user_type'] === 'ADM') {
            redirect_to('/approvals');
        } else {
            redirect_to('/dashboard');
        }
    }
}
