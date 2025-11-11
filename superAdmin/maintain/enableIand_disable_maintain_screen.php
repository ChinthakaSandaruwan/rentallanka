<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../error.log');

if ((function_exists('session_status') ? session_status() : PHP_SESSION_NONE) === PHP_SESSION_NONE) {
  session_start();
}

require_once dirname(__DIR__, 2) . '/config/config.php';

// Basic access guard: only allow super admin
$isSuper = isset($_SESSION['super_admin_id']) && (int)$_SESSION['super_admin_id'] > 0;
if (!$isSuper) {
  if (!headers_sent()) { http_response_code(403); }
  echo 'Forbidden: Super admin only';
  exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf_token'];

$flagPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'maintain.flag';
$enabled = is_file($flagPath);
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (hash_equals($csrf, $token)) {
    $action = $_POST['action'] ?? '';
    if ($action === 'enable') {
      @file_put_contents($flagPath, 'enabled ' . date('c'));
      $enabled = is_file($flagPath);
      $msg = $enabled ? 'Maintenance mode enabled.' : 'Failed to enable maintenance.';
    } elseif ($action === 'disable') {
      if (is_file($flagPath)) { @unlink($flagPath); }
      $enabled = is_file($flagPath);
      $msg = !$enabled ? 'Maintenance mode disabled.' : 'Failed to disable maintenance.';
    }
  } else {
    $msg = 'Invalid CSRF token.';
  }
  // PRG: store flash and redirect to avoid resubmission alert
  $_SESSION['__flash_msg'] = $msg;
  $self = (string)($_SERVER['REQUEST_URI'] ?? '');
  $target = $self;
  // If maintenance was disabled successfully, go to home
  if (($action === 'disable') && !$enabled) {
    $target = rtrim($base_url, '/') . '/';
    // no need to show flash on home
    unset($_SESSION['__flash_msg']);
  }
  if (!headers_sent() && $target !== '') { header('Location: ' . $target); }
  exit;
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root { --rl-primary:#004E98; --rl-accent:#3A6EA5; --rl-dark:#FF6700; --rl-border:#E5E7EB; }
    body { background:#f8fafc; }
    .rl-card { max-width: 720px; margin: 2rem auto; }
    .badge-on { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .badge-off { background: linear-gradient(135deg, #10b981, #059669); }
    .btn-enable { background: linear-gradient(135deg, #ef4444, #dc2626); color:#fff; border: none; }
    .btn-disable { background: linear-gradient(135deg, #10b981, #059669); color:#fff; border: none; }
  </style>
  </head>
<body>
  <div class="container rl-card">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="m-0">Maintenance Mode</h5>
        <?php if ($enabled): ?>
          <span class="badge badge-on">Enabled</span>
        <?php else: ?>
          <span class="badge badge-off">Disabled</span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php $flash = $_SESSION['__flash_msg'] ?? ''; if ($flash !== ''): unset($_SESSION['__flash_msg']); ?>
          <div class="alert alert-info py-2 mb-3"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>
        <p class="text-muted">This toggles a <code>maintain.flag</code> file in the project root. Your front controller or bootstrap should check for that file to show a maintenance screen.</p>
        <div class="d-flex gap-2">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="enable">
            <button type="submit" class="btn btn-enable" <?= $enabled ? 'disabled' : '' ?>>Enable</button>
          </form>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="disable">
            <button type="submit" class="btn btn-disable" <?= $enabled ? '' : 'disabled' ?>>Disable</button>
          </form>
          <a href="<?= htmlspecialchars($base_url) ?>/superAdmin/index.php" class="btn btn-outline-secondary">Back</a>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
