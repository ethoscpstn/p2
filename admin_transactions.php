<?php
session_start();
require 'mysql_connect.php';

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: LoginModule");
    exit();
}

// Fetch all rental transactions with full details
$stmt = $conn->prepare("
    SELECT rr.id, rr.tenant_id, rr.listing_id, rr.payment_method,
           rr.amount_due, rr.status, rr.requested_at, rr.receipt_path,
           l.title AS property_title, l.address AS property_address,
           l.price AS property_price, l.owner_id,
           t.first_name AS tenant_first_name, t.last_name AS tenant_last_name,
           t.email AS tenant_email,
           o.first_name AS owner_first_name, o.last_name AS owner_last_name,
           o.email AS owner_email
    FROM rental_requests rr
    JOIN tblistings l ON l.id = rr.listing_id
    JOIN tbadmin t ON t.id = rr.tenant_id
    JOIN tbadmin o ON o.id = l.owner_id
    ORDER BY rr.requested_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

// Calculate statistics
$total_transactions = count($transactions);
$total_revenue = array_sum(array_column($transactions, 'amount_due'));
$pending_count = count(array_filter($transactions, fn($t) => $t['status'] === 'pending'));
$approved_count = count(array_filter($transactions, fn($t) => $t['status'] === 'approved'));
$rejected_count = count(array_filter($transactions, fn($t) => $t['status'] === 'rejected'));
$cancelled_count = count(array_filter($transactions, fn($t) => $t['status'] === 'cancelled'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Transactions - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f7fb; }
        .topbar { background: #8B4513; color: #fff; }
        .logo { height: 42px; }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-card p {
            color: #6c757d;
            margin: 0;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d1e7dd; color: #0f5132; }
        .status-rejected { background: #f8d7da; color: #842029; }
        .status-cancelled { background: #e2e3e5; color: #383d41; }
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .filter-bar {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="topbar py-2">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <img src="Assets/Logo1.png" class="logo" alt="HanapBahay">
                <strong>Admin - Payment Transactions</strong>
            </div>
            <div class="d-flex gap-2">
                <a href="admin_listings" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-building"></i> Manage Listings
                </a>
                <a href="logout" class="btn btn-sm btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <i class="bi bi-receipt text-primary" style="font-size: 2rem;"></i>
                    <h3><?= $total_transactions ?></h3>
                    <p>Total Transactions</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <i class="bi bi-hourglass text-warning" style="font-size: 2rem;"></i>
                    <h3><?= $pending_count ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card">
                    <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                    <h3><?= $approved_count ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card">
                    <i class="bi bi-x-circle text-danger" style="font-size: 2rem;"></i>
                    <h3><?= $rejected_count ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card">
                    <i class="bi bi-slash-circle text-secondary" style="font-size: 2rem;"></i>
                    <h3><?= $cancelled_count ?></h3>
                    <p>Cancelled</p>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search tenant, owner, or property...">
                </div>
                <div class="col-md-2">
                    <select id="statusFilter" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="paymentFilter" class="form-select">
                        <option value="">All Payment Types</option>
                        <option value="half">Half Payment</option>
                        <option value="full">Full Payment</option>
                    </select>
                </div>
                <div class="col-md-5 text-end">
                    <button class="btn btn-outline-primary" onclick="exportToCSV()">
                        <i class="bi bi-download"></i> Export to CSV
                    </button>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="table-container">
            <h5 class="mb-3"><i class="bi bi-table"></i> All Transactions</h5>
            <div class="table-responsive">
                <table class="table table-hover" id="transactionsTable">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Property</th>
                            <th>Tenant</th>
                            <th>Owner</th>
                            <th>Payment Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                            <?php
                            $statusClass = 'status-pending';
                            if ($txn['status'] === 'approved') $statusClass = 'status-approved';
                            if ($txn['status'] === 'rejected') $statusClass = 'status-rejected';
                            if ($txn['status'] === 'cancelled') $statusClass = 'status-cancelled';

                            $paymentLabel = $txn['payment_method'] === 'half' ? 'Half (50%)' : 'Full (100%)';
                            $tenantName = trim($txn['tenant_first_name'] . ' ' . $txn['tenant_last_name']);
                            $ownerName = trim($txn['owner_first_name'] . ' ' . $txn['owner_last_name']);
                            ?>
                            <tr data-status="<?= $txn['status'] ?>" data-payment="<?= $txn['payment_method'] ?>">
                                <td><strong>#<?= $txn['id'] ?></strong></td>
                                <td><?= date('M d, Y', strtotime($txn['requested_at'])) ?><br>
                                    <small class="text-muted"><?= date('g:i A', strtotime($txn['requested_at'])) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($txn['property_title']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($txn['property_address']) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($tenantName) ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($txn['tenant_email']) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($ownerName) ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($txn['owner_email']) ?></small>
                                </td>
                                <td><?= $paymentLabel ?></td>
                                <td><strong class="text-success">₱<?= number_format($txn['amount_due'], 2) ?></strong></td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= strtoupper($txn['status']) ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewDetails(<?= $txn['id'] ?>)">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- View Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Transaction Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search and filter functionality
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const paymentFilter = document.getElementById('paymentFilter');
        const tableRows = document.querySelectorAll('#transactionsTable tbody tr');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value;
            const paymentValue = paymentFilter.value;

            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const status = row.dataset.status;
                const payment = row.dataset.payment;

                const matchesSearch = text.includes(searchTerm);
                const matchesStatus = !statusValue || status === statusValue;
                const matchesPayment = !paymentValue || payment === paymentValue;

                if (matchesSearch && matchesStatus && matchesPayment) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', filterTable);
        statusFilter.addEventListener('change', filterTable);
        paymentFilter.addEventListener('change', filterTable);

        // View details
        function viewDetails(id) {
            const transactions = <?= json_encode($transactions) ?>;
            const txn = transactions.find(t => t.id == id);

            if (txn) {
                const tenantName = `${txn.tenant_first_name} ${txn.tenant_last_name}`.trim();
                const ownerName = `${txn.owner_first_name} ${txn.owner_last_name}`.trim();
                const paymentLabel = txn.payment_method === 'half' ? 'Half Payment (50%)' : 'Full Payment (100%)';

                const html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Transaction Information</h6>
                            <p><strong>Transaction ID:</strong> #${txn.id}</p>
                            <p><strong>Date:</strong> ${new Date(txn.requested_at).toLocaleString()}</p>
                            <p><strong>Status:</strong> <span class="badge bg-${txn.status === 'approved' ? 'success' : txn.status === 'rejected' ? 'danger' : 'warning'}">${txn.status.toUpperCase()}</span></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Payment Details</h6>
                            <p><strong>Payment Type:</strong> ${paymentLabel}</p>
                            <p><strong>Property Price:</strong> ₱${parseFloat(txn.property_price).toLocaleString('en-PH', {minimumFractionDigits: 2})}</p>
                            <p><strong>Amount Due:</strong> <span class="text-success fs-5">₱${parseFloat(txn.amount_due).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span></p>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Tenant Information</h6>
                            <p><strong>Name:</strong> ${tenantName}</p>
                            <p><strong>Email:</strong> ${txn.tenant_email}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Owner Information</h6>
                            <p><strong>Name:</strong> ${ownerName}</p>
                            <p><strong>Email:</strong> ${txn.owner_email}</p>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <h6>Property Information</h6>
                            <p><strong>Title:</strong> ${txn.property_title}</p>
                            <p><strong>Address:</strong> ${txn.property_address}</p>
                        </div>
                    </div>
                `;

                document.getElementById('modalBody').innerHTML = html;
                new bootstrap.Modal(document.getElementById('detailsModal')).show();
            }
        }

        // Export to CSV
        function exportToCSV() {
            const transactions = <?= json_encode($transactions) ?>;
            let csv = 'ID,Date,Property,Tenant,Owner,Payment Type,Amount,Status\n';

            transactions.forEach(txn => {
                const tenantName = `${txn.tenant_first_name} ${txn.tenant_last_name}`.trim();
                const ownerName = `${txn.owner_first_name} ${txn.owner_last_name}`.trim();
                const paymentLabel = txn.payment_method === 'half' ? 'Half' : 'Full';

                csv += `${txn.id},"${txn.requested_at}","${txn.property_title}","${tenantName}","${ownerName}",${paymentLabel},${txn.amount_due},${txn.status}\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `hanapbahay_transactions_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
