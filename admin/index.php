<?php require_once __DIR__ . '/../public/includes/auth_guard.php'; require_role('admin'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .card-icon { font-size: 1.75rem; opacity: .7; }
  </style>
  </head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0">Admin Dashboard</h1>
      <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>

    <div class="row g-3">
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Customer Management</h5>
              <i class="bi bi-people card-icon"></i>
            </div>
            <p class="card-text text-muted">View and manage customers.</p>
            <a href="customer_management.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Admin Management</h5>
              <i class="bi bi-person-gear card-icon"></i>
            </div>
            <p class="card-text text-muted">Create, edit, and manage admins.</p>
            <a href="admin_management.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Owner Management</h5>
              <i class="bi bi-person-gear card-icon"></i>
            </div>
            <p class="card-text text-muted">Create, edit, and manage owners.</p>
            <a href="owner_management.php" class="btn btn-primary mt-auto">Open</a>
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
    

   <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Property Management</h5>
              <i class="bi bi-person-circle card-icon"></i>
            </div>
            <p class="card-text text-muted">Manage properties.</p>
            <a href="property_management.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
    

     <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Room Management</h5>
              <i class="bi bi-person-circle card-icon"></i>
            </div>
            <p class="card-text text-muted">Manage rooms.</p>
            <a href="room_management.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Location Management</h5>
              <i class="bi bi-person-circle card-icon"></i>
            </div>
            <p class="card-text text-muted">Manage locations.</p>
            <a href="location_management.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Notification</h5>
              <i class="bi bi-person-circle card-icon"></i>
            </div>
            <p class="card-text text-muted">Manage notifications.</p>
            <a href="notification.php" class="btn btn-primary mt-auto">Open</a>
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
                <div class="p-3 bg-light rounded">Role: <span class="fw-semibold">Admin</span></div>
              </div>
              <div class="col-6 col-md-3">
                <div class="p-3 bg-light rounded">Logged in</div>
              </div>
            </div>
          </div>
        </div>
      </div>




      <div class="col-12 col-lg-4">
        <div class="card h-100">
          <div class="card-header">Shortcuts</div>
          <div class="list-group list-group-flush">
            <a href="customer_management.php" class="list-group-item list-group-item-action">Customer Management</a>
            <a href="admin_management.php" class="list-group-item list-group-item-action">Admin Management</a>
            <a href="property_management.php" class="list-group-item list-group-item-action">Property Management</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php require_once __DIR__ . '/../public/includes/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
