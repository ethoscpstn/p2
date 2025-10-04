<?php
session_start();
require 'mysql_connect.php';

$verified = false;

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT id FROM tbadmin WHERE verification_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id);
        $stmt->fetch();

        // Mark account as verified
        $update = $conn->prepare("UPDATE tbadmin SET is_verified = 1, verification_token = NULL WHERE id = ?");
        $update->bind_param("i", $user_id);
        $update->execute();

        $verified = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Email Verified</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      background: #f7f7f7;
    }
    .message-box {
      text-align: center;
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .btn-orange {
      background-color: #ff914d;
      border: none;
      color: white;
    }
  </style>
</head>
<body>
  <div class="message-box">
    <h4>
      <?= $verified ? "✅ Your email has been verified." : "⚠️ Invalid or expired link." ?>
    </h4>
    <p class="mb-4">You may now proceed to login.</p>
    <a href="LoginModule.php" class="btn btn-orange px-4">Go to Login</a>
  </div>
</body>
</html>
