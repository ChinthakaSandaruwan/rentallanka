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

          <?php if ($loggedIn && $isSuper): ?>
            <li class="nav-item"><a class="nav-link text-danger" href="<?= $base_url ?>/superAdmin/index.php"><i class="bi bi-shield-lock me-1"></i>Super Admin Dashboard</a></li>
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
              if ($isSuper) {
                $notifUrl = $base_url . '/superAdmin/notification.php';
              } else {
                $notifUrl = $base_url . '/customer/notification.php';
                if ($role === 'admin') { $notifUrl = $base_url . '/admin/notification.php'; }
                elseif ($role === 'owner') { $notifUrl = $base_url . '/owner/notification.php'; }
              }
            ?>
            <a href="<?= $notifUrl ?>" class="btn btn-outline-secondary position-relative btn-sm" title="Notifications">
              <i class="bi bi-bell"></i>
              <?php if ($notifCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                  <?= $notifCount ?>
                </span>
              <?php endif; ?>
            </a>

            <?php
              if ($isSuper) {
                $dashUrl = $base_url . '/superAdmin/index.php';
              } else {
                $dashUrl = $base_url . '/customer/index.php';
                if ($role === 'admin') { $dashUrl = $base_url . '/admin/index.php'; }
                elseif ($role === 'owner') { $dashUrl = $base_url . '/owner/index.php'; }
              }
            ?>

            <div class="dropdown">
              <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle me-1"></i> Account
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= $dashUrl ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= $base_url ?>/public/includes/as_an_advertiser.php"><i class="bi bi-briefcase me-1"></i>As an advertiser</a></li>
                <li><hr class="dropdown-divider"></li>
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
