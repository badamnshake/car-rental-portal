<?php
session_start();
include 'includes/config.php';
require_once 'includes/encryption.php';

if (strlen($_SESSION['login']) == 0) {
  header('location:index.php');
  exit();
}

$useremail = $_SESSION['login'];
$uploadPath = "uploads/";

if (!is_dir($uploadPath)) {
  mkdir($uploadPath, 0755, true);
}

$success = '';
$error = '';
$isVerified = false;

function normalizeString($str) {
    // Convert to lowercase and remove extra spaces
    $str = strtolower(trim($str));
    
    // Remove special characters and keep only letters and spaces
    $str = preg_replace('/[^a-z\s]/', '', $str);
    
    // Replace multiple spaces with single space
    $str = preg_replace('/\s+/', ' ', $str);
    
    return $str;
}

function extractNames($fullName) {
    // Split full name into parts
    $parts = explode(' ', normalizeString($fullName));
    
    // Remove empty parts
    $parts = array_filter($parts, function($part) {
        return !empty(trim($part));
    });
    
    return array_values($parts); // Re-index array
}

function namesMatch($profileName, $passportSurname, $passportNames) {
    // Normalize all inputs
    $profileParts = extractNames($profileName);
    $passportSurname = normalizeString($passportSurname);
    $passportNames = normalizeString($passportNames);
    
    // Split passport names (given names)
    $passportGivenNames = array_filter(explode(' ', $passportNames));
    
    // Combine all passport name parts
    $allPassportParts = array_merge([$passportSurname], $passportGivenNames);
    $allPassportParts = array_filter($allPassportParts);
    
    // Check if profile has at least 2 name parts (first + last)
    if (count($profileParts) < 2) {
        return [
            'match' => false,
            'reason' => 'Profile name must contain at least first and last name'
        ];
    }
    
    // Strategy 1: Check if surname matches last name in profile
    $profileLastName = end($profileParts);
    if ($passportSurname !== $profileLastName) {
        return [
            'match' => false,
            'reason' => "Surname mismatch: Profile '{$profileLastName}' vs Passport '{$passportSurname}'"
        ];
    }
    
    // Strategy 2: Check if at least one given name matches
    $profileFirstNames = array_slice($profileParts, 0, -1); // All except last
    $matchedGivenNames = 0;
    
    foreach ($profileFirstNames as $profileFirstName) {
        foreach ($passportGivenNames as $passportGivenName) {
            // Check exact match or if one contains the other (for nicknames)
            if ($profileFirstName === $passportGivenName || 
                strpos($profileFirstName, $passportGivenName) !== false ||
                strpos($passportGivenName, $profileFirstName) !== false) {
                $matchedGivenNames++;
                break;
            }
        }
    }
    
    // Require at least one given name to match
    if ($matchedGivenNames === 0) {
        return [
            'match' => false,
            'reason' => 'No matching given names found between profile and passport'
        ];
    }
    
    // Calculate match confidence
    $totalProfileNames = count($profileParts);
    $totalPassportNames = count($allPassportParts);
    $matchConfidence = (($matchedGivenNames + 1) / max($totalProfileNames, $totalPassportNames)) * 100;
    
    return [
        'match' => true,
        'confidence' => round($matchConfidence, 2),
        'matched_names' => $matchedGivenNames + 1, // +1 for surname
        'reason' => 'Names match successfully'
    ];
}

function isPassportValid($result, $profileFullName) {
    // Check if basic response is valid
    if (!isset($result['success']) || $result['success'] !== true) {
        return [
            'valid' => false,
            'reason' => 'API request failed'
        ];
    }
    
    // Check MRZ validity - most important check
    if (!isset($result['valid_mrz']) || $result['valid_mrz'] !== true) {
        return [
            'valid' => false,
            'reason' => 'Invalid MRZ format'
        ];
    }
    
    // Check score threshold
    $validScore = $result['valid_score'] ?? 0;
    if ($validScore < 70) {
        return [
            'valid' => false,
            'reason' => "Validation score too low: {$validScore}%"
        ];
    }
    
    // Check individual field validations in parsed_data
    $parsedData = $result['parsed_data'] ?? [];
    $requiredFields = ['valid_number', 'valid_date_of_birth', 'valid_expiration_date'];
    $validFieldCount = 0;
    
    foreach ($requiredFields as $field) {
        if (isset($parsedData[$field]) && $parsedData[$field] === true) {
            $validFieldCount++;
        }
    }
    
    if ($validFieldCount < 2) {
        return [
            'valid' => false,
            'reason' => 'Insufficient valid passport fields'
        ];
    }
    
    // NEW: Check name matching
    $passportSurname = $parsedData['surname'] ?? '';
    $passportNames = $parsedData['names'] ?? '';
    
    if (empty($passportSurname) || empty($passportNames)) {
        return [
            'valid' => false,
            'reason' => 'Missing name information in passport'
        ];
    }
    
    $nameCheck = namesMatch($profileFullName, $passportSurname, $passportNames);
    
    if (!$nameCheck['match']) {
        return [
            'valid' => false,
            'reason' => 'Name verification failed: ' . $nameCheck['reason']
        ];
    }
    
    return [
        'valid' => true,
        'reason' => 'All validations passed',
        'name_confidence' => $nameCheck['confidence'] ?? 0,
        'validation_score' => $validScore
    ];
}

