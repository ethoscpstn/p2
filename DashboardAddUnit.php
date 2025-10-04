<?php
session_start();

// Security constant for config
define('HANAPBAHAY_SECURE', true);

require 'mysql_connect.php';
require 'config_keys.php';
require 'includes/csrf.php';

// 🔐 Require owner login
if (!isset($_SESSION['owner_id'])) {
  header("Location: LoginModule.php");
  exit();
}

$errors = [];
$owner_id = (int)$_SESSION['owner_id'];

// 📝 Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Verify CSRF token
  csrf_verify();

  // Basic sanitation
  $property_type = trim($_POST['property_type'] ?? '');
  $capacity      = (int)($_POST['capacity'] ?? 0);
  $description   = trim($_POST['description'] ?? '');
  $price         = (float)($_POST['price'] ?? 0);
  $rental_type   = trim($_POST['rental_type'] ?? 'residential');

  $address   = trim($_POST['address'] ?? '');
  $latitude  = isset($_POST['latitude'])  ? (float)$_POST['latitude']  : null;
  $longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;

  // amenities[] will be values like: wifi, parking, aircon, ...
  $amenities_arr = isset($_POST['amenities']) && is_array($_POST['amenities']) ? $_POST['amenities'] : [];
  $amenities_arr = array_map(function($a){ return strtolower(trim($a)); }, $amenities_arr);
  $amenities = implode(', ', $amenities_arr);

  // -------- Server-side validation --------
  if ($property_type === '') $errors[] = "Property Type is required.";
  if ($capacity <= 0)        $errors[] = "Capacity must be at least 1.";
  if ($price < 0)            $errors[] = "Price cannot be negative.";
  if ($address === '')       $errors[] = "Address is required.";

  // Coordinates should be present (from geocode flow)
  if ($latitude === null || $longitude === null) {
    $errors[] = "Could not determine map location for this address.";
  }

  // Government ID validation (required)
  $gov_id_path = null;
  if (empty($_FILES['gov_id']['name']) || $_FILES['gov_id']['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = "Government ID is required for verification.";
  }

  // Legal documents validation based on rental type
  if ($rental_type === 'residential') {
    // Residential: Barangay permit required
    if (empty($_FILES['barangay_permit']['name']) || $_FILES['barangay_permit']['error'] === UPLOAD_ERR_NO_FILE) {
      $errors[] = "Barangay permit is required for residential rental properties.";
    }
  } elseif ($rental_type === 'commercial') {
    // Commercial: DTI/SEC, Business permit, and BIR permit required
    if (empty($_FILES['dti_sec_permit']['name']) || $_FILES['dti_sec_permit']['error'] === UPLOAD_ERR_NO_FILE) {
      $errors[] = "DTI or SEC permit is required for commercial rental properties.";
    }
    if (empty($_FILES['business_permit']['name']) || $_FILES['business_permit']['error'] === UPLOAD_ERR_NO_FILE) {
      $errors[] = "Mayor's/Business permit is required for commercial rental properties.";
    }
    if (empty($_FILES['bir_permit']['name']) || $_FILES['bir_permit']['error'] === UPLOAD_ERR_NO_FILE) {
      $errors[] = "BIR permit is required for commercial rental properties.";
    }
  }

  // Property photos validation (1-3 required)
  $photo_count = 0;
  if (isset($_FILES['property_photos'])) {
    foreach ($_FILES['property_photos']['error'] as $err) {
      if ($err !== UPLOAD_ERR_NO_FILE) $photo_count++;
    }
  }
  if ($photo_count < 1) {
    $errors[] = "At least 1 property photo is required.";
  } elseif ($photo_count > 3) {
    $errors[] = "Maximum 3 property photos allowed.";
  }

  // Process government ID upload
  if (!$errors && !empty($_FILES['gov_id']['name']) && $_FILES['gov_id']['error'] === UPLOAD_ERR_OK) {
    $allowed = [
      'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
      'image/gif' => 'gif', 'application/pdf' => 'pdf'
    ];
    $mime = function_exists('mime_content_type') ? mime_content_type($_FILES['gov_id']['tmp_name']) : $_FILES['gov_id']['type'];
    if (!isset($allowed[$mime])) {
      $errors[] = "Invalid government ID file type. Only JPG, PNG, WebP, GIF, or PDF allowed.";
    } elseif ($_FILES['gov_id']['size'] > 10 * 1024 * 1024) {
      $errors[] = "Government ID file too large (max 10 MB).";
    } else {
      $dir = __DIR__ . "/uploads/gov_ids/" . date('Ymd');
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $ext = $allowed[$mime];
      $safeName = "gov_" . $owner_id . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
      $abs = $dir . "/" . $safeName;
      if (@move_uploaded_file($_FILES['gov_id']['tmp_name'], $abs)) {
        $gov_id_path = "uploads/gov_ids/" . date('Ymd') . "/" . $safeName;
      } else {
        $errors[] = "Failed to save government ID.";
      }
    }
  }

  // Process legal documents based on rental type
  $barangay_permit_path = null;
  $dti_sec_permit_path = null;
  $business_permit_path = null;
  $bir_permit_path = null;

  $allowed_docs = [
    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
    'image/gif' => 'gif', 'application/pdf' => 'pdf'
  ];

  // Process Barangay Permit (Residential)
  if (!$errors && $rental_type === 'residential' && !empty($_FILES['barangay_permit']['name']) && $_FILES['barangay_permit']['error'] === UPLOAD_ERR_OK) {
    $mime = function_exists('mime_content_type') ? mime_content_type($_FILES['barangay_permit']['tmp_name']) : $_FILES['barangay_permit']['type'];
    if (!isset($allowed_docs[$mime])) {
      $errors[] = "Invalid barangay permit file type. Only JPG, PNG, WebP, GIF, or PDF allowed.";
    } elseif ($_FILES['barangay_permit']['size'] > 10 * 1024 * 1024) {
      $errors[] = "Barangay permit file too large (max 10 MB).";
    } else {
      $dir = __DIR__ . "/uploads/legal_docs/" . date('Ymd');
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $ext = $allowed_docs[$mime];
      $safeName = "barangay_" . $owner_id . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
      $abs = $dir . "/" . $safeName;
      if (@move_uploaded_file($_FILES['barangay_permit']['tmp_name'], $abs)) {
        $barangay_permit_path = "uploads/legal_docs/" . date('Ymd') . "/" . $safeName;
      } else {
        $errors[] = "Failed to save barangay permit.";
      }
    }
  }

  // Process DTI/SEC Permit (Commercial)
  if (!$errors && $rental_type === 'commercial' && !empty($_FILES['dti_sec_permit']['name']) && $_FILES['dti_sec_permit']['error'] === UPLOAD_ERR_OK) {
    $mime = function_exists('mime_content_type') ? mime_content_type($_FILES['dti_sec_permit']['tmp_name']) : $_FILES['dti_sec_permit']['type'];
    if (!isset($allowed_docs[$mime])) {
      $errors[] = "Invalid DTI/SEC permit file type. Only JPG, PNG, WebP, GIF, or PDF allowed.";
    } elseif ($_FILES['dti_sec_permit']['size'] > 10 * 1024 * 1024) {
      $errors[] = "DTI/SEC permit file too large (max 10 MB).";
    } else {
      $dir = __DIR__ . "/uploads/legal_docs/" . date('Ymd');
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $ext = $allowed_docs[$mime];
      $safeName = "dti_sec_" . $owner_id . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
      $abs = $dir . "/" . $safeName;
      if (@move_uploaded_file($_FILES['dti_sec_permit']['tmp_name'], $abs)) {
        $dti_sec_permit_path = "uploads/legal_docs/" . date('Ymd') . "/" . $safeName;
      } else {
        $errors[] = "Failed to save DTI/SEC permit.";
      }
    }
  }

  // Process Business Permit (Commercial)
  if (!$errors && $rental_type === 'commercial' && !empty($_FILES['business_permit']['name']) && $_FILES['business_permit']['error'] === UPLOAD_ERR_OK) {
    $mime = function_exists('mime_content_type') ? mime_content_type($_FILES['business_permit']['tmp_name']) : $_FILES['business_permit']['type'];
    if (!isset($allowed_docs[$mime])) {
      $errors[] = "Invalid business permit file type. Only JPG, PNG, WebP, GIF, or PDF allowed.";
    } elseif ($_FILES['business_permit']['size'] > 10 * 1024 * 1024) {
      $errors[] = "Business permit file too large (max 10 MB).";
    } else {
      $dir = __DIR__ . "/uploads/legal_docs/" . date('Ymd');
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $ext = $allowed_docs[$mime];
      $safeName = "business_" . $owner_id . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
      $abs = $dir . "/" . $safeName;
      if (@move_uploaded_file($_FILES['business_permit']['tmp_name'], $abs)) {
        $business_permit_path = "uploads/legal_docs/" . date('Ymd') . "/" . $safeName;
      } else {
        $errors[] = "Failed to save business permit.";
      }
    }
  }

  // Process BIR Permit (Commercial)
  if (!$errors && $rental_type === 'commercial' && !empty($_FILES['bir_permit']['name']) && $_FILES['bir_permit']['error'] === UPLOAD_ERR_OK) {
    $mime = function_exists('mime_content_type') ? mime_content_type($_FILES['bir_permit']['tmp_name']) : $_FILES['bir_permit']['type'];
    if (!isset($allowed_docs[$mime])) {
      $errors[] = "Invalid BIR permit file type. Only JPG, PNG, WebP, GIF, or PDF allowed.";
    } elseif ($_FILES['bir_permit']['size'] > 10 * 1024 * 1024) {
      $errors[] = "BIR permit file too large (max 10 MB).";
    } else {
      $dir = __DIR__ . "/uploads/legal_docs/" . date('Ymd');
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $ext = $allowed_docs[$mime];
      $safeName = "bir_" . $owner_id . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
      $abs = $dir . "/" . $safeName;
      if (@move_uploaded_file($_FILES['bir_permit']['tmp_name'], $abs)) {
        $bir_permit_path = "uploads/legal_docs/" . date('Ymd') . "/" . $safeName;
      } else {
        $errors[] = "Failed to save BIR permit.";
      }
    }
  }

  // Process property photos (1-3 photos)
  $property_photos = [];
  if (!$errors && isset($_FILES['property_photos'])) {
    $allowed_img = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $dir = __DIR__ . "/uploads/property_photos/" . date('Ymd');
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

    for ($i = 0; $i < count($_FILES['property_photos']['name']); $i++) {
      if ($_FILES['property_photos']['error'][$i] === UPLOAD_ERR_OK) {
        $mime = function_exists('mime_content_type') ?
                mime_content_type($_FILES['property_photos']['tmp_name'][$i]) :
                $_FILES['property_photos']['type'][$i];
        if (!isset($allowed_img[$mime])) {
          $errors[] = "Invalid property photo file type. Only JPG, PNG, WebP, or GIF allowed.";
          break;
        }
        if ($_FILES['property_photos']['size'][$i] > 5 * 1024 * 1024) {
          $errors[] = "Property photo too large (max 5 MB each).";
          break;
        }
        $ext = $allowed_img[$mime];
        $safeName = "photo_" . $owner_id . "_" . time() . "_" . $i . "_" . bin2hex(random_bytes(4)) . "." . $ext;
        $abs = $dir . "/" . $safeName;
        if (@move_uploaded_file($_FILES['property_photos']['tmp_name'][$i], $abs)) {
          $property_photos[] = "uploads/property_photos/" . date('Ymd') . "/" . $safeName;
        } else {
          $errors[] = "Failed to save property photo.";
          break;
        }
      }
    }
  }

  if (!$errors) {
    // Convert photos array to JSON
    $photos_json = json_encode($property_photos);

    // Insert listing with verification status = pending
    $sql = "INSERT INTO tblistings
              (title, description, address, latitude, longitude, price, capacity, amenities, owner_id,
               gov_id_path, property_photos, rental_type, barangay_permit_path, dti_sec_permit_path,
               business_permit_path, bir_permit_path, verification_status)
            VALUES
              (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
      "sssdddisisssssss",
      $property_type,
      $description,
      $address,
      $latitude,
      $longitude,
      $price,
      $capacity,
      $amenities,
      $owner_id,
      $gov_id_path,
      $photos_json,
      $rental_type,
      $barangay_permit_path,
      $dti_sec_permit_path,
      $business_permit_path,
      $bir_permit_path
    );

    if ($stmt->execute()) {
      $stmt->close();
      $conn->close();
      // Regenerate CSRF token after successful submission
      csrf_regenerate();
      header("Location: DashboardUO.php?success=pending_verification");
      exit();
    } else {
      $errors[] = "Database error: " . $stmt->error;
      $stmt->close();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>HanapBahay - Add Unit</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="add_property.css?v=41">
  <link rel="stylesheet" href="darkmode.css">
  <style>
    .dashboard-header{
      display:flex; align-items:center; justify-content:space-between;
      padding:14px 16px; background:#fff; border-bottom:1px solid #e5e7eb;
      position:sticky; top:0; z-index:10;
    }
    .dashboard-header .logo{ height:36px; }
    .btn-hb-back{ background:#8B4513; color:#fff; }
    .btn-hb-back:hover{ color:#fff; opacity:.92; }
    .form-help{ font-size:.825rem; color:#6b7280; margin-top:.35rem; }
    .amenities-grid{
      display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:.35rem 1rem;
    }
    @media (max-width: 576px){
      .amenities-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
  </style>
</head>
<body class="dashboard-bg">
  <div class="d-flex" id="dashboardWrapper">
    <!-- Page Content -->
    <div id="pageContent" class="flex-grow-1">
      <header class="dashboard-header">
        <img src="Assets/Logo1.png" alt="HanapBahay Logo" class="logo" />
        <a href="DashboardUO.php" class="btn btn-hb-back">
          <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
      </header>

      <!-- Errors -->
      <?php if (!empty($errors)): ?>
        <div class="container mt-3">
          <div class="alert alert-danger">
            <strong>There were problems with your submission:</strong>
            <ul class="mb-0">
              <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

      <!-- Add Listing Form -->
      <section class="mb-4 p-4">
        <div class="container">
          <form method="POST" action="" enctype="multipart/form-data" onsubmit="return geocodeAndSubmit(event)" class="bg-white p-4 rounded shadow-sm">
            <?php echo csrf_field(); ?>

            <!-- Rental Type -->
            <div class="mb-3">
              <label class="form-label">Rental Type <span class="text-danger">*</span></label>
              <select name="rental_type" id="rentalType" class="form-select" required>
                <option value="residential">Residential (for rental only)</option>
                <option value="commercial">Commercial (apartment rental business)</option>
              </select>
              <div class="form-help">Select the type of rental to determine required legal documents.</div>
            </div>

            <!-- Property Type -->
            <div class="mb-3">
              <label class="form-label">Property Type <span class="text-danger">*</span></label>
              <select name="property_type" id="propertyType" class="form-select" required>
                <option value="">-- Select Type --</option>
                <option value="Apartment">Apartment</option>
                <option value="Condominium">Condominium</option>
                <option value="House">House</option>
              </select>
            </div>

            <!-- Capacity -->
            <div class="mb-3">
              <label class="form-label">Capacity <span class="text-danger">*</span></label>
              <input type="number" name="capacity" class="form-control" min="1" step="1" required>
            </div>
            
            <!-- Bedrooms and Unit Size - Side by Side -->
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Number of Bedrooms</label>
                <input type="number" name="bedroom" id="bedroomInput" class="form-control" min="1" step="1" value="1">
              </div>
              <div class="col-md-6">
                <label class="form-label">Unit Size (sqm)</label>
                <input type="number" name="unit_sqm" id="sqmInput" class="form-control" min="1" step="0.1" value="20">
              </div>
            </div>

            <!-- Kitchen Details - Side by Side -->
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Kitchen Available</label>
                <select name="kitchen" id="kitchenInput" class="form-select">
                  <option value="Yes">Yes</option>
                  <option value="No">No</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Kitchen Access</label>
                <select name="kitchen_type" id="kitchenTypeInput" class="form-select">
                  <option value="Private">Private</option>
                  <option value="Shared">Shared</option>
                </select>
              </div>
            </div>

            <!-- Policies - Side by Side -->
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Gender Restriction</label>
                <select name="gender_specific" id="genderInput" class="form-select">
                  <option value="Mixed">Mixed</option>
                  <option value="Male">Male Only</option>
                  <option value="Female">Female Only</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Pet Policy</label>
                <select name="pets" id="petsInput" class="form-select">
                  <option value="Allowed">Allowed</option>
                  <option value="Not Allowed">Not Allowed</option>
                </select>
              </div>
            </div>

            <!-- Description -->
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="4"></textarea>
            </div>

            <!-- Amenities -->
            <div class="mb-2">
              <label class="form-label">Amenities</label>
              <div class="amenities-grid">
                <?php
                  $ALL_AMENITIES = [
                    'wifi' => 'Wi-Fi', 'parking' => 'Parking', 'aircon' => 'Air Conditioning',
                    'kitchen' => 'Kitchen', 'laundry' => 'Laundry', 'furnished' => 'Furnished',
                    'elevator' => 'Elevator', 'security' => 'Security/CCTV', 'balcony' => 'Balcony',
                    'gym' => 'Gym', 'pool' => 'Pool', 'pet_friendly' => 'Pet Friendly',
                    'bathroom' => 'Bathroom', 'sink' => 'Sink',
                    'electricity' => 'Electricity (Submeter)', 'water' => 'Water (Submeter)'
                  ];
                ?>
                <?php foreach ($ALL_AMENITIES as $key => $label): ?>
                  <label class="form-check">
                    <input class="form-check-input" type="checkbox" name="amenities[]" value="<?= $key ?>">
                    <span class="form-check-label"><?= htmlspecialchars($label) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Address -->
            <div class="mb-3">
              <label class="form-label">Address <span class="text-danger">*</span></label>
              <input type="text" id="addressInput" name="address" class="form-control" placeholder="Start typing and pick from suggestions" required>
            </div>

            <!-- Price with Suggest Button -->
            <div class="mb-3">
              <label class="form-label">Price (₱) <span class="text-danger">*</span></label>
              <div class="d-flex gap-2">
                <input type="number" name="price" id="priceInput" class="form-control" min="0" step="1" inputmode="numeric" pattern="[0-9]*" required>
                <button type="button" class="btn btn-outline-primary" id="btnSuggestPrice">
                  <span class="btn-text">Suggest Price</span>
                  <span class="btn-loading" style="display:none;">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                    Loading...
                  </span>
                </button>
              </div>
              <div class="form-help" id="priceHint">Click "Suggest Price" to get a model-based estimate.</div>
            </div>

            <hr class="my-4">
            <h5 class="mb-3">Verification Documents</h5>

            <!-- Government ID -->
            <div class="mb-3">
              <label class="form-label">Government ID <span class="text-danger">*</span></label>
              <input type="file" name="gov_id" id="govIdInput" class="form-control" accept="image/*,.pdf" required>
              <div class="form-help">Upload a valid government ID (e.g., driver's license, passport). Accepted: JPG, PNG, WebP, GIF, PDF (max 10 MB).</div>
            </div>

            <!-- Legal Documents for Residential -->
            <div id="residentialDocs" class="legal-docs-section">
              <h6 class="mb-2">Residential Rental Documents</h6>
              <div class="mb-3">
                <label class="form-label">Barangay Permit <span class="text-danger">*</span></label>
                <input type="file" name="barangay_permit" id="barangayPermitInput" class="form-control" accept="image/*,.pdf">
                <div class="form-help">Required for residential rental properties. Accepted: JPG, PNG, WebP, GIF, PDF (max 10 MB).</div>
              </div>
            </div>

            <!-- Legal Documents for Commercial -->
            <div id="commercialDocs" class="legal-docs-section" style="display: none;">
              <h6 class="mb-2">Commercial Rental Documents</h6>
              <div class="mb-3">
                <label class="form-label">DTI or SEC Permit <span class="text-danger">*</span></label>
                <input type="file" name="dti_sec_permit" id="dtiSecPermitInput" class="form-control" accept="image/*,.pdf">
                <div class="form-help">Department of Trade and Industry or Securities and Exchange Commission permit. Accepted: JPG, PNG, WebP, GIF, PDF (max 10 MB).</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Mayor's / Business Permit <span class="text-danger">*</span></label>
                <input type="file" name="business_permit" id="businessPermitInput" class="form-control" accept="image/*,.pdf">
                <div class="form-help">Mayor's permit or business permit. Accepted: JPG, PNG, WebP, GIF, PDF (max 10 MB).</div>
              </div>
              <div class="mb-3">
                <label class="form-label">BIR Permit <span class="text-danger">*</span></label>
                <input type="file" name="bir_permit" id="birPermitInput" class="form-control" accept="image/*,.pdf">
                <div class="form-help">Bureau of Internal Revenue permit. Accepted: JPG, PNG, WebP, GIF, PDF (max 10 MB).</div>
              </div>
            </div>

            <!-- Property Photos -->
            <div class="mb-3">
              <label class="form-label">Property Photos <span class="text-danger">*</span></label>
              <input type="file" name="property_photos[]" id="propertyPhotosInput" class="form-control" accept="image/*" multiple required>
              <div class="form-help">Upload 1-3 clear photos of the property. Accepted: JPG, PNG, WebP, GIF (max 5 MB each).</div>
            </div>

            <div class="alert alert-info">
              <i class="bi bi-info-circle"></i> Your listing will be submitted for admin verification before it becomes visible to tenants.
            </div>

            <!-- Hidden coordinates -->
            <input type="hidden" id="latField" name="latitude">
            <input type="hidden" id="lngField" name="longitude">

            <button type="submit" class="btn btn-primary">Submit for Verification</button>
          </form>
        </div>
      </section>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="darkmode.js"></script>

  <!-- Toggle legal documents based on rental type -->
  <script>
    (function() {
      const rentalType = document.getElementById('rentalType');
      const residentialDocs = document.getElementById('residentialDocs');
      const commercialDocs = document.getElementById('commercialDocs');

      const barangayPermit = document.getElementById('barangayPermitInput');
      const dtiSecPermit = document.getElementById('dtiSecPermitInput');
      const businessPermit = document.getElementById('businessPermitInput');
      const birPermit = document.getElementById('birPermitInput');

      function toggleDocuments() {
        const type = rentalType.value;

        if (type === 'residential') {
          residentialDocs.style.display = 'block';
          commercialDocs.style.display = 'none';
          barangayPermit.required = true;
          dtiSecPermit.required = false;
          businessPermit.required = false;
          birPermit.required = false;
        } else if (type === 'commercial') {
          residentialDocs.style.display = 'none';
          commercialDocs.style.display = 'block';
          barangayPermit.required = false;
          dtiSecPermit.required = true;
          businessPermit.required = true;
          birPermit.required = true;
        }
      }

      rentalType.addEventListener('change', toggleDocuments);
      toggleDocuments(); // Initialize on page load
    })();
  </script>

  <!-- Price input guard -->
  <script>
        (function guardPrice(){
          const priceEl = document.getElementById('priceInput');
          if (!priceEl) return;
          const clamp = () => {
            let v = parseFloat(priceEl.value);
            if (isNaN(v) || v < 0) priceEl.value = 0;
            priceEl.value = String(Math.floor(parseFloat(priceEl.value || 0)));
          };
          priceEl.addEventListener('input', clamp);
          priceEl.addEventListener('blur', clamp);
        })();
      </script>
    
      <!-- Google Places Autocomplete + Geocoding -->
      <script>
        let autocomplete, geocoder, pickedPlace = null;
    
        function initPlaces() {
          const input = document.getElementById('addressInput');
          geocoder = new google.maps.Geocoder();
    
          autocomplete = new google.maps.places.Autocomplete(input, {
            fields: ['formatted_address', 'geometry'],
            componentRestrictions: { country: 'ph' }
          });
    
          autocomplete.addListener('place_changed', () => {
            pickedPlace = autocomplete.getPlace();
            if (pickedPlace && pickedPlace.geometry && pickedPlace.geometry.location) {
              const lat = pickedPlace.geometry.location.lat();
              const lng = pickedPlace.geometry.location.lng();
              document.getElementById('latField').value = lat;
              document.getElementById('lngField').value = lng;
    
              if (pickedPlace.formatted_address) {
                input.value = pickedPlace.formatted_address;
              }
            } else {
              pickedPlace = null;
            }
          });
        }
    
        async function geocodeAndSubmit(e){
          e.preventDefault();
          const form = e.target;
          const input = document.getElementById('addressInput');
          const latEl = document.getElementById('latField');
          const lngEl = document.getElementById('lngField');
    
          const alreadyHasCoords = latEl.value && lngEl.value;
          if (alreadyHasCoords) { form.submit(); return false; }
    
          const addr = (input.value || '').trim();
          if (!addr) { alert('Please enter the address.'); return false; }
    
          // Try Google Geocoder
          try {
            const g = await geocoder.geocode({ address: addr + ', Philippines' });
            if (g.status === 'OK' && g.results && g.results[0]) {
              const best = g.results[0];
              latEl.value = best.geometry.location.lat();
              lngEl.value = best.geometry.location.lng();
              input.value = best.formatted_address || addr;
              form.submit();
              return false;
            }
          } catch (err) {
            // Continue to fallback
          }
    
          // Fallback to Nominatim
          try {
            const url = 'https://nominatim.openstreetmap.org/search?format=json&addressdetails=0&limit=1&q='
                        + encodeURIComponent(addr + ', Philippines');
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await resp.json();
            if (Array.isArray(data) && data.length > 0) {
              latEl.value = data[0].lat;
              lngEl.value = data[0].lon;
              if (!/philippines/i.test(input.value)) input.value = (data[0].display_name || addr);
              form.submit();
              return false;
            }
          } catch (e2) {
            // Ignore
          }
    
          alert("Could not geocode the address. Please check the address or try a nearby landmark.");
          return false;
        }
    
        window.initPlaces = initPlaces;
      </script>
    
      <!-- Load Google Maps -->
      <script src="https://maps.googleapis.com/maps/api/js?key=<?= urlencode(GOOGLE_MAPS_API_KEY) ?>&libraries=places&callback=initPlaces" async defer></script>
      
      <!-- ML Price Suggestion -->
        <script>
        (function () {
          const $ = (sel) => document.querySelector(sel);
        
          function guessCityFromAddress(addr){
            const cities = ["Quezon City","Manila","Makati","Pasig","Taguig","Caloocan","Mandaluyong","Marikina","Parañaque","Las Piñas","Valenzuela","Pasay","San Juan"];
            for (const c of cities){ 
              if (addr && addr.toLowerCase().includes(c.toLowerCase())) return c; 
            }
            return "NCR";
          }
        
          async function suggestPrice(){
            console.log('=== Suggest Price Clicked ===');

            // Show loading state
            const btn = document.getElementById('btnSuggestPrice');
            const btnText = btn?.querySelector('.btn-text');
            const btnLoading = btn?.querySelector('.btn-loading');
            if (btn) btn.disabled = true;
            if (btnText) btnText.style.display = 'none';
            if (btnLoading) btnLoading.style.display = 'inline-block';

            // Get elements
            const capacityEl  = $('[name="capacity"]');
            const bedroomEl   = $('#bedroomInput');
            const sqmEl       = $('#sqmInput');
            const typeEl      = $('#propertyType');
            const kitchenEl   = $('#kitchenInput');
            const kitchenTyEl = $('#kitchenTypeInput');
            const genderEl    = $('#genderInput');
            const petsEl      = $('#petsInput');
            const addressEl   = $('#addressInput');
            
            // Validate elements exist
            if (!capacityEl || !bedroomEl || !sqmEl || !typeEl || !addressEl) {
              alert('Please ensure all required fields are filled');
              console.error('Missing form elements');
              return;
            }
            
            // Get values with proper defaults
            const capacity  = Number(capacityEl.value) || 1;
            const bedroom   = Number(bedroomEl.value) || 1;
            const sqm       = Number(sqmEl.value) || 20;
            const typeLabel = (typeEl.value || 'Apartment').trim();
            const kitchen   = (kitchenEl?.value || 'Yes').trim();
            const kitchenTy = (kitchenTyEl?.value || 'Private').trim();
            const gender    = (genderEl?.value || 'Mixed').trim();
            const pets      = (petsEl?.value || 'Allowed').trim();
            const address   = (addressEl.value || '').trim();
            const location  = guessCityFromAddress(address);
            
            console.log('Values:', {
              capacity, bedroom, sqm, typeLabel, kitchen, kitchenTy, 
              gender, pets, address, location
            });
            
            // Validation
            if (!typeLabel || typeLabel === '') {
              alert('Please select a Property Type first');
              return;
            }
            if (!address) {
              alert('Please enter an Address first');
              return;
            }
        
            // Build payload
            const payload = {
              inputs: [{
                "Capacity":        capacity,
                "Bedroom":         bedroom,
                "unit_sqm":        sqm,
                "cap_per_bedroom": bedroom > 0 ? (capacity / bedroom) : capacity,
                "Type":            typeLabel,
                "Kitchen":         kitchen,
                "Kitchen type":    kitchenTy,
                "Gender specific": gender,
                "Pets":            pets,
                "Location":        location
              }]
            };
        
            console.log('Payload:', JSON.stringify(payload, null, 2));
        
            const hint = document.getElementById('priceHint');
            if (hint) hint.textContent = 'Fetching suggestion…';
        
            try {
              // Auto-detect correct API path for localhost vs production
              const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
              const apiPath = isLocalhost ? '/public_html/api/ml_suggest_price.php' : '/api/ml_suggest_price.php';

              console.log('Sending POST to', apiPath);

              const res = await fetch(apiPath, {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify(payload)
              });
              
              console.log('Response status:', res.status);
              
              // Get raw text first to see the error
              const text = await res.text();
              console.log('Raw response:', text);
              
              // Try to parse as JSON
              let data;
              try {
                data = JSON.parse(text);
              } catch (e) {
                if (hint) hint.textContent = 'Server error - check console';
                console.error('Response is not JSON:', text);
                return;
              }
              
              console.log('Response data:', data);
              
              if (data && data.prediction){
                const priceInput = document.getElementById('priceInput');
                if (priceInput) {
                  priceInput.value = Math.round(data.prediction);
                }
                
                if (hint) {
                  if (data.interval){
                    const l = Math.round(data.interval.low).toLocaleString();
                    const p = Math.round(data.interval.pred).toLocaleString();
                    const h = Math.round(data.interval.high).toLocaleString();
                    hint.textContent = `Suggested: ₱${p} (likely ₱${l}–₱${h})`;
                  } else {
                    hint.textContent = `Suggested: ₱${Math.round(data.prediction).toLocaleString()}`;
                  }
                }
              } else if (data && data.error) {
                if (hint) hint.textContent = `Error: ${data.error}`;
                console.error('ML API Error:', data);
              } else {
                if (hint) hint.textContent = 'No suggestion available.';
                console.error('Unexpected response:', data);
              }
            } catch (err) {
              if (hint) hint.textContent = 'Could not contact ML service.';
              console.error('Fetch error:', err);
            } finally {
              // Hide loading state
              if (btn) btn.disabled = false;
              if (btnText) btnText.style.display = 'inline';
              if (btnLoading) btnLoading.style.display = 'none';
            }
          }
        
          const btn = document.getElementById('btnSuggestPrice');
          if (btn) {
            console.log('Button found, attaching listener');
            btn.addEventListener('click', suggestPrice);
          } else {
            console.error('Suggest Price button NOT found!');
          }
        })();
    </script>

</body>
</html>