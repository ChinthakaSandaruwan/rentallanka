<?php require_once __DIR__ . '/../public/includes/auth_guard.php'; require_super_admin(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Super Admin Dashboard</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

</head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0">Super Admin Dashboard</h1>
      <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-4">
      <div class="col">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">OTP Management</h5>
            <p class="card-text text-muted">Configure OTP settings and manage user OTP verifications.</p>
            <a href="otp_management.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">Profile</h5>
            <p class="card-text text-muted">View and update your email, phone, and password.</p>
            <a href="includes/profile.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">Error Log</h5>
            <p class="card-text text-muted">View and update error log.</p>
            <a href="error.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">Super Admin Management</h5>
            <p class="card-text text-muted">Manage super admin users.</p>
            <a href="super_admin_management.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">Uploads Management</h5>
            <p class="card-text text-muted">Manage uploaded files and images.</p>
            <a href="uploads_management.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
    </div>

   </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>