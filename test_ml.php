<?php
// public/test_ml.php
require_once __DIR__ . '/../includes/ml_client.php';

function block($title, $data) {
    echo "<h3>{$title}</h3><pre style='white-space:pre-wrap;'>"
       . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))
       . "</pre><hr>";
}

block('/version', ml_version());
block('/health',  ml_health());

$sample = [[
  'Capacity'=>3,'Bedroom'=>1,'unit_sqm'=>24,'cap_per_bedroom'=>3,
  'Type'=>'Apartment','Kitchen'=>'Yes','Kitchen type'=>'Shared',
  'Gender specific'=>'Mixed','Pets'=>'Allowed','Location'=>'Makati'
]];
block('/predict', ml_predict($sample));
block('/price_interval', ml_price_interval($sample));

$listings = [
  ['id'=>1,'Capacity'=>2,'Bedroom'=>1,'unit_sqm'=>20,'cap_per_bedroom'=>2,'Type'=>'Condo','Kitchen'=>'Yes','Kitchen type'=>'Private','Gender specific'=>'Mixed','Pets'=>'Allowed','Location'=>'Quezon City','dist_school_km'=>0.5,'dist_work_km'=>1.2],
  ['id'=>2,'Capacity'=>3,'Bedroom'=>2,'unit_sqm'=>35,'cap_per_bedroom'=>1.5,'Type'=>'Apartment','Kitchen'=>'Yes','Kitchen type'=>'Shared','Gender specific'=>'Mixed','Pets'=>'Not allowed','Location'=>'Makati','dist_school_km'=>1.0,'dist_work_km'=>0.8],
];
$user_pref = ['budget'=>15000];
block('/recommend', ml_recommend($listings, $user_pref));
block('/comps',      ml_comps($listings[0], $listings, 2));
block('/loc_score',  ml_loc_score([['dist_school_km'=>0.5,'dist_work_km'=>1.2,'rooms_score'=>0.6,'safety_index'=>0.7]]));
