<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
ini_set('log_errors',1);
ini_set('error_log', __DIR__.'/php-error.log');
// property_details.php — public, full property view
@session_start();
require 'mysql_connect.php';

// ---- read id safely ----
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(404);
  echo "Invalid listing id.";
  exit;
}

// who is viewing?
$session_role     = $_SESSION['role'] ?? '';             // 'tenant', 'unit_owner', 'admin' (whatever you use)
$session_owner_id = (int)($_SESSION['owner_id'] ?? 0);
$is_admin         = ($session_role === 'admin');

// ---- fetch listing ----
// Only show to everyone if approved (is_verified=1).
// Allow the OWNER (match a.id to session owner_id) and ADMINS to view even if not approved.
$sql = "
  SELECT
    l.id, l.title, l.description, l.address, l.latitude, l.longitude,
    l.price, l.capacity, l.is_available, l.owner_id, l.is_verified, l.amenities,
    l.property_photos, l.bedroom, l.unit_sqm, l.kitchen, l.kitchen_type,
    l.gender_specific, l.pets,
    a.gcash_name, a.gcash_number, a.gcash_qr_path,
    a.paymaya_name, a.paymaya_number, a.paymaya_qr_path,
    a.bank_name, a.bank_account_name, a.bank_account_number
  FROM tblistings l
  JOIN tbadmin a ON a.id = l.owner_id
  WHERE l.id = ?
    AND l.is_archived = 0
    AND (
          l.is_verified = 1               -- approved (public)
          OR a.id = ?                     -- owner can see their own
          OR ? = 1                        -- admin can see all
        )
  LIMIT 1";
$stmt = $conn->prepare($sql);
$ownerIdParam = $session_owner_id;
$isAdminParam = $is_admin ? 1 : 0;
$stmt->bind_param("iii", $id, $ownerIdParam, $isAdminParam);
$stmt->execute();
$res     = $stmt->get_result();
$listing = $res->fetch_assoc();
$stmt->close();

// Decode property photos JSON
$property_photos = [];
if (!empty($listing['property_photos'])) {
    $property_photos = json_decode($listing['property_photos'], true) ?: [];
}

if (!$listing) {
  http_response_code(404);
  echo "Listing not found.";
  exit;
}

// extra guard (not strictly needed because the SQL already enforced it):
$is_owner = ($session_owner_id > 0 && $session_owner_id === (int)$listing['owner_id']);
if ((int)$listing['is_verified'] !== 1 && !$is_owner && !$is_admin) {
  http_response_code(404);
  echo "Listing not found.";
  exit;
}

// session flags for header/buttons
$is_logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['owner_id']);
$role         = $session_role;
$user_id      = (int)($_SESSION['user_id'] ?? 0); // For tenant chat functionality

// convenience
$lat = is_null($listing['latitude'])  ? null : (float)$listing['latitude'];
$lng = is_null($listing['longitude']) ? null : (float)$listing['longitude'];

// ---- ML Price Prediction ----
$ml_prediction = null;
$price_comparison = null;
$actual_price = (float)$listing['price'];

// Extract location from address for ML prediction
$address_parts = explode(',', $listing['address']);
$location = trim(end($address_parts)); // Get last part (city/area)

// Derive property type from title (since property_type column doesn't exist)
$title_lower = strtolower($listing['title']);
if (strpos($title_lower, 'studio') !== false) {
  $property_type = 'Studio';
} elseif (strpos($title_lower, 'apartment') !== false) {
  $property_type = 'Apartment';
} elseif (strpos($title_lower, 'condo') !== false) {
  $property_type = 'Condominium';
} elseif (strpos($title_lower, 'house') !== false || strpos($title_lower, 'boarding') !== false) {
  $property_type = 'Boarding House';
} else {
  $property_type = 'Apartment'; // default
}

// Prepare ML input
$ml_input = [
  'Capacity' => (int)($listing['capacity'] ?? 1),
  'Bedroom' => (int)($listing['bedroom'] ?? 1),
  'unit_sqm' => (float)($listing['unit_sqm'] ?? 20),
  'cap_per_bedroom' => round((int)($listing['capacity'] ?? 1) / max((int)($listing['bedroom'] ?? 1), 1), 2),
  'Type' => $property_type,
  'Kitchen' => $listing['kitchen'] ?? 'Yes',
  'Kitchen type' => $listing['kitchen_type'] ?? 'Private',
  'Gender specific' => $listing['gender_specific'] ?? 'Mixed',
  'Pets' => $listing['pets'] ?? 'Allowed',
  'Location' => $location
];

// Call ML API - Auto-detect localhost vs production
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');
$api_url = $is_localhost
  ? 'http://localhost/public_html/api/ml_suggest_price.php'
  : 'https://' . $_SERVER['HTTP_HOST'] . '/api/ml_suggest_price.php';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['inputs' => [$ml_input]]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$ml_response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($ml_response && !$curl_error) {
  $ml_data = json_decode($ml_response, true);
  if (isset($ml_data['prediction'])) {
    $ml_prediction = $ml_data['prediction'];
    $actual_price = (float)$listing['price'];

    // Calculate price difference percentage
    $diff_percent = (($actual_price - $ml_prediction) / $ml_prediction) * 100;

    // Determine price status
    if ($diff_percent <= -10) {
      $price_comparison = ['status' => 'great', 'message' => 'Great Deal!', 'diff' => $diff_percent];
    } elseif ($diff_percent <= 10) {
      $price_comparison = ['status' => 'fair', 'message' => 'Fair Price', 'diff' => $diff_percent];
    } else {
      $price_comparison = ['status' => 'high', 'message' => 'Above Market', 'diff' => $diff_percent];
    }
  }
}

// ---- Fetch Similar Properties for Comparison ----
$similar_properties = [];
$price_range_min = $actual_price * 0.7; // 30% below
$price_range_max = $actual_price * 1.3; // 30% above

