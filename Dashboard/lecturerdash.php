<?php
session_start();
include '../connect.php';

// Check if the lecturer is logged in
if (!isset($_SESSION['lecturer_id'])) {
    header("Location: ../login.php"); // Redirect to login if not logged in
    exit();
}

// Get the lecturer ID from the session
$lecturer_id = $_SESSION['lecturer_id'];

// Fetch lecturer information
$sql_lecturer = "SELECT full_name, email, lecturer_id_no FROM lecturers WHERE lecturer_id = ?";
$stmt_lecturer = $con->prepare($sql_lecturer);
$stmt_lecturer->bind_param("i", $lecturer_id);
$stmt_lecturer->execute();
$result_lecturer = $stmt_lecturer->get_result();
$lecturer = $result_lecturer->fetch_assoc();
$stmt_lecturer->close();

// Update assignment status based on due date
$current_date = date('Y-m-d H:i:s');
$update_status_sql = "
    UPDATE student_assignments sa
    JOIN assignments a ON sa.assignment_id = a.assignment_id
    SET sa.status = CASE
        WHEN sa.status = 'not_submitted' AND a.due_date < ? THEN 'late'
        ELSE sa.status
    END
    WHERE a.lecturer_id = ?
";
$stmt_update_status = $con->prepare($update_status_sql);
$stmt_update_status->bind_param("si", $current_date, $lecturer_id);
$stmt_update_status->execute();
$stmt_update_status->close();

// Fetch updated assignment status counts
$sql_assignment_status = "
    SELECT 
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'not_submitted' THEN 1 ELSE 0 END) as not_submitted,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
    FROM student_assignments sa
    JOIN assignments a ON sa.assignment_id = a.assignment_id
    WHERE a.lecturer_id = ?
";
$stmt = $con->prepare($sql_assignment_status);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $assignment_status = $row;
}
$stmt->close();

// Fetch recent progress tracking data
$progress_data = [];
$sql_progress = "
    SELECT 
        s.full_name AS student_name,
        a.title AS assignment_title,
        sa.submission_date,
        sa.status as progress_status,
        sa.comments as remarks
    FROM 
        student_assignments sa
    JOIN 
        students s ON sa.student_id = s.student_id
    JOIN 
        assignments a ON sa.assignment_id = a.assignment_id
    WHERE 
        a.lecturer_id = ?
    ORDER BY sa.submission_date DESC
    LIMIT 5
";

$stmt_progress = $con->prepare($sql_progress);
$stmt_progress->bind_param("i", $lecturer_id);
$stmt_progress->execute();
$result_progress = $stmt_progress->get_result();
$progress_data = $result_progress->fetch_all(MYSQLI_ASSOC);
$stmt_progress->close();

// Fetch notifications sent to the lecturer
$received_notifications = [];
$sql_received_notifications = "
    SELECT 
        n.notification_id,
        n.message,
        n.created_at,
        n.is_read,
        s.full_name AS sender_name,
        s.student_id
    FROM 
        notifications n
    JOIN 
        students s ON n.student_id = s.student_id
    WHERE 
        n.lecturer_id = ? AND n.sender_type = 'student'
    ORDER BY 
        n.created_at DESC
    LIMIT 5
";

$stmt_received_notifications = $con->prepare($sql_received_notifications);
$stmt_received_notifications->bind_param("i", $lecturer_id);
$stmt_received_notifications->execute();
$result_received_notifications = $stmt_received_notifications->get_result();
$received_notifications = $result_received_notifications->fetch_all(MYSQLI_ASSOC);
$stmt_received_notifications->close();

// Fetch notifications sent by the lecturer
$sent_notifications = [];
$sql_sent_notifications = "
    SELECT 
        n.notification_id,
        n.message,
        n.created_at,
        s.full_name AS recipient_name
    FROM 
        notifications n
    JOIN 
        students s ON n.student_id = s.student_id
    WHERE 
        n.lecturer_id = ? AND n.sender_type = 'lecturer'
    ORDER BY 
        n.created_at DESC
    LIMIT 5
";

$stmt_sent_notifications = $con->prepare($sql_sent_notifications);
$stmt_sent_notifications->bind_param("i", $lecturer_id);
$stmt_sent_notifications->execute();
$result_sent_notifications = $stmt_sent_notifications->get_result();
$sent_notifications = $result_sent_notifications->fetch_all(MYSQLI_ASSOC);
$stmt_sent_notifications->close();

