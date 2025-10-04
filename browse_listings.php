<?php
// browse_listings.php — Public page (no login required)
require 'mysql_connect.php';

// Read filters safely
$q         = isset($_GET['q']) ? trim($_GET['q']) : '';
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)str_replace(',', '', $_GET['min_price']) : null;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)str_replace(',', '', $_GET['max_price']) : null;
$min_cap   = isset($_GET['min_cap'])   && $_GET['min_cap']   !== '' ? (int)$_GET['min_cap'] : null;
$amenities_filter = isset($_GET['amenities']) && is_array($_GET['amenities']) ? $_GET['amenities'] : [];

$sort   = $_GET['sort'] ?? 'newest';  // newest | price_asc | price_desc | capacity_desc
$page   = max(1, (int)($_GET['page'] ?? 1));
$pp     = 9;                           // per-page
$offset = ($page - 1) * $pp;

// Whitelist order by
switch ($sort) {
  case 'price_asc':      $order_by = 'price ASC, id DESC'; break;
  case 'price_desc':     $order_by = 'price DESC, id DESC'; break;
  case 'capacity_desc':  $order_by = 'capacity DESC, id DESC'; break;
  default:               $order_by = 'id DESC'; // newest
}

// WHERE parts
$where = ["is_archived = 0"];
if ($q !== '') {
  $q_esc = $conn->real_escape_string($q);
  $like  = "%{$q_esc}%";
  $where[] = "(title LIKE '{$like}' OR description LIKE '{$like}' OR address LIKE '{$like}')";
}

// Amenities filter: each selected amenity must be in the amenities column
if (!empty($amenities_filter)) {
  foreach ($amenities_filter as $amenity) {
    $amenity_esc = $conn->real_escape_string(trim($amenity));
    // Match amenity in comma-separated list (amenities stored as "wifi, parking, aircon")
    $where[] = "(amenities LIKE '%{$amenity_esc}%')";
  }
}

$where_sql = implode(" AND ", $where);

// Numeric filters via prepared params
$types = '';
$params = [];
if (!is_null($min_price)) { $types .= 'd'; $params[] = $min_price; $where_sql .= " AND price >= ?"; }
if (!is_null($max_price)) { $types .= 'd'; $params[] = $max_price; $where_sql .= " AND price <= ?"; }
if (!is_null($min_cap))   { $types .= 'i'; $params[] = $min_cap;   $where_sql .= " AND capacity >= ?"; }

// ---------- COUNT for pagination ----------
$count_sql = "SELECT COUNT(*) FROM tblistings WHERE {$where_sql}";
$count_stmt = $conn->prepare($count_sql);
if (!$count_stmt) { die('Prepare failed (count).'); }
$cnt = strlen($types);
if ($cnt === 1)       { $count_stmt->bind_param($types, $params[0]); }
elseif ($cnt === 2)   { $count_stmt->bind_param($types, $params[0], $params[1]); }
elseif ($cnt === 3)   { $count_stmt->bind_param($types, $params[0], $params[1], $params[2]); }
$count_stmt->execute();
$count_stmt->store_result();
$count_stmt->bind_result($total_rows);
$count_stmt->fetch();
$count_stmt->free_result();
$count_stmt->close();

$total_pages = max(1, (int)ceil($total_rows / $pp));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $pp; }

// ---------- MAIN query ----------
$sql = "SELECT id, title, description, address, price, capacity, property_photos, amenities
        FROM tblistings
        WHERE {$where_sql}
        ORDER BY {$order_by}
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
if (!$stmt) { die('Prepare failed (main).'); }

// bind (numeric filters) + offset/limit
$types_main  = $types . 'ii';
$params_main = $params; $params_main[] = $offset; $params_main[] = $pp;
$cntm = strlen($types_main);
if ($cntm === 2)      { $stmt->bind_param($types_main, $params_main[0], $params_main[1]); }
elseif ($cntm === 3)  { $stmt->bind_param($types_main, $params_main[0], $params_main[1], $params_main[2]); }
elseif ($cntm === 4)  { $stmt->bind_param($types_main, $params_main[0], $params_main[1], $params_main[2], $params_main[3]); }
elseif ($cntm === 5)  { $stmt->bind_param($types_main, $params_main[0], $params_main[1], $params_main[2], $params_main[3], $params_main[4]); }

$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $title, $description, $address, $price, $capacity, $property_photos, $amenities);

$listings = [];
while ($stmt->fetch()) {
  $photos_array = [];
  if (!empty($property_photos)) {
    $photos_array = json_decode($property_photos, true) ?: [];
  }
  $amenities_array = [];
  if (!empty($amenities)) {
    $amenities_array = array_map('trim', explode(',', $amenities));
  }
  $listings[] = compact('id','title','description','address','price','capacity','photos_array','amenities_array');
}
$stmt->free_result();
$stmt->close();

// helpers
function build_qs($overrides = []) {
  $qs = $_GET;
  unset($qs['page']);
  foreach ($overrides as $k=>$v) { $qs[$k] = $v; }
  return htmlspecialchars(http_build_query($qs));
}

