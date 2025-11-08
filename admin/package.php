<?php require_once __DIR__ . '/../public/includes/auth_guard.php'; require_role('admin'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packages</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0">Packages</h1>
      <a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
    </div>

    <div class="row g-3">
 <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Packages Management</h5>
              <i class="bi bi-person-gear card-icon"></i>
            </div>
            <p class="card-text text-muted">Create, edit, and manage packages.</p>
            <a href="package/packages_management.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

            <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Packages Create</h5>
              <i class="bi bi-person-gear card-icon"></i>
            </div>
            <p class="card-text text-muted">Create packages.</p>
            <a href="package/package_create.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>

               <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Packages Update</h5>
              <i class="bi bi-person-gear card-icon"></i>
            </div>
            <p class="card-text text-muted">Update packages.</p>
            <a href="package/package_update.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>


     

      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Packages Read</h5>
              <i class="bi bi-list-task card-icon"></i>
            </div>
            <p class="card-text text-muted">Browse and filter all packages.</p>
            <a href="package/package_read.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>