// Handle sending notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $student_id = $_POST['student_id'];
    $message = $_POST['message'];
    
    $sql_insert_notification = "
        INSERT INTO notifications (student_id, lecturer_id, message, created_at, sender_type)
        VALUES (?, ?, ?, NOW(), 'lecturer')
    ";
    $stmt_insert_notification = $con->prepare($sql_insert_notification);
    $stmt_insert_notification->bind_param("iis", $student_id, $lecturer_id, $message);
    
    if ($stmt_insert_notification->execute()) {
        $notification_sent = true;
    } else {
        $notification_error = "Error sending notification. Please try again.";
    }
    $stmt_insert_notification->close();
}

// Handle marking notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_read'])) {
    $notification_id = $_POST['notification_id'];
    
    $sql_mark_as_read = "
        UPDATE notifications
        SET is_read = 1
        WHERE notification_id = ? AND lecturer_id = ?
    ";
    $stmt_mark_as_read = $con->prepare($sql_mark_as_read);
    $stmt_mark_as_read->bind_param("ii", $notification_id, $lecturer_id);
    
    if ($stmt_mark_as_read->execute()) {
        $mark_as_read_success = true;
    } else {
        $mark_as_read_error = "Error marking notification as read. Please try again.";
    }
    $stmt_mark_as_read->close();
}

// Fetch assignments that need grading
$assignments_to_grade = [];
$sql_assignments_to_grade = "
    SELECT 
        sa.student_assignment_id,
        s.full_name AS student_name,
        a.title AS assignment_title,
        c.course_name,
        sa.submission_date,
        sa.status,
        sa.marks_obtained,
        asu.file_path,
        asu.submission_text
    FROM 
        student_assignments sa
    JOIN 
        assignments a ON sa.assignment_id = a.assignment_id
    JOIN 
        students s ON sa.student_id = s.student_id
    JOIN
        courses c ON a.course_id = c.course_id
    LEFT JOIN
        assignment_submissions asu ON sa.student_assignment_id = asu.student_assignment_id
    WHERE 
        a.lecturer_id = ? AND sa.status = 'submitted' AND sa.marks_obtained IS NULL
    ORDER BY 
        sa.submission_date ASC
";

$stmt_assignments_to_grade = $con->prepare($sql_assignments_to_grade);
$stmt_assignments_to_grade->bind_param("i", $lecturer_id);
$stmt_assignments_to_grade->execute();
$result_assignments_to_grade = $stmt_assignments_to_grade->get_result();
$assignments_to_grade = $result_assignments_to_grade->fetch_all(MYSQLI_ASSOC);
$stmt_assignments_to_grade->close();

// Handle assignment grading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_assignment'])) {
    $student_assignment_id = $_POST['student_assignment_id'];
    $marks_obtained = $_POST['marks_obtained'];
    $comments = $_POST['comments'];
    
    $sql_update_grade = "
        UPDATE student_assignments
        SET marks_obtained = ?, comments = ?
        WHERE student_assignment_id = ?
    ";
    $stmt_update_grade = $con->prepare($sql_update_grade);
    $stmt_update_grade->bind_param("dsi", $marks_obtained, $comments, $student_assignment_id);
    
    if ($stmt_update_grade->execute()) {
        $grading_success = true;
    } else {
        $grading_error = "Error updating grade. Please try again.";
    }
    $stmt_update_grade->close();
}



// Fetch courses taught by the lecturer
$courses = [];
$sql_courses = "
    SELECT c.course_id, c.course_name, c.course_code 
    FROM courses c
    JOIN course_lecturer cl ON c.course_id = cl.course_id
    WHERE cl.lecturer_id = ?
";
$stmt = $con->prepare($sql_courses);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();


// Fetch assignments created by the lecturer
$sql_assignments = "
    SELECT a.assignment_id, a.title, a.due_date, c.course_name
    FROM assignments a
    JOIN courses c ON a.course_id = c.course_id
    WHERE a.lecturer_id = ?
    ORDER BY a.due_date
";

$stmt_assignments = $con->prepare($sql_assignments);
$stmt_assignments->bind_param("i", $lecturer_id);
$stmt_assignments->execute();
$result_assignments = $stmt_assignments->get_result();

$assignments = [];
while ($row = $result_assignments->fetch_assoc()) {
    $assignments[] = $row;
}

$stmt_assignments->close();

