<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
    $f = __DIR__ . '/../error/error.log';
    if (is_readable($f)) {
        $lines = 100; $data = '';
        $fp = fopen($f, 'r');
        if ($fp) {
            fseek($fp, 0, SEEK_END); $pos = ftell($fp); $chunk = ''; $ln = 0;
            while ($pos > 0 && $ln <= $lines) {
                $step = max(0, $pos - 4096); $read = $pos - $step;
                fseek($fp, $step); $chunk = fread($fp, $read) . $chunk; $pos = $step;
                $ln = substr_count($chunk, "\n");
            }
            fclose($fp);
            $parts = explode("\n", $chunk); $slice = array_slice($parts, -$lines); $data = implode("\n", $slice);
        }
        header('Content-Type: text/plain; charset=utf-8'); echo $data; exit;
    }
}

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
                    redirect_with_message($base_url . '/index.php', 'Logged In');
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
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <meta name="robots" content="noindex,nofollow" />
    <meta http-equiv="Cache-Control" content="no-store" />

    <!-- Optional modern font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom theme layer (keeps Bootstrap, only adds styles) -->
    <style>
      /* Brand palette as CSS variables for reuse */
      :root {
        --rl-primary: #004E98;  /* Primary */
        --rl-bg: #EBEBEB;       /* Light background */
        --rl-secondary: #C0C0C0;/* Secondary */
        --rl-accent: #3A6EA5;   /* Accent */
        --rl-warm: #FF6700;     /* Dark (brand warm) */

        --rl-text: #1a1a1a;
        --rl-muted: #6b7280;
        --rl-white: #ffffff;

        --rl-radius: 14px;
        --rl-shadow-sm: 0 2px 10px rgba(0,0,0,.08);
        --rl-shadow-md: 0 8px 24px rgba(0,0,0,.12);
        --rl-focus: 0 0 0 .25rem rgb(0 78 152 / 35%);
      }

      html, body {
        height: 100%;
        background: var(--rl-bg);
        color: var(--rl-text);
        font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Helvetica Neue", Helvetica, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", sans-serif;
        font-size: clamp(15px, 1.05vw + .5rem, 16px);
      }

      /* Card styling */
      .rl-auth-card {
        border-radius: var(--rl-radius);
        box-shadow: var(--rl-shadow-md);
        background: var(--rl-white);
        border: 1px solid color-mix(in srgb, var(--rl-secondary) 55%, transparent);
      }

      /* Headings & subtle text */
      .rl-page-title { font-weight: 700; letter-spacing: -0.01em; }
      .rl-page-subtle { color: var(--rl-muted); font-size: .95rem; }

      /* Labels */
      .rl-form-label { font-weight: 600; color: #0f172a; }

      /* Inputs: scoped to .rl-input so Bootstrap defaults remain elsewhere */
      .form-control.rl-input {
        border-radius: 12px;
        border: 1px solid color-mix(in srgb, var(--rl-secondary) 70%, transparent);
        background: #fcfcfd;
        transition: border-color 150ms ease, background 150ms ease, box-shadow 150ms ease;
      }
      .form-control.rl-input:focus {
        border-color: var(--rl-primary);
        box-shadow: var(--rl-focus);
        background: #ffffff;
      }
      .form-control.rl-input::placeholder {
        color: color-mix(in srgb, var(--rl-muted) 80%, #fff 0%);
      }

      /* Checkboxes */
      .form-check-input.rl-check:focus { box-shadow: var(--rl-focus); border-color: var(--rl-primary); }
      .form-check-input.rl-check:checked { background-color: var(--rl-primary); border-color: var(--rl-primary); }

      /* Buttons */
      .btn.rl-btn-primary {
        --bs-btn-padding-y: .6rem;
        --bs-btn-padding-x: 1rem;
        --bs-btn-font-weight: 600;
        border-radius: 12px;
        border: 1px solid color-mix(in srgb, var(--rl-primary) 35%, var(--rl-accent) 65%);
        color: #fff;
        background: linear-gradient(135deg, var(--rl-primary), var(--rl-accent));
        box-shadow: 0 6px 16px color-mix(in srgb, var(--rl-primary) 28%, transparent);
        transition: transform 120ms ease, box-shadow 180ms ease, filter 180ms ease;
      }
      .btn.rl-btn-primary:hover { filter: brightness(1.05); transform: translateY(-1px); box-shadow: 0 10px 22px color-mix(in srgb, var(--rl-accent) 28%, transparent); }
      .btn.rl-btn-primary:focus-visible { box-shadow: var(--rl-focus); }

      .btn.rl-btn-ghost {
        border-radius: 12px;
        border: 1px dashed color-mix(in srgb, var(--rl-secondary) 70%, transparent);
        color: var(--rl-primary);
        background: #f6f7fb;
      }
      .btn.rl-btn-ghost:hover { background: #eef2f9; }

      /* Helpers */
      .rl-tip {
        background: color-mix(in srgb, var(--rl-warm) 90%, #fff 0%);
        color: #1a1a1a;
      }
    </style>
</head>
<body>
    <div class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
        <div class="row w-100 justify-content-center">
            <div class="col-12 col-md-6 col-lg-5">
                <div class="card rl-auth-card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h3 class="rl-page-title h3 mb-1 text-center">Login with OTP</h3>
                        <p class="rl-page-subtle text-center mb-3">Secure, fast, and mobile-friendly.</p>
                        <?php /* Alerts handled by SweetAlert2 below; Bootstrap alerts removed */ ?>
                        <?php if ($stage === 'request'): ?>
                            <form method="post" class="vstack gap-3" id="formOtpRequest">
                                <div>
                                    <label class="form-label rl-form-label">Phone</label>
                                    <input type="text" class="form-control rl-input" name="phone" maxlength="10" pattern="^[0]{1}[7]{1}[01245678]{1}[0-9]{7}$" title="Enter number like 07XXXXXXXX" placeholder="e.g. 07XXXXXXXX" required />
                                </div>
                                <input type="hidden" name="action" value="request" />
                                <button type="submit" class="btn rl-btn-primary w-100">Send OTP</button>
                            </form>
                            <a href="<?php echo $base_url; ?>/auth/register.php" class="btn rl-btn-ghost w-100 mt-2">Don't have an account? Register</a>
                        <?php else: ?>
                            <form method="post" class="vstack gap-3" id="formOtpVerify">
                                <div>
                                    <label class="form-label rl-form-label">Enter OTP</label>
                                    <input type="text" class="form-control rl-input" name="otp" maxlength="6" pattern="\d{6}" placeholder="6-digit code" required />
                                </div>
                                <input type="hidden" name="action" value="verify" />
                                <button type="submit" class="btn rl-btn-primary w-100">Verify & Login</button>
                            </form>
                            <form method="post" class="mt-2">
                                <input type="hidden" name="action" value="reset" />
                                <button type="submit" class="btn rl-btn-ghost w-100">Use different number</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      (function(){
        try {
          const flash = <?= json_encode($flash) ?>;
          const error = <?= json_encode($error) ?>;
          const info  = <?= json_encode($info) ?>;
          const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
          });
          if (error) {
            Toast.fire({ icon: 'error', title: String(error) });
          } else if (info) {
            Toast.fire({ icon: 'success', title: String(info) });
          } else if (flash) {
            Toast.fire({ icon: 'info', title: String(flash) });
          }

          // No confirmation prompts; forms submit normally.
        } catch(_) {}
      })();
    </script>
</body>
</html>