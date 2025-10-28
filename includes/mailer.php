<?php
// includes/mailer.php
// -------------------------------------------------------------
// Envío de correos por SMTP usando PHPMailer sin Composer.
// Carga manual de clases desde includes/lib/phpmailer/src
// Lee configuración desde $GLOBALS['app_config'] (app.local.ini).
// Soporta "smtp" y "file" (guarda .eml) para desarrollo.
// -------------------------------------------------------------
declare(strict_types=1);

// Carga manual de clases PHPMailer
$phpmailer_base = __DIR__ . '/lib/phpmailer';
require_once $phpmailer_base . '/src/Exception.php';
require_once $phpmailer_base . '/src/PHPMailer.php';
require_once $phpmailer_base . '/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envía un correo HTML (SMTP o "file").
 * Devuelve true si se envía/guarda; false si hubo error.
 * Errores se registran en storage/logs/mail.log
 */
function send_mail(string $to, string $subject, string $body, string $from_email = null, string $from_name = null): bool
{
    $cfg = $GLOBALS['app_config'] ?? [];

    $transport = $cfg['mail_transport'] ?? 'smtp'; // 'smtp' | 'file'
    $from_email = $from_email ?? ($cfg['smtp_from_email'] ?? 'no-reply@example.test');
    $from_name  = $from_name  ?? ($cfg['smtp_from_name']  ?? 'Contractor App');

    // Logs
    $log_dir  = __DIR__ . '/../storage/logs';
    $log_file = $log_dir . '/mail.log';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0775, true);
    }

    // Transport "file": guarda .eml para revisar sin SMTP
    if ($transport === 'file') {
        $dir = __DIR__ . '/../storage/mails';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $fname = $dir . '/' . date('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $to) . '.eml';
        $eml  = "From: {$from_name} <{$from_email}>\r\n";
        $eml .= "To: {$to}\r\n";
        $eml .= "Subject: {$subject}\r\n";
        $eml .= "MIME-Version: 1.0\r\n";
        $eml .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
        $eml .= $body;
        file_put_contents($fname, $eml);
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] [FILE] Saved mail to $fname\n", FILE_APPEND);
        return true;
    }

    // SMTP config (Gmail u otro)
    $host     = $cfg['smtp_host'] ?? 'smtp.gmail.com';
    $port     = (int)($cfg['smtp_port'] ?? 587);
    $username = $cfg['smtp_username'] ?? '';
    $password = $cfg['smtp_password'] ?? '';
    $secure   = $cfg['smtp_secure']  ?? 'tls'; // 'tls' | 'ssl' | ''
    $auth     = filter_var($cfg['smtp_auth'] ?? '1', FILTER_VALIDATE_BOOLEAN);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->Port       = $port;
        $mail->SMTPAuth   = $auth;
        $mail->Username   = $username;
        $mail->Password   = $password;

        if ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = false;
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        $mail->send();
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] [SMTP] Sent to {$to} subj='{$subject}'\n", FILE_APPEND);
        return true;
    } catch (Exception $e) {
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] [SMTP][ERROR] {$e->getMessage()}\n", FILE_APPEND);
        return false;
    }
}
