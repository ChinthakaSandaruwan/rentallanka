<?php
require_once __DIR__ . '/../config/config.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $err = 'Username and password required';
    } else {
        $stmt = db()->prepare('SELECT super_admin_id, password_hash, status FROM super_admins WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $sa = $res->fetch_assoc();
        $stmt->close();
        if (!$sa || $sa['status'] !== 'active' || !password_verify($password, $sa['password_hash'])) {
            $err = 'Invalid credentials';
        } else {
            $_SESSION['super_admin_id'] = (int)$sa['super_admin_id'];
            $_SESSION['loggedin'] = true;
            $_SESSION['role'] = 'super_admin';
            redirect_with_message($base_url . '/index.php', 'Welcome Super Admin');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Super Admin Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card shadow-sm">
          <div class="card-body">
            <h3 class="mb-3 text-center">Super Admin Login</h3>
            <?php if ($err): ?><div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
            <form method="post" class="vstack gap-3">
              <div>
                <label class="form-label">Username</label>
                <input type="text" class="form-control" name="username" required />
              </div>
              <div>
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" required />
              </div>
              <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
