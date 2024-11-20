<?php
session_start();
include '../connect.php';

// Check if the student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Fetch student information
$sql_student = "SELECT full_name, email, admission_no FROM students WHERE student_id = ?";
$stmt_student = $con->prepare($sql_student);
$stmt_student->bind_param("i", $student_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();
$student = $result_student->fetch_assoc();
$stmt_student->close();

// Update assignment status counts
$current_date = date('Y-m-d H:i:s');
$sql_assignment_status = "
    SELECT 
        SUM(CASE WHEN sa.status = 'submitted' AND sa.submission_date <= a.due_date THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN sa.status IS NULL OR sa.status = 'not_submitted' THEN 1 ELSE 0 END) as not_submitted,
        SUM(CASE WHEN sa.status = 'submitted' AND sa.submission_date > a.due_date THEN 1 ELSE 0 END) as late
    FROM assignments a
    LEFT JOIN student_assignments sa ON a.assignment_id = sa.assignment_id AND sa.student_id = ?
    JOIN course_enrollment ce ON a.course_id = ce.course_id
    WHERE ce.student_id = ?
";
$stmt = $con->prepare($sql_assignment_status);
$stmt->bind_param("ii", $student_id, $student_id);
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
        a.title AS assignment_title,
        c.course_name,
        a.due_date,
        sa.submission_date,
        CASE
            WHEN sa.status IS NULL THEN 'Not Submitted'
            WHEN sa.status = 'submitted' AND sa.submission_date <= a.due_date THEN 'Submitted On Time'
            WHEN sa.status = 'submitted' AND sa.submission_date > a.due_date THEN 'Submitted Late'
            ELSE sa.status
        END as progress_status
    FROM 
        assignments a
    JOIN 
        courses c ON a.course_id = c.course_id
    LEFT JOIN 
        student_assignments sa ON a.assignment_id = sa.assignment_id AND sa.student_id = ?
    JOIN 
        course_enrollment ce ON c.course_id = ce.course_id
    WHERE 
        ce.student_id = ?
    ORDER BY 
        COALESCE(sa.submission_date, a.due_date) DESC
    LIMIT 5
";

$stmt_progress = $con->prepare($sql_progress);
$stmt_progress->bind_param("ii", $student_id, $student_id);
$stmt_progress->execute();
$result_progress = $stmt_progress->get_result();

if ($result_progress->num_rows > 0) {
    while ($row = $result_progress->fetch_assoc()) {
        $progress_data[] = $row;
    }
}
$stmt_progress->close();

// Fetch notifications received by the student
$received_notifications = [];
$sql_received_notifications = "
    SELECT 
        n.notification_id,
        n.message,
        n.created_at,
        n.is_read,
        l.full_name AS sender_name,
        l.lecturer_id
    FROM 
        notifications n
    JOIN 
        lecturers l ON n.lecturer_id = l.lecturer_id
    WHERE 
        n.student_id = ? AND n.sender_type = 'lecturer'
    ORDER BY 
        n.created_at DESC
    LIMIT 5
";

$stmt_received_notifications = $con->prepare($sql_received_notifications);
$stmt_received_notifications->bind_param("i", $student_id);
$stmt_received_notifications->execute();
$result_received_notifications = $stmt_received_notifications->get_result();
$received_notifications = $result_received_notifications->fetch_all(MYSQLI_ASSOC);
$stmt_received_notifications->close();

// Fetch notifications sent by the student
$sent_notifications = [];
$sql_sent_notifications = "
    SELECT 
        n.notification_id,
        n.message,
        n.created_at,
        l.full_name AS recipient_name
    FROM 
        notifications n
    JOIN 
        lecturers l ON n.lecturer_id = l.lecturer_id
    WHERE 
        n.student_id = ? AND n.sender_type = 'student'
    ORDER BY 
        n.created_at DESC
    LIMIT 5
";

$stmt_sent_notifications = $con->prepare($sql_sent_notifications);
$stmt_sent_notifications->bind_param("i", $student_id);
$stmt_sent_notifications->execute();
$result_sent_notifications = $stmt_sent_notifications->get_result();
$sent_notifications = $result_sent_notifications->fetch_all(MYSQLI_ASSOC);
$stmt_sent_notifications->close();

// Handle sending notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $lecturer_id = $_POST['lecturer_id'];
    $message = $_POST['message'];
    
    $sql_insert_notification = "
        INSERT INTO notifications (student_id, lecturer_id, message, created_at, sender_type)
        VALUES (?, ?, ?, NOW(), 'student')
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
        WHERE notification_id = ? AND student_id = ?
    ";
    $stmt_mark_as_read = $con->prepare($sql_mark_as_read);
    $stmt_mark_as_read->bind_param("ii", $notification_id, $student_id);
    
    if ($stmt_mark_as_read->execute()) {
        $mark_as_read_success = true;
    } else {
        $mark_as_read_error = "Error marking notification as read. Please try again.";
    }
    $stmt_mark_as_read->close();
}


