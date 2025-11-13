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
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_super_admin();
require_once __DIR__ . '/../config/config.php';

$logFile = __DIR__ . '/../error/error.log';
$action = $_POST['action'] ?? '';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'clear') {
        if (file_exists($logFile)) {
            // truncate the file
            $fp = @fopen($logFile, 'w');
            if ($fp) { fclose($fp); $message = 'Log cleared'; } else { $error = 'Unable to clear log'; }
        } else {
            $message = 'Log already empty';
        }
    }
    // Enforce POST-Redirect-GET
    $self_url = rtrim($base_url, '/') . '/superAdmin/error.php';
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

// Read (tail) the log content safely (up to ~1MB)
$content = '';
if (file_exists($logFile)) {
    $size = filesize($logFile);
    $max = 1024 * 1024; // 1MB
    $offset = max(0, $size - $max);
    $fh = fopen($logFile, 'r');
    if ($fh) {
        if ($offset > 0) { fseek($fh, $offset); }
        $content = stream_get_contents($fh) ?: '';
        fclose($fh);
        if ($offset > 0) { $content = "[...truncated...]\n" . $content; }
    }
}

// Parse and categorize errors
$errorStats = ['total' => 0, 'fatal' => 0, 'system_alert' => 0, 'auth' => 0, 'db' => 0, 'exception' => 0, 'other' => 0];
$errorsByType = ['fatal' => [], 'system_alert' => [], 'auth' => [], 'db' => [], 'exception' => [], 'other' => []];
$lines = array_filter(array_map('trim', explode("\n", $content)));

