<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>DriveEase - ContactUs</title>
  <link rel="stylesheet" href="assets/css/contact.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">

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

    /* Navbar */
    .navbar {
      background-color: #000;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 60px;
      position: fixed;
      width: 100%;
      top: 0;
      z-index: 1000;
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

    /* Remove white space - contact section starts right under navbar */
    .contact-section {
      padding-top: 80px; /* Only enough padding to clear the navbar */
    }

    /* Popup Modal */
    .modal {
      display: none;
      position: fixed;
      z-index: 9999;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.6);
      justify-content: center;
      align-items: center;
    }
    .modal-content {
      background: #fff;
      padding: 30px;
      border-radius: 12px;
      text-align: center;
      max-width: 400px;
      width: 90%;
    }
    .modal-content h3 {
      margin-bottom: 15px;
      color: #4CAF50;
    }
    .close-btn {
      margin-top: 15px;
      padding: 10px 20px;
      background: #4CAF50;
      color: #fff;
      border: none;
      border-radius: 8px;
      cursor: pointer;
    }
    .map {
      margin-top: 30px;
      text-align: center;
    }
    .map iframe {
      width: 100%;
      height: 300px;
      border: none;
      border-radius: 12px;
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
      <a href="contact.html">Contact</a>
      <a href="login.php" class="signin-btn">Sign In</a>
    </nav>
  </header>

  <!-- Contact Form -->
  <section class="contact-section" style="background-image: url('assets/images/car1.jpeg');">
    <h2>Get in touch</h2>
    <p class="sub-text">Email us with any questions or inquiries <br> or use our contact data.</p>

    <form class="contact-form" id="form" action="https://api.web3forms.com/submit" method="POST">
      <input type="hidden" name="access_key" value="1ed7f051-2dfd-478a-932b-35e5b2ea1923">
      <input type="hidden" name="subject" value="New Contact Form Submission">
      <input type="checkbox" name="botcheck" style="display: none;">

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
    <a href="models.php" class="btn-cta">GET STARTED</a>
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

    <!-- Google Map -->
    <div class="map">
      <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3929.2581828869397!2d76.9641!3d9.5730!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3b07b0dd0df2b1ef%3A0x4d46f6cdbde9ab0b!2sKuttikkanam!5e0!3m2!1sen!2sin!4v1692345678901" allowfullscreen="" loading="lazy"></iframe>
    </div>
  </footer>

  <!-- Success Modal -->
  <div class="modal" id="successModal">
    <div class="modal-content">
      <h3>✅ Thank You!</h3>
      <p>Your message has been sent successfully. We will get back to you soon.</p>
      <button class="close-btn" onclick="closeModal()">Close</button>
    </div>
  </div>

  <script>
    const form = document.getElementById("form");
    const result = document.getElementById("result");
    const modal = document.getElementById("successModal");

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
          result.innerHTML = "";
          modal.style.display = "flex"; // show modal
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

    function closeModal() {
      modal.style.display = "none";
    }
  </script>
</body>
</html>