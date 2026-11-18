<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = new mysqli("localhost", "root", "", "car_rental");

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $email = $_POST['email'];
    $password = $_POST['password'];

    // ✅ Adjust column names as per your DB (assuming: id, username, email_id, password, role)
    $sql = "SELECT * FROM users WHERE email_id = '$email' AND password = '$password'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];

        // ✅ Role-based redirection
        if ($row['role'] == 'admin') {
          echo "<script>alert('Welcome Admin');
          window.location.href = 'admin.php';
          </script>";
            exit();
        } elseif ($row['role'] == 'user') {
            header("Location: user.php");
            exit();
        } else {
            echo "<script>alert('Unknown role!');</script>";
        }
    } else {
        echo "<script>alert('Invalid email or password');</script>";
    }

    $conn->close();
}
?>
<html>
<head>
  <title>DriveEase - Login</title>
  <link rel="stylesheet" href="assets/css/login.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .input-box {
      position: relative;
      margin-bottom: 20px;
    }
    
    .input-box input {
      width: 100%;
      padding: 15px;
      padding-right: 45px; /* Space for eye icon */
      border: 2px solid #ddd;
      border-radius: 8px;
      font-size: 16px;
      outline: none;
      transition: border-color 0.3s;
    }
    
    .password-toggle {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #666;
      font-size: 18px;
      transition: color 0.3s;
    }
    
    .password-toggle:hover {
      color: #333;
    }
  </style>
</head>
<body>
  <div class="background-slideshow"></div>

  <div class="login-card">
    <h2>Welcome Back</h2>
    <p>Please login to your account</p>
    <form method="post" action="">
      <div class="input-box">
        <input type="text" placeholder="Username" id="username" name="username" required />
      </div>
      <div class="input-box">
        <input type="email" placeholder="Email" id="email" name="email" required />
      </div>
      <div class="input-box">
        <input type="password" placeholder="Password" id="password" name="password" required />
        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
      </div>
      <button type="submit" id="login">Login</button><br><br>
      <p class="switch-form">
        Don't have an account? 
        <a href="register.php" style="color: rgb(14, 13, 13); font-weight: bold; text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
          Register here
        </a>
      </p>
    </form>
  </div>

  <script>
    // Password visibility toggle
    const togglePassword = document.getElementById('togglePassword');
    const passwordField = document.getElementById('password');

    togglePassword.addEventListener('click', function() {
      // Toggle password visibility
      const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordField.setAttribute('type', type);
      
      // Toggle eye icon
      this.classList.toggle('fa-eye');
      this.classList.toggle('fa-eye-slash');
    });
  </script>
  <script src="login.js"></script>
</body>
</html>