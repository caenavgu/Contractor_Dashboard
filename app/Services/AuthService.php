<?php
// app/Services/AuthService.php
// -------------------------------------------------------------
// Autenticación y manejo de sesión persistente
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
        if (strtoupper((string)$user['status']) !== 'ACTIVE') {
            return ['ok'=>false, 'field'=>null, 'error'=>'Your account is pending approval.'];
        }

        // Evento de éxito de login (antes de crear sesión)
        try {
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $this->audit_repo->log(
                (string)$user['user_id'],
                'login_success',
                $ip,
                [],
                'user',
                (string)$user['user_id']
            );
        } catch (\Throwable $_) {}

        return [
            'ok'   => true,
            'user' => [
                'user_id'   => (string)$user['user_id'],    // string tipo A000000001
                'email'     => (string)$user['email'],
                'user_type' => strtoupper(trim((string)$user['user_type'])),
                'username'  => (string)($user['username'] ?? ''),
            ],
        ];
    }

    /**
     * Crea sesión persistente + cookie.
     * @param string $userId    (varchar(16) tipo A000000001)
     * @param bool   $remember  true = 30 días; false = 30 min
     */
    public function issue_session(string $userId, bool $remember = false): void
    {
        $ttlShort = 30 * 60;
        $ttlLong  = 30 * 24 * 60 * 60;
        $ttl      = $remember ? $ttlLong : $ttlShort;

        $token = bin2hex(random_bytes(32));
        $ip    = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ua    = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $phpSid = session_id() ?: null; // CHAR(128) en BD: se guarda bien aunque sea más corto

        // Inserta en BD con userId STRING
        $this->session_repo->create($userId, $token, $ip, $ua, $ttl, $phpSid);
        // Marca actividad inicial y aplica expiración deslizante
        try {
            $this->session_repo->markSeenByToken($token);
        } catch (\Throwable $_) { /* no-op */ }

        // Auditoría de sign_in cuando la sesión ya existe
        try {
            $this->audit_repo->logSignIn($userId, (string)$phpSid);
        } catch (\Throwable $_) {}

        // Cookie
        $cookieName = 'app_session';
        $secure     = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $httpOnly   = true;
        $sameSite   = 'Lax';

        setcookie($cookieName, $token, [
            'expires'  => time() + $ttl,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ]);
    }

    public function logout(string $token, ?string $userId = null): void
    {
        // Auditoría de sign_out (mantengo tu firma y comportamiento)
        $ip  = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $sid = session_id() ?: null;

        try {
            if ($userId !== null) {
                $this->audit_repo->logSignOut($userId, $sid);
            }
        } catch (\Throwable $_) { /* no-op */ }
    }

    public function logoutAll(string $userId): void
    {
        // Cierra todas las sesiones del usuario
        try {
            $this->session_repo->deleteAllForUser($userId);
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $this->audit_repo->log(
                (string)$userId,
                'logout_all',
                $ip,
                [],
                'user',
                (string)$userId
            );
        } catch (\Throwable $_) { /* no-op */ }
    }
}
