<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
  $f = __DIR__ . '/../../error/error.log';
  if (is_readable($f)) {
    $lines = 100;
    $data = '';
    $fp = fopen($f, 'r');
    if ($fp) {
      fseek($fp, 0, SEEK_END);
      $pos = ftell($fp);
      $chunk = '';
      $ln = 0;
      while ($pos > 0 && $ln <= $lines) {
        $step = max(0, $pos - 4096);
        $read = $pos - $step;
        fseek($fp, $step);
        $chunk = fread($fp, $read) . $chunk;
        $pos = $step;
        $ln = substr_count($chunk, "\n");
      }
      fclose($fp);
      $parts = explode("\n", $chunk);
      $slice = array_slice($parts, -$lines);
      $data = implode("\n", $slice);
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo $data;
    exit;
  }
}

require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_role('admin');

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$user_id = (int)($_GET['user_id'] ?? 0);
$role = 'customer';
$error = '';
$flash = '';
$flash_type = 'success';

// Load existing customer
$customer = null;
if ($user_id > 0) {
  $stmt = db()->prepare('SELECT user_id, email, nic, name, phone, status, profile_image FROM users WHERE user_id = ? AND role = ?');
  if ($stmt) {
    $stmt->bind_param('is', $user_id, $role);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      $customer = $res->fetch_assoc();
      $res->free();
    }
    $stmt->close();
  }
}

