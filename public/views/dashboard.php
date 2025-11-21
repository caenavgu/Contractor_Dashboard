<?php
// public/views/dashboard.php
// Dashboard de usuario: listado de garantías + búsquedas.
declare(strict_types=1);

/** @var array<int,array<string,mixed>> $warranties */
/** @var array<string,mixed> $pagination */
/** @var array<string,mixed> $filters */
/** @var array<string,mixed>|null $external_warranty */
/** @var bool $external_search_performed */

$base_dashboard_url = route_url('/dashboard');
$search_query = (string)($filters['search_query'] ?? '');
$q_param = $search_query !== '' ? '&q=' . urlencode($search_query) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard – Warranties</title>
    <link rel="stylesheet" href="<?= asset_url('/css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?= asset_url('/css/app.css'); ?>">
</head>
<body>
<div class="container my-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Warranties</h1>

        <!-- TODO: ajusta esta ruta a la del formulario real de registro de garantía -->
        <a href="<?= sanitize_string(route_url('/warranty/register')); ?>" class="btn btn-primary">
            <i class="fa fa-plus me-1"></i> Register Warranty
        </a>
    </div>

    <!-- Barra de búsqueda interna -->
    <form method="get" action="<?= sanitize_string($base_dashboard_url); ?>" class="row g-2 mb-4">
        <div class="col-md-8">
            <input
                type="text"
                name="q"
                class="form-control"
                placeholder="Search by serial, email, name or warranty number (E-00000042)"
                value="<?= sanitize_string($search_query); ?>"
            >
        </div>
        <div class="col-md-4 d-flex">
            <button type="submit" class="btn btn-outline-secondary me-2 w-100">
                <i class="fa fa-search me-1"></i> Search
            </button>
            <a href="<?= sanitize_string($base_dashboard_url); ?>" class="btn btn-outline-light border w-100">
                Clear
            </a>
        </div>
    </form>

    <!-- Lista de garantías del usuario -->
    <div class="card mb-4">
        <div class="card-header">
            <strong>My warranties (<?= (int)$pagination['total']; ?>)</strong>
        </div>

        <div class="card-body p-0">
            <?php if (empty($warranties)): ?>
                <p class="m-3 text-muted mb-0">No warranties found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Warranty #</th>
                            <th>Outdoor Serial</th>
                            <th>Indoor Serial</th>
                            <th>Owner</th>
                            <th>Email</th>
                            <th>Installation City/State</th>
                            <th>Purchased Date</th>
                            <th>Status</th>
                            <th>Created At</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($warranties as $w): ?>
                            <tr>
                                <td><?= sanitize_string((string)$w['warranty_number']); ?></td>
                                <td><?= sanitize_string((string)($w['outdoor_serial_number'] ?? '')); ?></td>
                                <td><?= sanitize_string((string)($w['indoor_serial_number'] ?? '')); ?></td>
                                <td>
                                    <?= sanitize_string(
                                        trim((string)$w['owner_first_name'] . ' ' . (string)$w['owner_last_name'])
                                    ); ?>
                                </td>
                                <td><?= sanitize_string((string)($w['owner_email'] ?? '')); ?></td>
                                <td>
                                    <?= sanitize_string((string)($w['installation_city'] ?? '')); ?>
                                    <?php if (!empty($w['installation_state_code'])): ?>
                                        (<?= sanitize_string((string)$w['installation_state_code']); ?>)
                                    <?php endif; ?>
                                </td>
                                <td><?= sanitize_string((string)$w['purchased_date']); ?></td>
                                <td><?= sanitize_string((string)$w['status']); ?></td>
                                <td><?= sanitize_string((string)$w['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Paginación -->
        <?php if ((int)$pagination['total_pages'] > 1): ?>
            <?php
            $current_page = (int)$pagination['current_page'];
            $total_pages  = (int)$pagination['total_pages'];
            ?>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="text-muted">
                    Page <?= $current_page; ?> of <?= $total_pages; ?>
                </span>
                <nav>
                    <ul class="pagination mb-0">
                        <li class="page-item <?= $current_page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link"
                               href="<?= sanitize_string($base_dashboard_url . '?p=' . max(1, $current_page - 1) . $q_param); ?>">
                                Previous
                            </a>
                        </li>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i === $current_page ? 'active' : ''; ?>">
                                <a class="page-link"
                                   href="<?= sanitize_string($base_dashboard_url . '?p=' . $i . $q_param); ?>">
                                    <?= $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link"
                               href="<?= sanitize_string($base_dashboard_url . '?p=' . min($total_pages, $current_page + 1) . $q_param); ?>">
                                Next
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <!-- Búsqueda de garantías que NO pertenecen al usuario -->
    <div class="card">
        <div class="card-header">
            <strong>Search warranty from another user</strong>
        </div>
        <div class="card-body">
            <form method="get" action="<?= sanitize_string($base_dashboard_url); ?>" class="row g-2 mb-3">
                <?php if ($search_query !== ''): ?>
                    <input type="hidden" name="q" value="<?= sanitize_string($search_query); ?>">
                <?php endif; ?>

                <div class="col-md-4">
                    <label class="form-label">Serial number</label>
                    <input
                        type="text"
                        name="ext_serial"
                        class="form-control"
                        value="<?= isset($_GET['ext_serial']) ? sanitize_string((string)$_GET['ext_serial']) : ''; ?>"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label">Owner last name</label>
                    <input
                        type="text"
                        name="ext_last_name"
                        class="form-control"
                        value="<?= isset($_GET['ext_last_name']) ? sanitize_string((string)$_GET['ext_last_name']) : ''; ?>"
                        required
                    >
                </div>

                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-secondary w-100">
                        <i class="fa fa-search me-1"></i> Search
                    </button>
                </div>
            </form>

            <?php if (!empty($external_search_performed)): ?>
                <?php if ($external_warranty === null): ?>
                    <p class="text-muted mb-0">
                        No warranty found with that serial number and last name that belongs to another user.
                    </p>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <strong>Warranty found:</strong><br>
                        Warranty #:
                        <?= sanitize_string((string)$external_warranty['warranty_number']); ?><br>
                        Serial (outdoor):
                        <?= sanitize_string((string)($external_warranty['outdoor_serial_number'] ?? '')); ?><br>
                        Serial (indoor):
                        <?= sanitize_string((string)($external_warranty['indoor_serial_number'] ?? '')); ?><br>
                        Owner:
                        <?= sanitize_string(
                            trim((string)$external_warranty['owner_first_name'] . ' ' . (string)$external_warranty['owner_last_name'])
                        ); ?><br>
                        Email:
                        <?= sanitize_string((string)($external_warranty['owner_email'] ?? '')); ?><br>
                        Installation:
                        <?= sanitize_string((string)($external_warranty['installation_city'] ?? '')); ?>
                        <?php if (!empty($external_warranty['installation_state_code'])): ?>
                            (<?= sanitize_string((string)$external_warranty['installation_state_code']); ?>)
                        <?php endif; ?><br>
                        Purchased date:
                        <?= sanitize_string((string)$external_warranty['purchased_date']); ?><br>
                        Status:
                        <?= sanitize_string((string)$external_warranty['status']); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="<?= asset_url('/js/bootstrap.bundle.min.js'); ?>"></script>
</body>
</html>
