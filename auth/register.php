<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/otp_helper.php';

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
$error = '';
$info = '';
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
            $error = 'Enter valid Sri Lanka phone number';
            $stage = 'request';
        } elseif ($email === '') {
            $error = 'Email is required';
            $stage = 'request';
        } elseif ($name === '') {
            $error = 'Name is required';
            $stage = 'request';
        } elseif ($nic === '') {
            $error = 'NIC is required';
            $stage = 'request';
        } elseif (!preg_match('/^(([5,6,7,8,9]{1})([0-9]{1})([0,1,2,3,5,6,7,8]{1})([0-9]{6})([v|V|x|X]))|(([1,2]{1})([0,9]{1})([0-9]{2})([0,1,2,3,5,6,7,8]{1})([0-9]{7}))$/', $nic)) {
            $error = 'Enter valid NIC';
            $stage = 'request';
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

            if (!$error) {
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
                $info = 'OTP sent to ' . $phone07;
                $stage = 'verify';
            }
        }
    } elseif ($action === 'verify') {
        $phone07 = $_SESSION['reg_phone'] ?? '';
        $code = trim($_POST['otp'] ?? '');
        if (!preg_match('/^\d{6}$/', $code)) {
            $error = 'Invalid OTP';
            $stage = 'verify';
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
                $error = 'OTP is invalid or expired';
                $stage = 'verify';
            } else {
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
                            require_once __DIR__ . '/../php_mailer/mailer.php';
                            $subject = 'Welcome to Rentallanka';
                            $nameTo = (string)$name;
                            $body = '<p>Hi ' . htmlspecialchars($nameTo ?: 'there') . ',</p>'
                                  . '<p>Your registration is successful. You can now sign in and start using Rentallanka.</p>'
                                  . '<p>Thank you,<br>Rentallanka</p>';
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
                        $error = 'Registration failed. Email/Name/NIC/Phone already exists.';
                    } else {
                        $error = 'Registration failed due to a server error.';
                    }
                    $stage = 'request';
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
            $_SESSION['reg_otp_expires']
        );
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
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <meta name="robots" content="noindex,nofollow" />
    <meta http-equiv="Cache-Control" content="no-store" />
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-6 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h3 class="mb-3 text-center">Create Account</h3>
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
</body>
</html>
