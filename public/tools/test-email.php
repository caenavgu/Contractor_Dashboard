<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

$to = $_GET['to'] ?? ($GLOBALS['app_config']['smtp_username'] ?? '');
$ok = send_mail($to, 'SMTP test - Contractor App', '<p>This is a test email.</p>');

header('Content-Type: text/plain; charset=utf-8');
echo $ok ? "OK: sent to {$to}\n" : "ERROR: check storage/logs/mail.log\n";