function callPassportScanner($imageData, $isBase64 = false)
{
  if ($isBase64) {
    $imageData = str_replace(array("\r", "\n"), '', $imageData);
  }
  
  $url = 'http://passport-api:8000/scan';
  $payload = json_encode(['image_base64' => $isBase64 ? $imageData : base64_encode($imageData)]);

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($payload)
  ]);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Add timeout

  $response = curl_exec($ch);
  
  if (curl_errno($ch)) {
    echo 'Request Error: ' . curl_error($ch);
    curl_close($ch);
    return;
  }
  
  curl_close($ch);
  
  // Log API response for debugging
  error_log("API Response: " . $response);
  
  $result = json_decode($response, true);
  
  // Check for valid JSON response
  if ($result === null) {
    echo "API Error: Invalid response from passport scanner";
    return;
  }
  
  $conn = $GLOBALS['dbh'];
  $useremail = $_SESSION['login'];

  $stmt = $conn->prepare("SELECT id, dob, fullname FROM tblusers WHERE EmailId = :email");
  $stmt->bindParam(':email', $useremail, PDO::PARAM_STR);
  $stmt->execute();
  $userData = $stmt->fetchAll(PDO::FETCH_OBJ);

  if (empty($userData)) {
    echo "User not found";
    return;
  }

  $userId = $userData[0]->id;
  $profileFullName = $userData[0]->fullname; // Get user's full name
  
  // Use enhanced validation with name matching
  $validation = isPassportValid($result, $profileFullName);
  $isActuallyValid = $validation['valid'];

  // Debug section (uncomment for testing)
  /*
  echo "<h3>DEBUG INFO:</h3>";
  echo "<pre>Profile Name: " . htmlspecialchars($profileFullName) . "</pre>";
  echo "<pre>isActuallyValid: " . ($isActuallyValid ? 'TRUE' : 'FALSE') . "</pre>";
  echo "<pre>Validation Reason: " . htmlspecialchars($validation['reason']) . "</pre>";
  
  $parsedData = $result['parsed_data'] ?? [];
  echo "<pre>Passport Surname: " . htmlspecialchars($parsedData['surname'] ?? 'N/A') . "</pre>";
  echo "<pre>Passport Names: " . htmlspecialchars($parsedData['names'] ?? 'N/A') . "</pre>";
  
  if (isset($validation['name_confidence'])) {
      echo "<pre>Name Match Confidence: " . $validation['name_confidence'] . "%</pre>";
  }
  die();
  */

  if (!$isActuallyValid) {
    
    // Convert to binary if needed
    if ($isBase64) {
      $imageData = base64_decode($imageData);
    }

    $aesKey = generateAESKey();
    $iv = generateIV();
    $encryptedImage = encryptImage($imageData, $aesKey, $iv);
    $encryptedKey = encryptAESKeyWithRSA($aesKey);

    $stmt = $conn->prepare(
      "INSERT INTO tbluserphotos (user_id, photo_base64, aes_key, iv)
        VALUES (:userId, :image, :key, :iv)
        ON DUPLICATE KEY UPDATE photo_base64 = :image, aes_key = :key, iv = :iv"
    );
    
    $stmt->bindValue(':userId', $userId);
    $stmt->bindValue(':image', base64_encode($encryptedImage)); // For LONGTEXT
    $stmt->bindValue(':key', $encryptedKey);
    $stmt->bindValue(':iv', base64_encode($iv));
    
    try {
      $stmt->execute();
    } catch (PDOException $e) {
      error_log("Database error: " . $e->getMessage());
      echo "Database error occurred";
      return;
    }

    $conn->query("UPDATE tblusers SET is_verified = 0, verification_pending = 1 WHERE id = '$userId'");

    // Enhanced error message with specific reason
    echo "Passport validation failed: " . $validation['reason'];
    $_SESSION['verification_pending'] = 1;
    $_SESSION['is_verified'] = 0;

  } else {
    // SUCCESS - passport passed all validation checks including name matching
    $conn->query("UPDATE tblusers SET is_verified = 1, verification_pending = 0 WHERE EmailId = '$useremail'");

    $_SESSION['is_verified'] = 1;
    $_SESSION['verification_pending'] = 0;
    
    $nameConfidence = $validation['name_confidence'] ?? 0;
    $validationScore = $validation['validation_score'] ?? 0;
    
    echo "Passport verified successfully! Validation score: {$validationScore}%, Name match: {$nameConfidence}%";
  }

  header('Location: ' . $_SERVER['REQUEST_URI']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!empty($_FILES['id_image']['tmp_name'])) {
    // Case 1: File Upload
    $fileTmpPath = $_FILES['id_image']['tmp_name'];
    $fileName = basename($_FILES['id_image']['name']);
    $targetPath = $uploadPath . $fileName;
    $PhotoTypes = ['image/png', 'image/jpg', 'image/jpeg'];

    if (in_array($_FILES['id_image']['type'], $PhotoTypes)) {
      $imageData = file_get_contents($_FILES['id_image']['tmp_name']);
      callPassportScanner($imageData, false);
    } else {
      $error = "Error file type. Please upload JPG or PNG.";
    }

  } elseif (!empty($_POST['photoInfo'])) {
    // Case 2: Webcam
    $photoInfo = $_POST['photoInfo'];
    $photoInfo = str_replace('data:image/png;base64,', '', $photoInfo);
    $photoInfo = str_replace(' ', '+', $photoInfo);
    callPassportScanner($photoInfo, true);
  } else {
    $error = "No image data provided.";
  }
}
?>

