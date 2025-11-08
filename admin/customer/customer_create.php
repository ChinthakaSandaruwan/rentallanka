<?php
require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_role('admin');

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$error = '';
$flash = '';
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $error = 'Invalid request';
  } else {
    $email = trim($_POST['email'] ?? '');
    $nic = trim($_POST['nic'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $role = 'customer';

    $valid = true;
    if ($phone === '' || !preg_match('/^0[7][01245678][0-9]{7}$/', $phone)) { $valid = false; }
    if (!in_array($status, ['active','inactive','banned'], true)) { $valid = false; }
    if ($name === '') { $valid = false; }
    if (!$valid) {
      $error = 'Invalid input';
    } else {
      $stmt = db()->prepare('INSERT INTO users (email, nic, name, phone, role, status) VALUES (?,?,?,?,?,?)');
      if ($stmt) {
        $stmt->bind_param('ssssss', $email, $nic, $name, $phone, $role, $status);
        if ($stmt->execute()) {
          $stmt->close();
          $flash = 'Customer created';
          $flash_type = 'success';
        } else {
          $error = 'Create failed';
          $stmt->close();
        }
      } else {
        $error = 'Create failed';
      }
    }
  }
}

// Keep existing flash if set via session, but prefer local flash
list($sess_flash, $sess_flash_type) = get_flash();
if (!$flash && $sess_flash) { $flash = $sess_flash; $flash_type = $sess_flash_type; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Customer</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
  <?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Create Customer</h1>
      <div class="d-flex align-items-center gap-2">
        <a href="../index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
      </div>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if (!empty($flash)): ?>
      <div class="alert alert-<?php echo $flash_type==='error'?'danger':'success'; ?>" role="alert"><?php echo htmlspecialchars($flash); ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">Details</div>
      <div class="card-body">
        <form method="post" class="row g-3 needs-validation" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

          <div class="col-12 col-md-6">
            <label for="name" class="form-label">Name<span class="text-danger">*</span></label>
            <input type="text" id="name" class="form-control" name="name" maxlength="100" required>
            <div class="invalid-feedback">Name is required.</div>
          </div>

          <div class="col-12 col-md-6">
            <label for="nic" class="form-label">NIC</label>
            <input type="text" id="nic" class="form-control" name="nic" maxlength="20" placeholder="optional">
          </div>

          <div class="col-12 col-md-6">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" class="form-control" name="email" placeholder="optional" autocomplete="email" maxlength="255">
            <div class="invalid-feedback">Please enter a valid email or leave blank.</div>
          </div>

          <div class="col-12 col-md-6">
            <label for="phone" class="form-label">Phone<span class="text-danger">*</span></label>
            <input type="text" id="phone" class="form-control" name="phone" inputmode="tel" placeholder="07XXXXXXXX" pattern="^0[7][01245678][0-9]{7}$" minlength="10" maxlength="10" required>
            <div class="invalid-feedback">Enter a valid mobile number in 07XXXXXXXX format.</div>
          </div>

          <div class="col-12 col-md-6">
            <label for="status" class="form-label">Status<span class="text-danger">*</span></label>
            <select id="status" name="status" class="form-select" required>
              <option value="active" selected>Active</option>
              <option value="inactive">Inactive</option>
              <option value="banned">Banned</option>
            </select>
            <div class="invalid-feedback">Please select a status.</div>
          </div>

          <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Create</button>
            <a class="btn btn-outline-secondary" href="../index.php">Cancel</a>
          </div>
        </form>
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

