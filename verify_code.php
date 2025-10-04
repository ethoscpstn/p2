<?php
// verify_code.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require 'mysql_connect.php';

if (empty($_SESSION['pending_user_id'])) {
  // No pending login — go back to login
  header("Location: LoginModule");
  exit;
}

$user_id = (int)$_SESSION['pending_user_id'];
$role    = $_SESSION['pending_role'] ?? '';
$email   = $_SESSION['pending_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $code = trim($_POST['code'] ?? '');

  // Fetch user + current code/expiry
  $stmt = $conn->prepare("
    SELECT id, first_name, last_name, role, verification_code, code_expiry
    FROM tbadmin
    WHERE id = ?
    LIMIT 1
  ");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $res  = $stmt->get_result();
  $user = $res->fetch_assoc();
  $stmt->close();

  if (!$user) {
    $_SESSION['login_error'] = 'Session expired. Please log in again.';
    header("Location: LoginModule");
    exit;
  }

  // Validate code + expiry
  $now = date('Y-m-d H:i:s');
  if (
    !empty($user['verification_code']) &&
    !empty($user['code_expiry']) &&
    $user['verification_code'] === $code &&
    $user['code_expiry'] >= $now
  ) {
    // Clear code + finalize login
    $upd = $conn->prepare("
      UPDATE tbadmin
      SET verification_code=NULL, code_expiry=NULL
      WHERE id = ?
    ");
    $upd->bind_param("i", $user_id);
    $upd->execute();
    $upd->close();

    // Promote pending_* to full session
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['first_name'] = $user['first_name'] ?? '';
    $_SESSION['last_name']  = $user['last_name'] ?? '';
    $_SESSION['role']       = $user['role'] ?? 'tenant';

    if ($_SESSION['role'] === 'unit_owner') {
      $_SESSION['owner_id'] = (int)$user['id'];
    } else {
      unset($_SESSION['owner_id']);
    }

    // Remove pending
    unset($_SESSION['pending_user_id'], $_SESSION['pending_role'], $_SESSION['pending_email']);

    // Redirect by role
    if ($_SESSION['role'] === 'admin') {
      header("Location: admin_listings");
    } elseif ($_SESSION['role'] === 'unit_owner') {
      header("Location: DashboardUO");
    } else {
      header("Location: DashboardT");
    }
    exit;
  } else {
    $error = 'Invalid or expired code.';
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Verify Code</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="mx-auto bg-white p-4 rounded shadow-sm" style="max-width:420px;">
    <h4 class="mb-3">Enter Verification Code</h4>
    <p class="text-muted">We sent a 6-digit code to <strong><?= htmlspecialchars($email) ?></strong>.</p>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <label class="form-label">Code</label>
        <input type="text" name="code" class="form-control" inputmode="numeric" maxlength="6" required>
      </div>
      <button class="btn btn-primary w-100">Verify</button>
    </form>
    <div class="text-center mt-3">
      <a href="LoginModule">Back to login</a>
    </div>
  </div>
</div>
</body>
</html>
