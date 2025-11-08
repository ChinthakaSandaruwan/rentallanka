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
    </div>

   
   <div class="row g-3">
      <div class="col-12 col-sm-6 col-lg-3">
    
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Customer</h5>
              <i class="bi bi-person-circle card-icon"></i>
            </div>
            <p class="card-text text-muted">Update your details and password.</p>
            <a href="customer.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>


        <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Owner</h5>
              <i class="bi bi-person-circle card-icon"></i>
            </div>
            <p class="card-text text-muted">Update your details and password.</p>
            <a href="owner.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>


       <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Property</h5>
              <i class="bi bi-person-circle card-icon"></i>
            </div>
            <p class="card-text text-muted">Manage properties.</p>
            <a href="property.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

        <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Package</h5>
              <i class="bi bi-person-circle card-icon"></i>
            </div>
            <p class="card-text text-muted">Manage packages.</p>
            <a href="package.php" class="btn btn-primary mt-auto">Open</a>
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
            <a href="property.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

      <div class="col">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Room</h5>
              <i class="bi bi-person-circle card-icon"></i>
            </div>
            <p class="card-text text-muted">Manage rooms.</p>
            <a href="room.php" class="btn btn-primary mt-auto">Open</a>
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
            <a href="footer.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

         <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">User Type Change</h5>
              <i class="bi bi-person-circle card-icon"></i>
            </div>
            <p class="card-text text-muted">Manage User Type.</p>
            <a href="user_type.php" class="btn btn-primary mt-auto">Open</a>
          </div>
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