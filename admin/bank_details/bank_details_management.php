<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
  $f = __DIR__ . '/../../error/error.log';
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

require_once __DIR__ . '/../../public/includes/auth_guard.php';
require_role('admin');
require_once __DIR__ . '/../../config/config.php';

if (empty($_SESSION['csrf_bank'])) {
  $_SESSION['csrf_bank'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_bank'];

$flash = '';
$flash_type = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $token = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrf, $token)) {
    $error = 'Invalid request';
  } else {
    $action = (string)($_POST['action'] ?? '');
    $bank_id = (int)($_POST['bank_id'] ?? 0);
    $bank_name = trim((string)($_POST['bank_name'] ?? ''));
    $branch = trim((string)($_POST['branch'] ?? ''));
    $account_number = trim((string)($_POST['account_number'] ?? ''));
    $account_holder_name = trim((string)($_POST['account_holder_name'] ?? ''));

    try {
      if ($action === 'create') {
        if ($bank_name === '' || $branch === '' || $account_number === '' || $account_holder_name === '') {
          throw new Exception('All fields are required');
        }
        $st = db()->prepare('INSERT INTO bank_details (bank_name, branch, account_number, account_holder_name) VALUES (?,?,?,?)');
        if (!$st) { throw new Exception('Prepare failed'); }
        $st->bind_param('ssss', $bank_name, $branch, $account_number, $account_holder_name);
        if (!$st->execute()) { $st->close(); throw new Exception('Create failed'); }
        $st->close();
        $flash = 'Bank detail added';
        $flash_type = 'success';
      } elseif ($action === 'update') {
        if ($bank_id <= 0) { throw new Exception('Invalid bank id'); }
        if ($bank_name === '' || $branch === '' || $account_number === '' || $account_holder_name === '') {
          throw new Exception('All fields are required');
        }
        $st = db()->prepare('UPDATE bank_details SET bank_name=?, branch=?, account_number=?, account_holder_name=? WHERE bank_id=?');
        if (!$st) { throw new Exception('Prepare failed'); }
        $st->bind_param('ssssi', $bank_name, $branch, $account_number, $account_holder_name, $bank_id);
        if (!$st->execute()) { $st->close(); throw new Exception('Update failed'); }
        $st->close();
        $flash = 'Bank detail updated';
        $flash_type = 'success';
      } elseif ($action === 'delete') {
        if ($bank_id <= 0) { throw new Exception('Invalid bank id'); }
        $st = db()->prepare('DELETE FROM bank_details WHERE bank_id=?');
        if (!$st) { throw new Exception('Prepare failed'); }
        $st->bind_param('i', $bank_id);
        if (!$st->execute()) { $st->close(); throw new Exception('Delete failed'); }
        $st->close();
        $flash = 'Bank detail deleted';
        $flash_type = 'success';
      } else {
        throw new Exception('Unknown action');
      }
    } catch (Throwable $e) {
      $error = $e->getMessage();
    }
  }

  $msg = $flash ?: ($error ?: 'Action completed');
  $typ = $flash ? ($flash_type ?: 'success') : ($error ? 'error' : 'success');
  redirect_with_message(rtrim($base_url,'/') . '/admin/bank_details/bank_details_management.php', $msg, $typ);
  exit;
}

