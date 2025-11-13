<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ___DIR___ . '/error.log');
if (isset($_GET['show_errors']) && $_GET['show_errors'] === '1') {
  $f = ___DIR___ . '/error.log';
  if (is_file($f)) {
    $lines = @array_slice(@file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -100);
    if (!headers_sent()) { header('Content-Type: text/plain'); }
    echo implode("\n", $lines);
  } else {
    if (!headers_sent()) { header('Content-Type: text/plain'); }
    echo 'No error.log found';
  }
  exit;
}
require_once ___DIR___ . '/../../config/config.php';

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
                    'admin' => ___DIR___ . '/../../uploads/admin_profile_photo',
                    'owner' => ___DIR___ . '/../../uploads/owner_profile_photo',
                    'customer' => ___DIR___ . '/../../uploads/user_profile_photo',
                ];
                $target_dir = $dir_map[$display['role']] ?? (___DIR___ . '/../../uploads/user_profile_photo');
                if (!is_dir($target_dir)) { @mkdir($target_dir, 0777, true); }
                $fname = 'u' . $uid . '_' . time() . '.' . $ext;
                $dest = rtrim($target_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fname;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dest)) {
                    $rel_base = str_replace('\\', '/', str_replace(___DIR___ . '/../../', '', $target_dir));
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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
    /* ===========================
       PROFILE PAGE CUSTOM STYLES
       Brand Colors: Primary #004E98, Accent #3A6EA5, Orange #FF6700
       =========================== */
    
    :root {
      --rl-primary: #004E98;
      --rl-light-bg: #EBEBEB;
      --rl-secondary: #C0C0C0;
      --rl-accent: #3A6EA5;
      --rl-dark: #FF6700;
      --rl-white: #ffffff;
      --rl-text: #1f2a37;
      --rl-text-secondary: #4a5568;
      --rl-text-muted: #718096;
      --rl-border: #e2e8f0;
      --rl-shadow-sm: 0 2px 12px rgba(0,0,0,.06);
      --rl-shadow-md: 0 4px 16px rgba(0,0,0,.1);
      --rl-shadow-lg: 0 10px 30px rgba(0,0,0,.15);
      --rl-radius: 12px;
      --rl-radius-lg: 16px;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      color: var(--rl-text);
      background: linear-gradient(180deg, #fff 0%, var(--rl-light-bg) 100%);
      min-height: 100vh;
    }
    
    /* Profile Page Container - Scoped */
    body > .container {
      padding-top: clamp(1.5rem, 3vw, 3rem);
      padding-bottom: clamp(1.5rem, 3vw, 3rem);
    }
    
    /* Profile Card - Only style cards in main container */
    body > .container .card {
      border: 2px solid var(--rl-border);
      border-radius: var(--rl-radius-lg);
      box-shadow: var(--rl-shadow-md);
      overflow: hidden;
      background: var(--rl-white);
    }
    
    body > .container .card-body {
      padding: 2rem;
    }
    
    /* Role Badge - Only in card body */
    .card-body .badge {
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-weight: 700;
      font-size: 0.875rem;
      letter-spacing: 0.5px;
    }
    
    .card-body .bg-primary {
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%) !important;
    }
    
    .card-body .bg-success {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
    }
    
    .card-body .bg-danger {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
    }
    
    .card-body .bg-secondary {
      background: linear-gradient(135deg, var(--rl-secondary) 0%, #a0a0a0 100%) !important;
    }
    
    /* Profile Image Container - Scoped to card */
    .card-body .text-center.mb-3 {
      margin-bottom: 1.5rem !important;
    }
    
    .card-body .rounded-circle {
      border: 4px solid var(--rl-border);
      box-shadow: var(--rl-shadow-md);
      transition: all 0.3s ease;
    }
    
    .card-body .rounded-circle:hover {
      transform: scale(1.05);
      box-shadow: var(--rl-shadow-lg);
      border-color: var(--rl-accent);
    }
    
    /* Profile Info List */
    .list-group {
      border-radius: var(--rl-radius);
      overflow: hidden;
      margin-bottom: 1.5rem;
    }
    
    .list-group-item {
      border: none;
      border-bottom: 1px solid var(--rl-border);
      padding: 1rem 1.25rem;
      background: transparent;
      transition: all 0.2s ease;
      font-size: 0.9375rem;
    }
    
    .list-group-item:first-child {
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
      border-top-left-radius: var(--rl-radius);
      border-top-right-radius: var(--rl-radius);
    }
    
    .list-group-item:last-child {
      border-bottom: none;
      border-bottom-left-radius: var(--rl-radius);
      border-bottom-right-radius: var(--rl-radius);
    }
    
    .list-group-item:hover {
      background: rgba(0, 78, 152, 0.02);
    }
    
    .list-group-item span:first-child {
      font-weight: 600;
      color: var(--rl-text-secondary);
      text-transform: uppercase;
      font-size: 0.8125rem;
      letter-spacing: 0.5px;
    }
    
    .list-group-item span:last-child {
      font-weight: 600;
      color: var(--rl-text);
    }
    
    /* Horizontal Rule - Scoped to card */
    .card-body hr {
      border-color: var(--rl-border);
      opacity: 1;
      margin: 1.5rem 0;
    }
    
    /* Section Heading - Scoped to card */
    .card-body h5 {
      font-weight: 800;
      color: var(--rl-text);
      font-size: 1.25rem;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .card-body h5::before {
      content: '';
      width: 4px;
      height: 1.5rem;
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
      border-radius: 2px;
    }
    
    /* Form Inputs */
    .form-label {
      font-weight: 600;
      color: var(--rl-text);
      margin-bottom: 0.5rem;
      font-size: 0.9375rem;
    }
    
    .form-control {
      border: 2px solid var(--rl-border);
      border-radius: 10px;
      padding: 0.625rem 0.875rem;
      font-size: 0.9375rem;
      color: var(--rl-text);
      background: var(--rl-white);
      transition: all 0.2s ease;
      font-weight: 500;
    }
    
    .form-control:focus {
      border-color: var(--rl-primary);
      box-shadow: 0 0 0 3px rgba(0, 78, 152, 0.1);
      outline: none;
    }
    
    .form-control:hover:not(:focus) {
      border-color: #cbd5e0;
    }
    
    /* File Input */
    input[type="file"].form-control {
      padding: 0.5rem 0.875rem;
      cursor: pointer;
    }
    
    /* Validation Feedback */
    .invalid-feedback {
      font-size: 0.8125rem;
      font-weight: 500;
      margin-top: 0.25rem;
    }
    
    .was-validated .form-control:invalid {
      border-color: #ef4444;
    }
    
    .was-validated .form-control:valid {
      border-color: #10b981;
    }
    
    /* Buttons - Scoped to card body */
    .card-body .btn {
      font-weight: 600;
      border-radius: 10px;
      padding: 0.625rem 1.25rem;
      transition: all 0.2s ease;
      font-size: 0.9375rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.375rem;
      border: none;
    }
    
    .card-body .btn-primary {
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
      color: var(--rl-white);
      box-shadow: 0 4px 16px rgba(0, 78, 152, 0.25);
    }
    
    .card-body .btn-primary:hover {
      background: linear-gradient(135deg, #003a75 0%, #2d5a8f 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 24px rgba(0, 78, 152, 0.35);
      color: var(--rl-white);
    }
    
    .card-body .btn-primary:active {
      transform: translateY(0);
    }
    
    .card-body .btn-secondary {
      background: var(--rl-secondary);
      color: var(--rl-text);
    }
    
    .card-body .btn-secondary:hover {
      background: #a0a0a0;
      transform: translateY(-1px);
      color: var(--rl-text);
    }
    
    .card-body .btn-outline-primary {
      border: 2px solid var(--rl-primary);
      color: var(--rl-primary);
      background: transparent;
    }
    
    .card-body .btn-outline-primary:hover {
      background: var(--rl-primary);
      border-color: var(--rl-primary);
      color: var(--rl-white);
      transform: translateY(-1px);
    }
    
    .card-body .btn-outline-success {
      border: 2px solid #10b981;
      color: #059669;
      background: transparent;
    }
    
    .card-body .btn-outline-success:hover {
      background: #10b981;
      border-color: #10b981;
      color: var(--rl-white);
      transform: translateY(-1px);
    }
    
    .card-body .btn-outline-danger {
      border: 2px solid #ef4444;
      color: #dc2626;
      background: transparent;
    }
    
    .card-body .btn-outline-danger:hover {
      background: #ef4444;
      border-color: #dc2626;
      color: var(--rl-white);
      transform: translateY(-1px);
    }
    
    /* Button Container - Scoped to card */
    .card-body .d-flex.flex-wrap.gap-2 {
      margin-top: 1.5rem;
    }
    
    /* No Image Placeholder - Scoped to card */
    .card-body .bg-secondary.d-inline-flex {
      background: linear-gradient(135deg, var(--rl-secondary) 0%, #a0a0a0 100%) !important;
      font-size: 0.75rem;
    }
    
    /* Responsive - Scoped to profile page */
    @media (max-width: 767px) {
      body > .container .card-body {
        padding: 1.5rem;
      }
      
      .list-group-item {
        padding: 0.875rem 1rem;
        font-size: 0.875rem;
      }
      
      .list-group-item span:first-child {
        font-size: 0.75rem;
      }
      
      .card-body h5 {
        font-size: 1.125rem;
      }
      
      .card-body .btn {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
      }
      
      .card-body .d-flex.flex-wrap.gap-2 {
        flex-direction: column;
      }
      
      .card-body .d-flex.flex-wrap.gap-2 .btn {
        width: 100%;
      }
      
      .card-body .ms-auto {
        margin-left: 0 !important;
      }
      
      .card-body .d-flex.gap-2 {
        flex-direction: column;
      }
      
      .card-body .d-flex.gap-2 .btn {
        width: 100%;
      }
    }
    
    @media (max-width: 575px) {
      body > .container .card-body {
        padding: 1.25rem;
      }
      
      .card-body .rounded-circle {
        width: 80px !important;
        height: 80px !important;
      }
      
      .card-body .badge {
        padding: 0.375rem 0.875rem;
        font-size: 0.75rem;
      }
    }
    
    /* SweetAlert2 Custom Styling */
    .swal2-popup {
      font-family: 'Inter', sans-serif;
      border-radius: var(--rl-radius-lg);
    }
    
    .swal2-confirm {
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%) !important;
      border-radius: 10px !important;
      font-weight: 600 !important;
    }
    
    .swal2-cancel {
      border-radius: 10px !important;
      font-weight: 600 !important;
    }
  </style>
</head>
<body>
<?php require_once ___DIR___ . '/navbar.php'; ?>
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
              <button class="btn btn-primary" type="submit">Save Changes</button>
              <?php if ($display['role'] === 'customer'): ?>
                <button class="btn btn-outline-danger" name="action" value="delete_user_account" type="submit">Delete Account</button>
              <?php endif; ?>
            </div>
          </form>

        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  (() => {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
      form.addEventListener('submit', async (event) => {
        const submitter = event.submitter || document.activeElement;
        // Bootstrap validation
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
          form.classList.add('was-validated');
          return;
        }
        // SweetAlert2 confirm for delete account
        if (submitter && submitter.name === 'action' && submitter.value === 'delete_user_account') {
          event.preventDefault();
          try {
            const res = await Swal.fire({
              title: 'Delete your account?',
              text: 'This action cannot be undone.',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Yes, delete',
              cancelButtonText: 'Cancel'
            });
            if (res.isConfirmed) { form.submit(); }
          } catch (_) { form.submit(); }
        }
      }, false);
    });
  })();
</script>
</body>
</html>
