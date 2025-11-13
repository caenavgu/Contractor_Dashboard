<?php
// public/views/sign-in.php
declare(strict_types=1);

/**
 * Esta vista puede ser llamada sin $view definido.
 * Normalizamos para evitar warnings.
 */
$view = isset($view) && is_array($view) ? $view : [];

ensure_csrf_token();

$csrf         = $_SESSION['csrf_token'] ?? '';
$remember_me  = !empty($view['old']['remember_me']);
// Normalizar variables por si vienen sin definir
$error_msg     = $error_msg     ?? null;
$invalid_field = $invalid_field ?? null;
$old           = $old           ?? ['email' => ''];



$action_url   = route_url('/sign-in');
$sign_up_url  = route_url('/sign-up');
$logo_url     = asset_url('img/everwell_logo.png');
$signin_js    = asset_url('js/sign-in.js');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sign in · Contractor App</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <base href="<?= sanitize_string(base_url('/')) ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    .page-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f8f9fa}
    .auth-card{width:100%;max-width:420px;border-radius:12px}
    .brand-logo{filter: saturate(1);}
    .btn-brand{background:#e53935;border-color:#e53935;color:#fff;}
    .btn-brand:hover{background:#c62828;border-color:#c62828;color:#fff;}
    .link-brand{color:#e53935;text-decoration:none}
    .link-brand:hover{text-decoration:underline}
  </style>
</head>
<body class="page-wrap">
  <div class="card auth-card shadow-sm">
    <div class="card-body p-4">
      <div class="text-center mb-3">
        <img src="<?= sanitize_string($logo_url) ?>" alt="Everwell" class="brand-logo" width="120" height="auto">
      </div>

      <h1 class="h5 text-center mb-3">Log in</h1>

      <?php if ($error_msg): ?>
        <div class="alert alert-danger" role="alert">
          <?= sanitize_string($error_msg) ?>
        </div>
      <?php endif; ?>

      <form id="sign-in-form" method="post" action="<?= $action_url ?>" novalidate>
        <input type="hidden" name="_csrf" value="<?= sanitize_string($csrf) ?>">

        <div class="mb-3">
          <label for="email" class="form-label">Username</label>
          <input
            type="email"
            class="form-control"
            id="email"
            name="email"
            placeholder="name@example.com"
            value="<?= sanitize_string($old_email) ?>"
            required
            maxlength="320"
            autocomplete="username"
          >
          <div class="invalid-feedback">Please enter a valid email address.</div>
        </div>

        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <div class="input-group input-group-sm">
            <input
              type="password"
              class="form-control"
              id="password"
              name="password"
              placeholder="••••••••"
              required
              minlength="8"
              maxlength="64"
              autocomplete="current-password"
              aria-describedby="toggle-password"
            >
            <button class="btn btn-outline-secondary" type="button" id="toggle-password" aria-label="Show password">
              <span aria-hidden="true">👁️</span>
            </button>
          </div>
        </div>

        <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="remember-me" name="remember_me" <?= $remember_me ? 'checked' : '' ?>>
          <label for="remember-me" class="form-check-label">Remember me</label>
        </div>

        <div class="d-grid mb-2">
          <button class="btn btn-brand" id="btn-sign-in" type="submit">Sign in</button>
        </div>

        <p class="text-center small text-muted mb-0">
          Don’t have an account?
          <a class="link-brand" href="<?= $sign_up_url ?>">Sign up</a>
        </p>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= sanitize_string($signin_js) ?>"></script>
</body>
</html>
