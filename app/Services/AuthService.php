<?php
// app/Services/auth_service.php
// -------------------------------------------------------------
// Autenticación: valida credenciales y devuelve el usuario.
// Deja la responsabilidad de setear sesión al Presenter.
// -------------------------------------------------------------
declare(strict_types=1);

class AuthService
{
    public function __construct(
        private UserRepository $user_repo,
        private SessionRepository $session_repo,
        private AuditLogRepository $audit_repo
    ) {}

    public function attempt_login(string $email, string $password): array
    {
        $user = $this->user_repo->find_by_email($email);
        if (!$user) {
            return ['ok'=>false, 'field'=>'email', 'error'=>'Invalid credentials.'];
        }
        if (!password_verify($password, $user['password_hash'])) {
            return ['ok'=>false, 'field'=>'password', 'error'=>'Invalid credentials.'];
        }
        if (empty($user['email_verified_at'])) {
            return ['ok'=>false, 'field'=>null, 'error'=>'Please verify your email first.'];
        }
        if (strtoupper($user['status']) !== 'ACTIVE') {
            return ['ok'=>false, 'field'=>null, 'error'=>'Your account is pending approval.'];
        }

        try {
            $this->audit_repo->add((string)$user['user_id'], 'user', (string)$user['user_id'], 'login_success', []);
        } catch (\Throwable $e) { /* ignore */ }

        return [
            'ok'   => true,
            'user' => [
                'user_id'   => (string)$user['user_id'],
                'email'     => (string)$user['email'],
                'user_type' => strtoupper(trim((string)$user['user_type'])), // 'ADM'|'TEC'|'SOP'|'CON'
                'username'  => (string)($user['username'] ?? ''),
            ],
        ];
    }
}