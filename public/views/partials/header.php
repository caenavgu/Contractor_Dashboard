<!-- public/views/partials/header.php -->
<body class="<?= sanitize_string(trim($body_class)); ?>">
    <header class="ew-header-bar py-3 mb-4">
        <div class="container d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2">
                <!-- Cambia por tu logo si quieres -->
                <img src="<?= asset_url('/img/logo-everwell.svg'); ?>" alt="Everwell" height="80">
            </div>
            <?php
                $display_name   = $_SESSION['user']['name']          ?? '';
                $display_email  = $_SESSION['user']['email']         ?? '';
                $display_type   = $_SESSION['user']['user_type_label'] ?? '';
                $display_company= $_SESSION['user']['company_name']  ?? '';
            ?>
            <div class="ew-header-metrics text-center text-md-start">
                <div class="fw-semibold">
                    Welcome<br>
                    <?= $display_name !== '' ? sanitize_string($display_name) : sanitize_string($display_email); ?><br>
                    <?= $user_email ? ' ' . sanitize_string($user_email) : ''; ?>
                </div>
                <div class="text-muted small">
                    <?= sanitize_string($display_type); ?>
                    <?= $display_company ? ' · ' . sanitize_string($display_company) : ''; ?>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <form action="<?= sanitize_string(route_url('/sign-out')) ?>" method="post" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= sanitize_string(get_csrf_token()) ?>">
                    <button type="submit" class="btn btn-link">Sign out</button>
                </form>
                <form action="<?= sanitize_string(route_url('/sign-out-all')) ?>" method="post" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= sanitize_string(get_csrf_token()) ?>">
                    <button type="submit" class="btn btn-link">Sign out all</button>
                </form>
                <form action="<?= sanitize_string(route_url('/profile')) ?>" method="post" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= sanitize_string(get_csrf_token()) ?>">
                    <button type="submit" class="btn btn-link">Perfil</button>
                </form>
            </div>
        </div>
    </header>
