<?php
require_once __DIR__ . '/../../config/config.php';

$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
if (!$loggedIn) {
    redirect_with_message($base_url . '/auth/login.php', 'Please log in first', 'error');
}

$role = $_SESSION['role'] ?? '';
$user = $_SESSION['user'] ?? null; // for non-super users

// Prepare display data
$display = [
    'role' => $role,
    'name' => '',
    'nic' => '',
    'email' => $user['email'] ?? '',
    'phone' => $user['phone'] ?? '',
    'id' => $user['user_id'] ?? null,
    'profile_image' => $user['profile_image'] ?? '',
    'status' => '',
    'created_at' => '',
];

// Refresh details from DB (including profile_image) for logged-in user
if ($display['id'] !== null) {
    $uid = (int)$display['id'];
    $stmtU = db()->prepare('SELECT name, nic, email, phone, profile_image, role, status, created_at FROM users WHERE user_id = ? LIMIT 1');
    $stmtU->bind_param('i', $uid);
    $stmtU->execute();
    $resU = $stmtU->get_result();
    if ($rowU = $resU->fetch_assoc()) {
        $display['name'] = (string)($rowU['name'] ?? '');
        $display['nic'] = (string)($rowU['nic'] ?? '');
        $display['email'] = (string)($rowU['email'] ?? $display['email']);
        $display['phone'] = (string)($rowU['phone'] ?? $display['phone']);
        $display['profile_image'] = (string)($rowU['profile_image'] ?? $display['profile_image']);
        $display['role'] = (string)($rowU['role'] ?? $display['role']);
        $display['status'] = (string)($rowU['status'] ?? '');
        $display['created_at'] = (string)($rowU['created_at'] ?? '');
    }
    $stmtU->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_user_profile') {
            $name = trim((string)($_POST['name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $uid = (int)($display['id'] ?? 0);
            if ($uid <= 0) {
                redirect_with_message($base_url . '/public/includes/profile.php', 'Invalid user', 'error');
            }
            // Server-side validation
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                redirect_with_message($base_url . '/public/includes/profile.php', 'Invalid email', 'error');
            }
            if ($phone === '' || !preg_match('/^0[7][01245678][0-9]{7}$/', $phone)) {
                redirect_with_message($base_url . '/public/includes/profile.php', 'Invalid phone number. Use 07XXXXXXXX format.', 'error');
            }
            $img_path = null;
            if (isset($_FILES['profile_image']) && is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                    redirect_with_message($base_url . '/public/includes/profile.php', 'Invalid image', 'error');
                }
                $dir_map = [
                    'admin' => __DIR__ . '/../../uploads/admin_profile_photo',
                    'owner' => __DIR__ . '/../../uploads/owner_profile_photo',
                    'customer' => __DIR__ . '/../../uploads/user_profile_photo',
                ];
                $target_dir = $dir_map[$display['role']] ?? (__DIR__ . '/../../uploads/user_profile_photo');
                if (!is_dir($target_dir)) { @mkdir($target_dir, 0777, true); }
                $fname = 'u' . $uid . '_' . time() . '.' . $ext;
                $dest = rtrim($target_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fname;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dest)) {
                    $rel_base = str_replace('\\', '/', str_replace(__DIR__ . '/../../', '', $target_dir));
                    $img_path = $rel_base . '/' . $fname;
                }
            }
            if ($img_path !== null) {
                $stmt = db()->prepare('UPDATE users SET name = ?, email = ?, phone = ?, profile_image = ? WHERE user_id = ?');
                $stmt->bind_param('ssssi', $name, $email, $phone, $img_path, $uid);
            } else {
                $stmt = db()->prepare('UPDATE users SET name = ?, email = ?, phone = ? WHERE user_id = ?');
                $stmt->bind_param('sssi', $name, $email, $phone, $uid);
            }
            $stmt->execute();
            $stmt->close();
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['phone'] = $phone;
            $_SESSION['user']['email'] = $email;
            if ($img_path !== null) { $_SESSION['user']['profile_image'] = $img_path; }
            redirect_with_message($base_url . '/public/includes/profile.php', 'Profile updated');
    } elseif ($action === 'delete_user_account') {
            $uid = (int)($display['id'] ?? 0);
            if ($uid <= 0) {
                redirect_with_message($base_url . '/public/includes/profile.php', 'Invalid user', 'error');
            }
            $stmt = db()->prepare('DELETE FROM users WHERE user_id = ?');
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $stmt->close();
            session_destroy();
            redirect_with_message($base_url . '/index.php', 'Account deleted');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" /></head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="mb-3 d-flex align-items-center gap-2">
            <?php
              $badgeClass = 'bg-secondary';
              if ($display['role'] === 'admin') $badgeClass = 'bg-danger';
              elseif ($display['role'] === 'owner') $badgeClass = 'bg-success';
              elseif ($display['role'] === 'customer') $badgeClass = 'bg-primary';
            ?>
            <span class="badge <?= $badgeClass ?> text-uppercase"><?= htmlspecialchars($display['role'] ?: 'unknown') ?></span>
            <?php if (!empty($display['name'])): ?>
              <span class="text-muted"><?= htmlspecialchars($display['name']) ?></span>
            <?php endif; ?>
          </div>
          <div class="text-center mb-3">
            <?php if (!empty($display['profile_image'])): ?>
              <img src="<?= $base_url . '/' . ltrim($display['profile_image'], '/') ?>" alt="Profile" class="rounded-circle" style="width: 96px; height:96px; object-fit: cover;">
            <?php else: ?>
              <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" style="width:96px;height:96px;color:white;">
                <span style="font-weight:600;">No Image</span>
              </div>
            <?php endif; ?>
          </div>
          <ul class="list-group">
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>ID</span><span><?= $display['id'] !== null ? (int)$display['id'] : '-' ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>Name</span><span><?= $display['name'] !== '' ? htmlspecialchars($display['name']) : '-' ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>NIC</span><span><?= $display['nic'] !== '' ? htmlspecialchars($display['nic']) : '-' ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>Email</span><span><?= $display['email'] !== '' ? htmlspecialchars($display['email']) : '-' ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>Phone</span><span><?= $display['phone'] !== '' ? htmlspecialchars($display['phone']) : '-' ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>Role</span><span><?= $display['role'] !== '' ? htmlspecialchars($display['role']) : '-' ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>Status</span><span><?= $display['status'] !== '' ? htmlspecialchars($display['status']) : '-' ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>Created</span><span><?= $display['created_at'] !== '' ? htmlspecialchars($display['created_at']) : '-' ?></span>
            </li>
          </ul>

          <div class="d-flex flex-wrap gap-2 mt-3">
            <a class="btn btn-secondary" href="<?= $base_url ?>/">Home</a>
            <?php if ($display['role'] === 'admin'): ?>
              <a class="btn btn-outline-danger" href="<?= $base_url ?>/admin/index.php">Admin Dashboard</a>
            <?php elseif ($display['role'] === 'owner'): ?>
              <a class="btn btn-outline-success" href="<?= $base_url ?>/owner/index.php">Owner Dashboard</a>
            <?php elseif ($display['role'] === 'customer'): ?>
              <a class="btn btn-outline-primary" href="<?= $base_url ?>/user/index.php">Customer Dashboard</a>
            <?php endif; ?>
            <a class="btn btn-outline-danger ms-auto" href="<?= $base_url ?>/auth/logout.php">Logout</a>
          </div>
          <hr>
          <h5 class="mb-3">Edit Profile</h5>
          <form method="post" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate>
            <div class="col-12">
              <label for="name" class="form-label">Name</label>
              <input type="text" id="name" class="form-control" name="name" value="<?= htmlspecialchars($display['name']) ?>" maxlength="120">
            </div>
            <div class="col-12 col-md-6">
              <label for="email" class="form-label">Email</label>
              <input type="email" id="email" class="form-control" name="email" value="<?= htmlspecialchars($display['email']) ?>" maxlength="120">
              <div class="invalid-feedback">Please enter a valid email or leave blank.</div>
            </div>
            <div class="col-12 col-md-6">
              <label for="phone" class="form-label">Phone</label>
              <input type="text" id="phone" class="form-control" name="phone" value="<?= htmlspecialchars($display['phone']) ?>" inputmode="tel" placeholder="07XXXXXXXX" pattern="^0[7][01245678][0-9]{7}$" minlength="10" maxlength="10" required>
              <div class="invalid-feedback">Enter a valid mobile number in 07XXXXXXXX format.</div>
            </div>
            <div class="col-12">
              <label for="profile_image" class="form-label">Profile Image</label>
              <input type="file" id="profile_image" class="form-control" name="profile_image" accept="image/*">
            </div>
            <div class="col-12 d-flex gap-2">
              <input type="hidden" name="action" value="update_user_profile" />
              <button class="btn btn-primary" type="submit">Save</button>
              <?php if ($display['role'] === 'customer'): ?>
                <button class="btn btn-outline-danger" name="action" value="delete_user_account" type="submit" onclick="return confirm('Delete your account? This cannot be undone.');">Delete Account</button>
              <?php endif; ?>
            </div>
          </form>

        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
