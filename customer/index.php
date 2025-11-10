<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_role('customer');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customer Dashboard</title>

  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body {
      background-color: #f8f9fa;
      font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }

    .dashboard-header {
      background: linear-gradient(135deg, #0d6efd, #6610f2);
      color: white;
      border-radius: 1rem;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .dashboard-header h1 {
      font-weight: 600;
    }

    .dashboard-card {
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      border: none;
      border-radius: 1rem;
      overflow: hidden;
    }

    .dashboard-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
    }

    .card-icon {
      font-size: 2rem;
      color: #0d6efd;
      background-color: #e7f1ff;
      border-radius: 0.75rem;
      padding: 0.6rem;
      width: 3rem;
      height: 3rem;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .logout-btn {
      background: white;
      color: #dc3545;
      border: 1px solid #dc3545;
      transition: all 0.2s;
    }

    .logout-btn:hover {
      background: #dc3545;
      color: white;
    }
  </style>
</head>

<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>

  <div class="container-lg py-5">
    <!-- Header -->
    <div class="dashboard-header d-flex flex-column flex-md-row align-items-md-center justify-content-between text-center text-md-start">
      <div>
        <h1 class="h3 mb-2">Welcome Back, Customer!</h1>
        <p class="mb-0 opacity-75">Manage your profile, rentals, and more from one place.</p>
      </div>
     
    </div>

    <!-- Cards -->
    <div class="row g-4">
      
      <!-- Profile -->
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card dashboard-card h-100 shadow-sm">
          <div class="card-body d-flex flex-column gap-3">
            <div class="d-flex align-items-center gap-3">
              <div class="card-icon"><i class="bi bi-person-circle"></i></div>
              <h5 class="card-title mb-0">Profile</h5>
            </div>
            <p class="card-text text-muted">Update your personal details, contact info, and password securely.</p>
            <a href="../public/includes/profile.php" class="btn btn-primary mt-auto w-100">
              <i class="bi bi-arrow-right-circle me-1"></i> Open
            </a>
          </div>
        </div>
      </div>

      <!-- Rentals -->
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card dashboard-card h-100 shadow-sm">
          <div class="card-body d-flex flex-column gap-3">
            <div class="d-flex align-items-center gap-3">
              <div class="card-icon"><i class="bi bi-house-door"></i></div>
              <h5 class="card-title mb-0">My Rentals</h5>
            </div>
            <p class="card-text text-muted">View your booked properties, payments, and rental history.</p>
            <a href="../public/includes/my_rentals.php" class="btn btn-primary mt-auto w-100">
              <i class="bi bi-arrow-right-circle me-1"></i> Open
            </a>
          </div>
        </div>
      </div>

      <!-- Wishlist -->
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card dashboard-card h-100 shadow-sm">
          <div class="card-body d-flex flex-column gap-3">
            <div class="d-flex align-items-center gap-3">
              <div class="card-icon"><i class="bi bi-heart"></i></div>
              <h5 class="card-title mb-0">Wishlist</h5>
            </div>
            <p class="card-text text-muted">Keep track of your favorite properties for future bookings.</p>
            <a href="../public/includes/wish_list.php" class="btn btn-primary mt-auto w-100">
              <i class="bi bi-arrow-right-circle me-1"></i> Open
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
