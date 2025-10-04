<?php
// Temporary migration script - run this once then delete it
require 'mysql_connect.php';

echo "<h2>Running Database Migration...</h2>";

// Add columns
$queries = [
    "ALTER TABLE `tblistings` ADD COLUMN `gov_id_path` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Path to government ID file' AFTER `amenities`",
    "ALTER TABLE `tblistings` ADD COLUMN `property_photos` TEXT NULL DEFAULT NULL COMMENT 'JSON array of property photo paths' AFTER `gov_id_path`",
    "ALTER TABLE `tblistings` ADD COLUMN `verification_status` VARCHAR(20) NULL DEFAULT NULL COMMENT 'pending, approved, rejected' AFTER `property_photos`",
    "ALTER TABLE `tblistings` ADD COLUMN `rejection_reason` TEXT NULL DEFAULT NULL COMMENT 'Reason for rejection if status is rejected' AFTER `verification_status`",
    "CREATE INDEX `idx_verification_status` ON `tblistings` (`verification_status`)",
    "CREATE INDEX `idx_is_verified_archived` ON `tblistings` (`is_verified`, `is_archived`)"
];

foreach ($queries as $i => $sql) {
    echo "<p>Running query " . ($i + 1) . "...</p>";
    try {
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✓ Success: " . htmlspecialchars($sql) . "</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Warning: " . htmlspecialchars($conn->error) . "</p>";
            echo "<p>Query: " . htmlspecialchars($sql) . "</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠ Warning: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>Query: " . htmlspecialchars($sql) . "</p>";
    }
}

$conn->close();

echo "<h3 style='color: green;'>Migration Complete!</h3>";
echo "<p><strong>Important:</strong> Delete this file (run_migration.php) after running it once for security.</p>";
?>
