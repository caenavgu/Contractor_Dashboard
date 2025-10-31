<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sign up — Success</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="<?= asset_url('css/auth.css') ?>" rel="stylesheet">
  <link href="<?= asset_url('css/bootstrap.min.css') ?>" rel="stylesheet">
</head>
<body class="auth-bg d-flex align-items-center justify-content-center min-vh-100">
  <div class="auth-card card shadow-sm">
    <div class="card-body p-4 text-center">
      <h1 class="h5 mb-3">Account created</h1>
      <p class="text-muted mb-4">
        We sent a verification email to your inbox. Please confirm your email to continue.
      </p>
      <a class="btn btn-brand" href="<?= route_url('/sign-in') ?>">Go to sign in</a>
    </div>
  </div>
</body>
</html>
