<?php
// includes/mailer.php
// -------------------------------------------------------------
// Envío de correos (PHPMailer si está disponible) con fallback a mail().
// Configuración leída de $config['mail'] (cargado en bootstrap.php).
// Claves esperadas en [mail] del INI:
//   smtp_host, smtp_port, smtp_user, smtp_pass, from_email, from_name, admin_to, log_file
// Además expone funciones:
//   - send_email($to, $subject, $html): bool
//   - send_verification_email($to, $username, $token): bool
//   - send_admin_signup_email(array $payload): bool
//   - send_mail($to, $subject, $html): bool   <-- COMPATIBILIDAD
// -------------------------------------------------------------
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/** Config normalizada y ruta de log resuelta. */
function mail_config(): array
{
    global $config; // definido en bootstrap.php
    $m = $config['mail'] ?? [];

    $cfg = [
        'smtp_host'  => (string)($m['smtp_host']  ?? 'smtp.gmail.com'),
        'smtp_port'  => (int)   ($m['smtp_port']  ?? 587),
        'smtp_user'  => (string)($m['smtp_user']  ?? ''),
        'smtp_pass'  => (string)($m['smtp_pass']  ?? ''),
        'from_email' => (string)($m['from_email'] ?? 'no-reply@localhost'),
        'from_name'  => (string)($m['from_name']  ?? 'Contractor App'),
        'admin_to'   => (string)($m['admin_to']   ?? ''),
        'log_file'   => (string)($m['log_file']   ?? 'storage/mail.log'),
    ];

    // Resolver a ruta absoluta si es relativa
    $is_abs = str_starts_with($cfg['log_file'], DIRECTORY_SEPARATOR)
              || preg_match('~^[A-Za-z]:\\\\~', $cfg['log_file']); // Windows
    if (!$is_abs) {
        $cfg['log_file'] = rtrim(BASE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $cfg['log_file'];
    }
    return $cfg;
}

/** Escribe en mail.log (crea carpeta si no existe). */
function mail_log(string $line): void
{
    $cfg  = mail_config();
    $file = $cfg['log_file'];
    $dir  = dirname($file);
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($file, "[$ts] $line\n", FILE_APPEND);
}

/** Crea PHPMailer si está disponible; si no, null para fallback. */
function make_phpmailer_or_null(): ?PHPMailer
{
    $base = __DIR__ . '/lib/phpmailer/src';
    $req  = [$base.'/PHPMailer.php', $base.'/SMTP.php', $base.'/Exception.php'];
    foreach ($req as $f) { if (!is_file($f)) return null; }
    require_once $base.'/PHPMailer.php';
    require_once $base.'/SMTP.php';
    require_once $base.'/Exception.php';
    return new PHPMailer(true);
}

/** Envío genérico HTML (usa SMTP con PHPMailer o mail() como fallback). */
function send_email(string $to, string $subject, string $html): bool
{
    $cfg = mail_config();

    if ($pm = make_phpmailer_or_null()) {
        try {
            $pm->isSMTP();
            $pm->Host       = $cfg['smtp_host'];
            $pm->Port       = $cfg['smtp_port'];
            $pm->SMTPAuth   = true;
            $pm->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $pm->Username   = $cfg['smtp_user'];
            $pm->Password   = $cfg['smtp_pass'];

            $pm->setFrom($cfg['from_email'], $cfg['from_name']);
            $pm->addAddress($to);
            $pm->isHTML(true);
            $pm->Subject = $subject;
            $pm->Body    = $html;

            $pm->send();
            mail_log("SMTP OK to={$to} subject=" . str_replace(["\r","\n"], ' ', $subject));
            return true;
        } catch (Throwable $e) {
            mail_log("SMTP ERROR to={$to} msg=" . $e->getMessage());
            // continúa a fallback mail()
        }
    }

    // Fallback: mail()
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$cfg['from_name']} <{$cfg['from_email']}>\r\n";

    $ok = @mail($to, $subject, $html, $headers);
    mail_log(($ok ? 'MAIL OK ' : 'MAIL FAIL ') . "to={$to} subject=" . str_replace(["\r","\n"], ' ', $subject));
    return $ok;
}

/** COMPATIBILIDAD: algunos servicios llaman send_mail(); delega a send_email(). */
function send_mail(string $to, string $subject, string $html): bool
{
    return send_email($to, $subject, $html);
}

/** Email de verificación con token. */
function send_verification_email(string $to, string $username, string $token): bool
{
    $verify_path = route_url('/verify-email') . '?t=' . urlencode($token);
    $verify_url  = base_url($verify_path);

    $subject = 'Verify your email';
    $html = '
        <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;">
            <p>Hello ' . sanitize_string($username) . ',</p>
            <p>Please confirm your email by clicking the button below:</p>
            <p style="margin:24px 0;">
                <a href="' . sanitize_string($verify_url) . '"
                   style="background:#e31d1a;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;display:inline-block;">
                    Verify email
                </a>
            </p>
            <p>If you did not request this, you can ignore this message.</p>
            <hr/>
            <p style="font-size:12px;color:#666;">If the button does not work, copy and paste this URL:<br>'
            . sanitize_string($verify_url) . '</p>
        </div>';

    $ok = send_email($to, $subject, $html);
    if (!$ok) { mail_log("VERIFY FAIL to={$to}"); }
    return $ok;
}

/** Notificación al admin del nuevo registro pendiente. */
function send_admin_signup_email(array $payload): bool
{
    $cfg   = mail_config();
    $admin = $cfg['admin_to'] ?: $cfg['from_email'];
    if (!$admin) {
        mail_log('ADMIN EMAIL missing; cannot notify.');
        return false;
    }

    $rows = '';
    foreach ($payload as $k => $v) {
        $rows .= '<tr><td style="padding:6px 8px;border:1px solid #ddd;">'
              . sanitize_string((string)$k) . '</td><td style="padding:6px 8px;border:1px solid #ddd;">'
              . sanitize_string((string)$v) . '</td></tr>';
    }
    $link = base_url(route_url('/approvals'));
    $subject = '[Contractor App] New sign-up pending approval';
    $html = '
        <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;">
            <p>Hello Admin,</p>
            <p>A new sign-up requires approval. Details:</p>
            <table style="border-collapse:collapse;border:1px solid #ddd;">' . $rows . '</table>
            <p style="margin:24px 0;">
                <a href="' . sanitize_string($link) . '"
                   style="background:#0d6efd;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;display:inline-block;">
                    Open approvals
                </a>
            </p>
        </div>';

    $ok = send_email($admin, $subject, $html);
    if (!$ok) { mail_log("ADMIN NOTIFY FAIL to={$admin}"); }
    return $ok;
}
