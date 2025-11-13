<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', ___DIR___ . '/../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
  $f = ___DIR___ . '/../error/error.log';
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

require_once ___DIR___ . '/../public/includes/auth_guard.php';
require_role('customer');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customer Dashboard</title>

  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Inter font -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root { --rl-primary:#004E98; --rl-light-bg:#EBEBEB; --rl-secondary:#C0C0C0; --rl-accent:#3A6EA5; --rl-dark:#FF6700; --rl-white:#ffffff; --rl-text:#1f2a37; --rl-text-secondary:#4a5568; --rl-text-muted:#718096; --rl-border:#e2e8f0; --rl-shadow-sm:0 2px 12px rgba(0,0,0,.06); --rl-shadow-md:0 4px 16px rgba(0,0,0,.1); --rl-shadow-lg:0 10px 30px rgba(0,0,0,.15); --rl-radius:12px; --rl-radius-lg:16px; }
    body { font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; color:var(--rl-text); background:linear-gradient(180deg,#fff 0%, var(--rl-light-bg) 100%); min-height:100vh; }
    .rl-container { padding-top:clamp(1.5rem,2vw,2.5rem); padding-bottom:clamp(1.5rem,2vw,2.5rem); max-width:100%; overflow-x:hidden; }

    /* Header */
    .rl-dashboard-header { background:linear-gradient(135deg,var(--rl-primary) 0%, var(--rl-accent) 100%); border-radius:var(--rl-radius-lg); padding:2rem; margin-bottom:2.5rem; box-shadow:var(--rl-shadow-md); position:relative; overflow:hidden; }
    .rl-dashboard-header::before { content:''; position:absolute; top:-50%; right:-10%; width:300px; height:300px; background:rgba(255,255,255,.1); border-radius:50%; }
    .rl-dashboard-header::after { content:''; position:absolute; bottom:-30%; left:-5%; width:200px; height:200px; background:rgba(255,103,0,.15); border-radius:50%; }
    .rl-dashboard-title { font-size:clamp(1.5rem,3vw,2rem); font-weight:800; color:var(--rl-white); margin:0; text-align:center; text-shadow:0 2px 8px rgba(0,0,0,.2); }
    .rl-dashboard-subtitle { text-align:center; color:rgba(255,255,255,.9); margin-top:.5rem; font-size:1rem; }

    /* Cards */
    .rl-action-card { background:var(--rl-white); border-radius:var(--rl-radius); border:2px solid var(--rl-border); box-shadow:var(--rl-shadow-sm); transition:all .3s cubic-bezier(.4,0,.2,1); overflow:hidden; position:relative; height:100%; }
    .rl-action-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg,var(--rl-primary) 0%, var(--rl-dark) 100%); opacity:0; transition:opacity .3s ease; }
    .rl-action-card:hover { transform:translateY(-4px); box-shadow:var(--rl-shadow-lg); border-color:var(--rl-accent); }
    .rl-action-card:hover::before { opacity:1; }
    .rl-card-body { padding:1.5rem; display:flex; flex-direction:column; text-align:center; min-height:220px; height:100%; }
    .rl-card-icon { font-size:2.25rem; margin-bottom:1rem; }
    .rl-card-title { font-size:1.125rem; font-weight:700; color:var(--rl-text); margin-bottom:.5rem; }
    .rl-card-text { font-size:.9rem; color:var(--rl-text-muted); line-height:1.6; margin-bottom:1.25rem; min-height:2.5rem; }

    /* Buttons */
    .rl-btn { border-radius:8px; font-weight:600; padding:.625rem 1.5rem; transition:all .2s ease; font-size:.9375rem; border:none; display:inline-flex; align-items:center; justify-content:center; gap:.5rem; width:100%; text-decoration:none; }
    .rl-btn-primary { background:linear-gradient(135deg,var(--rl-primary) 0%, var(--rl-accent) 100%); color:var(--rl-white); }
    .rl-btn-primary:hover { background:linear-gradient(135deg,#003a75 0%, #2d5a8f 100%); transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,78,152,.3); color:var(--rl-white); }
    .rl-btn-secondary { background:linear-gradient(135deg,#6b7280 0%, #4b5563 100%); color:var(--rl-white); }
    .rl-btn-secondary:hover { background:linear-gradient(135deg,#4b5563 0%, #374151 100%); transform:translateY(-1px); box-shadow:0 4px 12px rgba(107,114,128,.3); color:var(--rl-white); }

    /* Grid */
    .rl-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:1.5rem; align-items:stretch; }

    @media (max-width: 575px){ .rl-card-body{ padding:1.25rem; min-height:200px; } .rl-card-icon{ font-size:2rem; } }
  </style>
</head>

<body>
  <?php require_once ___DIR___ . '/../public/includes/navbar.php'; ?>

  <div class="container rl-container">
    <div class="rl-dashboard-header">
      <h1 class="rl-dashboard-title"><i class="bi bi-people"></i> Customer Dashboard</h1>
      <p class="rl-dashboard-subtitle">Manage your profile, rentals and wishlist</p>
    </div>

    <div class="rl-grid">
      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-person-circle rl-card-icon rl-icon-primary"></i>
          <h5 class="rl-card-title">Profile</h5>
          <p class="rl-card-text">Update your personal details, contact info, and password securely.</p>
          <a href="../public/includes/profile.php" class="rl-btn rl-btn-primary"><i class="bi bi-arrow-right-circle"></i> Open</a>
        </div>
      </div>

      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-house-door rl-card-icon rl-icon-success"></i>
          <h5 class="rl-card-title">My Rentals</h5>
          <p class="rl-card-text">View your booked properties, payments, and rental history.</p>
          <a href="../public/includes/my_rentals.php" class="rl-btn rl-btn-secondary"><i class="bi bi-arrow-right-circle"></i> Open</a>
        </div>
      </div>

      <div class="rl-action-card">
        <div class="rl-card-body">
          <i class="bi bi-heart rl-card-icon rl-icon-orange"></i>
          <h5 class="rl-card-title">Wishlist</h5>
          <p class="rl-card-text">Keep track of your favorite properties for future bookings.</p>
          <a href="../public/includes/wish_list.php" class="rl-btn rl-btn-primary"><i class="bi bi-arrow-right-circle"></i> Open</a>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
