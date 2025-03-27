<?php
session_start();
require_once 'config.php';
require_once '../vendor/autoload.php';

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope("email");
$client->addScope("profile");

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token);

    $oauth = new Google_Service_Oauth2($client);
    $userInfo = $oauth->userinfo->get();

    $email = $userInfo->email;
    $name = $userInfo->name;
    $google_id = $userInfo->id;
    $token_value = json_encode($token);

    // Check if user exists
    $sql = "SELECT * FROM tblusers WHERE EmailId = :email";
    $query = $dbh->prepare($sql);
    $query->bindParam(':email', $email, PDO::PARAM_STR);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        // Update token
        $update = "UPDATE tblusers SET oauth_id=:oauth_id, oauth_token=:token WHERE EmailId=:email";
        $stmt = $dbh->prepare($update);
        $stmt->bindParam(':oauth_id', $google_id, PDO::PARAM_STR);
        $stmt->bindParam(':token', $token_value, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
    } else {
        // Insert new user
        $insert = "INSERT INTO tblusers (FullName, EmailId, oauth_id, oauth_token, auth_type) VALUES (:name, :email, :oauth_id, :oauth_token, 'googleauth')";
        $stmt = $dbh->prepare($insert);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':oauth_id', $google_id, PDO::PARAM_STR);
        $stmt->bindParam(':oauth_token', $token_value, PDO::PARAM_STR);
        $stmt->execute();
    }

    $_SESSION['login'] = $email;
    $_SESSION['fname'] = $name;

    header("Location: ../index.php");
    // header("Location: index.php");
    exit();
}
?>
