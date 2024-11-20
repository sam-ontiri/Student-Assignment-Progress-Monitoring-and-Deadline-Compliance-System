<?
include '../connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="landing_page.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">

</head>
<body>
    <section class="header">
    <nav>
    <div class="logo">
                <i class="fa-solid fa-book-open"></i>
                <span>EduTracker</span>
            </div>
        <div class="nav_links" id="nav_lk,m inks">
            
            <ul>
                <li><a href="#">Home</a></li>
                <li><a href="#">About</a></li>
                <li><a href="#">Offerings</a></li>
                <li><a href="#">Blog</a></li>
                <li><a href="#">Contact</a></li>
            </ul>
            <a href='../login/login.php'><button  class="login_btn">Login</button></a>
        </div>
    </nav>

    <!--home content-->
    <div class="home_box">
        <h2>Streamline Your Academic Progress Today</h2>
        <p>At EduTracker, we empower educators and students to achieve academic success with our intuitive platform.<br> By streamlining assignment management, offering real-time progress tracking, and sending automated reminders,<br> we help students stay organized and accountable.<br>Our mission is to enhance the learning experience, making education more efficient and engaging for everyone. <br>Join us in transforming how students and teachers connect!</p>
    <div class="getstarted_btn">
        <a href='..\login\studentsignup.php' class="btn">Get Started</a>
    </div>   
    </div>
    </section>
    <section class="About">
        <div class="About_home">
        <h2>The Story Behind EduTracker's SUCCESS</h2>
        
        <p>EduTracker was built to help students and educators easily manage assignments and deadlines. <br>Our platform simplifies tracking, boosts productivity, and reduces stress, making academic management straightforward. <br>Driven by a passion for education and technology, we offer tools like <br> ssignment monitoring and deadline alerts, <br>empowering students to stay on track and teachers to support them effectively. <br>Our mission is simple: make academic success achievable for everyone. <br> EduTracker is more than just software it is a smarter way to stay organized and succeed! </p>
        </div>
        
        <a href="" class="Learnbtn">Learn more</a>

    </section>
    <!--offer content-->
    <h2 class="heading_0ffer">What We Offer</h2>
    <section class="Offer_home">
        <div class="column_box">
        <div class="monitoring">
            <h2>01.</h2>
            <h1>Real-Time Monitoring</h1>
            <p>EduTracker’s Real-Time Monitoring gives instant updates on assignment progress, upcoming deadlines, and task completion. This ensures students stay organized, while educators can track progress effortlessly, making it easier to achieve academic goals.</p>
        </div>
        <div class="Notification">
            <h2>01.</h2>
            <h1>Real-Time Monitoring</h1>
            <p>EduTracker’s Real-Time Monitoring gives instant updates on assignment progress, upcoming deadlines, and task completion. This ensures students stay organized, while educators can track progress effortlessly, making it easier to achieve academic goals.</p>
        </div>
        <div class="Insights">
            <h2>01.</h2>
            <h1>Real-Time Monitoring</h1>
            <p>EduTracker’s Real-Time Monitoring gives instant updates on assignment progress, upcoming deadlines, and task completion. This ensures students stay organized, while educators can track progress effortlessly, making it easier to achieve academic goals.</p>
        </div>
        </div>

    </section>
   
    <!--contact content-->
    <section class="contact_home">
        <h2>Contact Us Today</h2>
        <p>Email:
            <a href="mailto:support@edutracker.com">support@edutracker.com</a> <br>
            For general inquiries, system support, or any questions related to EduTracker. <br>
            
            Phone:
            <a href="tel:+254123456789">+254-123-456-789</a> <br>
            Reach us directly for immediate assistance during our support hours.
            
            </p>
    </section>

</body>
</html>