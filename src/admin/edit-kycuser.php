<?php
session_start();
error_reporting(0);
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

	//Aprrove
	if (isset($_POST['approve'])) {
		$id = $_GET['id'];

		$sql = "UPDATE tblusers 
        SET is_verified = '1', verification_pending = 0
        WHERE id = :id";


		$query = $dbh->prepare($sql);
		$query->bindParam(':id', $id, PDO::PARAM_INT);

		$query->execute();

		$msg = "User updated successfully";
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
											<form method="post" name="chngpwd" class="form-horizontal" onSubmit="return valid();">


												<?php if ($error) { ?><div class="errorWrap"><strong>ERROR</strong>:<?php echo htmlentities($error); ?> </div><?php } else if ($msg) { ?><div class="succWrap"><strong>SUCCESS</strong>:<?php echo htmlentities($msg); ?> </div><?php } ?>

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
																<img src="img/usericon.png" alt="User Icon" style="width: 130px; height: auto;">
															</div>
															<div style="text-align: center;">
																<div>
																	<h3><?php echo htmlentities($result->FullName); ?></h3>
																</div>
																<!--<div>Passport Clarity : <?php echo htmlentities($result->pClarity); ?></div><br/>-->
															</div>




															<label class="col-sm-4 control-label">Full Name</label>
															<div class="col-sm-8">
																<input type="text" class="form-control" value="<?php echo htmlentities($result->FullName); ?>" name="fname" id="fname" required>
															</div>


															<label class="col-sm-4 control-label">Date of Birth</label>
															<div class="col-sm-8">
																<input type="text" class="form-control" value="<?php echo htmlentities($result->dob); ?>" name="dob" id="dob" required>
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


												<div class="form-group">

													<div class="col-sm-8 col-sm-offset-4">
														<button class="btn btn-success" name="approve" type="submit">Approve</button>
														<button class="btn btn-primary" name="update" type="submit">Update</button>
														<button class="btn btn-danger" name="decline" type="submit">Decline</button>

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