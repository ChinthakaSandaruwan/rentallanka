<?php
require_once __DIR__ . '/../config/config.php';

$to_e164 = function(string $phone){
    $p = preg_replace('/\D+/', '', $phone);
    if (preg_match('/^07[0-9]{8}$/', $p)) {
        return '+94' . substr($p, 1);
    }
    return $phone;
};

$me = $_SESSION['user'] ?? null;
if (!$me || ($me['role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$action = $_POST['action'] ?? '';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'invalidate') {
        $otp_id = (int)($_POST['otp_id'] ?? 0);
        if ($otp_id > 0) {
            $stmt = db()->prepare('UPDATE otp_verifications SET expires_at = NOW() WHERE otp_id = ?');
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
                $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
                $stmt = db()->prepare('INSERT INTO otp_verifications (user_id, otp_code, expires_at, is_verified) VALUES (?, ?, ?, 0)');
                $stmt->bind_param('iss', $user_id, $otp, $expires);
                $stmt->execute();
                $stmt->close();
                $sms = 'Your OTP code is '.$otp.'. It expires in 5 minutes.';
                $r = smslenz_send_sms($to_e164($usr['phone']), $sms);
                $message = $r['ok'] ? 'OTP resent' : ('Failed to send: '.($r['error'] ?? ''));
            } else {
                $error = 'User phone not found';
            }
        }
    }
}

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
        FROM otp_verifications o
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
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f4f4f4; text-align: left; }
        .ok { color: #090; }
        .err { color: #c00; }
        .row { margin-bottom: 12px; }
        input[type=text] { padding: 8px; width: 260px; }
        button { padding: 6px 10px; border: 0; border-radius: 4px; cursor: pointer; }
        .btn { background: #0d6efd; color: #fff; }
        .btn.gray { background: #6c757d; }
    </style>
</head>
<body>
    <h2>OTP Management</h2>
    <?php if ($message): ?>
        <div class="ok"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="err"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="get" class="row">
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search by phone or email" />
        <button class="btn" type="submit">Search</button>
    </form>

    <table>
        <thead>
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
                        <form method="post" style="display:inline">
                            <input type="hidden" name="action" value="invalidate" />
                            <input type="hidden" name="otp_id" value="<?php echo (int)$r['otp_id']; ?>" />
                            <button class="btn gray" type="submit">Invalidate</button>
                        </form>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="action" value="resend" />
                            <input type="hidden" name="user_id" value="<?php echo (int)$r['user_id']; ?>" />
                            <button class="btn" type="submit">Resend</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