// Convert assignments to JSON for JavaScript use
$assignments_json = json_encode($assignments);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard</title>
    <script defer src="lecturerdash.js"></script>
    <link rel="stylesheet" href="lecturerdash.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;300;400;500;600;700&display =swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.min.css" rel="stylesheet">
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
                <a href="#Dashboard">
                    <i class="fa-solid fa-grip"></i>
                    <span class="nav-item">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="#MyCourses">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <span class="nav-item">Courses</span>
                </a>
            </li>
            <li>
                <a href="#Assignments">
                    <i class="fa-solid fa-file-alt"></i>
                    <span class="nav-item">Assignments</span>
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
            <h1>Welcome, <?php echo htmlspecialchars($lecturer['full_name']); ?></h1>
            <p>Lecturer ID: <?php echo htmlspecialchars($lecturer['lecturer_id_no']); ?></p>
            <div class="cardBox">
                <div class="card">
                    <div class="numbers"><?php echo $assignment_status['submitted']; ?></div>
                    <div class="cardName">Submitted</div>
                    <i class="fa-solid fa-check"></i>
                </div>
                <div class="card">
                    <div class="numbers"><?php echo $assignment_status['not_submitted']; ?></div>
                    <div class="cardName">Not Submitted</div>
                    <i class="fa-solid fa-times"></i>
                </div>
                <div class="card">
                    <div class="numbers"><?php echo $assignment_status['late']; ?></div>
                    <div class="cardName">Late Submitted</div>
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>

            <div class="deadlinedetails">
                <div class="recentdeadlines">
                    <div class="cardHeader">
                        <h2>Student Progress Tracking</h2>
                        <a href="#" class="btn">View All</a>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <td>Student Name</td>
                                <td>Assignment</td>
                                <td>Submission Date</td>
                                <td>Status</td>
        
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($progress_data)) {
                                foreach ($progress_data as $progress) {
                                    echo '<tr>';
                                    echo '<td>' . htmlspecialchars($progress['student_name']) . '</td>';
                                    echo '<td>' . htmlspecialchars($progress['assignment_title']) . '</td>';
                                    echo '<td>' . htmlspecialchars($progress['submission_date']) . '</td>';
                                    echo '<td><span class="status ' . strtolower($progress['progress_status']) . '">' . htmlspecialchars($progress['progress_status']) . '</span></td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="5">No progress data available.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
               
                <section id="Notifications" class="container">
            <h1>Notifications</h1>
            <div class="notification-container">
                <div class="received-notifications">
                    <h2>Received Notifications</h2>
                    <ul>
                        <?php
                        if (!empty($received_notifications)) {
                            foreach ($received_notifications as $notification) {
                                echo '<li class="' . ($notification['is_read'] ? 'read' : 'unread') . '">';
                                echo '<div class="notification-content">';
                                echo '<p><strong>From: ' . htmlspecialchars($notification['sender_name']) . '</strong></p>';
                                echo '<p>' . htmlspecialchars($notification['message']) . '</p>';
                                echo '<span class="notification-date">' . htmlspecialchars(date("F j, Y, g:i a", strtotime($notification['created_at']))) . '</span>';
                                echo '</div>';
                                if (!$notification['is_read']) {
                                    echo '<form method="POST" action="">';
                                    echo '<input type="hidden" name="notification_id" value="' . $notification['notification_id'] . '">';
                                    echo '<button type="submit" name="mark_as_read">Mark as Read</button>';
                                    echo '</form>';
                                }
                                echo '</li>';
                            }
                        } else {
                            echo '<li class="no-notifications">No received notifications.</li>';
                        }
                        ?>
                    </ul>
                </div>
                
                <div class="sent-notifications">
                    <h2>Sent Notifications</h2>
                    <ul>
                        <?php
                        if (!empty($sent_notifications)) {
                            foreach ($sent_notifications as $notification) {
                                echo '<li>';
                                echo '<div class="notification-content">';
                                echo '<p><strong>To: ' . htmlspecialchars($notification['recipient_name']) . '</strong></p>';
                                echo '<p>' . htmlspecialchars($notification['message']) . '</p>';
                                echo '<span class="notification-date">' . htmlspecialchars(date("F j, Y, g:i a", strtotime($notification['created_at']))) . '</span>';
                                echo '</div>';
                                echo '</li>';
                            }
                        } else {
                            echo '<li class="no-notifications">No sent notifications.</li>';
                        }
                        ?>
                    </ul>
                </div>
            </div>
            
            <div class="send-notification">
                <h2>Send Notification</h2>
                <form method="POST" action="">
                    <select name="student_id" required>
                        <option value="">Select a student</option>
                        <?php
                        // Fetch students for the courses taught by the lecturer
                        $sql_students = "
                            SELECT DISTINCT s.student_id, s.full_name
                            FROM students s
                            JOIN course_enrollment ce ON s.student_id = ce.student_id
                            JOIN course_lecturer cl ON ce.course_id = cl.course_id
                            WHERE cl.lecturer_id = ?
                        ";
                        $stmt_students = $con->prepare($sql_students);
                        $stmt_students->bind_param("i", $lecturer_id);
                        $stmt_students->execute();
                        $result_students = $stmt_students->get_result();
                        while ($student = $result_students->fetch_assoc()) {
                            echo '<option value="' . $student['student_id'] . '">' . htmlspecialchars($student['full_name']) . '</option>';
                        }
                        $stmt_students->close();
                        ?>
                    </select>
                    <textarea name="message" placeholder="Type your message here" required></textarea>
                    <button type="submit" name="send_notification">Send Notification</button>
                </form>
                <?php
                if (isset($notification_sent)) {
                    echo '<p class="success">Notification sent successfully!</p>';
                }
                if (isset($notification_error)) {
                    echo '<p class="error">' . $notification_error . '</p>';
                }
                ?>
            </div>
            </div>    
        </section>

        <section id="MyCourses" class="container">
    <h1>My Courses</h1>
    <div class="course-container">
        <?php
        if (!empty($courses)) {
            foreach ($courses as $course) {
                echo '<div class="course-box" onclick="window.location.href=\'create_assignment.php?course_id=' . htmlspecialchars($course['course_id']) . '\'">';
                echo '<h3>' . htmlspecialchars($course['course_name']) . '</h3>';
                echo '<i class="fa-solid fa-graduation-cap"></i><br>';
                echo '<p>Course Code: ' . htmlspecialchars($course['course_code']) . '</p>';
                echo '</div>';
            }
        } else {
            echo '<p>You are not teaching any courses yet.</p>';
        }
        ?>
    </div>
</section>

<section id="Assignments" class="container">
        <h1>Assignments to Grade</h1>
        <?php if (isset($grading_success)): ?>
            <div class="message success">Assignment graded successfully!</div>
        <?php endif; ?>
        <?php if (isset($grading_error)): ?>
            <div class="message error"><?php echo $grading_error; ?></div>
        <?php endif; ?>
        <?php if (!empty($assignments_to_grade)): ?>
            <div class="assignments-grid">
                <?php foreach ($assignments_to_grade as $assignment): ?>
                    <div class="assignment-card">
                        <h3><?php echo htmlspecialchars($assignment['assignment_title']); ?></h3>
                        <p><strong>Course:</strong> <?php echo htmlspecialchars($assignment['course_name']); ?></p>
                        <p><strong>Student:</strong> <?php echo htmlspecialchars($assignment['student_name']); ?></p>
                        <p><strong>Submitted:</strong> <?php echo htmlspecialchars($assignment['submission_date']); ?></p>
                        <?php if ($assignment['submission_text']): ?>
                            <p><strong>Submission Text:</strong> <?php echo nl2br(htmlspecialchars($assignment['submission_text'])); ?></p>
                        <?php endif; ?>
                        <?php if ($assignment['file_path']): ?>
                            <p><strong>Attached File:</strong> <a href="<?php echo htmlspecialchars($assignment['file_path']); ?>" target="_blank">View File</a></p>
                        <?php endif; ?>
                        <form method="POST" action="" class="grading-form">
                            <input type="hidden" name="student_assignment_id" value="<?php echo $assignment['student_assignment_id']; ?>">
                            <div class="form-group">
                                <label for="marks_obtained">Marks:</label>
                                <input type="number" id="marks_obtained" name="marks_obtained" min="0" max="100" required>
                            </div>
                            <div class="form-group">
                                <label for="comments">Comments:</label>
                                <textarea id="comments" name="comments" rows="3"></textarea>
                            </div>
                            <button type="submit" name="grade_assignment" class="btn-grade">Submit Grade</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No assignments to grade at this time.</p>
        <?php endif; ?>
    </section>

    
    <section id="Calendar" class="container">
            <h1>Calendar</h1>
            <div id="calendar"></div>
        </section>

        <!-- Rest of your dashboard content -->
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var assignments = <?php echo $assignments_json; ?>;

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            events: assignments.map(function(assignment) {
                return {
                    title: assignment.title + ' (' + assignment.course_name + ')',
                    start: assignment.due_date,
                    allDay: true,
                    color: getRandomColor()
                };
            }),
            eventClick: function(info) {
                alert('Assignment: ' + info.event.title + '\nDue Date: ' + info.event.start.toDateString());
            }
        });

        calendar.render();

        function getRandomColor() {
            var letters = '0123456789ABCDEF';
            var color = '#';
            for (var i = 0; i < 6; i++) {
                color += letters[Math.floor(Math.random() * 16)];
            }
            return color;
        }
    });
    </script>
    </div>
</div>
</body>
</html>