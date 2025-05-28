<?php 
// DB credentials.
define('DB_HOST','');
define('DB_USER','');
define('DB_PASS','');
define('DB_NAME','');
define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');
define('GOOGLE_REDIRECT_URI', '');
// Establish database connection.
try
{
$dbh = new PDO('mysql:dbname=carrental;host=mariadb;charset=utf8mb4;port=3306', 
      DB_USER, 
      DB_PASS, 
  );
// $dbh = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME,DB_USER, DB_PASS,array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
}
catch (PDOException $e)
{
exit("Error: " . $e->getMessage());
}
?>