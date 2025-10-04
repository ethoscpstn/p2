<?php
session_start();
require 'mysql_connect.php';
require 'send_request_status_notification.php';

if (!isset($_SESSION['owner_id']) || $_SESSION['role'] !== 'unit_owner') {
    header("Location: LoginModule");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: rental_requests_uo");
    exit();
}

$owner_id = (int)$_SESSION['owner_id'];
$request_id = (int)$_POST['request_id'];
$action = $_POST['action']; // 'approve' or 'reject'

// Fetch full request details for email notification
$stmt = $conn->prepare("
    SELECT rr.id, rr.status, rr.amount_due,
           l.title AS property_title,
           t.email AS tenant_email, t.first_name AS tenant_first_name, t.last_name AS tenant_last_name,
           o.first_name AS owner_first_name, o.last_name AS owner_last_name
    FROM rental_requests rr
    JOIN tblistings l ON l.id = rr.listing_id
    JOIN tbadmin t ON t.id = rr.tenant_id
    JOIN tbadmin o ON o.id = l.owner_id
    WHERE rr.id = ? AND l.owner_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $request_id, $owner_id);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();
$stmt->close();

if (!$request) {
    $_SESSION['error'] = "Invalid request or you don't have permission to modify it.";
    header("Location: rental_requests_uo");
    exit();
}

// Only allow changes to pending requests
if ($request['status'] !== 'pending') {
    $_SESSION['error'] = "This request has already been " . $request['status'] . ".";
    header("Location: rental_requests_uo");
    exit();
}

// Update the status
$new_status = ($action === 'approve') ? 'approved' : 'rejected';

$stmt = $conn->prepare("UPDATE rental_requests SET status = ? WHERE id = ?");
$stmt->bind_param("si", $new_status, $request_id);

if ($stmt->execute()) {
    // Send email notification to tenant
    $tenantName = trim($request['tenant_first_name'] . ' ' . $request['tenant_last_name']);
    $ownerName = trim($request['owner_first_name'] . ' ' . $request['owner_last_name']);

    sendRequestStatusNotification(
        $request['tenant_email'],
        $tenantName,
        $ownerName,
        $request['property_title'],
        $request['amount_due'],
        $new_status,
        $request_id
    );

    if ($new_status === 'approved') {
        $_SESSION['success'] = "Rental request approved successfully! Tenant has been notified via email.";
    } else {
        $_SESSION['success'] = "Rental request rejected. Tenant has been notified via email.";
    }
} else {
    $_SESSION['error'] = "Failed to update request status.";
}

$stmt->close();
$conn->close();

header("Location: rental_requests_uo");
exit();
?>
