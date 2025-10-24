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
