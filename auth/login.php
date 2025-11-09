<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/otp_helper.php';

function normalize_phone_07(string $phone): string {
    $p = preg_replace('/\D+/', '', $phone);
    if (preg_match('/^07[01245678][0-9]{7}$/', $p)) {
        return $p; // keep as 07-format for storage and display
    }
    return '';
}

function to_e164_for_sms(string $phone07): string {
    // Assumes valid 07xxxxxxxx
    return '+94' . substr($phone07, 1);
}

$stage = $_SESSION['otp_stage'] ?? 'request';
$error = '';
$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'request') {
        $phone_in = trim($_POST['phone'] ?? '');
        $phone07 = normalize_phone_07($phone_in);
        if ($phone07 === '') {
            $error = 'Enter valid Sri Lanka phone number';
            $stage = 'request';
        } else {
            // Block super admins here: they must use the dedicated super admin login
            $stmt = db()->prepare('SELECT super_admin_id FROM super_admins WHERE phone = ? LIMIT 1');
            $stmt->bind_param('s', $phone07);
            $stmt->execute();
            $sa_res = $stmt->get_result();
            $super = $sa_res->fetch_assoc();
            $stmt->close();
            if ($super) {
                $error = 'This number is registered as Super Admin. Please use Super Admin login.';
                $stage = 'request';
            } else {
                // Regular users table lookup; if not found, redirect to register
                $stmt = db()->prepare('SELECT user_id, role, status FROM users WHERE phone = ?');
                $stmt->bind_param('s', $phone07);
                $stmt->execute();
                $res = $stmt->get_result();
                $user = $res->fetch_assoc();
                $stmt->close();
                if (!$user) {
                    redirect_with_message($base_url . '/auth/register.php?phone=' . urlencode($phone07), 'No account found. Please register.', 'info');
                }
                // Allow only customer and owner via this login
                $role = (string)($user['role'] ?? '');
                if ($role !== 'customer' && $role !== 'owner') {
                    $error = 'This login is only for Customers and Owners. Please use the appropriate admin login.';
                    $stage = 'request';
                } else {
                $uid = (int)$user['user_id'];
                $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
                $stmt = db()->prepare('INSERT INTO user_otps (user_id, otp_code, expires_at, is_verified) VALUES (?, ?, ?, 0)');
                $stmt->bind_param('iss', $uid, $otp, $expires);
                $stmt->execute();
                $stmt->close();
                $_SESSION['otp_stage'] = 'verify';
                $_SESSION['otp_user_id'] = $uid;
                $_SESSION['otp_phone'] = $phone07;
                $sms = 'Your OTP code is ' . $otp . '. It expires in 5 minutes.';
                sendOtp($phone07, $otp, $sms);
                $info = 'OTP sent to ' . $phone07;
                $stage = 'verify';
                }
            }
        }
    } elseif ($action === 'verify') {
        $phone07 = $_SESSION['otp_phone'] ?? '';
        $code = trim($_POST['otp'] ?? '');
        if (!preg_match('/^\d{6}$/', $code)) {
            $error = 'Invalid OTP';
            $stage = 'verify';
        } else {
            // Regular user OTP verification via database table
            $uid = (int)($_SESSION['otp_user_id'] ?? 0);
            if ($uid <= 0) {
                $error = 'Invalid OTP';
                $stage = 'verify';
            } else {
                $stmt = db()->prepare('SELECT o.otp_id, u.user_id, u.role, u.status FROM user_otps o JOIN users u ON u.user_id = o.user_id WHERE o.user_id = ? AND o.otp_code = ? AND o.is_verified = 0 AND o.expires_at >= NOW() ORDER BY o.created_at DESC LIMIT 1');
                $stmt->bind_param('is', $uid, $code);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();
                if (!$row) {
                    $error = 'OTP is invalid or expired';
                    $stage = 'verify';
                } else {
                    // Enforce role again at verification
                    $r = (string)($row['role'] ?? '');
                    if ($r !== 'customer' && $r !== 'owner') {
                        $error = 'This login is only for Customers and Owners. Please use the appropriate admin login.';
                        $stage = 'request';
                        unset($_SESSION['otp_stage'], $_SESSION['otp_user_id'], $_SESSION['otp_phone']);
                    } else {
                    $otp_id = (int)$row['otp_id'];
                    $stmt = db()->prepare('UPDATE user_otps SET is_verified = 1 WHERE otp_id = ?');
                    $stmt->bind_param('i', $otp_id);
                    $stmt->execute();
                    $stmt->close();
                    $_SESSION['user'] = [
                        'user_id' => (int)$row['user_id'],
                        'phone' => $phone07,
                        'role' => $row['role'],
                    ];
                    $_SESSION['loggedin'] = true;
                    $_SESSION['role'] = $row['role'];
                    unset($_SESSION['otp_stage'], $_SESSION['otp_user_id'], $_SESSION['otp_phone']);
                    redirect_with_message($base_url . '/index.php', 'Logged in');
                    }
                }
            }
        }
    } elseif ($action === 'reset') {
        unset($_SESSION['otp_stage'], $_SESSION['otp_user_id'], $_SESSION['otp_phone']);
        $stage = 'request';
    }
}

[$flash, $flash_type] = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <meta name="robots" content="noindex,nofollow" />
    <meta http-equiv="Cache-Control" content="no-store" />
</head>
<body>
    <div class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
        <div class="row w-100 justify-content-center">
            <div class="col-12 col-md-6 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h3 class="mb-3 text-center">Login with OTP</h3>
                        <?php if ($flash): ?>
                            <div class="alert alert-info"><?php echo htmlspecialchars($flash); ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($info): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($info); ?></div>
                        <?php endif; ?>
                        <?php if ($stage === 'request'): ?>
                            <form method="post" class="vstack gap-3">
                                <div>
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone" maxlength="10" pattern="^[0]{1}[7]{1}[01245678]{1}[0-9]{7}$" title="Enter number like 07XXXXXXXX" placeholder="e.g. 07XXXXXXXX" required />
                                </div>
                                <input type="hidden" name="action" value="request" />
                                <button type="submit" class="btn btn-primary w-100">Send OTP</button>
                            </form>
                            <a href="<?php echo $base_url; ?>/auth/register.php" class="btn btn-outline-secondary w-100 mt-2">Don't have an account? Register</a>
                        <?php else: ?>
                            <form method="post" class="vstack gap-3">
                                <div>
                                    <label class="form-label">Enter OTP</label>
                                    <input type="text" class="form-control" name="otp" maxlength="6" pattern="\d{6}" placeholder="6-digit code" required />
                                </div>
                                <input type="hidden" name="action" value="verify" />
                                <button type="submit" class="btn btn-primary w-100">Verify & Login</button>
                            </form>
                            <form method="post" class="mt-2">
                                <input type="hidden" name="action" value="reset" />
                                <button type="submit" class="btn btn-secondary w-100">Use different number</button>
                            </form>
                        <?php endif; ?>
                        <p class="text-muted small mt-3 text-center">Testing sender_id: SMSlenzDEMO. Configure real credentials in environment.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>