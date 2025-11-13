<?php
// app/Services/SignUpService.php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/UserRepository.php';
require_once __DIR__ . '/../Repositories/ContractorRepository.php';
require_once __DIR__ . '/../Repositories/UserDetailsRepository.php';
require_once __DIR__ . '/../Repositories/AuditLogRepository.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/mailer.php';

class SignUpService
{
    public function __construct(
        private PDO $pdo,
        private UserRepository $userRepo,
        private ContractorRepository $contractorRepo,
        private UserDetailsRepository $detailsRepo,
        private AuditLogRepository $auditRepo
    ) {}

    /** Registra al usuario y envía email de verificación. */
    public function register(array $form, array $files): array
    {
        try {
            // -------- Validaciones base --------
            $email = strtolower(trim((string)($form['email'] ?? '')));
            $pass  = (string)($form['password'] ?? '');
            $pass2 = (string)($form['confirm_password'] ?? '');

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['ok'=>false,'error'=>'Invalid email.'];
            }
            if ($pass === '' || strlen($pass) < 8) {
                return ['ok'=>false,'error'=>'Password must be at least 8 characters.'];
            }
            if ($pass !== $pass2) {
                return ['ok'=>false,'error'=>'Passwords do not match.'];
            }
            if ($this->userRepo->find_by_email($email)) {
                return ['ok'=>false,'error'=>'An account with this email already exists.', 'field'=>'email'];
            }

            // EPA requeridos
            $epa_number = strtoupper(trim((string)($form['epa_certification_number'] ?? '')));
            if ($epa_number === '') {
                return ['ok'=>false,'error'=>'EPA certification number is required.'];
            }
            $epa_file = $files['epa_photo'] ?? null;
            if (!$epa_file || (int)($epa_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                return ['ok'=>false,'error'=>'EPA certification photo is required.'];
            }

            // -------- Guardar archivo EPA (solo URL en BD) --------
            $maxBytes = 2 * 1024 * 1024; // 2MB
            if ((int)$epa_file['size'] > $maxBytes) {
                return ['ok'=>false,'error'=>'EPA photo must be under 2MB.'];
            }
            $allowedExt = ['jpg','jpeg','png','pdf'];
            $ext = strtolower(pathinfo((string)$epa_file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                return ['ok'=>false,'error'=>'EPA photo must be JPG, PNG or PDF.'];
            }

            $destDir = BASE_PATH . '/storage/uploads/EPA';
            if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
            $destName = 'EPA_' . preg_replace('/[^A-Z0-9_-]/', '', $epa_number) . '_' . date('YmdHis') . '.' . $ext;
            $destPath = $destDir . DIRECTORY_SEPARATOR . $destName;

            if (!@move_uploaded_file($epa_file['tmp_name'], $destPath)) {
                return ['ok'=>false,'error'=>'Could not save EPA file.'];
            }
            // En BD solo guardamos la URL relativa:
            $epa_url = '/storage/uploads/EPA/' . $destName;

            // -------- Transacción --------
            $this->pdo->beginTransaction();

            $token   = bin2hex(random_bytes(32));
            $expires = (new DateTimeImmutable('+7 days'))->format('Y-m-d H:i:s');

            // 1) users (PENDING)
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

            // 2) user_details (ajustado a tu esquema)
            $details = [
                'user_id'                 => $userId,
                'first_name'              => strtoupper(trim((string)($form['first_name'] ?? ''))),
                'last_name'               => strtoupper(trim((string)($form['last_name'] ?? ''))),
                'phone_number'            => (string)($form['phone_number'] ?? ''),
                'epa_certification_number'=> $epa_number,
                'certifying_organization' => strtoupper(trim((string)($form['certifying_organization'] ?? ''))),
                'epa_photo_url'           => $epa_url,
            ];
            $this->detailsRepo->create($details);

            // 3) Contractor (opcional)
            if (!empty($form['has_contractor'])) {
                $cac = strtoupper(trim((string)($form['cac_license_number'] ?? '')));
                if ($cac !== '') {
                    $existing = $this->contractorRepo->find_by_cac($cac);
                    if ($existing) {
                        // Asignar contractor existente
                        $this->userRepo->assign_contractor($userId, (int)$existing['contractor_id']);

                        // Registrar intento en contractor_staging si está disponible globalmente
                        if (isset($GLOBALS['staging_repo']) && $GLOBALS['staging_repo']) {
                            try {
                                $payload = [
                                    'existing_contractor_id'   => (int)$existing['contractor_id'],
                                    'input_cac_license_number' => $cac,
                                    'input_company_name'       => strtoupper(trim((string)($form['company_name'] ?? ''))),
                                    'input_address'            => strtoupper(trim((string)($form['address'] ?? ''))),
                                    'input_address_2'          => strtoupper(trim((string)($form['address_2'] ?? ''))),
                                    'input_city'               => strtoupper(trim((string)($form['city'] ?? ''))),
                                    'input_state_code'         => strtoupper(trim((string)($form['state_code'] ?? ''))),
                                    'input_zip_code'           => (string)($form['zip_code'] ?? ''),
                                    'input_company_phone'      => (string)($form['company_phone'] ?? ''),
                                    'input_company_email'      => (string)($form['company_email'] ?? ''),
                                    'input_company_website'    => (string)($form['company_website'] ?? ''),
                                    'input_raw_json'           => json_encode($form, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
                                    'created_by_user_id'       => $userId,
                                    'status'                   => 'pending',
                                ];
                                $repo = $GLOBALS['staging_repo'];
                                if (method_exists($repo, 'create'))      { $repo->create($payload); }
                                elseif (method_exists($repo, 'insert')) { $repo->insert($payload); }
                            } catch (\Throwable $e) {
                                app_log('contractor_staging insert failed: ' . $e->getMessage());
                            }
                        }
                    } else {
                        // Crear contractor PENDING y asignar
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
            }

            // 4) Auditoría
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            if (method_exists($this->auditRepo, 'log')) {
                $this->auditRepo->log($userId, 'user_created', $ip, ['email'=>$email,'status'=>'PENDING']);
            } elseif (method_exists($this->auditRepo, 'add')) {
                $this->auditRepo->add((string)$userId, 'user', (string)$userId, 'user_created', ['email'=>$email,'status'=>'PENDING']);
            }

            // 5) Email de verificación (usa tus templates)
            $full_name   = trim(strtoupper((string)($form['first_name'] ?? '')) . ' ' . strtoupper((string)($form['last_name'] ?? '')));
            $displayName = $full_name !== '' ? $full_name : $email;
            send_verification_email($email, $displayName, $token);

            $this->pdo->commit();
            return ['ok'=>true,'redirect'=>'/sign-up-success'];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            app_log('SignUpService::register error: ' . $e->getMessage());
            return ['ok'=>false,'error'=>'Registration failed. Please try again.'];
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
            return ['ok'=>false,'message'=>'Missing token.'];
        }

        $user = $this->userRepo->find_by_verification_token($token);
        if (!$user) {
            return ['ok'=>false,'message'=>'Invalid verification token.'];
        }

        try {
            $exp = (string)($user['email_verification_expires_at'] ?? '');
            if ($exp === '' || new DateTimeImmutable($exp) < new DateTimeImmutable('now')) {
                return ['ok'=>false,'message'=>'Verification link has expired.'];
            }

            $this->userRepo->mark_email_verified((string)$user['user_id']);

            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            if (method_exists($this->auditRepo, 'log')) {
                $this->auditRepo->log((string)$user['user_id'], 'email_verified', $ip, []);
            } elseif (method_exists($this->auditRepo, 'add')) {
                $this->auditRepo->add((string)$user['user_id'], 'user', (string)$user['user_id'], 'email_verified', []);
            }

            // ---- Notificar a TODOS los admins (ADM activos y verificados) ----
            // $approvals_url = rtrim((string)base_url('/'), '/') . route_url('/approvals');
            $approvals_url = absolute_route_url('/approvals');
            $admins = $this->userRepo->list_admin_recipients(); // [['email','name'], ...]

            foreach ($admins as $adm) {
                $toEmail = (string)$adm['email'];
                $toName  = (string)$adm['name'];
                if ($toEmail !== '') {
                    send_admin_approval_email_to($toEmail, $toName, $approvals_url);
                }
            }

            return ['ok'=>true,'message'=>'Email verified successfully. Your account is pending approval.'];
        } catch (\Throwable $e) {
            app_log('verify_email error: ' . $e->getMessage());
            return ['ok'=>false,'message'=>'Could not verify email.'];
        }
    }
}