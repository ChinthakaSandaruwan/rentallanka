<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../public/includes/auth_guard.php'; require_role('owner');

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
if ($uid <= 0) {
  http_response_code(302);
  header('Location: /rentallanka/index.php');
  exit;
}

[$flash, $flash_type] = (function(){
  if (function_exists('get_flash')) { return get_flash(); }
  return [null, null];
})();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $nid = (int)($_POST['notification_id'] ?? 0);
  if ($nid > 0) {
    if ($action === 'read') {
      $st = db()->prepare('UPDATE notifications SET is_read=1 WHERE notification_id=? AND user_id=? LIMIT 1');
      $st->bind_param('ii', $nid, $uid);
      $ok = $st->execute();
      $st->close();
      redirect_with_message($GLOBALS['base_url'] . '/owner/notification.php', $ok ? 'Marked as read' : 'Unable to mark as read', $ok ? 'success' : 'error');
    } elseif ($action === 'unread') {
      $st = db()->prepare('UPDATE notifications SET is_read=0 WHERE notification_id=? AND user_id=? LIMIT 1');
      $st->bind_param('ii', $nid, $uid);
      $ok = $st->execute();
      $st->close();
      redirect_with_message($GLOBALS['base_url'] . '/owner/notification.php', $ok ? 'Marked as unread' : 'Unable to mark as unread', $ok ? 'success' : 'error');
    } elseif ($action === 'delete') {
      $st = db()->prepare('DELETE FROM notifications WHERE notification_id=? AND user_id=? LIMIT 1');
      $st->bind_param('ii', $nid, $uid);
      $ok = $st->execute();
      $st->close();
      redirect_with_message($GLOBALS['base_url'] . '/owner/notification.php', $ok ? 'Notification deleted' : 'Unable to delete notification', $ok ? 'success' : 'error');
    }
  }
  redirect_with_message($GLOBALS['base_url'] . '/owner/notification.php', 'Invalid action', 'error');
}

$list = [];
$q = db()->prepare('SELECT notification_id, title, message, type, is_read, created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC');
$q->bind_param('i', $uid);
$q->execute();
$res = $q->get_result();
while ($row = $res->fetch_assoc()) { $list[] = $row; }
$q->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notifications</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>.nowrap{white-space:nowrap}</style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Your Notifications</h1>
    <a href="<?= $base_url ?>/owner/send_notification.php" class="btn btn-sm btn-primary"><i class="bi bi-send me-1"></i>Send Notification</a>
  </div>
  <?php if (!empty($flash)): ?>
    <div class="alert <?php echo ($flash_type==='success')?'alert-success':'alert-danger'; ?>" role="alert"><?php echo htmlspecialchars($flash); ?></div>
  <?php endif; ?>

  <?php if (!$list): ?>
    <div class="alert alert-secondary">No notifications yet.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Title</th>
            <th>Message</th>
            <th>Type</th>
            <th>Status</th>
            <th class="nowrap">Created</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($list as $n): ?>
            <tr>
              <td class="fw-semibold"><?php echo htmlspecialchars($n['title']); ?></td>
              <td><?php echo nl2br(htmlspecialchars($n['message'])); ?></td>
              <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($n['type']); ?></span></td>
              <td>
                <?php if ((int)$n['is_read'] === 1): ?>
                  <span class="badge bg-success">Read</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">Unread</span>
                <?php endif; ?>
              </td>
              <td class="nowrap text-muted small"><?php echo htmlspecialchars($n['created_at']); ?></td>
              <td class="text-end">
                <form method="post" action="" class="d-inline">
                  <input type="hidden" name="notification_id" value="<?php echo (int)$n['notification_id']; ?>">
                  <?php if ((int)$n['is_read'] === 1): ?>
                    <input type="hidden" name="action" value="unread">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-envelope"></i> Mark unread</button>
                  <?php else: ?>
                    <input type="hidden" name="action" value="read">
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-envelope-open"></i> Mark read</button>
                  <?php endif; ?>
                </form>
                <form method="post" action="" class="d-inline ms-1" onsubmit="return confirm('Delete this notification?');">
                  <input type="hidden" name="notification_id" value="<?php echo (int)$n['notification_id']; ?>">
                  <input type="hidden" name="action" value="delete">
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