<!DOCTYPE HTML>
<html lang="en">

<head>
  <title>Get Verified</title>
  <!--Bootstrap -->
  <link rel="stylesheet" href="assets/css/bootstrap.min.css" type="text/css">
  <!--Custome Style -->
  <link rel="stylesheet" href="assets/css/style.css" type="text/css">
  <!--OWL Carousel slider-->
  <link rel="stylesheet" href="assets/css/owl.carousel.css" type="text/css">
  <link rel="stylesheet" href="assets/css/owl.transitions.css" type="text/css">
  <!--slick-slider -->
  <link href="assets/css/slick.css" rel="stylesheet">
  <!--bootstrap-slider -->
  <link href="assets/css/bootstrap-slider.min.css" rel="stylesheet">
  <!--FontAwesome Font Style -->
  <link href="assets/css/font-awesome.min.css" rel="stylesheet">

  <!-- SWITCHER -->
  <link rel="stylesheet" id="switcher-css" type="text/css" href="assets/switcher/css/switcher.css" media="all" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/red.css" title="red" media="all"
    data-default-color="true" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/orange.css" title="orange" media="all" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/blue.css" title="blue" media="all" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/pink.css" title="pink" media="all" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/green.css" title="green" media="all" />
  <link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/purple.css" title="purple" media="all" />
  <link rel="apple-touch-icon-precomposed" sizes="144x144"
    href="assets/images/favicon-icon/apple-touch-icon-144-precomposed.png">
  <link rel="apple-touch-icon-precomposed" sizes="114x114"
    href="assets/images/favicon-icon/apple-touch-icon-114-precomposed.html">
  <link rel="apple-touch-icon-precomposed" sizes="72x72"
    href="assets/images/favicon-icon/apple-touch-icon-72-precomposed.png">
  <link rel="apple-touch-icon-precomposed" href="assets/images/favicon-icon/apple-touch-icon-57-precomposed.png">
  <link rel="shortcut icon" href="assets/images/favicon-icon/favicon.png">
  <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700,900" rel="stylesheet">
  <style>
    .errorWrap {
      padding: 10px;
      margin: 0 0 20px 0;
      background: #fff;
      border-left: 4px solid #dd3d36;
      -webkit-box-shadow: 0 1px 1px 0 rgba(0, 0, 0, .1);
      box-shadow: 0 1px 1px 0 rgba(0, 0, 0, .1);
    }

    .succWrap {
      padding: 10px;
      margin: 0 0 20px 0;
      background: #fff;
      border-left: 4px solid #5cb85c;
      -webkit-box-shadow: 0 1px 1px 0 rgba(0, 0, 0, .1);
      box-shadow: 0 1px 1px 0 rgba(0, 0, 0, .1);
    }

    .validation-info {
      background: #f8f9fa;
      border: 1px solid #dee2e6;
      border-radius: 5px;
      padding: 15px;
      margin: 15px 0;
    }

    .validation-info h6 {
      color: #495057;
      margin-bottom: 10px;
    }

    .validation-info ul {
      margin: 0;
      padding-left: 20px;
    }

    .name-info {
      background: #e7f3ff;
      border: 1px solid #b3d7ff;
      border-radius: 5px;
      padding: 12px;
      margin: 10px 0;
    }

    .name-info .fa {
      color: #0066cc;
    }
  </style>
