<?php
// public/views/verify-email.php
// -------------------------------------------------------------
// Muestra el resultado de la verificación de email.
// Espera en $view:
//   - 'ok'       => bool
//   - 'message'  => string (mensaje para el usuario)
// -------------------------------------------------------------
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Email verification</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="<?= asset_url('css/bootstrap.min.css') ?>" rel="stylesheet">
  <link href="<?= asset_url('css/auth.css') ?>" rel="stylesheet">
</head>
<body class="auth-bg d-flex align-items-center justify-content-center min-vh-100">
  <div class="auth-card card shadow-sm">
    <div class="card-body p-4 text-center">
      <div class="mb-3">
        <img src="<?= asset_url('img/everwell_logo.png') ?>" alt="Everwell" width="120" height="auto">
      </div>

      <?php if (!empty($view['ok'])): ?>
        <h1 class="h5 mb-2">Email verified</h1>
        <p class="text-muted mb-4"><?= htmlspecialchars($view['message'] ?? 'Your email has been verified successfully.', ENT_QUOTES, 'UTF-8') ?></p>
        <a class="btn btn-brand" href="<?= route_url('/sign-in') ?>">Go to sign in</a>
      <?php else: ?>
        <h1 class="h5 mb-2">Verification failed</h1>
        <p class="text-muted mb-4"><?= htmlspecialchars($view['message'] ?? 'Invalid or expired verification link.', ENT_QUOTES, 'UTF-8') ?></p>
        <a class="btn btn-secondary" href="<?= route_url('/sign-in') ?>">Back to sign in</a>
      <?php endif; ?>
    </div>
  </div>

  <script src="<?= asset_url('js/app.js') ?>" defer></script>
</body>
</html>
