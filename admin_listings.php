<?php
// admin_listings.php — manage property listing approvals
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'mysql_connect.php';

// Require admin login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: LoginModule.php");
    exit();
}

$admin_id = (int)($_SESSION['user_id'] ?? 0);

// --- Handle approve/reject actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['listing_id'], $_POST['action'])) {
    $listing_id = (int)$_POST['listing_id'];
    $action_raw = $_POST['action'];
    $notes = trim($_POST['verification_notes'] ?? '');

    // 1 = approved, -1 = rejected
    $decision = ($action_raw === 'approve') ? 1 : -1;

    $sql = "UPDATE tblistings
            SET is_verified = ?,
                verified_at = NOW(),
                verified_by = ?,
                verification_notes = ?
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisi", $decision, $admin_id, $notes, $listing_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        $_SESSION['flash'] = "Listing #$listing_id " . ($decision === 1 ? "approved" : "rejected") . ".";
    } else {
        $_SESSION['flash_error'] = "Failed to update listing #$listing_id.";
    }

    // Keep current filter on redirect
    $status = $_GET['status'] ?? 'all';
    header("Location: admin_listings.php?status=" . urlencode($status));
    exit();
}

// --- Handle filter ---
$status = $_GET['status'] ?? 'all';
$where = "";
if ($status === 'pending') {
    $where = "l.is_verified = 0";
} elseif ($status === 'approved') {
    $where = "l.is_verified = 1";
} elseif ($status === 'rejected') {
    $where = "l.is_verified = -1";
}

// --- Fetch listings with owner info (apply filter if any) ---
$sql = "
  SELECT l.id, l.title, l.address, l.price, l.capacity, l.is_available, l.created_at,
         l.is_verified, l.verification_notes, l.verified_at, l.verified_by,
         a.first_name, a.last_name, a.email
  FROM tblistings l
  JOIN tbadmin a ON a.id = l.owner_id
  WHERE l.is_archived = 0
  " . ($where ? " AND $where " : "") . "
  ORDER BY l.created_at DESC
";
$res = $conn->query($sql);
$listings = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$conn->close();

// simple helper
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin — Manage Listings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background:#f7f7f7; }
    .notes-cell textarea{ width:100%; min-height:42px; resize:vertical; }
    .status-badge { font-size:.85rem; }
    .table thead th { white-space: nowrap; }
  </style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Manage Property Listings</h3>
    <div class="d-flex gap-2">
      <a href="admin_verify_listings" class="btn btn-warning btn-sm">
        <i class="bi bi-shield-check"></i> Verify Listings
      </a>
      <a href="admin_transactions" class="btn btn-primary btn-sm">
        <i class="bi bi-receipt"></i> View Transactions
      </a>
      <a href="DashboardUO.php" class="btn btn-outline-secondary btn-sm ms-2">Back</a>
      <a href="logout.php" class="btn btn-outline-danger btn-sm ms-2">Logout</a>
    </div>
  </div>

  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert alert-success"><?= h($_SESSION['flash']) ?></div>
    <?php unset($_SESSION['flash']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= h($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>

  <!-- Filter -->
  <form method="get" class="mb-3">
    <label for="status" class="form-label fw-semibold me-2">Filter by Status:</label>
    <select id="status" name="status" class="form-select d-inline-block w-auto" onchange="this.form.submit()">
      <option value="all"      <?= $status==='all' ? 'selected':'' ?>>All</option>
      <option value="pending"  <?= $status==='pending' ? 'selected':'' ?>>Pending</option>
      <option value="approved" <?= $status==='approved' ? 'selected':'' ?>>Approved</option>
      <option value="rejected" <?= $status==='rejected' ? 'selected':'' ?>>Rejected</option>
    </select>
  </form>

  <div class="table-responsive">
    <table class="table table-bordered table-hover bg-white">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>Title</th>
          <th>Owner</th>
          <th>Email</th>
          <th>Address</th>
          <th>Price (₱)</th>
          <th>Capacity</th>
          <th>Created</th>
          <th>Status</th>
          <th style="min-width:200px;">Notes / Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($listings)): ?>
        <tr><td colspan="10" class="text-center text-muted py-4">No listings found.</td></tr>
      <?php else: ?>
        <?php foreach ($listings as $row): ?>
          <tr>
            <td><?= (int)$row['id'] ?></td>
            <td><?= h($row['title']) ?></td>
            <td><?= h(trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''))) ?></td>
            <td><?= h($row['email']) ?></td>
            <td><?= h($row['address']) ?></td>
            <td><?= number_format((float)$row['price'], 2) ?></td>
            <td><?= (int)$row['capacity'] ?></td>
            <td><?= h($row['created_at']) ?></td>
            <td>
              <?php if ((int)$row['is_verified'] === 1): ?>
                <span class="badge bg-success status-badge">Approved</span>
              <?php elseif ((int)$row['is_verified'] === -1): ?>
                <span class="badge bg-danger status-badge">Rejected</span>
              <?php else: ?>
                <span class="badge bg-warning text-dark status-badge">Pending</span>
              <?php endif; ?>
              <?php if (!empty($row['verified_at'])): ?>
                <div class="small text-muted mt-1">
                  <?= 'On ' . h($row['verified_at']) . ($row['verified_by'] ? " · by #".(int)$row['verified_by'] : '') ?>
                </div>
              <?php endif; ?>
            </td>

            <td class="notes-cell">
              <?php if ((int)$row['is_verified'] === 0): ?>
                <form method="POST" class="d-flex flex-column gap-2">
                  <input type="hidden" name="listing_id" value="<?= (int)$row['id'] ?>">
                  <textarea name="verification_notes" placeholder="Optional notes (visible to admins only)"><?= h($row['verification_notes'] ?? '') ?></textarea>
                  <div class="d-flex gap-2">
                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                    <button type="submit" name="action" value="reject"  class="btn btn-danger btn-sm">Reject</button>
                  </div>
                </form>
              <?php else: ?>
                <div class="small text-muted" style="white-space:pre-wrap;"><?= h($row['verification_notes'] ?? '—') ?></div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
