<?php
// Start session (still useful for other features, but we ignore login state here)
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>HanapBahay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="HomePage.css" />
    <link rel="stylesheet" href="darkmode.css" />
</head>
<body>

  <!-- ✅ NAVBAR -->
  <nav class="topFixedBar">
    <div class="container d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <img src="Assets/Logo1.png" alt="HanapBahay Logo" class="logo" />
      </div>
      <div class="d-flex align-items-center gap-2">
        <!-- Always visible -->
        <a href="browse_listings.php" class="btn btn-outline-light btn-sm">Browse</a>
        <a href="LoginModule.php" class="btn btn-warning btn-sm text-dark">Login</a>
      </div>
    </div>
  </nav>

  <!-- ✅ MAIN CONTENT -->
  <main class="main-content">

    <!-- Featured Listings Section -->
    <section class="layer layer-2">
      <h3>Featured Listings</h3>
      <p>Explore properties tailored for tenants and unit owners.</p>

      <?php
      require_once 'mysql_connect.php';

      $fsql = "SELECT id, title, address, price, capacity
               FROM tblistings
               WHERE is_archived = 0
               ORDER BY id DESC
               LIMIT 6";
      $fst = $conn->prepare($fsql);
      $fst->execute();
      $fst->store_result();
      $fst->bind_result($fid, $ftitle, $faddr, $fprice, $fcap);

      $featured = [];
      while ($fst->fetch()) {
        $featured[] = [
          'id' => $fid,
          'title' => $ftitle,
          'address' => $faddr,
          'price' => $fprice,
          'capacity' => $fcap
        ];
      }
      $fst->free_result();
      $fst->close();
      ?>

      <div class="container my-3">
        <?php if (!$featured): ?>
          <div class="alert alert-info mb-2">No featured listings yet.</div>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($featured as $l): ?>
              <div class="col-12 col-md-6 col-lg-4">
                <a class="card h-100 p-3 text-decoration-none" href="listing_details.php?id=<?= (int)$l['id'] ?>">
                  <h3 class="h6 mb-1 text-dark"><?= htmlspecialchars($l['title']) ?></h3>
                  <div class="small text-muted mb-2"><?= htmlspecialchars($l['address']) ?></div>
                  <div class="d-flex justify-content-between align-items-center mt-auto">
                    <span class="fw-bold text-dark">₱<?= number_format((float)$l['price'], 2) ?></span>
                    <span class="badge bg-warning text-dark">Cap: <?= (int)$l['capacity'] ?></span>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="text-center mt-3">
            <a class="btn btn-outline-secondary" href="browse_listings.php">See all listings</a>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="layer layer-4">
      <h3>Contact Us</h3>
      <p>Email: info@hanapbahay.com | Phone: 123-456-7890</p>
    </section>

  </main>

  <script src="darkmode.js"></script>
</body>
</html>
