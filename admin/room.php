<?php require_once __DIR__ . '/../public/includes/auth_guard.php'; 
require_role('admin'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rooms</title>
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
      <h1 class="h3 mb-0">Rooms</h1>
      <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-house-door me-1"></i> Dashboard
      </a>
    </div>

    <div class="row g-3">

        <!-- Room Approval -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100 border-primary">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Room Approval</h5>
              <i class="bi bi-check-circle card-icon text-primary"></i>
            </div>
            <p class="card-text text-muted">Review and approve room listings submitted by owners.</p>
            <a href="room/room_approval.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

      <!-- Room View -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100 border-primary">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Room View</h5>
              <i class="bi bi-eye card-icon text-primary"></i>
            </div>
            <p class="card-text text-muted">Browse and manage listed rooms.</p>
            <a href="room/room_view.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

      <!-- Room Delete -->
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100 border-danger">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Room Delete</h5>
              <i class="bi bi-trash3 card-icon text-danger"></i>
            </div>
            <p class="card-text text-muted">Delete or remove room listings.</p>
            <a href="room/room_delete.php" class="btn btn-danger mt-auto">Open</a>
          </div>
        </div>
      </div>

     

    </div>
  </div>
</body>
</html>
