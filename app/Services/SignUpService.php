<?php
// app/Services/SignUpService.php
// -------------------------------------------------------------
// Registra un nuevo técnico/contratista en estado PENDING,
// envía correo de verificación y registra en auditoría.
// -------------------------------------------------------------
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/UserRepository.php';
require_once __DIR__ . '/../Repositories/ContractorRepository.php';
require_once __DIR__ . '/../Repositories/UserDetailsRepository.php';
require_once __DIR__ . '/../Repositories/AuditLogRepository.php';
require_once __DIR__ . '/../../includes/mailer.php';

class SignUpService
{
    private UserRepository $userRepo;
    private ContractorRepository $contractorRepo;
    private UserDetailsRepository $detailsRepo;
    private AuditLogRepository $auditRepo;
    private PDO $pdo;

    public function __construct(
        PDO $pdo,
        UserRepository $userRepo,
        ContractorRepository $contractorRepo,
        UserDetailsRepository $detailsRepo,
        AuditLogRepository $auditRepo
    ) {
        $this->pdo            = $pdo;
        $this->userRepo       = $userRepo;
        $this->contractorRepo = $contractorRepo;
        $this->detailsRepo    = $detailsRepo;
        $this->auditRepo      = $auditRepo;
    }

    public function register(array $userData, array $contractorData): bool
    {
        $this->pdo->beginTransaction();
        try {
            $email    = strtolower(trim($userData['email'] ?? ''));
            $password = (string)($userData['password'] ?? '');
            $confirm  = (string)($userData['confirm_password'] ?? '');

            if ($email === '' || $password === '' || $confirm === '') {
                throw new Exception('All fields are required.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email.');
            }
            if ($password !== $confirm) {
                throw new Exception('Passwords do not match.');
            }

            // ¿existe?
            if ($this->userRepo->find_by_email($email)) {
                throw new Exception('An account with this email already exists.');
            }

            // hash + token verificación (7 días)
            $hash    = password_hash($password, PASSWORD_BCRYPT);
            $token   = bin2hex(random_bytes(32));
            $expires = (new DateTime('+7 days'))->format('Y-m-d H:i:s');

            $this->userRepo->set_email_verification($userId, $token, $expires);

            // insertar en users
            $user = [
                'email'                         => $email,
                'username'                      => $email,
                'password_hash'                 => $hash,
                'user_type'                     => 'TEC',
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

            // insertar detalles en user_details
            $details = [
                'user_id'                 => $userId,
                'first_name'              => strtoupper(trim($userData['first_name'] ?? '')),
                'last_name'               => strtoupper(trim($userData['last_name'] ?? '')),
                'phone_number'            => trim($userData['phone_number'] ?? ''),
                'certifying_organization' => strtoupper(trim($userData['certifying_organization'] ?? '')),
                'epa_certification_number'      => trim($userData['epa_certification_number'] ?? ''),
                'epa_photo_url'           => $userData['epa_photo_url'] ?? null,
                'epa_photo_filename'      => $userData['epa_photo_filename'] ?? null,
                'epa_photo_mime'          => $userData['epa_photo_mime'] ?? null,
                'epa_photo_size'          => $userData['epa_photo_size'] ?? null,
                'epa_photo_checksum'      => $userData['epa_photo_checksum'] ?? null,
            ];
            $this->detailsRepo->create($details);

            // contractor (opcional)
            if (!empty($contractorData['cac_license_number'])) {
                $existingContractor = $this->contractorRepo->find_by_cac($contractorData['cac_license_number']);
                if ($existingContractor) {
                    $this->userRepo->assign_contractor($userId, (int)$existingContractor['contractor_id']);
                } else {
                    $contractorId = $this->contractorRepo->create_pending($contractorData);
                    $this->userRepo->assign_contractor($userId, $contractorId);
                }
            }

            // log auditoría (IP como string)
            $clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $this->auditRepo->log($userId, 'user_created', $clientIp, [
                'email'  => $email,
                'status' => 'PENDING',
            ]);

            // email de verificación (link funcional)
            $verifyLink = base_url("/public/verify-email.php?token={$token}");
            $subject = 'Verify your Everwell account';
            $body = "<p>Hello,</p>
                     <p>Please verify your email by clicking the link below:</p>
                     <p><a href=\"{$verifyLink}\">Verify my email</a></p>
                     <p>This link will expire in 7 days.</p>";

            send_mail($email, $subject, $body);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log("SignUpService::register() failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function verifyEmail(string $token): bool
    {
        $user = $this->userRepo->find_by_token($token);
        if (!$user) {
            throw new Exception('Invalid verification token.');
        }

        $now = new DateTimeImmutable();
        if (new DateTimeImmutable($user['email_verification_expires_at']) < $now) {
            throw new Exception('Verification link has expired.');
        }

        $this->userRepo->mark_email_verified($user['user_id']);

        $clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $this->auditRepo->log($user['user_id'], 'email_verified', $clientIp, []);

        $adminEmail = getenv('APP_ADMIN_EMAIL') ?: 'admin@everwell-ac.com';
        send_mail(
            $adminEmail,
            'New user pending approval',
            "<p>User <strong>{$user['email']}</strong> has verified their email and is awaiting approval.</p>"
        );

        return true;
    }
}
