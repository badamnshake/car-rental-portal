<?php
session_start();
error_reporting(0);
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
include('includes/config.php');
if (strlen($_SESSION['alogin']) == 0) {
	header('location:index.php');
} else {
	// Update
	if (isset($_POST['update'])) {
		$fname = $_POST['fname'];
		$dob = $_POST['dob'];
		$id = $_GET['id'];

		$sql = "UPDATE tblusers 
				SET FullName = :fname, 
					dob = :dob
				WHERE id = :id";

		$query = $dbh->prepare($sql);
		$query->bindParam(':fname', $fname, PDO::PARAM_STR);
		$query->bindParam(':dob', $dob, PDO::PARAM_STR);
		$query->bindParam(':id', $id, PDO::PARAM_INT);

		$query->execute();

		$msg = "User updated successfully";
	}

	// Approve
	if (isset($_POST['approve'])) {
		$id = $_GET['id'];

		// Approve user
		$sql = "UPDATE tblusers 
            SET is_verified = '1', verification_pending = 0
            WHERE id = :id";
		$query = $dbh->prepare($sql);
		$query->bindParam(':id', $id, PDO::PARAM_INT);
		$query->execute();

		// Delete related photo record
		$deleteSql = "DELETE FROM tbluserphotos WHERE user_id = :id";
		$deleteQuery = $dbh->prepare($deleteSql);
		$deleteQuery->bindParam(':id', $id, PDO::PARAM_INT);
		$deleteQuery->execute();

		$msg = "User approved and document deleted successfully.";
	}

	//Decline
	if (isset($_POST['decline'])) {
		$id = $_GET['id'];

		$sql = "UPDATE tblusers 
        SET is_verified = '0', 
            verification_pending = '0', 
            verification_attempts = verification_attempts + 1
        WHERE id = :id";

		$query = $dbh->prepare($sql);
		$query->bindParam(':id', $id, PDO::PARAM_INT);

		$query->execute();

		$msg = "User updated successfully";
	}

	//Open Document
	require_once '../includes/encryption.php';
	$decryptedImage = '';

	if (isset($_GET['id'])) {
		$user_id = $_GET['id'];

		$sql = "SELECT photo_base64, aes_key, iv FROM tbluserphotos WHERE user_id = :user_id";
		$stmt = $dbh->prepare($sql);
		$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
		$stmt->execute();
		$result = $stmt->fetch(PDO::FETCH_ASSOC);

		if (isset($_POST['decrypt']) && isset($_FILES['private_key'])) {
			if ($result && is_uploaded_file($_FILES['private_key']['tmp_name'])) {
				$privateKey = file_get_contents($_FILES['private_key']['tmp_name']);

				if ($privateKey) {
					$aesKey = null;
					if (openssl_private_decrypt(base64_decode($result['aes_key']), $aesKey, $privateKey)) {
						$decryptedBinary = openssl_decrypt(
							base64_decode($result['photo_base64']),
							'AES-256-CBC',
							$aesKey,
							OPENSSL_RAW_DATA,
							base64_decode($result['iv'])
						);

						if ($decryptedBinary) {
							$decryptedImage = base64_encode($decryptedBinary);
						}
					}
				}
			}
		}
	}

	?>
	?>

	<!doctype html>
	<html lang="en" class="no-js">

	<head>
		<meta charset="UTF-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">
		<meta name="description" content="">
		<meta name="author" content="">
		<meta name="theme-color" content="#3e454c">

		<title>Car Rental Portal | Admin Update User</title>

		<!-- Font awesome -->
		<link rel="stylesheet" href="css/font-awesome.min.css">
		<!-- Sandstone Bootstrap CSS -->
		<link rel="stylesheet" href="css/bootstrap.min.css">
		<!-- Bootstrap Datatables -->
		<link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
		<!-- Bootstrap social button library -->
		<link rel="stylesheet" href="css/bootstrap-social.css">
		<!-- Bootstrap select -->
		<link rel="stylesheet" href="css/bootstrap-select.css">
		<!-- Bootstrap file input -->
		<link rel="stylesheet" href="css/fileinput.min.css">
		<!-- Awesome Bootstrap checkbox -->
		<link rel="stylesheet" href="css/awesome-bootstrap-checkbox.css">
		<!-- Admin Stye -->
		<link rel="stylesheet" href="css/style.css">
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
		<div class="ts-main-content">
			<?php include('includes/leftbar.php'); ?>
			<div class="content-wrapper">
				<div class="container-fluid">

					<div class="row">
						<div class="col-md-12">

							<h2 class="page-title">KYC Management</h2>
							<div class="row">
								<div class="col-md-10">
									<div class="panel panel-default">
										<!--div class="panel-heading">Update User Details</div-->
										<div class="panel-body">
											<form method="post" name="chngpwd" class="form-horizontal"
												onSubmit="return valid();">


												<?php if ($error) { ?>
													<div class="errorWrap">
														<strong>ERROR</strong>:<?php echo htmlentities($error); ?>
													</div>
												<?php } else if ($msg) { ?>
														<div class="succWrap">
															<strong>SUCCESS</strong>:<?php echo htmlentities($msg); ?>
														</div>
												<?php } ?>

												<?php
												$id = $_GET['id'];
												$ret = "select * from tblusers where id=:id";
												$query = $dbh->prepare($ret);
												$query->bindParam(':id', $id, PDO::PARAM_STR);
												$query->execute();
												$results = $query->fetchAll(PDO::FETCH_OBJ);
												$cnt = 1;
												if ($query->rowCount() > 0) {
													foreach ($results as $result) {
														?>

														<div class="form-group">
															<div style="text-align: center;">
																<img src="img/usericon.png" alt="User Icon"
																	style="width: 130px; height: auto;">
															</div>
															<div style="text-align: center;">
																<div>
																	<h3><?php echo htmlentities($result->FullName); ?></h3>
																</div>
																<!--<div>Passport Clarity : <?php echo htmlentities($result->pClarity); ?></div><br/>-->
															</div>




															<label class="col-sm-4 control-label">Full Name</label>
															<div class="col-sm-8">
																<input type="text" class="form-control"
																	value="<?php echo htmlentities($result->FullName); ?>"
																	name="fname" id="fname" required>
															</div>


															<label class="col-sm-4 control-label">Date of Birth</label>
															<div class="col-sm-8">
																<input type="text" class="form-control"
																	value="<?php echo htmlentities($result->dob); ?>" name="dob"
																	id="dob">
															</div>

															<!--
															<label class="col-sm-4 control-label">Passport Number</label>
															<div class="col-sm-8">
																<input type="text" class="form-control" value="<?php echo htmlentities($result->pNumber); ?>" name="pNumber" id="pNumber" required>
															</div>

															<label class="col-sm-4 control-label">Country</label>
															<div class="col-sm-8">
																<input type="text" class="form-control" value="<?php echo htmlentities($result->pCountry); ?>" name="pCountry" id="pCountry" required>
															</div>
													-->
														</div>
														<div class="hr-dashed"></div>

													<?php }
												} ?>

												<!-- View Verification Button -->
												<div class="form-group">
													<div class="col-sm-8 col-sm-offset-4 mb-3">
														<button type="button" class="btn btn-primary" data-toggle="modal"
															data-target="#documentModal">
															View Verification Document
														</button>
													</div>
												</div>

												<!-- Approve / Update / Decline Buttons -->
												<div class="form-group">
													<div class="col-sm-8 col-sm-offset-4">
														<button class="btn btn-success" name="approve"
															type="submit">Approve</button>
														<button class="btn btn-primary" name="update"
															type="submit">Update</button>
														<button class="btn btn-danger" name="decline"
															type="submit">Decline</button>
													</div>
												</div>


											</form>
										</div>
									</div>
								</div>

							</div>



						</div>
					</div>


				</div>
			</div>
		</div>

		<!-- Modal -->
		<div class="modal fade" id="documentModal" tabindex="-1" role="dialog" aria-labelledby="documentModalLabel"
			aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<!--<h5 class="modal-title" id="documentModalLabel">Verification Document</h5>-->
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body text-center">

						<form method="post" enctype="multipart/form-data">
							<div class="form-group">
								<label for="private_key">Upload RSA Private Key (.pem):</label>
								<input type="file" name="private_key" id="private_key" class="form-control" accept=".pem"
									required>
							</div>
							<button type="submit" name="decrypt" class="btn btn-primary">Decrypt Image</button>
						</form>

						<?php if (!empty($decryptedImage)): ?>
							<div style="margin-top:20px;">
								<img src="data:image/jpeg;base64,<?php echo $decryptedImage; ?>" alt="Decrypted Passport"
									style="max-width:100%; height:auto; border:1px solid #ccc;">
							</div>
						<?php elseif (isset($_POST['decrypt'])): ?>
							<p class="text-danger">❌ Decryption failed. Check private key and try again.</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<!-- Loading Scripts -->
		<script src="js/jquery.min.js"></script>
		<script src="js/bootstrap-select.min.js"></script>
		<script src="js/bootstrap.min.js"></script>
		<script src="js/jquery.dataTables.min.js"></script>
		<script src="js/dataTables.bootstrap.min.js"></script>
		<script src="js/Chart.min.js"></script>
		<script src="js/fileinput.js"></script>
		<script src="js/chartData.js"></script>
		<script src="js/main.js"></script>

	</body>

	</html>
<?php } ?>