<?php
    // public/views/dashboard.php
    // Dashboard de usuario: listado de garantías + búsquedas.
    declare(strict_types=1);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $user_email = $_SESSION['user']['email'] ?? null;
    $is_signed_in = !empty($_SESSION['user']['user_id'] ?? null);

    /** @var array<int,array<string,mixed>> $warranties */
    /** @var array<string,mixed> $pagination */
    /** @var array<string,mixed> $filters */
    /** @var array<string,mixed>|null $external_warranty */
    /** @var bool $external_search_performed */

    $base_dashboard_url = route_url('/dashboard');
    $search_query       = (string)($filters['search_query'] ?? '');
    $q_param            = $search_query !== '' ? '&q=' . urlencode($search_query) : '';

    $current_page = (int)$pagination['current_page'];
    $total_pages  = (int)$pagination['total_pages'];
    $total_w      = (int)$pagination['total'];

    $status_badges = [
        'ACTIVATE' => 'success',
        'VOID'     => 'secondary',
        'EXPIRED'  => 'warning',
    ];

    // Configuración para el header
    $page_title = 'My Warranties – Dashboard';
    $body_class = 'dashboard-body';

    // Aseguramos que exista token CSRF para el form de sign-out
    ensure_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dashboard · Contractor App</title>
        
        <base href="<?= sanitize_string(base_url('/')) ?>">

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="<?= asset_url('css/styles.css') ?>">
        <link rel="stylesheet" href="<?= asset_url('css/dashboard.css') ?>">

        <script src="https://kit.fontawesome.com/1d7ca8a227.js" crossorigin="anonymous"></script>
    </head>

    <?php 
        // public/views/partials/header.php
        require __DIR__ . '/partials/header.php'; 
    ?>
    
    <div class="dashboard-content">
        <div class="container mb-5">

            <!-- Header del módulo + botón principal -->
            <div class="card ew-card ew-card-shadow mb-4">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h1 class="h4 mb-1">My Warranties</h1>
                        <p class="text-muted mb-0 small">
                            Review, search and manage all the warranties registered under your account.
                        </p>
                    </div>

                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <!-- Ajusta esta ruta al formulario real de registro de garantía -->
                        <a href="<?= sanitize_string(route_url('/warranty/register')); ?>"
                        class="btn ew-btn-primary text-white w-100">
                            <i class="fa fa-plus me-1"></i> Register Warranty
                        </a>
                    </div>
                </div>
            </div>

            <!-- Buscador principal + resumen -->
            <div class="card ew-card ew-card-shadow mb-4">
                <div class="card-body">
                    <form method="get" action="<?= sanitize_string($base_dashboard_url); ?>" class="row g-2 align-items-center">
                        <div class="col-md-8">
                            <label class="form-label mb-1 small text-uppercase text-muted">
                                Search warranties
                            </label>
                            <div class="input-group">
                                <input
                                    type="text"
                                    name="q"
                                    class="form-control"
                                    placeholder="Serial, email, owner name or warranty number (E-00000042)"
                                    value="<?= sanitize_string($search_query); ?>"
                                >
                                <button type="submit" class="btn btn-outline-secondary">
                                    <i class="fa fa-search me-1"></i> Search
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4 mt-2 mt-md-0 d-flex flex-column align-items-md-end gap-2">
                            <div class="small text-muted">
                                <span class="me-3">
                                    <span class="fw-semibold"><?= $total_w; ?></span> warranties
                                </span>
                                <span>
                                    Page <span class="fw-semibold"><?= $current_page; ?></span>
                                    / <?= $total_pages; ?>
                                </span>
                            </div>
                            <div>
                                <a href="<?= sanitize_string($base_dashboard_url); ?>"
                                class="btn btn-sm btn-outline-light border">
                                    Clear search
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de garantías -->
            <div class="card ew-card ew-card-shadow mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Registered warranties</span>
                    <?php if ($search_query !== ''): ?>
                        <span class="badge bg-light text-muted rounded-pill">
                            Filter: <?= sanitize_string($search_query); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="card-body p-0">
                    <?php if (empty($warranties)): ?>
                        <p class="m-3 text-muted mb-3">
                            No warranties found. Try adjusting your search or register a new warranty.
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light ew-table-header">
                                <tr>
                                    <th scope="col">Warranty #</th>
                                    <th scope="col">Outdoor Serial</th>
                                    <th scope="col">Indoor Serial</th>
                                    <th scope="col">Owner</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Installation</th>
                                    <th scope="col">Purchased</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Created</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($warranties as $w): ?>
                                    <tr>
                                        <td class="fw-semibold">
                                            <?= sanitize_string((string)$w['warranty_number']); ?>
                                        </td>
                                        <td>
                                            <?= sanitize_string((string)($w['outdoor_serial_number'] ?? '')); ?>
                                        </td>
                                        <td>
                                            <?= sanitize_string((string)($w['indoor_serial_number'] ?? '')); ?>
                                        </td>
                                        <td>
                                            <?= sanitize_string(
                                                trim((string)$w['owner_first_name'] . ' ' . (string)$w['owner_last_name'])
                                            ); ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <?= sanitize_string((string)($w['owner_email'] ?? '')); ?>
                                        </td>
                                        <td>
                                            <?= sanitize_string((string)($w['installation_city'] ?? '')); ?>
                                            <?php if (!empty($w['installation_state_code'])): ?>
                                                <span class="text-muted">
                                                    (<?= sanitize_string((string)$w['installation_state_code']); ?>)
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <?= sanitize_string((string)$w['purchased_date']); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status = (string)$w['status'];
                                            $badge  = $status_badges[$status] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $badge; ?> ew-badge-pill">
                                                <?= sanitize_string($status); ?>
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <?= sanitize_string((string)$w['created_at']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Paginación -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                        <span class="text-muted small">
                            Showing page <?= $current_page; ?> of <?= $total_pages; ?>
                        </span>
                        <nav aria-label="Warranties pagination">
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

            <!-- Búsqueda de garantías externas -->
            <div class="card ew-card ew-card-shadow">
                <div class="card-header bg-white">
                    <span class="fw-semibold">Search warranty from another user</span>
                </div>
                <div class="card-body">
                    <form method="get" action="<?= sanitize_string($base_dashboard_url); ?>" class="row g-3 mb-3">
                        <?php if ($search_query !== ''): ?>
                            <input type="hidden" name="q" value="<?= sanitize_string($search_query); ?>">
                        <?php endif; ?>

                        <div class="col-md-4">
                            <label class="form-label small text-uppercase text-muted">Serial number</label>
                            <input
                                type="text"
                                name="ext_serial"
                                class="form-control"
                                value="<?= isset($_GET['ext_serial']) ? sanitize_string((string)$_GET['ext_serial']) : ''; ?>"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-uppercase text-muted">Owner last name</label>
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
                                <i class="fa fa-search me-1"></i> Search other warranty
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
                                <div class="fw-semibold mb-1">
                                    Warranty #<?= sanitize_string((string)$external_warranty['warranty_number']); ?>
                                </div>
                                <div class="small">
                                    <div>
                                        Owner:
                                        <?= sanitize_string(
                                            trim((string)$external_warranty['owner_first_name'] . ' ' . (string)$external_warranty['owner_last_name'])
                                        ); ?>
                                    </div>
                                    <div>Email: <?= sanitize_string((string)($external_warranty['owner_email'] ?? '')); ?></div>
                                    <div>
                                        Serial (outdoor):
                                        <?= sanitize_string((string)($external_warranty['outdoor_serial_number'] ?? '')); ?>
                                    </div>
                                    <div>
                                        Serial (indoor):
                                        <?= sanitize_string((string)($external_warranty['indoor_serial_number'] ?? '')); ?>
                                    </div>
                                    <div>
                                        Installation:
                                        <?= sanitize_string((string)($external_warranty['installation_city'] ?? '')); ?>
                                        <?php if (!empty($external_warranty['installation_state_code'])): ?>
                                            (<?= sanitize_string((string)$external_warranty['installation_state_code']); ?>)
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        Purchased:
                                        <?= sanitize_string((string)$external_warranty['purchased_date']); ?>
                                    </div>
                                    <div>
                                        Status:
                                        <span class="badge bg-<?= $status_badges[(string)$external_warranty['status']] ?? 'secondary'; ?> ew-badge-pill">
                                            <?= sanitize_string((string)$external_warranty['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <?php require __DIR__ . '/partials/footer.php'; ?>
