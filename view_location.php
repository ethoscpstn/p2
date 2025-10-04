<?php
session_start();
require 'mysql_connect.php';

// Get listing ID from URL
$listing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($listing_id <= 0) {
    header("Location: rental_request");
    exit();
}

// Fetch listing details
$stmt = $conn->prepare("
    SELECT id, title, address, latitude, longitude, price, capacity, is_available, description
    FROM tblistings
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$result = $stmt->get_result();
$listing = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$listing) {
    header("Location: rental_request");
    exit();
}

$lat = is_null($listing['latitude']) ? null : (float)$listing['latitude'];
$lng = is_null($listing['longitude']) ? null : (float)$listing['longitude'];
$has_coords = ($lat !== null && $lng !== null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Location - <?= htmlspecialchars($listing['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f7fb; }
        .topbar { background: #8B4513; color: #fff; }
        .logo { height: 42px; }
        #map {
            width: 100%;
            height: 600px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .info-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="topbar py-2">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <img src="Assets/Logo1.png" class="logo" alt="HanapBahay">
                <strong>Property Location</strong>
            </div>
            <div class="d-flex gap-2">
                <a href="rental_request" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-arrow-left"></i> Back to Requests
                </a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'tenant'): ?>
                    <a href="DashboardT" class="btn btn-sm btn-outline-light">Dashboard</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <!-- Property Info Card -->
        <div class="info-card p-4 mb-4">
            <div class="row">
                <div class="col-md-8">
                    <h4 class="mb-2"><?= htmlspecialchars($listing['title']) ?></h4>
                    <p class="text-muted mb-2">
                        <i class="bi bi-geo-alt-fill text-danger"></i>
                        <?= htmlspecialchars($listing['address']) ?>
                    </p>
                    <?php if ($listing['description']): ?>
                        <p class="mb-2"><?= htmlspecialchars($listing['description']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="mb-2">
                        <span class="badge bg-primary">Capacity: <?= (int)$listing['capacity'] ?></span>
                        <span class="badge bg-success">₱<?= number_format((float)$listing['price'], 2) ?>/month</span>
                    </div>
                    <div>
                        <span class="badge <?= $listing['is_available'] == 1 ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $listing['is_available'] == 1 ? 'Available' : 'Occupied' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Interactive Map -->
        <?php if ($has_coords): ?>
            <div id="map"></div>

            <div class="info-card p-3 mt-3">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Click the marker for more details. Use scroll to zoom, drag to pan.
                        </small>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <a href="https://www.google.com/maps?q=<?= $lat ?>,<?= $lng ?>"
                           target="_blank"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-map"></i> Open in Google Maps
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                No location coordinates available for this property.
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if ($has_coords): ?>
    <script>
        function initMap() {
            const location = { lat: <?= $lat ?>, lng: <?= $lng ?> };

            const map = new google.maps.Map(document.getElementById('map'), {
                center: location,
                zoom: 17,
                mapTypeControl: true,
                streetViewControl: true,
                fullscreenControl: true,
                gestureHandling: 'greedy'
            });

            const marker = new google.maps.Marker({
                position: location,
                map: map,
                title: <?= json_encode($listing['title']) ?>,
                animation: google.maps.Animation.DROP,
                icon: {
                    url: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png',
                    scaledSize: new google.maps.Size(50, 50)
                }
            });

            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div style="max-width: 300px;">
                        <h6 class="mb-2"><strong><?= htmlspecialchars($listing['title']) ?></strong></h6>
                        <p class="mb-1"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($listing['address']) ?></p>
                        <p class="mb-1"><strong>Price:</strong> ₱<?= number_format((float)$listing['price'], 2) ?>/month</p>
                        <p class="mb-2"><strong>Capacity:</strong> <?= (int)$listing['capacity'] ?> persons</p>
                        <a href="property_details?id=<?= $listing['id'] ?>" class="btn btn-sm btn-primary">
                            View Details
                        </a>
                    </div>
                `
            });

            marker.addListener('click', () => {
                infoWindow.open(map, marker);
            });

            // Auto-open info window
            setTimeout(() => {
                infoWindow.open(map, marker);
            }, 500);
        }
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCrKcnAX9KOdNp_TNHwWwzbLSQodgYqgnU&callback=initMap" async defer></script>
    <?php endif; ?>
</body>
</html>
