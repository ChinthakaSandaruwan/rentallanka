<?php require_once __DIR__ . '/../public/includes/auth_guard.php'; require_role('customer'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  </head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
  <div class="container-lg py-5">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
      <div>
        <h1 class="h3 mb-1">Customer Dashboard</h1>
      </div>
      <a href="../auth/logout.php" class="btn btn-outline-danger">Logout</a>
    </div>

    <div class="row g-4">
     
      
    
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

      
    </div>
  </div>

  <?php require_once __DIR__ . '/../public/includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
