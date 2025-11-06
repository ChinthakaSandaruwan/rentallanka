<?php require_once __DIR__ . '/../public/includes/auth_guard.php'; require_role('customer'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customer Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  </head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
  <div class="container-lg py-5">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
      <div>
        <h1 class="h3 mb-1">Customer Dashboard</h1>
        <p class="text-muted mb-0">Quick access to properties, rooms, rentals, and your profile.</p>
      </div>
      <a href="../auth/logout.php" class="btn btn-outline-danger">Logout</a>
    </div>

    <div class="row g-4">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card h-100 border rounded-3 shadow-sm">
          <div class="card-body d-flex flex-column gap-2">
            <div class="d-flex align-items-center">
              <h5 class="card-title mb-0">Browse Properties</h5>
            </div>
            <p class="card-text text-muted">Find places to rent.</p>
            <a href="../index.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
      
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card h-100 border rounded-3 shadow-sm">
          <div class="card-body d-flex flex-column gap-2">
            <div class="d-flex align-items-center">
              <h5 class="card-title mb-0">Browse Rooms</h5>
            </div>
            <p class="card-text text-muted">Find rooms to rent.</p>
            <a class="btn btn-primary mt-auto" href="../index.php">Open</a>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card h-100 border rounded-3 shadow-sm">
          <div class="card-body d-flex flex-column gap-2">
            <div class="d-flex align-items-center">
              <h5 class="card-title mb-0">My Rented Properties</h5>
            </div>
            <p class="card-text text-muted">View your active property rentals.</p>
            <div class="d-flex gap-2 mt-auto">
              <a href="my_rented_property.php" class="btn btn-primary">Open</a>
              <a href="my_property_monthly_payment.php" class="btn btn-outline-primary">Monthly Payment</a>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card h-100 border rounded-3 shadow-sm">
          <div class="card-body d-flex flex-column gap-2">
            <div class="d-flex align-items-center">
              <h5 class="card-title mb-0">My Rented Rooms</h5>
            </div>
            <p class="card-text text-muted">View your active room rentals.</p>
            <div class="d-flex gap-2 mt-auto">
              <a href="my_rented_room.php" class="btn btn-primary">Open</a>
              <a href="my_room_daily_payment.php" class="btn btn-outline-primary">Daily Payment</a>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card h-100 border rounded-3 shadow-sm">
          <div class="card-body d-flex flex-column gap-2">
            <div class="d-flex align-items-center">
              <h5 class="card-title mb-0">Profile</h5>
            </div>
            <p class="card-text text-muted">Update your details and password.</p>
            <a href="../public/includes/profile.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-6 col-lg-4">
        <div class="card h-100 border rounded-3 shadow-sm">
          <div class="card-body d-flex flex-column gap-2">
            <div class="d-flex align-items-center">
              <h5 class="card-title mb-0">Notification</h5>
            </div>
            <p class="card-text text-muted">Manage notifications.</p>
            <a href="notification.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php require_once __DIR__ . '/../public/includes/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
