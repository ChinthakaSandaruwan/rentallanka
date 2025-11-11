<?php
require_once __DIR__ . '/../public/includes/auth_guard.php';
require_super_admin();
require_once __DIR__ . '/../config/config.php';

$uploads_root = realpath(__DIR__ . '/../uploads');
if ($uploads_root === false) {
    @mkdir(__DIR__ . '/../uploads', 0777, true);
    $uploads_root = realpath(__DIR__ . '/../uploads');
}

function um_safe_join(string $base, string $rel): ?string {
    $rel = str_replace(['\\', "\0"], ['/', ''], $rel);
    $rel = ltrim($rel, '/');
    $target = $base . DIRECTORY_SEPARATOR . $rel;
    $real = realpath($target);
    if ($real === false) {
        // Path may not exist yet (e.g., new file). Normalize manually.
        $parts = [];
        foreach (explode('/', $rel) as $seg) {
            if ($seg === '' || $seg === '.') continue;
            if ($seg === '..') { array_pop($parts); continue; }
            $parts[] = $seg;
        }
        $real = $base . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
    }
    $baseReal = realpath($base) ?: $base;
    $realNorm = str_replace('\\', '/', $real);
    $baseNorm = rtrim(str_replace('\\', '/', $baseReal), '/');
    if (strpos($realNorm, $baseNorm) !== 0) { return null; }
    return $real;
}

function um_list_dir(string $dir): array {
    $items = [];
    if (!is_dir($dir)) { return $items; }
    $dh = opendir($dir);
    if (!$dh) { return $items; }
    while (($entry = readdir($dh)) !== false) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        $items[] = [
            'name' => $entry,
            'path' => $path,
            'is_dir' => is_dir($path),
            'size' => is_file($path) ? filesize($path) : 0,
            'mtime' => filemtime($path) ?: 0,
        ];
    }
    closedir($dh);
    usort($items, function($a,$b){
        if ($a['is_dir'] !== $b['is_dir']) return $a['is_dir'] ? -1 : 1;
        return strcasecmp($a['name'], $b['name']);
    });
    return $items;
}

function um_rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $p = $file->getPathname();
        if ($file->isDir()) { @rmdir($p); } else { @unlink($p); }
    }
    @rmdir($dir);
}

function um_clean(string $root): int {
    $removed = 0;
    $it = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $p = $file->getPathname();
        if ($file->isFile() && filesize($p) === 0) {
            if (@unlink($p)) { $removed++; }
        } elseif ($file->isDir()) {
            // Remove empty dirs
            $scan = @scandir($p);
            if ($scan && count($scan) <= 2) {
                if (@rmdir($p)) { $removed++; }
            }
        }
    }
    return $removed;
}

