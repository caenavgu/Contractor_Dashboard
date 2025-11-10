<?php
declare(strict_types=1);

/**
 * includes/mailer.php
 * Puente fino entre configuración INI y App\Mail\Mailer.
 * - Carga explícita de PHPMailer (sin Composer)
 * - Expone helpers con tu flujo de negocio
 */

require_once BASE_PATH . '/includes/lib/phpmailer/src/Exception.php';
require_once BASE_PATH . '/includes/lib/phpmailer/src/PHPMailer.php';
require_once BASE_PATH . '/includes/lib/phpmailer/src/SMTP.php';

require_once BASE_PATH . '/app/Mail/Mailer.php';
require_once BASE_PATH . '/app/Mail/EmailCopy.php';

use App\Mail\Mailer as AppMailer;

/** Construye y retorna Mailer con config de /config/*.ini */
function mailer_instance(): AppMailer
{
    $cfg  = read_config_ini();
    $mail = $cfg['mail'] ?? [];

    // Mapeo de claves a las esperadas por App\Mail\Mailer
    $opts = [
        'smtp_host'     => (string)($mail['smtp_host'] ?? 'localhost'),
        'smtp_port'     => (int)   ($mail['smtp_port'] ?? 587),
        'smtp_secure'   => (string)($mail['smtp_encryption'] ?? 'tls'), // tls|ssl|''
        'smtp_user'     => (string)($mail['smtp_user'] ?? ''),
        'smtp_pass'     => (string)($mail['smtp_pass'] ?? ''),
        'from_email'    => (string)($mail['from_email'] ?? 'no-reply@example.com'),
        'from_name'     => (string)($mail['from_name']  ?? 'Contractor App'),
        // MUY IMPORTANTE: base_url debe apuntar al /public de tu front controller
        'base_url'      => rtrim((string)base_url('/'), '/'),
        'templates_dir' => BASE_PATH . '/app/Mail/templates',
    ];

    return new AppMailer($opts);
}

/* ============ Helpers de dominio que usa tu SignUpService/Approval ============ */

function send_verification_email(string $toEmail, string $userName, string $token): void
{
    $mailer = mailer_instance();
    $mailer->sendVerifyEmail($toEmail, $userName, $token);
}

/** Envía un correo de aprobación a UN admin (email + nombre). */
function send_admin_approval_email_to(string $adminEmail, string $adminName, string $approvalsUrl): void
{
    $mailer = mailer_instance();
    try {
        $mailer->sendAdminApproval($adminEmail, $adminName, $approvalsUrl);
        app_log("admin-approval email queued to {$adminEmail}");
    } catch (\Throwable $e) {
        app_log('send_admin_approval_email_to error: ' . $e->getMessage());
    }
}

function send_approved_email(string $toEmail, string $userName): void
{
    $mailer = mailer_instance();
    $mailer->sendApproved($toEmail, $userName);
}

function send_rejected_email(string $toEmail, string $userName, string $reason): void
{
    $mailer = mailer_instance();
    $mailer->sendRejected($toEmail, $userName, $reason);
}
