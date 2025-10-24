<?php
// app/Services/sign_up_service.php
// -------------------------------------------------------------
// Lógica de negocio para Sign Up (TEC + optional Contractor).
// -------------------------------------------------------------
declare(strict_types=1);

class SignUpService
{
    public function __construct(
        private UserRepository $user_repo,
        private ContractorRepository $contractor_repo,
        private ContractorStagingRepository $staging_repo,
        private AuditLogRepository $audit_repo
    ) {}

    /**
     * Procesa el sign up.
     * Devuelve ['ok'=>bool, 'errors'=>array, 'user_id'=>int|null, 'staging_id'=>int|null, 'contractor_id'=>int|null]
     */
    public function register(array $input, array $file_upload): array
    {
        $errors = [];

        // 1) Validaciones básicas
        if (!filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
            return ['ok'=>false,'errors'=>$errors];
        }

        // Email único
        if ($this->user_repo->find_by_email($input['email'])) {
            $errors['email'] = 'This email is already registered.';
            return ['ok'=>false,'errors'=>$errors];
        }

        if (empty($input['password']) || strlen($input['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
            return ['ok'=>false,'errors'=>$errors];
        }

        if (empty($input['epa_certification_number']) || empty($input['certifying_organization'])) {
            $errors['epa'] = 'EPA certification number and certifying organization are required.';
            return ['ok'=>false,'errors'=>$errors];
        }

        // 2) Procesar upload EPA
        $epa_filename = null;
        $epa_mime = null;
        $epa_size = null;
        $epa_checksum = null;

        if (!isset($file_upload['epa_photo']) || $file_upload['epa_photo']['error'] !== UPLOAD_ERR_OK) {
            $errors['epa_photo'] = 'EPA photo is required.';
            return ['ok'=>false,'errors'=>$errors];
        }

        // Validar file
        $f = $file_upload['epa_photo'];
        $max_bytes = (int)($GLOBALS['app_config']['epa_photo_max_bytes'] ?? 2097152);
        if ($f['size'] > $max_bytes) {
            $errors['epa_photo'] = 'EPA photo exceeds max size.';
            return ['ok'=>false,'errors'=>$errors];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($f['tmp_name']);
        $allowed = array_map('trim', explode(',', $GLOBALS['app_config']['epa_photo_mime_whitelist'] ?? 'image/jpeg,image/png,application/pdf'));
        if (!in_array($mime, $allowed, true)) {
            $errors['epa_photo'] = 'EPA photo type not allowed.';
            return ['ok'=>false,'errors'=>$errors];
        }

        // Normalizar EPA number para filename
        $norm = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '_', $input['epa_certification_number']));
        $dt = date('Ymd_His');
        $ext = pathinfo($f['name'], PATHINFO_EXTENSION) ?: ($mime === 'application/pdf' ? 'pdf' : 'jpg');
        $epa_filename = sprintf('epa_%s_%s.%s', $norm, $dt, $ext);

        // Ruta destino
        $uploads_dir = UPLOADS_PATH;
        if (!is_dir($uploads_dir)) {
            @mkdir($uploads_dir, 0775, true);
        }
        $dest = $uploads_dir . DIRECTORY_SEPARATOR . $epa_filename;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            $errors['epa_photo'] = 'Failed to store EPA photo.';
            return ['ok'=>false,'errors'=>$errors];
        }

        // Calcular checksum
        $epa_checksum = hash_file('sha256', $dest);
        $epa_mime = $mime;
        $epa_size = (int)filesize($dest);

        // 3) Contractor logic
        $contractor_id = null;
        $staging_id = null;
        if (!empty($input['associated_with_contractor']) && ($input['associated_with_contractor'] === 'yes' || $input['associated_with_contractor'] === true)) {
            $cac = $input['cac_license_number'] ?? '';
            if (empty($cac)) {
                $errors['cac'] = 'CAC license number is required when associated to contractor.';
                return ['ok'=>false,'errors'=>$errors];
            }

            $exists = $this->contractor_repo->find_by_cac($cac);
            if ($exists) {
                // crear staging
                $staging_id = $this->staging_repo->create($input, (int)$exists['contractor_id'], null);
            } else {
                // crear contractor inactivo
                $contractor_id = $this->contractor_repo->create($input);
            }
        }

        // 4) Crear usuario inactivo con token
        $password_hash = password_hash($input['password'], PASSWORD_BCRYPT);
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + (7 * 24 * 3600));

