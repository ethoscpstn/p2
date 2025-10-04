<?php
session_start();
require 'mysql_connect.php';

$code = $_GET['code'] ?? '';

if ($code) {
    $stmt = $conn->prepare("SELECT id, username, email FROM tbadmin WHERE verification_token = ? AND is_verified = 0");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        $update = $conn->prepare("UPDATE tbadmin SET is_verified = 1, verification_token = NULL WHERE id = ?");
        $update->bind_param("i", $user['id']);
        $update->execute();

        $_SESSION['success_flash'] = "Your email has been verified! You can now log in.";
        header("refresh:3; url=LoginModule.php");
        exit();
    } else {
        echo "<h2>Invalid or already used verification code.</h2>";
    }
} else {
    echo "<h2>No verification code provided.</h2>";
}
