<?php
session_start();
require_once 'config.php';
require_once '../vendor/autoload.php';


use Google\Client;
use Google\Service\Oauth2;

$client = new Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope("email");
$client->addScope("profile");

if (!isset($_SESSION['pending_oauth']) && isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['access_token'])) {
        $client->setAccessToken($token['access_token']);
    } else {
        // Handle error, for example, by logging or redirecting
        echo "Error: Unable to fetch access token";
        exit();
    }

    $oauth = new Oauth2($client);
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
    #print(implode($result));

    if ($result && $result['auth_type'] == 'legacy') {

        $_SESSION['is_verified'] = $result['is_verified'];
        $_SESSION['verification_pending'] = $result['verification_pending'];

        $_SESSION['pending_oauth'] = [
            'email' => $email,
            'name' => $name,
            'password' => $result['Password'],
            'google_id' => $google_id,
            'token_value' => $token_value
        ];
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                $('#oldpasswordform').modal('show');
            });
        </script>";
    } elseif ($result) {


        $_SESSION['is_verified'] = $result['is_verified'];
        $_SESSION['verification_pending'] = $result['verification_pending'];
        // Update token
        $update = "UPDATE tblusers SET oauth_id=:oauth_id, oauth_token=:token WHERE EmailId=:email";
        $stmt = $dbh->prepare($update);
        $stmt->bindParam(':oauth_id', $google_id, PDO::PARAM_STR);
        $stmt->bindParam(':token', $token_value, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        $_SESSION['login'] = $email;
        $_SESSION['fname'] = $name;

        header("Location: ../index.php");
        exit();
    } else {

        // Insert new user
        //
        $_SESSION['is_verified'] = false;
        $_SESSION['verification_pending'] = false;



        $insert = "INSERT INTO tblusers (FullName, EmailId, oauth_id, oauth_token, auth_type) VALUES (:name, :email, :oauth_id, :oauth_token, :auth_type)";
        $stmt = $dbh->prepare($insert);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':oauth_id', $google_id, PDO::PARAM_STR);
        $stmt->bindParam(':oauth_token', $token_value, PDO::PARAM_STR);

        $googleoauth = "googleoauth";
        $stmt->bindParam(':auth_type', $googleoauth, PDO::PARAM_STR);
        $stmt->execute();

        $_SESSION['login'] = $email;
        $_SESSION['fname'] = $name;

        header("Location: ../index.php");
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['No'])) {
        header("Location: ../index.php");
        exit();
    } elseif (isset($_POST['Yes']) && isset($_SESSION['pending_oauth'])) {


        $userData = $_SESSION['pending_oauth'];

        $password = md5($_POST['password']);
        if ($password != $userData['password']) {

            unset($_SESSION['pending_oauth']);
            // Show the alert first, then redirect after a short delay
            echo "<script>
                    alert('Invalid Details, if you forgot your password, please click on forgot password and reset password');
                    setTimeout(function() {
                        window.location.href = '../index.php'; // Redirect after alert
                    }, 100); // Delay of 2 seconds before redirect
                </script>";
            exit();
        }



        $update = "UPDATE tblusers SET oauth_id=:oauth_id, oauth_token=:token, Password='', auth_type='googleoauth' WHERE EmailId=:email";
        $stmt = $dbh->prepare($update);
        $stmt->bindParam(':oauth_id', $userData['google_id'], PDO::PARAM_STR);
        $stmt->bindParam(':token', $userData['token_value'], PDO::PARAM_STR);
        $stmt->bindParam(':email', $userData['email'], PDO::PARAM_STR);
        $stmt->execute();

        $_SESSION['login'] = $userData['email'];
        $_SESSION['fname'] = $userData['name'];
        unset($_SESSION['pending_oauth']);

        header("Location: ../index.php");
        exit();
    }
}
?>

<!-- Modal -->
<div class="modal fade" id="oldpasswordform">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Do you want to permanently move to OAuth? If yes please enter the old password
                    otherwise say No</h3>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="login_wrap">
                        <div class="col-md-12 col-sm-6">
                            <form method="post">
                                <div class="form-group">
                                    <input type="password" class="form-control" name="password" placeholder="Password*"
                                        required>
                                </div>
                                <div class="form-group">
                                    <button type="submit" name="Yes" class="btn btn-success btn-block">Yes, Move me to
                                        Oauth
                                        OAuth</button>
                                    <button type="submit" name="No" class="btn btn-danger btn-block">Cancel</button>
                                </div>
                            </form>
                            <hr>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>