<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ___DIR___ . '/error.log');
if (isset($_GET['show_errors']) && $_GET['show_errors'] === '1') {
  $f = ___DIR___ . '/error.log';
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
require_once ___DIR___ . '/../../config/config.php';

$uid = (int)($_SESSION['user']['user_id'] ?? 0);
$role = (string)($_SESSION['role'] ?? '');
$rid = (int)($_GET['id'] ?? $_POST['room_id'] ?? 0);
// Detect AJAX/XHR requests
$isAjax = (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') || isset($_GET['ajax']) || isset($_POST['ajax']);

if ($rid <= 0) {
  http_response_code(302);
  header('Location: ' . rtrim($base_url, '/') . '/index.php');
  exit;
}

// Require login
if ($uid <= 0) {
  if ($isAjax) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Please sign in to rent a room']);
    exit;
  }
  redirect_with_message(rtrim($base_url,'/') . '/auth/login.php', 'Please sign in to rent a room', 'info');
}

// Load room and ensure available
$stmt = db()->prepare('SELECT room_id, owner_id, title, price_per_day, status, maximum_guests FROM rooms WHERE room_id = ? LIMIT 1');
$stmt->bind_param('i', $rid);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$room) {
  http_response_code(404);
  if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Room not found.']);
    exit;
  }
  redirect_with_message(rtrim($base_url,'/') . '/public/includes/view_room.php?id='.(int)$rid, 'Room not found.', 'error');
}
// Prevent owners from renting their own rooms
if ((int)($room['owner_id'] ?? 0) === $uid) {
  http_response_code(403);
  if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'You cannot rent your own room.']);
    exit;
  }
  redirect_with_message(rtrim($base_url,'/') . '/public/includes/view_room.php?id='.(int)$rid, 'You cannot rent your own room.', 'error');
}
if (($room['status'] ?? '') !== 'available') {
  http_response_code(409);
  if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'This room is not available for rent.']);
    exit;
  }
  redirect_with_message(rtrim($base_url,'/') . '/public/includes/view_room.php?id='.(int)$rid, 'This room is not available for rent.', 'error');
}

$unavailable = [];
// Load existing future bookings for this room to show unavailable ranges
try {
  $qb = db()->prepare("SELECT DATE(checkin_date) AS ci, DATE(checkout_date) AS co FROM room_rents WHERE room_id=? AND status IN ('booked','pending') AND checkout_date > NOW() ORDER BY checkin_date");
  $qb->bind_param('i', $rid);
  $qb->execute();
  $rs = $qb->get_result();
  while ($row = $rs->fetch_assoc()) {
    $ciD = (string)($row['ci'] ?? '');
    $coD = (string)($row['co'] ?? '');
    if ($ciD !== '' && $coD !== '') {
      $unavailable[] = [$ciD, $coD];
    }
  }
  $qb->close();
} catch (Throwable $e) {}

$meals = [];
// Load per-room meals only
try {
  $ov = db()->prepare('SELECT meal_id, meal_name, price FROM room_meals WHERE room_id=? ORDER BY meal_name');
  $ov->bind_param('i', $rid);
  $ov->execute();
  $or = $ov->get_result();
  while ($row = $or->fetch_assoc()) {
    $mid = (int)$row['meal_id'];
    $meals[$mid] = [
      'name' => (string)$row['meal_name'],
      'price' => max(0.0, (float)($row['price'] ?? 0)),
    ];
  }
  $ov->close();
} catch (Throwable $e) {}

