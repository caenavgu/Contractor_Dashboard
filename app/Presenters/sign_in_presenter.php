<?php
// app/Presenters/sign_in_presenter.php
// -------------------------------------------------------------
// Presenter de Sign In: orquesta validaciones, rate-limit y viewmodel
// -------------------------------------------------------------
declare(strict_types=1);

class SignInPresenter
{
    public function __construct(private AuthService $auth_service) {}

    public function handle_get(): array
    {
        ensure_csrf_token();
        return [
            'values' => ['email' => '', 'remember_me' => false],
            'field_errors' => ['email' => '', 'password' => ''],
            'general_error' => ''
        ];
    }

    public function handle_post(PDO $pdo): array
    {
        $email       = trim((string)($_POST['email'] ?? ''));
        $password    = (string)($_POST['password'] ?? '');
        $remember_me = isset($_POST['remember_me']);
        $csrf_token  = $_POST['_csrf'] ?? '';

        $view = [
            'values' => ['email' => $email, 'remember_me' => $remember_me],
            'field_errors' => ['email' => '', 'password' => ''],
            'general_error' => ''
        ];

        // CSRF
        if (!validate_csrf_token($csrf_token)) {
            $view['general_error'] = 'Unexpected error, please retry.';
            return $view;
        }

        // Validaciones mínimas
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $view['field_errors']['email'] = 'Please enter a valid email address.';
            return $view;
        }
        if (strlen($password) < 8 || strlen($password) > 64) {
            $view['field_errors']['password'] = 'Invalid credentials.';
            return $view;
        }

        // Rate limit (por IP): 5 intentos / 15min → bloqueo 10min
        $ip = get_client_ip();
        [$allowed, $remaining] = rate_limit_check_and_touch('signin_ip', $ip, 5, 15, 10);
        if (!$allowed) {
            $view['general_error'] = 'Too many failed attempts. Please try again later.';
            return $view;
        }

        // Autenticar
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $result = $this->auth_service->authenticate($email, $password, $ip, $ua);

        if (!$result['ok']) {
            // Nota: ya se registró audit en login_failed dentro del service cuando aplica.
            $view['general_error'] = $result['error'] ?? 'Invalid credentials.';
            return $view;
        }

        // Éxito: resetear rate limit e iniciar sesión
        rate_limit_reset('signin_ip', $ip);
        $this->auth_service->create_session($result['user'], $remember_me);

        // Redirección (el controller/index se encargará)
        $view['_redirect'] = '/dashboard';
        return $view;
    }
}
