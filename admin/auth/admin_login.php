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

// Helpers
function admin_normalize_phone_07(string $phone): string {
    $p = preg_replace('/\D+/', '', $phone);
    if (preg_match('/^0[7][01245678][0-9]{7}$/', $p)) {
        return $p;
    }
    return '';
}
function admin_to_e164(string $phone07): string { return '+94' . substr($phone07, 1); }

$stage = $_SESSION['admin_otp_stage'] ?? 'request';
$err = '';
$ok = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? 'request';
    if ($action === 'request') {
        $phone_in = trim((string)($_POST['phone'] ?? ''));
        $phone07 = admin_normalize_phone_07($phone_in);
        if ($phone07 === '') {
            $err = 'Enter a valid mobile number in 07XXXXXXXX format.';
            $stage = 'request';
        } else {
            // Disallow super admins here
            $sa = db()->prepare('SELECT super_admin_id FROM super_admins WHERE phone = ? LIMIT 1');
            if ($sa) {
                $sa->bind_param('s', $phone07);
                $sa->execute();
                $sr = $sa->get_result()->fetch_assoc();
                $sa->close();
                if ($sr) {
                    $err = 'This number is registered as Super Admin. Please use the Super Admin login page.';
                    $stage = 'request';
                }
            }
            if ($err === '') {
                // Must be an active admin in users table
                $st = db()->prepare('SELECT user_id, role, status FROM users WHERE phone = ? LIMIT 1');
                $st->bind_param('s', $phone07);
                $st->execute();
                $u = $st->get_result()->fetch_assoc();
                $st->close();
                if (!$u || (string)($u['role'] ?? '') !== 'admin') {
                    $err = 'This number is not associated with an admin account.';
                    $stage = 'request';
                } elseif (isset($u['status']) && (string)$u['status'] !== 'active') {
                    $err = 'Admin account is not active.';
                    $stage = 'request';
                } else {
                    $uid = (int)$u['user_id'];
                    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expires = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
                    $ins = db()->prepare('INSERT INTO user_otps (user_id, otp_code, expires_at, is_verified) VALUES (?, ?, ?, 0)');
                    $ins->bind_param('iss', $uid, $otp, $expires);
                    $ins->execute();
                    $ins->close();
                    $_SESSION['admin_otp_stage'] = 'verify';
                    $_SESSION['admin_otp_user_id'] = $uid;
                    $_SESSION['admin_otp_phone'] = $phone07;
                    $sms = 'Your admin OTP is ' . $otp . '. It expires in 5 minutes.';
                    smslenz_send_sms(admin_to_e164($phone07), $sms);
                    $ok = 'OTP sent to ' . $phone07;
                    $stage = 'verify';
                }
            }
        }
    } elseif ($action === 'verify') {
        $code = trim((string)($_POST['otp'] ?? ''));
        if (!preg_match('/^\d{6}$/', $code)) {
            $err = 'Invalid OTP';
            $stage = 'verify';
        } else {
            $uid = (int)($_SESSION['admin_otp_user_id'] ?? 0);
            if ($uid <= 0) {
                $err = 'Session expired. Please try again.';
                $stage = 'request';
                unset($_SESSION['admin_otp_stage'], $_SESSION['admin_otp_user_id'], $_SESSION['admin_otp_phone']);
            } else {
                $stmt = db()->prepare('SELECT o.otp_id, u.user_id, u.role, u.status FROM user_otps o JOIN users u ON u.user_id = o.user_id WHERE o.user_id = ? AND o.otp_code = ? AND o.is_verified = 0 AND o.expires_at >= NOW() ORDER BY o.created_at DESC LIMIT 1');
                $stmt->bind_param('is', $uid, $code);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();
                if (!$row) {
                    $err = 'OTP is invalid or expired';
                    $stage = 'verify';
                } elseif ((string)($row['role'] ?? '') !== 'admin') {
                    $err = 'This number is not associated with an admin account.';
                    $stage = 'request';
                    unset($_SESSION['admin_otp_stage'], $_SESSION['admin_otp_user_id'], $_SESSION['admin_otp_phone']);
                } elseif (isset($row['status']) && (string)$row['status'] !== 'active') {
                    $err = 'Admin account is not active.';
                    $stage = 'request';
                    unset($_SESSION['admin_otp_stage'], $_SESSION['admin_otp_user_id'], $_SESSION['admin_otp_phone']);
                } else {
                    $otp_id = (int)$row['otp_id'];
                    $up = db()->prepare('UPDATE user_otps SET is_verified = 1 WHERE otp_id = ?');
                    $up->bind_param('i', $otp_id);
                    $up->execute();
                    $up->close();
                    $_SESSION['user'] = [
                        'user_id' => (int)$row['user_id'],
                        'phone' => (string)($_SESSION['admin_otp_phone'] ?? ''),
                        'role' => 'admin',
                    ];
                    $_SESSION['loggedin'] = true;
                    $_SESSION['role'] = 'admin';
                    unset($_SESSION['admin_otp_stage'], $_SESSION['admin_otp_user_id'], $_SESSION['admin_otp_phone']);
                    header('Location: ' . rtrim($base_url, '/') . '/admin/index.php');
                    exit;
                }
            }
        }
    } elseif ($action === 'reset') {
        unset($_SESSION['admin_otp_stage'], $_SESSION['admin_otp_user_id'], $_SESSION['admin_otp_phone']);
        $stage = 'request';
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
            <p class="text-muted small mb-3 text-center">Only phone + OTP for Admin accounts.</p>
            <?php if ($err): ?>
              <div class="alert alert-danger" role="alert"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>
            <?php if ($ok): ?>
              <div class="alert alert-success" role="alert"><?= htmlspecialchars($ok) ?></div>
            <?php endif; ?>

            <?php if ($stage === 'request'): ?>
              <form method="post" class="needs-validation mb-3" novalidate>
                <div class="mb-3">
                  <label for="admin_phone" class="form-label">Mobile Number</label>
                  <input type="text" id="admin_phone" name="phone" class="form-control" placeholder="07XXXXXXXX" inputmode="tel" pattern="^[0]{1}[7]{1}[01245678]{1}[0-9]{7}$" minlength="10" maxlength="10" required>
                  <div class="invalid-feedback">Enter a valid number like 07XXXXXXXX.</div>
                </div>
                <input type="hidden" name="action" value="request" />
                <div class="d-grid gap-2">
                  <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Send OTP</button>
                </div>
              </form>
            <?php else: ?>
              <form method="post" class="needs-validation mb-3" novalidate>
                <div class="mb-3">
                  <label for="admin_otp" class="form-label">Enter OTP</label>
                  <input type="text" id="admin_otp" name="otp" class="form-control" placeholder="6-digit code" inputmode="numeric" pattern="\d{6}" minlength="6" maxlength="6" required>
                  <div class="invalid-feedback">Enter the 6-digit code.</div>
                </div>
                <input type="hidden" name="action" value="verify" />
                <div class="d-grid gap-2">
                  <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Verify & Login</button>
                </div>
              </form>
              <form method="post" class="mb-3">
                <input type="hidden" name="action" value="reset" />
                <button type="submit" class="btn btn-outline-secondary w-100"><i class="bi bi-telephone me-1"></i>Use different number</button>
              </form>
            <?php endif; ?>

            <div class="d-grid gap-2">
              <a class="btn btn-outline-primary" href="<?= $base_url ?>/auth/login.php">
                <i class="bi bi-box-arrow-in-right me-1"></i> Customer/Owner Login
              </a>
              <a class="btn btn-outline-dark" href="<?= $base_url ?>/superAdmin/auth/super_admin_login.php">
                <i class="bi bi-person-badge me-1"></i> Super Admin Login
              </a>
              <a class="btn btn-outline-secondary" href="<?= $base_url ?>/">
                <i class="bi bi-house me-1"></i> Back to Home
              </a>
            </div>
            <hr class="my-4">
            <div class="small text-muted">If you believe you should have admin access, contact a system administrator.</div>
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
