<?php
if ((function_exists('session_status') ? session_status() : PHP_SESSION_NONE) === PHP_SESSION_NONE) {
    session_start();
}

$isSuper = isset($_SESSION['super_admin_id']);
$loggedIn = (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) || $isSuper;
$role = $_SESSION['role'] ?? '';
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

        
        <!-- Right side -->
        <div class="d-flex align-items-center gap-2">

          <?php if (!$loggedIn): ?>
            <a href="<?= $base_url ?>/auth/login.php" class="btn btn-primary btn-sm">Login</a>
          <?php else: ?>
                        <?php if (!$isSuper): ?>
              <a href="<?= $base_url ?>/public/includes/wish_list.php" class="btn btn-outline-primary btn-sm position-relative" title="Wishlist">
                <i class="bi bi-heart"></i>
                <?php if ($wlCount > 0): ?>
                  <span class="position-absolute top-0 end-0 translate-middle-y badge rounded-pill bg-danger"><?= (int)$wlCount ?></span>
                <?php endif; ?>
              </a>
            <?php endif; ?>

            <?php if (in_array($role, ['owner','admin','customer'], true)): ?>
              <button type="button" class="btn btn-outline-secondary btn-sm position-relative" id="nl-bell" data-bs-toggle="modal" data-bs-target="#nlModal" title="Notifications">
                <i class="bi bi-bell"></i>
                <span class="position-absolute top-0 end-0 translate-middle-y badge rounded-pill bg-danger d-none" id="nl-badge">0</span>
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

            <div class="dropdown" data-bs-display="static">
              <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <i class="bi bi-person-circle me-1"></i> Account
              </button>
              <ul class="dropdown-menu dropdown-menu-end mt-2 shadow">
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
      const api = (role === 'customer')
        ? (baseUrl + '/notifications/between_customer_and_admin.php')
        : (baseUrl + '/notifications/between_admin_and_owner.php');
      const badge = document.getElementById('nl-badge');
      const listEl = document.getElementById('nl-list');
      if (!badge || !listEl) return;
      let csrf = '';

      function rjson(r){ return r.ok ? r.json() : r.json().then(j=>Promise.reject(j)); }

      function fetchCsrf(){
        return fetch(api + '?action=csrf').then(rjson).then(j=>{ csrf = j.data.csrf_token || ''; });
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
        if (role === 'admin' && currentUserId > 0 && api.indexOf('between_admin_and_owner.php') !== -1) {
          params.set('owner_id', String(currentUserId));
        }
        return fetch(api + '?' + params.toString()).then(rjson).then(j => render(j.data.items || []));
      }

      fetchCsrf().then(load);
      setInterval(load, 30000);
      document.addEventListener('shown.bs.modal', function(e){ if (e.target && e.target.id === 'nlModal') { load(); } });
    })();
  </script>
  <?php endif; ?>
