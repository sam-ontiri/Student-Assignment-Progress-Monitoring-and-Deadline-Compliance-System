<?php
session_start();
include '../connect.php';

if (!isset($_SESSION['lecturer_id'])) {
    header("Location: ../login.php");
    exit();
}

$lecturer_id = $_SESSION['lecturer_id'];
$course_id = $_GET['course_id'] ?? null;

if (!$course_id) {
    header("Location: lecturerdash.php");
    exit();
}

// Fetch course details
$sql_course = "SELECT course_name FROM courses WHERE course_id = ?";
$stmt_course = $con->prepare($sql_course);
$stmt_course->bind_param("i", $course_id);
$stmt_course->execute();
$course = $stmt_course->get_result()->fetch_assoc();
$stmt_course->close();

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_assignment']) || isset($_POST['update_assignment'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $due_date = $_POST['due_date'];
        $assignment_id = $_POST['assignment_id'] ?? null;

        if (empty($title) || empty($description) || empty($due_date)) {
            $error = "All fields are required.";
        } else {
            if (isset($_POST['create_assignment'])) {
                $sql = "INSERT INTO assignments (title, description, course_id, lecturer_id, due_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $con->prepare($sql);
                $stmt->bind_param("ssiss", $title, $description, $course_id, $lecturer_id, $due_date);
            } else {
                $sql = "UPDATE assignments SET title = ?, description = ?, due_date = ? WHERE assignment_id = ? AND lecturer_id = ?";
                $stmt = $con->prepare($sql);
                $stmt->bind_param("sssii", $title, $description, $due_date, $assignment_id, $lecturer_id);
            }

            if ($stmt->execute()) {
                $success = isset($_POST['create_assignment']) ? "Assignment created successfully!" : "Assignment updated successfully!";
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['delete_assignment'])) {
        $assignment_id = $_POST['assignment_id'];
        $sql = "DELETE FROM assignments WHERE assignment_id = ? AND lecturer_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("ii", $assignment_id, $lecturer_id);
        if ($stmt->execute()) {
            $success = "Assignment deleted successfully!";
        } else {
            $error = "Error deleting assignment: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch assignments for this course
$sql_assignments = "SELECT * FROM assignments WHERE course_id = ? AND lecturer_id = ? ORDER BY due_date";
$stmt_assignments = $con->prepare($sql_assignments);
$stmt_assignments->bind_param("ii", $course_id, $lecturer_id);
$stmt_assignments->execute();
$assignments = $stmt_assignments->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_assignments->close();

// Fetch assignment for editing
$edit_assignment = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $sql_edit = "SELECT * FROM assignments WHERE assignment_id = ? AND lecturer_id = ?";
    $stmt_edit = $con->prepare($sql_edit);
    $stmt_edit->bind_param("ii", $edit_id, $lecturer_id);
    $stmt_edit->execute();
    $edit_assignment = $stmt_edit->get_result()->fetch_assoc();
    $stmt_edit->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments for <?php echo htmlspecialchars($course['course_name']); ?></title>
    <link rel="stylesheet" href="create_assignment.css">
</head>
<body>
    <div class="container">
        <h1>Assignments for <?php echo htmlspecialchars($course['course_name']); ?></h1>
        
        <?php if (isset($success)): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="assignment_id" value="<?php echo $edit_assignment['assignment_id'] ?? ''; ?>">
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($edit_assignment['title'] ?? ''); ?>" required>
            
            <label for="description">Assignment:</label>
            <textarea id="description" name="description" required><?php echo htmlspecialchars($edit_assignment['description'] ?? ''); ?></textarea>
            
            <label for="due_date">Due Date:</label>
            <input type="datetime-local" id="due_date" name="due_date" value="<?php echo $edit_assignment ? date('Y-m-d\TH:i', strtotime($edit_assignment['due_date'])) : ''; ?>" required>
            
            <?php if ($edit_assignment): ?>
                <button type="submit" name="update_assignment">Update Assignment</button>
            <?php else: ?>
                <button type="submit" name="create_assignment">Create Assignment</button>
            <?php endif; ?>
        </form>

        <h2>Existing Assignments</h2>
        <?php if (!empty($assignments)): ?>
            <?php foreach ($assignments as $assignment): ?>
                <div class="assignment">
                    <h2><?php echo htmlspecialchars($assignment['title']); ?></h2>
                    <p><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                    <p class="due-date">Due: <?php echo date('F j, Y, g:i a', strtotime($assignment['due_date'])); ?></p>
                    <a href="?course_id=<?php echo $course_id; ?>&edit=<?php echo $assignment['assignment_id']; ?>">Edit</a>
                    <a href="#" onclick="confirmDelete(<?php echo $assignment['assignment_id']; ?>)">Delete</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No assignments found for this course.</p>
        <?php endif; ?>
        <a href="../Dashboard/lecturerdash.php"><button class="btn">Back to Dashboard</button></  
    </div>
     
   

    <script>
    function confirmDelete(assignmentId) {
        if (confirm('Are you sure you want to delete this assignment?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'assignment_id';
            input.value = assignmentId;
            form.appendChild(input);
            var submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'delete_assignment';
            submitInput.value = '1';
            form.appendChild(submitInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>