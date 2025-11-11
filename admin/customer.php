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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  <style>
    .card-icon {
      font-size: 1.75rem;
      opacity: 0.8;
    }
    .card:hover {
      transform: translateY(-3px);
      transition: 0.2s ease-in-out;
      box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>

  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0">Customers</h1>
      <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-house-door me-1"></i> Dashboard
      </a>
    </div>

    <div class="row g-3">

      <!-- Create Customer -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100 border-primary">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Create Customer</h5>
              <i class="bi bi-person-plus card-icon text-primary"></i>
            </div>
            <p class="card-text text-muted">Add a new customer to the system.</p>
            <a href="customer/customer_create.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

      <!-- Read Customer -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100 border-success">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Read Customer</h5>
              <i class="bi bi-person-lines-fill card-icon text-success"></i>
            </div>
            <p class="card-text text-muted">View and browse customer details.</p>
            <a href="customer/customer_read.php" class="btn btn-success mt-auto">Open</a>
          </div>
        </div>
      </div>

      <!-- Update Customer -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100 border-warning">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Update Customer</h5>
              <i class="bi bi-pencil-square card-icon text-warning"></i>
            </div>
            <p class="card-text text-muted">Edit or update customer information.</p>
            <a href="customer/customer_update.php" class="btn btn-warning text-white mt-auto">Open</a>
          </div>
        </div>
      </div>

      <!-- Delete Customer -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100 border-danger">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Delete Customer</h5>
              <i class="bi bi-person-dash card-icon text-danger"></i>
            </div>
            <p class="card-text text-muted">Remove a customer from the system.</p>
            <a href="customer/customer_delete.php" class="btn btn-danger mt-auto">Open</a>
          </div>
        </div>
      </div>

      <!-- Customer Status Change -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100 border-info">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Status Change</h5>
              <i class="bi bi-toggle-on card-icon text-info"></i>
            </div>
            <p class="card-text text-muted">Activate or deactivate customer accounts.</p>
            <a href="customer/customer_status.php" class="btn btn-info text-white mt-auto">Open</a>
          </div>
        </div>
      </div>

    </div>
  </div>
</body>
</html>
