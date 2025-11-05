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
  <div class="row g-3 ">
    <?php foreach ($items as $p): ?>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100 border rounded-3 shadow-sm position-relative overflow-hidden">
          <?php if (!empty($p['status'])): ?>
            <span class="badge bg-success position-absolute top-0 start-0 m-2 text-uppercase small"><?php echo htmlspecialchars($p['status']); ?></span>
          <?php endif; ?>
          <?php if (!empty($p['image'])): ?>
            <?php $src = $p['image']; if ($src && !preg_match('#^https?://#i', $src) && $src[0] !== '/') { $src = '/'.ltrim($src, '/'); } ?>
            <div class="ratio ratio-16x9">
              <img src="<?php echo htmlspecialchars($src); ?>" class="w-100 h-100 object-fit-cover" alt="">
            </div>
          <?php endif; ?>
          <div class="card-body d-flex flex-column">
            <h5 class="card-title mb-1"><?php echo htmlspecialchars($p['title']); ?></h5>
            <div class="mt-auto">
              <span class="fw-semibold">LKR <?php echo number_format((float)$p['price_per_month'], 2); ?>/month</span>
            </div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
            <div class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base_url; ?>/public/includes/view_property.php?id=<?php echo (int)$p['property_id']; ?>"><i class="bi bi-eye me-1"></i>View</a>
              <a class="btn btn-sm btn-primary" href="<?php echo $base_url; ?>/public/includes/rent_property.php?id=<?php echo (int)$p['property_id']; ?>"><i class="bi bi-bag-plus me-1"></i>Rent</a>
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