$rel = trim($_GET['p'] ?? '', '/');
$current_dir = um_safe_join($uploads_root, $rel);
if ($current_dir === null || !is_dir($current_dir)) {
    $rel = '';
    $current_dir = $uploads_root;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_folder') {
        $name = trim($_POST['folder_name'] ?? '');
        if ($name === '' || preg_match('/[\\\\\/:*?"<>|]/', $name)) {
            redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'Invalid folder name', 'error');
        }
        $dest = um_safe_join($current_dir, $name);
        if ($dest === null) {
            redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'Invalid path', 'error');
        }
        if (is_dir($dest)) {
            redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'Folder exists', 'error');
        }
        @mkdir($dest, 0777, true);
        redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'Folder created');
    } elseif ($action === 'upload_file') {
        if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'No file uploaded', 'error');
        }
        $name = basename($_FILES['file']['name']);
        if ($name === '' || preg_match('/[\\\\\/:*?"<>|]/', $name)) {
            redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'Invalid file name', 'error');
        }
        $dest = um_safe_join($current_dir, $name);
        if ($dest === null) {
            redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'Invalid path', 'error');
        }
        if (!@move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'Upload failed', 'error');
        }
        redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'File uploaded');
    } elseif ($action === 'rename') {
        $old = trim($_POST['item'] ?? '');
        $new = trim($_POST['new_name'] ?? '');
        if ($old === '' || $new === '' || preg_match('/[\\\\\/:*?"<>|]/', $new)) {
            redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'Invalid rename', 'error');
        }
        $oldPath = um_safe_join($current_dir, $old);
        $newPath = um_safe_join($current_dir, $new);
        if ($oldPath === null || $newPath === null) {
            redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'Invalid path', 'error');
        }
        if (!@rename($oldPath, $newPath)) {
            redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'Rename failed', 'error');
        }
        redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'Renamed');
    } elseif ($action === 'delete') {
        $item = trim($_POST['item'] ?? '');
        if ($item === '') {
            redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'Invalid delete', 'error');
        }
        $path = um_safe_join($current_dir, $item);
        if ($path === null) {
            redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'Invalid path', 'error');
        }
        if (is_dir($path)) { um_rrmdir($path); } else { @unlink($path); }
        redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'Deleted');
    } elseif ($action === 'clean') {
        $n = um_clean($uploads_root);
        redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'Cleaned ' . $n . ' items');
    } elseif ($action === 'delete_all') {
        // Delete all contents inside the CURRENT directory, but keep the directory itself
        $removed = 0;
        $scan = @scandir($current_dir) ?: [];
        foreach ($scan as $entry) {
            if ($entry === '.' || $entry === '..') { continue; }
            $path = $current_dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) { um_rrmdir($path); $removed++; }
            elseif (is_file($path)) { if (@unlink($path)) { $removed++; } }
        }
        redirect_with_message($base_url . '/superAdmin/uploads_management.php?p=' . urlencode($rel), 'Deleted all items in current folder (' . (int)$removed . ' removed)');
    }
}

[$flash, $flash_type] = get_flash();
$items = um_list_dir($current_dir);

