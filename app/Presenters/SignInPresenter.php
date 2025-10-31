<?php
// app/Presenters/SignInPresenter.php
// -------------------------------------------------------------
// Presenter de Sign In
// - GET: muestra formulario
// - POST: valida credenciales, setea sesión y redirige
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
        ensure_csrf_token();

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
        ensure_csrf_token();
        if (!validate_csrf_token((string)($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo '<h1>400 Bad Request</h1><p>Invalid CSRF token.</p>';
            return;
        }

        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $result = $this->auth_service->attempt_login($email, $password);

        if (!$result['ok']) {
            $view_error = (string)($result['error'] ?? 'Invalid credentials.');
            $view_field = $result['field'] ?? null;
            $view_file  = $this->view_path();
            require $view_file;
            return;
        }

        $u = $result['user']; // ['user_id','email','user_type']
        $_SESSION['user'] = [
            'user_id'   => (string)$u['user_id'],
            'email'     => (string)$u['email'],
            'user_type' => strtoupper(trim((string)$u['user_type'])), // 'ADM','TEC','SOP','CON'
        ];
        session_regenerate_id(true);

        if ($_SESSION['user']['user_type'] === 'ADM') {
            redirect_to('/approvals');
        } else {
            redirect_to('/dashboard');
        }
    }
}
