<?php
// app/Services/auth_service.php
// -------------------------------------------------------------
// Lógica de autenticación con auditoría a prueba de fallos
// -------------------------------------------------------------
declare(strict_types=1);

class AuthService
{
    public function __construct(
        private UserRepository $user_repo,
        private SessionRepository $session_repo,
        private AuditLogRepository $audit_repo
    ) {}

    public function authenticate(string $email, string $password, string $ip, string $ua): array
    {
        $user = $this->user_repo->find_by_email($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Auditoría no bloqueante
            try {
                $this->audit_repo->add(null, 'user', 0, 'login_failed', [
                    'email' => $email, 'ip' => $ip, 'user_agent' => (string)$ua
                ]);
            } catch (\Throwable $e) {
                // swallow: nunca romper login por auditoría
            }
            return ['ok' => false, 'error' => 'Invalid credentials.', 'user' => null];
        }

        if (empty($user['email_verified_at'])) {
            return ['ok' => false, 'error' => 'Please verify your email before signing in.', 'user' => null];
        }

        if ((int)$user['is_active'] !== 1) {
            return ['ok' => false, 'error' => 'Your account is pending approval by an administrator.', 'user' => null];
        }

        return ['ok' => true, 'error' => null, 'user' => $user];
    }

    public function create_session(array $user, bool $remember_me): void
    {
        $session_id    = bin2hex(random_bytes(64));
        $session_token = bin2hex(random_bytes(32));
        $ip = get_client_ip();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $ttl_seconds = $remember_me ? (30 * 24 * 3600) : (24 * 3600);
        $this->session_repo->create($user['user_id'], $session_id, $session_token, $ip, $ua, $ttl_seconds);

        setcookie(
            'session_token',
            $session_token,
            [
                'expires'  => time() + $ttl_seconds,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );

        // Auditoría no bloqueante
        try {
            $this->audit_repo->add($user['user_id'], 'user', 0, 'login_success', [
                'ip' => $ip, 'user_agent' => (string)$ua
            ]);
        } catch (\Throwable $e) {
            // swallow
        }
    }
}
