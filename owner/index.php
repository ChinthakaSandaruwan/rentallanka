<?php require_once __DIR__ . '/../public/includes/auth_guard.php'; require_role('owner'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Owner Dashboard</title>
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
      <h1 class="h3 mb-0">Owner Dashboard</h1>
      <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>

    <div class="row g-3">
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">My Properties</h5>
              <i class="bi bi-building card-icon"></i>
            </div>
            <p class="card-text text-muted">Manage your property listings.</p>
            <button class="btn btn-primary mt-auto" type="button"><a href="property_management.php" class="text-decoration-none text-white">View Properties</a></button>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Bookings</h5>
              <i class="bi bi-calendar-check card-icon"></i>
            </div>
            <p class="card-text text-muted">View and manage guest bookings.</p>
            <button class="btn btn-secondary mt-auto" type="button" disabled>Coming Soon</button>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Payments</h5>
              <i class="bi bi-credit-card card-icon"></i>
            </div>
            <p class="card-text text-muted">Track rental payments.</p>
            <button class="btn btn-secondary mt-auto" type="button" disabled>Coming Soon</button>
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
                <div class="p-3 bg-light rounded">Role: <span class="fw-semibold">Owner</span></div>
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
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php require_once __DIR__ . '/../public/includes/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
