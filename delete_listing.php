<?php
require 'mysql_connect.php';
session_start();

$id = $_GET['id'] ?? null;
$owner_id = $_SESSION['owner_id'] ?? 0;

if ($id) {
  $stmt = $conn->prepare("DELETE FROM tblistings WHERE id = ? AND owner_id = ?");
  $stmt->bind_param("ii", $id, $owner_id);
  $stmt->execute();
  $stmt->close();
}

header("Location: DashboardUO.php");
exit;
?>