foreach ($lines as $line) {
    if (empty($line)) continue;
    $errorStats['total']++;
    
    // Categorize error type
    if (strpos($line, '[Fatal Error]') !== false || strpos($line, 'PHP Fatal error') !== false) {
        $errorStats['fatal']++;
        $errorsByType['fatal'][] = $line;
    } elseif (strpos($line, '[system_alert]') !== false) {
        $errorStats['system_alert']++;
        $errorsByType['system_alert'][] = $line;
    } elseif (strpos($line, '[rent_') !== false || strpos($line, 'unauthenticated') !== false || strpos($line, 'uid=0') !== false) {
        $errorStats['auth']++;
        $errorsByType['auth'][] = $line;
    } elseif (strpos($line, 'database') !== false || strpos($line, 'mysqli') !== false || strpos($line, 'SQL') !== false) {
        $errorStats['db']++;
        $errorsByType['db'][] = $line;
    } elseif (strpos($line, '[Unhandled Exception]') !== false || strpos($line, 'Exception') !== false) {
        $errorStats['exception']++;
        $errorsByType['exception'][] = $line;
    } else {
        $errorStats['other']++;
        $errorsByType['other'][] = $line;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Error Log Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <style>
    pre.log { max-height: 70vh; overflow:auto; background:#0f172a; color:#e2e8f0; padding:1rem; border-radius:.5rem; font-size: 0.875rem; }
    .error-stat { text-align: center; padding: 1rem; border-radius: 0.5rem; }
    .error-stat.fatal { background: #fee2e2; color: #7f1d1d; border: 1px solid #fecaca; }
    .error-stat.system_alert { background: #fef3c7; color: #78350f; border: 1px solid #fde68a; }
    .error-stat.auth { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
    .error-stat.db { background: #f3e8ff; color: #5b21b6; border: 1px solid #e9d5ff; }
    .error-stat.exception { background: #dbeafe; color: #0c2d6b; border: 1px solid #bfdbfe; }
    .error-stat.other { background: #f0fdf4; color: #14532d; border: 1px solid #bbf7d0; }
    .error-stat h3 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.25rem; }
    .error-stat p { margin: 0; font-size: 0.875rem; }
    .error-section { margin-top: 1.5rem; }
    .error-section h5 { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; }
    .error-item { background: #f8f9fa; border-left: 4px solid #dee2e6; padding: 0.75rem; margin-bottom: 0.5rem; border-radius: 0.25rem; font-family: monospace; font-size: 0.85rem; overflow-x: auto; }
    .error-item.fatal { border-left-color: #dc2626; }
    .error-item.system_alert { border-left-color: #f59e0b; }
    .error-item.auth { border-left-color: #6366f1; }
    .error-item.db { border-left-color: #a855f7; }
    .error-item.exception { border-left-color: #0ea5e9; }
    .error-item.other { border-left-color: #10b981; }
    .empty-state { text-align: center; padding: 2rem; color: #6b7280; }
    .badge-count { display: inline-block; font-weight: 700; font-size: 1.25rem; }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Error Log Viewer</h1>
    <div class="d-flex gap-2">
      <a href="index.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
      <form method="post" class="d-inline" data-swal="clear-log">
        <input type="hidden" name="action" value="clear" />
        <button type="submit" class="btn btn-outline-danger btn-sm">Clear Log</button>
      </form>
      <a href="error.php" class="btn btn-outline-primary btn-sm">Refresh</a>
    </div>
  </div>

  <?php [$flash, $flash_type] = get_flash(); ?>
  <?php if ($flash): ?><?php endif; ?>

  <div class="card">
    <div class="card-body">
      <?php if ($content !== ''): ?>
        <!-- Error Statistics Dashboard -->
        <div class="mb-4">
          <h5 class="mb-3">📊 Error Summary</h5>
          <div class="row g-3">
            <div class="col-12 col-sm-6 col-md-4 col-lg-2">
              <div class="error-stat">
                <h3><?= $errorStats['total'] ?></h3>
                <p>Total Errors</p>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-2">
              <div class="error-stat fatal">
                <h3><?= $errorStats['fatal'] ?></h3>
                <p>Fatal Errors</p>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-2">
              <div class="error-stat system_alert">
                <h3><?= $errorStats['system_alert'] ?></h3>
                <p>System Alerts</p>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-2">
              <div class="error-stat auth">
                <h3><?= $errorStats['auth'] ?></h3>
                <p>Auth Issues</p>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-2">
              <div class="error-stat db">
                <h3><?= $errorStats['db'] ?></h3>
                <p>DB Errors</p>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-2">
              <div class="error-stat exception">
                <h3><?= $errorStats['exception'] ?></h3>
                <p>Exceptions</p>
              </div>
            </div>
          </div>
        </div>

        <hr />

        <!-- Error Breakdown by Type -->
        <?php if ($errorStats['fatal'] > 0): ?>
          <div class="error-section">
            <h5>🔴 Fatal Errors (<?= $errorStats['fatal'] ?>)</h5>
            <?php foreach (array_slice(array_reverse($errorsByType['fatal']), 0, 10) as $err): ?>
              <div class="error-item fatal"><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
            <?php if ($errorStats['fatal'] > 10): ?><small class="text-muted">... and <?= $errorStats['fatal'] - 10 ?> more</small><?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($errorStats['system_alert'] > 0): ?>
          <div class="error-section">
            <h5>⚠️ System Alerts (<?= $errorStats['system_alert'] ?>)</h5>
            <?php foreach (array_slice(array_reverse($errorsByType['system_alert']), 0, 10) as $err): ?>
              <div class="error-item system_alert"><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
            <?php if ($errorStats['system_alert'] > 10): ?><small class="text-muted">... and <?= $errorStats['system_alert'] - 10 ?> more</small><?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($errorStats['auth'] > 0): ?>
          <div class="error-section">
            <h5>🔐 Authentication Issues (<?= $errorStats['auth'] ?>)</h5>
            <?php foreach (array_slice(array_reverse($errorsByType['auth']), 0, 10) as $err): ?>
              <div class="error-item auth"><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
            <?php if ($errorStats['auth'] > 10): ?><small class="text-muted">... and <?= $errorStats['auth'] - 10 ?> more</small><?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($errorStats['db'] > 0): ?>
          <div class="error-section">
            <h5>🗄️ Database Errors (<?= $errorStats['db'] ?>)</h5>
            <?php foreach (array_slice(array_reverse($errorsByType['db']), 0, 10) as $err): ?>
              <div class="error-item db"><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
            <?php if ($errorStats['db'] > 10): ?><small class="text-muted">... and <?= $errorStats['db'] - 10 ?> more</small><?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($errorStats['exception'] > 0): ?>
          <div class="error-section">
            <h5>💥 Exceptions (<?= $errorStats['exception'] ?>)</h5>
            <?php foreach (array_slice(array_reverse($errorsByType['exception']), 0, 10) as $err): ?>
              <div class="error-item exception"><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
            <?php if ($errorStats['exception'] > 10): ?><small class="text-muted">... and <?= $errorStats['exception'] - 10 ?> more</small><?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($errorStats['other'] > 0): ?>
          <div class="error-section">
            <h5>ℹ️ Other Errors (<?= $errorStats['other'] ?>)</h5>
            <?php foreach (array_slice(array_reverse($errorsByType['other']), 0, 10) as $err): ?>
              <div class="error-item other"><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
            <?php if ($errorStats['other'] > 10): ?><small class="text-muted">... and <?= $errorStats['other'] - 10 ?> more</small><?php endif; ?>
          </div>
        <?php endif; ?>

        <hr />

        <details>
          <summary class="mb-3 cursor-pointer"><strong>📋 Raw Log View (Click to expand)</strong></summary>
          <pre class="log"><?php echo htmlspecialchars($content); ?></pre>
        </details>
      <?php else: ?>
        <div class="empty-state">
          <h5>✨ No Errors</h5>
          <p>Your application is running smoothly!</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function(){
    var flash = <?php echo json_encode($flash ?? ''); ?>;
    var flashType = <?php echo json_encode($flash_type ?? 'info'); ?>;
    if (flash) {
      Swal.fire({ toast: true, position: 'top-end', icon: (flashType === 'error' ? 'error' : (flashType === 'warning' ? 'warning' : 'success')), title: flash, showConfirmButton: false, timer: 3000, timerProgressBar: true });
    }
    var clearForm = document.querySelector('form[data-swal="clear-log"]');
    if (clearForm) {
      clearForm.addEventListener('submit', function(e){
        e.preventDefault();
        Swal.fire({
          title: 'Clear error log?',
          text: 'This will truncate the log file.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, clear',
          cancelButtonText: 'Cancel'
        }).then(res => { if (res.isConfirmed) clearForm.submit(); });
      });
    }
  });
</script>
</body>
</html>
