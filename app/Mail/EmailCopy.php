<?php
declare(strict_types=1);

namespace App\Mail;

/**
 * Centraliza subjects y frases breves (microcopy) para emails.
 * Cambia aquí los textos sin tocar plantillas ni lógica.
 *
 * Placeholders disponibles en templates:
 *  {{app_name}}, {{user_name}}, {{verify_link}}, {{signin_link}},
 *  {{approval_link}}, {{reset_link}}, {{reason}}, {{support_email}}
 */
final class EmailCopy
{
    // App-wide defaults
    public const APP_NAME = 'Everwell Contractor Portal';
    public const SUPPORT_EMAIL = 'support@everwell-ac.com';
    public const VERIFY_LINK_EXPIRY = '7 days';

    // Subjects
    public const SUBJECT_VERIFY_EMAIL     = 'Verify your email';
    public const SUBJECT_ADMIN_APPROVAL   = 'New user pending approval';
    public const SUBJECT_APPROVED         = 'Your account has been approved';
    public const SUBJECT_REJECTED         = 'Your account request was not approved';
    public const SUBJECT_RESET_PASSWORD   = 'Reset your password';

    // Short microcopy (puedes reutilizar en varias plantillas)
    public const CTA_VERIFY      = 'Verify Email';
    public const CTA_SIGN_IN     = 'Sign In';
    public const CTA_REVIEW_USER = 'Review User';
    public const CTA_RESET       = 'Reset Password';

    // Footer (se inserta al final de cada email HTML)
    public const FOOTER_HTML = '<p style="margin-top:24px;font-size:12px;color:#666">
        If you didn’t request this, you can ignore this message. Need help? Contact us at {{support_email}}.
    </p>';

    // Plaintext footnote
    public const FOOTER_TEXT = "If you didn’t request this, ignore this message. Help: {{support_email}}";
}
