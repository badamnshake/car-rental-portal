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

// Check if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!empty($_FILES['id_image']['tmp_name'])) {
    $fileTmpPath = $_FILES['id_image']['tmp_name'];
    $fileName = basename($_FILES['id_image']['name']);
    $targetPath = $uploadPath . $fileName;
    $PhotoTypes = ['image/png', 'image/jpg', 'image/jpeg'];

    if (in_array($_FILES['id_image']['type'], $PhotoTypes)) {
      if (move_uploaded_file($fileTmpPath, $targetPath)) {
        $success = "Passport image uploaded successfully.";
        $isVerified = true;
      } else {
        $error = "Error with the uploaded image.";
      }
    } else {
      $error = "Error file type. Please upload JPG or PNG.";
    }

  } elseif (!empty($_POST['photoInfo'])) {
    $photoInfo = $_POST['photoInfo'];
    $photoInfo = str_replace('data:image/png;base64,', '', $photoInfo);
    $photoInfo = str_replace(' ', '+', $photoInfo);
    $denosie = base64_decode($photoInfo);
    $fileName = 'webcam_' . time() . '.png';
    $filePath = $uploadPath . $fileName;

    if (file_put_contents($filePath, $denosie)) {
      $success = "Webcam image uploaded successfully.";
      $isVerified = true;
    } else {
      $error = "Failed to save webcam image.";
    }
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
    </div>
    <!-- Dark Overlay-->
    <div class="dark-overlay"></div>
  </section>


<?php 
$useremail=$_SESSION['login'];
$sql = "SELECT * from tblusers where EmailId=:useremail";
$query = $dbh -> prepare($sql);
$query -> bindParam(':useremail',$useremail, PDO::PARAM_STR);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$cnt=1;
if($query->rowCount() > 0)
{
foreach($results as $result)
{ ?>
<section class="user_profile inner_pages">
  <div class="container">
    <div class="user_profile_info gray-bg padding_4x4_40">
      <div class="upload_user_logo"> <img src="assets/images/dealer-logo.jpg" alt="image">
      </div>

      <div class="dealer_info">
        <h5><?php echo htmlentities($result->FullName);?></h5>
        <p><?php echo htmlentities($result->Address);?><br>
          <?php echo htmlentities($result->City);?>&nbsp;<?php echo htmlentities($result->Country); }}?></p>
      </div>
    </div>

      <div class="row">
        <div class="col-md-3 col-sm-3">
          <?php include('includes/sidebar.php'); ?>
          <div class="col-md-6 col-sm-8">
            <div class="profile_wrap">
                <p>Verification Status:
                  <?php echo $isVerified ? '<span style="color:green;">Verified</span>' : '<span style="color:red;">Not Verified</span>'; ?>
                </p>
            </div>

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