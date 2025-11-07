<?php require_once __DIR__ . '/../public/includes/auth_guard.php'; require_role('owner'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Owner Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .card-icon { font-size: 1.75rem; opacity: .7; }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0">Owner Dashboard</h1>
      <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>

    <div class="row g-3">
      <div class="col-12 col-md-4 col-lg-4">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">My Properties</h5>
              <i class="bi bi-building card-icon"></i>
            </div>
            <p class="card-text text-muted">Manage your property listings.</p>
            <a href="property_management.php" class="btn btn-primary mt-auto">View Properties</a>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-4 col-lg-4">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">My Rooms</h5>
              <i class="bi bi-calendar-check card-icon"></i>
            </div>
            <p class="card-text text-muted">View and manage guest rooms.</p>
            <a href="room_management.php" class="btn btn-primary mt-auto">View Rooms</a>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-4 col-lg-4">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Buy Advertising Packages</h5>
              <i class="bi bi-person-circle card-icon"></i>
            </div>
            <p class="card-text text-muted">Update your details and password.</p>
            <a href="buy_advertising_packages.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-4">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">My Advertising Packages</h5>
              <i class="bi bi-person-circle card-icon"></i>
            </div>
            <p class="card-text text-muted">Update your details and password.</p>
            <a href="bought_advertising_packages.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>




    </div>
  </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
