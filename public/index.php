<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user_repo    = new UserRepository($pdo);
$session_repo = new SessionRepository($pdo);
$audit_repo   = new AuditLogRepository($pdo);
$auth_srv     = new AuthService($user_repo, $session_repo, $audit_repo);
$signin       = new SignInPresenter($auth_srv);

$uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

/* --- SIGN IN --- */
if ($uri_path === route_url('/sign-in') || $uri_path === '/sign-in') {
    $view = $_SERVER['REQUEST_METHOD'] === 'POST' ? $signin->handle_post($pdo) : $signin->handle_get();
    if (!empty($view['_redirect'])) {
        header('Location: ' . route_url($view['_redirect']), true, 302);
        exit;
    }
    require __DIR__ . '/views/sign_in.php';
    exit;
}

/* --- DASHBOARD --- */
if ($uri_path === route_url('/dashboard') || $uri_path === '/dashboard') {
    // (más adelante) aquí validaremos la sesión, por ahora solo vista
    require __DIR__ . '/views/dashboard.php';
    exit;
}

/* --- HOME / FALLBACK --- */
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Contractor App</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <base href="<?= sanitize_string(base_url('/')) ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <h1 class="mb-3">Contractor App</h1>
  <p class="text-muted">Go to <a href="<?= route_url('/sign-in') ?>">Sign In</a>.</p>
</div>
</body>
</html>
