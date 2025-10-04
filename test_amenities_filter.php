<?php
// Test file to verify amenities filtering
require 'mysql_connect.php';

// Simulate selecting "wifi" amenity
$test_amenity = 'wifi';

echo "<h2>Testing Amenities Filter</h2>";
echo "<p>Testing filter for: <strong>$test_amenity</strong></p>";

// Build WHERE clause
$where = ["is_archived = 0"];
$amenity_esc = $conn->real_escape_string(trim($test_amenity));
$where[] = "(amenities LIKE '%{$amenity_esc}%')";
$where_sql = implode(" AND ", $where);

echo "<h3>Generated SQL WHERE clause:</h3>";
echo "<pre>" . htmlspecialchars($where_sql) . "</pre>";

// Run query
$sql = "SELECT id, title, amenities FROM tblistings WHERE {$where_sql}";
echo "<h3>Full SQL Query:</h3>";
echo "<pre>" . htmlspecialchars($sql) . "</pre>";

$result = $conn->query($sql);

echo "<h3>Results:</h3>";
if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Title</th><th>Amenities</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . htmlspecialchars($row['amenities']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><strong>Total: " . $result->num_rows . " properties with '$test_amenity'</strong></p>";
} else {
    echo "<p style='color: red;'><strong>No properties found with amenity: $test_amenity</strong></p>";
}

// Now test WITHOUT the filter
echo "<hr>";
echo "<h3>All Properties (No Filter):</h3>";
$sql2 = "SELECT id, title, amenities FROM tblistings WHERE is_archived = 0";
$result2 = $conn->query($sql2);

if ($result2 && $result2->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Title</th><th>Amenities</th></tr>";
    while ($row = $result2->fetch_assoc()) {
        $has_wifi = stripos($row['amenities'], 'wifi') !== false;
        $style = $has_wifi ? "background-color: #d4edda;" : "";
        echo "<tr style='$style'>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . htmlspecialchars($row['amenities'] ?: '(no amenities)') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><strong>Total: " . $result2->num_rows . " properties (highlighted = has wifi)</strong></p>";
} else {
    echo "<p>No properties found in database</p>";
}

$conn->close();
?>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; margin: 20px 0; }
th { background: #8B4513; color: white; }
</style>
