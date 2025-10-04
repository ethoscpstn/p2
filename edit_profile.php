<?php
session_start();
require 'mysql_connect.php';

// Debug during setup (remove later)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (empty($_SESSION['owner_id'])) {
  header("Location: LoginModule.php");
  exit;
}

$owner_id = (int)$_SESSION['owner_id'];
$errors = [];
$ok = false;

// Fetch current profile
$stmt = $conn->prepare("SELECT id, first_name, last_name, email, password, gcash_name, gcash_number, gcash_qr_path 
                        FROM tbadmin WHERE id=? LIMIT 1");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
  die("Owner not found in tbadmin");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $first_name = trim($_POST['first_name'] ?? $profile['first_name']);
  $last_name  = trim($_POST['last_name'] ?? $profile['last_name']);
  $email      = trim($_POST['email'] ?? $profile['email']);
  $gcash_name = trim($_POST['gcash_name'] ?? $profile['gcash_name']);
  $gcash_number = trim($_POST['gcash_number'] ?? $profile['gcash_number']);
  $gcash_qr_path = $profile['gcash_qr_path'];

  // Validate email
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email address.";
  }

  // Handle password change
  $new_password = trim($_POST['new_password'] ?? '');
  $confirm_password = trim($_POST['confirm_password'] ?? '');
  $update_password = false;
  if ($new_password !== '' || $confirm_password !== '') {
    if (strlen($new_password) < 6) {
      $errors[] = "Password must be at least 6 characters.";
    } elseif ($new_password !== $confirm_password) {
      $errors[] = "Password confirmation does not match.";
    } else {
      $update_password = true;
      $hashed_pw = password_hash($new_password, PASSWORD_DEFAULT);
    }
  }

  // Handle QR upload
  if (!empty($_FILES['gcash_qr']['name']) && $_FILES['gcash_qr']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    $detected = mime_content_type($_FILES['gcash_qr']['tmp_name']);
    if (!isset($allowed[$detected])) {
      $errors[] = "Invalid QR file type.";
    } elseif ($_FILES['gcash_qr']['size'] > 5*1024*1024) {
      $errors[] = "QR image too large (max 5MB).";
    } else {
      $dir = __DIR__ . "/uploads/owners/{$owner_id}";
      if (!is_dir($dir)) { mkdir($dir, 0775, true); }
      $fname = "gcashqr_" . time() . "." . $allowed[$detected];
      $abs = $dir . "/" . $fname;
      if (move_uploaded_file($_FILES['gcash_qr']['tmp_name'], $abs)) {
        if (!empty($gcash_qr_path) && file_exists(__DIR__ . '/' . $gcash_qr_path)) {
          unlink(__DIR__ . '/' . $gcash_qr_path);
        }
        $gcash_qr_path = "uploads/owners/{$owner_id}/" . $fname;
      }
    }
  }

  if (!$errors) {
    if ($update_password) {
      $stmt = $conn->prepare("UPDATE tbadmin 
        SET first_name=?, last_name=?, email=?, password=?, gcash_name=?, gcash_number=?, gcash_qr_path=? 
        WHERE id=?");
      $stmt->bind_param("sssssssi", $first_name, $last_name, $email, $hashed_pw, $gcash_name, $gcash_number, $gcash_qr_path, $owner_id);
    } else {
      $stmt = $conn->prepare("UPDATE tbadmin 
        SET first_name=?, last_name=?, email=?, gcash_name=?, gcash_number=?, gcash_qr_path=? 
        WHERE id=?");
      $stmt->bind_param("ssssssi", $first_name, $last_name, $email, $gcash_name, $gcash_number, $gcash_qr_path, $owner_id);
    }

    if ($stmt->execute()) {
      $ok = true;
      $profile['first_name'] = $first_name;
      $profile['last_name']  = $last_name;
      $profile['email']      = $email;
      $profile['gcash_name'] = $gcash_name;
      $profile['gcash_number'] = $gcash_number;
      $profile['gcash_qr_path'] = $gcash_qr_path;
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
  <title>Edit Profile</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="edit_profile.css?v=9" />
</head>
<body class="profile-page">
<div class="profile-container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Edit Profile</h3>
    <a href="DashboardUO.php" class="btn-back">Back to Dashboard</a>
  </div>

  <?php if ($ok): ?><div class="alert alert-success">Profile updated.</div><?php endif; ?>
  <?php if ($errors): ?><div class="alert alert-danger"><ul><?php foreach($errors as $e){ echo "<li>".htmlspecialchars($e)."</li>"; } ?></ul></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="bg-white p-4 shadow-sm rounded">
    <h6 class="text-muted">Account Information</h5>
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

    <div class="row g-3 mt-3">
      <div class="col-md-6">
        <label class="form-label">New Password</label>
        <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
      </div>
      <div class="col-md-6">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password">
      </div>
    </div>

    <hr class="my-4">
    <h5 class="mb-3">GCash Payment Information</h5>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">GCash Account Name</label>
        <input type="text" name="gcash_name" class="form-control" value="<?= htmlspecialchars($profile['gcash_name']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">GCash Number</label>
        <input type="text" name="gcash_number" class="form-control" value="<?= htmlspecialchars($profile['gcash_number']) ?>">
      </div>
    </div>

    <div class="mt-3">
      <label class="form-label">Replace GCash QR</label>
      <?php if (!empty($profile['gcash_qr_path'])): ?>
        <div class="mb-2"><img src="<?= htmlspecialchars($profile['gcash_qr_path']) ?>" class="qr-preview"></div>
      <?php endif; ?>
      <input type="file" name="gcash_qr" class="form-control" accept="image/*">
      <small class="text-muted">Accepted: JPG/PNG/WebP/GIF (max 5 MB).</small>
    </div>

    <div class="mt-4 d-flex gap-2">
      <button type="submit" class="btn-save">Save Changes</button>
      <a href="DashboardUO.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>
</div>
</body>
</html>
