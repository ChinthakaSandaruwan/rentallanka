<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Type</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/../public/includes/navbar.php'; ?>

<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">User Type</h1>
  </div>
  <div class="row g-3">

<div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="card-title mb-0">Change User Type</h5>
              <i class="bi bi-person-circle card-icon"></i>
            </div>
            <p class="card-text text-muted">Manage advertiser user type.</p>
            <a href="user_type/as_an_advertiser_management.php" class="btn btn-primary mt-auto">Manage</a>
          </div>
        </div>
      </div>
    
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>