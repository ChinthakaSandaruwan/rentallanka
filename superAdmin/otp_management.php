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
require_once __DIR__ . '/../config/otp_helper.php';
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_super_admin();

$to_e164 = function(string $phone){
    $p = preg_replace('/\D+/', '', $phone);
    if (preg_match('/^07[0-9]{8}$/', $p)) {
        return '+94' . substr($p, 1);
    }
    return $phone;
};

// Access controlled by require_super_admin();

$action = $_POST['action'] ?? '';
$message = '';
$error = '';

// Read current OTP and SMS settings
$otp_enabled = (int)setting_get('otp_enabled', '1');
$otp_length = (int)setting_get('otp_length', '6');
$otp_expiry_minutes = (int)setting_get('otp_expiry_minutes', '5');
$otp_max_attempts = (int)setting_get('otp_max_attempts', '5');
$otp_sms_prefix = setting_get('otp_sms_prefix', 'OTP');
$otp_mode = setting_get('otp_mode', 'development');

// Read current SMS settings
$sms_user_id = setting_get('sms_user_id', getenv('SMSLENZ_USER_ID') ?: '');
$sms_api_key = setting_get('sms_api_key', getenv('SMSLENZ_API_KEY') ?: '');
$sms_sender_id = setting_get('sms_sender_id', getenv('SMSLENZ_SENDER_ID') ?: 'SMSlenzDEMO');
$sms_base_url = setting_get('sms_base_url', 'https://smslenz.lk/api');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save_otp_settings') {
        $otp_enabled = isset($_POST['otp_enabled']) ? 1 : 0;
        $otp_length = max(4, min(8, (int)($_POST['otp_length'] ?? 6)));
        $otp_expiry_minutes = max(1, min(60, (int)($_POST['otp_expiry_minutes'] ?? 5)));
        $otp_max_attempts = max(1, min(20, (int)($_POST['otp_max_attempts'] ?? 5)));
        $otp_sms_prefix = trim($_POST['otp_sms_prefix'] ?? '');
        $otp_mode_in = strtolower(trim((string)($_POST['otp_mode'] ?? 'development')));
        if (!in_array($otp_mode_in, ['development','real'], true)) { $otp_mode_in = 'development'; }
        if (strlen($otp_sms_prefix) > 32) {
            $error = 'SMS Prefix must be 32 characters or less';
        } else {
            setting_set('otp_enabled', (string)$otp_enabled);
            setting_set('otp_length', (string)$otp_length);
            setting_set('otp_expiry_minutes', (string)$otp_expiry_minutes);
            setting_set('otp_max_attempts', (string)$otp_max_attempts);
            setting_set('otp_sms_prefix', $otp_sms_prefix);
            setting_set('otp_mode', $otp_mode_in);
            $otp_mode = $otp_mode_in;
            $message = 'OTP settings saved';
        }
    } elseif ($action === 'save_sms_settings') {
        $sms_user_id = trim($_POST['sms_user_id'] ?? '');
        $sms_api_key = trim($_POST['sms_api_key'] ?? '');
        $sms_sender_id = trim($_POST['sms_sender_id'] ?? '');
        $sms_base_url = trim($_POST['sms_base_url'] ?? '');
        $valid = true;
        if ($sms_user_id === '' || $sms_api_key === '') {
            $error = 'SMS User ID and API Key are required';
            $valid = false;
        }
        if ($sms_sender_id !== '' && !preg_match('/^[A-Za-z0-9]{3,11}$/', $sms_sender_id)) {
            $error = 'Sender ID must be 3-11 letters/numbers';
            $valid = false;
        }
        if ($sms_base_url === '' || !preg_match('#^https?://[^\s]+$#i', $sms_base_url)) {
            $error = 'Enter a valid API Base URL starting with http or https';
            $valid = false;
        }
        if ($valid) {
            setting_set('sms_user_id', $sms_user_id);
            setting_set('sms_api_key', $sms_api_key);
            setting_set('sms_sender_id', $sms_sender_id);
            setting_set('sms_base_url', $sms_base_url);
            $message = 'SMS API settings saved';
        }
    } elseif ($action === 'clear_sms_settings') {
        $keys = ['sms_user_id','sms_api_key','sms_sender_id','sms_base_url'];
        $in = implode(',', array_fill(0, count($keys), '?'));
        $types = str_repeat('s', count($keys));
        $stmt = db()->prepare("DELETE FROM settings WHERE setting_key IN ($in)");
        $stmt->bind_param($types, ...$keys);
        $stmt->execute();
        $stmt->close();
        $sms_user_id = $sms_api_key = '';
        $sms_sender_id = 'SMSlenzDEMO';
        $sms_base_url = 'https://smslenz.lk/api';
        $message = 'SMS API settings cleared';
    } elseif ($action === 'invalidate') {
        $otp_id = (int)($_POST['otp_id'] ?? 0);
        if ($otp_id > 0) {
            $stmt = db()->prepare('UPDATE user_otps SET expires_at = NOW() WHERE otp_id = ?');
            $stmt->bind_param('i', $otp_id);
            $stmt->execute();
            $stmt->close();
            $message = 'OTP invalidated';
        }
    } elseif ($action === 'resend') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id > 0) {
            $stmt = db()->prepare('SELECT phone FROM users WHERE user_id = ? LIMIT 1');
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $usr = $res->fetch_assoc();
            $stmt->close();
            if ($usr && !empty($usr['phone'])) {
                // Generate OTP using configured length and expiry
                $max = (10 ** $otp_length) - 1;
                $otp_num = random_int(0, $max);
                $otp = str_pad((string)$otp_num, $otp_length, '0', STR_PAD_LEFT);
                $expires = (new DateTime('+' . $otp_expiry_minutes . ' minutes'))->format('Y-m-d H:i:s');
                $stmt = db()->prepare('INSERT INTO user_otps (user_id, otp_code, expires_at, is_verified) VALUES (?, ?, ?, 0)');
                $stmt->bind_param('iss', $user_id, $otp, $expires);
                $stmt->execute();
                $stmt->close();
                $prefix = trim($otp_sms_prefix);
                $sms = ($prefix !== '' ? ($prefix . ' ') : '') . $otp . ' (expires in ' . $otp_expiry_minutes . ' min)';
                // Use global helper respecting OTP_MODE
                sendOtp((string)$usr['phone'], $otp, $sms);
                $message = 'OTP resent';
            } else {
                $error = 'User phone not found';
            }
        }
    } elseif ($action === 'delete_all_otps') {
        // Danger: delete all OTP records
        db()->query('DELETE FROM user_otps');
        $message = 'All OTPs deleted';
    }
}

