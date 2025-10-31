<?php
// public/views/approvals.php
// -------------------------------------------------------------
// UI de aprobaciones (Bootstrap 5)
// - Secciones:
//   1) Pending Users
//   2) Pending Contractors
//   3) Contractor Conflicts (Staging) -> Merge / Keep
// -------------------------------------------------------------
declare(strict_types=1);

// Datos esperados del presenter:
$pending_users        = $view['pending_users']        ?? [];
$pending_contractors  = $view['pending_contractors']  ?? [];
$pending_staging      = $view['pending_staging']      ?? [];

// Mensajes (opcional)
$flash_message = $view['message'] ?? null;

ensure_csrf_token();
$csrf = $_SESSION['csrf_token'] ?? '';
$action_url = route_url('/approvals');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Approvals · Contractor App</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <base href="<?= sanitize_string(base_url('/')) ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .section-title{ margin-top: 16px; margin-bottom: 8px; }
    .table td, .table th { vertical-align: middle; }
    .diff-box { background:#f8f9fa; border:1px solid #dee2e6; border-radius:8px; padding:12px; }
  </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <h1 class="h4 mb-3">Approvals</h1>

  <?php if (!empty($flash_message)): ?>
    <div class="alert alert-info"><?= sanitize_string($flash_message) ?></div>
  <?php endif; ?>

  <!-- 1) Pending Users -->
  <h2 class="h5 section-title">Pending Users</h2>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead>
        <tr>
          <th>User ID</th>
          <th>Email</th>
          <th>Type</th>
          <th>Contractor ID</th>
          <th>Email Verified</th>
          <th>Status</th>
          <th width="180">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$pending_users): ?>
        <tr><td colspan="7" class="text-muted">No pending users.</td></tr>
      <?php else: foreach ($pending_users as $u): ?>
        <tr>
          <td><?= sanitize_string($u['user_id']) ?></td>
          <td><?= sanitize_string($u['email']) ?></td>
          <td><?= sanitize_string($u['user_type']) ?></td>
          <td><?= sanitize_string((string)($u['contractor_id'] ?? '')) ?></td>
          <td><?= sanitize_string((string)($u['email_verified_at'] ?? '')) ?></td>
          <td><span class="badge text-bg-warning"><?= sanitize_string($u['status']) ?></span></td>
          <td>
            <form class="d-inline" method="post" action="<?= $action_url ?>">
              <input type="hidden" name="_csrf" value="<?= sanitize_string($csrf) ?>">
              <input type="hidden" name="user_id" value="<?= sanitize_string($u['user_id']) ?>">
              <button name="action" value="approve_user" class="btn btn-sm btn-success">Approve</button>
            </form>
            <form class="d-inline" method="post" action="<?= $action_url ?>" onsubmit="return confirmReject(this);">
              <input type="hidden" name="_csrf" value="<?= sanitize_string($csrf) ?>">
              <input type="hidden" name="user_id" value="<?= sanitize_string($u['user_id']) ?>">
              <input type="hidden" name="reason" value="">
              <button name="action" value="reject_user" class="btn btn-sm btn-danger">Reject</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- 2) Pending Contractors -->
  <h2 class="h5 section-title">Pending Contractors</h2>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>CAC</th>
          <th>Company</th>
          <th>Phone</th>
          <th>Email</th>
          <th>City/State</th>
          <th>Status</th>
          <th width="220">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$pending_contractors): ?>
        <tr><td colspan="8" class="text-muted">No pending contractors.</td></tr>
      <?php else: foreach ($pending_contractors as $c): ?>
        <tr>
          <td><?= (int)$c['contractor_id'] ?></td>
          <td><?= sanitize_string($c['cac_license_number']) ?></td>
          <td><?= sanitize_string($c['company_name']) ?></td>
          <td><?= sanitize_string((string)$c['company_phone']) ?></td>
          <td><?= sanitize_string((string)$c['company_email']) ?></td>
          <td><?= sanitize_string($c['city']) ?>/<?= sanitize_string($c['state_code']) ?></td>
          <td><span class="badge text-bg-warning"><?= sanitize_string($c['status']) ?></span></td>
          <td>
            <form class="d-inline" method="post" action="<?= $action_url ?>">
              <input type="hidden" name="_csrf" value="<?= sanitize_string($csrf) ?>">
              <input type="hidden" name="contractor_id" value="<?= (int)$c['contractor_id'] ?>">
              <button name="action" value="approve_contractor" class="btn btn-sm btn-success">Approve</button>
            </form>
            <form class="d-inline" method="post" action="<?= $action_url ?>" onsubmit="return confirmReject(this);">
              <input type="hidden" name="_csrf" value="<?= sanitize_string($csrf) ?>">
              <input type="hidden" name="contractor_id" value="<?= (int)$c['contractor_id'] ?>">
              <input type="hidden" name="reason" value="">
              <button name="action" value="reject_contractor" class="btn btn-sm btn-danger">Reject</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- 3) Contractor Conflicts (Staging) -->
  <h2 class="h5 section-title">Contractor Conflicts (Staging)</h2>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead>
        <tr>
          <th>Staging ID</th>
          <th>CAC</th>
          <th>Company (staged)</th>
          <th>Phone / Email</th>
          <th>Address</th>
          <th width="260">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$pending_staging): ?>
        <tr><td colspan="6" class="text-muted">No conflicts pending.</td></tr>
      <?php else: foreach ($pending_staging as $s): ?>
        <?php
          // Intentamos localizar el contractor existente por CAC
          // (si se insertó staging por duplicado de CAC)
          $existing_path = route_url('/approvals'); // no necesitamos URL; solo render
        ?>
        <tr>
          <td><?= (int)$s['staging_id'] ?></td>
          <td><?= sanitize_string($s['cac_license_number']) ?></td>
          <td><?= sanitize_string($s['company_name']) ?></td>
          <td><?= sanitize_string((string)$s['company_phone']) ?><br><?= sanitize_string((string)$s['company_email']) ?></td>
          <td>
            <div class="small">
              <?= sanitize_string($s['address']) ?> <?= sanitize_string((string)$s['address_2']) ?><br>
              <?= sanitize_string($s['city']) ?>, <?= sanitize_string($s['state_code']) ?> <?= sanitize_string($s['zip_code']) ?>
            </div>
          </td>
          <td>
            <!-- Merge (necesita contractor_id destino) -->
            <form class="d-inline" method="post" action="<?= $action_url ?>" onsubmit="return askContractorId(this);">
              <input type="hidden" name="_csrf" value="<?= sanitize_string($csrf) ?>">
              <input type="hidden" name="staging_id" value="<?= (int)$s['staging_id'] ?>">
              <input type="hidden" name="contractor_id" value="">
              <button name="action" value="merge_contractor" class="btn btn-sm btn-primary">Merge</button>
            </form>
            <!-- Keep (descartar staging) -->
            <form class="d-inline" method="post" action="<?= $action_url ?>" onsubmit="return askContractorId(this, true);">
              <input type="hidden" name="_csrf" value="<?= sanitize_string($csrf) ?>">
              <input type="hidden" name="staging_id" value="<?= (int)$s['staging_id'] ?>">
              <input type="hidden" name="contractor_id" value="">
              <button name="action" value="keep_contractor" class="btn btn-sm btn-secondary">Keep Existing</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

</div>

<script>
function confirmReject(formEl){
  const reason = prompt('Please enter a short reason:','Data could not be verified');
  if (reason === null) return false;
  formEl.querySelector('input[name="reason"]').value = reason.trim();
  return true;
}

function askContractorId(formEl, keepOnly=false){
  const msg = keepOnly
    ? 'Enter the existing contractor_id to KEEP (discard staging):'
    : 'Enter the existing contractor_id to MERGE INTO (existing record will be updated except CAC):';
  const id = prompt(msg,'');
  if (!id) return false;
  formEl.querySelector('input[name="contractor_id"]').value = id.trim();
  return true;
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
