<?php
// public/views/profile.php
 declare(strict_types=1);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $user_email = $_SESSION['user']['email'] ?? null;
    $is_signed_in = !empty($_SESSION['user']['user_id'] ?? null);

    /** @var array<string,mixed> $session_user */
    /** @var array<string,mixed>|null $user_details */
    /** @var array<string,mixed>|null $contractor */
    /** @var array<string,mixed> $verification */

    $page_title = 'My Profile – Contractor Portal';
    $body_class = 'dashboard-body';

    $user_name       = (string)($session_user['name']            ?? '');
    $user_email      = (string)($session_user['email']           ?? '');
    $user_type_label = (string)($session_user['user_type_label'] ?? '');
    $company_name    = (string)($session_user['company_name']    ?? '');

    $ud = $user_details ?? [];
    $ct = $contractor   ?? [];

    $has_name             = !empty($verification['has_name']);
    $has_epa_number       = !empty($verification['has_epa_number']);
    $has_epa_photo        = !empty($verification['has_epa_photo']);
    $is_epa_required      = !empty($verification['is_epa_required']);
    $is_epa_ok            = !empty($verification['is_epa_ok']);
    $has_contractor       = !empty($verification['has_contractor']);
    $has_cac_license      = !empty($verification['has_cac_license']);
    $is_contractor_active = !empty($verification['is_contractor_active']);
    $is_cac_verified      = !empty($verification['is_cac_verified']);
    $is_profile_verified  = !empty($verification['is_profile_verified']);
    
    ensure_csrf_token();
