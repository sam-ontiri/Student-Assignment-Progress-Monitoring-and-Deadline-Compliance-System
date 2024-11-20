<?php
session_start();
include '../connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Fetch admin information
$sql_admin = "SELECT username, email FROM admin WHERE admin_id = ?";
$stmt_admin = $con->prepare($sql_admin);
$stmt_admin->bind_param("i", $admin_id);
$stmt_admin->execute();
$result_admin = $stmt_admin->get_result();
$admin = $result_admin->fetch_assoc();
$stmt_admin->close();

// Fetch counts for dashboard
$counts = [
    'students' => 0,
    'lecturers' => 0,
    'courses' => 0,
    'assignments' => 0
];

$tables = ['students', 'lecturers', 'courses', 'assignments'];
foreach ($tables as $table) {
    $sql_count = "SELECT COUNT(*) as count FROM $table";
    $result_count = $con->query($sql_count);
    if ($result_count) {
        $counts[$table] = $result_count->fetch_assoc()['count'];
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_student'])) {
        $email = $_POST['email'];
        $full_name = $_POST['full_name'];
        $admission_no = $_POST['admission_no'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO students (email, full_name, admission_no, password_hash) VALUES (?, ?, ?, ?)";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("ssss", $email, $full_name, $admission_no, $password);
        $stmt->execute();
        $stmt->close();
    }
    
    if (isset($_POST['add_lecturer'])) {
        $email = $_POST['email'];
        $full_name = $_POST['full_name'];
        $lecturer_id_no = $_POST['lecturer_id_no'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO lecturers (email, full_name, lecturer_id_no, password_hash) VALUES (?, ?, ?, ?)";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("ssss", $email, $full_name, $lecturer_id_no, $password);
        $stmt->execute();
        $stmt->close();
    }
    
    if (isset($_POST['add_course'])) {
        $course_name = $_POST['course_name'];
        $course_code = $_POST['course_code'];
        
        $sql = "INSERT INTO courses (course_name, course_code) VALUES (?, ?)";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("ss", $course_name, $course_code);
        $stmt->execute();
        $stmt->close();
    }
    
    // Redirect to prevent form resubmission
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script defer src="example.js"></script>
    <link rel="stylesheet" href="example.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <div class="sidebar">
        <div class="top">
            <div class="logo">
                <i class="fa-solid fa-book-open"></i>
                <span>EduTracker</span>
            </div>
            <i class="fa-solid fa-bars" id="btn"></i>
        </div>
        <ul>
            <li>
                <a href="#student_management">
                <i class="fas fa-user-graduate"></i>
                    <span class="nav-item">Student</span>
                </a>
            </li>
            <li>
                <a href="#lecturer_management">
                <i class="fas fa-chalkboard-teacher"></i>
                    <span class="nav-item">Lecturer</span>
                </a>
            </li>
            <li>
                <a href="#admin_management">
                    <i class="fa-solid fa-user-shield"></i>
                    <span class="nav-item">Admin</span>
                </a>
            </li>
            <li>
                <a href="#course_management">
                <i class="fas fa-book"></i>
                    <span class="nav-item">Course</span>
                </a>
            </li>
            <li>
                <a href="../login/logout.php">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span class="nav-item">Logout</span>
                </a>
            </li>
        </ul>
    </div>
    <div class="main-content">
        <section id="Dashboard" class="container">
            <h1>Admin Dashboard</h1>
            <p>Admin ID: <?php echo htmlspecialchars($admin['username']); ?></p>
            <div class="cardBox">
                <div class="card">
                    <div class="numbers"><?php echo $counts['students']; ?></div>
                    <div class="cardName">Students</div>
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="card">
                    <div class="numbers"><?php echo $counts['lecturers']; ?></div>
                    <div class="cardName">Lecturers</div>
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="card">
                    <div class="numbers"><?php echo $counts['courses']; ?></div>
                    <div class="cardName">Courses</div>
                    <i class="fas fa-book"></i>
                </div>
                <div class="card">
                    <div class="numbers"><?php echo $counts['assignments']; ?></div>
                    <div class="cardName">Assignments</div>
                    <i class="fas fa-tasks"></i>
                </div>
            </div>
        </section>

        <section id="student_management" class="container">
            <h2>Student Management</h2>
            <form method="POST" action="">
                <input type="email" name="email" placeholder="Email" required>
                <input type="text" name="full_name" placeholder="Full Name" required>
                <input type="text" name="admission_no" placeholder="Admission Number" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="add_student">Add Student</button>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Admission No</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_students = "SELECT * FROM students";
                    $result_students = $con->query($sql_students);
                    while ($student = $result_students->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($student['student_id']) . "</td>";
                        echo "<td>" . htmlspecialchars($student['full_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($student['email']) . "</td>";
                        echo "<td>" . htmlspecialchars($student['admission_no']) . "</td>";
                        echo "<td>
                                <a href='edit_student.php?id=" . $student['student_id'] . "'>Edit</a>
                                <a href='delete_student.php?id=" . $student['student_id'] . "' onclick='return confirm(\"Are you sure?\");'>Delete</a>
                              </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </section>

        <section id="lecturer_management" class="container">
            <h2>Lecturer Management</h2>
            <form method="POST" action="">
                <input type="email" name="email" placeholder="Email" required>
                <input type="text" name="full_name" placeholder="Full Name" required>
                <input type="text" name="lecturer_id_no" placeholder="Lecturer ID Number" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="add_lecturer">Add Lecturer</button>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Lecturer ID</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_lecturers = "SELECT * FROM lecturers";
                    $result_lecturers = $con->query($sql_lecturers);
                    while ($lecturer = $result_lecturers->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($lecturer['lecturer_id']) . "</td>";
                        echo "<td>" . htmlspecialchars($lecturer['full_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($lecturer['email']) . "</td>";
                        echo "<td>" . htmlspecialchars($lecturer['lecturer_id_no']) . "</td>";
                        echo "<td>
                                <a href='edit_lecturer.php?id=" . $lecturer['lecturer_id'] . "'>Edit</a>
                                <a href='delete_lecturer.php?id=" . $lecturer['lecturer_id'] . "' onclick='return confirm(\"Are you sure?\");'>Delete</a>
                              </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </section>

        <section id="course_management" class="container">
            <h2>Course Management</h2>
            <form method="POST" action="">
                <input type="text" name="course_name" placeholder="Course Name" required>
                <input type="text" name="course_code" placeholder="Course Code" required>
                <button type="submit" name="add_course">Add Course</button>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_courses = "SELECT * FROM courses";
                    $result_courses = $con->query($sql_courses);
                    while ($course = $result_courses->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($course['course_id']) . "</td>";
                        echo "<td>" . htmlspecialchars($course['course_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($course['course_code']) . "</td>";
                        echo "<td>
                              <a href='edit_course.php?id=" . $course['course_id'] . "'>Edit</a>
                                <a href='delete_course.php?id=" . $course['course_id'] . "' onclick='return confirm(\"Are you sure?\");'>Delete</a>
                              </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </section>
    </div>

    
</body>
</html>