</head>

<body>

  <?php include('includes/header.php'); ?>
  <?php include('includes/colorswitcher.php'); ?>

  <section class="page-header profile_page">
    <div class="container">
      <div class="page-header_wrap">
        <div class="page-heading"></div>
        <h1>Get Verified</h1>
      </div>
      <ul class="coustom-breadcrumb">
        <li><a href="#">Home</a></li>
        <li>Get Verified</li>
      </ul>
    </div>
    <!-- Dark Overlay-->
    <div class="dark-overlay"></div>
  </section>

  <?php
  $useremail = $_SESSION['login'];
  $sql = "SELECT * from tblusers where EmailId=:useremail";
  $query = $dbh->prepare($sql);
  $query->bindParam(':useremail', $useremail, PDO::PARAM_STR);
  $query->execute();
  $results = $query->fetchAll(PDO::FETCH_OBJ);
  $cnt = 1;
  if ($query->rowCount() > 0) {
    foreach ($results as $result) { 
      $_SESSION['is_verified'] = $result->is_verified ?? 0;
      $_SESSION['verification_pending'] = $result->verification_pending ?? 0;
      ?>

      <section class="user_profile inner_pages">
        <div class="container">
          <div class="user_profile_info gray-bg padding_4x4_40">
            <div class="upload_user_logo"> <img src="assets/images/dealer-logo.jpg" alt="image">
            </div>

            <div class="dealer_info">
              <h5><?php echo htmlentities($result->FullName ?? ''); ?></h5>
              <p><?php echo htmlentities($result->Address ?? ''); ?><br>
                <?php echo htmlentities($result->City ?? ''); ?>&nbsp;<?php echo htmlentities($result->Country ?? '');
    }
  } ?></p>
        </div>
      </div>

      <div class="row">
        <div class="col-md-3 col-sm-3">
          <?php include('includes/sidebar.php'); ?>
          <div class="col-md-6 col-sm-8">
            <div class="profile_wrap">
              <p>Verification Status:
                <?php 
                if ($_SESSION['is_verified']) {
                    echo '<span style="color:green;">✓ Verified</span>';
                } elseif ($_SESSION['verification_pending']) {
                    echo '<span style="color:orange;">⏳ Pending Review</span>';
                } else {
                    echo '<span style="color:red;">✗ Not Verified</span>';
                }
                ?>
              </p>
            </div>

            <?php if ($_SESSION['verification_pending']): ?>
              <div class="alert alert-info">
                <i class="fa fa-clock-o"></i> We are currently verifying your details. You'll be notified once complete.
              </div>

            <?php elseif (!$_SESSION['is_verified']): ?>
              <div>
                <div class="row">
                  <div class="col-md-8 offset-md-2">
                    <h4>Verify your account by uploading a passport or taking a webcam photo</h4>

                    <!-- Profile name display -->
                    <?php 
                    $userFullName = '';
                    foreach ($results as $userResult) {
                        $userFullName = $userResult->FullName ?? '';
                        break;
                    }
                    ?>
                    
                    <?php if (!empty($userFullName)): ?>
                    <div class="name-info">
                      <h6><i class="fa fa-user"></i> Profile Name Verification:</h6>
                      <p><strong>Your Profile Name:</strong> <?php echo htmlspecialchars($userFullName); ?></p>
                      <small>This name must match the name on your passport for verification to succeed.</small>
                    </div>
                    <?php endif; ?>

                    <!-- Enhanced validation information -->
                    <div class="validation-info">
                      <h6><i class="fa fa-info-circle"></i> Validation Requirements:</h6>
                      <ul>
                        <li><strong>Name Matching:</strong> Passport name must match your profile name</li>
                        <li><strong>MRZ Quality:</strong> Machine Readable Zone must be clear and valid</li>
                        <li><strong>Image Quality:</strong> Well-lit, in focus, and clearly visible text</li>
                        <li><strong>Document Validity:</strong> Passport must not be expired</li>
                        <li><strong>Technical Score:</strong> Minimum 70% validation score required</li>
                      </ul>
                    </div>

                    <?php if ($success): ?>
                      <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php elseif ($error): ?>
                      <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                      <!-- File Upload -->
                      <div class="form-group">
                        <label for="id_image">Upload Passport</label>
                        <input type="file" name="id_image" id="id_image" class="form-control" accept="image/png,image/jpg,image/jpeg">
                        <small class="form-text text-muted">Supported formats: PNG, JPG, JPEG (Max: 10MB)</small>
                      </div>

                      <p class="text-center">OR</p>

                      <!-- Webcam -->
                      <div class="form-group text-center">
                        <video id="video" width="320" height="240" autoplay></video>
                        <canvas id="canvas" width="320" height="240" style="display:none;"></canvas>
                        <input type="hidden" name="photoInfo" id="photoInfo">
                        <br>
                        <button type="button" class="btn btn-info mt-2" onclick="startCamera()">Start Camera</button>
                        <button type="button" class="btn btn-warning mt-2" onclick="takeSnapshot()">Take Photo</button>
                        <button type="button" class="btn btn-secondary mt-2" onclick="stopCamera()">Stop Camera</button>
                      </div>

                      <button type="submit" class="btn btn-success btn-block">Submit for Verification</button>
                    </form>
                  </div>
                </div>
              <?php endif; ?>

            </div>
          </div>
        </div>
      </div>
  </section>

  <script>
    let currentStream = null;

    function startCamera() {
      const video = document.getElementById('video');
      navigator.mediaDevices.getUserMedia({ video: true })
        .then(stream => { 
          currentStream = stream;
          video.srcObject = stream; 
        })
        .catch(err => { alert("Unable to access webcam: " + err.message); });
    }

    function stopCamera() {
      if (currentStream) {
        currentStream.getTracks().forEach(track => track.stop());
        currentStream = null;
        document.getElementById('video').srcObject = null;
      }
    }

    function takeSnapshot() {
      const video = document.getElementById('video');
      const canvas = document.getElementById('canvas');
      const context = canvas.getContext('2d');
      
      if (video.videoWidth === 0) {
        alert("Please start the camera first");
        return;
      }
      
      canvas.style.display = 'block';
      context.drawImage(video, 0, 0, canvas.width, canvas.height);
      const dataURL = canvas.toDataURL('image/png');
      document.getElementById('photoInfo').value = dataURL;
      
      // Visual feedback
      canvas.style.border = "2px solid green";
      setTimeout(() => {
        canvas.style.display = 'none';
        canvas.style.border = "none";
      }, 2000);
    }

    // File upload validation
    document.getElementById('id_image').addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        const maxSize = 10 * 1024 * 1024; // 10MB
        if (file.size > maxSize) {
          alert('File size must be less than 10MB');
          e.target.value = '';
          return;
        }
        
        const allowedTypes = ['image/png', 'image/jpg', 'image/jpeg'];
        if (!allowedTypes.includes(file.type)) {
          alert('Please upload a PNG, JPG, or JPEG file');
          e.target.value = '';
          return;
        }
      }
    });
  </script>

  <?php include('includes/footer.php'); ?>
  <!--Back to top-->
  <div id="back-top" class="back-top"> <a href="#top"><i class="fa fa-angle-up" aria-hidden="true"></i> </a> </div>
  <!--/Back to top-->

  <!--Login-Form -->
  <?php include('includes/login.php'); ?>
  <!--/Login-Form -->

  <!--Register-Form -->
  <?php include('includes/registration.php'); ?>
  <!--/Register-Form -->

  <!--Forgot-password-Form -->
  <?php include('includes/forgotpassword.php'); ?>
  <!--/Forgot-password-Form -->

  <!-- Scripts -->
  <script src="assets/js/jquery.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <script src="assets/js/interface.js"></script>
  <!--Switcher-->
  <script src="assets/switcher/js/switcher.js"></script>
  <!--bootstrap-slider-JS-->
  <script src="assets/js/bootstrap-slider.min.js"></script>
  <!--Slider-JS-->
  <script src="assets/js/slick.min.js"></script>
  <script src="assets/js/owl.carousel.min.js"></script>

</body>

</html>