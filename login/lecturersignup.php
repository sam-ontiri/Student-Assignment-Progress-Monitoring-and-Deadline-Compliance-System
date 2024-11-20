<?php
include '../connect.php';
if(isset($_POST['signup'])){
    $email=$_POST['email'];
    $fname=$_POST['fname'];
    $lecid=$_POST['lecid'];
    $password=$_POST['password'];
   
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    //insert query
    $sql = "INSERT INTO `lecturers` (email, full_name, lecturer_id_no, password_hash) 
        VALUES ('$email', '$fname', '$lecid', '$hashed_password')";


    $result=mysqli_query($con,$sql);
    if($result){
       header ("location: login.php");
    }
    else{
        die(mysqli_error($con));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="lecturersignup.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <div class="container">
        <div class="user-options">
            <a href="studentsignup.php"><button class="user-btn">Student</button></a>
            <a href="lecturersignup.php"> <button class="user-btn">Lecturer</button></a>
            <a href="adminsignup.php"> <button class="user-btn">Admin</button></a>
           </div>
           
           <form method="post">
            <h1>Registration</h1>
            <div class="input_box">
                    <input type="email" placeholder="Enter your email" required name="email">
                    <i class="fa-solid fa-envelope"></i>
                    </div>
                    <div class="input_box">
                    <input type="text" placeholder="Enter your fullname" required name="fname">
                    <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="input_box">
                        <input type="text" placeholder="Lecturer ID NO" required name="lecid">
                        <i class="fa-solid fa-user"></i>
                        </div>
                    <div class="input_box">
                    <input type="password" placeholder="create a password" required name="password">
                    <i class="fa-solid fa-lock"></i>
                    </div>
                   
                    <div class="terms_condition">
                        <label><input type="checkbox">  I accept all terms & condtion</label>
                    </div>
                    <input type="submit" class="btn" value="sign up" name="signup">
                    <div class="login-link">
                        <p>Already have an account? <a href="login.php">Login</a></p>
                    </div>
           </form>
    </div>
    
</body>
</html>