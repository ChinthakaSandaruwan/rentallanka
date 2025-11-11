<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');
if (isset($_GET['show_errors']) && $_GET['show_errors'] === '1') {
  $f = __DIR__ . '/error.log';
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
require_once __DIR__ . '/../config/config.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if ($name === '' || $password === '') {
        $err = 'name and password required';
    } else {
        $stmt = db()->prepare('SELECT super_admin_id, password_hash, status FROM super_admins WHERE name = ? LIMIT 1');
        $stmt->bind_param('s', $name);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body class="py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card shadow-sm">
          <div class="card-body">
            <h3 class="mb-3 text-center">Super Admin Login</h3>
            <?php if ($err): ?><?php endif; ?>
            <form method="post" class="vstack gap-3">
              <div>
                <label class="form-label">name</label>
                <input type="text" class="form-control" name="name" required />
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function(){
        var err = <?php echo json_encode($err); ?>;
        if (err) {
          Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: err, showConfirmButton: false, timer: 3000, timerProgressBar: true });
        }
      });
    </script>
</body>
</html>
