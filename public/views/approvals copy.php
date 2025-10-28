<?php
// public/views/approvals.php
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Approvals · Contractor App</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <base href="<?= sanitize_string(base_url('/')) ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset_url('css/app.css') ?>">
</head>
<body class="app-page bg-light">
  <main class="container py-4">
    <div class="dashboard-panel mx-auto" style="max-width: 980px;">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0">Approvals</h1>
        <a href="<?= route_url('/dashboard') ?>" class="btn btn-sm btn-outline-secondary">Back to dashboard</a>
      </div>

      <?php if (!empty($view['message'])): ?>
        <div class="alert alert-success"><?= sanitize_string($view['message']) ?></div>
      <?php endif; ?>
      <?php if (!empty($view['error'])): ?>
        <div class="alert alert-danger"><?= sanitize_string($view['error']) ?></div>
      <?php endif; ?>

      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">Pending Users</h5>
          <?php if (empty($view['pending_users'])): ?>
            <p class="text-muted small mb-0">No pending users.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>Email</th>
                    <th>Name</th>
                    <th>Verified at</th>
                    <th style="width:220px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($view['pending_users'] as $u): ?>
                  <tr>
                    <td><?= sanitize_string($u['email']) ?></td>
                    <td><?= sanitize_string(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?></td>
                    <td><?= sanitize_string($u['email_verified_at'] ?? '') ?></td>
                    <td>
                      <form class="d-inline" method="post" action="<?= route_url('/approvals') ?>">
                        <input type="hidden" name="action" value="approve_user">
                        <input type="hidden" name="user_id" value="<?= sanitize_string($u['user_id']) ?>">
                        <button class="btn btn-sm btn-success">Approve</button>
                      </form>
                      <button class="btn btn-sm btn-outline-danger ms-1" data-bs-toggle="modal" data-bs-target="#rejectModal" data-user-id="<?= sanitize_string($u['user_id']) ?>">Reject</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">Pending Contractor Stagings</h5>
          <?php if (empty($view['pending_stagings'])): ?>
            <p class="text-muted small mb-0">No pending stagings.</p>
          <?php else: ?>
            <?php foreach ($view['pending_stagings'] as $s): ?>
              <div class="border rounded p-2 mb-2">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <strong>#<?= (int)$s['staging_id'] ?></strong>
                    · CAC: <?= sanitize_string($s['input_cac_license_number']) ?>
                    · Created: <?= sanitize_string($s['created_at']) ?>
                  </div>
                  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#stg-<?= (int)$s['staging_id'] ?>">Open</button>
                </div>
                <div id="stg-<?= (int)$s['staging_id'] ?>" class="collapse mt-2">
                  <form method="post" action="<?= route_url('/approvals') ?>">
                    <input type="hidden" name="action" value="merge_staging">
                    <input type="hidden" name="staging_id" value="<?= (int)$s['staging_id'] ?>">
                    <div class="row">
                      <div class="col-md-6">
                        <h6>Existing Contractor</h6>
                        <?php
                          // búsqueda rápida del existente por CAC (solo para vista)
                          $existing = null;
                          try {
                            $cr = new ContractorRepository($pdo);
                            $existing = $cr->find_by_cac((string)$s['input_cac_license_number']);
                          } catch (\Throwable $e) { /* ignore */ }
                        ?>
                        <?php if ($existing): ?>
                          <div><strong>Company:</strong> <?= sanitize_string($existing['company_name'] ?? '') ?></div>
                          <div><strong>Address:</strong> <?= sanitize_string($existing['address'] ?? '') ?></div>
                          <div><strong>City/State/Zip:</strong> <?= sanitize_string(($existing['city'] ?? '') . ' / ' . ($existing['state_code'] ?? '') . ' / ' . ($existing['zip_code'] ?? '')) ?></div>
                        <?php else: ?>
                          <div class="text-muted">No existing contractor found (by CAC).</div>
                        <?php endif; ?>
                      </div>
                      <div class="col-md-6">
                        <h6>Staging Proposal (select to merge)</h6>
                        <?php
                          $fields = [
                            'input_company_name'   => 'Company',
                            'input_address'        => 'Address',
                            'input_address_2'      => 'Address 2',
                            'input_city'           => 'City',
                            'input_state_code'     => 'State code',
                            'input_zip_code'       => 'Zip',
                            'input_company_phone'  => 'Phone',
                            'input_company_email'  => 'Email',
                            'input_company_website'=> 'Website',
                          ];
                          foreach ($fields as $k => $label):
                              $val = $s[$k] ?? '';
                        ?>
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="apply_fields[]" value="<?= $k ?>" id="<?= $k . '-' . (int)$s['staging_id'] ?>" <?= $val ? 'checked' : '' ?>>
                            <label class="form-check-label" for="<?= $k . '-' . (int)$s['staging_id'] ?>">
                              <?= $label ?>: <?= sanitize_string($val) ?>
                            </label>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                    <div class="mt-3">
                      <button class="btn btn-sm btn-success">Merge selected fields</button>
                    </div>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <!-- Reject Modal -->
  <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" method="post" action="<?= route_url('/approvals') ?>">
        <input type="hidden" name="action" value="reject_user">
        <input type="hidden" name="user_id" id="reject-user-id" value="">
        <div class="modal-header">
          <h5 class="modal-title">Reject user</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Reason (optional)</label>
            <textarea class="form-control" name="reason" rows="3" placeholder="Your data could not be verified. Please contact Technical Support."></textarea>
          </div>
          <p class="small text-muted mb-0">The user will receive an email with this information.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-danger" type="submit">Reject</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Cargar user_id en el modal de rechazo
    (function(){
      const modal = document.getElementById('rejectModal');
      modal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        const uid = btn?.getAttribute('data-user-id') || '';
        document.getElementById('reject-user-id').value = uid;
      });
    })();
  </script>
