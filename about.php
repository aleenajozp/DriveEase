<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DriveEase - AboutUs</title>
  <link rel="stylesheet" href="assets/css/about.css">
  <link rel="stylesheet" href="assets/css/index.css">
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <link href="assets/css/navbar.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      overflow-x: hidden;
      background-color: #f3f6fa;
      padding-top: 100px; /* Prevents content hiding behind fixed navbar */
    }

    /* Navbar Styles */
    .navbar {
      background-color: #000;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 60px;
      position: fixed;
      width: 100%;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 10px rgba(0,0,0,0.6);
    }

    .logo {
      font-size: 28px;
      font-weight: 700;
    }

    .logo span:nth-child(1) {
      color: white;
    }

    .logo span:nth-child(2) {
      color: #f24822;
    }

    .nav-links {
      display: flex;
      gap: 25px;
      align-items: center;
    }

    .nav-links a {
      color: white;
      text-decoration: none;
      font-size: 16px;
      font-weight: 500;
      padding: 10px 18px;
      border-radius: 25px;
      transition: all 0.3s ease;
    }

    .nav-links a:hover {
      background-color: #f24822;
      color: white;
    }

    /* Sign In Button */
    .signin-btn {
      background-color: transparent;
      border: 2px solid #f24822;
      color: #f24822;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .signin-btn:hover {
      background-color: #f24822;
      color: white;
    }
  </style>
</head>
<body>
    
  <!-- Navbar -->
  <header class="navbar">
    <div class="logo">
      <span>DRIVE</span><span>Ease</span>
    </div>
    <nav class="nav-links">
      <a href="1.php">Home</a>
      <a href="models.php">Models</a>
      <a href="about.php">About</a>
      <a href="contact.php">Contact</a>
      <a href="login.php" class="signin-btn">Sign In</a>
    </nav>
  </header>

  <!-- Hero / Welcome Section -->
  <section class="about-section">
    <div class="about-content">
      <div class="text-box" data-aos="fade-right">
        <h1>Welcome to DriveEase!</h1>
        <p>At DriveEase, we’re dedicated to making car rentals convenient, reliable, and affordable for everyone. 
         Whether you're a tourist exploring new places or a local in need of quick transportation, our fleet and services are designed to match your needs with ease and comfort.</p>
        <a href="#more" class="button">Learn More</a>
      </div>
      <div class="image-box" data-aos="fade-left">
        <img src="assets/images/carr.jpeg" alt="About DriveEase">
      </div>
    </div>
  </section>

  <!-- Who We Are -->
  <section id="more" class="who-we-are" data-aos="fade-up">
    <h2>Who We Are</h2>
    <p>DriveEase was founded with one goal in mind – to make your travel hassle-free. From compact city cars to spacious SUVs, we provide a variety of well-maintained vehicles that fit your lifestyle and budget.</p>
  </section>

  <!-- Mission & Vision -->
  <section class="mission-vision">
    <div class="mission" data-aos="fade-right">
      <h3>Our Mission</h3>
      <p>To redefine the car rental experience with exceptional service, transparency, and comfort at every step of your journey.</p>
    </div>
    <div class="vision" data-aos="fade-left">
      <h3>Our Vision</h3>
      <p>To be the most trusted car rental company nationwide, recognized for our innovation, customer care, and unbeatable value.</p>
    </div>
  </section>

  <!-- Why Choose Us -->
  <section class="why-choose-us">
    <h2 data-aos="fade-up">Why Choose DriveEase?</h2>
    <div class="reasons">
      <div class="reason" data-aos="zoom-in" data-aos-delay="100">
        <h4>🚗 Wide Vehicle Selection</h4>
        <p>From budget-friendly hatchbacks to luxury sedans, we’ve got the perfect ride for you.</p>
      </div>
      <div class="reason" data-aos="zoom-in" data-aos-delay="200">
        <h4>💰 Affordable Rates</h4>
        <p>We believe quality doesn’t have to come with a high price tag.</p>
      </div>
      <div class="reason" data-aos="zoom-in" data-aos-delay="300">
        <h4>⏰ 24/7 Customer Support</h4>
        <p>Our dedicated team is here to assist you anytime, anywhere.</p>
      </div>
    </div>
  </section>

  <!-- Meet the Team -->
  <section class="team">
    <h2 data-aos="fade-up">Meet Our Team</h2>
    <div class="team-members">
      <div class="member" data-aos="flip-left">
        <img src="assets/images/aleena.jpg" alt="CEO">
        <h4>Aleena Joseph</h4>
        <p>Founder & CEO</p>
      </div>
      <div class="member" data-aos="flip-left" data-aos-delay="200">
        <img src="assets/images/alvita.jpg" alt="CEO">
        <h4>Alvita Johny</h4>
        <p>Founder & CEO</p>
      </div>
    </div>
  </section>

  <!-- Call to Action -->
  <section class="cta" data-aos="fade-up">
    <h2>Ready to Hit the Road?</h2>
    <p>Book your next ride with DriveEase today and experience travel like never before!</p><br>
    <a href="models.php" class="button">Book Now</a>
  </section>

  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script src="assets/js/about.js"></script>
</body>
</html>
