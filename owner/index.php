<?php 
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');
if (isset($_GET['show_errors']) && $_GET['show_errors'] === '1') {
  $f = __DIR__ . '/error.log';
  if (is_file($f)) {
    $lines = @array_slice(@file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -100);
    if (!headers_sent()) { header('Content-Type', 'text/plain'); }
    echo implode("\n", $lines);
  } else {
    if (!headers_sent()) { header('Content-Type', 'text/plain'); }
    echo 'No error.log found';
  }
  exit;
}
require_once __DIR__ . '/../public/includes/auth_guard.php'; 
require_role('owner'); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Owner Dashboard</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    .card-icon {
      font-size: 2rem;
    }
    .section-title {
      margin-top: 2rem;
      margin-bottom: 1rem;
      font-weight: 600;
      border-bottom: 2px solid #dee2e6;
      padding-bottom: 0.25rem;
    }
    .card:hover {
      transform: translateY(-3px);
      transition: all 0.2s ease-in-out;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>

  <div class="container py-4">

    <!-- DASHBOARD TITLE -->
    <div class="d-flex justify-content-center align-items-center mb-4">
      <h1 class="h3 mb-0">Owner Dashboard</h1>
    </div>

    <!-- PROPERTY SECTION -->
    <h2 class="section-title">Properties</h2>
    <div class="row g-3">
      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-building-add text-primary card-icon mb-2"></i>
            <h5 class="card-title mb-0">Create Property</h5>
            <p class="card-text text-muted">Add new property.</p>
            <a href="property/property_create.php" class="btn btn-primary mt-auto">Create Property</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-eye text-success card-icon mb-2"></i>
            <h5 class="card-title mb-0">Read Property</h5>
            <p class="card-text text-muted">View your properties.</p>
            <a href="property/property_read.php" class="btn btn-success mt-auto">Read Property</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-pencil-square text-warning card-icon mb-2"></i>
            <h5 class="card-title mb-0">Update Property</h5>
            <p class="card-text text-muted">Modify your properties.</p>
            <a href="property/property_update.php" class="btn btn-warning mt-auto text-dark">Update Property</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-trash3 text-danger card-icon mb-2"></i>
            <h5 class="card-title mb-0">Delete Property</h5>
            <p class="card-text text-muted">Remove a property.</p>
            <a href="property/property_delete.php" class="btn btn-danger mt-auto">Delete Property</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Property tools -->
    <div class="row g-3 mt-1">
      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-clipboard-check text-primary card-icon mb-2"></i>
            <h5 class="card-title mb-0">Property Rent Approvals</h5>
            <p class="card-text text-muted">Approve or decline pending property rent requests from customers.</p>
            <a href="property/property_rent_approval.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-toggle2-on text-secondary card-icon mb-2"></i>
            <h5 class="card-title mb-0">Property Status</h5>
            <p class="card-text text-muted">Change property availability.</p>
            <a href="property/property_status.php" class="btn btn-secondary mt-auto">Open</a>
          </div>
        </div>
      </div>
    </div>

    <!-- ROOM SECTION -->
    <h2 class="section-title">Rooms</h2>
    <div class="row g-3">
      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-door-open text-info card-icon mb-2"></i>
            <h5 class="card-title mb-0">Create Room</h5>
            <p class="card-text text-muted">Add new room.</p>
            <a href="room/room_create.php" class="btn btn-info mt-auto text-white">Create Room</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-eye text-secondary card-icon mb-2"></i>
            <h5 class="card-title mb-0">Read Room</h5>
            <p class="card-text text-muted">View your rooms.</p>
            <a href="room/room_read.php" class="btn btn-secondary mt-auto">Read Room</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-pencil-square text-warning card-icon mb-2"></i>
            <h5 class="card-title mb-0">Update Room</h5>
            <p class="card-text text-muted">Edit room details.</p>
            <a href="room/room_update.php" class="btn btn-warning mt-auto text-dark">Update Room</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-trash text-danger card-icon mb-2"></i>
            <h5 class="card-title mb-0">Delete Room</h5>
            <p class="card-text text-muted">Remove a room.</p>
            <a href="room/room_delete.php" class="btn btn-danger mt-auto">Delete Room</a>
          </div>
        </div>
      </div>
   

    <!-- RENTED ROOM SECTION -->
    <div class="row g-3">
      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-calendar-check text-success card-icon mb-2"></i>
            <h5 class="card-title mb-0">Rented Rooms</h5>
            <p class="card-text text-muted">View your rented rooms.</p>
            <a href="room/room_rented.php" class="btn btn-success mt-auto">Open</a>
          </div>
        </div>
      </div>
   

      <!-- room status -->
      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-toggle2-on text-secondary card-icon mb-2"></i>
            <h5 class="card-title mb-0">Room Status</h5>
            <p class="card-text text-muted">Change room availability.</p>
            <a href="room/room_status.php" class="btn btn-secondary mt-auto">Open</a>
          </div>
        </div>
      </div>

      <!-- room approval -->
      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-clipboard-check text-primary card-icon mb-2"></i>
            <h5 class="card-title mb-0">Room Approvals</h5>
            <p class="card-text text-muted">Approve or decline room bookings.</p>
            <a href="room/room_rent_approval.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>  

 </div>

 </div>
    <!-- PACKAGE SECTION -->
    <h2 class="section-title">Packages</h2>
    <div class="row g-3">
      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-bag-plus text-primary card-icon mb-2"></i>
            <h5 class="card-title mb-0">Buy Package</h5>
            <p class="card-text text-muted">Buy a new package.</p>
            <a href="package/buy_advertising_packages.php" class="btn btn-primary mt-auto">Buy Package</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body d-flex flex-column">
            <i class="bi bi-bag-check text-success card-icon mb-2"></i>
            <h5 class="card-title mb-0">Bought Package</h5>
            <p class="card-text text-muted">View your purchased packages.</p>
            <a href="package/bought_advertising_packages.php" class="btn btn-success mt-auto">Bought Package</a>
          </div>
        </div>
      </div>
    </div>



  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
