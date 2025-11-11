<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
  $f = __DIR__ . '/../error/error.log';
  if (is_readable($f)) {
    $lines = 100; $data = '';
    $fp = fopen($f, 'r');
    if ($fp) {
      fseek($fp, 0, SEEK_END); $pos = ftell($fp); $chunk = ''; $ln = 0;
      while ($pos > 0 && $ln <= $lines) {
        $step = max(0, $pos - 4096); $read = $pos - $step;
        fseek($fp, $step); $chunk = fread($fp, $read) . $chunk; $pos = $step;
        $ln = substr_count($chunk, "\n");
      }
      fclose($fp);
      $parts = explode("\n", $chunk); $slice = array_slice($parts, -$lines); $data = implode("\n", $slice);
    }
    header('Content-Type: text/plain; charset=utf-8'); echo $data; exit;
  }
}

require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('admin'); ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <style>
    .card-icon {
      font-size: 1.75rem;
      opacity: .75;
    }
  </style>
</head>

<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>

  <div class="container py-4">
    <div class="d-flex justify-content-center align-items-center mb-3">
      <h1 class="h3 mb-0 text-center">Admin Dashboard</h1>
    </div>

    <div class="row g-3">

      <!-- Customer -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Customer</h5>
              <i class="bi bi-people card-icon text-primary"></i>
            </div>
            <p class="card-text text-muted">Manage customer accounts.</p>
            <a href="customer.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

      <!-- Owner -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Owner</h5>
              <i class="bi bi-person-badge card-icon text-success"></i>
            </div>
            <p class="card-text text-muted">View and manage property owners.</p>
            <a href="owner.php" class="btn btn-success mt-auto">Open</a>
          </div>
        </div>
      </div>

      <!-- Property -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Property</h5>
              <i class="bi bi-building card-icon text-warning"></i>
            </div>
            <p class="card-text text-muted">Manage listed properties.</p>
            <a href="property.php" class="btn btn-warning text-white mt-auto">Open</a>
          </div>
        </div>
      </div>

      <!-- Package -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Package</h5>
              <i class="bi bi-box-seam card-icon text-danger"></i>
            </div>
            <p class="card-text text-muted">Manage subscription packages.</p>
            <a href="package.php" class="btn btn-danger mt-auto">Open</a>
          </div>
        </div>
      </div>

      <!-- Profile -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Profile</h5>
              <i class="bi bi-person-circle card-icon text-info"></i>
            </div>
            <p class="card-text text-muted">Update your profile and password.</p>
            <a href="../public/includes/profile.php" class="btn btn-info text-white mt-auto">Open</a>
          </div>
        </div>
      </div>



      <!-- Room -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Room</h5>
              <i class="bi bi-door-open card-icon text-primary"></i>
            </div>
            <p class="card-text text-muted">Manage rooms and availability.</p>
            <a href="room.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

      <!-- Footer Management -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Footer Management</h5>
              <i class="bi bi-layout-text-window-reverse card-icon text-dark"></i>
            </div>
            <p class="card-text text-muted">Edit website footer content.</p>
            <a href="footer.php" class="btn btn-dark mt-auto">Open</a>
          </div>
        </div>
      </div>

      <!-- User Type -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">User Type Change</h5>
              <i class="bi bi-shuffle card-icon text-success"></i>
            </div>
            <p class="card-text text-muted">Manage and update user roles.</p>
            <a href="user_type.php" class="btn btn-success mt-auto">Open</a>
          </div>
        </div>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
</body>

</html>
