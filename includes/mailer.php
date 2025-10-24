<?php
// includes/mailer.php
// -------------------------------------------------------------
// Helper simple para envío de correos (usa mail() con cabeceras).
// Leerá smtp config si más adelante cambias por SMTP real.
// -------------------------------------------------------------
declare(strict_types=1);

/**
 * Envía un correo simple. Devuelve true/false.
 * Para producción reemplazar por PHPMailer/SwiftMailer o similar.
 */
function send_mail(string $to, string $subject, string $body, string $from_email = null, string $from_name = null): bool
{
    $from_email = $from_email ?? ($GLOBALS['app_config']['smtp_from_email'] ?? 'no-reply@local.test');
    $from_name  = $from_name ?? ($GLOBALS['app_config']['smtp_from_name'] ?? 'Contractor App');
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=utf-8';
    $headers[] = 'From: ' . mb_encode_mimeheader($from_name) . ' <' . $from_email . '>';
    $headers[] = 'Reply-To: ' . $from_email;
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    return @mail($to, $subject, $body, implode("\r\n", $headers));
}
