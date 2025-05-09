<?php
session_start();
include('includes/config.php');

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




function callPassportScanner($imageData)
{

  /* ------------------------------ passport api ------------------------------ */

  $imageData = str_replace(array("\r", "\n"), '', $imageData);


  $url = 'http://passport-api:8000/scan';
  $payload = json_encode(['image_base64' => $imageData]);

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($payload)
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  if (curl_errno($ch)) {
    echo 'Request Error: ' . curl_error($ch);
    return;
  }

  $result = json_decode($response, true);


  // echo $result['success'];
  // echo $result['valid_score'];
  // echo http_build_query($result, '\n', 'CRLF');



  /* ------------------------------ db connection ----------------------------- */
  $conn = $GLOBALS['dbh'];
  $useremail = $_SESSION['login'];

  $stmt = $conn->prepare("SELECT id, dob, fullname FROM tblusers WHERE EmailId = :email");
  $stmt->bindParam(':email', $useremail, PDO::PARAM_STR);
  $stmt->execute();
  $userData = $stmt->fetchAll(PDO::FETCH_OBJ);

  $userId = $userData[0]->id;

  // if ai was not successful
  if ($result == null || $result['success'] == false) {

    $stmt = $conn->prepare("INSERT INTO tbluserphotos (user_id, photo_base64) VALUES (:userId, :imageData) ON DUPLICATE KEY UPDATE photo_base64 = :imageDataUpdate");
    $stmt->bindValue(':userId', $userId);
    $stmt->bindValue(':imageData', $imageData);
    $stmt->bindValue(':imageDataUpdate', $imageData);
    $stmt->execute();

    $conn->query("UPDATE tblusers SET is_verified = 0, verification_pending = 1 WHERE id = '$userId'");

    echo "Passport scan failed or invalid. Verification pending.";
    $_SESSION['verification_pending'] = 1;

  } else {
    /* ---------------------------- passport is valid --------------------------- */
    $conn->query("UPDATE tblusers SET is_verified = 1, verification_pending = 0 WHERE EmailId = '$useremail'");

    $_SESSION['is_verified'] = 1;
    $_SESSION['verification_pending'] = 0;

    header('Location: ' . $_SERVER['REQUEST_URI']);
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
      $imageData = base64_encode(file_get_contents($fileTmpPath));
      callPassportScanner($imageData);
    } else {
      $error = "Error file type. Please upload JPG or PNG.";
    }

  } elseif (!empty($_POST['photoInfo'])) {
    // Case 2: Webcam
    $photoInfo = $_POST['photoInfo'];
    $photoInfo = str_replace('data:image/png;base64,', '', $photoInfo);
    $photoInfo = str_replace(' ', '+', $photoInfo);
    // $imageData = $photoInfo;
    // Call Python
    callPassportScanner($photoInfo);
  } else {
    $error = "No image data provided.";
  }
}



// Check if form submitted
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
    </>
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
    foreach ($results as $result) { ?>
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
                <?php echo $_SESSION['is_verified'] ? '<span style="color:green;">You are already Verified, Hooray!</span>' : '<span style="color:red;">Not Verified</span>'; ?>
              </p>
            </div>



            <?php if (isset($_SESSION['verification_pending']) && $_SESSION['verification_pending']): ?>
              <div style="color:blue;">
                We are currently verfying your details please keep your eye on for updates
              </div>


            <?php elseif (!$_SESSION['is_verified']): ?>
              <div>
                <div class="row">
                  <div class="col-md-8 offset-md-2">
                    <h4>Verify your account by uploading a passport or taking a webcam photo</h4>

                    <?php if ($success): ?>
                      <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php elseif ($error): ?>
                      <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                      <!-- File Upload -->
                      <div class="form-group">
                        <label for="id_image">Upload Passport</label>
                        <input type="file" name="id_image" id="id_image" class="form-control">
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
                      </div>

                      <button type="submit" class="btn btn-success btn-block">Submit Verification</button>
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
    function startCamera() {
      const video = document.getElementById('video');
      navigator.mediaDevices.getUserMedia({ video: true })
        .then(stream => { video.srcObject = stream; })
        .catch(err => { alert("Unable to access webcam."); });
    }

    function takeSnapshot() {
      const video = document.getElementById('video');
      const canvas = document.getElementById('canvas');
      const context = canvas.getContext('2d');
      canvas.style.display = 'block';
      context.drawImage(video, 0, 0, canvas.width, canvas.height);
      const dataURL = canvas.toDataURL('image/png');
      document.getElementById('photoInfo').value = dataURL;
    }
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