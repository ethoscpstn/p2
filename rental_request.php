<?php
session_start();
require 'mysql_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tenant') {
    header("Location: LoginModule");
    exit();
}

$tenant_id = $_SESSION['user_id'];

// Handle cancel request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request'])) {
    $request_id = (int)$_POST['request_id'];

    // Verify ownership and check if can be cancelled
    $stmt = $conn->prepare("SELECT id, status FROM rental_requests WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param("ii", $request_id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $req = $result->fetch_assoc();
    $stmt->close();

    if ($req && $req['status'] === 'pending') {
        $stmt = $conn->prepare("UPDATE rental_requests SET status = 'cancelled' WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Rental request cancelled successfully.";
        } else {
            $_SESSION['error'] = "Failed to cancel request.";
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "This request cannot be cancelled.";
    }

    header("Location: rental_request.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT rr.id, rr.status, rr.requested_at, rr.listing_id, rr.amount_due, rr.payment_method,
           l.title, l.address, l.latitude, l.longitude
    FROM rental_requests rr
    JOIN tblistings l ON rr.listing_id = l.id
    WHERE rr.tenant_id = ?
    ORDER BY rr.requested_at DESC
");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Rental Requests</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="rental_request.css" />
</head>
<body class="rental-request-page">
<div class="request-container">
  <h3 class="mb-4">My Rental Requests</h3>

  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <i class="bi bi-check-circle"></i> <?= htmlspecialchars($_SESSION['success']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
  <?php endif; ?>

  <?php if ($result->num_rows > 0): ?>
    <div class="table-responsive">
      <table class="table table-bordered">
        <thead class="table-light">
          <tr>
            <th>Request ID</th>
            <th>Property Title</th>
            <th>Location</th>
            <th>Amount</th>
            <th>Payment Type</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($request = $result->fetch_assoc()): ?>
            <?php
            $statusClass = 'badge bg-warning';
            if ($request['status'] === 'approved') $statusClass = 'badge bg-success';
            if ($request['status'] === 'rejected') $statusClass = 'badge bg-danger';
            if ($request['status'] === 'cancelled') $statusClass = 'badge bg-secondary';
            $paymentLabel = $request['payment_method'] === 'half' ? 'Half (50%)' : 'Full (100%)';
            ?>
            <tr>
              <td>#<?= $request['id'] ?></td>
              <td><?= htmlspecialchars($request['title']) ?></td>
              <td><?= htmlspecialchars($request['address']) ?></td>
              <td class="text-success fw-bold">₱<?= number_format($request['amount_due'], 2) ?></td>
              <td><?= $paymentLabel ?></td>
              <td><span class="<?= $statusClass ?>"><?= strtoupper($request['status']) ?></span></td>
              <td>
                <div class="d-flex gap-2">
                  <?php if (!empty($request['latitude']) && !empty($request['longitude'])): ?>
                    <a href="view_location?id=<?= $request['listing_id'] ?>"
                       class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-map"></i> Location
                    </a>
                  <?php endif; ?>

                  <?php if ($request['status'] === 'pending'): ?>
                    <form method="POST" style="display: inline;">
                      <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                      <input type="hidden" name="cancel_request" value="1">
                      <button type="submit" class="btn btn-sm btn-outline-danger"
                              onclick="return confirm('Are you sure you want to cancel this request?')">
                        <i class="bi bi-x-circle"></i> Cancel
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="text-muted">You haven't submitted any rental requests yet.</p>
  <?php endif; ?>
  <a href="DashboardT" class="btn btn-secondary mt-3">← Back to Dashboard</a>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