$ALL_AMENITIES = [
  'wifi' => 'Wi-Fi', 'parking' => 'Parking', 'aircon' => 'Air Conditioning',
  'kitchen' => 'Kitchen', 'laundry' => 'Laundry', 'furnished' => 'Furnished',
  'elevator' => 'Elevator', 'security' => 'Security/CCTV', 'balcony' => 'Balcony',
  'gym' => 'Gym', 'pool' => 'Pool', 'pet_friendly' => 'Pet Friendly',
  'bathroom' => 'Bathroom', 'sink' => 'Sink',
  'electricity' => 'Electricity (Submeter)', 'water' => 'Water (Submeter)'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Browse Listings • HanapBahay</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link rel="stylesheet" href="darkmode.css" />
  <style>
    body { background:#f7f7fb; }
    .topbar { position: sticky; top:0; z-index: 1000; background: #8B4513; color: #fff; }
    .logo { height: 42px; }
    .card-listing { border: 1px solid #eee; }
    .price { font-weight: 700; font-size: 1.1rem; }
    .badge-cap { background: #f1c64f; color:#222; }
    .search-row .form-control, .search-row .form-select { height: 44px; }
    a.card:hover { text-decoration:none; border-color:#8B4513; }
  </style>
</head>
<body>
  <nav class="topbar py-2">
    <div class="container d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-2">
        <img src="Assets/Logo1.png" class="logo" alt="HanapBahay" />
        <strong class="ms-1">HanapBahay</strong>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="LoginModule" class="btn btn-outline-light btn-sm">Login</a>
      </div>
    </div>
  </nav>

  <main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end mb-3">
      <div class="mb-2">
        <h1 class="h4 mb-1">Browse Properties</h1>
        <p class="text-muted mb-0">You can view properties without logging in. Log in when you want to apply or message an owner.</p>
      </div>

      <!-- Sort dropdown -->
      <form method="get" class="mb-2">
        <?php foreach (['q','min_price','max_price','min_cap'] as $keep): ?>
          <?php if (isset($_GET[$keep]) && $_GET[$keep] !== ''): ?>
            <input type="hidden" name="<?= $keep ?>" value="<?= htmlspecialchars($_GET[$keep]) ?>">
          <?php endif; ?>
        <?php endforeach; ?>
        <?php foreach ($amenities_filter as $am): ?>
          <input type="hidden" name="amenities[]" value="<?= htmlspecialchars($am) ?>">
        <?php endforeach; ?>
        <label class="form-label small mb-1">Sort by</label>
        <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="newest"        <?= $sort==='newest'?'selected':'' ?>>Newest</option>
          <option value="price_asc"     <?= $sort==='price_asc'?'selected':'' ?>>Price: Low to High</option>
          <option value="price_desc"    <?= $sort==='price_desc'?'selected':'' ?>>Price: High to Low</option>
          <option value="capacity_desc" <?= $sort==='capacity_desc'?'selected':'' ?>>Capacity: High to Low</option>
        </select>
      </form>
    </div>

    <!-- Filters -->
    <form method="get" class="search-row row g-2 mb-3">
      <div class="col-md-5">
        <label class="form-label mb-1">Keyword</label>
        <input type="text" name="q" class="form-control" placeholder="Search title, address…" value="<?= htmlspecialchars($q) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1">Min price (₱)</label>
        <input type="text" inputmode="decimal" name="min_price" class="form-control" value="<?= $min_price !== null ? htmlspecialchars(number_format((float)$min_price, 2, '.', '')) : '' ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1">Max price (₱)</label>
        <input type="text" inputmode="decimal" name="max_price" class="form-control" value="<?= $max_price !== null ? htmlspecialchars(number_format((float)$max_price, 2, '.', '')) : '' ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1">Min capacity</label>
        <input type="number" name="min_cap" class="form-control" value="<?= $min_cap !== null ? htmlspecialchars($min_cap) : '' ?>">
      </div>
      <div class="col-6 col-md-1 d-grid align-self-end">
        <button class="btn btn-dark" type="submit" style="height:44px;"><i class="bi bi-search"></i></button>
      </div>

      <!-- Amenities Filter -->
      <div class="col-12 mt-3">
        <label class="form-label mb-2 fw-bold">Filter by Amenities</label>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:0.5rem;">
          <?php foreach ($ALL_AMENITIES as $key => $label): ?>
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="amenities[]" value="<?= $key ?>"
                     <?= in_array($key, $amenities_filter) ? 'checked' : '' ?>>
              <span class="form-check-label"><?= htmlspecialchars($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
    </form>

    <!-- Active Filters Display -->
    <?php if (!empty($amenities_filter) || !empty($q) || $min_price !== null || $max_price !== null || $min_cap !== null): ?>
      <div class="alert alert-light border mb-3">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <strong><i class="bi bi-funnel"></i> Active Filters:</strong>
            <?php if (!empty($q)): ?>
              <span class="badge bg-primary ms-2">Search: "<?= htmlspecialchars($q) ?>"</span>
            <?php endif; ?>
            <?php if ($min_price !== null || $max_price !== null): ?>
              <span class="badge bg-success ms-2">
                Price: <?= $min_price !== null ? '₱'.number_format($min_price, 0) : 'Any' ?> - <?= $max_price !== null ? '₱'.number_format($max_price, 0) : 'Any' ?>
              </span>
            <?php endif; ?>
            <?php if ($min_cap !== null): ?>
              <span class="badge bg-info ms-2">Min Capacity: <?= $min_cap ?></span>
            <?php endif; ?>
            <?php foreach ($amenities_filter as $am): ?>
              <span class="badge bg-warning text-dark ms-2"><?= htmlspecialchars(ucfirst($ALL_AMENITIES[$am] ?? $am)) ?></span>
            <?php endforeach; ?>
          </div>
          <a href="browse_listings.php" class="btn btn-sm btn-outline-secondary">Clear All</a>
        </div>
      </div>
    <?php endif; ?>

    <?php if (count($listings) === 0): ?>
      <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> No properties matched your filters. Try adjusting your search criteria.
      </div>
    <?php else: ?>
      <div class="text-muted mb-3">
        <small>Showing <?= count($listings) ?> of <?= $total_rows ?> properties</small>
      </div>
    <?php endif; ?>

    <div class="row g-3">
      <?php foreach ($listings as $l): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <a class="card card-listing h-100 p-0" href="listing_details?id=<?= (int)$l['id'] ?>" style="text-decoration: none; color: inherit;">
            <?php if (!empty($l['photos_array'])): ?>
              <img src="<?= htmlspecialchars($l['photos_array'][0]) ?>"
                   alt="<?= htmlspecialchars($l['title']) ?>"
                   style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px 8px 0 0;">
            <?php else: ?>
              <div style="width: 100%; height: 200px; background: #e9ecef; display: flex; align-items: center; justify-content: center; border-radius: 8px 8px 0 0;">
                <i class="bi bi-image" style="font-size: 3rem; color: #adb5bd;"></i>
              </div>
            <?php endif; ?>
            <div class="p-3">
              <h2 class="h6 mb-2"><?= htmlspecialchars($l['title']) ?></h2>
              <div class="mb-2 text-muted small"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($l['address']) ?></div>

              <?php if (!empty($l['amenities_array'])): ?>
                <div class="mb-2" style="font-size: 0.75rem;">
                  <?php
                  $display_amenities = array_slice($l['amenities_array'], 0, 3);
                  foreach ($display_amenities as $amenity):
                  ?>
                    <span class="badge bg-secondary me-1 mb-1"><?= htmlspecialchars(ucfirst($amenity)) ?></span>
                  <?php endforeach; ?>
                  <?php if (count($l['amenities_array']) > 3): ?>
                    <span class="badge bg-light text-dark border me-1">+<?= count($l['amenities_array']) - 3 ?> more</span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <div class="d-flex justify-content-between align-items-center mt-auto">
                <span class="price">₱<?= number_format((float)$l['price'], 2) ?>/month</span>
                <span class="badge badge-cap">Cap: <?= (int)$l['capacity'] ?></span>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
      <nav class="mt-4">
        <ul class="pagination justify-content-center">
          <?php
            $prev = max(1, $page-1);
            $next = min($total_pages, $page+1);

            // Prev
            echo '<li class="page-item '.($page==1?'disabled':'').'"><a class="page-link" href="?'.build_qs(['page'=>$prev]).'">&laquo;</a></li>';

            if ($total_pages <= 7) {
              for ($p=1; $p<=$total_pages; $p++) {
                $active = $p==$page ? ' active' : '';
                echo '<li class="page-item'.$active.'"><a class="page-link" href="?'.build_qs(['page'=>$p]).'">'.$p.'</a></li>';
              }
            } else {
              $window = 2;
              $start = max(1, $page-$window);
              $end   = min($total_pages, $page+$window);

              // first
              echo '<li class="page-item'.($page==1?' active':'').'"><a class="page-link" href="?'.build_qs(['page'=>1]).'">1</a></li>';
              if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';

              for ($p=$start; $p<=$end; $p++) {
                if ($p==1 || $p==$total_pages) continue;
                $active = $p==$page ? ' active' : '';
                echo '<li class="page-item'.$active.'"><a class="page-link" href="?'.build_qs(['page'=>$p]).'">'.$p.'</a></li>';
              }

              if ($end < $total_pages-1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
              // last
              echo '<li class="page-item'.($page==$total_pages?' active':'').'"><a class="page-link" href="?'.build_qs(['page'=>$total_pages]).'">'.$total_pages.'</a></li>';
            }

            // Next
            echo '<li class="page-item '.($page==$total_pages?'disabled':'').'"><a class="page-link" href="?'.build_qs(['page'=>$next]).'">&raquo;</a></li>';
          ?>
        </ul>
      </nav>
    <?php endif; ?>

    <div class="mt-3 text-center text-muted small">
      Showing page <?= (int)$page ?> of <?= (int)$total_pages ?> • <?= (int)$total_rows ?> result<?= $total_rows==1?'':'s' ?>
    </div>
  </main>

  <script src="darkmode.js"></script>
</body>
</html>