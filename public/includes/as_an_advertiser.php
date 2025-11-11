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
require_once __DIR__ . '/../../config/config.php';

$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$user = $_SESSION['user'] ?? [];
$role = $_SESSION['role'] ?? '';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$err = '';
$ok = '';
$hasPending = false;

// Pre-check: show pending state on initial load
try {
  if ($loggedIn && $role === 'customer') {
    $uid = (int)($user['user_id'] ?? 0);
    if ($uid > 0) {
      $stc0 = db()->prepare("SELECT request_id FROM advertiser_requests WHERE user_id = ? AND status = 'pending' LIMIT 1");
      $stc0->bind_param('i', $uid);
      $stc0->execute();
      $has0 = $stc0->get_result()->fetch_assoc();
      $stc0->close();
      if ($has0) { $hasPending = true; }
    }
  }
} catch (Throwable $e) { /* ignore */ }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    $err = 'Invalid request.';
  } else {
    if (!$loggedIn) {
      $err = 'Please login to continue.';
    } elseif ($role !== 'customer') {
      if ($role === 'owner') {
        $err = 'Your account is already an Owner.';
      } else {
        $err = 'Only Customer accounts can request an upgrade.';
      }
    } else {
      try {
        $uid = (int)($user['user_id'] ?? 0);
        $name = (string)($user['name'] ?? ''); // may be empty depending on session structure
        // Fetch latest name/email if needed
        $stmt = db()->prepare('SELECT name, email, phone FROM users WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc() ?: [];
        $stmt->close();
        $name = $row['name'] ?? $name;
        $email = $row['email'] ?? '';
        $phone = $row['phone'] ?? ($user['phone'] ?? '');

        // Prevent duplicate pending requests
        $stc = db()->prepare("SELECT request_id FROM advertiser_requests WHERE user_id = ? AND status = 'pending' LIMIT 1");
        $stc->bind_param('i', $uid);
        $stc->execute();
        $has = $stc->get_result()->fetch_assoc();
        $stc->close();
        if ($has) {
          $ok = 'You already have a pending request. Our admin team will review it soon.';
        } else {
          $sti = db()->prepare("INSERT INTO advertiser_requests (user_id, status) VALUES (?, 'pending')");
          $sti->bind_param('i', $uid);
          if ($sti->execute()) {
            $ok = 'Your request has been submitted for admin approval.';
            // Notify first active admin about the new request
            try {
              $arid = (int)$sti->insert_id;
              $resAdm = db()->query("SELECT user_id FROM users WHERE role='admin' AND status='active' ORDER BY user_id ASC LIMIT 1");
              $adminId = 0;
              if ($resAdm && ($rowAdm = $resAdm->fetch_assoc())) { $adminId = (int)$rowAdm['user_id']; }
              if ($adminId > 0) {
                $titleN = 'New Advertiser Request';
                $msgN = 'Customer #' . (int)$uid . ' requested to become an advertiser (Request #' . $arid . ').';
                $typeN = 'system';
                $ns = db()->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)');
                $ns->bind_param('isss', $adminId, $titleN, $msgN, $typeN);
                $ns->execute();
                $ns->close();
              }
            } catch (Throwable $e2) { @error_log('[as_an_advertiser] admin notify failed: ' . $e2->getMessage()); }
          } else {
            $err = 'Could not submit request. Please try again.';
            @error_log('[as_an_advertiser] insert failed: ' . (string)db()->error);
          }
          $sti->close();
        }
      } catch (Throwable $e) {
        $err = 'Could not submit request. Please try again later.';
        @error_log('[as_an_advertiser] exception: ' . $e->getMessage());
      }
    }
  }
}

