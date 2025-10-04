<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/ml_client.php';

$test_data = [[
    "Capacity" => 2,
    "Bedroom" => 1,
    "unit_sqm" => 25,
    "cap_per_bedroom" => 2,
    "Type" => "Apartment",
    "Kitchen" => "Yes",
    "Kitchen type" => "Private",
    "Gender specific" => "Mixed",
    "Pets" => "Allowed",
    "Location" => "Quezon City"
]];

echo "Testing ML connection...\n";
$result = ml_predict($test_data, ML_BASE, ML_KEY);
echo json_encode($result, JSON_PRETTY_PRINT);