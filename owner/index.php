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
    .card-icon {
      font-size: 2rem;
      color: #0d6efd;
    }
    .card:hover {
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      transform: translateY(-3px);
      transition: all 0.2s ease-in-out;
    }
  </style>
</head>
<body>
  
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
  
  <div class="container">
    <div class="d-flex justify-content-center align-items-center mt-5 mb-5">
      <h1 class="h3 mb-0">Owner Dashboard</h1>
    </div>

    <!-- PROPERTY SECTION -->
    <div class="row g-3">
      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-building-add card-icon mb-2"></i>
            <h5 class="card-title mb-0">Create Property</h5>
            <p class="card-text text-muted">Add new property.</p>
            <a href="property/property_create.php" class="btn btn-primary mt-auto">Create Property</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-eye card-icon mb-2"></i>
            <h5 class="card-title mb-0">Read Property</h5>
            <p class="card-text text-muted">View your properties.</p>
            <a href="property/property_read.php" class="btn btn-primary mt-auto">Read Property</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-pencil-square card-icon mb-2"></i>
            <h5 class="card-title mb-0">Update Property</h5>
            <p class="card-text text-muted">Modify your properties.</p>
            <a href="property/property_update.php" class="btn btn-primary mt-auto">Update Property</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-trash3 card-icon mb-2"></i>
            <h5 class="card-title mb-0">Delete Property</h5>
            <p class="card-text text-muted">Remove a property.</p>
            <a href="property/property_delete.php" class="btn btn-primary mt-auto">Delete Property</a>
          </div>
        </div>
      </div>
    </div>
  </div>


<hr>

  <!-- ROOM SECTION -->
  <div class="container py-4">
    <div class="row g-3">
      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-door-open card-icon mb-2"></i>
            <h5 class="card-title mb-0">Create Room</h5>
            <p class="card-text text-muted">Add new room.</p>
            <a href="room/room_create.php" class="btn btn-primary mt-auto">Create Room</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-eye card-icon mb-2"></i>
            <h5 class="card-title mb-0">Read Room</h5>
            <p class="card-text text-muted">View your rooms.</p>
            <a href="room/room_read.php" class="btn btn-primary mt-auto">Read Room</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-pencil card-icon mb-2"></i>
            <h5 class="card-title mb-0">Update Room</h5>
            <p class="card-text text-muted">Edit room details.</p>
            <a href="room/room_update.php" class="btn btn-primary mt-auto">Update Room</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-trash card-icon mb-2"></i>
            <h5 class="card-title mb-0">Delete Room</h5>
            <p class="card-text text-muted">Remove a room.</p>
            <a href="room/room_delete.php" class="btn btn-primary mt-auto">Delete Room</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <hr>




   <!-- PACKAGE SECTION -->
  <div class="container py-4">
    <div class="row g-3">
      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-bag-plus card-icon mb-2"></i>
            <h5 class="card-title mb-0">Buy Package</h5>
            <p class="card-text text-muted">Buy a new package.</p>
            <a href="package/buy_advertising_packages.php" class="btn btn-primary mt-auto">Buy Package</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-bag-check card-icon mb-2"></i>
            <h5 class="card-title mb-0">Bought Package</h5>
            <p class="card-text text-muted">View your purchased packages.</p>
            <a href="package/bought_advertising_packages.php" class="btn btn-primary mt-auto">Bought Package</a>
          </div>
        </div>
      </div>
    </div>
  </div>



  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