?>
<!DOCTYPE html>
  <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Approvals · Contractor App</title>
    
        <base href="<?= sanitize_string(base_url('/')) ?>">

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="<?= asset_url('css/styles.css') ?>">
        <!-- <link rel="stylesheet" href="<?= asset_url('css/approvals.css')?>"> -->

        <script src="https://kit.fontawesome.com/1d7ca8a227.js" crossorigin="anonymous"></script>
    </head>
    <?php 
        // public/views/partials/header.php
        require __DIR__ . '/partials/header.php'; 
    ?>
    <div class="dashboard-content">
    <div class="container mb-5">

        <!-- Título + estado de verificación -->
        <div class="card ew-card ew-card-shadow mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
            <h1 class="h4 mb-1">My Profile</h1>
            <p class="text-muted mb-0 small">
                Review your personal information, EPA certification and contractor details.
            </p>
            </div>

            <div class="text-md-end">
            <?php if ($is_profile_verified): ?>
                <span class="badge bg-success ew-badge-pill me-2">
                <i class="fa-solid fa-circle-check me-1"></i> Profile verified
                </span>
            <?php else: ?>
                <span class="badge bg-warning text-dark ew-badge-pill me-2">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> Profile not fully verified
                </span>
            <?php endif; ?>

            <div class="small text-muted">
                <?= sanitize_string($user_type_label !== '' ? $user_type_label : 'User'); ?>
                <?= $company_name !== '' ? ' · ' . sanitize_string($company_name) : ''; ?>
            </div>
            </div>
        </div>
        </div>

        <div class="row g-4">

        <!-- Datos personales -->
        <div class="col-lg-6">
            <div class="card ew-card ew-card-shadow h-100">
            <div class="card-header bg-white">
                <span class="fw-semibold">Personal information</span>
            </div>
            <div class="card-body">

                <dl class="row mb-0">
                <dt class="col-sm-4 text-muted small text-uppercase">Name</dt>
                <dd class="col-sm-8">
                    <?php
                    $fallback_name = trim(
                        (string)($ud['first_name'] ?? '') . ' ' .
                        (string)($ud['last_name'] ?? '')
                    );
                    $display_name = $user_name !== '' ? $user_name : $fallback_name;
                    echo $display_name !== ''
                        ? sanitize_string($display_name)
                        : '<span class="text-muted">Not provided</span>';
                    ?>
                </dd>

                <dt class="col-sm-4 text-muted small text-uppercase">Email</dt>
                <dd class="col-sm-8">
                    <?= $user_email !== ''
                        ? sanitize_string($user_email)
                        : '<span class="text-muted">Not provided</span>'; ?>
                </dd>

                <dt class="col-sm-4 text-muted small text-uppercase">Phone</dt>
                <dd class="col-sm-8">
                    <?= !empty($ud['phone_number'])
                        ? sanitize_string((string)$ud['phone_number'])
                        : '<span class="text-muted">Not provided</span>'; ?>
                </dd>
                </dl>

            </div>
            </div>
        </div>

        <!-- EPA -->
        <div class="col-lg-6">
            <div class="card ew-card ew-card-shadow h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">EPA Certification</span>
                <?php if ($is_epa_ok && $has_epa_number): ?>
                <span class="badge bg-success ew-badge-pill">
                    <i class="fa-solid fa-circle-check me-1"></i> EPA OK
                </span>
                <?php elseif ($is_epa_required): ?>
                <span class="badge bg-warning text-dark ew-badge-pill">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Required
                </span>
                <?php else: ?>
                <span class="badge bg-secondary ew-badge-pill">
                    <i class="fa-solid fa-circle-question me-1"></i> Optional
                </span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                <dt class="col-sm-4 text-muted small text-uppercase">EPA number</dt>
                <dd class="col-sm-8">
                    <?= $has_epa_number
                        ? sanitize_string((string)$ud['epa_certification_number'])
                        : '<span class="text-muted">Not provided</span>'; ?>
                </dd>

                <dt class="col-sm-4 text-muted small text-uppercase">Organization</dt>
                <dd class="col-sm-8">
                    <?= !empty($ud['certifying_organization'])
                        ? sanitize_string((string)$ud['certifying_organization'])
                        : '<span class="text-muted">Not provided</span>'; ?>
                </dd>

                <dt class="col-sm-4 text-muted small text-uppercase">EPA photo</dt>
                <dd class="col-sm-8">
                    <?php if ($has_epa_photo): ?>
                    <?php $epa_photo_url = (string)$ud['epa_photo_url']; ?>
                    <div class="d-flex flex-column">
                        <div class="mb-2">
                        <span class="badge bg-success ew-badge-pill">
                            <i class="fa-solid fa-image me-1"></i> Photo on file
                        </span>
                        </div>
                        <!-- Si epa_photo_url es una ruta relativa, el <base> del header ya ayuda -->
                        <div class="border rounded p-2 bg-light">
                        <img src="<?= sanitize_string($epa_photo_url); ?>"
                            alt="EPA ID photo"
                            class="img-fluid"
                            style="max-height: 160px; object-fit: contain;">
                        </div>
                    </div>
                    <?php else: ?>
                    <span class="text-muted">No photo on file</span>
                    <?php endif; ?>
                </dd>
                </dl>
            </div>
            </div>
        </div>

        <!-- Contractor / CAC -->
        <div class="col-12">
            <div class="card ew-card ew-card-shadow">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Contractor information</span>

                <?php if (!$has_contractor): ?>
                <span class="badge bg-secondary ew-badge-pill">
                    <i class="fa-solid fa-circle-question me-1"></i> No contractor linked
                </span>
                <?php else: ?>
                <?php if ($is_cac_verified): ?>
                    <span class="badge bg-success ew-badge-pill">
                    <i class="fa-solid fa-circle-check me-1"></i> CAC verified
                    </span>
                <?php elseif ($has_cac_license): ?>
                    <span class="badge bg-warning text-dark ew-badge-pill">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> CAC pending
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary ew-badge-pill">
                    <i class="fa-solid fa-circle-question me-1"></i> CAC info missing
                    </span>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="card-body">
                <?php if (!$has_contractor): ?>
                <p class="text-muted mb-0">
                    No contractor information associated with this account.
                </p>
                <?php else: ?>
                <dl class="row mb-0">
                    <dt class="col-sm-3 text-muted small text-uppercase">Company name</dt>
                    <dd class="col-sm-9">
                    <?= !empty($ct['company_name'])
                        ? sanitize_string((string)$ct['company_name'])
                        : '<span class="text-muted">Not provided</span>'; ?>
                    </dd>

                    <dt class="col-sm-3 text-muted small text-uppercase">CAC license</dt>
                    <dd class="col-sm-9">
                    <?= !empty($ct['cac_license_number'])
                        ? sanitize_string((string)$ct['cac_license_number'])
                        : '<span class="text-muted">Not provided</span>'; ?>
                    </dd>

                    <dt class="col-sm-3 text-muted small text-uppercase">Address</dt>
                    <dd class="col-sm-9">
                    <?php
                    $addr_lines = [];
                    if (!empty($ct['address'])) {
                        $addr_lines[] = (string)$ct['address'];
                    }
                    if (!empty($ct['address_2'])) {
                        $addr_lines[] = (string)$ct['address_2'];
                    }

                    $city_line = trim(
                        ((string)($ct['city'] ?? '')) . ', ' .
                        ((string)($ct['state_code'] ?? '')) . ' ' .
                        (string)($ct['zip_code'] ?? '')
                    );

                    if ($city_line !== ',  ') {
                        $addr_lines[] = $city_line;
                    }

                    if (empty($addr_lines)) {
                        echo '<span class="text-muted">Not provided</span>';
                    } else {
                        echo nl2br(sanitize_string(implode("\n", $addr_lines)));
                    }
                    ?>
                    </dd>

                    <?php if (!empty($ct['company_phone'])): ?>
                    <dt class="col-sm-3 text-muted small text-uppercase">Phone</dt>
                    <dd class="col-sm-9">
                        <?= sanitize_string((string)$ct['company_phone']); ?>
                    </dd>
                    <?php endif; ?>

                    <?php if (!empty($ct['company_email'])): ?>
                    <dt class="col-sm-3 text-muted small text-uppercase">Email</dt>
                    <dd class="col-sm-9">
                        <?= sanitize_string((string)$ct['company_email']); ?>
                    </dd>
                    <?php endif; ?>

                    <?php if (!empty($ct['company_website'])): ?>
                    <dt class="col-sm-3 text-muted small text-uppercase">Website</dt>
                    <dd class="col-sm-9">
                        <a href="<?= sanitize_string((string)$ct['company_website']); ?>"
                        target="_blank" rel="noopener">
                        <?= sanitize_string((string)$ct['company_website']); ?>
                        </a>
                    </dd>
                    <?php endif; ?>

                    <?php if (!empty($ct['approved_at'])): ?>
                    <dt class="col-sm-3 text-muted small text-uppercase">Approved at</dt>
                    <dd class="col-sm-9">
                        <?= sanitize_string((string)$ct['approved_at']); ?>
                        <?php if ($is_contractor_active): ?>
                        <span class="badge bg-success ew-badge-pill ms-2">Active</span>
                        <?php else: ?>
                        <span class="badge bg-secondary ew-badge-pill ms-2">Inactive</span>
                        <?php endif; ?>
                    </dd>
                    <?php endif; ?>
                </dl>
                <?php endif; ?>
            </div>
            </div>
        </div>

        </div><!-- row -->

    </div><!-- container -->
    </div><!-- dashboard-content -->

        <?php require __DIR__ . '/partials/footer.php'; ?>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
      <!-- <script src="<?=  sanitize_string($approvals_js) ?>"></script> -->
    </body>
  </html>