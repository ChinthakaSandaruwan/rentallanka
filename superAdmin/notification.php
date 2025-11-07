<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_super_admin();
require_once __DIR__ . '/../config/config.php';

[$flash, $flash_type] = (function(){
  if (function_exists('get_flash')) { return get_flash(); }
  return [null, null];
})();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  // Bulk operations
  if (in_array($action, ['bulk_read','bulk_unread','bulk_delete'], true)) {
    $ids = $_POST['ids'] ?? [];
    if (is_array($ids) && $ids) {
      $ids = array_values(array_filter(array_map('intval', $ids), function($v){ return $v > 0; }));
      if ($ids) {
        db()->begin_transaction();
        try {
          foreach ($ids as $id) {
            if ($action === 'bulk_read') {
              $st = db()->prepare('UPDATE notifications SET is_read=1 WHERE notification_id=? LIMIT 1');
              $st->bind_param('i', $id);
              $st->execute();
              $st->close();
            } elseif ($action === 'bulk_unread') {
              $st = db()->prepare('UPDATE notifications SET is_read=0 WHERE notification_id=? LIMIT 1');
              $st->bind_param('i', $id);
              $st->execute();
              $st->close();
            } elseif ($action === 'bulk_delete') {
              $st = db()->prepare('DELETE FROM notifications WHERE notification_id=? LIMIT 1');
              $st->bind_param('i', $id);
              $st->execute();
              $st->close();
            }
          }
          db()->commit();
          $msg = $action === 'bulk_delete' ? 'Deleted selected' : ($action === 'bulk_read' ? 'Marked selected as read' : 'Marked selected as unread');
          redirect_with_message($GLOBALS['base_url'] . '/superAdmin/notification.php', $msg, 'success');
        } catch (Throwable $e) {
          db()->rollback();
          redirect_with_message($GLOBALS['base_url'] . '/superAdmin/notification.php', 'Bulk action failed', 'error');
        }
      }
    }
    redirect_with_message($GLOBALS['base_url'] . '/superAdmin/notification.php', 'No items selected', 'error');
  }

  $nid = (int)($_POST['notification_id'] ?? 0);
  if ($nid > 0) {
    if ($action === 'read') {
      $st = db()->prepare('UPDATE notifications SET is_read=1 WHERE notification_id=? LIMIT 1');
      $st->bind_param('i', $nid);
      $ok = $st->execute();
      $st->close();
      redirect_with_message($GLOBALS['base_url'] . '/superAdmin/notification.php', $ok ? 'Marked as read' : 'Unable to mark as read', $ok ? 'success' : 'error');
    } elseif ($action === 'unread') {
      $st = db()->prepare('UPDATE notifications SET is_read=0 WHERE notification_id=? LIMIT 1');
      $st->bind_param('i', $nid);
      $ok = $st->execute();
      $st->close();
      redirect_with_message($GLOBALS['base_url'] . '/superAdmin/notification.php', $ok ? 'Marked as unread' : 'Unable to mark as unread', $ok ? 'success' : 'error');
    } elseif ($action === 'delete') {
      $st = db()->prepare('DELETE FROM notifications WHERE notification_id=? LIMIT 1');
      $st->bind_param('i', $nid);
      $ok = $st->execute();
      $st->close();
      redirect_with_message($GLOBALS['base_url'] . '/superAdmin/notification.php', $ok ? 'Notification deleted' : 'Unable to delete notification', $ok ? 'success' : 'error');
    }
  }
  redirect_with_message($GLOBALS['base_url'] . '/superAdmin/notification.php', 'Invalid action', 'error');
}

$list = [];
// Filters
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$qtext = trim($_GET['q'] ?? '');
$dfrom = trim($_GET['dfrom'] ?? '');
$dto = trim($_GET['dto'] ?? '');
$where = [];
$params = [];
$types = '';
if ($type !== '' && in_array($type, ['system','rental','payment','other'], true)) {
  $where[] = 'n.type = ?';
  $params[] = $type; $types .= 's';
}
if ($status !== '') {
  if ($status === 'read') { $where[] = 'n.is_read = 1'; }
  elseif ($status === 'unread') { $where[] = 'n.is_read = 0'; }
}
if ($qtext !== '') {
  $like = '%' . $qtext . '%';
  $where[] = '(n.title LIKE ? OR n.message LIKE ? OR u.name LIKE ?)';
  $params[] = $like; $types .= 's';
  $params[] = $like; $types .= 's';
  $params[] = $like; $types .= 's';
}
if ($dfrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dfrom)) {
  $where[] = 'n.created_at >= ?';
  $params[] = $dfrom . ' 00:00:00'; $types .= 's';
}
if ($dto !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dto)) {
  $where[] = 'n.created_at <= ?';
  $params[] = $dto . ' 23:59:59'; $types .= 's';
}
$sql = 'SELECT n.notification_id, n.title, n.message, n.type, n.is_read, n.created_at, '
     . 'u.user_id, u.name, u.role '
     . 'FROM notifications n '
     . 'LEFT JOIN users u ON u.user_id = n.user_id';
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY n.created_at DESC, n.notification_id DESC';
$stmt = db()->prepare($sql);
if ($params) {
  $bind = array_merge([$types], $params);
  // Convert to references for bind_param
  $refs = [];
  foreach ($bind as $k => $v) { $refs[$k] = &$bind[$k]; }
  call_user_func_array([$stmt, 'bind_param'], $refs);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $list[] = $row; }
$stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Super Admin - Notifications</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>.nowrap{white-space:nowrap}</style>
</head>
<body>
<?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">All Notifications</h1>
    <a href="<?= $base_url ?>/superAdmin/index.php" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>

  <form method="get" class="card mb-3">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label">Type</label>
          <select name="type" class="form-select">
            <option value="">All</option>
            <?php foreach (['system','rental','payment','other'] as $opt): ?>
              <option value="<?= $opt ?>" <?= $type===$opt?'selected':'' ?>><?= ucfirst($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="">All</option>
            <option value="unread" <?= $status==='unread'?'selected':'' ?>>Unread</option>
            <option value="read" <?= $status==='read'?'selected':'' ?>>Read</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">From</label>
          <input type="date" name="dfrom" value="<?= htmlspecialchars($dfrom) ?>" class="form-control">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">To</label>
          <input type="date" name="dto" value="<?= htmlspecialchars($dto) ?>" class="form-control">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Search</label>
          <input type="text" name="q" value="<?= htmlspecialchars($qtext) ?>" placeholder="Title, message, recipient" class="form-control">
        </div>
      </div>
      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Apply</button>
        <a class="btn btn-outline-secondary" href="<?= $base_url ?>/superAdmin/notification.php">Reset</a>
      </div>
    </div>
  </form>
  <?php if (!empty($flash)): ?>
    <div class="alert <?= $flash_type==='success'?'alert-success':'alert-danger' ?>" role="alert"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <?php if (!$list): ?>
    <div class="alert alert-secondary">No notifications yet.</div>
  <?php else: ?>
    <form method="post" id="bulkForm">
    <div class="d-flex gap-2 mb-2">
      <button name="action" value="bulk_read" type="submit" class="btn btn-sm btn-outline-primary">Mark Read</button>
      <button name="action" value="bulk_unread" type="submit" class="btn btn-sm btn-outline-secondary">Mark Unread</button>
      <button name="action" value="bulk_delete" type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete selected notifications?');">Delete</button>
    </div>
    <div class="table-responsive">
      <table class="table align-middle table-striped">
        <thead class="table-light">
          <tr>
            <th style="width:32px"><input type="checkbox" id="checkAll"></th>
            <th>ID</th>
            <th>Recipient</th>
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
              <td><input type="checkbox" name="ids[]" value="<?= (int)$n['notification_id'] ?>" class="rowcheck"></td>
              <td class="text-muted small">#<?= (int)$n['notification_id'] ?></td>
              <td>
                <div class="fw-semibold mb-0"><?= htmlspecialchars($n['name'] ?? '-') ?></div>
                <div class="text-muted small">User ID: <?= (int)($n['user_id'] ?? 0) ?> • <?= htmlspecialchars($n['role'] ?? '-') ?></div>
              </td>
              <td class="fw-semibold"><?= htmlspecialchars($n['title']) ?></td>
              <td><?= nl2br(htmlspecialchars($n['message'])) ?></td>
              <td><span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($n['type']) ?></span></td>
              <td>
                <?php if ((int)$n['is_read'] === 1): ?>
                  <span class="badge bg-success">Read</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">Unread</span>
                <?php endif; ?>
              </td>
              <td class="nowrap text-muted small"><?= htmlspecialchars($n['created_at']) ?></td>
              <td class="text-end">
                <form method="post" action="" class="d-inline">
                  <input type="hidden" name="notification_id" value="<?= (int)$n['notification_id'] ?>">
                  <?php if ((int)$n['is_read'] === 1): ?>
                    <input type="hidden" name="action" value="unread">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-envelope"></i> Mark unread</button>
                  <?php else: ?>
                    <input type="hidden" name="action" value="read">
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-envelope-open"></i> Mark read</button>
                  <?php endif; ?>
                </form>
                <form method="post" action="" class="d-inline ms-1" onsubmit="return confirm('Delete this notification?');">
                  <input type="hidden" name="notification_id" value="<?= (int)$n['notification_id'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    </form>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function(){
    const all = document.getElementById('checkAll');
    const rows = document.querySelectorAll('.rowcheck');
    if (all) {
      all.addEventListener('change', function(){ rows.forEach(cb=>cb.checked = all.checked); });
    }
  })();
</script>
</body>
</html>