$errors = [];
$success = false;
$rentId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $checkin = trim($_POST['checkin_date'] ?? '');
  $checkout = trim($_POST['checkout_date'] ?? '');
  $guests = (int)($_POST['guests'] ?? 1);
  $meal_id = (int)($_POST['meal_id'] ?? 0);

  // Validate dates
  try {
    $dtIn = new DateTime($checkin);
    $dtOut = new DateTime($checkout);
  } catch (Throwable $e) {
    $dtIn = $dtOut = null;
  }
  if (!$dtIn || !$dtOut) {
    $errors[] = 'Please provide valid check-in and check-out dates.';
  } else {
    // Set time to noon to avoid DST issues
    $dtIn->setTime(12, 0, 0);
    $dtOut->setTime(12, 0, 0);
    $now = new DateTime('now');
    if ($dtOut <= $dtIn) {
      $errors[] = 'Check-out must be after check-in.';
    }
    if ($dtIn < (clone $now)->setTime(0,0,0)) {
      $errors[] = 'Check-in cannot be in the past.';
    }
  }
  if ($guests <= 0) { $errors[] = 'Guests must be at least 1.'; }

  if ($meal_id !== 0 && !array_key_exists($meal_id, $meals)) { $errors[] = 'Invalid meal selection.'; }

  if (!$errors) {
    // Prevent overlapping or touching bookings for this room for active statuses (inclusive bounds)
    $sql = 'SELECT COUNT(*) AS c FROM room_rents WHERE room_id = ? AND status IN (\'booked\', \'pending\') AND checkin_date <= ? AND checkout_date >= ?';
    $st = db()->prepare($sql);
    $ci = $dtIn->format('Y-m-d H:i:s');
    $co = $dtOut->format('Y-m-d H:i:s');
    $st->bind_param('iss', $rid, $co, $ci);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if ((int)($row['c'] ?? 0) > 0) {
      $errors[] = 'Selected dates are not available.';
    }
  }

  if (!$errors) {
    // Calculate totals
    $days = (int)$dtIn->diff($dtOut)->days;
    if ($days < 1) { $days = 1; }
    $pricePerDay = (float)($room['price_per_day'] ?? 0);
    if ($meal_id > 0 && isset($meals[$meal_id])) {
      $mealPerGuest = (float)($meals[$meal_id]['price'] ?? 0);
      if ($mealPerGuest < 0) { $mealPerGuest = 0.0; }
      $mealPerDay = $mealPerGuest * max(1, (int)$guests);
    } else {
      $mealPerDay = 0.0;
    }
    $pricePerDayWithMeal = $pricePerDay + $mealPerDay;
    $total = $pricePerDayWithMeal * $days;

    db()->begin_transaction();
    try {
      // Insert booking
      if ($meal_id > 0 && isset($meals[$meal_id])) {
        $ins = db()->prepare('INSERT INTO room_rents (room_id, customer_id, checkin_date, checkout_date, guests, meal_id, price_per_night, total_amount, status) VALUES (?,?,?,?,?,?,?,?,\'pending\')');
        $ins->bind_param('iissiidd', $rid, $uid, $ci, $co, $guests, $meal_id, $pricePerDayWithMeal, $total);
      } else {
        $ins = db()->prepare('INSERT INTO room_rents (room_id, customer_id, checkin_date, checkout_date, guests, price_per_night, total_amount, status) VALUES (?,?,?,?,?,?,?,\'pending\')');
        $ins->bind_param('iissidd', $rid, $uid, $ci, $co, $guests, $pricePerDayWithMeal, $total);
      }
      $ok1 = $ins->execute();
      $rentId = (int)db()->insert_id;
      $ins->close();

      if (!$ok1) {
        throw new Exception('Failed to book room.');
      }

      // Notify the room owner about the new pending booking (best-effort)
      try {
        $ownerId = (int)($room['owner_id'] ?? 0);
        if ($ownerId > 0) {
          $titleN = 'Booking Pending Request Came, Please Get a Action';
          $ciLbl = substr($ci, 0, 10);
          $coLbl = substr($co, 0, 10);
          // Look up customer's phone to include in the owner's notification
          $customerPhone = '';
          try {
            $q2 = db()->prepare('SELECT phone FROM users WHERE user_id = ? LIMIT 1');
            $q2->bind_param('i', $uid);
            $q2->execute();
            $r2 = $q2->get_result();
            $row2 = $r2 ? $r2->fetch_assoc() : null;
            $customerPhone = (string)($row2['phone'] ?? '');
            $q2->close();
          } catch (Throwable $_) {}
          $msgN = 'A new booking #' . (int)$rentId . ' for room ' . (string)($room['title'] ?? ('#'.$rid)) . ' is pending from ' . $ciLbl . ' to ' . $coLbl . '. Guests: ' . (int)$guests . '.'
                . ($customerPhone !== '' ? (' Customer mobile: ' . $customerPhone . '.') : '');
          $typeN = 'system';
          // notifications table has optional property_id, set to NULL for room events
          $nt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, NULL)');
          $nt->bind_param('isss', $ownerId, $titleN, $msgN, $typeN);
          $nt->execute();
          $nt->close();
        }
      } catch (Throwable $eN) { /* ignore notification failure */ }

      // Notify the customer about booking pending (best-effort)
      try {
        $ciLbl = substr($ci, 0, 10);
        $coLbl = substr($co, 0, 10);
        $titleC = 'Booking Pending Please Wait';
        $msgC = 'Your booking #' . (int)$rentId . ' for room ' . (string)($room['title'] ?? ('#'.$rid)) . ' is pending from ' . $ciLbl . ' to ' . $coLbl . '. Guests: ' . (int)$guests . '.';
        $typeC = 'system';
        $nt2 = db()->prepare('INSERT INTO notifications (user_id, title, message, type, property_id) VALUES (?,?,?,?, NULL)');
        $nt2->bind_param('isss', $uid, $titleC, $msgC, $typeC);
        $nt2->execute();
        $nt2->close();
      } catch (Throwable $eC) { /* ignore notification failure */ }

      db()->commit();
      $success = true;
    } catch (Throwable $e) {
      db()->rollback();
      $errors[] = 'Booking failed. Please try again.';
    }
  }
  // AJAX response for POST
  if ($isAjax) {
    header('Content-Type: application/json');
    if ($success) {
      echo json_encode(['status' => 'success', 'message' => 'Booking pending', 'rent_id' => (int)$rentId]);
    } else {
      echo json_encode(['status' => 'error', 'message' => ($errors ? implode("\n", $errors) : 'Booking failed')]);
    }
    exit;
  }
  // Non-AJAX: redirect to My Rentals with flash via navbar
  $msg = $success ? ('Booking #' . (int)$rentId . ' submitted and pending approval.') : (($errors ? implode(" \n", $errors) : 'Booking failed'));
  $typ = $success ? 'success' : 'error';
  redirect_with_message(rtrim($base_url,'/') . '/public/includes/my_rentals.php', $msg, $typ);
}

