<?php
require_once __DIR__ . '/../../config/config.php';

function sa_normalize_phone_07(string $phone): string {
    $p = preg_replace('/\D+/', '', $phone);
    if (preg_match('/^07[0-9]{8}$/', $p)) { return $p; }
    return '';
}

function sa_to_e164(string $phone07): string {
    return '+94' . substr($phone07, 1);
}

$error = '';
$info = '';
$stage = $_SESSION['sa_stage'] ?? 'phone';
$pending = $_SESSION['sa_pending'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'send_otp') {
        $phone_input = trim($_POST['phone'] ?? '');
        $phone07 = sa_normalize_phone_07($phone_input);
        if ($phone07 === '') {
            $error = 'Enter a valid phone number starting with 07';
            $stage = 'phone';
        } else {
            $stmt = db()->prepare('SELECT super_admin_id, name, phone, status, password_hash FROM super_admins WHERE phone = ? LIMIT 1');
            $stmt->bind_param('s', $phone07);
            $stmt->execute();
            $res = $stmt->get_result();
            $sa = $res->fetch_assoc();
            $stmt->close();
            if (!$sa || $sa['status'] !== 'active') {
                $error = 'No active super admin found for this phone';
                $stage = 'phone';
            } else {
                $has_password = isset($sa['password_hash']) && (string)$sa['password_hash'] !== '';
                if ($has_password) {
                    $_SESSION['sa_stage'] = 'credentials';
                    $_SESSION['sa_pending'] = [
                        'super_admin_id' => (int)$sa['super_admin_id'],
                        'name' => (string)$sa['name'],
                        'phone07' => $phone07,
                        'attempts' => 0,
                    ];
                    $stage = 'credentials';
                    $info = 'Enter username and password to continue';
                } else {
                    $otp_len = max(4, min(8, (int)setting_get('otp_length', '6')));
                    $otp_exp_min = max(1, min(60, (int)setting_get('otp_expiry_minutes', '5')));
                    $max = (10 ** $otp_len) - 1;
                    $otp_num = random_int(0, $max);
                    $otp = str_pad((string)$otp_num, $otp_len, '0', STR_PAD_LEFT);
                    $expires_at = (new DateTime('+' . $otp_exp_min . ' minutes'))->getTimestamp();
                    $_SESSION['sa_stage'] = 'otp';
                    $_SESSION['sa_pending'] = [
                        'super_admin_id' => (int)$sa['super_admin_id'],
                        'name' => (string)$sa['name'],
                        'phone07' => $phone07,
                        'otp' => $otp,
                        'expires_ts' => $expires_at,
                        'attempts' => 0,
                    ];
                    $exp_dt = (new DateTime('+' . $otp_exp_min . ' minutes'))->format('Y-m-d H:i:s');
                    $stmt2 = db()->prepare('INSERT INTO super_admin_otps (super_admin_id, otp_code, expires_at, is_verified) VALUES (?, ?, ?, 0)');
                    $sid = (int)$sa['super_admin_id'];
                    $stmt2->bind_param('iss', $sid, $otp, $exp_dt);
                    $stmt2->execute();
                    $stmt2->close();
                    $prefix = trim((string)setting_get('otp_sms_prefix', 'OTP'));
                    $sms = ($prefix !== '' ? ($prefix . ' ') : '') . $otp . ' (expires in ' . $otp_exp_min . ' min)';
                    smslenz_send_sms(sa_to_e164($phone07), $sms);
                    $info = 'OTP sent to ' . $phone07;
                    $stage = 'otp';
                }
            }
        }
    } elseif ($action === 'login') {
        // Verify username/password for the selected super admin, then send OTP
        $pending = $_SESSION['sa_pending'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        if (!$pending || $name === '' || $password === '') {
            $error = 'Invalid request';
            $stage = 'phone';
            unset($_SESSION['sa_stage'], $_SESSION['sa_pending']);
        } else {
            $sid = (int)$pending['super_admin_id'];
            $stmt = db()->prepare('SELECT super_admin_id, name, password_hash, phone, status FROM super_admins WHERE super_admin_id = ? AND name = ? LIMIT 1');
            $stmt->bind_param('is', $sid, $name);
            $stmt->execute();
            $res = $stmt->get_result();
            $sa = $res->fetch_assoc();
            $stmt->close();
            if (!$sa || $sa['status'] !== 'active' || !(isset($sa['password_hash']) && (string)$sa['password_hash'] !== '')) {
                $error = 'Invalid credentials';
                $stage = 'credentials';
            } else {
                $ok = password_verify($password, (string)$sa['password_hash']);
                if (!$ok) {
                    $error = 'Invalid credentials';
                    $stage = 'credentials';
                } else {
                    $otp_len = max(4, min(8, (int)setting_get('otp_length', '6')));
                    $otp_exp_min = max(1, min(60, (int)setting_get('otp_expiry_minutes', '5')));
                    $max = (10 ** $otp_len) - 1;
                    $otp_num = random_int(0, $max);
                    $otp = str_pad((string)$otp_num, $otp_len, '0', STR_PAD_LEFT);
                    $expires_at = (new DateTime('+' . $otp_exp_min . ' minutes'))->getTimestamp();
                    $_SESSION['sa_stage'] = 'otp';
                    $_SESSION['sa_pending'] = [
                        'super_admin_id' => (int)$sa['super_admin_id'],
                        'name' => (string)$sa['name'],
                        'phone07' => sa_normalize_phone_07((string)$sa['phone']),
                        'otp' => $otp,
                        'expires_ts' => $expires_at,
                        'attempts' => 0,
                    ];
                    $exp_dt = (new DateTime('+' . $otp_exp_min . ' minutes'))->format('Y-m-d H:i:s');
                    $stmt2 = db()->prepare('INSERT INTO super_admin_otps (super_admin_id, otp_code, expires_at, is_verified) VALUES (?, ?, ?, 0)');
                    $sid2 = (int)$sa['super_admin_id'];
                    $stmt2->bind_param('iss', $sid2, $otp, $exp_dt);
                    $stmt2->execute();
                    $stmt2->close();
                    $prefix = trim((string)setting_get('otp_sms_prefix', 'OTP'));
                    $sms = ($prefix !== '' ? ($prefix . ' ') : '') . $otp . ' (expires in ' . $otp_exp_min . ' min)';
                    $phone07 = sa_normalize_phone_07((string)$sa['phone']);
                    if ($phone07 !== '') {
                        smslenz_send_sms(sa_to_e164($phone07), $sms);
                    }
                    $info = 'OTP sent to ' . ($phone07 ?: 'linked number');
                    $stage = 'otp';
                }
            }
        }
    } elseif ($action === 'verify_otp') {
        $code = trim($_POST['otp'] ?? '');
        $pending = $_SESSION['sa_pending'] ?? null;
        if (!$pending) {
            $error = 'Session expired. Please log in again';
            $stage = 'phone';
            unset($_SESSION['sa_stage'], $_SESSION['sa_pending']);
        } elseif (!preg_match('/^\d+$/', $code)) {
            $error = 'Invalid OTP';
            $stage = 'otp';
        } else {
            $max_attempts = max(1, min(20, (int)setting_get('otp_max_attempts', '5')));
            $now = time();
            if ($pending['attempts'] >= $max_attempts) {
                $error = 'Too many attempts. Please log in again';
                $stage = 'phone';
                unset($_SESSION['sa_stage'], $_SESSION['sa_pending']);
            } else {
                $sid = (int)$pending['super_admin_id'];
                $stmtv = db()->prepare('SELECT sa_otp_id FROM super_admin_otps WHERE super_admin_id = ? AND otp_code = ? AND is_verified = 0 AND expires_at >= NOW() ORDER BY created_at DESC LIMIT 1');
                if ($stmtv) {
                    $stmtv->bind_param('is', $sid, $code);
                    $stmtv->execute();
                    $resv = $stmtv->get_result();
                    $rowv = $resv->fetch_assoc();
                    $stmtv->close();
                } else {
                    $rowv = null;
                }
                if (!$rowv) {
                    $_SESSION['sa_pending']['attempts'] = ((int)$pending['attempts']) + 1;
                    $error = 'OTP is invalid or expired';
                    $stage = 'otp';
                } else {
                    $otp_id = (int)$rowv['sa_otp_id'];
                    $stmtu = db()->prepare('UPDATE super_admin_otps SET is_verified = 1 WHERE sa_otp_id = ?');
                    $stmtu->bind_param('i', $otp_id);
                    $stmtu->execute();
                    $stmtu->close();
                    $_SESSION['super_admin_id'] = $sid;
                    unset($_SESSION['sa_stage'], $_SESSION['sa_pending']);
                    redirect_with_message($base_url . '/superAdmin/includes/profile.php', 'Logged in');
                }
            }
        }
    } elseif ($action === 'resend_otp') {
        $pending = $_SESSION['sa_pending'] ?? null;
        if (!$pending) {
            $error = 'Session expired. Please log in again';
            $stage = 'phone';
            unset($_SESSION['sa_stage'], $_SESSION['sa_pending']);
        } else {
            $otp_len = max(4, min(8, (int)setting_get('otp_length', '6')));
            $otp_exp_min = max(1, min(60, (int)setting_get('otp_expiry_minutes', '5')));
            $max = (10 ** $otp_len) - 1;
            $otp_num = random_int(0, $max);
            $otp = str_pad((string)$otp_num, $otp_len, '0', STR_PAD_LEFT);
            $_SESSION['sa_pending']['otp'] = $otp;
            $_SESSION['sa_pending']['expires_ts'] = (new DateTime('+' . $otp_exp_min . ' minutes'))->getTimestamp();
            $_SESSION['sa_pending']['attempts'] = 0;
            $exp_dt = (new DateTime('+' . $otp_exp_min . ' minutes'))->format('Y-m-d H:i:s');
            $sid = (int)$pending['super_admin_id'];
            $stmt2 = db()->prepare('INSERT INTO super_admin_otps (super_admin_id, otp_code, expires_at, is_verified) VALUES (?, ?, ?, 0)');
            $stmt2->bind_param('iss', $sid, $otp, $exp_dt);
            $stmt2->execute();
            $stmt2->close();
            $prefix = trim((string)setting_get('otp_sms_prefix', 'OTP'));
            $sms = ($prefix !== '' ? ($prefix . ' ') : '') . $otp . ' (expires in ' . $otp_exp_min . ' min)';
            smslenz_send_sms(sa_to_e164($pending['phone07']), $sms);
            $info = 'OTP resent';
            $stage = 'otp';
        }
    } elseif ($action === 'reset') {
        unset($_SESSION['sa_stage'], $_SESSION['sa_pending']);
        $stage = 'phone';
    }
}

