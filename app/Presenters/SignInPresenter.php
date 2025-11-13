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
        private AuthService $auth_service
    ) {}

    private function view_path(): string
    {
        return BASE_PATH . '/public/views/sign-in.php';
    }

    public function handle_get(): void
    {
        // Asegura token CSRF
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        ensure_csrf_token();

        $view_file = $this->view_path();
        if (!is_file($view_file)) {
            http_response_code(500);
            echo '<h1>500 Internal Server Error</h1><p>View not found: ' . htmlspecialchars($view_file) . '</p>';
            exit;
        }

        // Variables opcionales para la vista (no rompemos tu HTML/CSS)
        $view_error = null;
        $view_field = null;
        $old_email  = '';

        require $view_file;
    }

    public function handle_post(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        ensure_csrf_token();

        // Validar CSRF
        $csrf = (string)($_POST['_csrf'] ?? '');
        if (!validate_csrf_token($csrf)) {
            http_response_code(400);
            echo '<h1>400 Bad Request</h1><p>Invalid CSRF token.</p>';
            return;
        }

        $email       = trim((string)($_POST['email'] ?? ''));
        $password    = (string)($_POST['password'] ?? '');
        $remember_me = !empty($_POST['remember_me']); // ← checkbox opcional

         app_log('SIGNIN presenter: attempt_login for '.$email);

        // Autenticación
        $result = $this->auth_service->attempt_login($email, $password);

        if (empty($result['ok'])) {
            $view_error = (string)($result['error'] ?? 'Invalid credentials.');
            $view_field = $result['field'] ?? null;
            $old_email  = $email;

            $view_file = $this->view_path();
            require $view_file;
            return;
        }

        // Login OK → sesión mínima y regenerar ID
        $u = $result['user'];
        $_SESSION['user'] = [
            'user_id'   => (string)$u['user_id'],
            'email'     => (string)$u['email'],
            'user_type' => strtoupper(trim((string)$u['user_type'])),
        ];
        session_regenerate_id(true);

        // 🔴 CLAVE: emitir sesión persistente -> aquí se dispara el log sign_in
        try {
            // $this->auth_service->issue_session($_SESSION['user']['user_id'], $remember);
             $this->auth_service->issue_session((string)$u['user_id'], $remember_me);
            app_log('SIGNIN presenter: issue_session ok (uid='.$_SESSION['user']['user_id'].')');
        } catch (\Throwable $e) {
            app_log('SIGNIN presenter: issue_session EXCEPTION -> '.$e->getMessage());
            // No bloqueamos el login por un fallo de sesión persistente/audit
        }

        // Crear sesión PERSISTENTE en BD + COOKIE
        // $this->auth_service->issue_session((int)$u['user_id'], $remember_me);
        // $this->auth_service->issue_session((string)$u['user_id'], $remember_me);

        // Redirección por rol
        if ($_SESSION['user']['user_type'] === 'ADM' || $_SESSION['user']['user_type'] === 'ADMIN') {
            redirect_to('/approvals');
        } else {
            redirect_to('/dashboard');
        }
    }
}