$similar_sql = "
  SELECT id, title, address, price, capacity, bedroom, unit_sqm,
         property_photos, is_available
  FROM tblistings
  WHERE id != ?
    AND is_verified = 1
    AND is_archived = 0
    AND price BETWEEN ? AND ?
    AND capacity = ?
  ORDER BY ABS(price - ?) ASC
  LIMIT 3";

$similar_stmt = $conn->prepare($similar_sql);
$similar_stmt->bind_param("iddid", $id, $price_range_min, $price_range_max, $listing['capacity'], $actual_price);
$similar_stmt->execute();
$similar_res = $similar_stmt->get_result();
while ($row = $similar_res->fetch_assoc()) {
  $similar_properties[] = $row;
}
$similar_stmt->close();

/* ---------- Smart BACK target ---------- */
$allowed = [
  'DashboardT.php','DashboardUO.php','DashboardT','DashboardUO',
  'browse_listings.php','Browse.php','browse.php','index.php',
  'browse_listings','Browse','browse','index'
];

$ret = isset($_GET['ret']) ? basename($_GET['ret']) : '';

if (!$ret) {
  if ($role === 'tenant') {
    $ret = 'DashboardT.php';
  } elseif (!empty($_SESSION['owner_id'])) {
    $ret = 'DashboardUO.php';
  }
}

if (!$ret && !empty($_SERVER['HTTP_REFERER'])) {
  $ref = basename(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH));
  if (in_array($ref, $allowed, true)) $ret = $ref;
}

if (!$ret || !in_array($ret, $allowed, true)) {
  $ret = 'browse_listings';
}

$backUrl = $ret;

