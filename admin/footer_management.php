<?php require_once __DIR__ . '/../public/includes/auth_guard.php'; require_role('admin'); ?>
<?php require_once __DIR__ . '/../config/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Footer Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/../public/includes/navbar.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Footer Management</h1>
    <a href="index.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
  </div>

  <?php
  $saved = false; $error = '';
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
      try {
          $fields = [
            'footer_company_name','footer_about','footer_address','footer_email','footer_phone',
            'footer_social_facebook','footer_social_twitter','footer_social_google','footer_social_instagram','footer_social_linkedin','footer_social_github',
            'footer_products_links','footer_useful_links','footer_copyright_text',
            'footer_show_social','footer_show_products','footer_show_useful_links','footer_show_contact'
          ];
          foreach ($fields as $k) {
              $v = $_POST[$k] ?? '';
              if (in_array($k, ['footer_show_social','footer_show_products','footer_show_useful_links','footer_show_contact'], true)) {
                  $v = ($v === '1') ? '1' : '0';
              }
              setting_set($k, $v);
          }
          $saved = true;
      } catch (Throwable $e) { $error = 'Save failed'; }
  }

  $val = fn(string $k, string $d='') => (string)(setting_get($k, $d) ?? $d);
  ?>

  <?php if ($saved): ?>
    <div class="alert alert-success">Saved</div>
  <?php elseif ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title">Brand</h5>
          <div class="mb-3">
            <label class="form-label">Company Name</label>
            <input type="text" name="footer_company_name" class="form-control" value="<?= htmlspecialchars($val('footer_company_name','Company name')) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">About Text</label>
            <textarea name="footer_about" rows="4" class="form-control"><?= htmlspecialchars($val('footer_about','Here you can use rows and columns to organize your footer content.')) ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title">Contact</h5>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Address</label>
              <input type="text" name="footer_address" class="form-control" value="<?= htmlspecialchars($val('footer_address','New York, NY 10012, US')) ?>">
            </div>
            <div class="col-6">
              <label class="form-label">Email</label>
              <input type="email" name="footer_email" class="form-control" value="<?= htmlspecialchars($val('footer_email','info@example.com')) ?>">
            </div>
            <div class="col-6">
              <label class="form-label">Phone</label>
              <input type="text" name="footer_phone" class="form-control" value="<?= htmlspecialchars($val('footer_phone','+ 01 234 567 88')) ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Social Links</h5>
          <div class="row g-3">
            <div class="col-12 col-md-6 col-lg-4">
              <label class="form-label">Facebook URL</label>
              <input type="url" name="footer_social_facebook" class="form-control" value="<?= htmlspecialchars($val('footer_social_facebook','')) ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-4">
              <label class="form-label">Twitter/X URL</label>
              <input type="url" name="footer_social_twitter" class="form-control" value="<?= htmlspecialchars($val('footer_social_twitter','')) ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-4">
              <label class="form-label">Google URL</label>
              <input type="url" name="footer_social_google" class="form-control" value="<?= htmlspecialchars($val('footer_social_google','')) ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-4">
              <label class="form-label">Instagram URL</label>
              <input type="url" name="footer_social_instagram" class="form-control" value="<?= htmlspecialchars($val('footer_social_instagram','')) ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-4">
              <label class="form-label">LinkedIn URL</label>
              <input type="url" name="footer_social_linkedin" class="form-control" value="<?= htmlspecialchars($val('footer_social_linkedin','')) ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-4">
              <label class="form-label">GitHub URL</label>
              <input type="url" name="footer_social_github" class="form-control" value="<?= htmlspecialchars($val('footer_social_github','')) ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title">Products Links</h5>
          <p class="text-muted small mb-2">One per line: Label|URL</p>
          <textarea name="footer_products_links" rows="6" class="form-control"><?= htmlspecialchars($val('footer_products_links',"Angular|#\nReact|#\nVue|#\nLaravel|#")) ?></textarea>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title">Useful Links</h5>
          <p class="text-muted small mb-2">One per line: Label|URL</p>
          <textarea name="footer_useful_links" rows="6" class="form-control"><?= htmlspecialchars($val('footer_useful_links',"Pricing|#\nSettings|#\nOrders|#\nHelp|#")) ?></textarea>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-12 col-md-6 col-lg-4">
              <label class="form-label">Copyright Text</label>
              <input type="text" name="footer_copyright_text" class="form-control" value="<?= htmlspecialchars($val('footer_copyright_text','© '.date('Y').' Copyright:')) ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-8">
              <div class="row g-3">
                <div class="col-6 col-lg-3">
                  <label class="form-label">Show Social</label>
                  <select name="footer_show_social" class="form-select">
                    <?php $ss=$val('footer_show_social','1'); ?>
                    <option value="1" <?= $ss==='1'?'selected':'' ?>>Yes</option>
                    <option value="0" <?= $ss==='0'?'selected':'' ?>>No</option>
                  </select>
                </div>
                <div class="col-6 col-lg-3">
                  <label class="form-label">Show Products</label>
                  <?php $sp=$val('footer_show_products','1'); ?>
                  <select name="footer_show_products" class="form-select">
                    <option value="1" <?= $sp==='1'?'selected':'' ?>>Yes</option>
                    <option value="0" <?= $sp==='0'?'selected':'' ?>>No</option>
                  </select>
                </div>
                <div class="col-6 col-lg-3">
                  <label class="form-label">Show Useful</label>
                  <?php $su=$val('footer_show_useful_links','1'); ?>
                  <select name="footer_show_useful_links" class="form-select">
                    <option value="1" <?= $su==='1'?'selected':'' ?>>Yes</option>
                    <option value="0" <?= $su==='0'?'selected':'' ?>>No</option>
                  </select>
                </div>
                <div class="col-6 col-lg-3">
                  <label class="form-label">Show Contact</label>
                  <?php $sc=$val('footer_show_contact','1'); ?>
                  <select name="footer_show_contact" class="form-select">
                    <option value="1" <?= $sc==='1'?'selected':'' ?>>Yes</option>
                    <option value="0" <?= $sc==='0'?'selected':'' ?>>No</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 d-flex justify-content-end gap-2">
      <button type="submit" class="btn btn-primary">Save</button>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