        // IMPORTANTE: user_type en formato CHAR(3) — usamos 'TEC' para technician
        $user_data = [
            'email' => $input['email'],
            'password_hash' => $password_hash,
            'contractor_id' => $contractor_id,
            'email_verification_token' => $token,
            'email_verification_expires_at' => $expires,
            'user_type' => 'TEC', // <-- código CHAR(3) para technician
            // detalles que irán a user_details
            'details' => [
                'first_name' => $input['first_name'] ?? null,
                'last_name' => $input['last_name'] ?? null,
                'phone_number' => $input['phone_number'] ?? null,
                'epa_certification_number' => $input['epa_certification_number'],
                'certifying_organization' => $input['certifying_organization'],
                'epa_photo_filename' => $epa_filename,
                'epa_photo_mime' => $epa_mime,
                'epa_photo_size' => $epa_size,
                'epa_photo_checksum' => $epa_checksum,
                // 'epa_photo_url' => null // opcional
            ],
        ];

        $user_id = $this->user_repo->create($user_data);

        // 5) Auditoría
        try {
            $this->audit_repo->add($user_id, 'user', $user_id, 'user_created', ['email' => $user_data['email']]);
        } catch (\Throwable $e) { /* swallow */ }

        // 6) Enviar correos: verification to user, notify admin
        try {
            // Email verification to user
            $verification_link = route_url('/verify-email') . '?token=' . $token;
            $subject = 'Verify your email address';
            $body = "<p>Hello " . htmlspecialchars($user_data['details']['first_name'] ?? '') . ",</p>
                     <p>Please verify your email by clicking the link below:</p>
                     <p><a href=\"" . htmlspecialchars($verification_link) . "\">Verify email</a></p>
                     <p>This link will expire in 7 days.</p>";
            send_mail($user_data['email'], $subject, $body);
            $this->audit_repo->add($user_id, 'user', $user_id, 'email_user', ['type'=>'verify_sent','email'=>$user_data['email']]);
        } catch (\Throwable $e) { /* swallow to avoid breaking */ }

        try {
            // Notify admin (simple)
            $admin_email = $GLOBALS['app_config']['smtp_from_email'] ?? 'no-reply@local.test';
            $subject = 'New registration pending review';
            $admin_body = "<p>New user registration:</p>
                           <p>Email: " . htmlspecialchars($user_data['email']) . "</p>
                           <p>Name: " . htmlspecialchars(($user_data['details']['first_name'] ?? '') . ' ' . ($user_data['details']['last_name'] ?? '')) . "</p>";
            if ($contractor_id) {
                $admin_body .= "<p>Contractor created (id: $contractor_id), CAC: " . htmlspecialchars($input['cac_license_number'] ?? '') . "</p>";
            } elseif ($staging_id) {
                $admin_body .= "<p>Contractor staging created (id: $staging_id), CAC: " . htmlspecialchars($input['cac_license_number'] ?? '') . "</p>";
            }
            $admin_body .= "<p><a href=\"" . route_url('/approvals') . "\">Open approvals</a></p>";
            send_mail($admin_email, $subject, $admin_body);
            $this->audit_repo->add(null, 'admin', null, 'email_admin', ['type'=>'new_registration','user_id'=>$user_id]);
        } catch (\Throwable $e) { /* swallow */ }

        return ['ok'=>true, 'errors'=>[], 'user_id'=>$user_id, 'staging_id'=>$staging_id, 'contractor_id'=>$contractor_id];
    }
}
