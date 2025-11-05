<?php require_once __DIR__ . '/../public/includes/auth_guard.php'; require_super_admin(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Super Admin Dashboard</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0">Super Admin Dashboard</h1>
      <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>

    <div class="row g-3">
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">OTP Management</h5>
            <p class="card-text text-muted">Configure OTP settings and manage user OTP verifications.</p>
            <a href="otp_management.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">PayHere Settings</h5>
            <p class="card-text text-muted">Manage Merchant ID, Secret, mode, and callback URLs.</p>
            <a href="payhere_management.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">Profile</h5>
            <p class="card-text text-muted">View and update your email, phone, and password.</p>
            <a href="includes/profile.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">Error Log</h5>
            <p class="card-text text-muted">View and update error log.</p>
            <a href="error.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mt-3">
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">Super Admin Management</h5>
            <p class="card-text text-muted">Manage super admin users.</p>
            <a href="super_admin_management.php" class="btn btn-primary mt-auto">Open</a>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mt-3">
      <div class="col-12 col-lg-8">
        <div class="card h-100">
          <div class="card-header">System Info</div>
          <div class="card-body">
            <div class="row text-center g-3">
              <div class="col-6 col-md-3">
                <div class="p-3 bg-light rounded">OTP: <span class="fw-semibold"><?= (int)setting_get('otp_enabled','1') ? 'Enabled' : 'Disabled' ?></span></div>
              </div>
              <div class="col-6 col-md-3">
                <div class="p-3 bg-light rounded">OTP Len: <span class="fw-semibold"><?= (int)setting_get('otp_length','6') ?></span></div>
              </div>
              <div class="col-6 col-md-3">
                <div class="p-3 bg-light rounded">Expiry: <span class="fw-semibold"><?= (int)setting_get('otp_expiry_minutes','5') ?>m</span></div>
              </div>
              <div class="col-6 col-md-3">
                <div class="p-3 bg-light rounded">Attempts: <span class="fw-semibold"><?= (int)setting_get('otp_max_attempts','5') ?></span></div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-4">
        <div class="card h-100">
          <div class="card-header">Shortcuts</div>
          <div class="list-group list-group-flush">
            <a href="otp_management.php" class="list-group-item list-group-item-action">OTP Management</a>
            <a href="payhere_management.php" class="list-group-item list-group-item-action">PayHere Settings</a>
            <a href="includes/profile.php" class="list-group-item list-group-item-action">Super Admin Profile</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php require_once __DIR__ . '/../public/includes/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>