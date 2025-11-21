<?php
// public/views/dashboard.php
// -------------------------------------------------------------
// Dashboard mínimo con 3 acciones y Font Awesome (kit gratuito)
// UI en inglés; comentarios en español.
// -------------------------------------------------------------
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Dashboard · Contractor App</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Base para que todos los enlaces/recursos respeten tu subcarpeta -->
  <base href="<?= sanitize_string(base_url('/')) ?>">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- CSS de app (paleta y estilos generales) -->
  <link rel="stylesheet" href="<?= asset_url('css/app.css') ?>">

  <!-- Font Awesome (kit gratuito) -->
  <script src="https://kit.fontawesome.com/1d7ca8a227.js" crossorigin="anonymous"></script>
</head>
<body class="app-page">

  <main class="container">
    <div class="dashboard-panel">
      <form action="<?= sanitize_string(route_url('/sign-out')) ?>" method="post" style="display:inline;">
        <input type="hidden" name="_csrf" value="<?= sanitize_string(get_csrf_token()) ?>">
        <button type="submit" class="btn btn-link">Sign out</button>
      </form>

      <form action="<?= sanitize_string(route_url('/sign-out-all')) ?>" method="post" style="display:inline;">
        <input type="hidden" name="_csrf" value="<?= sanitize_string(get_csrf_token()) ?>">
        <button type="submit" class="btn btn-link">Sign out all</button>
      </form>

      <a class="dash-item" href="<?= route_url('/warranty-registration') ?>">
        <i class="fa-solid fa-pen-to-square me-2" aria-hidden="true"></i>
        <span>Warranty Registration</span>
      </a>

      <a class="dash-item" href="<?= route_url('/warranty-search') ?>">
        <i class="fa-solid fa-magnifying-glass me-2" aria-hidden="true"></i>
        <span>Search Warranty</span>
      </a>

      <a class="dash-item" href="<?= route_url('/my-warranties') ?>">
        <i class="fa-solid fa-folder-open me-2" aria-hidden="true"></i>
        <span>My Warranties</span>
      </a>

    </div>
  </main>

</body>
</html>
