<?php
// public/views/partials/header.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$page_title = $page_title ?? 'Everwell Portal';
$body_class = $body_class ?? '';

$user_email = $_SESSION['user']['email'] ?? null;
$is_signed_in = !empty($_SESSION['user']['user_id'] ?? null);

// Aseguramos que exista token CSRF para el form de sign-out
ensure_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Dashboard · Contractor App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="<?= sanitize_string(base_url('/')) ?>">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= asset_url('css/dashboard.css') ?>">

    <script src="https://kit.fontawesome.com/1d7ca8a227.js" crossorigin="anonymous"></script>
</head>
<body class="<?= sanitize_string(trim($body_class)); ?>">

    <header class="ew-header-bar py-3 mb-4">
        <div class="container d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2">
                <!-- Cambia por tu logo si quieres -->
                <!-- <img src="<?= asset_url('/img/everwell-logo.svg'); ?>" alt="Everwell" height="32"> -->
                <span class="ew-logo-text text-danger">EVERWELL</span>
            </div>

            <div class="ew-header-metrics text-center text-md-start">
                <div class="fw-semibold">
                    Welcome<?= $user_email ? ' ' . sanitize_string($user_email) : ''; ?>
                </div>
                <div class="text-muted small">User Dashboard</div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <?php if ($is_signed_in): ?>
                    <form method="post"
                        action="<?= sanitize_string(route_url('/sign-out')); ?>"
                        class="m-0">
                        <input type="hidden" name="_csrf"
                            value="<?= sanitize_string(get_csrf_token()); ?>">
                        <button type="submit"
                                class="btn btn-link btn-sm text-decoration-none">
                            Sign out
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </header>
