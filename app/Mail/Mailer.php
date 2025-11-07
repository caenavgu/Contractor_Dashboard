<?php
declare(strict_types=1);

namespace App\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Mailer de la app:
 *  - Usa PHPMailer nativo (sin Composer autoload)
 *  - Renderiza templates con {{placeholders}}
 *  - Expone métodos públicos específicos del dominio
 *
 * Convenciones de ruteo (coherentes con tu front controller):
 *   /verify-email?t={TOKEN}
 *   /sign-in
 */
class Mailer
{
    private PHPMailer $mailer;
    private string $baseUrl;       // ej: http://localhost/contractor.everwell-ac.com/public
    private string $templatesDir;  // ej: /path/app/Mail/templates
    private string $supportEmail;
    private string $appName;

    public function __construct(array $opts)
    {
        // Espera claves en $opts:
        // smtp_host, smtp_port, smtp_secure('tls'|'ssl'|''), smtp_user, smtp_pass,
        // from_email, from_name, base_url, templates_dir

        // PHPMailer listo para SMTP
        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host       = (string)($opts['smtp_host'] ?? 'localhost');
        $this->mailer->Port       = (int)($opts['smtp_port'] ?? 587);
        $secure = strtolower((string)($opts['smtp_secure'] ?? 'tls'));
        if ($secure === 'ssl') {
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls' || $secure === 'starttls') {
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $this->mailer->SMTPSecure = '';
        }

        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = (string)($opts['smtp_user'] ?? '');
        $this->mailer->Password   = (string)($opts['smtp_pass'] ?? '');

        $fromEmail = (string)($opts['from_email'] ?? 'no-reply@example.com');
        $fromName  = (string)($opts['from_name']  ?? EmailCopy::APP_NAME);
        $this->mailer->setFrom($fromEmail, $fromName);

        $this->baseUrl      = rtrim((string)($opts['base_url'] ?? ''), '/'); // debe incluir /public
        $this->templatesDir = rtrim((string)($opts['templates_dir'] ?? __DIR__ . '/templates'), '/');

        $this->supportEmail = EmailCopy::SUPPORT_EMAIL;
        $this->appName      = EmailCopy::APP_NAME;

        $this->mailer->isHTML(true);
        $this->mailer->CharSet = 'UTF-8';
    }

    /* ===================== Public API ===================== */

    public function sendVerifyEmail(string $toEmail, string $toName, string $token): void
    {
        // Ruta coherente con tu router: /verify-email?t=TOKEN
        $verifyLink = $this->baseUrl . '/verify-email?t=' . urlencode($token);

        $subject = EmailCopy::SUBJECT_VERIFY_EMAIL;
        $html = $this->render('verify-email.html.php', [
            'app_name'      => $this->appName,
            'user_name'     => $toName,
            'verify_link'   => $verifyLink,
            'support_email' => $this->supportEmail,
            'cta_text'      => EmailCopy::CTA_VERIFY,
            'expires_in'    => EmailCopy::VERIFY_LINK_EXPIRY,
        ]);
        $text = $this->toText("Hi {$toName}, verify your email: {$verifyLink}");

        $this->deliver($toEmail, $toName, $subject, $html, $text);
    }

    public function sendAdminApproval(string $adminEmail, string $adminName, string $approvalUrl): void
    {
        $subject = EmailCopy::SUBJECT_ADMIN_APPROVAL;
        $html = $this->render('admin-approval.html.php', [
            'app_name'      => $this->appName,
            'user_name'     => $adminName,
            'approval_link' => $approvalUrl,
            'support_email' => $this->supportEmail,
            'cta_text'      => EmailCopy::CTA_REVIEW_USER,
        ]);
        $text = $this->toText("New user pending approval: {$approvalUrl}");

        $this->deliver($adminEmail, $adminName, $subject, $html, $text);
    }

    public function sendApproved(string $toEmail, string $toName): void
    {
        // Ruta coherente: /sign-in
        $signin = $this->baseUrl . '/sign-in';

        $subject = EmailCopy::SUBJECT_APPROVED;
        $html = $this->render('approved.html.php', [
            'app_name'      => $this->appName,
            'user_name'     => $toName,
            'signin_link'   => $signin,
            'support_email' => $this->supportEmail,
            'cta_text'      => EmailCopy::CTA_SIGN_IN,
        ]);
        $text = $this->toText("Hi {$toName}, your account is active. Sign in: {$signin}");

        $this->deliver($toEmail, $toName, $subject, $html, $text);
    }

    public function sendRejected(string $toEmail, string $toName, string $reason): void
    {
        $subject = EmailCopy::SUBJECT_REJECTED;
        $html = $this->render('rejected.html.php', [
            'app_name'      => $this->appName,
            'user_name'     => $toName,
            'reason'        => $reason,
            'support_email' => $this->supportEmail,
        ]);
        $text = $this->toText("Hi {$toName}, your request was not approved. Reason: {$reason}");

        $this->deliver($toEmail, $toName, $subject, $html, $text);
    }

    public function sendResetPassword(string $toEmail, string $toName, string $resetLink): void
    {
        $subject = EmailCopy::SUBJECT_RESET_PASSWORD;
        $html = $this->render('reset-password.html.php', [
            'app_name'      => $this->appName,
            'user_name'     => $toName,
            'reset_link'    => $resetLink,
            'support_email' => $this->supportEmail,
            'cta_text'      => EmailCopy::CTA_RESET,
        ]);
        $text = $this->toText("Hi {$toName}, reset your password: {$resetLink}");

        $this->deliver($toEmail, $toName, $subject, $html, $text);
    }

    /* ===================== Internos ===================== */

    private function deliver(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): void
    {
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->clearAttachments();

            $this->mailer->addAddress($toEmail, $toName);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $htmlBody;
            $this->mailer->AltBody = $textBody;

            $this->mailer->send();
        } catch (PHPMailerException $e) {
            throw $e;
        }
    }

    private function render(string $templateName, array $vars): string
    {
        $file = $this->templatesDir . '/' . ltrim($templateName, '/');
        if (!is_file($file)) {
            throw new \RuntimeException("Template not found: {$file}");
        }

        $contents = file_get_contents($file);

        // Valores por defecto disponibles para todos los templates
        $vars = array_merge([
            'app_name'      => $this->appName,
            'support_email' => $this->supportEmail,
        ], $vars);

        $footerHtml = str_replace('{{support_email}}', $this->supportEmail, EmailCopy::FOOTER_HTML);

        $map = [];
        foreach ($vars as $k => $v) {
            $map['{{' . $k . '}}'] = (string)$v;
        }
        $map['{{support_email}}'] = $this->supportEmail;
        $map['{{footer_html}}']   = $footerHtml;

        return strtr($contents, $map);
    }

    private function toText(string $primaryLine): string
    {
        $footer = str_replace('{{support_email}}', $this->supportEmail, EmailCopy::FOOTER_TEXT);
        return trim($primaryLine . "\n\n" . $footer);
    }
}