$list = [];
$res = db()->query('SELECT bank_id, bank_name, branch, account_number, account_holder_name, created_at FROM bank_details ORDER BY bank_id DESC');
if ($res) { while ($row = $res->fetch_assoc()) { $list[] = $row; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
  <?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Bank Details</h1>
      <div class="d-flex align-items-center gap-2">
        <a href="../index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mdlCreate"><i class="bi bi-plus-lg me-1"></i>Add New</button>
      </div>
    </div>

    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:80px;">ID</th>
                <th>Bank</th>
                <th>Branch</th>
                <th>Account #</th>
                <th>Account Holder</th>
                <th style="width:160px;">Created</th>
                <th style="width:140px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($list)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No bank details found.</td></tr>
              <?php else: foreach ($list as $b): ?>
                <tr>
                  <td><?= (int)$b['bank_id'] ?></td>
                  <td><?= htmlspecialchars($b['bank_name'] ?? '') ?></td>
                  <td><?= htmlspecialchars($b['branch'] ?? '') ?></td>
                  <td><?= htmlspecialchars($b['account_number'] ?? '') ?></td>
                  <td><?= htmlspecialchars($b['account_holder_name'] ?? '') ?></td>
                  <td><?= htmlspecialchars($b['created_at'] ?? '') ?></td>
                  <td class="text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit"
                      data-id="<?= (int)$b['bank_id'] ?>"
                      data-bank_name="<?= htmlspecialchars($b['bank_name'] ?? '', ENT_QUOTES) ?>"
                      data-branch="<?= htmlspecialchars($b['branch'] ?? '', ENT_QUOTES) ?>"
                      data-account_number="<?= htmlspecialchars($b['account_number'] ?? '', ENT_QUOTES) ?>"
                      data-account_holder_name="<?= htmlspecialchars($b['account_holder_name'] ?? '', ENT_QUOTES) ?>">
                      <i class="bi bi-pencil-square me-1"></i>Edit
                    </button>
                    <form method="post" class="d-inline align-middle form-del">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="bank_id" value="<?= (int)$b['bank_id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="mdlCreate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Bank Detail</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form method="post" id="formCreate" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="create">
            <div class="mb-3">
              <label class="form-label">Bank name</label>
              <input type="text" class="form-control" name="bank_name" required maxlength="100">
              <div class="invalid-feedback">Required</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Branch</label>
              <input type="text" class="form-control" name="branch" required maxlength="100">
              <div class="invalid-feedback">Required</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Account number</label>
              <input type="text" class="form-control" name="account_number" required maxlength="50">
              <div class="invalid-feedback">Required</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Account holder name</label>
              <input type="text" class="form-control" name="account_holder_name" required maxlength="100">
              <div class="invalid-feedback">Required</div>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">Save</button>
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="mdlEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Bank Detail</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form method="post" id="formEdit" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="bank_id" id="edit_bank_id" value="0">
            <div class="mb-3">
              <label class="form-label">Bank name</label>
              <input type="text" class="form-control" name="bank_name" id="edit_bank_name" required maxlength="100">
              <div class="invalid-feedback">Required</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Branch</label>
              <input type="text" class="form-control" name="branch" id="edit_branch" required maxlength="100">
              <div class="invalid-feedback">Required</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Account number</label>
              <input type="text" class="form-control" name="account_number" id="edit_account_number" required maxlength="50">
              <div class="invalid-feedback">Required</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Account holder name</label>
              <input type="text" class="form-control" name="account_holder_name" id="edit_account_holder_name" required maxlength="100">
              <div class="invalid-feedback">Required</div>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">Update</button>
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    (function(){
      try {
        document.querySelectorAll('.form-del').forEach(function(f){
          f.addEventListener('submit', async function(e){
            e.preventDefault();
            const res = await Swal.fire({ title: 'Delete this bank detail?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', cancelButtonText: 'Cancel' });
            if (res.isConfirmed) { f.submit(); }
          });
        });

        const editModalEl = document.getElementById('mdlEdit');
        const editModal = new bootstrap.Modal(editModalEl);
        document.querySelectorAll('.btn-edit').forEach(function(btn){
          btn.addEventListener('click', function(){
            document.getElementById('edit_bank_id').value = this.dataset.id || '0';
            document.getElementById('edit_bank_name').value = this.dataset.bank_name || '';
            document.getElementById('edit_branch').value = this.dataset.branch || '';
            document.getElementById('edit_account_number').value = this.dataset.account_number || '';
            document.getElementById('edit_account_holder_name').value = this.dataset.account_holder_name || '';
            editModal.show();
          });
        });

        document.querySelectorAll('.needs-validation').forEach(function(form){
          form.addEventListener('submit', function(e){
            if (!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
            form.classList.add('was-validated');
          }, false);
        });
      } catch(_) {}
    })();
  </script>
</body>
</html>

