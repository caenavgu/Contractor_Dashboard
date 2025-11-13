<?php
// public/views/sign-up.php
// -------------------------------------------------------------
// Sign Up (Bootstrap limpio, colores por defecto).
// Cambios: se agrega "Confirm password" y se mantiene el layout anterior simple.
// - Technician phone requerido.
// - Contractor: phone/email/website + state dropdown desde usa_states.
// - Validación de confirmación de password (JS + server).
// -------------------------------------------------------------
declare(strict_types=1);

ensure_csrf_token();

$csrf    = $_SESSION['csrf_token'] ?? '';
$states  = $view['states'] ?? []; // [ ['state_code'=>'FL','state_name'=>'Florida'], ... ]
$err_msg = $view['error'] ?? null;

$action_url  = route_url('/sign-up');
$sign_in_url = route_url('/sign-in');
$logo_url    = asset_url('img/everwell_logo.png');
$signup_js   = asset_url('js/sign-up.js');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sign up · Contractor App</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <base href="<?= sanitize_string(base_url('/')) ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= asset_url('css/auth.css') ?>">
  <link rel="stylesheet" href="<?= asset_url('css/sign-up.css') ?>">
  <script src="https://kit.fontawesome.com/1d7ca8a227.js" crossorigin="anonymous"></script>
  <style>
    /* Estilo neutro: centrado, tarjeta simple, sin colores custom */
    .page-wrap{min-height:100vh;display:flex;align-items:flex-start;justify-content:center;background:#f8f9fa}
    .auth-card{width:100%;max-width:920px;border-radius:12px;margin-top:32px}
    .section-title{margin:16px 0 12px}
    .required::after{content:" *"; color:#dc3545;}
  </style>
</head>
<body class="page-wrap">
<div class="card auth-card shadow-sm">
  <div class="card-body p-4">

    <div class="text-center mb-3">
      <img src="<?= sanitize_string($logo_url) ?>" alt="Everwell" width="120" height="auto">
    </div>

    <h1 class="h5 text-center mb-3">Create your account</h1>

    <?php if (!empty($err_msg)): ?>
      <div class="alert alert-danger" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i><?= sanitize_string($err_msg) ?>
      </div>
    <?php endif; ?>

    <form id="sign-up-form" method="post" action="<?= $action_url ?>" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="_csrf" value="<?= sanitize_string($csrf) ?>">

      <!-- Technician Information -->
      <h2 class="h6 section-title">Technician information</h2>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label required" for="first_name">First name</label>
          <input type="text" class="form-control uppercase " id="first_name" name="first_name" maxlength="100" required>
        </div>
        <div class="col-md-6">
          <label class="form-label required" for="last_name">Last name</label>
          <input type="text" class="form-control uppercase " id="last_name" name="last_name" maxlength="100" required>
        </div>

        <div class="col-md-6">
          <label class="form-label required" for="email">Email (username)</label>
          <input type="email" class="form-control" id="email" name="email" maxlength="320" required autocomplete="username">
        </div>

        <div class="col-md-6">
          <label class="form-label required" for="phone_number">Phone number</label>
          <input type="tel" class="form-control" id="phone_number" name="phone_number" maxlength="30" required>
        </div>


        <div class="col-md-3">
          <label class="form-label required" for="password">Password</label>
          <input type="password" class="form-control" id="password" name="password" minlength="8" maxlength="64" required autocomplete="new-password">
        </div>
        <div class="col-md-3">
          <label class="form-label required" for="confirm_password">Confirm password</label>
          <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" maxlength="64" required autocomplete="new-password">
          <div class="invalid-feedback">Passwords do not match.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label required" for="certifying_organization">Certifying organization</label>
          <input type="text" class="form-control uppercase" id="certifying_organization" name="certifying_organization" maxlength="191" required>
        </div>
        <div class="col-md-6">
          <label class="form-label required" for="epa_certification_number">EPA Certification number</label>
          <input type="text" class="form-control uppercase" id="epa_certification_number" name="epa_certification_number" maxlength="100" required>
        </div>


        <div class="col-md-6">
          <label class="form-label required" for="epa_photo">EPA Certification photo (JPG/PNG/PDF)</label>
          <input type="file" class="form-control" id="epa_photo" name="epa_photo" accept=".jpg,.jpeg,.png,.pdf" required>
          <div class="form-text">Max 2MB. File will be renamed to <code>EPA_[license]_[YYYYMMDDhhmmss].ext</code>.</div>
        </div>
      </div>

      <hr class="my-4">

      <!-- Contractor Association -->
      <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" id="has_contractor" name="has_contractor" value="1" aria-describedby="contractor_help">
        <label class="form-check-label" for="has_contractor">I am associated with a contractor</label>
      </div>
      <div id="contractor_help" class="form-text mb-3">
        If enabled, please complete the contractor information below.
      </div>

      <div id="contractor-section" class="d-none">
        <h2 class="h6 section-title">Contractor information</h2>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label required" for="cac_license_number">CAC License number</label>
            <input type="text" class="form-control uppercase" id="cac_license_number" name="cac_license_number" maxlength="100">
          </div>
          <div class="col-md-6">
            <label class="form-label required" for="company_name">Company name</label>
            <input type="text" class="form-control uppercase" id="company_name" name="company_name" maxlength="191">
          </div>

          <div class="col-md-4">
            <label class="form-label" for="company_phone">Company phone</label>
            <input type="tel" class="form-control" id="company_phone" name="company_phone" maxlength="30">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="company_email">Company email</label>
            <input type="email" class="form-control" id="company_email" name="company_email" maxlength="320">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="company_website">Company website</label>
            <input type="url" class="form-control" id="company_website" name="company_website" maxlength="191" placeholder="https://">
          </div>

          <div class="col-md-8">
            <label class="form-label required" for="address">Address</label>
            <input type="text" class="form-control uppercase" id="address" name="address" maxlength="191">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="address_2">Address 2</label>
            <input type="text" class="form-control uppercase" id="address_2" name="address_2" maxlength="191">
          </div>

          <div class="col-md-6">
            <label class="form-label required" for="city">City</label>
            <input type="text" class="form-control uppercase" id="city" name="city" maxlength="100">
          </div>
          <div class="col-md-3">
            <label class="form-label required" for="state_code">State</label>
            <select class="form-select" id="state_code" name="state_code">
              <option value="">Select...</option>
              <?php foreach ($states as $st): ?>
                <option value="<?= sanitize_string($st['state_code']) ?>"><?= sanitize_string($st['state_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label required" for="zip_code">ZIP code</label>
            <input type="text" class="form-control" id="zip_code" name="zip_code" maxlength="20">
          </div>
        </div>
      </div>

      <div class="d-grid mt-4">
        <button class="btn btn-brand" type="submit">
          <i class="fa-solid fa-user-plus me-1"></i> Create account
        </button>
      </div>

      <p class="text-center small text-muted mt-2 mb-0">
        Already have an account?
        <a class="link-brand" href="<?= $sign_in_url ?>">Sign in</a>
      </p>
    </form>
  </div>
</div>
<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= sanitize_string($signup_js) ?>"></script>
</body>
</html>
