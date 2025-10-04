<?php
// restore_listing.php
session_start();
require 'mysql_connect.php';

if (!isset($_SESSION['owner_id'])) {
  header('Location: LoginModule.php');
  exit();
}

$owner_id  = (int)$_SESSION['owner_id'];
$listing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($listing_id <= 0) {
  header('Location: DashboardUO.php?err=bad_request');
  exit();
}

// Only restore your own listing
$stmt = $conn->prepare("UPDATE tblistings SET is_archived = 0 WHERE id = ? AND owner_id = ?");
$stmt->bind_param("ii", $listing_id, $owner_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
  $stmt->close();
  $conn->close();
  header('Location: DashboardUO.php?restored=1');
  exit();
}

$stmt->close();
$conn->close();
header('Location: DashboardUO.php?restored=0');
