<?php
$servername = "localhost"; // Change if using an online server
$username = "root"; // Default username for XAMPP/WAMP
$password = ""; // Default password is empty for local servers
$dbname = "virtual_study_group"; // Change to your actual database name

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
