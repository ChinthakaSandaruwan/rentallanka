<?php require_once __DIR__ . '/../public/includes/auth_guard.php'; require_role('customer'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customer Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .card-icon { font-size: 1.75rem; opacity: .7; }
  </style>
  </head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0">Customer Dashboard</h1>
      <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>

    <div class="row g-3">
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Browse Properties</h5>
              <i class="bi bi-building card-icon"></i>
            </div>
            <p class="card-text text-muted">Find places to rent.</p>
            <a href="../index.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
      
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Browse Rooms</h5>
              <i class="bi bi-calendar-check card-icon"></i>
            </div>
            <p class="card-text text-muted">Find rooms to rent.</p>
            <a class="btn btn-primary mt-auto" href="../index.php">Open</a>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">My Rented Properties</h5>
              <i class="bi bi-house-door card-icon"></i>
            </div>
            <p class="card-text text-muted">View your active property rentals.</p>
            <div class="d-flex justify-content-between mt-auto">
              <a href="my_rented_property.php" class="btn btn-outline-primary">Open</a>
              <a href="my_property_monthly_payment.php" class="btn btn-outline-primary">Monthly Payment</a>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">My Rented Rooms</h5>
              <i class="bi bi-door-open card-icon"></i>
            </div>
            <p class="card-text text-muted">View your active room rentals.</p>
            <div class="d-flex justify-content-between mt-auto">
              <a href="my_rented_room.php" class="btn btn-outline-primary">Open</a>
              <a href="my_room_daily_payment.php" class="btn btn-outline-primary">Daily Payment</a>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Profile</h5>
              <i class="bi bi-person-circle card-icon"></i>
            </div>
            <p class="card-text text-muted">Update your details and password.</p>
            <a href="../public/includes/profile.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mt-3">
      <div class="col-12 col-lg-8">
        <div class="card h-100">
          <div class="card-header">Info</div>
          <div class="card-body">
            <div class="row text-center g-3">
              <div class="col-6 col-md-3">
                <div class="p-3 bg-light rounded">Role: <span class="fw-semibold">Customer</span></div>
              </div>
              <div class="col-6 col-md-3">
                <div class="p-3 bg-light rounded">Welcome</div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-4">
        <div class="card h-100">
          <div class="card-header">Shortcuts</div>
          <div class="list-group list-group-flush">
            <a href="../public/includes/profile.php" class="list-group-item list-group-item-action">Profile</a>
            <a href="../index.php" class="list-group-item list-group-item-action">Home</a>
            <a href="my_rented_property.php" class="list-group-item list-group-item-action">My Rented Properties</a>
            <a href="my_rented_room.php" class="list-group-item list-group-item-action">My Rented Rooms</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php require_once __DIR__ . '/../public/includes/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
