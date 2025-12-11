<?php
// app/Presenters/ProfilePresenter.php
// -------------------------------------------------------------
// Muestra la página de perfil del usuario.
// Requiere sesión iniciada.
// -------------------------------------------------------------
declare(strict_types=1);

class ProfilePresenter
{
    public function __construct(
        private ProfileService $profile_service
    ) {}

    private function view_path(): string
    {
        return BASE_PATH . '/public/views/profile.php';
    }

    public function handle_get(): void
    {
        $user_id = require_signed_in(); // asegura $_SESSION['user']

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $session_user = $_SESSION['user'] ?? [];

        $profile_data = $this->profile_service->get_profile_data($session_user);

        /** @var array<string,mixed> $session_user */
        $session_user = $profile_data['session_user'];
        /** @var array<string,mixed>|null $user_details */
        $user_details = $profile_data['user_details'];
        /** @var array<string,mixed>|null $contractor */
        $contractor   = $profile_data['contractor'];
        /** @var array<string,mixed> $verification */
        $verification = $profile_data['verification'];

        $view_file = $this->view_path();
        if (!is_file($view_file)) {
            http_response_code(500);
            echo '<h1>500 Internal Server Error</h1><p>View not found: ' . htmlspecialchars($view_file) . '</p>';
            exit;
        }

        require $view_file;
    }
}