// POST-Redirect-GET: redirect with flash so SweetAlert2 shows message via navbar
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $msg = $ok ?: ($err ?: 'Action completed.');
  $typ = $ok ? 'success' : 'error';
  redirect_with_message(rtrim($base_url,'/') . '/public/includes/as_an_advertiser.php', $msg, $typ);
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Rentallanka – Properties & Rooms for Rent in Sri Lanka</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <!-- Modern Typography -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
    /* =========================================
       RentalLanka Advertiser Page - Custom Styles
       Modern, responsive design with brand colors
       ========================================= */

    :root {
      /* Brand Colors */
      --rl-primary: #004E98;       /* Primary Blue */
      --rl-light-bg: #EBEBEB;      /* Light Background */
      --rl-secondary: #C0C0C0;     /* Secondary Gray */
      --rl-accent: #3A6EA5;        /* Accent Blue */
      --rl-dark: #FF6700;          /* Dark Orange/CTA */
      
      /* Extended Palette */
      --rl-white: #ffffff;
      --rl-text-primary: #1a202c;
      --rl-text-secondary: #4a5568;
      --rl-text-muted: #718096;
      --rl-border: #e2e8f0;
      --rl-success: #10b981;
      --rl-warning: #f59e0b;
      --rl-danger: #ef4444;
      
      /* Shadows */
      --rl-shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
      --rl-shadow-md: 0 4px 16px rgba(0, 0, 0, 0.10);
      --rl-shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.15);
      --rl-shadow-xl: 0 20px 60px rgba(0, 0, 0, 0.20);
      
      /* Border Radius */
      --rl-radius-sm: 6px;
      --rl-radius-md: 10px;
      --rl-radius-lg: 16px;
      --rl-radius-xl: 24px;
      
      /* Transitions */
      --rl-transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Base Typography & Layout */
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: linear-gradient(135deg, var(--rl-light-bg) 0%, #f8f9fa 100%);
      color: var(--rl-text-primary);
      line-height: 1.6;
      min-height: 100vh;
    }

    h1, h2, h3, h4, h5, h6 {
      font-weight: 700;
      letter-spacing: -0.02em;
      color: var(--rl-text-primary);
    }

    /* Page Header Enhancement */
    .rl-page-header {
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
      padding: 2.5rem 0;
      margin-bottom: 2rem;
      box-shadow: var(--rl-shadow-md);
      position: relative;
      overflow: hidden;
    }

    .rl-page-header::before {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
      border-radius: 50%;
      transform: translate(30%, -30%);
    }

    .rl-page-header h1 {
      color: var(--rl-white);
      font-size: clamp(1.75rem, 4vw, 2.5rem);
      margin: 0;
      position: relative;
      z-index: 1;
    }

    .rl-page-header .rl-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 56px;
      height: 56px;
      background: rgba(255, 255, 255, 0.15);
      border-radius: var(--rl-radius-lg);
      backdrop-filter: blur(10px);
      margin-right: 1rem;
      font-size: 1.75rem;
    }

    .rl-page-header .rl-subtitle {
      color: rgba(255, 255, 255, 0.9);
      font-size: 1.1rem;
      margin-top: 0.75rem;
      font-weight: 400;
    }

    /* Enhanced Card Styling */
    .rl-card {
      background: var(--rl-white);
      border-radius: var(--rl-radius-xl);
      border: 1px solid var(--rl-border);
      box-shadow: var(--rl-shadow-lg);
      overflow: hidden;
      transition: var(--rl-transition);
    }

    .rl-card:hover {
      box-shadow: var(--rl-shadow-xl);
      transform: translateY(-2px);
    }

    .rl-card-header {
      background: linear-gradient(135deg, #f8fafc 0%, var(--rl-white) 100%);
      padding: 1.5rem;
      border-bottom: 1px solid var(--rl-border);
    }

    .rl-card-body {
      padding: 2rem;
    }

    /* Feature List Styling */
    .rl-feature-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .rl-feature-list li {
      display: flex;
      align-items: flex-start;
      padding: 0.75rem 0;
      color: var(--rl-text-secondary);
      font-size: 0.95rem;
    }

    .rl-feature-list li::before {
      content: '\F26A';
      font-family: 'bootstrap-icons';
      color: var(--rl-accent);
      margin-right: 0.75rem;
      font-size: 1.1rem;
      flex-shrink: 0;
    }

    /* Status Badge */
    .rl-status-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.75rem 1.25rem;
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      border: 1px solid #fbbf24;
      border-radius: var(--rl-radius-lg);
      color: #92400e;
      font-weight: 600;
      font-size: 0.95rem;
      box-shadow: var(--rl-shadow-sm);
    }

    .rl-status-badge i {
      font-size: 1.25rem;
      animation: spin 2s linear infinite;
    }

    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    /* Button Enhancements */
    .rl-btn {
      font-weight: 600;
      padding: 0.75rem 1.5rem;
      border-radius: var(--rl-radius-md);
      transition: var(--rl-transition);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      border: none;
      cursor: pointer;
      text-decoration: none;
    }

    .rl-btn-primary {
      background: linear-gradient(135deg, var(--rl-primary) 0%, var(--rl-accent) 100%);
      color: var(--rl-white);
      box-shadow: 0 4px 12px rgba(0, 78, 152, 0.25);
    }

    .rl-btn-primary:hover:not(:disabled) {
      background: linear-gradient(135deg, #003a75 0%, #2d5a8f 100%);
      box-shadow: 0 6px 20px rgba(0, 78, 152, 0.35);
      transform: translateY(-2px);
      color: var(--rl-white);
    }

    .rl-btn-primary:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      box-shadow: none;
    }

    .rl-btn-outline {
      background: var(--rl-white);
      color: var(--rl-text-secondary);
      border: 1px solid var(--rl-border);
      box-shadow: var(--rl-shadow-sm);
    }

    .rl-btn-outline:hover {
      background: var(--rl-light-bg);
      border-color: var(--rl-primary);
      color: var(--rl-primary);
      box-shadow: var(--rl-shadow-md);
    }

    .rl-btn-cta {
      background: linear-gradient(135deg, var(--rl-dark) 0%, #ff8534 100%);
      color: var(--rl-white);
      font-size: 1.1rem;
      padding: 1rem 2rem;
      box-shadow: 0 4px 16px rgba(255, 103, 0, 0.3);
    }

    .rl-btn-cta:hover:not(:disabled) {
      background: linear-gradient(135deg, #e65d00 0%, #ff7621 100%);
      box-shadow: 0 6px 24px rgba(255, 103, 0, 0.4);
      transform: translateY(-2px);
      color: var(--rl-white);
    }

    /* Form Enhancements */
    .rl-form-label {
      font-weight: 600;
      color: var(--rl-text-primary);
      margin-bottom: 0.5rem;
      font-size: 0.95rem;
      display: block;
    }

    .rl-form-control {
      border: 2px solid var(--rl-border);
      border-radius: var(--rl-radius-md);
      padding: 0.875rem 1rem;
      font-size: 1rem;
      transition: var(--rl-transition);
      background: var(--rl-white);
      width: 100%;
    }

    .rl-form-control:focus {
      outline: none;
      border-color: var(--rl-primary);
      box-shadow: 0 0 0 3px rgba(0, 78, 152, 0.1);
    }

    .rl-form-control:disabled {
      background: var(--rl-light-bg);
      color: var(--rl-text-muted);
      cursor: not-allowed;
    }

    /* Info Alert Box */
    .rl-info-box {
      background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
      border: 1px solid #93c5fd;
      border-left: 4px solid var(--rl-primary);
      border-radius: var(--rl-radius-md);
      padding: 1.25rem;
      margin-bottom: 1.5rem;
      color: #1e3a8a;
    }

    .rl-info-box p {
      margin: 0;
      line-height: 1.6;
    }

    /* Success Box */
    .rl-success-box {
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      border: 1px solid #6ee7b7;
      border-left: 4px solid var(--rl-success);
      border-radius: var(--rl-radius-md);
      padding: 1.25rem;
      margin-bottom: 1.5rem;
      color: #065f46;
    }

    /* Already Owner Status */
    .rl-owner-status {
      text-align: center;
      padding: 3rem 2rem;
    }

    .rl-owner-status i {
      font-size: 4rem;
      color: var(--rl-success);
      margin-bottom: 1rem;
      display: block;
    }

    .rl-owner-status h3 {
      color: var(--rl-text-primary);
      margin-bottom: 0.5rem;
    }

    .rl-owner-status p {
      color: var(--rl-text-muted);
      margin: 0;
    }

    /* Login Prompt */
    .rl-login-prompt {
      text-align: center;
      padding: 3rem 2rem;
    }

    .rl-login-prompt i {
      font-size: 3.5rem;
      color: var(--rl-accent);
      margin-bottom: 1rem;
      display: block;
    }

    .rl-login-prompt a {
      color: var(--rl-primary);
      text-decoration: none;
      font-weight: 600;
    }

    .rl-login-prompt a:hover {
      color: var(--rl-accent);
      text-decoration: underline;
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
      .rl-page-header {
        padding: 2rem 0;
      }

      .rl-page-header h1 {
        font-size: 1.75rem;
      }

      .rl-page-header .rl-icon {
        width: 48px;
        height: 48px;
        font-size: 1.5rem;
      }

      .rl-card-body {
        padding: 1.5rem;
      }

      .rl-btn {
        padding: 0.65rem 1.25rem;
        font-size: 0.95rem;
      }

      .rl-btn-cta {
        padding: 0.875rem 1.5rem;
        font-size: 1rem;
      }
    }

    @media (max-width: 576px) {
      .rl-page-header {
        padding: 1.5rem 0;
      }

      .rl-page-header h1 {
        font-size: 1.5rem;
      }

      .rl-card-body {
        padding: 1.25rem;
      }

      .rl-feature-list li {
        font-size: 0.9rem;
      }
    }

    /* Loading Animation */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .rl-card {
      animation: fadeInUp 0.6s ease-out;
    }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/navbar.php'; ?>
  
  <!-- Page Header -->
  <div class="rl-page-header">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
            <div class="d-flex align-items-center mb-3 mb-md-0">
              <div class="rl-icon">
                <i class="bi bi-briefcase"></i>
              </div>
              <div>
                <h1>Become an Advertiser</h1>
                <div class="rl-subtitle">Upgrade your account and start listing properties</div>
              </div>
            </div>
            <a href="<?= $base_url ?>/" class="rl-btn rl-btn-outline">
              <i class="bi bi-house"></i>
              <span>Home</span>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container py-4 py-md-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-10 col-lg-8">
        
        <?php if (!$loggedIn): ?>
          <!-- Login Required -->
          <div class="rl-card">
            <div class="rl-card-body">
              <div class="rl-login-prompt">
                <i class="bi bi-shield-lock"></i>
                <h3>Login Required</h3>
                <p class="mb-3">Please <a href="<?= $base_url ?>/auth/login.php">log in</a> to request an advertiser upgrade.</p>
                <a href="<?= $base_url ?>/auth/login.php" class="rl-btn rl-btn-primary">
                  <i class="bi bi-box-arrow-in-right"></i>
                  <span>Login Now</span>
                </a>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($loggedIn && $role === 'customer'): ?>
          <!-- Customer - Upgrade Form -->
          <div class="rl-card">
            <div class="rl-card-body">
              
              <!-- Info Box -->
              <div class="rl-info-box">
                <p><strong>Ready to grow your business?</strong><br>
                Upgrade your Customer account to an Owner account to start posting properties and rooms as an advertiser. Your request will be reviewed by an Admin for quality assurance.</p>
              </div>

              <!-- Benefits List -->
              <h3 class="h5 mb-3" style="color: var(--rl-text-primary);">What you'll get:</h3>
              <ul class="rl-feature-list mb-4">
                <li>Post unlimited properties and rooms</li>
                <li>Manage your listings with a dedicated dashboard</li>
                <li>Connect with potential customers directly</li>
                <li>Get verified advertiser badge</li>
                <li>Admin approval ensures quality standards</li>
                <li>Verification process protects all users</li>
              </ul>

              <?php if ($hasPending): ?>
                <!-- Pending Status -->
                <div class="rl-status-badge mb-4">
                  <i class="bi bi-hourglass-split"></i>
                  <span>Your request is pending admin review. We'll notify you once approved!</span>
                </div>
              <?php endif; ?>

              <!-- Upgrade Form -->
              <form method="post" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
                
                <div class="mb-4">
                  <label class="rl-form-label">Confirm your intention to upgrade</label>
                  <input type="text" class="rl-form-control" value="I want to upgrade to Owner" disabled />
                </div>

                <div class="d-grid">
                  <button type="submit" class="rl-btn rl-btn-cta w-100" <?= $hasPending ? 'disabled' : '' ?>>
                    <i class="bi bi-send"></i>
                    <span><?= $hasPending ? 'Request Already Sent' : 'Send Upgrade Request' ?></span>
                  </button>
                </div>
              </form>

              <?php if (!$hasPending): ?>
                <div class="text-center mt-3">
                  <small class="text-muted">By submitting, you agree to our terms and verification process</small>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($loggedIn && $role === 'owner'): ?>
          <!-- Already Owner -->
          <div class="rl-card">
            <div class="rl-card-body">
              <div class="rl-owner-status">
                <i class="bi bi-patch-check-fill"></i>
                <h3>You're Already an Owner!</h3>
                <p class="mb-4">Your account has advertiser privileges. You can post properties and rooms anytime.</p>
                <a href="<?= $base_url ?>/" class="rl-btn rl-btn-primary">
                  <i class="bi bi-speedometer2"></i>
                  <span>Go to Dashboard</span>
                </a>
              </div>
            </div>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    (() => {
      'use strict';
      const forms = document.querySelectorAll('.needs-validation');
      Array.from(forms).forEach(form => {
        form.addEventListener('submit', async (e) => {
          if (!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); form.classList.add('was-validated'); return; }
          e.preventDefault();
          // SweetAlert2 confirmation before sending request
          try {
            const res = await Swal.fire({
              title: 'Send upgrade request?',
              text: 'We will notify an admin to review your request.',
              icon: 'question',
              showCancelButton: true,
              confirmButtonText: 'Yes, send',
              cancelButtonText: 'Cancel'
            });
            if (res.isConfirmed) { form.submit(); }
          } catch(_) { form.submit(); }
        });
      });
    })();
  </script>
</body>
</html>
