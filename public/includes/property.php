<?php
require_once __DIR__ . '/../../config/config.php';
$items = [];
$stmt = db()->prepare("SELECT property_id, title, price_per_month, image, status FROM properties WHERE status = 'available' ORDER BY property_id DESC LIMIT 8");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $items[] = $row; }
$stmt->close();
?>
<section class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="h4 mb-0">Latest Properties</h2>
    <a href="owner/property_management.php" class="btn btn-sm btn-outline-primary d-none">View all</a>
  </div>
  <div class="row g-3">
    <?php foreach ($items as $p): ?>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
          <?php if (!empty($p['image'])): ?>
            <img src="<?php echo htmlspecialchars($p['image']); ?>" class="card-img-top" alt="">
          <?php endif; ?>
          <div class="card-body d-flex flex-column">
            <h5 class="card-title mb-1"><?php echo htmlspecialchars($p['title']); ?></h5>
            <div class="text-muted mb-2 small text-uppercase"><?php echo htmlspecialchars($p['status']); ?></div>
            <div class="mt-auto fw-semibold">LKR <?php echo number_format((float)$p['price_per_month'], 2); ?>/month</div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
            <div class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-secondary" href="public/includes/view_property.php?id=<?php echo (int)$p['property_id']; ?>">View</a>
              <a class="btn btn-sm btn-primary" href="public/includes/rent_property.php?id=<?php echo (int)$p['property_id']; ?>">Rent</a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$items): ?>
      <div class="col-12"><div class="alert alert-light border">No properties to show.</div></div>
    <?php endif; ?>
  </div>
</section>