</body>
</html>
<?php
// public/views/approvals.php
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Approvals · Contractor App</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <base href="<?= sanitize_string(base_url('/')) ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset_url('css/app.css') ?>">
</head>
<body class="app-page">
  <main class="container">
    <div class="dashboard-panel" style="max-width:900px;">
      <div class="card p-3 mb-3">
        <h5>Pending Users (verified email)</h5>
        <?php if (empty($pending_users)): ?><div class="text-muted small">No pending users.</div><?php endif; ?>
        <?php foreach ($pending_users as $u): ?>
          <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <div><?= sanitize_string($u['email']) ?> — <?= sanitize_string($u['first_name'].' '.$u['last_name']) ?></div>
            <form method="post" action="<?= route_url('/approvals/user/approve') ?>">
              <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
              <button class="btn btn-sm btn-outline-success">Approve</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="card p-3">
        <h5>Pending Contractor Stagings</h5>
        <?php if (empty($pending_stagings)): ?><div class="text-muted small">No pending stagings.</div><?php endif; ?>
        <?php foreach ($pending_stagings as $s): ?>
          <div class="mb-2 border rounded p-2">
            <div><strong>Staging #<?= (int)$s['staging_id'] ?></strong> — CAC: <?= sanitize_string($s['input_cac_license_number']) ?> — Created: <?= sanitize_string($s['created_at']) ?></div>
            <div class="mt-2">
              <a class="btn btn-sm btn-outline-primary" href="<?= route_url('/approvals') ?>?view_staging=<?= (int)$s['staging_id'] ?>">Open</a>
              <form method="post" action="<?= route_url('/approvals/staging/discard') ?>" style="display:inline-block;">
                <input type="hidden" name="staging_id" value="<?= (int)$s['staging_id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Discard</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($_GET['view_staging'])):
        $sid = (int)$_GET['view_staging'];
        $stg = $staging_repo->find_by_id($sid);
        if ($stg):
          $existing = $stg['existing_contractor_id'] ? $contractor_repo->find_by_cac($stg['input_cac_license_number']) : null;
      ?>
        <div class="card p-3 mt-3">
          <h5>Staging Detail #<?= $sid ?></h5>
          <form method="post" action="<?= route_url('/approvals/staging/merge') ?>">
            <input type="hidden" name="staging_id" value="<?= $sid ?>">
            <div class="row">
              <div class="col-md-6">
                <h6>Existing Contractor</h6>
                <?php if ($existing): ?>
                  <div><strong>Company:</strong> <?= sanitize_string($existing['company_name']) ?></div>
                  <div><strong>Address:</strong> <?= sanitize_string($existing['address']) ?></div>
                  <div><strong>City/State/Zip:</strong> <?= sanitize_string($existing['city'] . ' / ' . $existing['state_code'] . ' / ' . $existing['zip_code']) ?></div>
                <?php else: ?>
                  <div class="text-muted">No existing contractor found.</div>
                <?php endif; ?>
              </div>
              <div class="col-md-6">
                <h6>Staging Proposal</h6>
                <div><label><input type="checkbox" name="apply_fields[]" value="input_company_name" checked> Company: <?= sanitize_string($stg['input_company_name']) ?></label></div>
                <div><label><input type="checkbox" name="apply_fields[]" value="input_address" checked> Address: <?= sanitize_string($stg['input_address']) ?></label></div>
                <div><label><input type="checkbox" name="apply_fields[]" value="input_city" checked> City: <?= sanitize_string($stg['input_city']) ?></label></div>
                <div><label><input type="checkbox" name="apply_fields[]" value="input_state_code" checked> State code: <?= sanitize_string($stg['input_state_code']) ?></label></div>
                <div><label><input type="checkbox" name="apply_fields[]" value="input_zip_code" checked> Zip: <?= sanitize_string($stg['input_zip_code']) ?></label></div>
                <div><label><input type="checkbox" name="apply_fields[]" value="input_company_phone" checked> Phone: <?= sanitize_string($stg['input_company_phone']) ?></label></div>
                <div><label><input type="checkbox" name="apply_fields[]" value="input_company_email" checked> Email: <?= sanitize_string($stg['input_company_email']) ?></label></div>
                <div><label><input type="checkbox" name="apply_fields[]" value="input_company_website" checked> Website: <?= sanitize_string($stg['input_company_website']) ?></label></div>
              </div>
            </div>

            <div class="mt-3">
              <button class="btn btn-success">Merge selected fields</button>
            </div>
          </form>
        </div>
      <?php endif; endif; ?>

    </div>
  </main>
</body>
</html>
