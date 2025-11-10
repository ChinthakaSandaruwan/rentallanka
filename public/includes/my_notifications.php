<?php
require_once __DIR__ . '/../../config/config.php';

$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
if (!$loggedIn) { redirect_with_message($base_url . '/auth/login.php', 'Please log in first', 'error'); }

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Notifications</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .notif-item.unread { background-color: #fffdf5; }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h4 mb-1"><i class="bi bi-bell me-2"></i>My Notifications</h1>
      <div class="text-muted small">Messages from Admin and system alerts</div>
    </div>
    <div class="btn-group">
      <button class="btn btn-outline-secondary btn-sm" id="toggle-unread"><i class="bi bi-filter me-1"></i><span>Show Unread Only</span></button>
      <button class="btn btn-outline-secondary btn-sm" id="refresh"><i class="bi bi-arrow-clockwise"></i></button>
    </div>
  </div>

  <div id="notifList" class="list-group shadow-sm"></div>

  <div id="emptyState" class="alert alert-light border d-none mt-3">No notifications found.</div>
</div>

<script>
(function(){
  const base = <?php echo json_encode($base_url); ?>;
  const role = <?php echo json_encode($_SESSION['role'] ?? ''); ?>;
  const api = (role === 'admin')
    ? (base + '/notifications/between_admin_and_owner.php')
    : (base + '/notifications/between_customer_and_owner.php');
  const listEl = document.getElementById('notifList');
  const emptyEl = document.getElementById('emptyState');
  const toggleUnreadBtn = document.getElementById('toggle-unread');
  const refreshBtn = document.getElementById('refresh');
  let unreadOnly = false;

  async function fetchList() {
    const p = new URLSearchParams({ action: 'list' });
    if (unreadOnly) p.set('unread_only', '1');
    const url = `${api}?${p.toString()}`;
    const res = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Failed to load');
    renderList(data.data.items || []);
  }

  function itemTemplate(it){
    const unreadCls = String(it.is_read) === '0' ? 'unread' : '';
    const badge = String(it.is_read) === '0' ? '<span class="badge bg-warning text-dark ms-2">Unread</span>' : '';
    const created = it.created_at ? new Date(it.created_at.replace(' ', 'T')).toLocaleString() : '';
    return `
      <div class="list-group-item notif-item ${unreadCls}" data-id="${it.notification_id}">
        <div class="d-flex w-100 justify-content-between">
          <h6 class="mb-1">${escapeHtml(it.title || 'Notification')} ${badge}</h6>
          <small class="text-muted">${escapeHtml(created)}</small>
        </div>
        <p class="mb-2">${escapeHtml(it.message || '')}</p>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-success act-mark" ${String(it.is_read) === '0' ? '' : 'disabled'}><i class="bi bi-check2"></i> Mark read</button>
          <button class="btn btn-sm btn-outline-danger act-del"><i class="bi bi-trash"></i> Delete</button>
        </div>
      </div>
    `;
  }

  function renderList(items){
    listEl.innerHTML = items.map(itemTemplate).join('');
    const has = items.length > 0;
    listEl.classList.toggle('d-none', !has);
    emptyEl.classList.toggle('d-none', has);
  }

  function escapeHtml(s){
    return String(s).replace(/[&<>"]+/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]); });
  }

  async function getCsrf(){
    const res = await fetch(`${api}?action=csrf`, { credentials: 'same-origin' });
    const data = await res.json();
    return data.data && data.data.csrf_token ? data.data.csrf_token : '';
  }

  async function markRead(id){
    const token = await getCsrf();
    const res = await fetch(`${api}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action:'mark_read', notification_id:String(id), csrf_token: token })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Failed to mark');
  }

  async function delItem(id){
    const token = await getCsrf();
    const res = await fetch(`${api}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action:'delete', notification_id:String(id), csrf_token: token })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Failed to delete');
  }

  document.addEventListener('click', async (e) => {
    const card = e.target.closest('.notif-item');
    if (!card) return;
    const id = parseInt(card.getAttribute('data-id') || '0', 10);
    if (id <= 0) return;
    if (e.target.closest('.act-mark')){
      try { await markRead(id); await fetchList(); } catch(err){ alert(err.message || 'Failed'); }
    } else if (e.target.closest('.act-del')){
      if (!confirm('Delete this notification?')) return;
      try { await delItem(id); await fetchList(); } catch(err){ alert(err.message || 'Failed'); }
    }
  });

  toggleUnreadBtn.addEventListener('click', () => {
    unreadOnly = !unreadOnly;
    toggleUnreadBtn.querySelector('span').textContent = unreadOnly ? 'Showing Unread Only' : 'Show Unread Only';
    fetchList();
  });
  refreshBtn.addEventListener('click', () => fetchList());

  // Initial load
  fetchList().catch(() => { renderList([]); });
})();
</script>
</body>
</html>
