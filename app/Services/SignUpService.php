<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/UserRepository.php';
require_once __DIR__ . '/../Repositories/ContractorRepository.php';
require_once __DIR__ . '/../Repositories/UserDetailsRepository.php';
require_once __DIR__ . '/../Repositories/AuditLogRepository.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/helpers.php';

class SignUpService
{
    public function __construct(
        private PDO $pdo,
        private UserRepository $userRepo,
        private ContractorRepository $contractorRepo,
        private UserDetailsRepository $detailsRepo,
        private AuditLogRepository $auditRepo
    ) {}

    /**
     * Registra al usuario y envía email de verificación.
     * @param array $form  Datos del formulario (usuario + contractor)
     * @param array $files Archivos subidos (ej: EPA photo)
     * @return array { ok: bool, redirect?: string, error?: string }
     */
    public function register(array $form, array $files): array
    {
        try {
            $email    = strtolower(trim((string)($form['email'] ?? '')));
            $pass     = (string)($form['password'] ?? '');
            $pass2    = (string)($form['password_confirm'] ?? '');

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => 'Invalid email.'];
            }
            if ($pass === '' || strlen($pass) < 8) {
                return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
            }
            if ($pass !== $pass2) {
                return ['ok' => false, 'error' => 'Passwords do not match.'];
            }
            if ($this->userRepo->find_by_email($email)) {
                return ['ok' => false, 'error' => 'An account with this email already exists.'];
            }

            $this->pdo->beginTransaction();

            // 1) Crear usuario
            $token   = bin2hex(random_bytes(32));
            $expires = (new DateTimeImmutable('+7 days'))->format('Y-m-d H:i:s');

            $user = [
                'email'                         => $email,
                'username'                      => $email,
                'password_hash'                 => password_hash($pass, PASSWORD_DEFAULT),
                'user_type'                     => (string)($form['user_type'] ?? 'TEC'),
                'contractor_id'                 => null,
                'status'                        => 'PENDING',
                'email_verification_token'      => $token,
                'email_verification_expires_at' => $expires,
                'email_verified_at'             => null,
                'approved_by'                   => null,
                'approved_at'                   => null,
                'rejected_by'                   => null,
                'rejected_at'                   => null,
                'rejection_reason'              => null,
            ];
            $userId = $this->userRepo->create($user);

            // 2) Detalles del usuario (EPA, etc.)
            $details = [
                'user_id'                 => $userId,
                'first_name'              => strtoupper(trim((string)($form['first_name'] ?? ''))),
                'last_name'               => strtoupper(trim((string)($form['last_name'] ?? ''))),
                'phone_number'            => (string)($form['phone_number'] ?? ''),
                'certifying_organization' => strtoupper(trim((string)($form['certifying_organization'] ?? ''))),
                'epa_certification_number'=> (string)($form['epa_certification_number'] ?? ''),
                // si subes foto EPA en $files, aquí procesas y completas estos campos:
                'epa_photo_url'      => $form['epa_photo_url']      ?? null,
                'epa_photo_filename' => $form['epa_photo_filename'] ?? null,
                'epa_photo_mime'     => $form['epa_photo_mime']     ?? null,
                'epa_photo_size'     => $form['epa_photo_size']     ?? null,
                'epa_photo_checksum' => $form['epa_photo_checksum'] ?? null,
            ];
            $this->detailsRepo->create($details);

            // 3) Contractor (si aplica)
            $cac = strtoupper(trim((string)($form['cac_license_number'] ?? '')));
            if ($cac !== '') {
                $existing = $this->contractorRepo->find_by_cac($cac);
                if ($existing) {
                    $this->userRepo->assign_contractor($userId, (int)$existing['contractor_id']);
                } else {
                    // crear contractor pendiente con los datos del form
                    $contractorData = [
                        'cac_license_number' => $cac,
                        'company_name'       => strtoupper(trim((string)($form['company_name'] ?? ''))),
                        'company_phone'      => (string)($form['company_phone'] ?? ''),
                        'company_email'      => (string)($form['company_email'] ?? ''),
                        'company_website'    => (string)($form['company_website'] ?? ''),
                        'address'            => strtoupper(trim((string)($form['address'] ?? ''))),
                        'address_2'          => strtoupper(trim((string)($form['address_2'] ?? ''))),
                        'city'               => strtoupper(trim((string)($form['city'] ?? ''))),
                        'state_code'         => strtoupper(trim((string)($form['state_code'] ?? ''))),
                        'zip_code'           => (string)($form['zip_code'] ?? ''),
                        'status'             => 'PENDING',
                    ];
                    $contractorId = $this->contractorRepo->create_pending($contractorData);
                    $this->userRepo->assign_contractor($userId, $contractorId);
                }
            }

            // 4) Auditoría
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            if (method_exists($this->auditRepo, 'log')) {
                $this->auditRepo->log($userId, 'user_created', $ip, ['email' => $email, 'status' => 'PENDING']);
            } elseif (method_exists($this->auditRepo, 'add')) {
                $this->auditRepo->add((string)$userId, 'user', (string)$userId, 'user_created', ['email' => $email, 'status' => 'PENDING']);
            }

            // 5) Email de verificación (con ruta correcta)
            $verifyUrl = route_url('/verify-email') . '?t=' . urlencode($token);
            $subject = 'Verify your Everwell account';
            $body = "<p>Hello,</p>
                     <p>Please verify your email by clicking the link below:</p>
                     <p><a href=\"{$verifyUrl}\">Verify my email</a></p>
                     <p>This link will expire in 7 days.</p>";
            send_mail($email, $subject, $body);

            $this->pdo->commit();
            return ['ok' => true, 'redirect' => '/sign-up-success'];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("SignUpService::register error: " . $e->getMessage());
            return ['ok' => false, 'error' => 'Registration failed. Please try again.'];
        }
    }

    /**
     * Verifica el email por token (?t=...).
     * @return array { ok: bool, message: string }
     */
    public function verify_email(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return ['ok' => false, 'message' => 'Missing token.'];
        }

        $user = $this->userRepo->find_by_token($token);
        if (!$user) {
            return ['ok' => false, 'message' => 'Invalid verification token.'];
        }

        try {
            $expiresAt = (string)($user['email_verification_expires_at'] ?? '');
            if ($expiresAt === '' || new DateTimeImmutable($expiresAt) < new DateTimeImmutable('now')) {
                return ['ok' => false, 'message' => 'Verification link has expired.'];
            }

            $this->userRepo->mark_email_verified((int)$user['user_id']);

            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            if (method_exists($this->auditRepo, 'log')) {
                $this->auditRepo->log((int)$user['user_id'], 'email_verified', $ip, []);
            } elseif (method_exists($this->auditRepo, 'add')) {
                $this->auditRepo->add((string)$user['user_id'], 'user', (string)$user['user_id'], 'email_verified', []);
            }

            // avisa al admin
            $adminEmail = getenv('APP_ADMIN_EMAIL') ?: 'admin@everwell-ac.com';
            send_mail(
                $adminEmail,
                'New user pending approval',
                "<p>User <strong>" . htmlspecialchars((string)$user['email']) . "</strong> has verified their email and is awaiting approval.</p>"
            );

            return ['ok' => true, 'message' => 'Email verified successfully. Your account is pending approval.'];
        } catch (\Throwable $e) {
            error_log("verify_email error: " . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not verify email.'];
        }
    }
}
