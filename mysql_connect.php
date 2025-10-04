<?php
// Set timezone to Philippine time for consistency
date_default_timezone_set('Asia/Manila');

$servername = "localhost";
$username = "u412552698_dbhanapbahay";
$password = "Obu8@20a6|";
$database = "u412552698_dbhanapbahay";

 ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set MySQL timezone to match PHP timezone
$conn->query("SET time_zone = '+08:00'");
?>


