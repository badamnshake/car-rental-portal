<?php if (!empty($imageData)): ?>
							<img src="data:image/jpeg;base64,<?php echo $imageData; ?>" alt="Verification Document"
								style="max-width: 100%; height: auto; border: 1px solid #ccc;">
						<?php else: ?>
							<p>No image found for this user.</p>
						<?php endif; ?>


                        $imageData = '';
	if (isset($_GET['id'])) {
		$user_id = $_GET['id'];
		$sql = "SELECT photo_base64 FROM tbluserphotos WHERE user_id = :user_id";
		$query = $dbh->prepare($sql);
		$query->bindParam(':user_id', $user_id, PDO::PARAM_INT);
		$query->execute();
		$result = $query->fetch(PDO::FETCH_ASSOC);
		if ($result) {
			$imageData = $result['photo_base64'];
		}
	}