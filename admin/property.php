<?php require_once __DIR__ . '/../public/includes/auth_guard.php'; 
require_role('admin'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Properties</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

  <style>
    .card-icon {
      font-size: 1.75rem;
      opacity: 0.9;
    }
    .card:hover {
      transform: translateY(-4px);
      transition: 0.25s ease-in-out;
      box-shadow: 0 0.75rem 1.25rem rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>

  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0">Properties</h1>
      <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-house-door me-1"></i> Dashboard
      </a>
    </div>

    <div class="row g-3">

      <!-- Property Approval -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100 border-success">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Property Approval</h5>
              <i class="bi bi-check-circle card-icon text-success"></i>
            </div>
            <p class="card-text text-muted">Review and approve property listings submitted by owners.</p>
            <a href="property/property_approval.php" class="btn btn-success mt-auto">Open</a>
          </div>
        </div>
      </div>

         <!-- Property Status -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100 border-warning">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Property View</h5>
              <i class="bi bi-building-gear card-icon text-warning"></i>
            </div>
            <p class="card-text text-muted">View all properties submitted by owners.</p>
            <a href="property/property_view.php" class="btn btn-warning text-white mt-auto">Open</a>
          </div>
        </div>
      </div>

      <!-- Property Status -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100 border-warning">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Property Status</h5>
              <i class="bi bi-building-gear card-icon text-warning"></i>
            </div>
            <p class="card-text text-muted">Update or modify a property’s visibility or status.</p>
            <a href="property/property_status.php" class="btn btn-warning text-white mt-auto">Open</a>
          </div>
        </div>
      </div>

    </div>
  </div>
</body>
</html>