// AJAX response for GET: return fragment with booking form card only
if ($isAjax && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  ob_start();
  ?>
  <div class="card">
    <div class="card-header">Booking details</div>
    <div class="card-body">
      <div id="formAlert"></div>
      <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <input type="hidden" name="ajax" value="1">
        <input type="hidden" name="room_id" value="<?php echo (int)$rid; ?>">
        <div class="row g-3">
          <div class="col-sm-6">
            <label for="checkin_date" class="form-label">Check-in date</label>
            <input type="date" class="form-control" id="checkin_date" name="checkin_date" required>
          </div>
          <div class="col-sm-6">
            <label for="checkout_date" class="form-label">Check-out date</label>
            <input type="date" class="form-control" id="checkout_date" name="checkout_date" required>
          </div>
          <div class="col-sm-6">
            <label for="guests" class="form-label">Guests</label>
            <?php $maxGuests = (int)($room['maximum_guests'] ?? 0); ?>
            <input type="number" class="form-control" id="guests" name="guests" value="1" required>
          </div>
          <div class="col-sm-6">
            <label for="meal_id" class="form-label">Meal option</label>
            <select id="meal_id" name="meal_id" class="form-select">
              <option value="0" selected>No meals</option>
              <?php foreach ($meals as $mid => $meta): ?>
                <option value="<?php echo (int)$mid; ?>" data-price="<?php echo number_format((float)($meta['price'] ?? 0), 2, '.', ''); ?>">
                  <?php echo htmlspecialchars(ucwords(str_replace('_',' ', (string)$meta['name'])) . ' • LKR ' . number_format((float)($meta['price'] ?? 0), 2) . '/night'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <div id="priceInfo" class="text-muted small">
              Room Per Night: LKR <?php echo number_format((float)($room['price_per_day'] ?? 0), 2); ?><?php if (!empty($meals)) { echo ' • Meal Per Night depends on selection'; } ?>
            </div>
          </div>
          <div class="col-12">
            <div class="form-text mb-1">Unavailable dates</div>
            <div class="d-flex flex-wrap gap-2" id="unavailBadges">
              <?php if (!empty($unavailable)): ?>
                <?php foreach ($unavailable as $rng): ?>
                  <?php $ciLbl = htmlspecialchars(date('Y-m-d', strtotime($rng[0]))); $coLbl = htmlspecialchars(date('Y-m-d', strtotime($rng[1]))); ?>
                  <span class="badge bg-danger"><?php echo $ciLbl; ?> to <?php echo $coLbl; ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="badge bg-success">No current blocks</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="mt-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary"><i class="bi bi-bag-check me-1"></i>Confirm Booking</button>
        </div>
      </form>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    (function(){
      const priceInfo = document.getElementById('priceInfo');
      const alertHost = document.getElementById('formAlert');
      const roomPrice = <?php echo json_encode((float)($room['price_per_day'] ?? 0)); ?>;
      const mealSel = document.getElementById('meal_id');
      const ci = document.getElementById('checkin_date');
      const co = document.getElementById('checkout_date');
      const guests = document.getElementById('guests');
      const unavailable = <?php echo json_encode($unavailable, JSON_UNESCAPED_SLASHES); ?>; // [[ci,co], ...]

      function parseDate(input){ if (!input || !input.value) return null; const d=new Date(input.value+'T12:00:00'); return isNaN(d)?null:d; }
      function daysBetween(a,b){ if(!a||!b) return 0; const ms=(b-a)/(1000*60*60*24); const d=Math.floor(ms); return d>0?d:1; }
      function getMealPerDay(){ if(!mealSel) return 0; const opt=mealSel.options[mealSel.selectedIndex]; const raw=opt&&opt.dataset?opt.dataset.price:'0'; const perGuest=parseFloat(raw||'0'); const g=parseInt(guests&&guests.value?guests.value:'1',10); const cnt=isNaN(g)||g<1?1:g; const v=(isNaN(perGuest)?0:perGuest)*cnt; return v<0?0:v; }
      function fmt(n){ return (Number(n)||0).toFixed(2); }
      function inUnavailable(d){ if(!d) return false; const x=new Date(d.getTime()); x.setHours(12,0,0,0); for(const [a,b] of (unavailable||[])){ if(!a||!b) continue; const da=new Date(a+'T00:00:00'); const db=new Date(b+'T00:00:00'); if(x>=da && x<=db) return true; } return false; }
      function rangeOverlapsUnavailable(d1,d2){ if(!d1||!d2) return false; for(const [a,b] of (unavailable||[])){ if(!a||!b) continue; const da=new Date(a+'T00:00:00'); const db=new Date(b+'T00:00:00'); if(d1<=db && d2>=da) return true; } return false; }
      function enforceMinCheckout(){ const d1=parseDate(ci); if(!co) return; if(d1){ const nxt=new Date(d1.getTime()); nxt.setDate(nxt.getDate()+1); co.min = nxt.toISOString().slice(0,10); } else { co.removeAttribute('min'); } }

      function update(){ if(!priceInfo) return; const mealPerDay=getMealPerDay(); const pricePerDay=roomPrice+mealPerDay; const d1=parseDate(ci); const d2=parseDate(co); const days=daysBetween(d1,d2); const total=pricePerDay*(d1&&d2?days:1); const g=parseInt(guests&&guests.value?guests.value:'1',10)||1; priceInfo.innerHTML = '<div>Guests: '+g+'</div><div>Room Per Night: LKR '+fmt(roomPrice)+'</div><div>Meal Per Night (all guests): LKR '+fmt(mealPerDay)+'</div><div>Price Per Night: LKR '+fmt(pricePerDay)+'</div>' + (d1&&d2?'<div>Days: '+days+'</div>':'') + '<div class="mt-2"><span class="badge bg-success">Total: LKR '+fmt(total)+'</span></div>'; if(ci) ci.classList.toggle('is-invalid', inUnavailable(d1)); if(co) co.classList.toggle('is-invalid', inUnavailable(d2)); if(ci && co){ const bad = rangeOverlapsUnavailable(d1,d2); ci.classList.toggle('is-invalid', bad || inUnavailable(d1)); co.classList.toggle('is-invalid', bad || inUnavailable(d2)); }
      }

      mealSel && mealSel.addEventListener('change', update);
      ci && ci.addEventListener('change', function(){ enforceMinCheckout(); update(); });
      co && co.addEventListener('change', update);
      guests && guests.addEventListener('input', update);
      // init
      enforceMinCheckout();
      update();
    })();
  </script>
  <?php
  $html = ob_get_clean();
  echo $html;
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rent Room</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php require_once ___DIR___ . '/navbar.php'; ?>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0"><i class="bi bi-bag-check me-2"></i>Rent: <?php echo htmlspecialchars($room['title'] ?? ''); ?></h1>
        <a href="<?php echo $base_url; ?>/public/includes/view_room.php?id=<?php echo (int)$rid; ?>" class="btn btn-outline-secondary btn-sm">Back</a>
      </div>

      <?php if (!$success): ?>
        <?php if ($errors): ?>
          <div class="text-danger small">Please fix the errors below and try again.</div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header">Booking details</div>
          <div class="card-body">
            <div id="formAlert"></div>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
              <input type="hidden" name="room_id" value="<?php echo (int)$rid; ?>">
              <div class="row g-3">
                <div class="col-sm-6">
                  <label for="checkin_date" class="form-label">Check-in date</label>
                  <input type="date" class="form-control" id="checkin_date" name="checkin_date" value="<?php echo htmlspecialchars($_POST['checkin_date'] ?? ''); ?>" required>
                </div>
                <div class="col-sm-6">
                  <label for="checkout_date" class="form-label">Check-out date</label>
                  <input type="date" class="form-control" id="checkout_date" name="checkout_date" value="<?php echo htmlspecialchars($_POST['checkout_date'] ?? ''); ?>" required>
                </div>
                <div class="col-sm-6">
                  <label for="guests" class="form-label">Guests</label>
                  <?php $maxGuests = (int)($room['maximum_guests'] ?? 0); ?>
                  <input type="number" class="form-control" id="guests" name="guests" value="<?php echo htmlspecialchars((string)($_POST['guests'] ?? '1')); ?>" required>
                </div>
                <div class="col-sm-6">
                  <label for="meal_id" class="form-label">Meal option</label>
                  <select id="meal_id" name="meal_id" class="form-select">
                    <?php $curMeal = (int)($_POST['meal_id'] ?? 0); ?>
                    <option value="0" <?php echo $curMeal===0 ? 'selected' : ''; ?>>No meals</option>
                    <?php foreach ($meals as $mid => $meta): ?>
                      <option value="<?php echo (int)$mid; ?>" data-price="<?php echo number_format((float)($meta['price'] ?? 0), 2, '.', ''); ?>" <?php echo $curMeal===(int)$mid ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(ucwords(str_replace('_',' ', (string)$meta['name'])) . ' • LKR ' . number_format((float)($meta['price'] ?? 0), 2) . '/night'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <div id="priceInfo" class="text-muted small">
                    Room Per Night: LKR <?php echo number_format((float)($room['price_per_day'] ?? 0), 2); ?><?php if (!empty($meals)) { echo ' • Meal Per Night depends on selection'; } ?>
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-text mb-1">Unavailable dates</div>
                  <div class="d-flex flex-wrap gap-2" id="unavailBadges">
                    <?php if (!empty($unavailable)): ?>
                      <?php foreach ($unavailable as $rng): ?>
                        <?php
                          $ciLbl = htmlspecialchars(date('Y-m-d', strtotime($rng[0])));
                          $coLbl = htmlspecialchars(date('Y-m-d', strtotime($rng[1])));
                        ?>
                        <span class="badge bg-danger"><?php echo $ciLbl; ?> to <?php echo $coLbl; ?></span>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <span class="badge bg-success">No current blocks</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-bag-check me-1"></i>Confirm Booking</button>
                <a href="<?php echo $base_url; ?>/public/includes/view_room.php?id=<?php echo (int)$rid; ?>" class="btn btn-outline-secondary">Cancel</a>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function() {
    const priceInfo = document.getElementById('priceInfo');
    const alertHost = document.getElementById('formAlert');
    const roomPrice = <?php echo json_encode((float)($room['price_per_day'] ?? 0)); ?>;
    const mealSel = document.getElementById('meal_id');
    const ci = document.getElementById('checkin_date');
    const co = document.getElementById('checkout_date');
    const guests = document.getElementById('guests');
    const form = document.querySelector('form');
    const unavailable = <?php echo json_encode($unavailable, JSON_UNESCAPED_SLASHES); ?>; // [[ci,co], ...], co exclusive

    function parseDate(input) {
      if (!input || !input.value) return null;
      const d = new Date(input.value + 'T12:00:00');
      return isNaN(d) ? null : d;
    }

    function daysBetween(a, b) {
      if (!a || !b) return 0;
      const ms = (b - a) / (1000*60*60*24);
      const d = Math.floor(ms);
      return d > 0 ? d : 1; // at least 1 day when both present
    }

    function getMealPerDay() {
      if (!mealSel) return 0;
      const opt = mealSel.options[mealSel.selectedIndex];
      const raw = opt && opt.dataset ? opt.dataset.price : '0';
      const perGuest = parseFloat(raw || '0');
      const g = parseInt(guests && guests.value ? guests.value : '1', 10);
      const cnt = isNaN(g) || g < 1 ? 1 : g;
      const v = (isNaN(perGuest) ? 0 : perGuest) * cnt;
      return v < 0 ? 0 : v;
    }

    function fmt(n) { return (Number(n)||0).toFixed(2); }

    function inUnavailable(d) {
      if (!d) return false;
      const x = new Date(d.getTime());
      x.setHours(12,0,0,0);
      for (const [a,b] of (unavailable||[])) {
        if (!a || !b) continue;
        const da = new Date(a + 'T00:00:00');
        const db = new Date(b + 'T00:00:00');
        // treat range as [ci, co] inclusive: end date is also blocked
        if (x >= da && x <= db) return true;
      }
      return false;
    }

    function rangeOverlapsUnavailable(d1, d2) {
      if (!d1 || !d2) return false;
      // overlap/touch if exists [ci,co] such that d1 <= co && d2 >= ci
      for (const [a,b] of (unavailable||[])) {
        if (!a || !b) continue;
        const da = new Date(a + 'T00:00:00');
        const db = new Date(b + 'T00:00:00');
        if (d1 <= db && d2 >= da) return true;
      }
      return false;
    }

    function showAlert(html) {
      if (window.Swal) {
        Swal.fire({ icon: 'error', title: 'Error', html: html, confirmButtonText: 'OK' });
      }
    }
    function clearAlert() { /* not needed with SweetAlert2 */ }

    function update() {
      if (!priceInfo) return;
      const mealPerDay = getMealPerDay();
      const pricePerDay = roomPrice + mealPerDay;
      const d1 = parseDate(ci);
      const d2 = parseDate(co);
      const days = daysBetween(d1, d2);
      const total = pricePerDay * (d1 && d2 ? days : 1);
      const g = parseInt(guests && guests.value ? guests.value : '1', 10) || 1;
      priceInfo.innerHTML = `
        <div>Guests: ${g}</div>
        <div>Room Per Night: LKR ${fmt(roomPrice)}</div>
        <div>Meal Per Night (all guests): LKR ${fmt(mealPerDay)}</div>
        <div>Price Per Night: LKR ${fmt(pricePerDay)}</div>
        ${d1 && d2 ? `<div>Days: ${days}</div>` : ''}
        <div class="mt-2"><span class="badge bg-success">Total: LKR ${fmt(total)}</span></div>
      `;
      // Simple visual invalid state on selecting blocked dates
      if (ci) ci.classList.toggle('is-invalid', inUnavailable(d1));
      if (co) co.classList.toggle('is-invalid', inUnavailable(d2));
    }

    mealSel && mealSel.addEventListener('change', update);
    ci && ci.addEventListener('change', update);
    co && co.addEventListener('change', update);
    guests && guests.addEventListener('input', update);
    update();

    // Client-side validation
    form && form.addEventListener('submit', function(e) {
      clearAlert();
      const errs = [];
      const d1 = parseDate(ci);
      const d2 = parseDate(co);
      if (!d1 || !d2) {
        errs.push('Please provide valid check-in and check-out dates.');
      } else {
        const now = new Date(); now.setHours(0,0,0,0);
        if (d2 <= d1) errs.push('Check-out must be after check-in.');
        if (d1 < now) errs.push('Check-in cannot be in the past.');
        if (inUnavailable(d1) || inUnavailable(d2) || rangeOverlapsUnavailable(d1, d2)) {
          errs.push('Your selected dates overlap or touch an unavailable range.');
        }
      }
      const g = parseInt(guests && guests.value ? guests.value : '0', 10);
      if (isNaN(g) || g < 1) errs.push('Guests must be at least 1.');
      // Removed client-side max guests constraint; server-side will validate if needed
      if (errs.length) {
        e.preventDefault();
        showAlert('<div class="fw-semibold mb-2">Please fix the following:</div><ul class="mb-0">' + errs.map(x=>'\n<li>'+x+'</li>').join('') + '</ul>');
      }
    });
  })();
</script>
</body>
</html>
