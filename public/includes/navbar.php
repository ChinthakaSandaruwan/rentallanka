<?php
if ((function_exists('session_status') ? session_status() : PHP_SESSION_NONE) === PHP_SESSION_NONE) {
    session_start();
}

$isSuper = isset($_SESSION['super_admin_id']);
$loggedIn = (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) || $isSuper;
$role = $_SESSION['role'] ?? '';
require_once __DIR__ . '/../../config/config.php';
// Active link helper
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
?>
  <nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom shadow-sm sticky-top">
    <div class="container">
      <a class="navbar-brand fw-bold" href="<?= $base_url ?>/"><i class="bi bi-house-door-fill me-1"></i>Rentallanka</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
        data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
        aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <!-- Navigation Links -->
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link <?= ($reqPath==='/'||$reqPath==='/index.php')?'active':'' ?>" href="<?= $base_url ?>/index.php"><i class="bi bi-house-door me-1"></i>Home</a></li>
          <li class="nav-item"><a class="nav-link <?= ($reqPath==='/public/includes/all_properties.php')?'active':'' ?>" href="<?= $base_url ?>/public/includes/all_properties.php"><i class="bi bi-building me-1"></i>Properties</a></li>
          <li class="nav-item"><a class="nav-link <?= ($reqPath==='/public/includes/all_rooms.php')?'active':'' ?>" href="<?= $base_url ?>/public/includes/all_rooms.php"><i class="bi bi-door-open me-1"></i>Rooms</a></li>
          <li class="nav-item"><a class="nav-link <?= ($reqPath==='/about.php')?'active':'' ?>" href="<?= $base_url ?>/about.php"><i class="bi bi-info-circle me-1"></i>About</a></li>
          <li class="nav-item"><a class="nav-link <?= ($reqPath==='/contact.php')?'active':'' ?>" href="<?= $base_url ?>/contact.php"><i class="bi bi-envelope me-1"></i>Contact</a></li>

          <?php if ($loggedIn): ?>
            <?php if ($isSuper): ?>
              <li class="nav-item"><a class="nav-link text-danger" href="<?= $base_url ?>/superAdmin/index.php"><i class="bi bi-shield-lock me-1"></i>Super Admin Dashboard</a></li>
            <?php endif; ?>
            <?php if ($role === 'admin'): ?>
              <li class="nav-item"><a class="nav-link text-danger" href="<?= $base_url ?>/admin/index.php"><i class="bi bi-speedometer2 me-1"></i>Admin Dashboard</a></li>
            <?php elseif ($role === 'owner'): ?>
              <li class="nav-item"><a class="nav-link text-success" href="<?= $base_url ?>/owner/index.php"><i class="bi bi-briefcase me-1"></i>Owner Dashboard</a></li>
            <?php elseif ($role === 'customer'): ?>
              <li class="nav-item"><a class="nav-link text-primary" href="<?= $base_url ?>/customer/index.php"><i class="bi bi-person-badge me-1"></i>Customer Dashboard</a></li>
            <?php endif; ?>
            <!-- <li class="nav-item"><a class="nav-link" href="<?= $base_url ?>/public/includes/profile.php">Profile</a></li> -->
          <?php endif; ?>
        </ul>

        <?php
          $cartCount = 0;
          if ($loggedIn && ($role === 'customer')) {
            try {
              $cid = (int)($_SESSION['user']['user_id'] ?? 0);
              if ($cid > 0) {
                $c = db()->prepare('SELECT cart_id FROM carts WHERE customer_id=? AND status="active" LIMIT 1');
                $c->bind_param('i', $cid);
                $c->execute();
                $cres = $c->get_result()->fetch_assoc();
                $c->close();
                if ($cres) {
                  $cart_id = (int)$cres['cart_id'];
                  $q = db()->prepare('SELECT COUNT(*) AS cnt FROM cart_items WHERE cart_id=?');
                  $q->bind_param('i', $cart_id);
                  $q->execute();
                  $cnt = $q->get_result()->fetch_assoc();
                  $q->close();
                  $cartCount = (int)($cnt['cnt'] ?? 0);
                }
              }
            } catch (Throwable $e) { /* ignore */ }
          }
        ?>
        <?php
          // Notifications unread count (all roles)
          $notifCount = 0;
          try {
            $uid = (int)($_SESSION['user']['user_id'] ?? 0);
            if ($loggedIn && $uid > 0) {
              $qn = db()->prepare('SELECT COUNT(*) AS cnt FROM notifications WHERE user_id=? AND is_read=0');
              $qn->bind_param('i', $uid);
              $qn->execute();
              $nres = $qn->get_result()->fetch_assoc();
              $qn->close();
              $notifCount = (int)($nres['cnt'] ?? 0);
            }
          } catch (Throwable $e) { /* ignore */ }
        ?>
        <!-- Right side -->
        <div class="d-flex align-items-center gap-2">
          <!-- Theme Toggle -->
          <button id="themeToggle" class="btn btn-light btn-sm" title="Toggle theme">
            <i id="themeIcon" class="bi bi-moon"></i>
          </button>

          <?php if (!$loggedIn): ?>
            <a href="<?= $base_url ?>/auth/login.php" class="btn btn-primary btn-sm">Login</a>
          <?php else: ?>
            <?php if ($role === 'customer'): ?>
              <a href="<?= $base_url ?>/public/includes/cart.php" class="btn btn-outline-primary position-relative btn-sm" title="Cart">
                <i class="bi bi-cart"></i>
                <?php if ($cartCount > 0): ?>
                  <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?= $cartCount ?>
                  </span>
                <?php endif; ?>
              </a>
            <?php endif; ?>

            <?php
              // Role-specific notifications page URL
              $notifUrl = $base_url . '/customer/notification.php';
              if ($role === 'admin') { $notifUrl = $base_url . '/admin/notification.php'; }
              elseif ($role === 'owner') { $notifUrl = $base_url . '/owner/notification.php'; }
            ?>
            <a href="<?= $notifUrl ?>" class="btn btn-outline-secondary position-relative btn-sm" title="Notifications">
              <i class="bi bi-bell"></i>
              <?php if ($notifCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                  <?= $notifCount ?>
                </span>
              <?php endif; ?>
            </a>

            <div class="dropdown">
              <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle me-1"></i> Account
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <?php if ($isSuper): ?>
                  <li><a class="dropdown-item text-danger" href="<?= $base_url ?>/superAdmin/index.php"><i class="bi bi-shield-lock me-1"></i>Super Admin</a></li>
                  <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                <?php if ($role === 'admin'): ?>
                  <li><a class="dropdown-item" href="<?= $base_url ?>/admin/index.php"><i class="bi bi-speedometer2 me-1"></i>Admin Dashboard</a></li>
                <?php elseif ($role === 'owner'): ?>
                  <li><a class="dropdown-item" href="<?= $base_url ?>/owner/index.php"><i class="bi bi-briefcase me-1"></i>Owner Dashboard</a></li>
                <?php elseif ($role === 'customer'): ?>
                  <li><a class="dropdown-item" href="<?= $base_url ?>/customer/index.php"><i class="bi bi-person-badge me-1"></i>Customer Dashboard</a></li>
                <?php endif; ?>
                <li><a class="dropdown-item" href="<?= $base_url ?>/public/includes/profile.php"><i class="bi bi-person-lines-fill me-1"></i>Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= $base_url ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>
  <script>
    // Theme Toggle
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const htmlElement = document.documentElement;
    const savedTheme = localStorage.getItem('theme') || 'light';
    htmlElement.setAttribute('data-bs-theme', savedTheme);
    themeIcon.className = savedTheme === 'dark' ? 'bi bi-sun' : 'bi bi-moon';

    themeToggle.addEventListener('click', () => {
      const currentTheme = htmlElement.getAttribute('data-bs-theme');
      const newTheme = currentTheme === 'light' ? 'dark' : 'light';
      htmlElement.setAttribute('data-bs-theme', newTheme);
      localStorage.setItem('theme', newTheme);
      themeIcon.className = newTheme === 'dark' ? 'bi bi-sun' : 'bi bi-moon';
    });
  </script>

