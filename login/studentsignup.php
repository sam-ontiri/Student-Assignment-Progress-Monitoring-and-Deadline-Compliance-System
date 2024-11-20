<?php
include '../connect.php'; // Ensure this points to the correct database connection

if (isset($_POST['signup'])) {
    $email = $_POST['email'];
    $fname = $_POST['fname'];
    $admno = $_POST['admno'];
    $password = $_POST['password'];

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert query using prepared statements to prevent SQL injection
    $sql = "INSERT INTO `students` (email, full_name, admission_no, password_hash) 
            VALUES (?, ?, ?, ?)";
    $stmt = $con->prepare($sql);
    
    // Check if prepare was successful
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($con->error));
    }

    $stmt->bind_param("ssss", $email, $fname, $admno, $hashed_password);

    if ($stmt->execute()) {
        header("Location: login.php");
        exit();
    } else {
        echo "Error: " . htmlspecialchars($stmt->error);
        exit();
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="studentsignup.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script defer src="landing_page.js"></script>
</head>
<body>
    <div class="container" id="signup">
        <div class="user-options">
           <a href="studentsignup.php"><button class="user-btn">Student</button></a>
           <a href="lecturersignup.php"><button class="user-btn">Lecturer</button></a>
           <a href="adminsignup.php"> <button class="user-btn">Admin</button></a>
        </div>
        <form method="post" autocomplete="off">
            <h1>Registration</h1>
            <div class="input_box">
                <input type="email" placeholder="Enter your email" required name="email" autocomplete="off">
                <i class="fa-solid fa-envelope"></i>
            </div>
            <div class="input_box">
                <input type="text" placeholder="Enter your fullname" required name="fname" autocomplete="off">
                <i class="fa-solid fa-user"></i>
            </div>
            <div class="input_box">
                <input type="text" placeholder="Student Admission no" required name="admno" autocomplete="off">
                <i class="fa-solid fa-user"></i>
            </div>
            <div class="input_box">
                <input type="password" placeholder ="create a password" required name="password" autocomplete="off">
                <i class="fa-solid fa-lock"></i>
            </div>
            <div class="terms_condition">
                <label><input type="checkbox" name="cheakbox"> I accept all terms & conditions</label>
            </div>
            <input type="submit" class="btn" value="sign up" name="signup">
            <div class="login-link">
                <p>Already have an account? <a href="login.php">Login</a></p>
            </div>
        </form>
    </div>
</body>
</html>