// Do not redirect out of this page; show inline message only when an invalid id was provided
if ($user_id > 0 && !$customer) {
  $error = 'Invalid customer id';
}

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
    $img_path = null;

    $valid = true;
    if ($name === '') { $valid = false; }
    if ($phone === '' || !preg_match('/^0[7][01245678][0-9]{7}$/', $phone)) { $valid = false; }
    if (!in_array($status, ['active','inactive','banned'], true)) { $valid = false; }

    if (isset($_FILES['profile_image']) && is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
      $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
        $valid = false;
      } else {
        $target_dir = __DIR__ . '/../../uploads/profile';
        if (!is_dir($target_dir)) { @mkdir($target_dir, 0777, true); }
        $fname = 'u' . $user_id . '_' . time() . '.' . $ext;
        $dest = rtrim($target_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fname;
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dest)) {
          $rel_base = str_replace('\\', '/', str_replace(__DIR__ . '/../../', '', $target_dir));
          $img_path = $rel_base . '/' . $fname;
        } else {
          $valid = false;
        }
      }
    }

    if (!$valid) {
      $error = 'Invalid input';
    } else {
      if ($img_path !== null) {
        $stmt = db()->prepare('UPDATE users SET email = ?, nic = ?, name = ?, phone = ?, status = ?, profile_image = ? WHERE user_id = ? AND role = ?');
      } else {
        $stmt = db()->prepare('UPDATE users SET email = ?, nic = ?, name = ?, phone = ?, status = ? WHERE user_id = ? AND role = ?');
      }
      if ($stmt) {
        if ($img_path !== null) {
          $stmt->bind_param('ssssssis', $email, $nic, $name, $phone, $status, $img_path, $user_id, $role);
        } else {
          $stmt->bind_param('sssssis', $email, $nic, $name, $phone, $status, $user_id, $role);
        }
        if ($stmt->execute()) {
          $stmt->close();
          $flash = 'Customer updated';
          $flash_type = 'success';
          // Reload customer (including profile_image)
          $stmt2 = db()->prepare('SELECT user_id, email, nic, name, phone, status, profile_image FROM users WHERE user_id = ? AND role = ? LIMIT 1');
          if ($stmt2) {
            $stmt2->bind_param('is', $user_id, $role);
            if ($stmt2->execute()) {
              $res2 = $stmt2->get_result();
              $customer = $res2->fetch_assoc();
              $res2->free();
            }
            $stmt2->close();
          }
        } else {
          $error = 'Update failed';
          $stmt->close();
        }
      } else {
        $error = 'Update failed';
      }
    }
  }
}
// POST-Redirect-GET to avoid resubmission on refresh (after processing above)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $msg = $flash ?: ($error ?: 'Action completed.');
  $typ = $flash ? ($flash_type ?: 'success') : ($error ? 'error' : 'success');
  $url = rtrim($base_url,'/') . '/admin/customer/customer_update.php?user_id=' . (int)$user_id;
  redirect_with_message($url, $msg, $typ);
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Update Customer</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
  <?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Update Customer</h1>
      <div class="d-flex align-items-center gap-2">
        <a href="../index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
      </div>
    </div>

    <?php /* Alerts handled by SweetAlert2 via JS below */ ?>

    <?php if ($customer): ?>
    <div class="card">
      <div class="card-header">Details</div>
      <div class="card-body">
        <div class="text-center mb-3">
          <?php $img = (string)($customer['profile_image'] ?? ''); ?>
          <?php if ($img !== ''): ?>
            <img src="<?php echo $base_url . '/' . ltrim($img, '/'); ?>" alt="Profile" class="rounded-circle border" style="width:96px;height:96px;object-fit:cover;">
          <?php else: ?>
            <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" style="width:96px;height:96px;color:white;">
              <span style="font-weight:600;">No Image</span>
            </div>
          <?php endif; ?>
        </div>
        <form method="post" class="row g-3 needs-validation" novalidate enctype="multipart/form-data" id="formCustomerUpdate">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

          <div class="col-12 col-md-6">
            <label for="name" class="form-label">Name<span class="text-danger">*</span></label>
            <input type="text" id="name" class="form-control" name="name" maxlength="100" value="<?php echo htmlspecialchars($customer['name'] ?? ''); ?>" required>
            <div class="invalid-feedback">Name is required.</div>
          </div>

          <div class="col-12 col-md-6">
            <label for="nic" class="form-label">NIC</label>
            <input type="text" id="nic" class="form-control" name="nic" maxlength="20" value="<?php echo htmlspecialchars($customer['nic'] ?? ''); ?>" placeholder="optional">
          </div>

          <div class="col-12 col-md-6">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" class="form-control" name="email" maxlength="255" value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>" placeholder="optional" autocomplete="email">
            <div class="invalid-feedback">Please enter a valid email or leave blank.</div>
          </div>

          <div class="col-12 col-md-6">
            <label for="phone" class="form-label">Phone<span class="text-danger">*</span></label>
            <input type="text" id="phone" class="form-control" name="phone" inputmode="tel" placeholder="07XXXXXXXX" pattern="^0[7][01245678][0-9]{7}$" minlength="10" maxlength="10" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>" required>
            <div class="invalid-feedback">Enter a valid mobile number in 07XXXXXXXX format.</div>
          </div>

          <div class="col-12 col-md-6">
            <label for="status" class="form-label">Status<span class="text-danger">*</span></label>
            <select id="status" name="status" class="form-select" required>
              <?php $st = $customer['status'] ?? 'active'; ?>
              <option value="active" <?php echo $st==='active'?'selected':''; ?>>Active</option>
              <option value="inactive" <?php echo $st==='inactive'?'selected':''; ?>>Inactive</option>
              <option value="banned" <?php echo $st==='banned'?'selected':''; ?>>Banned</option>
            </select>
            <div class="invalid-feedback">Please select a status.</div>
          </div>

          <div class="col-12">
            <label for="profile_image" class="form-label">Profile Image</label>
            <input type="file" id="profile_image" class="form-control" name="profile_image" accept="image/*">
          </div>

          <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Update</button>
            <a class="btn btn-outline-secondary" href="../index.php">Cancel</a>
          </div>
        </form>
      </div>
    </div>
    <?php else: ?>
    <?php
      // Load customers to choose from when no valid user_id is provided with filters
      $q = trim($_GET['q'] ?? '');
      $status_filter = $_GET['status'] ?? '';
      $wheres = ["role='customer'"];
      $params = [];
      $types = '';
      if ($q !== '') {
        $wheres[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR nic LIKE ?)";
        $like = '%' . $q . '%';
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        $types .= 'ssss';
      }
      if (in_array($status_filter, ['active','inactive','banned'], true)) {
        $wheres[] = 'status = ?';
        $params[] = $status_filter;
        $types .= 's';
      }
      $sql = 'SELECT user_id, name, email, phone, status FROM users WHERE ' . implode(' AND ', $wheres) . ' ORDER BY user_id DESC';
      $customers = [];
      if ($types !== '') {
        $stmtL = db()->prepare($sql);
        if ($stmtL) {
          $stmtL->bind_param($types, ...$params);
          if ($stmtL->execute()) {
            $resL = $stmtL->get_result();
            while ($row = $resL->fetch_assoc()) { $customers[] = $row; }
            $resL->free();
          }
          $stmtL->close();
        }
      } else {
        $result = db()->query($sql);
        if ($result) {
          while ($row = $result->fetch_assoc()) { $customers[] = $row; }
          $result->close();
        }
      }
    ?>
    <div class="card">
      <div class="card-header">Select a Customer to Update</div>
      <div class="card-body p-0">
        <form method="get" class="p-3 border-bottom bg-light">
          <div class="row g-2 align-items-end">
            <div class="col-12 col-md-6">
              <label class="form-label" for="q">Search</label>
              <input type="text" id="q" name="q" class="form-control" placeholder="name, email, phone, NIC" value="<?php echo htmlspecialchars($q ?? ''); ?>">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label" for="status">Status</label>
              <select id="status" name="status" class="form-select">
                <option value="">Any</option>
                <option value="active" <?php echo ($status_filter==='active')?'selected':''; ?>>Active</option>
                <option value="inactive" <?php echo ($status_filter==='inactive')?'selected':''; ?>>Inactive</option>
                <option value="banned" <?php echo ($status_filter==='banned')?'selected':''; ?>>Banned</option>
              </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
              <button type="submit" class="btn btn-primary mt-3 mt-md-0"><i class="bi bi-search me-1"></i>Filter</button>
              <a href="customer_update.php" class="btn btn-outline-secondary mt-3 mt-md-0">Reset</a>
            </div>
          </div>
        </form>
        <div class="table-responsive">
          <table class="table table-striped table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th style="width: 80px;">ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th style="width: 120px;">Status</th>
                <th style="width: 120px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($customers)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No customers found.</td></tr>
              <?php else: ?>
                <?php foreach ($customers as $c): ?>
                  <tr>
                    <td><?php echo (int)$c['user_id']; ?></td>
                    <td><?php echo htmlspecialchars($c['name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($c['email'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($c['phone'] ?? ''); ?></td>
                    <td>
                      <span class="badge bg-<?php echo $c['status']==='active'?'success':($c['status']==='inactive'?'secondary':'danger'); ?>">
                        <?php echo htmlspecialchars($c['status']); ?>
                      </span>
                    </td>
                    <td>
                      <a class="btn btn-sm btn-primary" href="customer_update.php?user_id=<?php echo (int)$c['user_id']; ?>">
                        <i class="bi bi-pencil-square me-1"></i>Edit
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  <script>
    (function(){
      try {
        const err = <?= json_encode($error) ?>;
        const msg = <?= json_encode($flash) ?>;
        const typ = (<?= json_encode($flash_type) ?> || 'info').toLowerCase();
        const icon = ({ success:'success', error:'error', danger:'error', warning:'warning', info:'info' })[typ] || 'info';
        if (err) {
          Swal.fire({ icon: 'error', title: 'Error', text: String(err), confirmButtonText: 'OK' });
        } else if (msg) {
          Swal.fire({ icon, title: icon==='success'?'Success':icon==='warning'?'Warning':'Info', text: String(msg), confirmButtonText: 'OK' });
        }

        const form = document.getElementById('formCustomerUpdate');
        if (form) {
          form.addEventListener('submit', async function(e){
            if (!form.checkValidity()) return; // existing validation script will handle blocking
            e.preventDefault();
            const name = (form.querySelector('#name')?.value || '').trim();
            const res = await Swal.fire({
              title: 'Save changes?',
              text: name ? ('Update customer ' + name + '?') : 'Update this customer?',
              icon: 'question',
              showCancelButton: true,
              confirmButtonText: 'Yes, update',
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
