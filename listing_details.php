<?php
// listing_details.php — Public page (no login required)
require 'mysql_connect.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header("Location: browse_listings"); exit(); }

$stmt = $conn->prepare("
  SELECT l.id, l.title, l.description, l.address, l.price, l.capacity, l.is_archived,
         a.first_name, a.last_name
  FROM tblistings l
  LEFT JOIN tbadmin a ON a.id = l.owner_id
  WHERE l.id = ?
  LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$listing = $res->fetch_assoc();
$stmt->close();

if (!$listing || (int)$listing['is_archived'] === 1) {
  header("Location: browse_listings"); exit();
}

// Google Maps Embed API (address-only)
$GOOGLE_MAPS_EMBED_KEY = "AIzaSyCrKcnAX9KOdNp_TNHwWwzbLSQodgYqgnU";
$address = trim($listing['address'] ?? '');
$mapsSrc = $address !== ''
  ? "https://www.google.com/maps/embed/v1/place?key=" . urlencode($GOOGLE_MAPS_EMBED_KEY) . "&q=" . urlencode($address)
  : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($listing['title']) ?> • HanapBahay</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body { background:#fff; }
    .topbar { position: sticky; top:0; z-index: 1000; background: #8B4513; color:#fff; }
    .logo { height: 42px; }
    .map-embed { width: 100%; aspect-ratio: 16 / 9; border: 0; border-radius: 12px; }
  </style>
</head>
<body>
  <nav class="topbar py-2">
    <div class="container d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-2">
        <img src="Assets/Logo1.png" class="logo" alt="HanapBahay" />
        <a class="text-white text-decoration-none fw-semibold" href="browse_listings">Back to Browse</a>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="LoginModule" class="btn btn-outline-light btn-sm">Login</a>
        <a href="register" class="btn btn-warning btn-sm text-dark">Register</a>
      </div>
    </div>
  </nav>

  <main class="container py-4">
    <div class="row g-4">
      <div class="col-lg-7">
        <h1 class="h4 mb-2"><?= htmlspecialchars($listing['title']) ?></h1>
        <div class="text-muted mb-2"><?= htmlspecialchars($listing['address']) ?></div>
        <div class="mb-3">
          <span class="badge text-bg-warning text-dark">Capacity: <?= (int)$listing['capacity'] ?></span>
          <span class="ms-2 fw-bold">₱<?= number_format((float)$listing['price'], 2) ?></span>
        </div>
        <p class="mb-4"><?= nl2br(htmlspecialchars($listing['description'])) ?></p>

        <?php if ($mapsSrc): ?>
          <iframe
            class="map-embed"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            src="<?= $mapsSrc ?>">
          </iframe>
          <small class="text-muted d-block mt-2">Map is based on the address; shown location may be approximate.</small>
        <?php else: ?>
          <div class="alert alert-secondary">No address provided.</div>
        <?php endif; ?>
      </div>

      <div class="col-lg-5">
        <div class="p-3 border rounded-3">
          <div class="d-flex align-items-center mb-3">
            <div class="rounded-circle bg-warning-subtle me-2" style="width:38px;height:38px;"></div>
            <div>
              <div class="small text-muted">Listed by</div>
              <div class="fw-semibold">
                <?= htmlspecialchars(trim(($listing['first_name'] ?? '').' '.($listing['last_name'] ?? ''))) ?: 'Unit Owner' ?>
              </div>
            </div>
          </div>

          <a href="LoginModule" class="btn btn-dark w-100 mb-2">Login to Apply</a>
          <a href="LoginModule" class="btn btn-outline-secondary w-100">Login to Message Owner</a>
          <div class="small text-muted mt-2">You can browse without an account. To apply or chat, please log in or register.</div>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
