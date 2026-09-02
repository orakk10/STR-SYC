<?php
session_start();


if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role == 'faculty') {
        header("Location: faculty_portal.php");
    } else {
        header("Location: {$role}_dashboard.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STRAND-SYNC | Academic Management System</title>
    <link rel="stylesheet" href="../assets/css/landing.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body>
    <header class="navbar">
        <div class="logo-container">
            <div class="logo">
                <img src="../assets/img/logo_strandsync.png" alt="Strand-Sync Logo" class="nav-logo-img">
                STRAND-<span>SYNC</span>
            </div>
        </div>
        <nav>
            <a href="#features">Features</a>
            <a href="#about">About</a>
            <a href="../login.php" class="btn-nav-login">Login Portal</a>
        </nav>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h1>Syncing Academic <span>Success</span></h1>
            <p>The unified management system engineered for Senior High School strands, dynamic grade monitoring, and streamlined class advisement operations.</p>
            <div class="hero-btns">
                <a href="../login.php" class="btn-primary">Login to Dashboard</a>
                <a href="#features" class="btn-outline">Learn More</a>
            </div>
        </div>
    </section>

    <section id="features" class="features-section">
        <div class="section-header">
            <h2>Platform Features</h2>
            <p>Explore customized capabilities built purposefully for every operational academic role in the Strand-Sync infrastructure.</p>
        </div>
        <div class="features">
            <div class="feature-card">
                <div class="icon-box">📊</div>
                <h3>For Class Advisers</h3>
                <p>Gain real-time oversight of student section rosters, manage dynamic strand distribution maps, and authorize official progress report actions.</p>
            </div>
            <div class="feature-card">
                <div class="icon-box">✏️</div>
                <h3>For Subject Faculty</h3>
                <p>Experience streamlined grade encoding interfaces, simplified individual subject assignments, and completely automated final grade calculations.</p>
            </div>
            <div class="feature-card">
                <div class="icon-box">🎓</div>
                <h3>For Active Students</h3>
                <p>Access your centralized user dashboard instantly to view assigned subjects, track seasonal grade averages, and view published bulletin announcements.</p>
            </div>
        </div>
    </section>

    <section id="about" class="about-section">
        <div class="about-container">
            <div class="about-text">
                <h2>About STRAND-SYNC</h2>
                <p>STRAND-SYNC is an advanced academic tracking environment tailored explicitly to simplify Senior High School tracking workflows. By replacing cumbersome manual record tracking with high-integrity database cross-referencing, we bridge communication gaps between students, instructors, and assigned academic advisers.</p>
                <p>Our goal is to give educational institutions clear oversight into strand performance metrics, help teachers submit grades without technical friction, and keep students aligned with their path to graduation.</p>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <p>&copy; 2026 STRAND-SYNC Infrastructure. All rights reserved. Built for unified academic success.</p>
    </footer>
</body>

</html>