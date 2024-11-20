<?php
// Database connection
$con = mysqli_connect("localhost", "root", "", "mydb");

// Check connection
if (!$con) {
    die("Connection failed: " . mysqli_connect_error($con));
}
?>