[$flash, $flash_type] = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <meta name="robots" content="noindex,nofollow" />
    <meta http-equiv="Cache-Control" content="no-store" />
    <style>body{background:#f6f7fb}</style>
    </head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-6 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h3 class="mb-3 text-center">Super Admin Login</h3>
                        <?php if ($flash): ?>
                            <div class="alert alert-info"><?php echo htmlspecialchars($flash); ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($info): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($info); ?></div>
                        <?php endif; ?>

                        <?php if ($stage === 'phone'): ?>
                        <form method="post" class="vstack gap-3">
                            <div>
                                <label class="form-label">Phone number</label>
                                <input type="text" class="form-control" name="phone" placeholder="07XXXXXXXX" required />
                            </div>
                            <input type="hidden" name="action" value="send_otp" />
                            <button type="submit" class="btn btn-primary w-100">Send OTP</button>
                        </form>
                        <?php elseif ($stage === 'credentials'): ?>
                        <form method="post" class="vstack gap-3">
                            <div>
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="name" required />
                            </div>
                            <div>
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password" id="sa_password" required />
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Show password">
                                        <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="action" value="login" />
                            <button type="submit" class="btn btn-primary w-100">Continue</button>
                        </form>
                        <?php else: ?>
                        <form method="post" class="vstack gap-3">
                            <div>
                                <label class="form-label">Enter OTP</label>
                                <input type="text" class="form-control" name="otp" pattern="\d+" maxlength="8" placeholder="Code" required />
                            </div>
                            <input type="hidden" name="action" value="verify_otp" />
                            <button type="submit" class="btn btn-primary w-100">Verify & Login</button>
                        </form>
                        <form method="post" class="mt-2 d-flex gap-2">
                            <input type="hidden" name="action" value="resend_otp" />
                            <button type="submit" class="btn btn-outline-primary w-50">Resend OTP</button>
                        </form>
                        <form method="post" class="mt-2">
                            <input type="hidden" name="action" value="reset" />
                            <button type="submit" class="btn btn-secondary w-100">Use different account</button>
                        </form>
                        <?php endif; ?>
                        <p class="text-muted small mt-3 text-center">For super admins only</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function(){
            var btn = document.getElementById('togglePassword');
            var input = document.getElementById('sa_password');
            var icon = document.getElementById('togglePasswordIcon');
            if (btn && input && icon) {
                btn.addEventListener('click', function(){
                    var isPwd = input.getAttribute('type') === 'password';
                    input.setAttribute('type', isPwd ? 'text' : 'password');
                    icon.classList.toggle('bi-eye');
                    icon.classList.toggle('bi-eye-slash');
                    btn.setAttribute('aria-label', isPwd ? 'Hide password' : 'Show password');
                });
            }
        })();
    </script>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

</body>
</html>
