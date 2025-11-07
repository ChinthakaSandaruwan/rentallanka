<?php
require_once __DIR__ . '/../../config/config.php';

// If already logged in as admin (or super admin), send to admin dashboard
$role = $_SESSION['role'] ?? '';
$isSuper = isset($_SESSION['super_admin_id']);
$loggedIn = (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) || $isSuper;
if ($loggedIn && ($role === 'admin' || $isSuper)) {
    header('Location: ' . rtrim($base_url, '/') . '/admin/index.php');
    exit;
}

$err = '';
$ok = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $phone = trim((string)($_POST['phone'] ?? ''));
    if ($phone === '' || !preg_match('/^0[7][01245678][0-9]{7}$/', $phone)) {
        $err = 'Enter a valid mobile number in 07XXXXXXXX format.';
    } else {
        try {
            // Check whether the number belongs to an admin or super admin (informational only)
            $is_admin = false; $is_super = false;
            $st = db()->prepare('SELECT role FROM users WHERE phone=? LIMIT 1');
            if ($st) {
                $st->bind_param('s', $phone);
                $st->execute();
                $u = $st->get_result()->fetch_assoc();
                $st->close();
                if ($u && ($u['role'] ?? '') === 'admin') { $is_admin = true; }
            }
            $sa = db()->prepare('SELECT super_admin_id FROM super_admins WHERE phone=? LIMIT 1');
            if ($sa) {
                $sa->bind_param('s', $phone);
                $sa->execute();
                $sr = $sa->get_result()->fetch_assoc();
                $sa->close();
                if ($sr) { $is_super = true; }
            }
            if ($is_super) {
                $ok = 'This number is registered as Super Admin. Please use the Super Admin login page.';
            } elseif ($is_admin) {
                $ok = 'This number belongs to an Admin account. Use appropriate admin credentials to continue.';
            } else {
                $err = 'This number is not associated with an admin account.';
            }
        } catch (Throwable $e) {
            $err = 'Validation failed. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body class="bg-light">
  <?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-8 col-lg-5">
        <div class="card shadow-sm">
          <div class="card-body p-4">
            <div class="text-center mb-3">
              <i class="bi bi-shield-lock display-6 text-primary"></i>
              <h1 class="h4 mt-2 mb-0">Admin Login</h1>
            </div>
            <p class="text-muted small mb-3 text-center">
              Only accounts with the role <strong>admin</strong> or <strong>super admin</strong> can proceed.
            </p>
            <?php if ($err): ?>
              <div class="alert alert-danger" role="alert"><?= htmlspecialchars($err) ?></div>
            <?php elseif ($ok): ?>
              <div class="alert alert-info" role="alert"><?= htmlspecialchars($ok) ?></div>
            <?php endif; ?>
            <form method="post" class="needs-validation mb-3" novalidate>
              <div class="mb-3">
                <label for="admin_phone" class="form-label">Mobile Number</label>
                <input type="text" id="admin_phone" name="phone" class="form-control" placeholder="07XXXXXXXX" inputmode="tel" pattern="^0[7][01245678][0-9]{7}$" minlength="10" maxlength="10" required>
                <div class="invalid-feedback">Enter a valid number like 07XXXXXXXX.</div>
              </div>
              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Validate</button>
              </div>
            </form>
            <div class="d-grid gap-2">
              <a class="btn btn-primary" href="<?= $base_url ?>/auth/login.php">
                <i class="bi bi-box-arrow-in-right me-1"></i> Go to Login
              </a>
              <a class="btn btn-outline-secondary" href="<?= $base_url ?>/">
                <i class="bi bi-house me-1"></i> Back to Home
              </a>
            </div>
            <hr class="my-4">
            <div class="small text-muted">
              If you believe you should have admin access, please contact a system administrator to elevate your account permissions.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  <script>
    (() => {
      'use strict';
      const forms = document.querySelectorAll('.needs-validation');
      Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
          }
          form.classList.add('was-validated');
        }, false);
      });
    })();
  </script>
</body>
</html>