// Fetch courses the student is enrolled in
$courses = [];
$sql_courses = "
    SELECT c.course_id, c.course_name, c.course_code 
    FROM courses c
    JOIN course_enrollment ce ON c.course_id = ce.course_id
    WHERE ce.student_id = ?
";
$stmt = $con->prepare($sql_courses);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}
$stmt->close();

// Handle course enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_course'])) {
    $course_id = $_POST['course_id'];
    
    // Check if already enrolled
    $sql_check_enrollment = "SELECT * FROM course_enrollment WHERE student_id = ? AND course_id = ?";
    $stmt_check = $con->prepare($sql_check_enrollment);
    $stmt_check->bind_param("ii", $student_id, $course_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows == 0) { // Not already enrolled
        $sql_enroll = "INSERT INTO course_enrollment (student_id, course_id, enrollment_date) VALUES (?, ?, NOW())";
        $stmt_enroll = $con->prepare($sql_enroll);
        $stmt_enroll->bind_param("ii", $student_id, $course_id);
        
        if ($stmt_enroll->execute()) {
            $enrollment_message = "Enrolled in course successfully!";
        } else {
            $enrollment_error = "Error enrolling in course. Please try again.";
        }
        $stmt_enroll->close();
    } else {
        $enrollment_error = "You are already enrolled in this course.";
    }
    $stmt_check->close();

    // Redirect to the same page to prevent resubmission
    header("Location: studentdash.php");
    exit();
}

// Fetch available courses for enrollment
$available_courses = [];
$sql_available_courses = "
    SELECT c.course_id, c.course_name, c.course_code 
    FROM courses c
    WHERE c.course_id NOT IN (
        SELECT ce.course_id 
        FROM course_enrollment ce 
        WHERE ce.student_id = ?
    )
";
$stmt_available = $con->prepare($sql_available_courses);
$stmt_available->bind_param("i", $student_id);
$stmt_available->execute();
$result_available = $stmt_available->get_result();

if ($result_available->num_rows > 0) {
    while ($row = $result_available->fetch_assoc()) {
        $available_courses[] = $row;
    }
}
$stmt_available->close();

// Fetch assignments for the student
$sql_assignments = "
    SELECT a.assignment_id, a.title, a.due_date, c.course_name
    FROM assignments a
    JOIN courses c ON a.course_id = c.course_id
    JOIN course_enrollment ce ON c.course_id = ce.course_id
    WHERE ce.student_id = ? AND a.due_date >= CURDATE()
    ORDER BY a.due_date
";

