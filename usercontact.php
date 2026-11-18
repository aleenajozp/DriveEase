<?php
// Start session
session_start();

// Database connection and user data fetch
$profile_picture = 'assets/images/default-profile.jpg'; // Default profile picture

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
<html>
<head>
  <title>DriveEase - ContactUs</title>
  <link rel="stylesheet" href="assets/css/contact.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">

  <style>
    /* Reset + base */
    * {
      margin: 0; padding: 0; box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }
    body {
      overflow-x: hidden;
      background-color: #f3f6fa;
    }

    /* Navbar */
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
    .logo span:nth-child(1) { color: white; }
    .logo span:nth-child(2) { color: #f24822; }

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

    .profile-link {
      display: flex;
      align-items: center;
      text-decoration: none;
      position: relative;
      margin-left: 10px;
    }
    .profile-pic-navbar {
      width: 40px; height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #f24822;
    }
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
    }
    .profile-link:hover::after {
      opacity: 1;
      visibility: visible;
    }

    /* Push content below navbar */
    .contact-section { margin-top: 100px; }
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

  <!-- Contact Form -->
  <section class="contact-section" style="background-image: url('assets/images/car1.jpeg');">
    <h2>Get in touch</h2>
    <p class="sub-text">Email us with any questions or inquiries <br> or use our contact data.</p>

    <form class="contact-form" id="form" action="https://api.web3forms.com/submit" method="POST">
      <input type="hidden" name="access_key" value="1ed7f051-2dfd-478a-932b-35e5b2ea1923">
      <input type="hidden" name="subject" value="New Contact Form Submission">
      <input type="checkbox" name="botcheck" style="display:none;">

      <div class="form-row">
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" placeholder="Enter a valid email address" 
                 pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" 
                 title="Please enter a valid email address (e.g., user@example.com)" required>
        </div>
        <div class="form-group">
          <label>Name</label>
          <input type="text" name="name" placeholder="Enter your name" 
                 maxlength="15" 
                 pattern="[A-Za-z\s]+" 
                 title="Name should only contain letters and spaces (max 15 characters)" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Phone</label>
          <input type="tel" name="phone" placeholder="Enter your 10-digit phone" 
                 pattern="[0-9]{10}" 
                 maxlength="10" 
                 title="Please enter exactly 10 digits">
        </div>
        <div class="form-group">
          <label>Address</label>
          <input type="text" name="address" placeholder="Enter your address">
        </div>
      </div>

      <div class="form-group full">
        <label>Message</label>
        <textarea name="message" placeholder="Enter your message" required></textarea>
      </div>

      <button type="submit">SUBMIT</button>
      <p id="result" class="form-result"></p>
    </form>
  </section>

  <!-- Call to Action -->
  <section class="cta">
    <h2>Want to rent a car with DriveEase?</h2>
    <p>Our team is always ready to provide friendly and reliable support to get you on the road with ease.</p>
    <a href="usermodels.php" class="btn-cta">GET STARTED</a>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-columns">
      <div>
        <h4>Location:</h4>
        <p>Kuttikkanam Road<br>Kuttikkanam DriveEase</p>
      </div>
      <div>
        <h4>Contacts:</h4>
        <p>driveeaseee@gmail.com<br>8848388378</p>
      </div>
      <div>
        <h4>Follow Us:</h4>
        <p>
          <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i> Facebook</a><br>
          <a href="https://www.instagram.com/" class="social-icon"><i class="fab fa-instagram"></i> Instagram</a>
        </p>
      </div>
    </div>
  </footer>

  <script>
    const form = document.getElementById("form");
    const result = document.getElementById("result");

    form.addEventListener("submit", function (e) {
      const formData = new FormData(form);
      e.preventDefault();
      var object = {};
      formData.forEach((value, key) => { object[key] = value; });
      var json = JSON.stringify(object);
      result.innerHTML = "Please wait...";
      result.style.color = "#666";

      fetch("https://api.web3forms.com/submit", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: json
      })
      .then(async (response) => {
        let json = await response.json();
        if (response.status == 200) {
          result.innerHTML = json.message;
          result.style.color = "#4CAF50";
        } else {
          result.innerHTML = json.message;
          result.style.color = "#F44336";
        }
      })
      .catch(() => {
        result.innerHTML = "Something went wrong!";
        result.style.color = "#F44336";
      })
      .then(() => {
        form.reset();
        setTimeout(() => { result.style.display = "none"; }, 5000);
      });
    });
  </script>
</body>
</html>