<?php
session_start();
require 'mysql_connect.php';

// Only unit owners can access
if (!isset($_SESSION['owner_id']) || ($_SESSION['role'] ?? '') !== 'unit_owner') {
    header("Location: LoginModule");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: view_requests");
    exit();
}

$owner_id = (int)$_SESSION['owner_id'];
$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$new_status = isset($_POST['status']) ? trim($_POST['status']) : '';

// Validate status
if (!in_array($new_status, ['approved', 'rejected'])) {
    $_SESSION['error'] = 'Invalid status provided.';
    header("Location: view_requests");
    exit();
}

// Verify that this request belongs to one of the owner's properties
$verify_stmt = $conn->prepare("
    SELECT rr.id
    FROM rental_requests rr
    JOIN tblistings l ON rr.listing_id = l.id
    WHERE rr.id = ? AND l.owner_id = ?
    LIMIT 1
");
$verify_stmt->bind_param("ii", $request_id, $owner_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    $_SESSION['error'] = 'Request not found or you do not have permission to modify it.';
    header("Location: view_requests");
    exit();
}
$verify_stmt->close();

// Update the request status
$update_stmt = $conn->prepare("UPDATE rental_requests SET status = ? WHERE id = ?");
$update_stmt->bind_param("si", $new_status, $request_id);

if ($update_stmt->execute()) {
    $_SESSION['success'] = 'Request ' . ($new_status === 'approved' ? 'approved' : 'rejected') . ' successfully.';
} else {
    $_SESSION['error'] = 'Failed to update request status.';
}

$update_stmt->close();
header("Location: view_requests");
exit();
