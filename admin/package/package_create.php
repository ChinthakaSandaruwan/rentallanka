<?php
require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_role('admin');

$alert = ['type' => '', 'msg' => ''];
$is_post = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST');
if ($is_post) {
  try {
    $package_name = trim((string)($_POST['package_name'] ?? ''));
    $type_choice = trim((string)($_POST['type_choice'] ?? 'property')); // property | room
    $duration_choice = trim((string)($_POST['duration_choice'] ?? 'monthly')); // monthly | yearly
    $max_count = (int)($_POST['max_count'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $description = (string)($_POST['description'] ?? '');
    $status = trim((string)($_POST['status'] ?? 'active'));

    if ($package_name === '') { throw new Exception('Package name required'); }
    if (!in_array($type_choice, ['property','room'], true)) { throw new Exception('Invalid type'); }
    if (!in_array($duration_choice, ['monthly','yearly'], true)) { throw new Exception('Invalid duration'); }
    if ($max_count < 0) { throw new Exception('Max Advertising Count must be [>=[0m 0'); }
    $allowed_status = ['active','inactive'];
    if (!in_array($status, $allowed_status, true)) { throw new Exception('Invalid status'); }

    $package_type = $duration_choice; // monthly|yearly
    $duration_days = ($duration_choice === 'monthly') ? 30 : 365;
    $max_properties = ($type_choice === 'property') ? $max_count : 0;
    $max_rooms = ($type_choice === 'room') ? $max_count : 0;

    $stmt = db()->prepare('INSERT INTO packages (package_name, package_type, duration_days, max_properties, max_rooms, price, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) { throw new Exception('DB error'); }
    $stmt->bind_param('ssiiidss', $package_name, $package_type, $duration_days, $max_properties, $max_rooms, $price, $description, $status);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) { throw new Exception('Create failed'); }
    redirect_with_message(rtrim($base_url,'/') . '/admin/package/package_create.php', 'Package created', 'success');
    exit;
  } catch (Throwable $e) {
    $alert = ['type' => 'danger', 'msg' => htmlspecialchars($e->getMessage())];
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Package</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Create Package</h1>
    <div class="d-flex gap-2">
      <a href="packages_management.php" class="btn btn-outline-secondary btn-sm">All Packages</a>
      <a href="../index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
    </div>
  </div>

  <?php /* Alerts handled by SweetAlert2 via JS below; Bootstrap alerts removed */ ?>

  <div class="card">
    <div class="card-body">
      <form method="post" class="row g-3 needs-validation" novalidate id="formPackageCreate">
        <div class="col-12 col-md-6">
          <label for="package_name" class="form-label">Package Name</label>
          <input type="text" id="package_name" name="package_name" class="form-control" maxlength="120" required>
          <div class="invalid-feedback">Package name is required.</div>
        </div>
        <div class="col-12 col-md-3">
          <label for="type_choice" class="form-label">Type</label>
          <select id="type_choice" name="type_choice" class="form-select" required>
            <option value="property">Property</option>
            <option value="room">Room</option>
          </select>
          <div class="invalid-feedback">Please select a type.</div>
        </div>
        <div class="col-12 col-md-3">
          <label for="duration_choice" class="form-label">Duration</label>
          <select id="duration_choice" name="duration_choice" class="form-select" required>
            <option value="monthly">Monthly</option>
            <option value="yearly">Yearly</option>
          </select>
          <div class="invalid-feedback">Please select a duration.</div>
        </div>
        <div class="col-12 col-md-3">
          <label for="max_count" class="form-label">Max Advertising Count</label>
          <input type="number" id="max_count" name="max_count" class="form-control" value="0" min="0" step="1" required>
          <div class="invalid-feedback">Please enter a valid max count (0 or more).</div>
        </div>
        <div class="col-12 col-md-3">
          <label for="price" class="form-label">Price (LKR)</label>
          <input type="number" id="price" step="0.01" min="0" name="price" class="form-control" value="0.00" required>
          <div class="invalid-feedback">Please enter a valid price.</div>
        </div>
        <div class="col-12 col-md-4">
          <label for="status" class="form-label">Status</label>
          <select id="status" name="status" class="form-select" required>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
          <div class="invalid-feedback">Please select a status.</div>
        </div>
        <div class="col-12">
          <label for="description" class="form-label">Description</label>
          <textarea id="description" name="description" class="form-control" rows="3"></textarea>
        </div>
        <div class="col-12 d-flex gap-2 justify-content-end">
          <a href="packages_management.php" class="btn btn-outline-secondary">Cancel</a>
          <button class="btn btn-primary" type="submit">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script>
  (function(){
    try {
      const alertMsg = <?= json_encode($alert['msg']) ?>;
      const alertType = (<?= json_encode($alert['type']) ?> || '').toLowerCase();
      if (alertMsg) {
        const icon = ({ success:'success', danger:'error', error:'error', warning:'warning', info:'info' })[alertType] || 'error';
        Swal.fire({ icon, title: icon==='success'?'Success':icon==='warning'?'Warning':icon==='info'?'Info':'Error', text: String(alertMsg), confirmButtonText: 'OK' });
      }

      const form = document.getElementById('formPackageCreate');
      if (form) {
        form.addEventListener('submit', async function(e){
          if (!form.checkValidity()) return; // validation script will block if invalid
          e.preventDefault();
          const name = (form.querySelector('#package_name')?.value || '').trim();
          const res = await Swal.fire({
            title: 'Create package?',
            text: name ? ('Create package "' + name + '"?') : 'Create this package?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, create',
            cancelButtonText: 'Cancel'
          });
          if (res.isConfirmed) { form.submit(); }
        });
      }
    } catch(_) {}
  })();
</script>
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
