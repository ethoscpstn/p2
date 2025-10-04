<?php
// edit_profile_tenant.php — Tenant profile (tbadmin)
session_start();
require 'mysql_connect.php';

// show errors while wiring (remove later)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Must be logged in as tenant
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'tenant' || empty($_SESSION['user_id'])) {
  header("Location: LoginModule.php");
  exit;
}

$tenant_id = (int)$_SESSION['user_id'];
$errors = [];
$ok = false;

// Fetch current profile
$stmt = $conn->prepare("SELECT id, first_name, last_name, email, password FROM tbadmin WHERE id=? LIMIT 1");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) { die("Tenant not found in tbadmin"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $first_name = trim($_POST['first_name'] ?? $profile['first_name']);
  $last_name  = trim($_POST['last_name']  ?? $profile['last_name']);
  $email      = trim($_POST['email']      ?? $profile['email']);

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please enter a valid email address.";
  }

  // Optional password change
  $new_password     = trim($_POST['new_password'] ?? '');
  $confirm_password = trim($_POST['confirm_password'] ?? '');
  $update_password = false;
  if ($new_password !== '' || $confirm_password !== '') {
    if (strlen($new_password) < 6) {
      $errors[] = "New password must be at least 6 characters.";
    } elseif ($new_password !== $confirm_password) {
      $errors[] = "Password confirmation does not match.";
    } else {
      $update_password = true;
      $hashed_pw = password_hash($new_password, PASSWORD_DEFAULT);
    }
  }

  if (!$errors) {
    if ($update_password) {
      $stmt = $conn->prepare("UPDATE tbadmin SET first_name=?, last_name=?, email=?, password=? WHERE id=?");
      $stmt->bind_param("ssssi", $first_name, $last_name, $email, $hashed_pw, $tenant_id);
    } else {
      $stmt = $conn->prepare("UPDATE tbadmin SET first_name=?, last_name=?, email=? WHERE id=?");
      $stmt->bind_param("sssi", $first_name, $last_name, $email, $tenant_id);
    }

    if ($stmt->execute()) {
      $ok = true;
      // refresh in-session display names if you use them
      $_SESSION['first_name'] = $first_name;
      $_SESSION['last_name']  = $last_name;
      $profile['first_name']  = $first_name;
      $profile['last_name']   = $last_name;
      $profile['email']       = $email;
    } else {
      $errors[] = "Update failed: " . $stmt->error;
    }
    $stmt->close();
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tenant Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="edit_profile.css?v=9" />
</head>
<body class="profile-page">
<div class="profile-container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Settings</h3>
    <a href="DashboardT.php" class="btn-back">Back to Dashboard</a>
  </div>

  <?php if ($ok): ?><div class="alert alert-success">Profile updated.</div><?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e){ echo '<li>'.htmlspecialchars($e).'</li>'; } ?></ul></div>
  <?php endif; ?>

  <form method="POST">
    <h6 class="text-muted">Account Information</h6>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">First Name</label>
        <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($profile['first_name']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Last Name</label>
        <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($profile['last_name']) ?>">
      </div>
    </div>

    <div class="mt-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($profile['email']) ?>" required>
    </div>

    <hr class="my-4">
    <h6 class="text-muted">Change Password</h6>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">New Password (Optional)</label>
        <input type="password" name="new_password" class="form-control" placeholder="New Password">
      </div>
      <div class="col-md-6">
        <label class="form-label">Confirm New Password</label>
        <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password">
      </div>
    </div>

    <div class="mt-4 d-flex gap-2">
      <button type="submit" class="btn-save">Save Changes</button>
      <a href="DashboardT.php" class="btn-cancel">Cancel</a>
    </div>
  </form>
</div>
</body>
</html>
