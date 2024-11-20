<?php
session_start();
include '../connect.php';

if (isset($_POST['Login'])) {
    $admission_no = $_POST['admission_no'];
    $password = $_POST['password'];

    // Prepare the SQL statement to prevent SQL injection
    $sql_user = "
    SELECT student_id AS user_id, admission_no AS username, password_hash, 'student' AS user_type
    FROM students
    WHERE admission_no = ?
    UNION
    SELECT lecturer_id AS user_id, lecturer_id_no AS username, password_hash, 'lecturer' AS user_type
    FROM lecturers
    WHERE lecturer_id_no = ?
    UNION
    SELECT admin_id AS user_id, username, password_hash, 'admin' AS user_type
    FROM admin
    WHERE username = ?
    ";

    // Prepare the statement
    $stmt = mysqli_prepare($con, $sql_user);
    mysqli_stmt_bind_param($stmt, 'sss', $admission_no, $admission_no, $admission_no);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $hashed_password = $user['password_hash'];

        // Verify the entered password
        if (password_verify($password, $hashed_password)) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            // Store the user ID in the session based on user type
            if ($user['user_type'] === 'student') {
                $_SESSION['student_id'] = $user['user_id'];
                header("Location: ../Dashboard/studentdash.php");
            } elseif ($user['user_type'] === 'lecturer') {
                $_SESSION['lecturer_id'] = $user['user_id'];
                header("Location: ../Dashboard/lecturerdash.php");
            } elseif ($user['user_type'] === 'admin') {
                $_SESSION['admin_id'] = $user['user_id'];
                header("Location: ../Dashboard/admin_dash.php");
            }
            exit;
        } else {
            echo "Invalid login credentials.";
            error_log("Invalid password for admission number: $admission_no");
        }
    } else {
        echo "Invalid login credentials.";
        error_log("User  not found for admission number: $admission_no");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="login.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script defer src="landing_page.js"></script>
</head>
<body>
    <div class="container" id="Login">
        <form action="login.php" method="POST">
        <h1>Login</h1>
            <div class="input_box">
                    <input type="text" placeholder="Enter your username" required name="admission_no">
                    <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="input_box">
                    <input type="password" id="password" placeholder="Enter your password" required name="password">
                    <i class="fa-solid fa-lock"></i>
                    </div>
                    <div class="remeber-forget">
                        <label><input type="checkbox"> Remeber Me</label>
                        <a href="#">Forget Password</a>
                    </div>
                    <input type="submit" class="btn" value="Login" name="Login">
                    <div class="register-link">
                        <p>Dont have an account? <a href="../login/studentsignup.php">Register</a></p>
                    </div>
        </form>
    </div>
</body>
</html>