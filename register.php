<?php
$conn = new mysqli("localhost", "root", "", "car_rental");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// AJAX request to check username availability
if (isset($_GET['check_username']) && isset($_GET['username'])) {
    $username = $_GET['username'];
    $sql = "SELECT username FROM users WHERE username = '$username'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        echo "exists";
    } else {
        echo "available";
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirmpassword = $_POST['confirmpassword'];
    $role = 'user';

    // Server-side validation: Check if passwords match
    if ($password !== $confirmpassword) {
        echo "<script>alert('Passwords do not match!');</script>";
    } else {
        // Only insert username, email, password, and role (removed confirm_password column)
        $sql = "INSERT INTO users (username, email_id, password, role) 
                VALUES ('$username', '$email', '$password', '$role')";

        if ($conn->query($sql) === TRUE) {
            echo "<script>
                    window.location.href = 'success.html';
                  </script>";
        } else {
            echo "<script>alert('Error occurred: " . $conn->error . "');</script>";
        }
    }
}

$conn->close();
?>

<html>
<head>
  <title>DriveEase - Signup</title>
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
    
    .input-box input:focus {
      border-color: #4a90e2;
    }
    
    .input-box input.valid {
      border-color: #4CAF50;
    }
    
    .input-box input.invalid {
      border-color: #f44336;
    }
    
    .input-box input.checking {
      border-color: #FFA500;
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
    
    .validation-message {
      font-size: 14px;
      margin-top: 5px;
      display: block;
    }
    
    .valid-message {
      color: #4CAF50;
      font-weight: bold;
    }
    
    .invalid-message {
      color: #f44336;
      font-weight: bold;
    }
    
    .checking-message {
      color: #FFA500;
      font-weight: bold;
    }
    
    button:disabled {
      background-color: #cccccc;
      cursor: not-allowed;
    }
    
    .loading-spinner {
      display: inline-block;
      width: 12px;
      height: 12px;
      border: 2px solid #f3f3f3;
      border-top: 2px solid #3498db;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin-left: 5px;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
  <div class="background-slideshow"></div>

  <div class="login-card">
    <h2>Create Account</h2>
    <p>Sign up to get started</p>
    <form onsubmit="return validateForm()" method="POST">
      <div class="input-box">
        <label for="username"></label>
        <input type="text" id="username" name="username" placeholder="Username" required />
        <span id="usernameMessage" class="validation-message"></span>
      </div>
      <div class="input-box">
        <label for="email"></label>
        <input type="email" id="email" name="email" placeholder="Email" required />
        <span id="emailMessage" class="validation-message"></span>
      </div>
      <div class="input-box">
        <label for="password"></label>
        <input type="password" id="password" name="password" placeholder="Password" required />
        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
        <span id="passwordMessage" class="validation-message"></span>
      </div>
      <div class="input-box">
        <label for="confirmpassword"></label>
        <input type="password" id="confirmpassword" name="confirmpassword" placeholder="Confirm Password" required />
        <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
        <span id="confirmPasswordMessage" class="validation-message"></span>
      </div>
      <button type="submit" id="signup">Sign Up</button><br><br>
      <p class="switch-form">
        Already have an account? 
        <a href="login.php" style="color: rgb(14, 13, 13); font-weight: bold; text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
          Login here
        </a>
      </p>
    </form>
  </div>

  <script>
    // Get form elements
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmpassword');
    const emailInput = document.getElementById('email');
    const signupButton = document.getElementById('signup');
    
    // Get message elements
    const usernameMessage = document.getElementById('usernameMessage');
    const passwordMessage = document.getElementById('passwordMessage');
    const confirmPasswordMessage = document.getElementById('confirmPasswordMessage');
    const emailMessage = document.getElementById('emailMessage');

    // Variables for username validation
    let usernameValid = false;
    let usernameAvailable = false;
    let usernameTimeout = null;

    // Username validation with AJAX
    usernameInput.addEventListener('input', function() {
      const username = this.value.trim();
      const usernameRegex = /^[a-zA-Z0-9]{3,10}$/;
      
      // Clear previous timeout
      if (usernameTimeout) {
        clearTimeout(usernameTimeout);
      }
      
      if (username.length === 0) {
        usernameMessage.textContent = '';
        this.classList.remove('valid', 'invalid', 'checking');
        usernameValid = false;
        usernameAvailable = false;
      } else if (!usernameRegex.test(username)) {
        usernameMessage.textContent = 'Username must be 3-10 characters and contain only letters and numbers';
        usernameMessage.className = 'validation-message invalid-message';
        this.classList.add('invalid');
        this.classList.remove('valid', 'checking');
        usernameValid = false;
        usernameAvailable = false;
      } else {
        // Valid format, check availability
        usernameValid = true;
        this.classList.add('checking');
        this.classList.remove('valid', 'invalid');
        usernameMessage.innerHTML = 'Checking availability... <span class="loading-spinner"></span>';
        usernameMessage.className = 'validation-message checking-message';
        
        // Set timeout to avoid too many requests
        usernameTimeout = setTimeout(() => {
          checkUsernameAvailability(username);
        }, 500);
      }
      validateForm();
    });

    // AJAX function to check username availability
    function checkUsernameAvailability(username) {
      const xhr = new XMLHttpRequest();
      xhr.open('GET', `register.php?check_username=1&username=${encodeURIComponent(username)}`, true);
      
      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
          if (xhr.status === 200) {
            if (xhr.responseText === 'available') {
              usernameMessage.textContent = 'Username is available!';
              usernameMessage.className = 'validation-message valid-message';
              usernameInput.classList.add('valid');
              usernameInput.classList.remove('invalid', 'checking');
              usernameAvailable = true;
            } else {
              usernameMessage.textContent = 'Username already exists. Please choose another.';
              usernameMessage.className = 'validation-message invalid-message';
              usernameInput.classList.add('invalid');
              usernameInput.classList.remove('valid', 'checking');
              usernameAvailable = false;
            }
            validateForm();
          } else {
            usernameMessage.textContent = 'Error checking username availability';
            usernameMessage.className = 'validation-message invalid-message';
            usernameInput.classList.add('invalid');
            usernameInput.classList.remove('valid', 'checking');
            usernameAvailable = false;
            validateForm();
          }
        }
      };
      
      xhr.send();
    }

    // Password validation (6-10 characters)
    passwordInput.addEventListener('input', function() {
      const password = this.value;
      
      if (password.length === 0) {
        passwordMessage.textContent = '';
        this.classList.remove('valid', 'invalid');
      } else if (password.length >= 6 && password.length <= 10) {
        passwordMessage.textContent = 'Password is valid';
        passwordMessage.className = 'validation-message valid-message';
        this.classList.add('valid');
        this.classList.remove('invalid');
      } else if (password.length < 6) {
        passwordMessage.textContent = 'Password must be at least 6 characters';
        passwordMessage.className = 'validation-message invalid-message';
        this.classList.add('invalid');
        this.classList.remove('valid');
      } else {
        passwordMessage.textContent = 'Password must be maximum 10 characters';
        passwordMessage.className = 'validation-message invalid-message';
        this.classList.add('invalid');
        this.classList.remove('valid');
      }
      
      // Also validate confirm password when password changes
      validateConfirmPassword();
      validateForm();
    });

    // Confirm password validation
    confirmPasswordInput.addEventListener('input', validateConfirmPassword);

    function validateConfirmPassword() {
      const password = passwordInput.value;
      const confirmPassword = confirmPasswordInput.value;
      
      if (confirmPassword.length === 0) {
        confirmPasswordMessage.textContent = '';
        confirmPasswordInput.classList.remove('valid', 'invalid');
      } else if (password === confirmPassword && password.length >= 6 && password.length <= 10) {
        confirmPasswordMessage.textContent = 'Passwords match';
        confirmPasswordMessage.className = 'validation-message valid-message';
        confirmPasswordInput.classList.add('valid');
        confirmPasswordInput.classList.remove('invalid');
      } else {
        confirmPasswordMessage.textContent = 'Passwords do not match';
        confirmPasswordMessage.className = 'validation-message invalid-message';
        confirmPasswordInput.classList.add('invalid');
        confirmPasswordInput.classList.remove('valid');
      }
      validateForm();
    }

    // Email validation
    emailInput.addEventListener('input', function() {
      const email = this.value;
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      
      if (email.length === 0) {
        emailMessage.textContent = '';
        this.classList.remove('valid', 'invalid');
      } else if (emailRegex.test(email)) {
        emailMessage.textContent = 'Email is valid';
        emailMessage.className = 'validation-message valid-message';
        this.classList.add('valid');
        this.classList.remove('invalid');
      } else {
        emailMessage.textContent = 'Please enter a valid email address';
        emailMessage.className = 'validation-message invalid-message';
        this.classList.add('invalid');
        this.classList.remove('valid');
      }
      validateForm();
    });

    // Form validation function
    function validateForm() {
      const emailValid = emailInput.classList.contains('valid');
      const passwordValid = passwordInput.classList.contains('valid');
      const confirmPasswordValid = confirmPasswordInput.classList.contains('valid');
      
      // Username is valid if it passes format check AND is available
      const isUsernameValid = usernameValid && usernameAvailable;
      
      if (isUsernameValid && emailValid && passwordValid && confirmPasswordValid) {
        signupButton.disabled = false;
        return true;
      } else {
        signupButton.disabled = true;
        return false;
      }
    }

    // Password visibility toggle for password field
    const togglePassword = document.getElementById('togglePassword');
    const passwordField = document.getElementById('password');

    togglePassword.addEventListener('click', function() {
      const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordField.setAttribute('type', type);
      
      this.classList.toggle('fa-eye');
      this.classList.toggle('fa-eye-slash');
    });

    // Password visibility toggle for confirm password field
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const confirmPasswordField = document.getElementById('confirmpassword');

    toggleConfirmPassword.addEventListener('click', function() {
      const type = confirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
      confirmPasswordField.setAttribute('type', type);
      
      this.classList.toggle('fa-eye');
      this.classList.toggle('fa-eye-slash');
    });

    // Initialize form validation on page load
    validateForm();
  </script>
</body>
</html>