<?php
session_start();
require_once '../connect.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Function to sanitize input
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $table = $_POST['table'] ?? '';

    switch ($action) {
        case 'create':
        case 'update':
            $fields = $_POST['fields'] ?? [];
            $id = $_POST['id'] ?? null;
            
            // Handle password hashing for users
            if (($table === 'admins' || $table === 'lecturers' || $table === 'students') && isset($fields['password'])) {
                $fields['password_hash'] = password_hash($fields['password'], PASSWORD_DEFAULT);
                unset($fields['password']);
            }
            
            $sql = ($action === 'create') 
                ? "INSERT INTO $table (" . implode(", ", array_keys($fields)) . ") VALUES (" . implode(", ", array_fill(0, count($fields), "?")) . ")"
                : "UPDATE $table SET " . implode(" = ?, ", array_keys($fields)) . " = ? WHERE " . $table . "_id = ?";
            
            $stmt = $con->prepare($sql);
            if ($action === 'update') {
                $fields[$table . '_id'] = $id;
            }
            $stmt->bind_param(str_repeat('s', count($fields)), ...array_values($fields));
            $stmt->execute();
            break;
        case 'delete':
            $id = $_POST['id'] ?? null;
            $sql = "DELETE FROM $table WHERE " . $table . "_id = ?";
            $stmt = $con->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            break;
    }
}

// Fetch data for display
$admins = $con->query("SELECT * FROM admin")->fetch_all(MYSQLI_ASSOC);
$lecturers = $con->query("SELECT * FROM lecturers")->fetch_all(MYSQLI_ASSOC);
$students = $con->query("SELECT * FROM students")->fetch_all(MYSQLI_ASSOC);
$courses = $con->query("SELECT * FROM courses")->fetch_all(MYSQLI_ASSOC);
$assignments = $con->query("SELECT * FROM assignments")->fetch_all(MYSQLI_ASSOC);

// Fetch counts for dashboard cards
$stmt = $con->prepare("SELECT 
    (SELECT COUNT(*) FROM students) as student_count,
    (SELECT COUNT(*) FROM lecturers) as lecturer_count,
    (SELECT COUNT(*) FROM courses) as course_count,
    (SELECT COUNT(*) FROM assignments) as assignment_count");
$stmt->execute();
$result = $stmt->get_result();
$counts = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            width: 80%;
            margin: auto;
            overflow: hidden;
            padding: 20px;
        }
        .cardBox {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .card {
            flex: 1;
            background: #fff;
            padding: 20px;
            margin: 0 10px 20px 10px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        .card .numbers {
            font-size: 35px;
            font-weight: 500;
            color: #333;
        }
        .card .cardName {
            color: #999;
            font-size: 16px;
            margin-top: 5px;
        }
        .card .iconBx {
            font-size: 40px;
            color: #4CAF50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            background: #333;
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 15px;
            border-radius: 5px;
        }
        .btn:hover {
            background: #444;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Dashboard</h1>
        
        <div class="cardBox">
            <div class="card">
                <div>
                    <div class="numbers"><?php echo $counts['student_count']; ?></div>
                    <div class="cardName">Students</div>
                </div>
                <div class="iconBx">
                    <i class="fas fa-user-graduate"></i>
                </div>
            </div>
            <div class="card">
                <div>
                    <div class="numbers"><?php echo $counts['lecturer_count']; ?></div>
                    <div class="cardName">Lecturers</div>
                </div>
                <div class="iconBx">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
            </div>
            <div class="card">
                <div>
                    <div class="numbers"><?php echo $counts['course_count']; ?></div>
                    <div class="cardName">Courses</div>
                </div>
                <div class="iconBx">
                    <i class="fas fa-book"></i>
                </div>
            </div>
            <div class="card">
                <div>
                    <div class="numbers"><?php echo $counts['assignment_count']; ?></div>
                    <div class="cardName">Assignments</div>
                </div>
                <div class="iconBx">
                    <i class="fas fa-tasks"></i>
                </div>
            </div>
        </div>

        <h2>Admins</h2>
        <button class="btn" onclick="openModal('admins')">Add Admin</button>
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($admins as $admin): ?>
            <tr>
                <td><?php echo $admin['admin_id']; ?></td>
                <td><?php echo $admin['username']; ?></td>
                <td><?php echo $admin['email']; ?></td>
                <td>
                    <button class="btn" onclick="openModal('admins', <?php echo $admin['admin_id']; ?>)">Edit</button>
                    <button class="btn" onclick="deleteEntity('admins', <?php echo $admin['admin_id']; ?>)">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h2>Lecturers</h2>
        <button class="btn" onclick="openModal('lecturers')">Add Lecturer</button>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Lecturer ID</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($lecturers as $lecturer): ?>
            <tr>
                <td><?php echo $lecturer['lecturer_id']; ?></td>
                <td><?php echo $lecturer['full_name']; ?></td>
                <td><?php echo $lecturer['email']; ?></td>
                <td><?php echo $lecturer['lecturer_id_no']; ?></td>
                <td>
                    <button class="btn" onclick="openModal('lecturers', <?php echo $lecturer['lecturer_id']; ?>)">Edit</button>
                    <button class="btn" onclick="deleteEntity('lecturers', <?php echo $lecturer['lecturer_id']; ?>)">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h2>Students</h2>
        <button class="btn" onclick="openModal('students')">Add Student</button>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Admission No</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($students as $student): ?>
            <tr>
                <td><?php echo $student['student_id']; ?></td>
                <td><?php echo $student['full_name']; ?></td>
                <td><?php echo $student['email']; ?></td>
                <td><?php echo $student['admission_no']; ?></td>
                <td>
                    <button class="btn" onclick="openModal('students', <?php echo $student['student_id']; ?>)">Edit</button>
                    <button class="btn" onclick="deleteEntity('students', <?php echo $student['student_id']; ?>)">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h2>Courses</h2>
        <button class="btn" onclick="openModal('courses')">Add Course</button>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Code</th>
                <th>Actions</th>
            </tr>
            