// Enforce POST-Redirect-GET to avoid resubmission prompt on refresh/back
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $self_url = rtrim($base_url, '/') . '/superAdmin/otp_management.php';
    if (!headers_sent()) {
        if (!empty($error)) {
            redirect_with_message($self_url, $error, 'error');
        } elseif (!empty($message)) {
            redirect_with_message($self_url, $message, 'success');
        } else {
            header('Location: ' . $self_url);
            exit;
        }
    }
}

[$flash, $flash_type] = get_flash();

$q = trim($_GET['q'] ?? '');
$where = '';
$params = [];
$types = '';
if ($q !== '') {
    $where = 'WHERE u.phone LIKE ? OR u.email LIKE ?';
    $like = "%$q%";
    $params = [$like, $like];
    $types = 'ss';
}

$sql = 'SELECT o.otp_id, o.user_id, o.otp_code, o.expires_at, o.is_verified, o.created_at, u.phone, u.email, u.role
        FROM user_otps o
        JOIN users u ON u.user_id = o.user_id ' . $where . ' 
        ORDER BY o.created_at DESC
        LIMIT 200';
$stmt = db()->prepare($sql);
if ($types) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Management</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
    <div class="container py-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="h4 mb-0">OTP Management</h2>
            <div class="d-flex gap-2">
              <form method="post" class="d-inline" data-swal="delete-all-otps">
                <input type="hidden" name="action" value="delete_all_otps" />
                <button type="submit" class="btn btn-outline-danger btn-sm">Delete All OTPs</button>
              </form>
              <a href="index.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
            </div>
        </div>
        <?php if ($message): ?>
        <?php endif; ?>
        <?php if ($error): ?>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">OTP Settings</div>
            <div class="card-body">
                <form method="post">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="py-2 mb-0">
                                <span class="fw-semibold">Current OTP Mode:</span>
                                <span class="badge bg-info text-dark"><?php echo htmlspecialchars(strtoupper($otp_mode)); ?></span>
                            </div>
                        </div>
                        <div class="col-12 col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="otp_enabled" name="otp_enabled" <?php echo $otp_enabled ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="otp_enabled">On/Off</label>
                            </div>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label">Global OTP Mode</label>
                            <select class="form-select" name="otp_mode">
                                <option value="development" <?php echo ($otp_mode === 'development') ? 'selected' : ''; ?>>Development</option>
                                <option value="real" <?php echo ($otp_mode === 'real') ? 'selected' : ''; ?>>Real</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">OTP Length</label>
                            <input type="number" class="form-control" name="otp_length" min="4" max="8" value="<?php echo (int)$otp_length; ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Expiry (minutes)</label>
                            <input type="number" class="form-control" name="otp_expiry_minutes" min="1" max="60" value="<?php echo (int)$otp_expiry_minutes; ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Max Attempts</label>
                            <input type="number" class="form-control" name="otp_max_attempts" min="1" max="20" value="<?php echo (int)$otp_max_attempts; ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">SMS Prefix</label>
                            <input type="text" class="form-control" name="otp_sms_prefix" value="<?php echo htmlspecialchars($otp_sms_prefix); ?>" placeholder="Optional text before OTP code">
                            <div class="form-text">Optional text prefix included before the OTP code in the SMS.</div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <input type="hidden" name="action" value="save_otp_settings">
                        <button type="submit" class="btn btn-primary">Save OTP Settings</button>
                        <div class="form-text mt-2">Development Mode will not send SMS; OTP code will be shown in the UI for testing.</div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">SMS API Settings</div>
            <div class="card-body">
                <form method="post">
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">SMSLenz User ID</label>
                            <input type="text" class="form-control" name="sms_user_id" value="<?php echo htmlspecialchars($sms_user_id); ?>">
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <label class="form-label">SMSLenz API Key</label>
                            <input type="text" class="form-control" name="sms_api_key" value="<?php echo htmlspecialchars($sms_api_key); ?>">
                        </div>
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Sender ID</label>
                            <input type="text" class="form-control" name="sms_sender_id" value="<?php echo htmlspecialchars($sms_sender_id); ?>">
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <label class="form-label">API Base URL</label>
                            <input type="text" class="form-control" name="sms_base_url" value="<?php echo htmlspecialchars($sms_base_url); ?>">
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <input type="hidden" name="action" value="save_sms_settings" />
                        <button class="btn btn-primary" type="submit">Save Settings</button>
                    </div>
                </form>
                <form method="post" class="mt-2">
                    <input type="hidden" name="action" value="clear_sms_settings" />
                    <button class="btn btn-secondary" type="submit">Clear Settings</button>
                </form>
                <p class="text-muted small mt-2 mb-0">Send SMS endpoint: <code><?php echo htmlspecialchars(rtrim($sms_base_url,'/').'/send-sms'); ?></code><br>
                Send Bulk endpoint: <code><?php echo htmlspecialchars(rtrim($sms_base_url,'/').'/send-bulk-sms'); ?></code></p>
            </div>
        </div>

        <form method="get" class="row g-2 align-items-center mb-3">
            <div class="col-12 col-sm-6 col-md-4">
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search by phone or email" />
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-primary" type="submit">Search</button>
            </div>
        </form>

    <div class="table-responsive">
    <table class="table table-sm table-striped table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Phone</th>
                <th>OTP</th>
                <th>Expires</th>
                <th>Verified</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo (int)$r['otp_id']; ?></td>
                    <td><?php echo (int)$r['user_id']; ?></td>
                    <td><?php echo htmlspecialchars($r['phone'] ?: ''); ?></td>
                    <td><?php echo htmlspecialchars($r['otp_code']); ?></td>
                    <td><?php echo htmlspecialchars($r['expires_at']); ?></td>
                    <td><?php echo $r['is_verified'] ? 'yes' : 'no'; ?></td>
                    <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                    <td>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="invalidate" />
                            <input type="hidden" name="otp_id" value="<?php echo (int)$r['otp_id']; ?>" />
                            <button class="btn btn-secondary btn-sm" type="submit">Invalidate</button>
                        </form>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="resend" />
                            <input type="hidden" name="user_id" value="<?php echo (int)$r['user_id']; ?>" />
                            <button class="btn btn-primary btn-sm" type="submit">Resend</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
      <script>
        document.addEventListener('DOMContentLoaded', function(){
          var flash = <?php echo json_encode($flash ?? ''); ?>;
          var flashType = <?php echo json_encode($flash_type ?? 'info'); ?>;
          var msg = <?php echo json_encode($message); ?>;
          var err = <?php echo json_encode($error); ?>;
          if (flash) {
            Swal.fire({ toast: true, position: 'top-end', icon: (flashType === 'error' ? 'error' : (flashType === 'warning' ? 'warning' : 'success')), title: flash, showConfirmButton: false, timer: 3000, timerProgressBar: true });
          } else if (err) {
            Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: err, showConfirmButton: false, timer: 3000, timerProgressBar: true });
          } else if (msg) {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: msg, showConfirmButton: false, timer: 3000, timerProgressBar: true });
          }
          // Confirm delete all OTPs
          var delAll = document.querySelector('form[data-swal="delete-all-otps"]');
          if (delAll) {
            delAll.addEventListener('submit', function(e){
              e.preventDefault();
              Swal.fire({
                title: 'Delete all OTPs?',
                text: 'This will remove all OTP records. This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete all',
                cancelButtonText: 'Cancel'
              }).then(res => { if (res.isConfirmed) delAll.submit(); });
            });
          }
        });
      </script>
</body>
</html>
