<?php
session_start();
include '../connect.php';

// Check if the student is logged in
if (!isset($_SESSION['student_id'])) {
  header("Location: ../login.php");
  exit();
}

$student_id = $_SESSION['student_id'];
$course_id = $_GET['course_id'] ?? null;

if (!$course_id) {
  die("No course selected.");
}

// Fetch course details
$sql_course = "SELECT course_name, course_code FROM courses WHERE course_id = ?";
$stmt_course = $con->prepare($sql_course);
$stmt_course->bind_param("i", $course_id);
$stmt_course->execute();
$result_course = $stmt_course->get_result();
$course = $result_course->fetch_assoc();
$stmt_course->close();

// Fetch assignments for the course
$sql_assignments = "
  SELECT a.assignment_id, a.title, a.description, a.due_date,
         sa.status, sa.marks_obtained, sa.comments
  FROM assignments a
  LEFT JOIN student_assignments sa ON a.assignment_id = sa.assignment_id AND sa.student_id = ?
  WHERE a.course_id = ? AND a.is_active = TRUE
  ORDER BY a.due_date
";
$stmt_assignments = $con->prepare($sql_assignments);
$stmt_assignments->bind_param("ii", $student_id, $course_id);
$stmt_assignments->execute();
$result_assignments = $stmt_assignments->get_result();
$assignments = $result_assignments->fetch_all(MYSQLI_ASSOC);
$stmt_assignments->close();

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
  $assignment_id = $_POST['assignment_id'];
  $submission_text = $_POST['submission_text'];
  $file_path = null;

  // Start transaction
  $con->begin_transaction();

  try {
      // Insert or update in student_assignments table
      $sql_submit = "
          INSERT INTO student_assignments (student_id, assignment_id, submission_date, status)
          VALUES (?, ?, NOW(), 'submitted')
          ON DUPLICATE KEY UPDATE
          submission_date = NOW(), status = 'submitted'
      ";
      $stmt_submit = $con->prepare($sql_submit);
      $stmt_submit->bind_param("ii", $student_id, $assignment_id);
      $stmt_submit->execute();

      // Get the student_assignment_id
      $student_assignment_id = $stmt_submit->insert_id ?: $con->insert_id;

      // Handle file upload if present
      if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == 0) {
          $upload_dir = 'uploads/';
          $file_name = uniqid() . '_' . $_FILES['submission_file']['name'];
          $file_path = $upload_dir . $file_name;
          move_uploaded_file($_FILES['submission_file']['tmp_name'], $file_path);
      }

      // Insert into assignment_submissions table
      $sql_submit_file = "
          INSERT INTO assignment_submissions (student_assignment_id, file_path, submission_text, uploaded_at)
          VALUES (?, ?, ?, NOW())
          ON DUPLICATE KEY UPDATE
          file_path = VALUES(file_path), submission_text = VALUES(submission_text), uploaded_at = NOW()
      ";
      $stmt_submit_file = $con->prepare($sql_submit_file);
      $stmt_submit_file->bind_param("iss", $student_assignment_id, $file_path, $submission_text);
      $stmt_submit_file->execute();

      // Commit transaction
      $con->commit();

      $submission_message = "Assignment submitted successfully!";
  } catch (Exception $e) {
      // Rollback transaction on error
      $con->rollback();
      $submission_error = "Error submitting assignment: " . $e->getMessage();
  }

  // Close statements
  if (isset($stmt_submit)) $stmt_submit->close();
  if (isset($stmt_submit_file)) $stmt_submit_file->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Assignment Details - <?php echo htmlspecialchars($course['course_name']); ?></title>
  <link rel="stylesheet" href="studentdash.css">
  <style>
      .assignment-box {
          background-color: #f9f9f9;
          border: 1px solid #ddd;
          padding: 20px;
          margin-bottom: 20px;
          border-radius: 5px;
      }
      .assignment-box h3 {
          margin-top: 0;
      }
      .submission-form {
          margin-top: 15px;
      }
      .submission-form label {
          display: block;
          margin-top: 10px;
      }
      .submission-form textarea,
      .submission-form input[type="file"] {
          width: 100%;
          padding: 8px;
          margin-top: 5px;
      }
      .submission-form button {
          background-color: #4CAF50;
          color: white;
          padding: 10px 15px;
          border: none;
          border-radius: 4px;
          cursor: pointer;
          margin-top: 10px;
      }
      .submission-form button:hover {
          background-color: #45a049;
      }
      .success {
          color: green;
          font-weight: bold;
      }
      .error {
          color: red;
          font-weight: bold;
      }
  </style>
</head>
<body>
  <div class="main-content">
      <h1><?php echo htmlspecialchars($course['course_name']); ?> (<?php echo htmlspecialchars($course['course_code']); ?>)</h1>
      <h2>Assignments</h2>
      
      <?php if (isset($submission_message)): ?>
          <p class="success"><?php echo $submission_message; ?></p>
      <?php endif; ?>
      
      <?php if (isset($submission_error)): ?>
          <p class="error"><?php echo $submission_error; ?></p>
      <?php endif; ?>
      
      <?php foreach ($assignments as $assignment): ?>
          <div class="assignment-box">
              <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
              <p class="due-date">Due Date: <?php echo htmlspecialchars($assignment['due_date']); ?></p>
              <div class="assignment-description">
                  <h4>Assignment:</h4>
                  <p><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
              </div>
              <p class="status">Status: <?php echo htmlspecialchars($assignment['status'] ?? 'Not submitted'); ?></p>
              
              <?php if ($assignment['status'] === 'submitted' || $assignment['status'] === 'graded'): ?>
                  <p>Marks: <?php echo htmlspecialchars($assignment['marks_obtained'] ?? 'Not graded yet'); ?></p>
                  <p>Comments: <?php echo htmlspecialchars($assignment['comments'] ?? 'No comments'); ?></p>
              <?php else: ?>
                  <form action="" method="POST" enctype="multipart/form-data" class="submission-form">
                      <input type="hidden" name="assignment_id" value="<?php echo $assignment['assignment_id']; ?>">
                      <label for="submission_text_<?php echo $assignment['assignment_id']; ?>">Your Submission:</label>
                      <textarea id="submission_text_<?php echo $assignment['assignment_id']; ?>" name="submission_text" rows="5" placeholder="Enter your response here"></textarea>
                      <label for="submission_file_<?php echo $assignment['assignment_id']; ?>">Attach File (optional):</label>
                      <input type="file" id="submission_file_<?php echo $assignment['assignment_id']; ?>" name="submission_file">
                      <button type="submit" name="submit_assignment">Submit Assignment</button>
                  </form>
              <?php endif; ?>
          </div>
      <?php endforeach; ?>
      
      <a href="studentdash.php" class="btn">Back to Dashboard</a>
  </div>
</body>
</html>