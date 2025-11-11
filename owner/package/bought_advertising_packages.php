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
require_role('owner');
require_once __DIR__ . '/../../config/config.php';

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
if ($uid <= 0) {
    redirect_with_message($GLOBALS['base_url'] . '/auth/login.php', 'Please log in', 'error');
}

$rows = [];
try {
    $sql = 'SELECT bp.*, p.package_name, p.package_type, p.duration_days, p.max_properties, p.max_rooms, p.price
            FROM bought_packages bp
            JOIN packages p ON p.package_id = bp.package_id
            WHERE bp.user_id = ?
            ORDER BY bp.bought_package_id DESC';
    $st = db()->prepare($sql);
    $st->bind_param('i', $uid);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $st->close();
} catch (Throwable $e) { $rows = []; }

[$flash, $flash_type] = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Advertising Packages</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ===========================
       BOUGHT PACKAGES PAGE - Shared Design System
       =========================== */
    :root {
      --rl-primary: #004E98;
      --rl-light-bg: #EBEBEB;
      --rl-secondary: #C0C0C0;
      --rl-accent: #3A6EA5;
      --rl-dark: #FF6700;
      --rl-white: #ffffff;
      --rl-text: #1f2a37;
      --rl-text-secondary: #4a5568;
      --rl-text-muted: #718096;
      --rl-border: #e2e8f0;
      --rl-shadow-sm: 0 2px 12px rgba(0,0,0,.06);
      --rl-shadow-md: 0 4px 16px rgba(0,0,0,.1);
      --rl-shadow-lg: 0 10px 30px rgba(0,0,0,.15);
      --rl-radius: 12px;
      --rl-radius-lg: 16px;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      color: var(--rl-text);
      background: linear-gradient(180deg, #fff 0%, var(--rl-light-bg) 100%);
      min-height: 100vh;
    }

    .rl-container {
      padding-top: clamp(1.5rem, 2vw, 2.5rem);
      padding-bottom: clamp(1.5rem, 2vw, 2.5rem);
    }

    /* Page Header */
    .rl-page-header {
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
      border-radius: var(--rl-radius-lg);
      padding: 1.5rem 2rem;
      margin-bottom: 2rem;
      box-shadow: var(--rl-shadow-md);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .rl-page-title {
      font-size: clamp(1.5rem, 3vw, 1.875rem);
      font-weight: 800;
      color: var(--rl-white);
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .rl-btn-back {
      background: var(--rl-white);
      border: none;
      color: var(--rl-primary);
      font-weight: 600;
      padding: 0.5rem 1.25rem;
      border-radius: 8px;
      transition: all 0.2s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .rl-btn-back:hover {
      background: var(--rl-light-bg);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      color: var(--rl-primary);
    }

    /* Cards */
    .rl-form-card {
      background: var(--rl-white);
      border-radius: var(--rl-radius-lg);
      box-shadow: var(--rl-shadow-md);
      border: 2px solid var(--rl-border);
      overflow: hidden;
    }

    .rl-form-header {
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
      padding: 1.25rem 1.5rem;
      border-bottom: 2px solid var(--rl-border);
    }

    .rl-form-header-title {
      font-size: 1.125rem;
      font-weight: 700;
      color: var(--rl-text);
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .rl-form-body {
      padding: 1.5rem;
    }

    /* Package Cards */
    .pkg-card { position: relative; transition: all .3s cubic-bezier(.4,0,.2,1); }
    .pkg-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, var(--rl-primary) 0%, var(--rl-dark) 100%); opacity:0; transition:opacity .3s ease; }
    .pkg-card:hover { transform: translateY(-6px); box-shadow: var(--rl-shadow-lg); border-color: var(--rl-accent) !important; }
    .pkg-card:hover::before { opacity:1; }
    .pkg-title { font-weight:800; font-size:1.25rem; color:var(--rl-text); }
    .pkg-type { font-weight:700; text-transform:uppercase; }

    /* Badges */
    .rl-badge-accent { background: linear-gradient(135deg, var(--rl-accent) 0%, var(--rl-primary) 100%); color: var(--rl-white); padding: .375rem .875rem; border-radius: 20px; font-weight:700; font-size:.8125rem; letter-spacing:.5px; }
    .rl-badge-dark { background: linear-gradient(135deg, var(--rl-dark) 0%, #ff8533 100%); color: var(--rl-white); padding: .375rem .875rem; border-radius: 20px; font-weight:700; font-size:.8125rem; letter-spacing:.5px; }

    /* Buttons */
    .btn-primary { background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%); border:none; color:var(--rl-white); font-weight:700; padding:.875rem 1.5rem; border-radius:10px; box-shadow:0 4px 16px rgba(0,78,152,.25); transition:all .2s ease; font-size:.9375rem; }
    .btn-primary:hover { background: linear-gradient(135deg, #003a75 0%, #2d5a8f 100%); transform: translateY(-2px); box-shadow:0 6px 24px rgba(0,78,152,.35); color:var(--rl-white); }
    .btn-primary:active { transform: translateY(0); }

    /* Empty state */
    .rl-empty-state { text-align:center; padding:4rem 2rem; background:var(--rl-white); border-radius:var(--rl-radius-lg); box-shadow:var(--rl-shadow-sm); border:2px dashed var(--rl-border); }
    .rl-empty-state i { font-size:3rem; color:var(--rl-secondary); margin-bottom:1rem; }

    @media (max-width: 767px) {
      .rl-page-header { padding: 1.25rem 1rem; flex-direction: column; align-items: flex-start; }
      .rl-btn-back { width:100%; justify-content:center; }
      .rl-form-body { padding: 1rem; }
      .btn-primary { width: 100%; padding: .75rem 1.25rem; }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../../public/includes/navbar.php'; ?>
<div class="container rl-container">
  <div class="rl-page-header">
    <h1 class="rl-page-title"><i class="bi bi-bag-check"></i> My Advertising Packages</h1>
    <a href="../index.php" class="rl-btn-back"><i class="bi bi-speedometer2"></i> Dashboard</a>
  </div>

  <?php if ($flash): ?>
    <div class="rl-form-card mb-3">
      <div class="rl-form-body py-3">
        <div class="d-flex align-items-start">
          <i class="bi <?= $flash_type==='success'?'bi-check-circle text-success':'bi-exclamation-triangle text-warning' ?> me-2"></i>
          <div><?= htmlspecialchars($flash) ?></div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="rl-form-card mb-3">
    <div class="rl-form-body py-3">
      <div class="d-flex">
        <i class="bi bi-info-circle me-2 text-primary"></i>
        <div>
          Posting a property or room will automatically deduct one slot from the relevant purchased package
          (Property slot or Room slot). Only <strong>paid</strong> and <strong>active</strong> packages can be used.
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <?php foreach ($rows as $r): ?>
      <?php
        $typeLbl = ((int)($r['max_properties'] ?? 0) > 0) ? 'Property' : 'Room';
        $durLbl = ((string)($r['package_type'] ?? 'monthly') === 'yearly') ? 'Yearly' : 'Monthly';
        $remProps = (int)($r['remaining_properties'] ?? 0);
        $remRooms = (int)($r['remaining_rooms'] ?? 0);
        $isActive = ((string)($r['status'] ?? '') === 'active');
        $isPaid = ((string)($r['payment_status'] ?? '') === 'paid');
      ?>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="rl-form-card h-100 pkg-card">
          <div class="rl-form-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h5 class="pkg-title mb-0"><?= htmlspecialchars((string)($r['package_name'] ?? '')) ?></h5>
              <span class="badge rl-badge-dark pkg-type"><?= htmlspecialchars($durLbl) ?></span>
            </div>
            <ul class="list-unstyled small mb-3">
              <li><i class="bi bi-megaphone me-1"></i> Type: <?= htmlspecialchars($typeLbl) ?></li>
              <li><i class="bi bi-tags me-1"></i> Price: LKR <?= number_format((float)($r['price'] ?? 0), 2) ?></li>
              <?php if (!empty($r['end_date'])): ?>
                <li><i class="bi bi-calendar2-check me-1"></i> Ends: <?= htmlspecialchars((string)$r['end_date']) ?></li>
              <?php endif; ?>
            </ul>
            <div class="mb-2">
              <span class="badge rl-badge-accent">Status: <?= htmlspecialchars((string)$r['status']) ?></span>
              <span class="badge <?= $isPaid?'rl-badge-accent':'rl-badge-dark' ?>">Payment: <?= htmlspecialchars((string)$r['payment_status']) ?></span>
            </div>
            <div class="mt-auto">
              <div class="border rounded p-2 small">
                <div class="fw-semibold mb-1">Remaining Quotas</div>
                <div>Properties: <?= $remProps ?></div>
                <div>Rooms: <?= $remRooms ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <div class="col-12">
        <div class="rl-empty-state">
          <i class="bi bi-box-seam"></i>
          <p class="mb-3">You have not purchased any advertising packages yet.</p>
          <a href="buy_advertising_packages.php" class="btn btn-primary"><i class="bi bi-bag-plus me-1"></i>Buy a Package</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
