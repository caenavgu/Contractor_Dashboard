<?php
declare(strict_types=1);

namespace App\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Mailer
{
    private PHPMailer $mailer;
    private string $baseUrl;
    private string $templatesDir;
    private string $supportEmail;
    private string $appName;

    /**
     * @param array $opts [
     *   'smtp_host','smtp_port','smtp_user','smtp_pass','smtp_secure'('tls'|'ssl'|null),
     *   'from_email','from_name','base_url','templates_dir'
     * ]
     */
    public function __construct(array $opts)
    {
        // PHPMailer include (ajusta la ruta si la tienes en otra carpeta)
        // require_once __DIR__ . '/../../includes/lib/phpmailer/src/PHPMailer.php';
        // require_once __DIR__ . '/../../includes/lib/phpmailer/src/SMTP.php';
        // require_once __DIR__ . '/../../includes/lib/phpmailer/src/Exception.php';

        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host       = $opts['smtp_host']    ?? 'localhost';
        $this->mailer->Port       = (int)($opts['smtp_port'] ?? 587);
        if (!empty($opts['smtp_secure'])) {
            $this->mailer->SMTPSecure = $opts['smtp_secure']; // 'tls' | 'ssl'
        }
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = $opts['smtp_user']    ?? '';
        $this->mailer->Password   = $opts['smtp_pass']    ?? '';
        $this->mailer->setFrom($opts['from_email'] ?? 'no-reply@example.com', $opts['from_name'] ?? 'No-Reply');

        $this->baseUrl     = rtrim((string)($opts['base_url'] ?? ''), '/');
        $this->templatesDir = rtrim((string)($opts['templates_dir'] ?? __DIR__ . '/templates'), '/');
        $this->supportEmail = EmailCopy::SUPPORT_EMAIL;
        $this->appName      = EmailCopy::APP_NAME;

        // Por defecto enviamos HTML y texto plano
        $this->mailer->isHTML(true);
    }

    /* ===================== Public API ===================== */

    public function sendVerifyEmail(string $toEmail, string $toName, string $token): void
    {
        $verifyLink = $this->baseUrl . '/verify-email.php?token=' . urlencode($token);

        $subject = EmailCopy::SUBJECT_VERIFY_EMAIL;
        $html = $this->render('verify-email.html.php', [
            'app_name'     => $this->appName,
            'user_name'    => $toName,
            'verify_link'  => $verifyLink,
            'support_email'=> $this->supportEmail,
            'cta_text'     => EmailCopy::CTA_VERIFY,
            'expires_in'   => EmailCopy::VERIFY_LINK_EXPIRY,
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
        $signin = $this->baseUrl . '/sign-in.php';
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

    public function sendRejected(string $toEmail, string $toName, string $reason = ''): void
    {
        $subject = EmailCopy::SUBJECT_REJECTED;
        $html = $this->render('rejected.html.php', [
            'app_name'      => $this->appName,
            'user_name'     => $toName,
            'reason'        => $reason,
            'support_email' => $this->supportEmail,
        ]);
        $textReason = $reason ? " Reason: {$reason}" : '';
        $text = $this->toText("Hi {$toName}, your request was not approved.$textReason");

        $this->deliver($toEmail, $toName, $subject, $html, $text);
    }

    public function sendResetPassword(string $toEmail, string $toName, string $token): void
    {
        $reset = $this->baseUrl . '/reset-password.php?token=' . urlencode($token);
        $subject = EmailCopy::SUBJECT_RESET_PASSWORD;
        $html = $this->render('reset-password.html.php', [
            'app_name'      => $this->appName,
            'user_name'     => $toName,
            'reset_link'    => $reset,
            'support_email' => $this->supportEmail,
            'cta_text'      => EmailCopy::CTA_RESET,
        ]);
        $text = $this->toText("Hi {$toName}, reset your password: {$reset}");

        $this->deliver($toEmail, $toName, $subject, $html, $text);
    }

    /* ===================== Internals ===================== */

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
            // Aquí puedes loguear con tu logger central
            throw $e;
        }
    }

    private function render(string $templateName, array $vars): string
    {
        $file = $this->templatesDir . '/' . $templateName;
        if (!is_file($file)) {
            throw new \RuntimeException("Template not found: {$file}");
        }

        // Sustitución simple de placeholders {{var}}
        $contents = file_get_contents($file);
        $vars = array_merge([
            'app_name'      => $this->appName,
            'support_email' => $this->supportEmail,
        ], $vars);

        // Footer
        $footerHtml = str_replace('{{support_email}}', $this->supportEmail, EmailCopy::FOOTER_HTML);

        $map = [];
        foreach ($vars as $k => $v) {
            $map['{{' . $k . '}}'] = (string)$v;
        }
        $map['{{support_email}}'] = $this->supportEmail;
        $map['{{footer_html}}'] = $footerHtml;

        return strtr($contents, $map);
    }

    private function toText(string $primaryLine): string
    {
        $footer = str_replace('{{support_email}}', $this->supportEmail, EmailCopy::FOOTER_TEXT);
        return trim($primaryLine . "\n\n" . $footer);
    }
}
