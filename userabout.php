<?php
session_start();

// Default profile picture
$profile_picture = 'assets/images/default-profile.jpg';

// Fetch user profile picture if logged in
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
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DriveEase - AboutUs</title>
  <link rel="stylesheet" href="assets/css/about.css">
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
    body { overflow-x: hidden; background-color: #f3f6fa; }

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
    .logo { font-size: 28px; font-weight: 700; }
    .logo span:nth-child(1) { color: white; }
    .logo span:nth-child(2) { color: #f24822; }

    .nav-links { display: flex; gap: 25px; align-items: center; }
    .nav-links a {
      color: white; text-decoration: none; font-size: 16px; font-weight: 500;
      padding: 10px 18px; border-radius: 25px; transition: all 0.3s ease;
    }
    .nav-links a:hover { background-color: #f24822; color: white; }

    .logout-btn {
      background-color: transparent;
      border: 2px solid #f24822;
      color: #f24822 !important;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    .logout-btn:hover { background-color: #f24822; color: white !important; }

    .profile-link { display: flex; align-items: center; text-decoration: none; position: relative; margin-left: 10px; }
    .profile-pic-navbar {
      width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
      border: 2px solid #f24822;
    }
    .profile-link::after {
      content: 'My Profile';
      position: absolute;
      bottom: -30px; right: 0;
      background: #333; color: white;
      padding: 5px 8px; border-radius: 4px; font-size: 12px;
      white-space: nowrap; opacity: 0; visibility: hidden;
      transition: opacity 0.3s ease; z-index: 1000;
    }
    .profile-link:hover::after { opacity: 1; visibility: visible; }

    /* Padding for fixed navbar */
    body { padding-top: 90px; }
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
    <a href="usermodels.php" class="button">Book Now</a>
  </section>

  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script>AOS.init();</script>
</body>
</html>
