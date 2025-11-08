<?php require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('admin'); ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <style>
    .card-icon {
      font-size: 1.75rem;
      opacity: .7;
    }
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
              <h5 class="card-title mb-0">Create Customer</h5>
              <i class="bi bi-people card-icon"></i>
            </div>
            <p class="card-text text-muted">Create a new customer.</p>
            <a href="customer/customer_create.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Delete Customer</h5>
              <i class="bi bi-people card-icon"></i>
            </div>
            <p class="card-text text-muted">Delete a customer.</p>
            <a href="customer/customer_delete.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

       <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Update Customer</h5>
              <i class="bi bi-people card-icon"></i>
            </div>
            <p class="card-text text-muted">Update a customer.</p>
            <a href="customer/customer_update.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

       <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Read Customer</h5>
              <i class="bi bi-people card-icon"></i>
            </div>
            <p class="card-text text-muted">Read a customer.</p>
            <a href="customer/customer_read.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
 

      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Property Approval</h5>
              <i class="bi bi-person-gear card-icon"></i>
            </div>
            <p class="card-text text-muted">Approve properties.</p>
            <a href="property_approval.php" class="btn btn-primary mt-auto">Open</a>
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
              <h5 class="card-title mb-0">Packages Management</h5>
              <i class="bi bi-person-gear card-icon"></i>
            </div>
            <p class="card-text text-muted">Create, edit, and manage packages.</p>
            <a href="packages_management.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Bought Packages Management</h5>
              <i class="bi bi-person-gear card-icon"></i>
            </div>
            <p class="card-text text-muted">Create, edit, and manage bought packages.</p>
            <a href="bought_packages_management.php" class="btn btn-primary mt-auto">Open</a>
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
              <h5 class="card-title mb-0">Footer Management</h5>
              <i class="bi bi-person-circle card-icon"></i>
            </div>
            <p class="card-text text-muted">Manage footer.</p>
            <a href="footer_management.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">As an advertiser Management</h5>
              <i class="bi bi-person-circle card-icon"></i>
            </div>
            <p class="card-text text-muted">Manage as an advertiser.</p>
            <a href="as_an_advertiser_management.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
      crossorigin="anonymous"></script>
  </div>
</body>

</html>