<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', ___DIR___ . '/../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
    $f = ___DIR___ . '/../error/error.log';
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

require_once ___DIR___ . '/../config/config.php';
require_once ___DIR___ . '/../config/otp_helper.php';

function normalize_phone_07(string $phone): string {
    $p = preg_replace('/\D+/', '', $phone);
    if (preg_match('/^07[01245678][0-9]{7}$/', $p)) {
        return $p; // keep as 07xxxxxxxx
    }
    return '';
}

function to_e164_for_sms(string $phone07): string {
    return '+94' . substr($phone07, 1);
}

$stage = $_SESSION['reg_stage'] ?? 'request';
$error = $_SESSION['reg_error'] ?? '';
$info = $_SESSION['reg_info'] ?? '';
unset($_SESSION['reg_error'], $_SESSION['reg_info']);
$prefill_phone = normalize_phone_07($_GET['phone'] ?? '');
$allowed_roles = null; // role selection disabled; always customer

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'request') {
        $phone_in = trim($_POST['phone'] ?? '');
        $phone07 = normalize_phone_07($phone_in);
        $email = trim($_POST['email'] ?? '');
        $nic   = trim($_POST['nic'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $role  = 'customer';
        if ($phone07 === '') {
            $_SESSION['reg_error'] = 'Enter valid Sri Lanka phone number';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($email === '') {
            $_SESSION['reg_error'] = 'Email is required';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($name === '') {
            $_SESSION['reg_error'] = 'Name is required';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($nic === '') {
            $_SESSION['reg_error'] = 'NIC is required';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif (!preg_match('/^(([5,6,7,8,9]{1})([0-9]{1})([0,1,2,3,5,6,7,8]{1})([0-9]{6})([v|V|x|X]))|(([1,2]{1})([0,9]{1})([0-9]{2})([0,1,2,3,5,6,7,8]{1})([0-9]{7}))$/', $nic)) {
            $_SESSION['reg_error'] = 'Enter valid NIC';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            // If number already exists -> go to login
            $stmt = db()->prepare('SELECT user_id FROM users WHERE phone = ? LIMIT 1');
            $stmt->bind_param('s', $phone07);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($existing) {
                redirect_with_message($base_url . '/auth/login.php?phone=' . urlencode($phone07), 'Account exists. Please login.', 'info');
            }
            // Profile image removed from registration flow
            $profileUrl = null;

            // Generate OTP and store required data in session (no DB writes yet)
            $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiresAt = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');

                $_SESSION['reg_stage'] = 'verify';
                $_SESSION['reg_phone'] = $phone07;
                if ($email) { $_SESSION['reg_email'] = $email; }
                $_SESSION['reg_name'] = $name;
                $_SESSION['reg_nic'] = $nic;
                $_SESSION['reg_role'] = $role;
                $_SESSION['reg_profile'] = $profileUrl;
                $_SESSION['reg_otp'] = $otp;
                $_SESSION['reg_otp_expires'] = $expiresAt;

                $sms = 'Your Registration OTP is ' . $otp . '. It expires in 5 minutes.';
                sendOtp($phone07, $otp, $sms);
                $_SESSION['reg_info'] = 'OTP sent to ' . $phone07;
                $_SESSION['reg_dev_otp'] = $otp; // Store OTP for dev display
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
        }
    } elseif ($action === 'verify') {
        $phone07 = $_SESSION['reg_phone'] ?? '';
        $code = trim($_POST['otp'] ?? '');
        if (!preg_match('/^\d{6}$/', $code)) {
            $_SESSION['reg_error'] = 'Invalid OTP';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $sessionOtp = (string)($_SESSION['reg_otp'] ?? '');
            $expiresAt = (string)($_SESSION['reg_otp_expires'] ?? '');
            $email = (string)($_SESSION['reg_email'] ?? '');
            $name = (string)($_SESSION['reg_name'] ?? '');
            $nic = (string)($_SESSION['reg_nic'] ?? '');
            $role = (string)($_SESSION['reg_role'] ?? 'customer');
            $profileUrl = $_SESSION['reg_profile'] ?? null;

            $now = (new DateTime())->format('Y-m-d H:i:s');
            if ($sessionOtp === '' || $expiresAt === '' || $phone07 === '' || $code !== $sessionOtp || $expiresAt < $now) {
                $_SESSION['reg_error'] = 'OTP is invalid or expired';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                // Double-check NIC is still unique just before insert
                $stmtNic2 = db()->prepare('SELECT user_id FROM users WHERE nic = ? LIMIT 1');
                if ($stmtNic2) {
                    $stmtNic2->bind_param('s', $nic);
                    $stmtNic2->execute();
                    $nicRow2 = $stmtNic2->get_result()->fetch_assoc();
                    $stmtNic2->close();
                    if ($nicRow2) {
                        $_SESSION['reg_error'] = 'Registration failed. NIC already exists.';
                        unset($_SESSION['reg_stage']);
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    }
                }
                // Create user now that OTP is verified
                try {
                    $status = 'active';
                    $stmt = db()->prepare('INSERT INTO users (email, nic, name, phone, profile_image, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('sssssss', $email, $nic, $name, $phone07, $profileUrl, $role, $status);
                    $stmt->execute();
                    $uid = (int)$stmt->insert_id;
                    $stmt->close();

                    // Send registration success email (best-effort)
                    try {
                        if (!empty($email)) {
                            require_once ___DIR___ . '/../php_mailer/mailer.php';
                            $subject = 'Welcome to Rentallanka';
                            $nameTo = (string)$name;
                            $safeName = htmlspecialchars($nameTo ?: 'there', ENT_QUOTES, 'UTF-8');
                            $year = date('Y');
                            $body = <<<HTML
<!doctype html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width" />
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>
  <style>
    body{margin:0;padding:0;background:#f6f9fc;color:#222;font-family:Arial,Helvetica,sans-serif}
    .wrap{max-width:600px;margin:0 auto;padding:24px}
    .card{background:#ffffff;border:1px solid #eaeaea;border-radius:12px;overflow:hidden}
    .header{padding:24px;text-align:center;border-bottom:1px solid #f0f0f0}
    .logo{font-size:24px;font-weight:700;color:#0d6efd;text-decoration:none}
    .content{padding:24px}
    h1{margin:0 0 12px 0;font-size:22px}
    p{margin:0 0 12px 0;line-height:1.6}
    .btn{display:inline-block;background:#0d6efd;color:#fff !important;text-decoration:none;padding:12px 18px;border-radius:6px}
    .footer{padding:16px 24px;text-align:center;color:#8a8f98;font-size:12px;border-top:1px solid #f0f0f0}
  </style>
  <!--[if mso]>
  <style type="text/css">
    body, table, td { font-family: Arial, Helvetica, sans-serif !important; }
  </style>
  <![endif]-->
  </head>
  <body>
    <div class="wrap">
      <div class="card">
        <div class="header">
          <a href="{$base_url}" class="logo">Rentallanka</a>
        </div>
        <div class="content">
          <h1>Hi {$safeName},</h1>
          <p>Your registration was successful. You can now sign in and start using Rentallanka.</p>
          <p><a class="btn" href="{$base_url}/auth/login.php">Sign in</a></p>
          <p>If you didn’t create this account, please ignore this email.</p>
        </div>
        <div class="footer">© {$year} Rentallanka. All rights reserved.</div>
      </div>
    </div>
  </body>
</html>
HTML;
                            @mailer_send($email, $nameTo, $subject, $body);
                        }
                    } catch (Throwable $e) { /* ignore */ }

                    $_SESSION['user'] = [
                        'user_id' => $uid,
                        'phone' => $phone07,
                        'role' => $role,
                    ];
                    $_SESSION['loggedin'] = true;
                    $_SESSION['role'] = $role;
                    unset($_SESSION['reg_stage'], $_SESSION['reg_user_id'], $_SESSION['reg_phone'], $_SESSION['reg_email'], $_SESSION['reg_name'], $_SESSION['reg_nic'], $_SESSION['reg_role'], $_SESSION['reg_profile'], $_SESSION['reg_otp'], $_SESSION['reg_otp_expires']);
                    redirect_with_message($base_url . '/index.php', 'Registered & logged in');
                } catch (Throwable $e) {
                    $codeErr = (int)($e->getCode() ?? 0);
                    if ($codeErr === 1062) {
                        $_SESSION['reg_error'] = 'Registration failed. Email/Name/NIC/Phone already exists.';
                    } else {
                        $_SESSION['reg_error'] = 'Registration failed due to a server error.';
                    }
                    unset($_SESSION['reg_stage']);
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }
            }
        }
    } elseif ($action === 'reset') {
        unset(
            $_SESSION['reg_stage'],
            $_SESSION['reg_user_id'],
            $_SESSION['reg_phone'],
            $_SESSION['reg_email'],
            $_SESSION['reg_name'],
            $_SESSION['reg_nic'],
            $_SESSION['reg_role'],
            $_SESSION['reg_profile'],
            $_SESSION['reg_otp'],
            $_SESSION['reg_otp_expires'],
            $_SESSION['reg_dev_otp']
        );
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta name="robots" content="noindex,nofollow" />
    <meta http-equiv="Cache-Control" content="no-store" />
    
    <style>
      /* ===========================
         REGISTER PAGE CUSTOM STYLES
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
        background: var(--rl-light-bg);
        height: 100vh;
        display: flex;
        align-items: center;
        padding: 0;
        overflow: hidden;
      }
      
      .container {
        padding: 0 1rem;
      }
      
      /* Registration Card */
      .card {
        border: none;
        border-radius: var(--rl-radius-lg);
        box-shadow: var(--rl-shadow-lg);
        overflow: hidden;
        background: var(--rl-white);
        animation: slideUp 0.4s ease-out;
        max-height: 95vh;
        overflow-y: auto;
      }
      
      @keyframes slideUp {
        from {
          opacity: 0;
          transform: translateY(30px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
      
      .card-body {
        padding: 2rem !important;
      }
      
      /* Header */
      h3 {
        font-weight: 800;
        color: var(--rl-text);
        font-size: 1.5rem;
        margin-bottom: 1.25rem !important;
        text-align: center;
        position: relative;
      }
      
      h3::after {
        content: '';
        display: block;
        width: 50px;
        height: 3px;
        background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
        margin: 0.5rem auto 0;
        border-radius: 2px;
      }
      
      /* Form Labels */
      .form-label {
        font-weight: 600;
        color: var(--rl-text);
        margin-bottom: 0.4rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.375rem;
      }
      
      .form-label::before {
        content: '';
        width: 3px;
        height: 0.875rem;
        background: var(--rl-accent);
        border-radius: 2px;
      }
      
      /* Form Inputs */
      .form-control {
        border: 2px solid var(--rl-border);
        border-radius: 10px;
        padding: 0.625rem 0.875rem;
        font-size: 0.875rem;
        color: var(--rl-text);
        background: var(--rl-white);
        transition: all 0.2s ease;
        font-weight: 500;
      }
      
      .form-control:focus {
        border-color: var(--rl-primary);
        box-shadow: 0 0 0 4px rgba(0, 78, 152, 0.1);
        outline: none;
      }
      
      .form-control:hover:not(:focus) {
        border-color: #cbd5e0;
      }
      
      .form-control::placeholder {
        color: var(--rl-text-muted);
        font-weight: 400;
      }
      
      /* Form Validation */
      .form-control:invalid:not(:placeholder-shown) {
        border-color: #fca5a5;
      }
      
      .form-control:valid:not(:placeholder-shown) {
        border-color: #86efac;
      }
      
      /* Buttons */
      .btn {
        font-weight: 700;
        border-radius: 10px;
        padding: 0.75rem 1.25rem;
        transition: all 0.2s ease;
        font-size: 0.875rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        border: none;
        letter-spacing: 0.3px;
      }
      
      .btn-primary {
        background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
        color: var(--rl-white);
        box-shadow: 0 4px 16px rgba(0, 78, 152, 0.3);
      }
      
      .btn-primary:hover {
        background: linear-gradient(135deg, #003a75 0%, #2d5a8f 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 24px rgba(0, 78, 152, 0.4);
        color: var(--rl-white);
      }
      
      .btn-primary:active {
        transform: translateY(0);
      }
      
      .btn-secondary {
        background: var(--rl-secondary);
        color: var(--rl-text);
      }
      
      .btn-secondary:hover {
        background: #a0a0a0;
        transform: translateY(-1px);
        color: var(--rl-text);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      }
      
      .btn-outline-secondary {
        border: 2px solid var(--rl-border);
        color: var(--rl-text-secondary);
        background: transparent;
        font-weight: 600;
      }
      
      .btn-outline-secondary:hover {
        background: var(--rl-light-bg);
        border-color: var(--rl-secondary);
        color: var(--rl-text);
        transform: translateY(-1px);
      }
      
      /* Form Stack */
      .vstack {
        gap: 1rem !important;
      }
      
      /* Footer Text */
      .text-muted.small {
        color: var(--rl-text-muted) !important;
        font-size: 0.75rem !important;
        margin-top: 1rem !important;
        line-height: 1.4;
      }
      
      /* OTP Stage Specific */
      .mt-2 {
        margin-top: 1rem !important;
      }
      
      /* Stage Indicator */
      .card-body::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--rl-primary) 0%, var(--rl-dark) 100%);
      }
      
      .card-body {
        position: relative;
      }
      
      /* Responsive */
      @media (max-width: 767px) {
        .container {
          padding: 0 0.75rem;
        }
        
        .card-body {
          padding: 1.5rem 1.25rem !important;
        }
        
        h3 {
          font-size: 1.375rem;
          margin-bottom: 1rem !important;
        }
        
        h3::after {
          width: 40px;
          height: 3px;
          margin: 0.375rem auto 0;
        }
        
        .form-label {
          font-size: 0.8125rem;
          margin-bottom: 0.375rem;
        }
        
        .btn {
          padding: 0.625rem 1rem;
          font-size: 0.8125rem;
        }
        
        .form-control {
          padding: 0.5rem 0.75rem;
          font-size: 0.8125rem;
        }
        
        .vstack {
          gap: 0.875rem !important;
        }
      }
      
      @media (max-width: 575px) {
        .card-body {
          padding: 1.25rem 1rem !important;
        }
        
        h3 {
          font-size: 1.25rem;
        }
        
        .text-muted.small {
          font-size: 0.6875rem !important;
          margin-top: 0.75rem !important;
        }
      }
      
      /* SweetAlert2 Custom Styling */
      .swal2-popup {
        font-family: 'Inter', sans-serif;
        border-radius: var(--rl-radius-lg);
      }
      
      .swal2-toast {
        border-radius: var(--rl-radius);
        box-shadow: var(--rl-shadow-lg);
      }
      
      /* Loading State */
      .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
      }
      
      /* Dev OTP Display Box */
      .dev-otp-box {
        position: fixed;
        top: 10px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        padding: 12px 20px;
        background: #e7ffe7;
        border: 2px solid #2ecc71;
        border-radius: 8px;
        color: #27ae60;
        font-weight: 600;
        font-size: 0.875rem;
        box-shadow: 0 4px 12px rgba(46, 204, 113, 0.3);
        animation: slideDown 0.3s ease-out;
      }
      
      @keyframes slideDown {
        from {
          opacity: 0;
          transform: translateX(-50%) translateY(-20px);
        }
        to {
          opacity: 1;
          transform: translateX(-50%) translateY(0);
        }
      }
      
      .dev-otp-box strong {
        color: #229954;
      }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['reg_dev_otp']) && $stage === 'verify'): ?>
        <div class="dev-otp-box">
            <strong>[DEV MODE]</strong> OTP for <?= htmlspecialchars($_SESSION['reg_phone'] ?? '') ?> is <strong><?= htmlspecialchars($_SESSION['reg_dev_otp']) ?></strong>
        </div>
    <?php endif; ?>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-6 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h3 class="mb-3 text-center">Create Account</h3>
                        
                        <?php if ($stage === 'request'): ?>
                            <form method="post" class="vstack gap-3">
                                <div>
                                    <label class="form-label">Name</label>
                                    <input type="text" class="form-control" name="name" placeholder="name" required />
                                </div>
                                <div>
                                    <label class="form-label">NIC</label>
                                    <input type="text" class="form-control" name="nic" placeholder="NIC" required pattern="^(([5,6,7,8,9]{1})([0-9]{1})([0,1,2,3,5,6,7,8]{1})([0-9]{6})([v|V|x|X]))|(([1,2]{1})([0,9]{1})([0-9]{2})([0,1,2,3,5,6,7,8]{1})([0-9]{7}))" title="Enter a valid Sri Lankan NIC (old 10-char with V/X or new 12-digit)" />
                                </div>
                                <div>
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone" maxlength="10" pattern="^[0]{1}[7]{1}[01245678]{1}[0-9]{7}$" title="Enter number like 07XXXXXXXX" placeholder="e.g. 07XXXXXXXX" value="<?php echo htmlspecialchars($prefill_phone); ?>" required />
                                </div>
                                <div>
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" placeholder="you@example.com" required />
                                </div>
                              
                          
                                
                                <input type="hidden" name="action" value="request" />
                                <button type="submit" class="btn btn-primary w-100">Send OTP</button>
                                <a href="<?php echo $base_url; ?>/auth/login.php" class="btn btn-outline-secondary w-100">Have an account? Login</a>
                            </form>
                        <?php else: ?>
                            <form method="post" class="vstack gap-3">
                                <div>
                                    <label class="form-label">Enter OTP</label>
                                    <input type="text" class="form-control" name="otp" maxlength="6" pattern="\d{6}" placeholder="6-digit code" required />
                                </div>
                                <input type="hidden" name="action" value="verify" />
                                <button type="submit" class="btn btn-primary w-100">Verify & Create Account</button>
                            </form>
                            <form method="post" class="mt-2">
                                <input type="hidden" name="action" value="reset" />
                                <button type="submit" class="btn btn-secondary w-100">Use different number</button>
                            </form>
                        <?php endif; ?>
                        <p class="text-muted small mt-3 text-center">Registration uses OTP sent via SMS.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      (function(){
        try {
          const flash = <?= json_encode($flash) ?>;
          const error = <?= json_encode($error) ?>;
          const info  = <?= json_encode($info) ?>;
          const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3500, timerProgressBar: true });
          if (error) { Toast.fire({ icon: 'error', title: String(error) }); }
          else if (info) { Toast.fire({ icon: 'success', title: String(info) }); }
          else if (flash) { Toast.fire({ icon: 'info', title: String(flash) }); }
        } catch(_) {}
      })();
    </script>
</body>
</html>