// Build breadcrumbs
$crumbs = [];
$acc = '';
if ($rel !== '') {
    foreach (explode('/', $rel) as $seg) {
        $acc = ($acc === '' ? $seg : ($acc . '/' . $seg));
        $crumbs[] = [$seg, $acc];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Uploads Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Uploads Management</h3>
    <div class="d-flex align-items-center gap-2">
      <a href="index.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
      <form method="post" class="d-inline" data-swal="clean">
        <input type="hidden" name="action" value="clean">
        <button class="btn btn-outline-secondary btn-sm" type="submit">Clean</button>
      </form>
      <form method="post" class="d-inline" data-swal="delete-all">
        <input type="hidden" name="action" value="delete_all">
        <button class="btn btn-outline-danger btn-sm" type="submit">Delete All</button>
      </form>
    </div>
  </div>

  <?php if ($flash): ?>
  <?php endif; ?>

  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= $base_url ?>/superAdmin/uploads_management.php">uploads</a></li>
      <?php foreach ($crumbs as [$name,$path]): ?>
        <li class="breadcrumb-item"><a href="<?= $base_url ?>/superAdmin/uploads_management.php?p=<?= urlencode($path) ?>"><?= htmlspecialchars($name) ?></a></li>
      <?php endforeach; ?>
    </ol>
  </nav>

  <div class="card mb-3">
    <div class="card-header">New</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <form method="post" enctype="multipart/form-data" class="vstack gap-2">
            <label class="form-label">Upload file to current folder</label>
            <input type="file" class="form-control" name="file" required>
            <input type="hidden" name="action" value="upload_file">
            <button class="btn btn-primary" type="submit">Upload</button>
          </form>
        </div>
        <div class="col-md-6">
          <form method="post" class="vstack gap-2">
            <label class="form-label">Create folder</label>
            <input type="text" class="form-control" name="folder_name" placeholder="Folder name" required>
            <input type="hidden" name="action" value="create_folder">
            <button class="btn btn-outline-primary" type="submit">Create</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Listing: <code><?= htmlspecialchars($rel === '' ? '/' : '/' . $rel) ?></code></span>
      <?php if ($rel !== ''): ?>
        <?php 
          $up = explode('/', $rel); array_pop($up); $up = implode('/', $up);
        ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= $base_url ?>/superAdmin/uploads_management.php?p=<?= urlencode($up) ?>">Up</a>
      <?php endif; ?>
    </div>
    <div class="list-group list-group-flush">
      <?php if (empty($items)): ?>
        <div class="list-group-item text-muted">Empty</div>
      <?php endif; ?>
      <?php foreach ($items as $it): ?>
        <div class="list-group-item d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-3">
            <i class="bi <?= $it['is_dir'] ? 'bi-folder' : 'bi-file-earmark' ?>"></i>
            <?php if ($it['is_dir']): ?>
              <a href="<?= $base_url ?>/superAdmin/uploads_management.php?p=<?= urlencode(trim($rel . '/' . $it['name'], '/')) ?>"><?= htmlspecialchars($it['name']) ?></a>
            <?php else: ?>
              <span><?= htmlspecialchars($it['name']) ?></span>
            <?php endif; ?>
            <span class="text-muted small"><?= $it['is_dir'] ? 'dir' : (number_format((float)$it['size']/1024, 1) . ' KB') ?> • <?= date('Y-m-d H:i', $it['mtime']) ?></span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <?php if (!$it['is_dir']): ?>
              <a class="btn btn-sm btn-outline-success" href="<?= $base_url ?>/uploads/<?= urlencode(trim($rel . '/' . $it['name'], '/')) ?>" target="_blank">Open</a>
            <?php endif; ?>
            <form method="post" class="d-inline-flex gap-2">
              <input type="hidden" name="action" value="rename">
              <input type="hidden" name="item" value="<?= htmlspecialchars($it['name']) ?>">
              <input type="text" name="new_name" class="form-control form-control-sm" placeholder="New name" required>
              <button class="btn btn-sm btn-outline-secondary" type="submit">Rename</button>
            </form>
            <form method="post" class="d-inline" data-swal="delete-item" data-item-type="<?= $it['is_dir'] ? 'folder' : 'file' ?>" data-item-name="<?= htmlspecialchars($it['name']) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="item" value="<?= htmlspecialchars($it['name']) ?>">
              <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function(){
    const flash = <?php echo json_encode($flash ?? ''); ?>;
    const flashType = <?php echo json_encode($flash_type ?? 'info'); ?>;
    if (flash) {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: (flashType === 'error') ? 'error' : (flashType === 'warning' ? 'warning' : 'success'),
        title: flash,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
      });
    }

    const cleanForm = document.querySelector('form[data-swal="clean"]');
    if (cleanForm) {
      cleanForm.addEventListener('submit', function(e){
        e.preventDefault();
        Swal.fire({
          title: 'Clean uploads?',
          text: 'Remove zero-byte files and empty folders.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, clean',
          cancelButtonText: 'Cancel'
        }).then(res => { if (res.isConfirmed) cleanForm.submit(); });
      });
    }

    const delAllForm = document.querySelector('form[data-swal="delete-all"]');
    if (delAllForm) {
      delAllForm.addEventListener('submit', function(e){
        e.preventDefault();
        Swal.fire({
          title: 'Delete ALL items?',
          text: 'This will remove all files and folders in this directory. This cannot be undone.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, delete all',
          cancelButtonText: 'Cancel'
        }).then(res => { if (res.isConfirmed) delAllForm.submit(); });
      });
    }

    document.querySelectorAll('form[data-swal="delete-item"]').forEach(frm => {
      frm.addEventListener('submit', function(e){
        e.preventDefault();
        const itemType = frm.getAttribute('data-item-type') || 'item';
        const itemName = frm.getAttribute('data-item-name') || '';
        Swal.fire({
          title: `Delete this ${itemType}?`,
          text: itemName ? `\"${itemName}\" will be removed.` : '',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, delete',
          cancelButtonText: 'Cancel'
        }).then(res => { if (res.isConfirmed) frm.submit(); });
      });
    });
  });
</script>
</body>
</html>
