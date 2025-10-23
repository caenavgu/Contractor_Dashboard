<?php
// public/views/sign_in.php
// Vista de Sign In (UI en inglés) con URL base y action correcto
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sign In · Contractor App</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= sanitize_string($_SESSION['csrf_token'] ?? '') ?>">

  <!-- IMPORTANTE: base para que todo (links/recursos) respete /contractor.everwell-ac.com/public -->
  <base href="<?= sanitize_string(base_url('/')) ?>">

  <!-- Bootstrap + CSS propio -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset_url('css/auth.css') ?>">
</head>
<body class="auth-page">

  <main class="container">
    <div class="auth-card card shadow-sm">
      <div class="card-body p-4">
        <div class="text-center">
          <img src="<?= asset_url('img/everwell_logo.png') ?>" alt="Everwell" class="brand-logo" width="120" height="auto">
        </div>

        <h1 class="h5 text-center mb-3">Log in</h1>

        <?php if (!empty($view['general_error'])): ?>
          <div class="alert alert-danger" role="alert">
            <?= sanitize_string($view['general_error']) ?>
          </div>
        <?php endif; ?>

        <!-- ACTION CORRECTO: usa route_url('/sign-in') -->
        <form id="sign-in-form" method="post" action="<?= route_url('/sign-in') ?>" novalidate>
          <input type="hidden" name="_csrf" value="<?= sanitize_string($_SESSION['csrf_token'] ?? '') ?>">

          <div class="mb-3">
            <label for="email" class="form-label">Username</label>
            <input
              type="email"
              class="form-control form-control-sm <?= $view['field_errors']['email'] ? 'is-invalid' : '' ?>"
              id="email"
              name="email"
              placeholder="name@example.com"
              value="<?= sanitize_string($view['values']['email'] ?? '') ?>"
              required maxlength="320" autocomplete="username">
            <?php if (!empty($view['field_errors']['email'])): ?>
              <div class="invalid-feedback"><?= sanitize_string($view['field_errors']['email']) ?></div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group input-group-sm">
              <input
                type="password"
                class="form-control <?= $view['field_errors']['password'] ? 'is-invalid' : '' ?>"
                id="password" name="password" placeholder="••••••••"
                required minlength="8" maxlength="64" autocomplete="current-password" aria-describedby="toggle-password">
              <button class="btn btn-outline-secondary" type="button" id="toggle-password" aria-label="Show password"><span aria-hidden="true">👁️</span></button>
              <?php if (!empty($view['field_errors']['password'])): ?>
                <div class="invalid-feedback d-block"><?= sanitize_string($view['field_errors']['password']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="remember-me" name="remember_me" <?= !empty($view['values']['remember_me']) ? 'checked' : '' ?>>
            <label for="remember-me" class="form-check-label">Remember me</label>
          </div>

          <div class="d-grid mb-2">
            <button class="btn btn-brand" id="btn-sign-in" type="submit">Sign in</button>
          </div>
        <div class="mt-3 text-center">
          
        </div>
          <p class="text-center small text-muted mb-0">
            Forgot your password?
            <a class="link-brand" href="<?= route_url('/forgot-password') ?>">Reset it</a>
          </p>
          <p class="text-center small text-muted mb-0">
            Don’t have an account?
            <a class="link-brand" href="<?= route_url('/sign-up') ?>">Sign up</a>
          </p>
        </form>
      </div>
    </div>
  </main>

  <script src="<?= asset_url('js/sign-in.js') ?>" defer></script>
</body>
</html>