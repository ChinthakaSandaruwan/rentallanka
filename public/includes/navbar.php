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
if ((function_exists('session_status') ? session_status() : PHP_SESSION_NONE) === PHP_SESSION_NONE) {
    session_start();
}

$isSuper = isset($_SESSION['super_admin_id']);
$loggedIn = (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) || $isSuper;
$role = $_SESSION['role'] ?? '';
$who = "I'm a Visitor";
if ($loggedIn) {
    if ($isSuper) { $who = "I'm a Super Admin"; }
    elseif ($role === 'admin') { $who = "I'm an Admin"; }
    elseif ($role === 'owner') { $who = "I'm an Owner"; }
    elseif ($role === 'customer') { $who = "I'm a Customer"; }
}
require_once __DIR__ . '/../../config/config.php';
// Wishlist count for logged-in regular users
$wlCount = 0;
if ($loggedIn && !$isSuper) {
    $uid = (int)($_SESSION['user']['user_id'] ?? 0);
    if ($uid > 0) {
        $st = db()->prepare('SELECT COUNT(*) AS c FROM wishlist WHERE customer_id = ?');
        $st->bind_param('i', $uid);
        $st->execute();
        $rs = $st->get_result();
        $row = $rs ? $rs->fetch_assoc() : ['c' => 0];
        $wlCount = (int)($row['c'] ?? 0);
        $st->close();
    }
}
// Active link helper
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
?>
  <!-- Custom Navbar Styles -->
  <style>
    /* ===========================
       NAVBAR CUSTOM STYLES
       Brand Colors: Primary #004E98, Accent #3A6EA5, Orange #FF6700
       =========================== */
    
    /* Navbar Container */
    .rl-navbar {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%) !important;
      border-bottom: 2px solid #e2e8f0 !important;
      box-shadow: 0 4px 16px rgba(0, 78, 152, 0.08) !important;
      backdrop-filter: blur(10px);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      padding: 0.75rem 0;
    }
    
    /* Navbar scrolled state */
    .rl-navbar.scrolled {
      box-shadow: 0 6px 20px rgba(0, 78, 152, 0.12) !important;
      padding: 0.5rem 0;
    }
    
    /* Brand Logo */
    .rl-navbar .navbar-brand {
      font-size: 1.5rem;
      font-weight: 800;
      background: linear-gradient(135deg, #004E98 0%, #3A6EA5 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      transition: all 0.3s ease;
      letter-spacing: -0.02em;
      display: flex;
      align-items: center;
      padding: 0.5rem 0;
    }
    
    .rl-navbar .navbar-brand i {
      background: linear-gradient(135deg, #FF6700 0%, #ff8534 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      font-size: 1.6rem;
      margin-right: 0.5rem;
    }
    
    .rl-navbar .navbar-brand:hover {
      transform: translateY(-2px);
      filter: brightness(1.1);
    }
    
    /* Navigation Links */
    .rl-navbar .nav-link {
      font-weight: 600;
      color: #4a5568;
      padding: 0.625rem 1rem;
      margin: 0 0.25rem;
      border-radius: 10px;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      display: flex;
      align-items: center;
    }
    
    .rl-navbar .nav-link i {
      font-size: 1.1rem;
      transition: transform 0.2s ease;
    }
    
    .rl-navbar .nav-link:hover {
      color: #004E98;
      background: rgba(0, 78, 152, 0.08);
      transform: translateY(-1px);
    }
    
    .rl-navbar .nav-link:hover i {
      transform: scale(1.1);
    }
    
    /* Active Navigation Link */
    .rl-navbar .nav-link.active {
      color: #004E98;
      background: linear-gradient(135deg, rgba(0, 78, 152, 0.1) 0%, rgba(58, 110, 165, 0.1) 100%);
      font-weight: 700;
      position: relative;
    }
    
    .rl-navbar .nav-link.active::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 60%;
      height: 3px;
      background: linear-gradient(90deg, #FF6700 0%, #ff8534 100%);
      border-radius: 2px;
    }
    
    /* Disabled/Role Badge */
    .rl-navbar .nav-link.disabled {
      background: linear-gradient(135deg, #EBEBEB 0%, #f1f1f1 100%);
      color: #718096;
      border: 1px solid #e2e8f0;
      font-size: 0.875rem;
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-weight: 600;
      cursor: default;
    }
    
    /* Navbar Toggler */
    .rl-navbar .navbar-toggler {
      border: 2px solid #004E98;
      border-radius: 10px;
      padding: 0.5rem 0.75rem;
      transition: all 0.2s ease;
    }
    
    .rl-navbar .navbar-toggler:hover {
      background: rgba(0, 78, 152, 0.08);
      transform: scale(1.05);
    }
    
    .rl-navbar .navbar-toggler:focus {
      box-shadow: 0 0 0 3px rgba(0, 78, 152, 0.2);
      outline: none;
    }
    
    .rl-navbar .navbar-toggler-icon {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='%23004E98' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2.5' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
    }
    
    /* Buttons */
    .rl-navbar .btn-primary {
      background: linear-gradient(135deg, #004E98 0%, #3A6EA5 100%);
      border: none;
      font-weight: 700;
      padding: 0.625rem 1.5rem;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0, 78, 152, 0.25);
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      color: #ffffff;
    }
    
    .rl-navbar .btn-primary:hover {
      background: linear-gradient(135deg, #003a75 0%, #2d5a8f 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 78, 152, 0.35);
      color: #ffffff;
    }
    
    .rl-navbar .btn-primary:active {
      transform: translateY(0);
    }
    
    .rl-navbar .btn-outline-primary {
      border: 2px solid #004E98;
      color: #004E98;
      font-weight: 600;
      padding: 0.5rem 0.875rem;
      border-radius: 10px;
      background: transparent;
      transition: all 0.2s ease;
    }
    
    .rl-navbar .btn-outline-primary:hover {
      background: #004E98;
      color: #ffffff;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 78, 152, 0.2);
    }
    
    .rl-navbar .btn-outline-secondary {
      border: 2px solid #e2e8f0;
      color: #4a5568;
      font-weight: 600;
      padding: 0.5rem 0.875rem;
      border-radius: 10px;
      background: #ffffff;
      transition: all 0.2s ease;
    }
    
    .rl-navbar .btn-outline-secondary:hover {
      border-color: #3A6EA5;
      color: #3A6EA5;
      background: rgba(58, 110, 165, 0.05);
      transform: translateY(-1px);
    }
    
    /* Badge on Buttons */
    .rl-navbar .badge {
      font-size: 0.7rem;
      padding: 0.25rem 0.5rem;
      font-weight: 700;
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      border: 2px solid #ffffff;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }
    
    /* Dropdown Menu */
    .rl-navbar .dropdown-menu {
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      box-shadow: 0 10px 40px rgba(0, 78, 152, 0.15);
      padding: 0.75rem;
      margin-top: 0.75rem;
      min-width: 220px;
      animation: dropdownFadeIn 0.3s ease;
    }
    
    @keyframes dropdownFadeIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .rl-navbar .dropdown-item {
      border-radius: 10px;
      padding: 0.75rem 1rem;
      transition: all 0.2s ease;
      font-weight: 500;
      color: #4a5568;
      display: flex;
      align-items: center;
    }
    
    .rl-navbar .dropdown-item i {
      width: 20px;
      transition: transform 0.2s ease;
    }
    
    .rl-navbar .dropdown-item:hover {
      background: rgba(0, 78, 152, 0.08);
      color: #004E98;
      transform: translateX(4px);
    }
    
    .rl-navbar .dropdown-item:hover i {
      transform: scale(1.1);
    }
    
    .rl-navbar .dropdown-item.text-danger {
      color: #ef4444;
    }
    
    .rl-navbar .dropdown-item.text-danger:hover {
      background: rgba(239, 68, 68, 0.08);
      color: #dc2626;
    }
    
    .rl-navbar .dropdown-divider {
      border-color: #e2e8f0;
      margin: 0.5rem 0;
    }
    
    /* Right Side Actions */
    .rl-navbar-actions {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 991px) {
      .rl-navbar {
        padding: 0.5rem 0;
      }
      
      .rl-navbar .navbar-brand {
        font-size: 1.25rem;
      }
      
      .rl-navbar .navbar-brand i {
        font-size: 1.4rem;
      }
      
      .rl-navbar .navbar-nav {
        padding: 1rem 0;
      }
      
      .rl-navbar .nav-link {
        margin: 0.25rem 0;
        padding: 0.75rem 1rem;
      }
      
      .rl-navbar .nav-link.active::after {
        bottom: auto;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 60%;
      }
      
      .rl-navbar .nav-link.disabled {
        margin: 0.5rem 0;
      }
      
      .rl-navbar-actions {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 2px solid #e2e8f0;
        flex-wrap: wrap;
        gap: 0.75rem;
      }
      
      .rl-navbar .btn-primary {
        flex: 1;
        min-width: 120px;
      }
    }
    
    @media (max-width: 575px) {
      .rl-navbar .navbar-brand {
        font-size: 1.125rem;
      }
      
      .rl-navbar .navbar-brand i {
        font-size: 1.25rem;
        margin-right: 0.375rem;
      }
      
      .rl-navbar-actions {
        width: 100%;
      }
      
      .rl-navbar-actions > * {
        flex: 1;
        min-width: 0;
      }
      
      .rl-navbar .btn {
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
      }
      
      .rl-navbar .dropdown-menu {
        min-width: calc(100vw - 3rem);
      }
    }
    
    /* Focus States for Accessibility */
    .rl-navbar .nav-link:focus,
    .rl-navbar .btn:focus,
    .rl-navbar .dropdown-item:focus {
      outline: 3px solid rgba(255, 103, 0, 0.4);
      outline-offset: 2px;
    }
    
    /* Smooth scrolling effect */
    .rl-navbar.sticky-top {
      transition: all 0.3s ease;
    }
  </style>

  <nav class="navbar navbar-expand-lg rl-navbar sticky-top">
    <div class="container">
      <a class="navbar-brand" href="<?= $base_url ?>/"><i class="bi bi-house-door-fill"></i>Rentallanka</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
        data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
        aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <!-- Navigation Links -->
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link <?= ($reqPath==='/'||$reqPath==='/index.php')?'active':'' ?>" href="<?= $base_url ?>/index.php"><i class="bi bi-house-door"></i>Home</a></li>
          <li class="nav-item"><a class="nav-link <?= ($reqPath==='/public/includes/all_properties.php')?'active':'' ?>" href="<?= $base_url ?>/public/includes/all_properties.php"><i class="bi bi-building"></i>Properties</a></li>
          <li class="nav-item"><a class="nav-link <?= ($reqPath==='/public/includes/all_rooms.php')?'active':'' ?>" href="<?= $base_url ?>/public/includes/all_rooms.php"><i class="bi bi-door-open"></i>Rooms</a></li>
          <?php if ($loggedIn && !$isSuper && in_array($role, ['customer','owner','admin'], true)): ?>
            <li class="nav-item"><a class="nav-link <?= ($reqPath==='/public/includes/my_rentals.php')?'active':'' ?>" href="<?= $base_url ?>/public/includes/my_rentals.php"><i class="bi bi-receipt"></i>My Rentals</a></li>
          <?php endif; ?>
          <li class="nav-item">
            <span class="nav-link disabled" tabindex="-1" aria-disabled="true"><?= htmlspecialchars($who) ?></span>
          </li>

        </ul>

        
        <!-- Right side -->
        <div class="d-flex align-items-center gap-2 rl-navbar-actions">

          <?php if (!$loggedIn): ?>
            <a href="<?= $base_url ?>/auth/login.php" class="btn btn-primary btn-sm">Login</a>
          <?php else: ?>
                        <?php if (!$isSuper): ?>
              <a href="<?= $base_url ?>/public/includes/wish_list.php" class="btn btn-outline-primary btn-sm position-relative" title="Wishlist">
                <i class="bi bi-heart"></i>
                <?php if ($wlCount > 0): ?>
                  <span class="position-absolute top-0 end-0 translate-middle-y badge rounded-pill"><?= (int)$wlCount ?></span>
                <?php endif; ?>
              </a>
            <?php endif; ?>

            <?php if (in_array($role, ['owner','admin','customer'], true)): ?>
              <button type="button" class="btn btn-outline-secondary btn-sm position-relative" id="nl-bell" data-bs-toggle="modal" data-bs-target="#nlModal" title="Notifications">
                <i class="bi bi-bell"></i>
                <span class="position-absolute top-0 end-0 translate-middle-y badge rounded-pill d-none" id="nl-badge">0</span>
              </button>
            <?php endif; ?>

            <?php
              if ($isSuper) {
                $dashUrl = $base_url . '/superAdmin/index.php';
              } else {
                $dashUrl = $base_url . '/customer/index.php';
                if ($role === 'admin') { $dashUrl = $base_url . '/admin/index.php'; }
                elseif ($role === 'owner') { $dashUrl = $base_url . '/owner/index.php'; }
              }
            ?>

            <?php if (in_array($role, ['owner','admin'], true)): ?>
            <!-- Quick Create Dropdown (Property / Room) - appears to the left of Account -->
            <div class="dropdown">
              <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" id="createDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-plus-square"></i> Create
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="createDropdownBtn">
                <li><a class="dropdown-item" href="<?= $base_url ?>/owner/property/property_create.php"><i class="bi bi-building-add me-2"></i>Property Create</a></li>
                <li><a class="dropdown-item" href="<?= $base_url ?>/owner/room/room_create.php"><i class="bi bi-door-open me-2"></i>Room Create</a></li>
              </ul>
            </div>
            <?php endif; ?>

            <div class="dropdown">
              <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="accountDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i> Account
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="accountDropdownBtn">
                <li><a class="dropdown-item" href="<?= $dashUrl ?>"><i class="bi bi-speedometer2"></i>Dashboard</a></li>
                <?php if ($role === 'customer'): ?>
                <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item" href="<?= $base_url ?>/public/includes/as_an_advertiser.php"><i class="bi bi-briefcase"></i>As an advertiser</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= $base_url ?>/public/includes/profile.php"><i class="bi bi-person-lines-fill"></i>Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= $base_url ?>/auth/logout.php"><i class="bi bi-box-arrow-right"></i>Logout</a></li>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <!-- Navbar Scroll Effect Script -->
  <script>
    (function() {
      const navbar = document.querySelector('.rl-navbar');
      if (!navbar) return;
      
      let lastScroll = 0;
      window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset;
        if (currentScroll > 50) {
          navbar.classList.add('scrolled');
        } else {
          navbar.classList.remove('scrolled');
        }
        lastScroll = currentScroll;
      });
    })();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    (function(){
      try {
        const params = new URLSearchParams(window.location.search);
        const msg = params.get('flash');
        const type = (params.get('type') || 'info').toLowerCase();
        const icon = ({ success:'success', error:'error', danger:'error', warning:'warning', info:'info' })[type] || 'info';
        if (msg) {
          Swal.fire({ icon, title: (icon==='success'?'Success':icon==='error'?'Error':icon==='warning'?'Warning':'Info'), text: msg, confirmButtonText: 'OK' });
          params.delete('flash'); params.delete('type');
          const qs = params.toString();
          const newUrl = window.location.pathname + (qs?('?'+qs):'') + window.location.hash;
          window.history.replaceState({}, document.title, newUrl);
        }
      } catch(_) {}
    })();
  </script>
  
  <?php if ($loggedIn && in_array($role, ['owner','admin','customer'], true)): ?>
  <div class="modal fade" id="nlModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Notifications</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-0">
          <div id="nl-list" class="list-group list-group-flush"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  <script>
    (function(){
      const role = <?= json_encode($role) ?>;
      const baseUrl = <?= json_encode($base_url) ?>;
      const currentUserId = <?= json_encode((int)($_SESSION['user']['user_id'] ?? 0)) ?>;
      const api = (role === 'admin')
        ? (baseUrl + '/notifications/between_admin_and_owner.php')
        : (baseUrl + '/notifications/between_customer_and_owner.php');
      const badge = document.getElementById('nl-badge');
      const listEl = document.getElementById('nl-list');
      if (!badge || !listEl) return;
      let csrf = '';

      function rjson(r){
        return r.text().then(t => {
          let j = {};
          try { j = t ? JSON.parse(t) : {}; } catch (e) { j = {}; }
          return j;
        }).catch(() => ({}));
      }

      function fetchCsrf(){
        return fetch(api + '?action=csrf').then(rjson).then(j=>{
          const token = j && j.data && j.data.csrf_token ? j.data.csrf_token : '';
          csrf = token;
        }).catch(()=>{ csrf = ''; });
      }

      function render(items){
        listEl.innerHTML = '';
        if (!items || items.length === 0) {
          listEl.innerHTML = '<div class="list-group-item text-center text-muted small">No notifications</div>';
          badge.classList.add('d-none');
          badge.textContent = '0';
          return;
        }
        let unread = 0;
        items.forEach(it => {
          if (String(it.is_read) === '0' || it.is_read === 0) unread++;
          const readBtn = (String(it.is_read) === '0' || it.is_read === 0)
            ? '<button class="btn btn-sm btn-outline-primary nl-mark" data-id="'+it.notification_id+'">Mark read</button>'
            : '<span class="badge bg-secondary me-2">Read</span>';
          const delBtn = '<button class="btn btn-sm btn-outline-danger nl-del ms-2" data-id="'+it.notification_id+'">Delete</button>';
          const row = document.createElement('div');
          row.className = 'list-group-item';
          row.innerHTML = '<div class="d-flex justify-content-between align-items-start">'
            + '<div class="me-2"><div class="fw-semibold">'+ (it.title || 'Notification') +'</div>'
            + '<div class="small text-muted">'+ (it.created_at || '') +'</div>'
            + '<div class="small">'+ (it.message || '') +'</div></div>'
            + '<div class="d-flex align-items-center">'+ readBtn + delBtn +'</div>'
            + '</div>';
          listEl.appendChild(row);
        });
        if (unread > 0) {
          badge.classList.remove('d-none');
          badge.textContent = String(unread);
        } else {
          badge.classList.add('d-none');
          badge.textContent = '0';
        }
        listEl.querySelectorAll('.nl-mark').forEach(b => {
          b.addEventListener('click', function(){
            const id = this.getAttribute('data-id');
            if (!id || !csrf) return;
            const body = new URLSearchParams({ action: 'mark_read', notification_id: id, csrf_token: csrf });
            fetch(api, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body })
              .then(rjson).then(()=> load())
              .catch(()=>{});
          });
        });
        listEl.querySelectorAll('.nl-del').forEach(b => {
          b.addEventListener('click', function(){
            const id = this.getAttribute('data-id');
            if (!id || !csrf) return;
            const body = new URLSearchParams({ action: 'delete', notification_id: id, csrf_token: csrf });
            fetch(api, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body })
              .then(rjson).then(()=> load())
              .catch(()=>{});
          });
        });
      }

      function load(){
        const params = new URLSearchParams({ action: 'list', unread_only: '0', limit: '20' });
        return fetch(api + '?' + params.toString()).then(rjson).then(j => {
          const items = (j && j.data && Array.isArray(j.data.items)) ? j.data.items : [];
          return render(items);
        }).catch(()=>{ render([]); });
      }

      // Lightweight unread count polling for live badge update
      function pollUnread(){
        const url = api + '?action=count_unread';
        fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(rjson)
          .then(j => {
            const unread = (j && j.data && typeof j.data.unread === 'number') ? j.data.unread : 0;
            if (unread > 0) {
              badge.classList.remove('d-none');
              badge.textContent = String(unread);
            } else {
              badge.classList.add('d-none');
              badge.textContent = '0';
            }
          })
          .catch(()=>{});
      }

      fetchCsrf().then(() => { load(); pollUnread(); });
      setInterval(load, 30000);
      setInterval(pollUnread, 10000);
      document.addEventListener('shown.bs.modal', function(e){ if (e.target && e.target.id === 'nlModal') { load(); } });
    })();
  </script>
  <?php endif; ?>
