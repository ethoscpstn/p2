<?php
session_start();
require 'mysql_connect.php';

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: LoginModule");
    exit();
}

// Handle verification action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['listing_id'])) {
    $listing_id = (int)$_POST['listing_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';
    $admin_id = (int)($_SESSION['user_id'] ?? 0);

    if ($action === 'approve') {
        // Approve: is_verified = 1, log timestamp and admin
        $stmt = $conn->prepare("UPDATE tblistings
            SET verification_status = 'approved',
                is_verified = 1,
                is_archived = 0,
                verified_at = NOW(),
                verified_by = ?,
                verification_notes = 'Approved'
            WHERE id = ?");
        $stmt->bind_param("ii", $admin_id, $listing_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Listing approved successfully!";
    } elseif ($action === 'reject') {
        // Reject: is_verified = -1, log timestamp and admin with reason
        $stmt = $conn->prepare("UPDATE tblistings
            SET verification_status = 'rejected',
                rejection_reason = ?,
                is_verified = -1,
                is_archived = 1,
                verified_at = NOW(),
                verified_by = ?,
                verification_notes = ?
            WHERE id = ?");
        $stmt->bind_param("siis", $rejection_reason, $admin_id, $rejection_reason, $listing_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Listing rejected.";
    }

    header("Location: admin_verify_listings");
    exit();
}

// Fetch pending listings
$stmt = $conn->prepare("
    SELECT l.id, l.title, l.address, l.price, l.capacity, l.description, l.amenities,
           l.gov_id_path, l.property_photos, l.verification_status, l.rejection_reason,
           l.created_at, o.first_name, o.last_name, o.email, o.id as owner_id
    FROM tblistings l
    JOIN tbadmin o ON o.id = l.owner_id
    WHERE l.verification_status = 'pending'
    ORDER BY l.created_at ASC
");
$stmt->execute();
$result = $stmt->get_result();
$pending_listings = [];
while ($row = $result->fetch_assoc()) {
    if (!empty($row['property_photos'])) {
        $row['property_photos_array'] = json_decode($row['property_photos'], true) ?: [];
    } else {
        $row['property_photos_array'] = [];
    }
    $pending_listings[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Listings - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f7fb; }
        .topbar { background: #8B4513; color: #fff; }
        .logo { height: 42px; }
        .listing-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 2px solid #e0e0e0;
        }
        .section-header {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 4px solid #8B4513;
        }
        .photo-preview {
            max-width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 2px solid #dee2e6;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .photo-preview:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .id-preview {
            max-width: 100%;
            max-height: 400px;
            border-radius: 8px;
            border: 3px solid #ffc107;
            cursor: pointer;
            transition: transform 0.2s;
            background: #fff;
            padding: 5px;
        }
        .id-preview:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .photo-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }
        .badge-count {
            background: #8B4513;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .id-container {
            background: #fffbf0;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #ffc107;
        }
        .no-content-box {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #f5c6cb;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="topbar py-2">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <img src="Assets/Logo1.png" class="logo" alt="HanapBahay">
                <strong>Admin - Verify Listings</strong>
            </div>
            <div class="d-flex gap-2">
                <a href="admin_listings" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-building"></i> Manage Listings
                </a>
                <a href="admin_transactions" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-receipt"></i> Transactions
                </a>
                <a href="logout" class="btn btn-sm btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <h3 class="mb-4"><i class="bi bi-shield-check"></i> Pending Verification (<?= count($pending_listings) ?>)</h3>

        <?php if (empty($pending_listings)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No listings pending verification at this time.
            </div>
        <?php else: ?>
            <?php foreach ($pending_listings as $listing): ?>
                <?php
                $ownerName = trim($listing['first_name'] . ' ' . $listing['last_name']);
                $amenities_arr = !empty($listing['amenities']) ? explode(', ', $listing['amenities']) : [];
                ?>
                <div class="listing-card">
                    <div class="row">
                        <!-- Property Photos -->
                        <div class="col-md-4">
                            <div class="section-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-camera-fill"></i> Property Photos
                                    <?php if (!empty($listing['property_photos_array'])): ?>
                                        <span class="badge-count"><?= count($listing['property_photos_array']) ?></span>
                                    <?php endif; ?>
                                </h5>
                            </div>
                            <?php if (!empty($listing['property_photos_array'])): ?>
                                <div class="photo-grid">
                                    <?php foreach ($listing['property_photos_array'] as $idx => $photo): ?>
                                        <div>
                                            <small class="text-muted d-block mb-1">Photo <?= $idx + 1 ?>:</small>
                                            <a href="<?= htmlspecialchars($photo) ?>" target="_blank" title="Click to view full size">
                                                <img src="<?= htmlspecialchars($photo) ?>" alt="Property Photo <?= $idx + 1 ?>" class="photo-preview">
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-content-box">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>No photos uploaded</strong>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Property Details -->
                        <div class="col-md-4">
                            <div class="section-header">
                                <h5 class="mb-0"><i class="bi bi-info-circle-fill"></i> Property Details</h5>
                            </div>
                            <p><strong>Title:</strong> <?= htmlspecialchars($listing['title']) ?></p>
                            <p><strong>Address:</strong> <?= htmlspecialchars($listing['address']) ?></p>
                            <p><strong>Price:</strong> <span class="text-success fw-bold">₱<?= number_format($listing['price'], 2) ?>/month</span></p>
                            <p><strong>Capacity:</strong> <?= (int)$listing['capacity'] ?> person(s)</p>
                            <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($listing['description'])) ?></p>
                            <?php if (!empty($amenities_arr)): ?>
                                <p><strong>Amenities:</strong><br>
                                    <?php foreach ($amenities_arr as $amenity): ?>
                                        <span class="badge bg-secondary me-1 mb-1"><?= htmlspecialchars($amenity) ?></span>
                                    <?php endforeach; ?>
                                </p>
                            <?php endif; ?>
                            <hr>
                            <div class="section-header">
                                <h5 class="mb-0"><i class="bi bi-person-fill"></i> Owner Information</h5>
                            </div>
                            <p><strong>Name:</strong> <?= htmlspecialchars($ownerName) ?></p>
                            <p><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($listing['email']) ?>"><?= htmlspecialchars($listing['email']) ?></a></p>
                            <p><strong>Submitted:</strong> <?= date('M d, Y g:i A', strtotime($listing['created_at'])) ?></p>
                        </div>

                        <!-- Government ID & Actions -->
                        <div class="col-md-4">
                            <div class="section-header">
                                <h5 class="mb-0"><i class="bi bi-shield-check"></i> Government ID Verification</h5>
                            </div>
                            <?php if (!empty($listing['gov_id_path'])): ?>
                                <div class="id-container">
                                    <?php
                                    $ext = strtolower(pathinfo($listing['gov_id_path'], PATHINFO_EXTENSION));
                                    if ($ext === 'pdf'):
                                    ?>
                                        <div class="text-center mb-3">
                                            <i class="bi bi-file-pdf" style="font-size: 3rem; color: #dc3545;"></i>
                                            <p class="mb-2"><strong>PDF Document</strong></p>
                                        </div>
                                        <a href="<?= htmlspecialchars($listing['gov_id_path']) ?>" target="_blank" class="btn btn-danger w-100 mb-2">
                                            <i class="bi bi-file-pdf"></i> Open PDF in New Tab
                                        </a>
                                        <small class="text-muted d-block text-center">Click above to view the government ID</small>
                                    <?php else: ?>
                                        <small class="text-muted d-block mb-2 text-center">Click image to view full size</small>
                                        <a href="<?= htmlspecialchars($listing['gov_id_path']) ?>" target="_blank" title="Click to view full size">
                                            <img src="<?= htmlspecialchars($listing['gov_id_path']) ?>" alt="Government ID" class="id-preview">
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-content-box">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>No ID uploaded</strong>
                                </div>
                            <?php endif; ?>

                            <hr class="my-4">
                            <div class="section-header">
                                <h5 class="mb-0"><i class="bi bi-hand-thumbs-up"></i> Verification Actions</h5>
                            </div>
                            <div class="alert alert-warning mb-3">
                                <small><i class="bi bi-info-circle"></i> <strong>Review checklist:</strong></small>
                                <ul class="mb-0 mt-2" style="font-size: 0.85rem;">
                                    <li>Verify government ID is clear and valid</li>
                                    <li>Check property photos show actual property</li>
                                    <li>Confirm property details are accurate</li>
                                </ul>
                            </div>
                            <form method="POST" class="mb-2">
                                <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success w-100 btn-lg" onclick="return confirm('✓ Approve this listing?\n\nThe property will become visible to all tenants.')">
                                    <i class="bi bi-check-circle-fill"></i> Approve Listing
                                </button>
                            </form>
                            <button class="btn btn-danger w-100 btn-lg" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $listing['id'] ?>">
                                <i class="bi bi-x-circle-fill"></i> Reject Listing
                            </button>

                            <!-- Reject Modal -->
                            <div class="modal fade" id="rejectModal<?= $listing['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject Listing</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <div class="mb-3">
                                                    <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                                                    <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Explain why this listing is being rejected..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Reject</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