// Close database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($listing['title']) ?> • HanapBahay</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
    body { background:#fafafa; }
    .topbar { background:#8B4513; color:#fff; }
    .logo { height: 28px; }
    .badge-cap { background:#f1c64f; color:#222; }
    #map { width:100%; height:360px; border-radius:12px; }
    .btn-brown { background:#8B4513; color:#fff; border:none; }
    .btn-outline-brown { color:#fff; border:1px solid #fff; background:transparent; }
    .btn-outline-brown:hover { color:#8B4513; background:#fff; }
  </style>
</head>
<body>
  <!-- Top bar -->
  <nav class="topbar py-2">
    <div class="container d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center gap-2">
        <img src="Assets/Logo1.png" class="logo" alt="HanapBahay" />
      </div>
      <div class="d-flex gap-2">
        <!-- Single smart Back button -->
        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-sm btn-outline-brown">
          <i class="bi bi-arrow-left"></i> Back
        </a>

        <?php if (!$is_logged_in): ?>
          <a href="LoginModule" class="btn btn-sm btn-warning text-dark">Login / Register</a>
        <?php else: ?>
          <?php if ($role === 'tenant'): ?>
            <a href="DashboardT" class="btn btn-sm btn-outline-brown">Dashboard</a>
          <?php elseif (!empty($_SESSION['owner_id'])): ?>
            <a href="DashboardUO" class="btn btn-sm btn-outline-brown">Dashboard</a>
          <?php endif; ?>
          <a href="logout" class="btn btn-sm btn-outline-brown">Logout</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <main class="container my-4">
    <div class="row g-4">
      <!-- Left column -->
      <div class="col-lg-8">
        <h1 class="h4 mb-1"><?= htmlspecialchars($listing['title']) ?></h1>
        <div class="d-flex align-items-center gap-3 mb-2">
          <span class="badge badge-cap">Capacity: <?= (int)$listing['capacity'] ?></span>
          <strong>₱<?= number_format((float)$listing['price'], 2) ?></strong>
          <span class="text-muted">•</span>
          <span>
            <strong>Status:</strong> <?= ((int)$listing['is_available'] === 1 ? 'Available' : 'Occupied') ?>
          </span>
        </div>

        <!-- Price Comparison Button -->
        <?php if ($price_comparison && $ml_prediction): ?>
        <div class="mb-3">
          <button type="button" class="btn btn-<?= $price_comparison['status'] === 'great' ? 'success' : ($price_comparison['status'] === 'fair' ? 'info' : 'warning') ?> btn-sm" data-bs-toggle="modal" data-bs-target="#compareModal">
            <i class="bi bi-graph-up-arrow"></i> Compare Prices
            <span class="badge bg-white text-dark ms-2"><?= htmlspecialchars($price_comparison['message']) ?></span>
          </button>
        </div>
        <?php endif; ?>

        <div class="text-muted mb-3">
          <i class="bi bi-geo-alt"></i>
          <?= htmlspecialchars($listing['address']) ?>
        </div>

        <!-- Property Photos -->
        <?php if (!empty($property_photos)): ?>
        <section class="card mb-3">
          <div class="card-body">
            <h2 class="h5 mb-3"><i class="bi bi-images"></i> Property Photos</h2>
            <div id="propertyCarousel" class="carousel slide" data-bs-ride="carousel">
              <div class="carousel-indicators">
                <?php foreach ($property_photos as $idx => $photo): ?>
                  <button type="button" data-bs-target="#propertyCarousel" data-bs-slide-to="<?= $idx ?>"
                          <?= $idx === 0 ? 'class="active" aria-current="true"' : '' ?>
                          aria-label="Slide <?= $idx + 1 ?>"></button>
                <?php endforeach; ?>
              </div>
              <div class="carousel-inner" style="border-radius: 8px; overflow: hidden;">
                <?php foreach ($property_photos as $idx => $photo): ?>
                  <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?>">
                    <img src="<?= htmlspecialchars($photo) ?>" class="d-block w-100"
                         alt="Property Photo <?= $idx + 1 ?>"
                         style="height: 400px; object-fit: cover;">
                  </div>
                <?php endforeach; ?>
              </div>
              <?php if (count($property_photos) > 1): ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#propertyCarousel" data-bs-slide="prev">
                  <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                  <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#propertyCarousel" data-bs-slide="next">
                  <span class="carousel-control-next-icon" aria-hidden="true"></span>
                  <span class="visually-hidden">Next</span>
                </button>
              <?php endif; ?>
            </div>
          </div>
        </section>
        <?php endif; ?>

        <div id="map" class="mb-3"></div>

        <section class="card mb-3">
          <div class="card-body">
            <h2 class="h5">Description</h2>
            <p class="mb-0"><?= nl2br(htmlspecialchars($listing['description'] ?? '')) ?></p>
          </div>
        </section>

        <?php if (!empty($listing['amenities'])): ?>
        <section class="card">
          <div class="card-body">
            <h2 class="h5 mb-3">Amenities</h2>
            <div class="row g-2">
              <?php
              $amenities = explode(', ', $listing['amenities']);
              foreach ($amenities as $amenity):
              ?>
                <div class="col-6 col-md-4">
                  <i class="bi bi-check-circle-fill text-success"></i>
                  <span><?= htmlspecialchars(ucfirst($amenity)) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
        <?php endif; ?>
      </div>

      <!-- Right column -->
      <div class="col-lg-4">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center mb-3">
              <span class="rounded-circle bg-warning-subtle d-inline-block me-2" style="width:28px;height:28px;"></span>
              <div>
                <div class="small text-muted">Listed by</div>
                <strong>Unit Owner</strong>
              </div>
            </div>

            <?php if ($is_logged_in): ?>
              <!-- Logged-in actions -->
              <button class="btn btn-brown w-100 mb-2" data-bs-toggle="modal" data-bs-target="#rentModal">
                Apply / Reserve
              </button>

              <button class="btn btn-outline-secondary w-100" id="messageOwnerBtn"
                      data-listing-id="<?= (int)$listing['id'] ?>"
                      data-owner-id="<?= (int)$listing['owner_id'] ?>">
                Message Owner
              </button>

              <p class="text-muted small mt-2 mb-0">You’re logged in. You can apply or message the owner directly.</p>
            <?php else: ?>
              <!-- Guest actions -->
              <a href="LoginModule" class="btn btn-brown w-100 mb-2">Login to Apply</a>
              <a href="LoginModule" class="btn btn-outline-secondary w-100">Login to Message Owner</a>
              <p class="text-muted small mt-2 mb-0">You can browse without an account. To apply or chat, please log in or register.</p>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- ===== Reservation Modal (FULLY UPDATED) ===== -->
  <div class="modal fade" id="rentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <!-- enctype added for image upload -->
        <form id="reservationForm" action="submit_rental" method="POST" enctype="multipart/form-data">
          <div class="modal-header">
            <h5 class="modal-title">Reserve: <?= htmlspecialchars($listing['title']) ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="mb-1"><strong>Address:</strong> <?= htmlspecialchars($listing['address']) ?></p>
            <p class="mb-3"><strong>Price:</strong> ₱<?= number_format((float)$listing['price'], 2) ?></p>

            <input type="hidden" name="listing_id" value="<?= (int)$listing['id'] ?>">
            <input type="hidden" name="price" value="<?= (float)$listing['price'] ?>">

            <!-- Payment Method -->
            <div class="mb-3">
              <label class="form-label">Payment Method</label>
              <select class="form-select" name="payment_method" id="payment_method" required>
                <option value="">Select...</option>
                <option value="gcash">GCash</option>
                <option value="paymaya">PayMaya</option>
                <option value="bank_transfer">Bank Transfer</option>
              </select>
            </div>

            <!-- Payment Option -->
            <div class="mb-3">
              <label class="form-label">Payment Option</label>
              <select class="form-select" name="payment_option" id="paymentOption" required>
                <option value="">Choose...</option>
                <option value="full">Full Payment</option>
                <option value="half">50% Downpayment</option>
              </select>
            </div>

            <p class="mb-3"><strong>Amount to Pay:</strong> <span id="calculatedAmount">₱0.00</span></p>

            <!-- ===== GCash Payment Box ===== -->
            <div id="gcashBox" class="payment-box" style="display:none; margin-top:12px;">
              <hr class="my-3">
              <div class="text-center p-3" style="background: #f8f9fa; border-radius: 8px;">
                <h6 class="mb-3"><i class="bi bi-wallet2 text-primary"></i> GCash Payment</h6>

                <?php if (!empty($listing['gcash_qr_path'])): ?>
                  <div class="mb-3">
                    <img src="<?= htmlspecialchars($listing['gcash_qr_path']) ?>"
                         alt="GCash QR Code" class="img-fluid"
                         style="max-width:280px; border:2px solid #007bff; border-radius:12px; padding:10px; background:#fff; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                  </div>
                  <div class="alert alert-info small mb-2">
                    <i class="bi bi-info-circle"></i> Open your GCash app and scan this QR code
                  </div>
                <?php else: ?>
                  <div class="alert alert-warning small mb-2">
                    <i class="bi bi-exclamation-triangle"></i> Owner hasn't set up GCash QR code yet
                  </div>
                <?php endif; ?>

                <div class="text-start mt-3" style="font-size:0.9rem;">
                  <div class="mb-1"><strong>Pay to:</strong> <?= htmlspecialchars($listing['gcash_name'] ?? 'N/A') ?></div>
                  <div class="mb-2"><strong>Number:</strong> <?= htmlspecialchars($listing['gcash_number'] ?? 'N/A') ?></div>
                </div>
              </div>

              <div style="margin-top:16px;">
                <label class="form-label fw-bold">
                  <i class="bi bi-upload"></i> Upload Payment Screenshot
                </label>
                <input type="file" name="receipt_image" id="receipt_image_gcash" accept="image/*" class="form-control receipt-input" required>
                <small class="text-muted d-block mt-1">Screenshot your successful GCash payment (JPG/PNG, max 5 MB)</small>
                <div id="receiptPreviewGcash" class="receipt-preview" style="display:none;margin-top:12px; text-align:center;">
                  <p class="small text-muted mb-2">Preview:</p>
                  <img class="receipt-preview-img" src="" alt="Receipt preview"
                       style="max-width:100%; max-height:200px; border-radius:8px; border:1px solid #ddd;">
                </div>
              </div>
            </div>
            <!-- ===== /GCash Box ===== -->

            <!-- ===== PayMaya Payment Box ===== -->
            <div id="paymayaBox" class="payment-box" style="display:none; margin-top:12px;">
              <hr class="my-3">
              <div class="text-center p-3" style="background: #f8f9fa; border-radius: 8px;">
                <h6 class="mb-3"><i class="bi bi-wallet text-success"></i> PayMaya Payment</h6>

                <?php if (!empty($listing['paymaya_qr_path'])): ?>
                  <div class="mb-3">
                    <img src="<?= htmlspecialchars($listing['paymaya_qr_path']) ?>"
                         alt="PayMaya QR Code" class="img-fluid"
                         style="max-width:280px; border:2px solid #28a745; border-radius:12px; padding:10px; background:#fff; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                  </div>
                  <div class="alert alert-success small mb-2">
                    <i class="bi bi-info-circle"></i> Open your PayMaya app and scan this QR code
                  </div>
                <?php else: ?>
                  <div class="alert alert-warning small mb-2">
                    <i class="bi bi-exclamation-triangle"></i> Owner hasn't set up PayMaya QR code yet
                  </div>
                <?php endif; ?>

                <div class="text-start mt-3" style="font-size:0.9rem;">
                  <div class="mb-1"><strong>Pay to:</strong> <?= htmlspecialchars($listing['paymaya_name'] ?? 'N/A') ?></div>
                  <div class="mb-2"><strong>Number:</strong> <?= htmlspecialchars($listing['paymaya_number'] ?? 'N/A') ?></div>
                </div>
              </div>

              <div style="margin-top:16px;">
                <label class="form-label fw-bold">
                  <i class="bi bi-upload"></i> Upload Payment Screenshot
                </label>
                <input type="file" name="receipt_image" id="receipt_image_paymaya" accept="image/*" class="form-control receipt-input" required>
                <small class="text-muted d-block mt-1">Screenshot your successful PayMaya payment (JPG/PNG, max 5 MB)</small>
                <div id="receiptPreviewPaymaya" class="receipt-preview" style="display:none;margin-top:12px; text-align:center;">
                  <p class="small text-muted mb-2">Preview:</p>
                  <img class="receipt-preview-img" src="" alt="Receipt preview"
                       style="max-width:100%; max-height:200px; border-radius:8px; border:1px solid #ddd;">
                </div>
              </div>
            </div>
            <!-- ===== /PayMaya Box ===== -->

            <!-- ===== Bank Transfer Box ===== -->
            <div id="bankBox" class="payment-box" style="display:none; margin-top:12px;">
              <hr class="my-3">
              <div class="p-3" style="background: #f8f9fa; border-radius: 8px;">
                <h6 class="mb-3"><i class="bi bi-bank text-info"></i> Bank Transfer Details</h6>

                <?php if (!empty($listing['bank_name']) && !empty($listing['bank_account_number'])): ?>
                  <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle"></i> Transfer the amount to this bank account
                  </div>
                  <table class="table table-sm table-borderless mb-0">
                    <tr>
                      <td width="120"><strong>Bank Name:</strong></td>
                      <td><?= htmlspecialchars($listing['bank_name'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                      <td><strong>Account Name:</strong></td>
                      <td><?= htmlspecialchars($listing['bank_account_name'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                      <td><strong>Account Number:</strong></td>
                      <td><code><?= htmlspecialchars($listing['bank_account_number'] ?? 'N/A') ?></code></td>
                    </tr>
                  </table>
                <?php else: ?>
                  <div class="alert alert-warning small mb-2">
                    <i class="bi bi-exclamation-triangle"></i> Owner hasn't set up bank transfer details yet
                  </div>
                <?php endif; ?>
              </div>

              <div style="margin-top:16px;">
                <label class="form-label fw-bold">
                  <i class="bi bi-upload"></i> Upload Bank Transfer Receipt
                </label>
                <input type="file" name="receipt_image" id="receipt_image_bank" accept="image/*" class="form-control receipt-input" required>
                <small class="text-muted d-block mt-1">Upload proof of bank transfer (JPG/PNG, max 5 MB)</small>
                <div id="receiptPreviewBank" class="receipt-preview" style="display:none;margin-top:12px; text-align:center;">
                  <p class="small text-muted mb-2">Preview:</p>
                  <img class="receipt-preview-img" src="" alt="Receipt preview"
                       style="max-width:100%; max-height:200px; border-radius:8px; border:1px solid #ddd;">
                </div>
              </div>
            </div>
            <!-- ===== /Bank Transfer Box ===== -->

          </div>
          <div class="modal-footer">
            <button class="btn btn-brown" type="submit">Confirm Reservation</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- ===== /Reservation Modal ===== -->

  <!-- ===== Price Comparison Modal ===== -->
  <div class="modal fade" id="compareModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header" style="background: #8B4513; color: white;">
          <h5 class="modal-title"><i class="bi bi-graph-up-arrow"></i> Price Comparison</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info mb-3">
            <i class="bi bi-info-circle"></i> <strong>AI Price Analysis:</strong>
            This property is <strong><?= htmlspecialchars($price_comparison['message']) ?></strong>
            (ML Predicted: ₱<?= number_format($ml_prediction, 2) ?>)
          </div>

          <h6 class="mb-3">Compare with Similar Properties</h6>

          <div class="row g-3">
            <!-- Current Property (Highlighted) -->
            <div class="col-md-6 col-lg-3">
              <div class="card h-100 border-primary border-3">
                <div class="position-relative">
                  <?php
                  $photos = !empty($listing['property_photos']) ? json_decode($listing['property_photos'], true) : [];
                  $main_photo = !empty($photos[0]) ? $photos[0] : 'https://via.placeholder.com/300x200?text=No+Image';
                  ?>
                  <img src="<?= htmlspecialchars($main_photo) ?>" class="card-img-top" alt="Current Property" style="height: 150px; object-fit: cover;">
                  <span class="position-absolute top-0 start-0 m-2 badge bg-primary">Current Property</span>
                  <span class="position-absolute top-0 end-0 m-2 badge bg-<?= $price_comparison['status'] === 'great' ? 'success' : ($price_comparison['status'] === 'fair' ? 'info' : 'warning') ?>">
                    <?= htmlspecialchars($price_comparison['message']) ?>
                  </span>
                </div>
                <div class="card-body">
                  <h6 class="card-title text-truncate" title="<?= htmlspecialchars($listing['title']) ?>">
                    <?= htmlspecialchars($listing['title']) ?>
                  </h6>
                  <p class="card-text small text-muted mb-2">
                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars(substr($listing['address'], 0, 40)) ?>...
                  </p>
                  <div class="mb-2">
                    <strong class="text-primary fs-5">₱<?= number_format((float)$listing['price'], 2) ?></strong>
                  </div>
                  <div class="small text-muted">
                    <div><i class="bi bi-people"></i> <?= (int)$listing['capacity'] ?> capacity</div>
                    <div><i class="bi bi-door-closed"></i> <?= (int)($listing['bedroom'] ?? 1) ?> bedroom</div>
                    <div><i class="bi bi-rulers"></i> <?= number_format((float)($listing['unit_sqm'] ?? 20), 1) ?> sqm</div>
                  </div>
                  <div class="mt-2 small">
                    <div class="text-muted">ML Predicted:</div>
                    <strong>₱<?= number_format($ml_prediction, 2) ?></strong>
                    <span class="badge bg-secondary ms-1">
                      <?= $price_comparison['diff'] > 0 ? '+' : '' ?><?= number_format($price_comparison['diff'], 1) ?>%
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Similar Properties -->
            <?php if (!empty($similar_properties)): ?>
              <?php foreach ($similar_properties as $prop): ?>
                <div class="col-md-6 col-lg-3">
                  <div class="card h-100">
                    <div class="position-relative">
                      <?php
                      $prop_photos = !empty($prop['property_photos']) ? json_decode($prop['property_photos'], true) : [];
                      $prop_photo = !empty($prop_photos[0]) ? $prop_photos[0] : 'https://via.placeholder.com/300x200?text=No+Image';
                      ?>
                      <img src="<?= htmlspecialchars($prop_photo) ?>" class="card-img-top" alt="Similar Property" style="height: 150px; object-fit: cover;">
                      <span class="position-absolute top-0 end-0 m-2 badge bg-<?= ((int)$prop['is_available'] === 1) ? 'success' : 'secondary' ?>">
                        <?= ((int)$prop['is_available'] === 1) ? 'Available' : 'Occupied' ?>
                      </span>
                    </div>
                    <div class="card-body">
                      <h6 class="card-title text-truncate" title="<?= htmlspecialchars($prop['title']) ?>">
                        <?= htmlspecialchars($prop['title']) ?>
                      </h6>
                      <p class="card-text small text-muted mb-2">
                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars(substr($prop['address'], 0, 40)) ?>...
                      </p>
                      <div class="mb-2">
                        <strong class="fs-5">₱<?= number_format((float)$prop['price'], 2) ?></strong>
                      </div>
                      <div class="small text-muted">
                        <div><i class="bi bi-people"></i> <?= (int)$prop['capacity'] ?> capacity</div>
                        <div><i class="bi bi-door-closed"></i> <?= (int)($prop['bedroom'] ?? 1) ?> bedroom</div>
                        <div><i class="bi bi-rulers"></i> <?= number_format((float)($prop['unit_sqm'] ?? 20), 1) ?> sqm</div>
                      </div>
                      <a href="property_details.php?id=<?= (int)$prop['id'] ?>" class="btn btn-sm btn-outline-primary mt-2 w-100">
                        View Details
                      </a>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="col-12">
                <div class="alert alert-secondary mb-0">
                  <i class="bi bi-info-circle"></i> No similar properties found in this price range.
                </div>
              </div>
            <?php endif; ?>
          </div>

          <div class="mt-4">
            <h6>Legend:</h6>
            <div class="d-flex gap-3 flex-wrap">
              <span><span class="badge bg-success">Great Deal!</span> 10%+ below market</span>
              <span><span class="badge bg-info">Fair Price</span> Within ±10% of market</span>
              <span><span class="badge bg-warning">Above Market</span> 10%+ above market</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <small class="text-muted me-auto"><i class="bi bi-lightbulb"></i> AI predictions are estimates based on property features and market data</small>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  <!-- ===== /Price Comparison Modal ===== -->

  <!-- ============ FLOATING CHAT WIDGET ============ -->
  <?php if ($is_logged_in && $role === 'tenant'): ?>
  <div id="hb-chat-widget" class="hb-chat-widget" style="display: none;">
    <div id="hb-chat-header" class="hb-chat-header-bar">
      <span><i class="bi bi-chat-dots"></i> Chat with Owner</span>
      <button id="hb-toggle-btn" class="hb-btn-ghost">_</button>
    </div>
    <div id="hb-chat-body-container" class="hb-chat-body-container">
      <div id="hb-chat" class="hb-chat-container">
        <div class="hb-chat-header">
          <div class="hb-chat-title">
            <span class="hb-dot"></span>
            <strong id="hb-counterparty">Owner</strong>
          </div>
        </div>
        <div id="hb-chat-body" class="hb-chat-body">
          <div id="hb-history-sentinel" class="hb-history-sentinel">
            Click "Message Owner" to start chatting...
          </div>
          <div id="hb-messages" class="hb-messages" aria-live="polite"></div>

          <!-- Quick Replies -->
          <div id="hb-quick-replies" class="hb-quick-replies" aria-label="Suggested questions"></div>
        </div>
        <form id="hb-send-form" class="hb-chat-input" autocomplete="off">
          <textarea id="hb-input" rows="1" placeholder="Type a message…" required disabled></textarea>
          <button id="hb-send" type="submit" class="hb-btn" disabled>Send</button>
        </form>
      </div>
    </div>
  </div>

  <style>
    .hb-chat-widget {
      position: fixed;
      bottom: 20px;
      right: 20px;
      width: 350px;
      max-height: 500px;
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.15);
      z-index: 1050;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    .hb-chat-widget.collapsed .hb-chat-body-container { display: none; }
    .hb-chat-header-bar {
      background: #8B4513;
      color: white;
      padding: 12px 16px;
      border-radius: 12px 12px 0 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
      user-select: none;
    }
    .hb-btn-ghost {
      background: none;
      border: none;
      color: white;
      cursor: pointer;
      font-size: 18px;
      padding: 0;
      width: 24px;
      height: 24px;
    }
    .hb-chat-body-container {
      display: flex;
      flex-direction: column;
      height: 400px;
    }
    .hb-chat-container {
      display: flex;
      flex-direction: column;
      height: 100%;
    }
    .hb-chat-header {
      padding: 12px 16px;
      border-bottom: 1px solid #eee;
      background: #f8f9fa;
    }
    .hb-chat-title {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .hb-dot {
      width: 8px;
      height: 8px;
      background: #28a745;
      border-radius: 50%;
    }
    .hb-chat-body {
      flex: 1;
      overflow-y: auto;
      padding: 12px;
      display: flex;
      flex-direction: column;
    }
    .hb-history-sentinel {
      text-align: center;
      color: #666;
      font-size: 14px;
      margin-bottom: 12px;
    }
    .hb-messages {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .hb-message {
      max-width: 80%;
      padding: 8px 12px;
      border-radius: 12px;
      word-wrap: break-word;
    }
    .hb-message-me {
      background: #8B4513;
      color: white;
      align-self: flex-end;
      border-bottom-right-radius: 4px;
    }
    .hb-message-them {
      background: #e9ecef;
      color: #333;
      align-self: flex-start;
      border-bottom-left-radius: 4px;
    }
    .hb-quick-replies {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 8px;
    }
    .hb-quick-reply-btn {
      background: #f8f9fa;
      border: 1px solid #dee2e6;
      border-radius: 16px;
      padding: 4px 12px;
      font-size: 12px;
      cursor: pointer;
      transition: all 0.2s;
    }
    .hb-quick-reply-btn:hover {
      background: #8B4513;
      color: white;
      border-color: #8B4513;
    }
    .hb-chat-input {
      border-top: 1px solid #eee;
      padding: 12px;
      display: flex;
      gap: 8px;
      align-items: flex-end;
    }
    .hb-chat-input textarea {
      flex: 1;
      border: 1px solid #ddd;
      border-radius: 20px;
      padding: 8px 12px;
      resize: none;
      max-height: 80px;
      font-size: 14px;
    }
    .hb-btn {
      background: #8B4513;
      color: white;
      border: none;
      border-radius: 20px;
      padding: 8px 16px;
      cursor: pointer;
      font-size: 14px;
    }
    .hb-btn:disabled {
      background: #ccc;
      cursor: not-allowed;
    }
  </style>
  <?php endif; ?>
  <!-- ============================================== -->

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://js.pusher.com/8.2/pusher.min.js"></script>

  <script>
    // Payment calculation + Payment method toggles + receipt preview
    document.addEventListener('DOMContentLoaded', function () {
      const price = <?= json_encode((float)$listing['price']) ?>;
      const methodSel = document.getElementById('payment_method');
      const optionSel = document.getElementById('paymentOption');
      const amountEl  = document.getElementById('calculatedAmount');

      // Payment boxes
      const gcashBox = document.getElementById('gcashBox');
      const paymayaBox = document.getElementById('paymayaBox');
      const bankBox = document.getElementById('bankBox');

      // Receipt inputs
      const receiptInputs = {
        gcash: document.getElementById('receipt_image_gcash'),
        paymaya: document.getElementById('receipt_image_paymaya'),
        bank: document.getElementById('receipt_image_bank')
      };

      // Calculate amount based on payment option
      function recalc() {
        const opt = optionSel?.value || '';
        const amt = (opt === 'half') ? (price / 2) : (opt === 'full' ? price : 0);
        amountEl.textContent = '₱' + amt.toLocaleString(undefined, { minimumFractionDigits: 2 });
      }

      // Toggle payment method sections
      function togglePaymentMethod() {
        const method = (methodSel?.value || '').toLowerCase();

        // Hide all payment boxes
        gcashBox.style.display = 'none';
        paymayaBox.style.display = 'none';
        bankBox.style.display = 'none';

        // Disable all receipt inputs
        Object.values(receiptInputs).forEach(input => {
          if (input) {
            input.required = false;
            input.disabled = true;
            input.name = '';
          }
        });

        // Show selected payment box and enable its receipt input
        if (method === 'gcash') {
          gcashBox.style.display = 'block';
          if (receiptInputs.gcash) {
            receiptInputs.gcash.required = true;
            receiptInputs.gcash.disabled = false;
            receiptInputs.gcash.name = 'receipt_image';
          }
        } else if (method === 'paymaya') {
          paymayaBox.style.display = 'block';
          if (receiptInputs.paymaya) {
            receiptInputs.paymaya.required = true;
            receiptInputs.paymaya.disabled = false;
            receiptInputs.paymaya.name = 'receipt_image';
          }
        } else if (method === 'bank_transfer') {
          bankBox.style.display = 'block';
          if (receiptInputs.bank) {
            receiptInputs.bank.required = true;
            receiptInputs.bank.disabled = false;
            receiptInputs.bank.name = 'receipt_image';
          }
        }
      }

      methodSel?.addEventListener('change', togglePaymentMethod);
      optionSel?.addEventListener('change', recalc);

      // Initialize defaults
      togglePaymentMethod();
      recalc();

      // Receipt preview handlers (for all payment methods)
      document.querySelectorAll('.receipt-input').forEach(input => {
        input.addEventListener('change', function() {
          const preview = this.parentElement.querySelector('.receipt-preview');
          const previewImg = preview?.querySelector('.receipt-preview-img');
          const f = this.files?.[0];

          if (!f) {
            if (preview) preview.style.display = 'none';
            return;
          }

          // Validate file type
          if (!/^image\/(jpeg|png|webp|gif)$/i.test(f.type)) {
            alert('Please upload an image (JPG/PNG/WebP/GIF).');
            this.value = '';
            if (preview) preview.style.display = 'none';
            return;
          }

          // Validate file size (5MB max)
          if (f.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5 MB.');
            this.value = '';
            if (preview) preview.style.display = 'none';
            return;
          }

          // Show preview
          if (preview && previewImg) {
            const url = URL.createObjectURL(f);
            previewImg.src = url;
            preview.style.display = 'block';
          }
        });
      });

      // Form validation and AJAX submission
      const rentForm = document.querySelector('#reservationForm');
      rentForm?.addEventListener('submit', async function(e) {
        e.preventDefault();

        const method = methodSel?.value || '';

        if (!method) {
          alert('Please select a payment method.');
          return false;
        }

        const activeInput = Object.entries(receiptInputs).find(([key, input]) =>
          input && !input.disabled && input.required
        )?.[1];

        if (activeInput && !activeInput.files?.[0]) {
          alert('Please upload your payment receipt/screenshot.');
          return false;
        }

        // Submit form via AJAX with FormData (for file upload)
        const submitBtn = rentForm.querySelector('button[type="submit"]');
        const originalText = submitBtn?.textContent;
        if (submitBtn) submitBtn.disabled = true;
        if (submitBtn) submitBtn.textContent = 'Submitting...';

        try {
          const formData = new FormData(rentForm);
          const response = await fetch(rentForm.action, {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          const contentType = response.headers.get('content-type');
          let result;

          if (contentType && contentType.includes('application/json')) {
            result = await response.json();
          } else {
            result = await response.text();
          }

          // Check if successful
          if (response.ok && (result.success === true || !String(result).toLowerCase().includes('error'))) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('rentModal'));
            modal?.hide();

            // Show success message
            const successMsg = result.message || 'Reservation submitted successfully! The owner will review your payment.';
            alert('✅ ' + successMsg);

            // Reset form
            rentForm.reset();

            // Redirect to application status page
            setTimeout(() => {
              window.location.href = 'rental_request';
            }, 1500);
          } else {
            const errorMsg = result.error || 'Failed to submit reservation. Please try again.';
            alert('❌ ' + errorMsg);
            if (submitBtn) submitBtn.disabled = false;
            if (submitBtn) submitBtn.textContent = originalText;
          }
        } catch (error) {
          console.error('Submission error:', error);
          alert('❌ Network error. Please check your connection and try again.');
          if (submitBtn) submitBtn.disabled = false;
          if (submitBtn) submitBtn.textContent = originalText;
        }
      });
    });
  </script>

  <?php if ($is_logged_in && $role === 'tenant'): ?>
  <script>
    // Chat functionality
    document.addEventListener('DOMContentLoaded', function() {
      const messageOwnerBtn = document.getElementById('messageOwnerBtn');
      const chatWidget = document.getElementById('hb-chat-widget');
      const toggleBtn = document.getElementById('hb-toggle-btn');
      const chatHeader = document.getElementById('hb-chat-header');
      const messagesContainer = document.getElementById('hb-messages');
      const messageInput = document.getElementById('hb-input');
      const sendButton = document.getElementById('hb-send');
      const sendForm = document.getElementById('hb-send-form');
      const quickRepliesContainer = document.getElementById('hb-quick-replies');
      const historySentinel = document.getElementById('hb-history-sentinel');

      let currentThreadId = 0;
      let pusher = null;
      let currentChannel = null;
      let quickRepliesLoaded = false;

      // Toggle chat widget
      function toggleWidget() {
        chatWidget.classList.toggle('collapsed');
        toggleBtn.textContent = chatWidget.classList.contains('collapsed') ? '▴' : '_';
      }

      chatHeader.addEventListener('click', (e) => {
        if (e.target !== toggleBtn) toggleWidget();
      });
      toggleBtn.addEventListener('click', toggleWidget);

      // Message Owner button click
      if (messageOwnerBtn) {
        messageOwnerBtn.addEventListener('click', async function(e) {
          e.preventDefault();
        const listingId = this.dataset.listingId;
        const ownerId = this.dataset.ownerId;

        try {
          // Show and expand chat widget
          chatWidget.style.display = 'block';
          chatWidget.classList.remove('collapsed');
          toggleBtn.textContent = '_';

          // Start or get existing chat
          const response = await fetch('start_chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `listing_id=${listingId}&ajax=1`
          });

          if (!response.ok) throw new Error('Failed to start chat');

          const data = await response.json();
          if (data.thread_id) {
            currentThreadId = data.thread_id;
            initializeChat(currentThreadId);
          } else {
            throw new Error(data.error || 'Unknown error');
          }
        } catch (error) {
          console.error('Error starting chat:', error);
          alert('Failed to start chat. Please try again.');
        }
        });
      }

      // Initialize chat for a thread
      function initializeChat(threadId) {
        currentThreadId = threadId;

        // Enable input
        messageInput.disabled = false;
        sendButton.disabled = false;

        // Update UI
        historySentinel.textContent = 'Loading messages...';

        // Load messages
        loadMessages();

        // Load quick replies if not loaded
        if (!quickRepliesLoaded) {
          loadQuickReplies();
          quickRepliesLoaded = true;
        }

        // Setup Pusher
        setupPusher();
      }

      // Load messages
      async function loadMessages() {
        try {
          const response = await fetch(`api/chat/fetch_messages.php?thread_id=${currentThreadId}`);
          const data = await response.json();

          if (data.ok) {
            messagesContainer.innerHTML = '';
            historySentinel.textContent = '';

            data.messages.forEach(msg => {
              displayMessage(msg);
            });

            scrollToBottom();
          }
        } catch (error) {
          console.error('Error loading messages:', error);
          historySentinel.textContent = 'Error loading messages';
        }
      }

      // Load quick replies
      async function loadQuickReplies() {
        try {
          const response = await fetch('api/chat/get_quick_replies.php');
          const data = await response.json();

          if (data.ok && data.quick_replies) {
            quickRepliesContainer.innerHTML = '';
            data.quick_replies.forEach(reply => {
              const btn = document.createElement('button');
              btn.className = 'hb-quick-reply-btn';
              btn.textContent = reply.message;
              btn.onclick = () => {
                messageInput.value = reply.message;
                messageInput.focus();
              };
              quickRepliesContainer.appendChild(btn);
            });
          }
        } catch (error) {
          console.error('Error loading quick replies:', error);
        }
      }

      // Setup Pusher for real-time messages
      function setupPusher() {
        if (pusher) {
          if (currentChannel) {
            pusher.unsubscribe(`thread-${currentThreadId}`);
          }
        } else {
          pusher = new Pusher('c9a924289093535f51f9', {
            cluster: 'ap1',
            encrypted: true
          });
        }

        currentChannel = pusher.subscribe(`thread-${currentThreadId}`);
        currentChannel.bind('new-message', function(data) {
          displayMessage(data);
          scrollToBottom();
        });
      }

      // Display a message
      function displayMessage(message) {
        const div = document.createElement('div');
        div.className = `hb-message ${parseInt(message.sender_id) === <?= $user_id ?> ? 'hb-message-me' : 'hb-message-them'}`;
        div.textContent = message.body;
        messagesContainer.appendChild(div);
      }

      // Send message
      sendForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const messageText = messageInput.value.trim();
        if (!messageText || !currentThreadId) return;

        try {
          const response = await fetch('api/chat/post_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `thread_id=${currentThreadId}&body=${encodeURIComponent(messageText)}`
          });

          if (response.ok) {
            messageInput.value = '';
            // Message will be displayed via Pusher
          } else {
            throw new Error('Failed to send message');
          }
        } catch (error) {
          console.error('Error sending message:', error);
          alert('Failed to send message. Please try again.');
        }
      });

      // Scroll to bottom
      function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
      }

      // Auto-resize textarea
      messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 80) + 'px';
      });
    });
  </script>
  <?php endif; ?>

  <script>
    // Google Map using stored lat/lng. Wheel zoom without Ctrl (gestureHandling: 'greedy')
    function initMap(){
      const hasCoords = <?= ($lat !== null && $lng !== null) ? 'true' : 'false' ?>;
      const defaultCenter = { lat: 14.5995, lng: 120.9842 }; // Manila

      const center = hasCoords ? { lat: <?= $lat ?? 'null' ?>, lng: <?= $lng ?? 'null' ?> } : defaultCenter;

      const map = new google.maps.Map(document.getElementById('map'), {
        center,
        zoom: hasCoords ? 15 : 12,
        mapTypeControl: false,
        streetViewControl: true,
        fullscreenControl: true,
        clickableIcons: false,
        gestureHandling: 'greedy' // allow wheel zoom without Ctrl
      });

      if (hasCoords) {
        const marker = new google.maps.Marker({
          map,
          position: center,
          title: <?= json_encode($listing['title']) ?>,
          icon: {
            url: (<?= (int)$listing['is_available'] ?> === 1)
              ? "http://maps.google.com/mapfiles/ms/icons/green-dot.png"
              : "http://maps.google.com/mapfiles/ms/icons/red-dot.png"
          }
        });

        const info = new google.maps.InfoWindow({
          content: `
            <div style="max-width:220px;">
              <h6 class="mb-1"><?= htmlspecialchars($listing['title']) ?></h6>
              <p class="mb-1"><strong>Address:</strong> <?= htmlspecialchars($listing['address']) ?></p>
              <p class="mb-1"><strong>Price:</strong> ₱<?= number_format((float)$listing['price'], 2) ?></p>
              <p class="mb-0"><strong>Status:</strong> <?= ((int)$listing['is_available'] === 1 ? 'Available' : 'Occupied') ?></p>
            </div>
          `
        });
        marker.addEventListener('click', () => info.open(map, marker));
      }
    }
  </script>
  <script src="https://maps.googleapis.com/maps/api/js?key=<?= urlencode('AIzaSyCrKcnAX9KOdNp_TNHwWwzbLSQodgYqgnU') ?>&callback=initMap" async defer></script>
</body>
</html>