$stmt_assignments = $con->prepare($sql_assignments);
$stmt_assignments->bind_param("i", $student_id);
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
    <title>Student Dashboard</title>
    <script defer src="studentdash.js"></script>
    <link rel="stylesheet" href="studentdash.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;300;400;500;600;700&display=swap" rel="stylesheet">
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
                    <i class="fas fa-user-graduate"></i>
                    <span class="nav-item">My Courses</span>
                </a>
            </li>
            <li>
                <a href="#Calendar">
                    <i class="fa-solid fa-calendar"></i>
                    <span class="nav-item">Calendar</span>
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
            <h1>Welcome, <?php echo htmlspecialchars($student['full_name']); ?></h1>
            <p>Admission No: <?php echo htmlspecialchars($student['admission_no']); ?></p>
            <div class="cardBox">
                <div class="card">
                    <div class="numbers"><?php echo $assignment_status['submitted']; ?></div>
                    <div class="cardName">Submitted On Time</div>
                    <i class="fa-solid fa-check"></i>
                </div>
                <div class="card">
                    <div class="numbers"><?php echo $assignment_status['not_submitted']; ?></div>
                    <div class="cardName">Not Submitted</div>
                    <i class="fa-solid fa-times"></i>
                </div>
                <div class="card">
                    <div class="numbers"><?php echo $assignment_status['late']; ?></div>
                    <div class="cardName">Submitted Late</div>
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>

            <div class="deadlinedetails">
                <div class="recentdeadlines">
                    <div class="cardHeader">
                        <h2>Recent Progress</h2>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <td>Assignment</td>
                                <td>Course</td>
                                <td>Due Date</td>
                                <td>Status</td>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        if (!empty($progress_data)) {
                            foreach ($progress_data as $progress) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($progress['assignment_title']) . '</td>';
                                echo '<td>' . htmlspecialchars($progress['course_name']) . '</td>';
                                echo '<td>' . htmlspecialchars($progress['due_date']) . '</td>';
                                echo '<td><span class="status ' . strtolower(str_replace(' ', '-', $progress['progress_status'])) . '">' . htmlspecialchars($progress['progress_status']) . '</span></td>';
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
                            <select name="lecturer_id" required>
                                <option value="">Select a lecturer</option>
                                <?php
                                // Fetch lecturers for the courses enrolled by the student
                                $sql_lecturers = "
                                    SELECT DISTINCT l.lecturer_id, l.full_name
                                    FROM lecturers l
                                    JOIN course_lecturer cl ON l.lecturer_id = cl.lecturer_id
                                    JOIN course_enrollment ce ON cl.course_id = ce.course_id
                                    WHERE ce.student_id = ?
                                ";
                                $stmt_lecturers = $con->prepare($sql_lecturers);
                                $stmt_lecturers->bind_param("i", $student_id);
                                $stmt_lecturers->execute();
                                $result_lecturers = $stmt_lecturers->get_result();
                                while ($lecturer = $result_lecturers->fetch_assoc()) {
                                    echo '<option value="' . $lecturer['lecturer_id'] . '">' . htmlspecialchars($lecturer['full_name']) . '</option>';
                                }
                                $stmt_lecturers->close();
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
                </section>
            </div>    
        </section>
                
    
<section id="MyCourses" class="container">
    <h1>My Courses</h1>
    <div class="course-container">
        <?php
        if (!empty($courses)) {
            foreach ($courses as $course) {
                echo '<div class="course-box" onclick="window.location.href=\'assignment_details.php?course_id=' . htmlspecialchars($course['course_id']) . '\'">';
                echo '<h3>' . htmlspecialchars($course['course_name']) . '</h3>';
                echo '<i class="fa-solid fa-graduation-cap"></i><br>';
                echo '<p>Course Code: ' . htmlspecialchars($course['course_code']) . '</p>';
                echo '</div>';
            }
        } else {
            echo '<p>You are not enrolled in any courses yet.</p>';
        }
        ?>
    </div>
</section>

    <h2>Available Courses for Enrollment</h2>
    <form method="POST" action="">
        <select name="course_id" required>
            <option value="">Select a course</option>
            <?php foreach ($available_courses as $course): ?>
                <option value="<?php echo $course['course_id']; ?>">
                    <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="enroll_course" class="btn">Enroll</button>
    </form>
    <?php
    if (isset($enrollment_message)) {
        echo '<p class="success">' . $enrollment_message . '</p>';
    }
    if (isset($enrollment_error)) {
        echo '<p class="error">' . $enrollment_error . '</p>';
    }
    ?>
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
</body>
</html>