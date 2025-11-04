<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_super_admin();

$action = $_POST['action'] ?? '';
$message = '';
$error = '';

// Load current PayHere config (settings override config defaults)
$cur_merchant_id = setting_get('payhere_merchant_id', $merchant_id);
$cur_merchant_secret = setting_get('payhere_merchant_secret', $merchant_secret);
$cur_mode = setting_get('payhere_mode', (strpos($payhere_url, 'sandbox') !== false ? 'sandbox' : 'live'));
$cur_return_url = setting_get('payhere_return_url', $return_url);
$cur_cancel_url = setting_get('payhere_cancel_url', $cancel_url);
$cur_notify_url = setting_get('payhere_notify_url', $notify_url);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save_payhere') {
        $cur_merchant_id = trim($_POST['merchant_id'] ?? '');
        $cur_merchant_secret = trim($_POST['merchant_secret'] ?? '');
        $cur_mode = ($_POST['mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
        $cur_return_url = trim($_POST['return_url'] ?? '');
        $cur_cancel_url = trim($_POST['cancel_url'] ?? '');
        $cur_notify_url = trim($_POST['notify_url'] ?? '');

        setting_set('payhere_merchant_id', $cur_merchant_id);
        setting_set('payhere_merchant_secret', $cur_merchant_secret);
        setting_set('payhere_mode', $cur_mode);
        setting_set('payhere_return_url', $cur_return_url);
        setting_set('payhere_cancel_url', $cur_cancel_url);
        setting_set('payhere_notify_url', $cur_notify_url);
        $message = 'PayHere settings saved';
    } elseif ($action === 'clear_payhere') {
        $keys = ['payhere_merchant_id','payhere_merchant_secret','payhere_mode','payhere_return_url','payhere_cancel_url','payhere_notify_url'];
        $in = implode(',', array_fill(0, count($keys), '?'));
        $types = str_repeat('s', count($keys));
        $stmt = db()->prepare("DELETE FROM settings WHERE setting_key IN ($in)");
        $stmt->bind_param($types, ...$keys);
        $stmt->execute();
        $stmt->close();
        $message = 'PayHere settings cleared';
        // Reset to defaults from config
        $cur_merchant_id = $merchant_id;
        $cur_merchant_secret = $merchant_secret;
        $cur_mode = (strpos($payhere_url, 'sandbox') !== false ? 'sandbox' : 'live');
        $cur_return_url = $return_url;
        $cur_cancel_url = $cancel_url;
        $cur_notify_url = $notify_url;
    }
}

// Effective checkout URL preview based on mode
$effective_payhere_url = ($cur_mode === 'live') ? 'https://www.payhere.lk/pay/checkout' : 'https://sandbox.payhere.lk/pay/checkout';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayHere Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="h4 mb-0">PayHere Management</h2>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">PayHere Settings</div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="form-label">Merchant ID</label>
                        <input type="text" class="form-control" name="merchant_id" value="<?php echo htmlspecialchars($cur_merchant_id); ?>" required>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="form-label">Merchant Secret</label>
                        <input type="text" class="form-control" name="merchant_secret" value="<?php echo htmlspecialchars($cur_merchant_secret); ?>" required>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="form-label">Mode</label>
                        <select class="form-select" name="mode">
                            <option value="sandbox" <?php echo $cur_mode==='sandbox'?'selected':''; ?>>Sandbox</option>
                            <option value="live" <?php echo $cur_mode==='live'?'selected':''; ?>>Live</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label">Return URL</label>
                        <input type="url" class="form-control" name="return_url" value="<?php echo htmlspecialchars($cur_return_url); ?>" required>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label">Cancel URL</label>
                        <input type="url" class="form-control" name="cancel_url" value="<?php echo htmlspecialchars($cur_cancel_url); ?>" required>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label">Notify URL</label>
                        <input type="url" class="form-control" name="notify_url" value="<?php echo htmlspecialchars($cur_notify_url); ?>" required>
                    </div>
                    <div class="col-12">
                        <div class="form-text">Effective Checkout URL: <code><?php echo htmlspecialchars($effective_payhere_url); ?></code></div>
                    </div>
                    <div class="col-12 d-flex gap-2 mt-2">
                        <input type="hidden" name="action" value="save_payhere" />
                        <button class="btn btn-primary" type="submit">Save Settings</button>
                    </div>
                </form>
                <form method="post" class="mt-2">
                    <input type="hidden" name="action" value="clear_payhere" />
                    <button class="btn btn-secondary" type="submit">Clear Settings</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Hints</div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Merchant ID and Secret are provided by PayHere.</li>
                    <li>Switch Sandbox/Live using the Mode selector. Checkout URL changes accordingly.</li>
                    <li>Return/Cancel/Notify URLs should be accessible by PayHere and your app.</li>
                </ul>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
 </body>
 </html>
