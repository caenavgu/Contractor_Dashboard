<?php
// public/views/sign_up.php
// -------------------------------------------------------------
// Vista Sign Up (Bootstrap, multi-step simple).
// -------------------------------------------------------------
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sign Up · Contractor App</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <base href="<?= sanitize_string(base_url('/')) ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset_url('css/auth.css') ?>">
</head>
<body class="auth-page">
  <main class="container">
    <div class="auth-card card shadow-sm">
      <div class="card-body p-4">
        <div class="text-center mb-3">
          <img src="<?= asset_url('img/everwell_logo.png') ?>" alt="Everwell" class="brand-logo" width="120">
        </div>

        <h1 class="h5 text-center mb-3">Create your account</h1>

        <?php if (!empty($view['general_error'])): ?>
          <div class="alert alert-danger"><?= sanitize_string($view['general_error']) ?></div>
        <?php endif; ?>
        <?php if (!empty($view['success_message'])): ?>
          <div class="alert alert-success"><?= sanitize_string($view['success_message']) ?></div>
        <?php endif; ?>

        <form id="sign-up-form" method="post" action="<?= route_url('/sign-up') ?>" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="_csrf" value="<?= sanitize_string($_SESSION['csrf_token'] ?? '') ?>">

          <!-- SECTION: Technician -->
          <div id="section-tech">
            <div class="mb-2"><strong>Technician information</strong></div>

            <div class="mb-2">
              <label class="form-label">First name</label>
              <input name="first_name" class="form-control form-control-sm" value="<?= sanitize_string($view['values']['first_name'] ?? '') ?>">
            </div>

            <div class="mb-2">
              <label class="form-label">Last name</label>
              <input name="last_name" class="form-control form-control-sm" value="<?= sanitize_string($view['values']['last_name'] ?? '') ?>">
            </div>

            <div class="mb-2">
              <label class="form-label">Email</label>
              <input name="email" type="email" class="form-control form-control-sm <?= !empty($view['field_errors']['email']) ? 'is-invalid' : '' ?>" value="<?= sanitize_string($view['values']['email'] ?? '') ?>">
              <?php if (!empty($view['field_errors']['email'])): ?><div class="invalid-feedback"><?= sanitize_string($view['field_errors']['email']) ?></div><?php endif; ?>
            </div>

            <div class="mb-2">
              <label class="form-label">Password</label>
              <input name="password" type="password" class="form-control form-control-sm <?= !empty($view['field_errors']['password']) ? 'is-invalid' : '' ?>">
              <?php if (!empty($view['field_errors']['password'])): ?><div class="invalid-feedback"><?= sanitize_string($view['field_errors']['password']) ?></div><?php endif; ?>
            </div>

            <div class="mb-2">
              <label class="form-label">EPA Certification Number</label>
              <input name="epa_certification_number" class="form-control form-control-sm" value="<?= sanitize_string($view['values']['epa_certification_number'] ?? '') ?>">
            </div>

            <div class="mb-2">
              <label class="form-label">Certifying organization</label>
              <input name="certifying_organization" class="form-control form-control-sm" value="<?= sanitize_string($view['values']['certifying_organization'] ?? '') ?>">
            </div>

            <div class="mb-2">
              <label class="form-label">EPA Photo (JPG/PNG/PDF, ≤ 2MB)</label>
              <input name="epa_photo" type="file" accept=".jpg,.jpeg,.png,.pdf" class="form-control form-control-sm">
            </div>

            <div class="mb-3 form-check">
              <input type="checkbox" class="form-check-input" id="associated-with-contractor" name="associated_with_contractor" value="yes" <?= !empty($view['values']['associated_with_contractor']) ? 'checked' : '' ?>>
              <label for="associated-with-contractor" class="form-check-label">I am associated with a contractor</label>
            </div>
          </div>

          <!-- SECTION: Contractor (hidden until checkbox checked) -->
          <div id="section-contractor" style="display:none;">
            <hr>
            <div class="mb-2"><strong>Contractor information</strong></div>

            <div class="mb-2">
              <label class="form-label">CAC License Number</label>
              <input name="cac_license_number" class="form-control form-control-sm" value="<?= sanitize_string($view['values']['cac_license_number'] ?? '') ?>">
            </div>

            <div class="mb-2">
              <label class="form-label">Company name</label>
              <input name="company_name" class="form-control form-control-sm" value="<?= sanitize_string($view['values']['company_name'] ?? '') ?>">
            </div>

            <div class="mb-2">
              <label class="form-label">Address</label>
              <input name="address" class="form-control form-control-sm" value="<?= sanitize_string($view['values']['address'] ?? '') ?>">
            </div>

            <div class="mb-2">
              <label class="form-label">City</label>
              <input name="city" class="form-control form-control-sm" value="<?= sanitize_string($view['values']['city'] ?? '') ?>">
            </div>

            <div class="mb-2">
              <label class="form-label">State code</label>
              <input name="state_code" class="form-control form-control-sm" value="<?= sanitize_string($view['values']['state_code'] ?? '') ?>">
            </div>

            <div class="mb-2">
              <label class="form-label">Zip code</label>
              <input name="zip_code" class="form-control form-control-sm" value="<?= sanitize_string($view['values']['zip_code'] ?? '') ?>">
            </div>
          </div>

          <div class="d-grid mt-3">
            <button class="btn btn-brand" type="submit">Submit</button>
          </div>
        </form>
      </div>
    </div>
  </main>

  <script>
    // Simple JS para toggle contractor block
    (function(){
      const cb = document.getElementById('associated-with-contractor');
      const sec = document.getElementById('section-contractor');
      function update() {
        sec.style.display = cb.checked ? 'block' : 'none';
      }
      cb && cb.addEventListener('change', update);
      document.addEventListener('DOMContentLoaded', update);
    })();
  </script>
</body>
</html>
