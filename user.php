<?php
// Start session
session_start();

// Database connection and user data fetch
$profile_picture = 'assets/images/default-profile.jpg'; // Default profile picture

// Initialize feedback array
$feedbacks = [];

if (isset($_SESSION['username'])) {
    $host = "localhost";
    $dbname = "car_rental";
    $dbuser = "root";
    $dbpass = "";

    $conn = new mysqli($host, $dbuser, $dbpass, $dbname);
    if (!$conn->connect_error) {
        $username = $_SESSION['username'];
        $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            if (!empty($user_data['profile_picture']) && file_exists($user_data['profile_picture'])) {
                $profile_picture = $user_data['profile_picture'];
            }
        }
        
        // Fetch feedbacks from database
        $feedback_query = "SELECT f.*, u.username, u.profile_picture 
                          FROM feedback f 
                          JOIN users u ON f.user_id = u.user_id 
                          ORDER BY f.created_at DESC";
        $feedback_result = $conn->query($feedback_query);
        
        if ($feedback_result && $feedback_result->num_rows > 0) {
            while ($row = $feedback_result->fetch_assoc()) {
                $feedbacks[] = $row;
            }
        }
        
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>DriveEase - User Dashboard</title>
  <link rel="stylesheet" href="assets/css/index.css">
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
    }

    /* Navbar Styles */
    .navbar {
      background-color: black;
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

    /* Logout styled like Sign In */
    .logout-btn {
      background-color: transparent;
      border: 2px solid #f24822;
      color: #f24822 !important;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .logout-btn:hover {
      background-color: #f24822;
      color: white !important;
    }

    /* Profile picture */
    .profile-link {
      display: flex;
      align-items: center;
      text-decoration: none;
      position: relative;
      margin-left: 10px;
    }

    .profile-pic-navbar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #f24822;
    }

    /* Tooltip */
    .profile-link::after {
      content: 'My Profile';
      position: absolute;
      bottom: -30px;
      right: 0;
      background: #333;
      color: white;
      padding: 5px 8px;
      border-radius: 4px;
      font-size: 12px;
      white-space: nowrap;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s ease;
      z-index: 1000;
    }

    .profile-link:hover::after {
      opacity: 1;
      visibility: visible;
    }

    /* Hero Section */
    .hero {
      width: 100%;
      height: 100vh;
      display: flex;
      align-items: center;
      padding-left: 60px;
      background: url("assets/images/car1.jpeg") no-repeat center center/cover;
      position: relative;
      margin-top: 80px;
    }

    .hero::after {
      content: "";
      position: absolute;
      inset: 0;
      background-color: rgba(0, 0, 0, 0.4);
    }

    .hero-text {
      position: relative;
      color: white;
      z-index: 2;
      max-width: 600px;
    }

    .hero-text h1 {
      font-size: 64px;
      font-weight: 700;
      line-height: 1.2;
      margin-bottom: 20px;
    }

    .hero-text p {
      font-size: 16px;
      font-weight: 300;
      margin-bottom: 30px;
      line-height: 1.6;
    }

    .btn {
      background: #f24822;
      color: #fff;
      padding: 12px 30px;
      border-radius: 30px;
      text-decoration: none;
      font-weight: 500;
      transition: background 0.3s;
    }

    .btn:hover {
      background: #c53b1c;
    }

    /* About Us Section */
    .about-section {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 60px 20px;
      background-color: #ffffff;
    }

    .about-content {
      display: flex;
      max-width: 1000px;
      background: #fff;
      box-shadow: 0 10px 30px rgba(0,0,0,0.1);
      border-radius: 10px;
      overflow: hidden;
    }

    .text-box {
      flex: 1;
      padding: 50px;
      background-color: #f5f5f5;
    }

    .text-box h1 {
      background-color: #d35400;
      color: #fff;
      padding: 10px 20px;
      display: inline-block;
      font-size: 32px;
      margin-bottom: 20px;
    }

    .text-box p {
      font-size: 16px;
      color: #444;
      margin-bottom: 30px;
      line-height: 1.6;
    }

    .text-box a.button {
      display: inline-block;
      background-color: #000;
      color: #fff;
      padding: 10px 20px;
      text-decoration: none;
      font-weight: 500;
      border-radius: 4px;
      transition: 0.3s ease;
    }

    .text-box a.button:hover {
      background-color: #333;
    }

    .image-box {
      flex: 1;
      background-color: #ccc;
    }

    .image-box img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    /* Feedback Section - Updated to match 1.php */
    .feedback-section {
      padding: 60px 20px;
      background: #fff;
      text-align: center;
      position: relative;
    }

    .section-title h2 {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 40px;
      color: #222;
    }

    .feedback-slide {
      display: none;
    }

    .feedback-slide.active {
      display: block;
      animation: fade 1s;
    }

    @keyframes fade {
      from {opacity: 0.3;}
      to {opacity: 1;}
    }

    .feedback-profile {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #f24822;
      margin-bottom: 20px;
    }

    .feedback-message {
      font-size: 18px;
      font-style: italic;
      color: #555;
      margin-bottom: 15px;
    }

    .feedback-author {
      font-weight: 700;
      font-size: 16px;
      margin-bottom: 10px;
      color: #333;
    }

    .feedback-rating i {
      color: #f4c150;
      margin: 0 2px;
    }

    .no-feedback {
      text-align: center;
      color: #555;
    }

    .no-feedback i {
      font-size: 48px;
      color: #f24822;
      margin-bottom: 15px;
    }

    .no-feedback h3 {
      font-size: 22px;
      margin-bottom: 10px;
    }

    .no-feedback p {
      margin-bottom: 20px;
    }

    .no-feedback .btn {
      display: inline-block;
      background: #f24822;
      color: #fff;
      padding: 10px 20px;
      border-radius: 6px;
      text-decoration: none;
      transition: 0.3s;
    }

    .no-feedback .btn:hover {
      background: #d93e1d;
    }

    /* Footer */
    footer {
      background-color: #1f3d3d;
      color: #fff;
      padding: 60px 20px 20px 20px;
      margin-top: 50px;
      position: relative;
      border-top-left-radius: 20px;
      border-top-right-radius: 20px;
    }

    footer a {
      color: #fff;
      text-decoration: none;
      margin-bottom: 10px;
      display: block;
      font-weight: 400;
    }

    footer a:hover {
      color: #f24822;
    }

    .footer-container {
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      max-width: 1200px;
      margin: 0 auto;
    }

    .footer-left, .footer-links {
      margin-bottom: 20px;
    }

    .footer-left p {
      max-width: 300px;
      margin-bottom: 20px;
    }

    .social-icons a {
      margin-right: 10px;
      font-size: 20px;
      display: inline-block;
      color: #fff;
      transition: 0.3s;
    }

    .social-icons a:hover {
      color: #f24822;
    }

    .back-to-top {
      margin-top: 20px;
      display: inline-block;
      padding: 8px 15px;
      border: 1px solid #fff;
      border-radius: 5px;
      font-size: 14px;
      transition: 0.3s;
    }

    .back-to-top:hover {
      background-color: #f24822;
      border-color: #f24822;
      color: #fff;
    }

    .footer-bottom {
      text-align: center;
      margin-top: 30px;
      font-size: 14px;
      color: #ccc;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .about-content {
        flex-direction: column;
      }

      .text-box, .image-box {
        width: 100%;
      }

      .text-box {
        padding: 30px;
      }

      .navbar {
        padding: 15px 20px;
      }

      .nav-links {
        gap: 10px;
      }

      .nav-links a {
        padding: 8px 12px;
        font-size: 14px;
      }

      .hero {
        padding-left: 20px;
      }

      .hero-text h1 {
        font-size: 36px;
      }
    }

    @media (max-width: 480px) {
      .feedback-profile {
        width: 80px;
        height: 80px;
      }

      .feedback-message {
        font-size: 16px;
      }
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
      <a href="user.php">Home</a>
      <a href="usermodels.php">Models</a>
      <a href="userabout.php">About</a>
      <a href="usercontact.php">Contact</a>
      <a href="logout.php" class="logout-btn">Logout</a>
      <a href="userprofile.php" class="profile-link">
        <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
             alt="Profile Picture" 
             class="profile-pic-navbar"
             onerror="this.src='assets/images/default-profile.jpg';">
      </a>
    </nav>
  </header>

  <!-- Hero Section -->
  <section class="hero">
    <div class="hero-text">
      <h1>Experience<br>Automotive<br>Excellence</h1>
      <p>Discover our collection of premium vehicles crafted with precision engineering and timeless design. Elevate your driving experience today.</p>
      <a href="usermodels.php" class="btn">Explore Models</a>
    </div>
  </section>

  <!-- About Us Section -->
  <section class="about-section">
    <div class="about-content">
      <div class="text-box">
        <h1>Welcome!</h1>
        <p>At DriveEase, we're dedicated to making car rentals convenient, reliable, and affordable for everyone. 
         Whether you're a tourist exploring new places or a local in need of quick transportation, our fleet and services are designed to match your needs with ease and comfort.</p>
        <a href="userabout.php" class="button">READ MORE</a>
      </div>
      <div class="image-box">
        <img src="assets/images/carr.jpeg" alt="About Image">
      </div>
    </div>
  </section>

  <!-- Feedback Section - Updated to match 1.php -->
  <section class="feedback-section">
    <div class="section-title">
      <h2>Happy Customers</h2>
    </div>

    <?php if (!empty($feedbacks)): ?>
      <?php foreach ($feedbacks as $index => $feedback): ?>
        <div class="feedback-slide <?php echo $index === 0 ? 'active' : ''; ?>">
          <img src="<?php echo !empty($feedback['profile_picture']) ? htmlspecialchars($feedback['profile_picture']) : 'assets/images/default-profile.jpg'; ?>" 
               alt="<?php echo htmlspecialchars($feedback['username']); ?>" 
               class="feedback-profile"
               onerror="this.src='assets/images/default-profile.jpg';">
          <p class="feedback-message">"<?php echo htmlspecialchars($feedback['message']); ?>"</p>
          <p class="feedback-author">— <?php echo htmlspecialchars($feedback['username']); ?></p>
          <div class="feedback-rating">
            <?php 
              if (!empty($feedback['rating'])) {
                $rating = intval($feedback['rating']);
                for ($i=1; $i<=5; $i++) {
                  echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                }
              }
            ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="no-feedback">
        <i class="fas fa-comments"></i>
        <h3>No Feedback Yet</h3>
        <p>Be the first to share your experience with DriveEase!</p>
        <a href="userprofile.php?tab=feedback" class="btn">Share Feedback</a>
      </div>
    <?php endif; ?>
  </section>

  <!-- Footer Section -->
  <footer>
    <div class="footer-container">
      <div class="footer-left">
        <h2>DRIVE<span class="highlight">Ease</span></h2>
        <p>Providing premium car rentals with reliability and comfort, making every journey memorable for our clients.</p>
        <div class="social-icons">
          <a href="#"><i class="fab fa-x"></i></a>
          <a href="#"><i class="fab fa-linkedin"></i></a>
          <a href="#"><i class="fab fa-instagram"></i></a>
          <a href="#"><i class="fab fa-facebook"></i></a>
        </div>
        <a href="#top" class="back-to-top">BACK TO TOP</a>
      </div>

      <div class="footer-links">
        <h3>Site Map</h3><br>
        <a href="#">Homepage</a>
        <a href="usermodels.php">Models</a>
        <a href="userabout.php">About</a>
        <a href="usercontact.php">Contact</a>
      </div>

      <div class="footer-links">
        <h3>Legal</h3>
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
      </div>
    </div>
    <div class="footer-bottom">
      &copy; 2025 DriveEase. All Rights Reserved.
    </div>
  </footer>

  <script>
    <?php if (!empty($feedbacks) && count($feedbacks) > 1): ?>
    let slideIndex = 0;
    showSlides();

    function showSlides() {
      let slides = document.getElementsByClassName("feedback-slide");
      for (let i = 0; i < slides.length; i++) {
        slides[i].classList.remove("active");
      }
      slideIndex++;
      if (slideIndex > slides.length) { slideIndex = 1 }
      slides[slideIndex-1].classList.add("active");
      setTimeout(showSlides, 5000); // Change slide every 5 seconds
    }
    <?php endif; ?>
  </script>

</body>
</html>